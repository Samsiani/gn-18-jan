<?php
/**
 * REST API — Dashboard, Accountant & Settings Endpoints
 *
 * GET  /cig/v1/dashboard/kpis                          — 4-query stats summary
 * GET  /cig/v1/dashboard/expiring-reservations          — items expiring within 3 days
 * GET  /cig/v1/dashboard/accountant-invoices            — accountant-filtered paginated list
 * PATCH /cig/v1/invoices/{id}/accountant-status         — toggle boolean accountant columns
 * PATCH /cig/v1/invoices/{id}/accountant-note           — update accountant_note
 * GET  /cig/v1/settings/company                        — read cig_settings from wp_options
 * PUT  /cig/v1/settings/company                        — save cig_settings to wp_options
 *
 * @package CIG
 * @since 5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CIG_Rest_Dashboard {

    const NAMESPACE = 'cig/v1';

    /** Map of camelCase field names from Vue → snake_case DB columns */
    private const ACCOUNTANT_FIELDS = [
        'isRsUploaded'    => 'is_rs_uploaded',
        'isCreditChecked' => 'is_credit_checked',
        'isReceiptChecked'=> 'is_receipt_checked',
        'isCorrected'     => 'is_corrected',
    ];

    private $t_invoices;
    private $t_items;
    private $t_payments;
    private $t_customers;

    public function __construct() {
        global $wpdb;
        $this->t_invoices  = $wpdb->prefix . 'cig_invoices';
        $this->t_items     = $wpdb->prefix . 'cig_invoice_items';
        $this->t_payments  = $wpdb->prefix . 'cig_payments';
        $this->t_customers = $wpdb->prefix . 'cig_customers';

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    // -------------------------------------------------------------------------
    // Route registration
    // -------------------------------------------------------------------------

    public function register_routes() {

        register_rest_route( self::NAMESPACE, '/dashboard/kpis', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_kpis' ],
            'permission_callback' => [ $this, 'require_login' ],
        ] );

        register_rest_route( self::NAMESPACE, '/dashboard/expiring-reservations', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_expiring_reservations' ],
            'permission_callback' => [ $this, 'require_login' ],
        ] );

        register_rest_route( self::NAMESPACE, '/dashboard/accountant-invoices', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_accountant_invoices' ],
            'permission_callback' => [ $this, 'require_login' ],
        ] );

        // PATCH /invoices/{id}/accountant-status
        // Body: { "field": "isRsUploaded"|"isCreditChecked"|"isReceiptChecked"|"isCorrected", "value": true|false }
        register_rest_route( self::NAMESPACE, '/invoices/(?P<id>[\d]+)/accountant-status', [
            'methods'             => 'PATCH',
            'callback'            => [ $this, 'update_accountant_status' ],
            'permission_callback' => [ $this, 'require_accountant_or_woocommerce' ],
        ] );

        // PATCH /invoices/{id}/accountant-note
        // Body: { "note": "..." }
        register_rest_route( self::NAMESPACE, '/invoices/(?P<id>[\d]+)/accountant-note', [
            'methods'             => 'PATCH',
            'callback'            => [ $this, 'update_accountant_note' ],
            'permission_callback' => [ $this, 'require_accountant_or_woocommerce' ],
        ] );

        register_rest_route( self::NAMESPACE, '/settings/company', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_settings' ],
                'permission_callback' => [ $this, 'require_login' ],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'save_settings' ],
                'permission_callback' => [ $this, 'require_admin' ],
            ],
        ] );
    }

    // -------------------------------------------------------------------------
    // Callbacks
    // -------------------------------------------------------------------------

    /**
     * GET /cig/v1/dashboard/kpis
     *
     * Query params: date_from, date_to, status (standard|fictive|all), search
     *
     * Runs 4 SQL queries:
     *   1. Revenue  — SUM(total_amount) on sale_date range
     *   2. Cashflow — SUM(payment amounts) on payment date range, broken by method
     *   3. Items    — SUM(sold/reserved quantities)
     *   4. Outstanding — all-time unpaid balance
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_kpis( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $date_from = sanitize_text_field( $request->get_param( 'date_from' ) ?? '' );
        $date_to   = sanitize_text_field( $request->get_param( 'date_to' )   ?? '' );
        $status    = sanitize_text_field( $request->get_param( 'status' )    ?: 'standard' );
        $search    = sanitize_text_field( $request->get_param( 'search' )    ?? '' );

        // Select the appropriate date column based on status
        $date_col = ( $status === 'fictive' ) ? 'i.created_at' : 'i.sale_date';

        // ---- Query 1: Revenue ----
        if ( $status === 'fictive' ) {
            $where_rev = "WHERE i.status = 'fictive'";
        } elseif ( $status === 'all' ) {
            $where_rev = 'WHERE 1=1';
        } else {
            $where_rev = "WHERE (i.status = 'standard' OR i.status IS NULL)";
        }
        $params_rev = [];
        if ( $date_from ) { $where_rev .= " AND {$date_col} >= %s"; $params_rev[] = $date_from . ' 00:00:00'; }
        if ( $date_to )   { $where_rev .= " AND {$date_col} <= %s"; $params_rev[] = $date_to   . ' 23:59:59'; }
        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where_rev .= " AND (i.invoice_number LIKE %s OR c.name LIKE %s OR c.tax_id LIKE %s)";
            $params_rev[] = $like; $params_rev[] = $like; $params_rev[] = $like;
        }

        $sql_rev = "SELECT COUNT(DISTINCT i.id) AS invoice_count, COALESCE(SUM(i.total_amount),0) AS total_revenue
                    FROM {$this->t_invoices} i LEFT JOIN {$this->t_customers} c ON i.customer_id = c.id
                    {$where_rev}";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
        $rev = $wpdb->get_row( empty( $params_rev ) ? $sql_rev : $wpdb->prepare( $sql_rev, $params_rev ), ARRAY_A );

        // ---- Query 2: Cash flow (by payment date) ----
        $where_cf = 'WHERE 1=1';
        $params_cf = [];
        if ( $date_from ) { $where_cf .= ' AND p.date >= %s'; $params_cf[] = $date_from . ' 00:00:00'; }
        if ( $date_to )   { $where_cf .= ' AND p.date <= %s'; $params_cf[] = $date_to   . ' 23:59:59'; }
        if ( $status === 'fictive' ) {
            $where_cf .= " AND i.status = 'fictive'";
        } elseif ( $status !== 'all' ) {
            $where_cf .= " AND (i.status = 'standard' OR i.status IS NULL)";
        }
        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where_cf .= " AND (i.invoice_number LIKE %s OR c.name LIKE %s OR c.tax_id LIKE %s)";
            $params_cf[] = $like; $params_cf[] = $like; $params_cf[] = $like;
        }

        $sql_cf = "SELECT
            COALESCE(SUM(CASE WHEN p.method != 'consignment' THEN p.amount ELSE 0 END),0) AS total_paid,
            COALESCE(SUM(CASE WHEN p.method = 'company_transfer' THEN p.amount ELSE 0 END),0) AS total_company_transfer,
            COALESCE(SUM(CASE WHEN p.method = 'cash' THEN p.amount ELSE 0 END),0) AS total_cash,
            COALESCE(SUM(CASE WHEN p.method = 'consignment' THEN p.amount ELSE 0 END),0) AS total_consignment,
            COALESCE(SUM(CASE WHEN p.method = 'credit' THEN p.amount ELSE 0 END),0) AS total_credit,
            COALESCE(SUM(CASE WHEN p.method = 'other' OR p.method = '' OR p.method IS NULL THEN p.amount ELSE 0 END),0) AS total_other
            FROM {$this->t_payments} p
            LEFT JOIN {$this->t_invoices} i ON p.invoice_id = i.id
            LEFT JOIN {$this->t_customers} c ON i.customer_id = c.id
            {$where_cf}";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
        $cf = $wpdb->get_row( empty( $params_cf ) ? $sql_cf : $wpdb->prepare( $sql_cf, $params_cf ), ARRAY_A );

        // ---- Query 3: Item quantities ----
        $where_items  = 'WHERE 1=1';
        $params_items = [];
        if ( $status === 'fictive' ) {
            $where_items .= " AND i.status = 'fictive'";
        } elseif ( $status !== 'all' ) {
            $where_items .= " AND (i.status = 'standard' OR i.status IS NULL)";
        }
        if ( $date_from ) { $where_items .= " AND {$date_col} >= %s"; $params_items[] = $date_from . ' 00:00:00'; }
        if ( $date_to )   { $where_items .= " AND {$date_col} <= %s"; $params_items[] = $date_to   . ' 23:59:59'; }
        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where_items .= " AND (i.invoice_number LIKE %s OR c.name LIKE %s OR c.tax_id LIKE %s)";
            $params_items[] = $like; $params_items[] = $like; $params_items[] = $like;
        }

        $sql_items = "SELECT
            COALESCE(SUM(CASE WHEN it.item_status = 'sold'     THEN it.quantity ELSE 0 END),0) AS total_sold,
            COALESCE(SUM(CASE WHEN it.item_status = 'reserved' THEN it.quantity ELSE 0 END),0) AS total_reserved,
            COUNT(DISTINCT CASE WHEN it.item_status = 'reserved' THEN it.invoice_id END)        AS reserved_invoices_count
            FROM {$this->t_invoices} i
            LEFT JOIN {$this->t_items} it ON i.id = it.invoice_id
            LEFT JOIN {$this->t_customers} c ON i.customer_id = c.id
            {$where_items}";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
        $items = $wpdb->get_row( empty( $params_items ) ? $sql_items : $wpdb->prepare( $sql_items, $params_items ), ARRAY_A );

        // ---- Query 4: All-time outstanding balance ----
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $outstanding = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(total_amount - paid_amount),0)
             FROM {$this->t_invoices}
             WHERE (status = 'standard' OR status IS NULL) AND (total_amount - paid_amount) > 0.01"
        );

        return new WP_REST_Response( [
            'data' => [
                'totalInvoices'          => (int)   ( $rev['invoice_count']    ?? 0 ),
                'totalRevenue'           => (float)  ( $rev['total_revenue']   ?? 0 ),
                'totalPaid'              => (float)  ( $cf['total_paid']        ?? 0 ),
                'totalCompanyTransfer'   => (float)  ( $cf['total_company_transfer'] ?? 0 ),
                'totalCash'              => (float)  ( $cf['total_cash']        ?? 0 ),
                'totalConsignment'       => (float)  ( $cf['total_consignment'] ?? 0 ),
                'totalCredit'            => (float)  ( $cf['total_credit']      ?? 0 ),
                'totalOther'             => (float)  ( $cf['total_other']       ?? 0 ),
                'totalSold'              => (float)  ( $items['total_sold']     ?? 0 ),
                'totalReserved'          => (float)  ( $items['total_reserved'] ?? 0 ),
                'totalReservedInvoices'  => (int)    ( $items['reserved_invoices_count'] ?? 0 ),
                'totalOutstanding'       => $outstanding,
            ],
        ], 200 );
    }

    /**
     * GET /cig/v1/dashboard/expiring-reservations
     *
     * Query params: author_id (optional — defaults to current user for sales role)
     *
     * Returns reserved items whose expiry date (sale_date + reservation_days) falls
     * within the next 3 days, sorted by days_left ASC.
     *
     * Replaces the legacy postmeta-based get_expiring_reservations() AJAX handler.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_expiring_reservations( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $uid       = get_current_user_id();
        $author_id = (int) ( $request->get_param( 'author_id' ) ?: 0 );

        // Sales users see only their own reservations; admins/managers can pass any author_id
        $can_see_all = current_user_can( 'manage_woocommerce' ) || current_user_can( 'administrator' );
        if ( ! $can_see_all ) {
            $author_id = $uid; // Force to own invoices
        } elseif ( $author_id === 0 ) {
            $author_id = $uid; // Default to current user even for admins
        }

        $where  = "WHERE ii.item_status = 'reserved' AND ii.reservation_days > 0 AND (i.status = 'standard' OR i.status IS NULL)";
        $params = [];
        if ( $author_id > 0 ) {
            $where .= ' AND i.author_id = %d';
            $params[] = $author_id;
        }

        $sql = "SELECT
            ii.id AS item_id,
            ii.invoice_id,
            i.invoice_number,
            i.sale_date,
            i.author_id,
            ii.product_name,
            ii.sku,
            ii.quantity,
            ii.reservation_days
            FROM {$this->t_items} ii
            INNER JOIN {$this->t_invoices} i ON i.id = ii.invoice_id
            {$where}";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results(
            empty( $params ) ? $sql : $wpdb->prepare( $sql, $params ),
            ARRAY_A
        );

        $now       = current_time( 'timestamp' );
        $threshold = $now + ( 3 * DAY_IN_SECONDS );
        $expiring  = [];

        foreach ( $rows as $row ) {
            $sale_date = $row['sale_date'] ?? '';
            if ( empty( $sale_date ) ) {
                continue;
            }
            $days    = (int) $row['reservation_days'];
            $exp_ts  = strtotime( $sale_date ) + ( $days * DAY_IN_SECONDS );

            if ( $exp_ts > $now && $exp_ts <= $threshold ) {
                $days_left = (int) ceil( ( $exp_ts - $now ) / DAY_IN_SECONDS );
                $expiring[] = [
                    'itemId'        => (int) $row['item_id'],
                    'invoiceId'     => (int) $row['invoice_id'],
                    'invoiceNumber' => $row['invoice_number'],
                    'productName'   => $row['product_name'],
                    'sku'           => $row['sku'],
                    'quantity'      => (float) $row['quantity'],
                    'expiresAt'     => gmdate( 'Y-m-d H:i:s', $exp_ts ),
                    'daysLeft'      => $days_left,
                ];
            }
        }

        usort( $expiring, fn( $a, $b ) => $a['daysLeft'] <=> $b['daysLeft'] );

        return new WP_REST_Response( [
            'data'  => $expiring,
            'count' => count( $expiring ),
        ], 200 );
    }

    /**
     * GET /cig/v1/dashboard/accountant-invoices
     *
     * Query params:
     *   search      — invoice_number, customer name, or tax_id LIKE
     *   completion  — all | incomplete | completed (has any accountant flag set)
     *   type_filter — all | rs | credit | receipt | corrected
     *   date_from, date_to — filter by sale_date
     *   per_page, page
     *
     * Only shows standard invoices with lifecycle_status IN (completed, reserved) and total > 0.
     * Uses new boolean columns instead of the legacy _cig_acc_status postmeta.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_accountant_invoices( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $per_page    = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );
        $page        = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
        $offset      = ( $page - 1 ) * $per_page;
        $search      = sanitize_text_field( $request->get_param( 'search' )      ?? '' );
        $completion  = sanitize_text_field( $request->get_param( 'completion' )  ?: 'all' );
        $type_filter = sanitize_text_field( $request->get_param( 'type_filter' ) ?: 'all' );
        $date_from   = sanitize_text_field( $request->get_param( 'date_from' )   ?? '' );
        $date_to     = sanitize_text_field( $request->get_param( 'date_to' )     ?? '' );

        // Base filter: standard invoices, lifecycle completed|reserved, total > 0
        $where_parts = [
            "(i.status = 'standard' OR i.status IS NULL)",
            'i.total_amount > 0',
            "i.lifecycle_status IN ('completed','reserved')",
        ];
        $where_vals = [];

        if ( ! empty( $date_from ) ) { $where_parts[] = 'i.sale_date >= %s'; $where_vals[] = $date_from . ' 00:00:00'; }
        if ( ! empty( $date_to ) )   { $where_parts[] = 'i.sale_date <= %s'; $where_vals[] = $date_to   . ' 23:59:59'; }

        if ( ! empty( $search ) ) {
            $like          = '%' . $wpdb->esc_like( $search ) . '%';
            $where_parts[] = '(i.invoice_number LIKE %s OR c.name LIKE %s OR c.tax_id LIKE %s)';
            $where_vals[]  = $like; $where_vals[] = $like; $where_vals[] = $like;
        }

        // Completion filter (based on new boolean columns)
        if ( $completion === 'incomplete' ) {
            $where_parts[] = '(i.is_rs_uploaded = 0 AND i.is_credit_checked = 0 AND i.is_receipt_checked = 0 AND i.is_corrected = 0)';
        } elseif ( $completion === 'completed' ) {
            $where_parts[] = '(i.is_rs_uploaded = 1 OR i.is_credit_checked = 1 OR i.is_receipt_checked = 1 OR i.is_corrected = 1)';
        }

        // Type filter (specific boolean column)
        $type_col_map = [
            'rs'       => 'i.is_rs_uploaded',
            'credit'   => 'i.is_credit_checked',
            'receipt'  => 'i.is_receipt_checked',
            'corrected'=> 'i.is_corrected',
        ];
        if ( $type_filter !== 'all' && isset( $type_col_map[ $type_filter ] ) ) {
            $where_parts[] = $type_col_map[ $type_filter ] . ' = 1';
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where_parts );
        $join_sql  = "LEFT JOIN {$this->t_customers} c ON c.id = i.customer_id";

        // Count
        $count_sql = "SELECT COUNT(DISTINCT i.id) FROM {$this->t_invoices} i {$join_sql} {$where_sql}";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
        $total = (int) (
            empty( $where_vals )
                ? $wpdb->get_var( $count_sql )
                : $wpdb->get_var( $wpdb->prepare( $count_sql, $where_vals ) )
        );

        // Data
        $data_vals = array_merge( $where_vals, [ $per_page, $offset ] );
        $data_sql  = "SELECT i.*, c.name AS customer_name, c.tax_id AS customer_tax_id
                      FROM {$this->t_invoices} i {$join_sql} {$where_sql}
                      ORDER BY i.sale_date DESC LIMIT %d OFFSET %d";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_vals ), ARRAY_A );

        if ( empty( $rows ) ) {
            return new WP_REST_Response( [
                'data' => [],
                'meta' => [ 'total' => $total, 'per_page' => $per_page, 'current_page' => $page, 'last_page' => max( 1, (int) ceil( $total / $per_page ) ) ],
            ], 200 );
        }

        // Batch load payments for payment-method summary
        $ids         = array_column( $rows, 'id' );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
        $all_payments = $wpdb->get_results(
            $wpdb->prepare( "SELECT invoice_id, method, amount FROM {$this->t_payments} WHERE invoice_id IN ({$placeholders}) AND amount > 0", ...$ids ),
            ARRAY_A
        );

        $payments_map = [];
        foreach ( $all_payments as $p ) {
            $payments_map[ $p['invoice_id'] ][] = $p;
        }

        $method_labels = [
            'company_transfer' => 'Company Transfer',
            'cash'             => 'Cash',
            'consignment'      => 'Consignment',
            'credit'           => 'Credit',
            'other'            => 'Other',
        ];

        $data = [];
        foreach ( $rows as $row ) {
            $iid      = $row['id'];
            $payments = $payments_map[ $iid ] ?? [];

            // Build payment title / description
            $sums = [];
            foreach ( $payments as $p ) {
                $method = $p['method'] ?? 'other';
                $label  = $method_labels[ $method ] ?? $method;
                $sums[ $label ] = ( $sums[ $label ] ?? 0 ) + (float) $p['amount'];
            }
            if ( count( $sums ) === 1 ) {
                $payment_title = array_key_first( $sums );
                $payment_desc  = '';
            } elseif ( count( $sums ) > 1 ) {
                $payment_title = implode( ' + ', array_keys( $sums ) );
                $parts = [];
                foreach ( $sums as $lbl => $amt ) {
                    $parts[] = $lbl . ' ' . number_format( $amt, 0 ) . ' ₾';
                }
                $payment_desc = '(' . implode( ', ', $parts ) . ')';
            } else {
                $payment_title = '—';
                $payment_desc  = '';
            }

            $data[] = [
                'id'               => (int) $row['id'],
                'number'           => $row['invoice_number'],
                'date'             => $row['sale_date'] ? substr( $row['sale_date'], 0, 10 ) : null,
                'soldDate'         => $row['sold_date'] ?? null,
                'totalAmount'      => (float) $row['total_amount'],
                'paidAmount'       => (float) $row['paid_amount'],
                'customerName'     => $row['customer_name'] ?: '—',
                'customerTaxId'    => $row['customer_tax_id'] ?: '',
                'paymentTitle'     => $payment_title,
                'paymentDesc'      => $payment_desc,
                'isRsUploaded'     => (bool) $row['is_rs_uploaded'],
                'isCreditChecked'  => (bool) ( $row['is_credit_checked']  ?? false ),
                'isReceiptChecked' => (bool) ( $row['is_receipt_checked'] ?? false ),
                'isCorrected'      => (bool) ( $row['is_corrected']       ?? false ),
                'accountantNote'   => $row['accountant_note'] ?? '',
                'generalNote'      => $row['general_note']    ?? '',
            ];
        }

        return new WP_REST_Response( [
            'data' => $data,
            'meta' => [
                'total'        => $total,
                'per_page'     => $per_page,
                'current_page' => $page,
                'last_page'    => max( 1, (int) ceil( $total / $per_page ) ),
            ],
        ], 200 );
    }

    /**
     * PATCH /cig/v1/invoices/{id}/accountant-status
     *
     * Body: { "field": "isRsUploaded"|"isCreditChecked"|"isReceiptChecked"|"isCorrected", "value": true|false }
     *
     * Writes directly to the boolean column in cig_invoices.
     * Also records rs_uploaded_by + rs_uploaded_date when is_rs_uploaded is set to true.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function update_accountant_status( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $id    = (int) $request->get_param( 'id' );
        $body  = $request->get_json_params() ?: [];
        $field = sanitize_text_field( $body['field'] ?? '' );
        $value = (bool) ( $body['value'] ?? false );

        if ( ! isset( self::ACCOUNTANT_FIELDS[ $field ] ) ) {
            return new WP_REST_Response( [
                'code'    => 'invalid_field',
                'message' => __( 'Invalid accountant status field.', 'cig' ),
            ], 400 );
        }

        $col = self::ACCOUNTANT_FIELDS[ $field ];

        $update_data   = [ $col => $value ? 1 : 0 ];
        $update_format = [ '%d' ];

        // Track who uploaded to RS and when
        if ( $col === 'is_rs_uploaded' && $value ) {
            $update_data['rs_uploaded_by']   = get_current_user_id();
            $update_data['rs_uploaded_date'] = current_time( 'mysql' );
            $update_format[]                 = '%d';
            $update_format[]                 = '%s';
        } elseif ( $col === 'is_rs_uploaded' && ! $value ) {
            $update_data['rs_uploaded_by']   = null;
            $update_data['rs_uploaded_date'] = null;
            $update_format[]                 = '%s';
            $update_format[]                 = '%s';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->update( $this->t_invoices, $update_data, [ 'id' => $id ], $update_format, [ '%d' ] );

        if ( false === $result ) {
            return new WP_REST_Response( [
                'code'    => 'db_error',
                'message' => __( 'Failed to update accountant status.', 'cig' ),
            ], 500 );
        }

        return new WP_REST_Response( [ 'data' => [ 'id' => $id, 'field' => $field, 'value' => $value ] ], 200 );
    }

    /**
     * PATCH /cig/v1/invoices/{id}/accountant-note
     *
     * Body: { "note": "..." }
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function update_accountant_note( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $id   = (int) $request->get_param( 'id' );
        $body = $request->get_json_params() ?: [];
        $note = sanitize_textarea_field( $body['note'] ?? '' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->update(
            $this->t_invoices,
            [ 'accountant_note' => $note ],
            [ 'id' => $id ],
            [ '%s' ],
            [ '%d' ]
        );

        if ( false === $result ) {
            return new WP_REST_Response( [
                'code'    => 'db_error',
                'message' => __( 'Failed to update note.', 'cig' ),
            ], 500 );
        }

        return new WP_REST_Response( [ 'data' => [ 'id' => $id, 'accountantNote' => $note ] ], 200 );
    }

    /**
     * GET /cig/v1/settings/company
     *
     * Returns the full cig_settings array from wp_options, keyed in camelCase
     * for the fields the Vue SPA needs (company info + bank details).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_settings( WP_REST_Request $request ): WP_REST_Response {
        $s = get_option( 'cig_settings', [] );

        return new WP_REST_Response( [
            'data' => [
                // Company
                'companyName'   => $s['company_name']   ?? '',
                'companyTaxId'  => $s['company_tax_id'] ?? '',
                'companyLogo'   => $s['company_logo']   ?? '',
                'address'       => $s['address']        ?? '',
                'phone'         => $s['phone']           ?? '',
                'email'         => $s['email']           ?? '',
                'website'       => $s['website']         ?? '',
                // Bank
                'bankName'      => $s['bank_name']      ?? '',
                'bankAccount'   => $s['bank_account']   ?? '',
                'bankCode'      => $s['bank_code']      ?? '',
                // Invoice
                'startingInvoiceNumber' => $s['starting_invoice_number'] ?? '',
            ],
        ], 200 );
    }

    /**
     * PUT /cig/v1/settings/company
     *
     * Body keys (camelCase) mirror the GET response.
     * Only updates the fields listed here — other cig_settings keys are preserved.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function save_settings( WP_REST_Request $request ): WP_REST_Response {
        $body = $request->get_json_params() ?: [];

        $existing = get_option( 'cig_settings', [] );

        $allowed = [
            'companyName'           => 'company_name',
            'companyTaxId'          => 'company_tax_id',
            'companyLogo'           => 'company_logo',
            'address'               => 'address',
            'phone'                 => 'phone',
            'email'                 => 'email',
            'website'               => 'website',
            'bankName'              => 'bank_name',
            'bankAccount'           => 'bank_account',
            'bankCode'              => 'bank_code',
            'startingInvoiceNumber' => 'starting_invoice_number',
        ];

        foreach ( $allowed as $camel => $snake ) {
            if ( array_key_exists( $camel, $body ) ) {
                $existing[ $snake ] = sanitize_text_field( (string) $body[ $camel ] );
            }
        }

        update_option( 'cig_settings', $existing, false );

        return $this->get_settings( $request );
    }

    // -------------------------------------------------------------------------
    // Permission callbacks
    // -------------------------------------------------------------------------

    public function require_login() {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'not_authenticated', __( 'You must be logged in.', 'cig' ), [ 'status' => 401 ] );
        }
        return true;
    }

    public function require_admin() {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'not_authenticated', __( 'You must be logged in.', 'cig' ), [ 'status' => 401 ] );
        }
        if ( ! current_user_can( 'administrator' ) ) {
            return new WP_Error( 'forbidden', __( 'Only administrators can modify settings.', 'cig' ), [ 'status' => 403 ] );
        }
        return true;
    }

    public function require_accountant_or_woocommerce() {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'not_authenticated', __( 'You must be logged in.', 'cig' ), [ 'status' => 401 ] );
        }
        if (
            current_user_can( 'administrator' ) ||
            current_user_can( 'manage_woocommerce' ) ||
            current_user_can( 'cig_accountant_access' )
        ) {
            return true;
        }
        return new WP_Error( 'forbidden', __( 'You do not have permission to perform this action.', 'cig' ), [ 'status' => 403 ] );
    }
}
