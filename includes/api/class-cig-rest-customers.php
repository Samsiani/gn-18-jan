<?php
/**
 * REST API — Customer & User Endpoints
 *
 * GET  /cig/v1/customers           — paginated list with search + aggregated stats
 * POST /cig/v1/customers           — upsert by tax_id via CIG_Customers::sync_customer()
 * GET  /cig/v1/customers/{id}      — single customer with computed totals
 * GET  /cig/v1/customers/{id}/invoices — invoice history for a customer
 * GET  /cig/v1/users               — all WP users with CIG role mapping
 *
 * @package CIG
 * @since 5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CIG_Rest_Customers {

    const NAMESPACE = 'cig/v1';

    private $t_invoices;
    private $t_customers;

    public function __construct() {
        global $wpdb;
        $this->t_invoices  = $wpdb->prefix . 'cig_invoices';
        $this->t_customers = $wpdb->prefix . 'cig_customers';

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    // -------------------------------------------------------------------------
    // Route registration
    // -------------------------------------------------------------------------

    public function register_routes() {

        register_rest_route( self::NAMESPACE, '/customers', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'list_customers' ],
                'permission_callback' => [ $this, 'require_login' ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'upsert_customer' ],
                'permission_callback' => [ $this, 'require_manage_woocommerce' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/customers/(?P<id>[\d]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_customer' ],
                'permission_callback' => [ $this, 'require_login' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/customers/(?P<id>[\d]+)/invoices', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_customer_invoices' ],
            'permission_callback' => [ $this, 'require_login' ],
        ] );

        register_rest_route( self::NAMESPACE, '/users', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'list_users' ],
            'permission_callback' => [ $this, 'require_login' ],
        ] );
    }

    // -------------------------------------------------------------------------
    // Callbacks
    // -------------------------------------------------------------------------

    /**
     * GET /cig/v1/customers
     *
     * Query params: search, date_from, date_to, per_page, page
     *
     * Returns each customer with computed invoice_count, total_spent, total_paid,
     * and outstanding — all scoped to the optional date range (standard invoices only).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function list_customers( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $per_page  = max( 1, min( 200, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );
        $page      = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
        $offset    = ( $page - 1 ) * $per_page;
        $search    = sanitize_text_field( $request->get_param( 'search' ) ?? '' );
        $date_from = sanitize_text_field( $request->get_param( 'date_from' ) ?? '' );
        $date_to   = sanitize_text_field( $request->get_param( 'date_to' ) ?? '' );

        // Customer-level WHERE (search)
        $where_parts = [];
        $where_vals  = [];
        if ( ! empty( $search ) ) {
            $like          = '%' . $wpdb->esc_like( $search ) . '%';
            $where_parts[] = '(c.name LIKE %s OR c.tax_id LIKE %s)';
            $where_vals[]  = $like;
            $where_vals[]  = $like;
        }
        $where_sql = $where_parts ? 'WHERE ' . implode( ' AND ', $where_parts ) : 'WHERE 1=1';

        // Count
        $count_sql = "SELECT COUNT(DISTINCT c.id) FROM {$this->t_customers} c {$where_sql}";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
        $total = (int) (
            empty( $where_vals )
                ? $wpdb->get_var( $count_sql )
                : $wpdb->get_var( $wpdb->prepare( $count_sql, $where_vals ) )
        );

        // Invoice-level CASE WHEN for date-scoped aggregation (standard invoices only)
        $inv_condition = "(i.status = 'standard' OR i.status IS NULL)";
        $inv_params    = [];
        if ( ! empty( $date_from ) ) {
            $inv_condition .= ' AND i.sale_date >= %s';
            $inv_params[]   = $date_from . ' 00:00:00';
        }
        if ( ! empty( $date_to ) ) {
            $inv_condition .= ' AND i.sale_date <= %s';
            $inv_params[]   = $date_to . ' 23:59:59';
        }

        // Params order: inv_condition appears 3× in the CASE WHEN clauses, then customer WHERE, then LIMIT/OFFSET
        $all_params = array_merge( $inv_params, $inv_params, $inv_params, $where_vals, [ $per_page, $offset ] );

        $sql = "SELECT
            c.id, c.name, c.tax_id, c.phone, c.email, c.address,
            COUNT(DISTINCT CASE WHEN {$inv_condition} THEN i.id END)                                      AS invoice_count,
            COALESCE(SUM(CASE WHEN {$inv_condition} THEN i.total_amount ELSE 0 END), 0)                   AS total_spent,
            COALESCE(SUM(CASE WHEN {$inv_condition} THEN i.paid_amount  ELSE 0 END), 0)                   AS total_paid
            FROM {$this->t_customers} c
            LEFT JOIN {$this->t_invoices} i ON c.id = i.customer_id
            {$where_sql}
            GROUP BY c.id, c.name, c.tax_id, c.phone, c.email, c.address
            ORDER BY total_spent DESC
            LIMIT %d OFFSET %d";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $all_params ), ARRAY_A );

        return new WP_REST_Response( [
            'data' => array_map( [ $this, 'format_customer_row' ], $rows ?: [] ),
            'meta' => [
                'total'        => $total,
                'per_page'     => $per_page,
                'current_page' => $page,
                'last_page'    => max( 1, (int) ceil( $total / $per_page ) ),
            ],
        ], 200 );
    }

    /**
     * POST /cig/v1/customers
     *
     * Body (camelCase): { name, taxId, phone, email, address }
     * Upserts via CIG_Customers::sync_customer() — finds existing by tax_id or creates new.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function upsert_customer( WP_REST_Request $request ): WP_REST_Response {
        $body  = $request->get_json_params() ?: [];
        $buyer = [
            'name'    => sanitize_text_field( $body['name']    ?? '' ),
            'tax_id'  => sanitize_text_field( $body['taxId']   ?? $body['tax_id'] ?? '' ),
            'phone'   => sanitize_text_field( $body['phone']   ?? '' ),
            'email'   => sanitize_email(      $body['email']   ?? '' ),
            'address' => sanitize_text_field( $body['address'] ?? '' ),
        ];

        if ( empty( $buyer['name'] ) || empty( $buyer['tax_id'] ) ) {
            return new WP_REST_Response( [
                'code'    => 'missing_fields',
                'message' => __( 'Name and tax ID are required.', 'cig' ),
            ], 400 );
        }

        $customer_id = CIG()->customers->sync_customer( $buyer );
        if ( ! $customer_id ) {
            return new WP_REST_Response( [
                'code'    => 'sync_failed',
                'message' => __( 'Failed to save customer.', 'cig' ),
            ], 500 );
        }

        return $this->fetch_single_customer( $customer_id );
    }

    /**
     * GET /cig/v1/customers/{id}
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_customer( WP_REST_Request $request ): WP_REST_Response {
        return $this->fetch_single_customer( (int) $request->get_param( 'id' ) );
    }

    /**
     * GET /cig/v1/customers/{id}/invoices
     *
     * Query params: date_from, date_to
     * Returns the customer's standard invoices, most recent first (max 200).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_customer_invoices( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $id        = (int) $request->get_param( 'id' );
        $date_from = sanitize_text_field( $request->get_param( 'date_from' ) ?? '' );
        $date_to   = sanitize_text_field( $request->get_param( 'date_to' )   ?? '' );

        $where  = "WHERE i.customer_id = %d AND (i.status = 'standard' OR i.status IS NULL)";
        $params = [ $id ];
        if ( ! empty( $date_from ) ) { $where .= ' AND i.sale_date >= %s'; $params[] = $date_from . ' 00:00:00'; }
        if ( ! empty( $date_to ) )   { $where .= ' AND i.sale_date <= %s'; $params[] = $date_to   . ' 23:59:59'; }

        $sql = "SELECT i.id, i.invoice_number, i.sale_date, i.total_amount, i.paid_amount
                FROM {$this->t_invoices} i {$where}
                ORDER BY i.sale_date DESC LIMIT 200";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

        $data = array_map( function ( $row ) {
            $total = (float) $row['total_amount'];
            $paid  = (float) $row['paid_amount'];
            return [
                'id'     => (int) $row['id'],
                'number' => $row['invoice_number'],
                'date'   => $row['sale_date'] ? substr( $row['sale_date'], 0, 10 ) : null,
                'total'  => $total,
                'paid'   => $paid,
                'due'    => max( 0.0, $total - $paid ),
                'isPaid' => ( $total - $paid ) < 0.01,
            ];
        }, $rows ?: [] );

        return new WP_REST_Response( [ 'data' => $data ], 200 );
    }

    /**
     * GET /cig/v1/users
     *
     * Returns all WordPress users with their CIG role mapped via CIG_Rest_API::format_user().
     * Used by the Vue SPA to populate author/consultant selects.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function list_users( WP_REST_Request $request ): WP_REST_Response {
        $wp_users = get_users( [ 'orderby' => 'display_name', 'order' => 'ASC' ] );

        $data = array_map( function ( $u ) {
            return CIG()->rest_api->format_user( $u );
        }, $wp_users );

        return new WP_REST_Response( [ 'data' => $data ], 200 );
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch a single customer row and return a formatted REST response.
     *
     * @param int $id
     * @return WP_REST_Response
     */
    private function fetch_single_customer( int $id ): WP_REST_Response {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->t_customers} WHERE id = %d", $id ),
            ARRAY_A
        );

        if ( ! $row ) {
            return new WP_REST_Response( [
                'code'    => 'not_found',
                'message' => __( 'Customer not found.', 'cig' ),
            ], 404 );
        }

        // Aggregate stats (all-time, standard invoices only)
        $stats = CIG()->invoice_manager->get_customer_stats( $id );

        $spent       = (float) ( $stats['revenue'] ?? 0 );
        $paid        = (float) ( $stats['paid']    ?? 0 );
        $outstanding = max( 0.0, $spent - $paid );

        return new WP_REST_Response( [
            'data' => [
                'id'           => (int) $row['id'],
                'name'         => $row['name'],
                'taxId'        => $row['tax_id'],
                'phone'        => $row['phone'],
                'email'        => $row['email'],
                'address'      => $row['address'],
                'invoiceCount' => (int) ( $stats['count'] ?? 0 ),
                'totalSpent'   => $spent,
                'totalPaid'    => $paid,
                'outstanding'  => $outstanding,
            ],
        ], 200 );
    }

    /**
     * Format a customer row from the aggregation query.
     *
     * @param array $row DB row (may include invoice_count, total_spent, total_paid)
     * @return array
     */
    private function format_customer_row( array $row ): array {
        $spent       = (float) ( $row['total_spent'] ?? 0 );
        $paid        = (float) ( $row['total_paid']  ?? 0 );
        $outstanding = max( 0.0, $spent - $paid );
        return [
            'id'           => (int) $row['id'],
            'name'         => $row['name'] ?: '',
            'taxId'        => $row['tax_id'] ?: '',
            'phone'        => $row['phone'] ?: '',
            'email'        => $row['email'] ?: '',
            'address'      => $row['address'] ?: '',
            'invoiceCount' => (int) ( $row['invoice_count'] ?? 0 ),
            'totalSpent'   => $spent,
            'totalPaid'    => $paid,
            'outstanding'  => $outstanding,
        ];
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

    public function require_manage_woocommerce() {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'not_authenticated', __( 'You must be logged in.', 'cig' ), [ 'status' => 401 ] );
        }
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'administrator' ) ) {
            return new WP_Error( 'forbidden', __( 'You do not have permission to perform this action.', 'cig' ), [ 'status' => 403 ] );
        }
        return true;
    }
}
