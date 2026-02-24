<?php
/**
 * REST API — Invoice Endpoints
 *
 * Registers all invoice CRUD routes under the cig/v1 namespace.
 * Replicates the exact business logic from CIG_Ajax_Invoices:
 *   - Status auto-determination (standard if payments exist, fictive otherwise)
 *   - Item status enforcement (none→reserved for standard; none for fictive)
 *   - sale_date = latest payment date + current H:i:s
 *   - Lifecycle: completed/reserved/unfinished from item statuses
 *   - Stock validation and reservation sync via CIG_Stock_Manager
 *   - Customer upsert via CIG_Customers::sync_customer()
 *
 * @package CIG
 * @since 5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CIG_Rest_Invoices {

    const NAMESPACE = 'cig/v1';

    /** @var string */
    private $t_invoices;
    /** @var string */
    private $t_items;
    /** @var string */
    private $t_payments;
    /** @var string */
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

        // GET /cig/v1/invoices  — paginated, filterable list
        // POST /cig/v1/invoices — create new invoice
        register_rest_route( self::NAMESPACE, '/invoices', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'list_invoices' ],
                'permission_callback' => [ $this, 'require_login' ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_invoice' ],
                'permission_callback' => [ $this, 'require_manage_woocommerce' ],
            ],
        ] );

        // GET /cig/v1/invoices/next-number — must be registered before the {id} route
        register_rest_route( self::NAMESPACE, '/invoices/next-number', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'next_number' ],
            'permission_callback' => [ $this, 'require_manage_woocommerce' ],
        ] );

        // GET, PUT, DELETE /cig/v1/invoices/{id}
        register_rest_route( self::NAMESPACE, '/invoices/(?P<id>[\d]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_invoice' ],
                'permission_callback' => [ $this, 'require_login' ],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_invoice' ],
                'permission_callback' => [ $this, 'require_manage_woocommerce' ],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_invoice' ],
                'permission_callback' => [ $this, 'require_manage_woocommerce' ],
            ],
        ] );

        // POST /cig/v1/invoices/{id}/toggle-status
        register_rest_route( self::NAMESPACE, '/invoices/(?P<id>[\d]+)/toggle-status', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'toggle_status' ],
            'permission_callback' => [ $this, 'require_manage_woocommerce' ],
        ] );

        // POST /cig/v1/invoices/{id}/mark-sold
        register_rest_route( self::NAMESPACE, '/invoices/(?P<id>[\d]+)/mark-sold', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'mark_sold' ],
            'permission_callback' => [ $this, 'require_manage_woocommerce' ],
        ] );
    }

    // -------------------------------------------------------------------------
    // Callbacks
    // -------------------------------------------------------------------------

    /**
     * GET /cig/v1/invoices
     *
     * Query params:
     *   status, lifecycle_status, author_id, search (number|customer name|tax_id),
     *   payment_method, date_from, date_to, per_page (default 50), page (default 1)
     *
     * Uses 3 queries regardless of page size (count + rows + batch items + batch payments).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function list_invoices( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $per_page = max( 1, min( 500, (int) ( $request->get_param( 'per_page' ) ?: 50 ) ) );
        $page     = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
        $offset   = ( $page - 1 ) * $per_page;

        $status           = sanitize_text_field( $request->get_param( 'status' ) ?? '' );
        $lifecycle_status = sanitize_text_field( $request->get_param( 'lifecycle_status' ) ?? '' );
        $author_id        = (int) ( $request->get_param( 'author_id' ) ?: 0 );
        $search           = sanitize_text_field( $request->get_param( 'search' ) ?? '' );
        $payment_method   = sanitize_text_field( $request->get_param( 'payment_method' ) ?? '' );
        $date_from        = sanitize_text_field( $request->get_param( 'date_from' ) ?? '' );
        $date_to          = sanitize_text_field( $request->get_param( 'date_to' ) ?? '' );

        // Build WHERE clauses with separate placeholder arrays (single prepare call per query)
        $where_parts = [];
        $where_vals  = [];

        if ( ! empty( $status ) ) {
            $where_parts[] = 'i.status = %s';
            $where_vals[]  = $status;
        }
        if ( ! empty( $lifecycle_status ) ) {
            $where_parts[] = 'i.lifecycle_status = %s';
            $where_vals[]  = $lifecycle_status;
        }
        if ( $author_id > 0 ) {
            $where_parts[] = 'i.author_id = %d';
            $where_vals[]  = $author_id;
        }
        if ( ! empty( $date_from ) ) {
            $where_parts[] = 'i.sale_date >= %s';
            $where_vals[]  = $date_from . ' 00:00:00';
        }
        if ( ! empty( $date_to ) ) {
            $where_parts[] = 'i.sale_date <= %s';
            $where_vals[]  = $date_to . ' 23:59:59';
        }

        // Search: matches invoice_number, customer name, or customer tax_id
        $join_sql = "LEFT JOIN {$this->t_customers} c ON c.id = i.customer_id";
        if ( ! empty( $search ) ) {
            $like          = '%' . $wpdb->esc_like( $search ) . '%';
            $where_parts[] = '(i.invoice_number LIKE %s OR c.name LIKE %s OR c.tax_id LIKE %s)';
            $where_vals[]  = $like;
            $where_vals[]  = $like;
            $where_vals[]  = $like;
        }

        // Payment method: EXISTS sub-select
        if ( ! empty( $payment_method ) ) {
            $where_parts[] = 'EXISTS (SELECT 1 FROM ' . $this->t_payments . ' p WHERE p.invoice_id = i.id AND p.method = %s)';
            $where_vals[]  = $payment_method;
        }

        $where_sql = $where_parts ? 'WHERE ' . implode( ' AND ', $where_parts ) : '';

        // Count query (same filters, no pagination)
        $count_sql = "SELECT COUNT(DISTINCT i.id) FROM {$this->t_invoices} i {$join_sql} {$where_sql}";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
        $total = (int) ( empty( $where_vals )
            ? $wpdb->get_var( $count_sql )
            : $wpdb->get_var( $wpdb->prepare( $count_sql, $where_vals ) )
        );

        // Data query — add pagination params to values array
        $data_vals = array_merge( $where_vals, [ $per_page, $offset ] );
        $data_sql  = "SELECT i.* FROM {$this->t_invoices} i {$join_sql} {$where_sql} ORDER BY i.id DESC LIMIT %d OFFSET %d";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_vals ), ARRAY_A );

        $last_page = max( 1, (int) ceil( $total / $per_page ) );

        if ( empty( $rows ) ) {
            return new WP_REST_Response( [
                'data' => [],
                'meta' => [
                    'total'        => $total,
                    'per_page'     => $per_page,
                    'current_page' => $page,
                    'last_page'    => $last_page,
                ],
            ], 200 );
        }

        // Batch-load items and payments in one query each (prevents N+1)
        $ids              = array_column( $rows, 'id' );
        $ids_placeholder  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
        $all_items = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$this->t_items} WHERE invoice_id IN ({$ids_placeholder}) ORDER BY id ASC", ...$ids ),
            ARRAY_A
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
        $all_payments = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$this->t_payments} WHERE invoice_id IN ({$ids_placeholder}) ORDER BY date ASC, id ASC", ...$ids ),
            ARRAY_A
        );

        // Group by invoice_id
        $items_map    = [];
        $payments_map = [];
        foreach ( $all_items as $item ) {
            $items_map[ $item['invoice_id'] ][] = $item;
        }
        foreach ( $all_payments as $payment ) {
            $payments_map[ $payment['invoice_id'] ][] = $payment;
        }

        $data = [];
        foreach ( $rows as $row ) {
            $iid    = $row['id'];
            $data[] = $this->format_invoice(
                $row,
                $items_map[ $iid ] ?? [],
                $payments_map[ $iid ] ?? []
            );
        }

        return new WP_REST_Response( [
            'data' => $data,
            'meta' => [
                'total'        => $total,
                'per_page'     => $per_page,
                'current_page' => $page,
                'last_page'    => $last_page,
            ],
        ], 200 );
    }

    /**
     * GET /cig/v1/invoices/next-number
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function next_number( WP_REST_Request $request ): WP_REST_Response {
        return new WP_REST_Response( [
            'data' => [ 'number' => CIG_Invoice::get_next_number() ],
        ], 200 );
    }

    /**
     * GET /cig/v1/invoices/{id}
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_invoice( WP_REST_Request $request ): WP_REST_Response {
        $id = (int) $request->get_param( 'id' );

        $invoice_data = CIG()->invoice_manager->get_invoice( $id );
        if ( ! $invoice_data ) {
            return new WP_REST_Response( [
                'code'    => 'not_found',
                'message' => __( 'Invoice not found.', 'cig' ),
            ], 404 );
        }

        return new WP_REST_Response( [
            'data' => $this->format_invoice(
                $invoice_data['invoice'],
                $invoice_data['items'],
                $invoice_data['payments']
            ),
        ], 200 );
    }

    /**
     * POST /cig/v1/invoices
     *
     * Expected JSON body (camelCase):
     * {
     *   "number": "N25000001",   // optional — auto-generated if omitted/invalid
     *   "buyer": { "name", "taxId", "phone", "email", "address" },
     *   "items": [ { "productId", "name", "sku", "qty", "price", "total",
     *                "itemStatus", "warranty", "reservationDays", "image", "description" } ],
     *   "payments": [ { "amount", "date", "method", "comment", "userId" } ],
     *   "generalNote": "",
     *   "soldDate": ""
     * }
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function create_invoice( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $body = $request->get_json_params() ?: [];

        // 1. Validate buyer
        $buyer = $this->parse_buyer_input( $body['buyer'] ?? [] );
        if ( is_wp_error( $buyer ) ) {
            return new WP_REST_Response( [
                'code'    => $buyer->get_error_code(),
                'message' => $buyer->get_error_message(),
            ], 400 );
        }

        // 2. Parse payments → determine status
        $payments = $this->parse_payments_input( $body['payments'] ?? [] );
        $status   = $this->determine_status( $payments );

        // Guard: explicit fictive request when payments exist
        if ( sanitize_text_field( $body['status'] ?? '' ) === 'fictive' && $status === 'standard' ) {
            return new WP_REST_Response( [
                'code'    => 'payments_prevent_fictive',
                'message' => __( 'Cannot set invoice to fictive when payments exist. Remove all payments first.', 'cig' ),
            ], 400 );
        }

        // 3. Parse items
        $raw_items = array_values( array_filter(
            (array) ( $body['items'] ?? [] ),
            fn( $i ) => ! empty( $i['name'] ?? '' )
        ) );
        if ( empty( $raw_items ) ) {
            return new WP_REST_Response( [
                'code'    => 'no_items',
                'message' => __( 'At least one product is required.', 'cig' ),
            ], 400 );
        }
        $items = $this->parse_items_input( $raw_items, $status );

        // 4. Stock validation for standard invoices
        if ( $status === 'standard' ) {
            $items_for_stock = $this->items_to_stock_format( $items );
            $stock_errors    = CIG()->stock->validate_stock( $items_for_stock, 0 );
            if ( $stock_errors ) {
                return new WP_REST_Response( [
                    'code'    => 'stock_error',
                    'message' => __( 'Stock validation failed.', 'cig' ),
                    'errors'  => $stock_errors,
                ], 400 );
            }
        }

        // 5. Ensure unique invoice number
        $number = CIG_Invoice::ensure_unique_number( sanitize_text_field( $body['number'] ?? '' ) );

        // 6. Sync customer
        $customer_id = CIG()->customers->sync_customer( $buyer );
        if ( ! $customer_id ) {
            return new WP_REST_Response( [
                'code'    => 'customer_sync_failed',
                'message' => __( 'Failed to sync customer data.', 'cig' ),
            ], 500 );
        }

        // 7. Compute totals & lifecycle
        $total_amount     = $this->calculate_total( $items );
        $lifecycle_status = $this->calculate_lifecycle_status( $status, $items );
        $sale_date        = $this->calculate_sale_date( $status, $payments );

        // 8. Create via manager
        $invoice_id = CIG()->invoice_manager->create_invoice( [
            'invoice_number'   => $number,
            'customer_id'      => $customer_id,
            'status'           => $status,
            'lifecycle_status' => $lifecycle_status,
            'total_amount'     => $total_amount,
            'paid_amount'      => 0, // Recalculated inside manager from payments
            'author_id'        => get_current_user_id(),
            'general_note'     => sanitize_textarea_field( $body['generalNote'] ?? '' ),
            'sold_date'        => sanitize_text_field( $body['soldDate'] ?? '' ),
            'items'            => $items,
            'payments'         => $payments,
        ] );

        if ( is_wp_error( $invoice_id ) ) {
            return new WP_REST_Response( [
                'code'    => $invoice_id->get_error_code(),
                'message' => $invoice_id->get_error_message(),
            ], 500 );
        }

        // 9. Override sale_date: manager sets current_time() internally;
        //    we need the payment-date-based value instead.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->update(
            $this->t_invoices,
            [ 'sale_date' => $sale_date ],
            [ 'id' => $invoice_id ],
            [ '%s' ],
            [ '%d' ]
        );

        // 10. Update stock reservations
        $new_stock_items = ( $status === 'fictive' ) ? [] : $this->items_to_stock_format( $items );
        CIG()->stock->update_invoice_reservations( $invoice_id, [], $new_stock_items );

        // 11. Return created invoice
        $invoice_data = CIG()->invoice_manager->get_invoice( $invoice_id );

        return new WP_REST_Response( [
            'data' => $this->format_invoice(
                $invoice_data['invoice'],
                $invoice_data['items'],
                $invoice_data['payments']
            ),
        ], 201 );
    }

    /**
     * PUT /cig/v1/invoices/{id}
     *
     * Same body shape as POST. Partial updates are NOT supported — send the
     * full invoice on every PUT (mirrors the legacy AJAX "cig_update_invoice" action).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function update_invoice( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $id   = (int) $request->get_param( 'id' );
        $body = $request->get_json_params() ?: [];

        // Fetch existing invoice
        $existing_data = CIG()->invoice_manager->get_invoice( $id );
        if ( ! $existing_data ) {
            return new WP_REST_Response( [ 'code' => 'not_found', 'message' => __( 'Invoice not found.', 'cig' ) ], 404 );
        }
        $existing = $existing_data['invoice'];

        // Block editing completed invoices for non-admins
        if ( ( $existing['lifecycle_status'] ?? '' ) === 'completed' && ! current_user_can( 'administrator' ) ) {
            return new WP_REST_Response( [
                'code'    => 'forbidden',
                'message' => __( 'Editing a completed invoice is not allowed.', 'cig' ),
            ], 403 );
        }

        // Validate buyer
        $buyer = $this->parse_buyer_input( $body['buyer'] ?? [] );
        if ( is_wp_error( $buyer ) ) {
            return new WP_REST_Response( [
                'code'    => $buyer->get_error_code(),
                'message' => $buyer->get_error_message(),
            ], 400 );
        }

        // Parse payments → determine status
        $payments = $this->parse_payments_input( $body['payments'] ?? [] );
        $status   = $this->determine_status( $payments );

        if ( sanitize_text_field( $body['status'] ?? '' ) === 'fictive' && $status === 'standard' ) {
            return new WP_REST_Response( [
                'code'    => 'payments_prevent_fictive',
                'message' => __( 'Cannot set invoice to fictive when payments exist. Remove all payments first.', 'cig' ),
            ], 400 );
        }

        // Parse items
        $raw_items = array_values( array_filter(
            (array) ( $body['items'] ?? [] ),
            fn( $i ) => ! empty( $i['name'] ?? '' )
        ) );
        if ( empty( $raw_items ) ) {
            return new WP_REST_Response( [ 'code' => 'no_items', 'message' => __( 'At least one product is required.', 'cig' ) ], 400 );
        }
        $items = $this->parse_items_input( $raw_items, $status );

        // Stock validation
        if ( $status === 'standard' ) {
            $items_for_stock = $this->items_to_stock_format( $items );
            $stock_errors    = CIG()->stock->validate_stock( $items_for_stock, $id );
            if ( $stock_errors ) {
                return new WP_REST_Response( [
                    'code'    => 'stock_error',
                    'message' => __( 'Stock validation failed.', 'cig' ),
                    'errors'  => $stock_errors,
                ], 400 );
            }
        }

        // Ensure unique invoice number (skip the current invoice's own number)
        $number = CIG_Invoice::ensure_unique_number( sanitize_text_field( $body['number'] ?? '' ), $id );

        // Sync customer
        $customer_id = CIG()->customers->sync_customer( $buyer );
        if ( ! $customer_id ) {
            return new WP_REST_Response( [ 'code' => 'customer_sync_failed', 'message' => __( 'Failed to sync customer data.', 'cig' ) ], 500 );
        }

        // Compute totals & lifecycle
        $total_amount     = $this->calculate_total( $items );
        $lifecycle_status = $this->calculate_lifecycle_status( $status, $items );

        // Capture old stock-format items before overwriting
        $old_stock_items = $this->db_items_to_stock_format( $existing_data['items'] );

        // Determine sale_date override (mirrors update_invoice_in_manager() logic):
        //   fictive             → null always
        //   transitioning or no date yet → payment-date-based value
        //   already standard with date   → preserve existing (don't override)
        $old_status      = $existing['status'] ?? 'fictive';
        $old_sale_date   = $existing['sale_date'] ?? null;
        $do_sale_date_override = false;
        $sale_date_value       = null;

        if ( $status === 'fictive' ) {
            $do_sale_date_override = true;
            $sale_date_value       = null;
        } elseif ( $old_status === 'fictive' || empty( $old_sale_date ) ) {
            $do_sale_date_override = true;
            $sale_date_value       = $this->calculate_sale_date( $status, $payments );
        }

        // Update via manager
        $result = CIG()->invoice_manager->update_invoice( $id, [
            'invoice_number'   => $number,
            'customer_id'      => $customer_id,
            'status'           => $status,
            'lifecycle_status' => $lifecycle_status,
            'total_amount'     => $total_amount,
            'general_note'     => sanitize_textarea_field( $body['generalNote'] ?? '' ),
            'sold_date'        => sanitize_text_field( $body['soldDate'] ?? '' ),
            'items'            => $items,
            'payments'         => $payments,
        ] );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [
                'code'    => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 500 );
        }

        // Apply sale_date override if needed
        if ( $do_sale_date_override ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->update(
                $this->t_invoices,
                [ 'sale_date' => $sale_date_value ],
                [ 'id' => $id ],
                [ '%s' ],
                [ '%d' ]
            );
        }

        // Sync stock reservations (diff old vs new)
        $new_stock_items = ( $status === 'fictive' ) ? [] : $this->items_to_stock_format( $items );
        CIG()->stock->update_invoice_reservations( $id, $old_stock_items, $new_stock_items );

        // Return updated invoice
        $invoice_data = CIG()->invoice_manager->get_invoice( $id );

        return new WP_REST_Response( [
            'data' => $this->format_invoice(
                $invoice_data['invoice'],
                $invoice_data['items'],
                $invoice_data['payments']
            ),
        ], 200 );
    }

    /**
     * DELETE /cig/v1/invoices/{id}
     *
     * Purges stock reservations then hard-deletes from all custom tables.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function delete_invoice( WP_REST_Request $request ): WP_REST_Response {
        $id = (int) $request->get_param( 'id' );

        if ( ! CIG()->invoice_manager->get_invoice( $id ) ) {
            return new WP_REST_Response( [ 'code' => 'not_found', 'message' => __( 'Invoice not found.', 'cig' ) ], 404 );
        }

        // Purge all stock reservations for this invoice before deleting rows
        CIG()->stock->purge_invoice_reservations( $id );

        $result = CIG()->invoice_manager->delete_invoice( $id );
        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [
                'code'    => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 500 );
        }

        return new WP_REST_Response( [ 'data' => [ 'deleted' => true, 'id' => $id ] ], 200 );
    }

    /**
     * POST /cig/v1/invoices/{id}/toggle-status
     *
     * Body: { "status": "standard"|"fictive" }
     *
     * Replicates CIG_Ajax_Invoices::toggle_invoice_status():
     *   - Blocks switch to fictive when payments exist
     *   - Validates stock before activating
     *   - Updates sale_date based on latest payment date when activating
     *   - Purges reservations when deactivating
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function toggle_status( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $id         = (int) $request->get_param( 'id' );
        $body       = $request->get_json_params() ?: [];
        $new_status = sanitize_text_field( $body['status'] ?? '' );

        if ( ! in_array( $new_status, [ 'standard', 'fictive' ], true ) ) {
            return new WP_REST_Response( [ 'code' => 'invalid_status', 'message' => __( 'Status must be "standard" or "fictive".', 'cig' ) ], 400 );
        }

        $existing_data = CIG()->invoice_manager->get_invoice( $id );
        if ( ! $existing_data ) {
            return new WP_REST_Response( [ 'code' => 'not_found', 'message' => __( 'Invoice not found.', 'cig' ) ], 404 );
        }

        $existing   = $existing_data['invoice'];
        $old_status = $existing['status'] ?? 'fictive';
        $payments   = $existing_data['payments'];

        // Block switching to fictive when payments exist (real cash OR consignment)
        if ( $new_status === 'fictive' ) {
            $real_cash       = 0;
            $has_consignment = false;
            foreach ( $payments as $p ) {
                $amt = floatval( $p['amount'] ?? 0 );
                if ( $amt > 0.001 ) {
                    if ( strtolower( $p['method'] ?? '' ) === 'consignment' ) {
                        $has_consignment = true;
                    } else {
                        $real_cash += $amt;
                    }
                }
            }
            if ( $real_cash > 0.001 || $has_consignment ) {
                return new WP_REST_Response( [
                    'code'    => 'payments_prevent_fictive',
                    'message' => __( 'Cannot set invoice to fictive when payments exist. Remove all payments first.', 'cig' ),
                ], 400 );
            }
        }

        // Stock validation when activating
        if ( $new_status === 'standard' ) {
            $items_for_stock = $this->db_items_to_stock_format( $existing_data['items'] );
            $stock_errors    = CIG()->stock->validate_stock( $items_for_stock, $id );
            if ( $stock_errors ) {
                return new WP_REST_Response( [
                    'code'    => 'stock_error',
                    'message' => __( 'Stock validation failed.', 'cig' ),
                    'errors'  => $stock_errors,
                ], 400 );
            }
        }

        // Build DB update (direct — bypasses manager's conditional date logic)
        $update_data   = [ 'status' => $new_status ];
        $update_format = [ '%s' ];

        if ( $new_status === 'fictive' ) {
            $update_data['sale_date'] = null;
            $update_format[]          = '%s';
        } elseif ( $old_status === 'fictive' && $new_status === 'standard' ) {
            $update_data['sale_date'] = $this->calculate_sale_date( 'standard', $payments );
            $update_format[]          = '%s';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->update( $this->t_invoices, $update_data, [ 'id' => $id ], $update_format, [ '%d' ] );

        // Sync stock reservations
        $db_items        = $existing_data['items'];
        $old_stock_items = ( $old_status === 'fictive' ) ? [] : $this->db_items_to_stock_format( $db_items );
        $new_stock_items = ( $new_status === 'fictive' ) ? [] : $this->db_items_to_stock_format( $db_items );
        CIG()->stock->update_invoice_reservations( $id, $old_stock_items, $new_stock_items );

        // Explicitly purge all reservations when deactivating (ensures cleanup)
        if ( $new_status === 'fictive' && $old_status !== 'fictive' ) {
            CIG()->stock->purge_invoice_reservations( $id );
        }

        return new WP_REST_Response( [ 'data' => [ 'id' => $id, 'status' => $new_status ] ], 200 );
    }

    /**
     * POST /cig/v1/invoices/{id}/mark-sold
     *
     * Replicates CIG_Ajax_Invoices::mark_as_sold():
     *   Transitions all reserved items → sold, sets lifecycle=completed,
     *   status=standard, updates stock reservations.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function mark_sold( WP_REST_Request $request ): WP_REST_Response {
        $id = (int) $request->get_param( 'id' );

        $existing_data = CIG()->invoice_manager->get_invoice( $id );
        if ( ! $existing_data ) {
            return new WP_REST_Response( [ 'code' => 'not_found', 'message' => __( 'Invoice not found.', 'cig' ) ], 404 );
        }

        $db_items    = $existing_data['items'];
        $has_reserved = false;
        foreach ( $db_items as $item ) {
            if ( ( $item['item_status'] ?? '' ) === 'reserved' ) {
                $has_reserved = true;
                break;
            }
        }

        if ( ! $has_reserved ) {
            return new WP_REST_Response( [
                'code'    => 'no_reserved_items',
                'message' => __( 'No reserved items found to mark as sold.', 'cig' ),
            ], 400 );
        }

        // Snapshot old stock format before status change
        $old_stock_items = $this->db_items_to_stock_format( $db_items );

        // Mark via manager: changes reserved→sold in cig_invoice_items,
        // sets lifecycle=completed, status=standard in cig_invoices.
        $result = CIG()->invoice_manager->mark_as_sold( $id );
        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [
                'code'    => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 500 );
        }

        // Build updated item list (reserved→sold) for stock reservation diff
        $updated_db_items = array_map( function ( $item ) {
            if ( ( $item['item_status'] ?? '' ) === 'reserved' ) {
                $item['item_status']      = 'sold';
                $item['reservation_days'] = 0;
            }
            return $item;
        }, $db_items );

        $new_stock_items = $this->db_items_to_stock_format( $updated_db_items );
        CIG()->stock->update_invoice_reservations( $id, $old_stock_items, $new_stock_items );

        // Return freshly-fetched invoice to reflect DB state
        $invoice_data = CIG()->invoice_manager->get_invoice( $id );

        return new WP_REST_Response( [
            'data' => $this->format_invoice(
                $invoice_data['invoice'],
                $invoice_data['items'],
                $invoice_data['payments']
            ),
        ], 200 );
    }

    // -------------------------------------------------------------------------
    // Permission callbacks
    // -------------------------------------------------------------------------

    /**
     * Any authenticated user may read invoices.
     *
     * @return true|WP_Error
     */
    public function require_login() {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'not_authenticated', __( 'You must be logged in.', 'cig' ), [ 'status' => 401 ] );
        }
        return true;
    }

    /**
     * Only WooCommerce managers and WordPress administrators may write invoices.
     *
     * @return true|WP_Error
     */
    public function require_manage_woocommerce() {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'not_authenticated', __( 'You must be logged in.', 'cig' ), [ 'status' => 401 ] );
        }
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'administrator' ) ) {
            return new WP_Error( 'forbidden', __( 'You do not have permission to perform this action.', 'cig' ), [ 'status' => 403 ] );
        }
        return true;
    }

    // -------------------------------------------------------------------------
    // Business logic helpers (mirrors CIG_Ajax_Invoices)
    // -------------------------------------------------------------------------

    /**
     * Auto-determine invoice status from payments array.
     * standard = any payment with amount > 0; fictive = no payments.
     *
     * @param array $payments Snake-case payment rows
     * @return string 'standard'|'fictive'
     */
    private function determine_status( array $payments ): string {
        foreach ( $payments as $p ) {
            if ( floatval( $p['amount'] ?? 0 ) > 0 ) {
                return 'standard';
            }
        }
        return 'fictive';
    }

    /**
     * Calculate sale_date: latest payment date + current H:i:s.
     * Returns null for fictive invoices.
     *
     * @param string $status   'standard'|'fictive'
     * @param array  $payments Payment rows (must have 'date' key)
     * @return string|null
     */
    private function calculate_sale_date( string $status, array $payments ): ?string {
        if ( $status !== 'standard' ) {
            return null;
        }
        $latest = $this->get_latest_payment_date( $payments );
        $hms    = current_time( 'H:i:s' );
        return $latest ? ( $latest . ' ' . $hms ) : current_time( 'mysql' );
    }

    /**
     * Get the latest date string from a payments array.
     *
     * @param array $payments
     * @return string|null Y-m-d or Y-m-d H:i:s; null if no valid dates
     */
    private function get_latest_payment_date( array $payments ): ?string {
        $latest_ts   = 0;
        $latest_date = null;
        foreach ( $payments as $p ) {
            $date = $p['date'] ?? '';
            if ( ! empty( $date ) ) {
                $ts = strtotime( $date );
                if ( $ts !== false && $ts > $latest_ts ) {
                    $latest_ts   = $ts;
                    $latest_date = $date;
                }
            }
        }
        return $latest_date;
    }

    /**
     * Calculate lifecycle status from items and invoice status.
     *
     * fictive → always 'unfinished'
     * standard:
     *   all active items sold     → 'completed'
     *   all active items reserved → 'reserved'
     *   mixed                     → 'unfinished'
     *
     * @param string $status Invoice status
     * @param array  $items  Items (with 'item_status' or 'status' key)
     * @return string
     */
    private function calculate_lifecycle_status( string $status, array $items ): string {
        if ( $status === 'fictive' ) {
            return 'unfinished';
        }
        $active = $sold = $reserved = 0;
        foreach ( $items as $item ) {
            $st = strtolower( $item['item_status'] ?? $item['status'] ?? 'none' );
            if ( $st !== 'canceled' && $st !== 'none' ) {
                $active++;
                if ( $st === 'sold' ) {
                    $sold++;
                } elseif ( $st === 'reserved' ) {
                    $reserved++;
                }
            }
        }
        if ( $active > 0 && $sold === $active ) {
            return 'completed';
        }
        if ( $active > 0 && $reserved === $active ) {
            return 'reserved';
        }
        return 'unfinished';
    }

    /**
     * Sum item totals (includes 'none'-status items for display in fictive invoices).
     *
     * @param array $items
     * @return float
     */
    private function calculate_total( array $items ): float {
        $total = 0.0;
        foreach ( $items as $item ) {
            $st = strtolower( $item['item_status'] ?? $item['status'] ?? 'none' );
            if ( $st !== 'canceled' ) {
                $qty    = floatval( $item['qty'] ?? $item['quantity'] ?? 0 );
                $price  = floatval( $item['price'] ?? 0 );
                $total += floatval( $item['total'] ?? ( $qty * $price ) );
            }
        }
        return $total;
    }

    // -------------------------------------------------------------------------
    // Input parsing helpers
    // -------------------------------------------------------------------------

    /**
     * Parse and validate buyer from camelCase REST input.
     *
     * @param array $raw
     * @return array|WP_Error Snake-case buyer array, or WP_Error if required fields missing
     */
    private function parse_buyer_input( array $raw ) {
        $buyer = [
            'name'    => sanitize_text_field( $raw['name']    ?? '' ),
            'tax_id'  => sanitize_text_field( $raw['taxId']   ?? $raw['tax_id'] ?? '' ),
            'phone'   => sanitize_text_field( $raw['phone']   ?? '' ),
            'email'   => sanitize_email(      $raw['email']   ?? '' ),
            'address' => sanitize_text_field( $raw['address'] ?? '' ),
        ];
        if ( empty( $buyer['name'] ) || empty( $buyer['tax_id'] ) || empty( $buyer['phone'] ) ) {
            return new WP_Error(
                'missing_buyer_fields',
                __( 'Buyer name, tax ID, and phone are required.', 'cig' )
            );
        }
        return $buyer;
    }

    /**
     * Parse camelCase items from REST input → snake_case rows accepted by
     * CIG_Invoice_Manager and CIG_Stock_Manager (provides both key aliases).
     *
     * Also enforces item statuses:
     *   fictive → item_status = 'none', reservation_days = 0
     *   standard → 'none'/'empty' → 'reserved'
     *
     * @param array  $raw_items
     * @param string $invoice_status
     * @return array
     */
    private function parse_items_input( array $raw_items, string $invoice_status ): array {
        $items = [];
        foreach ( $raw_items as $ri ) {
            if ( empty( $ri['name'] ?? '' ) ) {
                continue;
            }
            $qty   = floatval( $ri['qty'] ?? $ri['quantity'] ?? 0 );
            $price = floatval( $ri['price'] ?? 0 );
            $total = floatval( $ri['total'] ?? 0 );
            if ( $total <= 0 && $qty > 0 && $price > 0 ) {
                $total = $qty * $price;
            }

            $item_status      = sanitize_text_field( $ri['itemStatus'] ?? $ri['item_status'] ?? $ri['status'] ?? 'none' );
            $reservation_days = intval( $ri['reservationDays'] ?? $ri['reservation_days'] ?? 0 );

            if ( $invoice_status === 'fictive' ) {
                $item_status      = 'none';
                $reservation_days = 0;
            } elseif ( $item_status === 'none' || $item_status === '' ) {
                $item_status = 'reserved';
            }

            // Sanitize image — only keep valid http(s) URLs
            $raw_image = $ri['image'] ?? '';
            $image     = '';
            if ( ! empty( $raw_image ) ) {
                $sanitized = esc_url_raw( $raw_image );
                if ( preg_match( '/^https?:\/\//i', $sanitized ) ) {
                    $image = $sanitized;
                }
            }

            $name = sanitize_text_field( $ri['name'] ?? '' );

            $items[] = [
                // Manager primary keys
                'product_id'        => intval( $ri['productId'] ?? $ri['product_id'] ?? 0 ),
                'product_name'      => $name,
                'sku'               => sanitize_text_field( $ri['sku'] ?? '' ),
                'description'       => sanitize_textarea_field( $ri['description'] ?? $ri['desc'] ?? '' ),
                'quantity'          => $qty,
                'price'             => $price,
                'total'             => $total,
                'item_status'       => $item_status,
                'warranty_duration' => sanitize_text_field( $ri['warranty'] ?? $ri['warrantyDuration'] ?? $ri['warranty_duration'] ?? '' ),
                'reservation_days'  => $reservation_days,
                'image'             => $image,
                // Aliases for manager / stock-manager fallbacks
                'name'    => $name,
                'qty'     => $qty,
                'status'  => $item_status,
                'warranty'=> sanitize_text_field( $ri['warranty'] ?? $ri['warrantyDuration'] ?? $ri['warranty_duration'] ?? '' ),
            ];
        }
        return $items;
    }

    /**
     * Parse camelCase payments from REST input → snake_case rows.
     * Skips entries with amount effectively equal to zero.
     * Negative amounts are intentional (refund payments) and are allowed through.
     *
     * @param array $raw_payments
     * @return array
     */
    private function parse_payments_input( array $raw_payments ): array {
        $payments = [];
        foreach ( $raw_payments as $rp ) {
            $amount = floatval( $rp['amount'] ?? 0 );
            if ( abs( $amount ) < 0.01 ) {
                continue;
            }
            $date = sanitize_text_field( $rp['date'] ?? '' );
            if ( empty( $date ) ) {
                $date = current_time( 'Y-m-d' );
            }
            $payments[] = [
                'amount'  => $amount,
                'date'    => $date,
                'method'  => sanitize_text_field( $rp['method'] ?? 'other' ),
                'user_id' => intval( $rp['userId'] ?? $rp['user_id'] ?? get_current_user_id() ),
                'comment' => sanitize_textarea_field( $rp['comment'] ?? '' ),
            ];
        }
        return $payments;
    }

    /**
     * Convert parsed items (post-parse_items_input) to CIG_Stock_Manager format.
     * Stock manager needs: product_id, qty, status, reservation_days.
     *
     * @param array $items
     * @return array
     */
    private function items_to_stock_format( array $items ): array {
        return array_map( function ( $item ) {
            return [
                'product_id'       => intval( $item['product_id'] ?? $item['productId'] ?? 0 ),
                'qty'              => floatval( $item['qty'] ?? $item['quantity'] ?? 0 ),
                'status'           => $item['item_status'] ?? $item['status'] ?? 'none',
                'reservation_days' => intval( $item['reservation_days'] ?? $item['reservationDays'] ?? 0 ),
            ];
        }, $items );
    }

    /**
     * Convert raw DB item rows (from CIG_Invoice_Manager) to CIG_Stock_Manager format.
     * DB rows use 'quantity' and 'item_status'; stock manager expects 'qty' and 'status'.
     *
     * @param array $db_items  Rows from cig_invoice_items
     * @return array
     */
    private function db_items_to_stock_format( array $db_items ): array {
        return array_map( function ( $item ) {
            return [
                'product_id'       => intval( $item['product_id'] ?? 0 ),
                'qty'              => floatval( $item['quantity'] ?? 0 ),
                'status'           => $item['item_status'] ?? 'none',
                'reservation_days' => intval( $item['reservation_days'] ?? 0 ),
            ];
        }, $db_items );
    }

    // -------------------------------------------------------------------------
    // Response formatters  (DB snake_case → API camelCase)
    // -------------------------------------------------------------------------

    /**
     * Format a cig_invoices row + items + payments to the camelCase shape
     * the Vue auth store expects.
     *
     * @param array $invoice  Row from cig_invoices
     * @param array $items    Rows from cig_invoice_items
     * @param array $payments Rows from cig_payments
     * @return array
     */
    private function format_invoice( array $invoice, array $items, array $payments ): array {
        return [
            'id'               => (int) $invoice['id'],
            'number'           => $invoice['invoice_number'],
            'customerId'       => (int) $invoice['customer_id'],
            'status'           => $invoice['status'],
            'lifecycleStatus'  => $invoice['lifecycle_status'],
            'totalAmount'      => (float) $invoice['total_amount'],
            'paidAmount'       => (float) $invoice['paid_amount'],
            'createdAt'        => $invoice['created_at'],
            'saleDate'         => $invoice['sale_date'],
            'soldDate'         => $invoice['sold_date'],
            'authorId'         => (int) $invoice['author_id'],
            'generalNote'      => $invoice['general_note'] ?? '',
            'isRsUploaded'     => (bool) ( $invoice['is_rs_uploaded'] ?? false ),
            'accountantNote'   => $invoice['accountant_note']   ?? null,
            'rsUploadedBy'     => isset( $invoice['rs_uploaded_by'] )   ? (int) $invoice['rs_uploaded_by']   : null,
            'rsUploadedDate'   => $invoice['rs_uploaded_date']   ?? null,
            'isCreditChecked'  => (bool) ( $invoice['is_credit_checked']  ?? false ),
            'isReceiptChecked' => (bool) ( $invoice['is_receipt_checked'] ?? false ),
            'isCorrected'      => (bool) ( $invoice['is_corrected']       ?? false ),
            'items'            => array_map( [ $this, 'format_item' ],    $items ),
            'payments'         => array_map( [ $this, 'format_payment' ], $payments ),
        ];
    }

    /**
     * Format a cig_invoice_items row.
     *
     * @param array $item
     * @return array
     */
    private function format_item( array $item ): array {
        return [
            'id'              => (int) $item['id'],
            'productId'       => (int) $item['product_id'],
            'name'            => $item['product_name'],
            'sku'             => $item['sku'],
            'description'     => $item['description'],
            'qty'             => (float) $item['quantity'],
            'price'           => (float) $item['price'],
            'total'           => (float) $item['total'],
            'itemStatus'      => $item['item_status'],
            'warranty'        => $item['warranty_duration'],
            'reservationDays' => (int) $item['reservation_days'],
            'image'           => $item['image'],
        ];
    }

    /**
     * Format a cig_payments row.
     *
     * @param array $payment
     * @return array
     */
    private function format_payment( array $payment ): array {
        return [
            'id'      => (int) $payment['id'],
            'amount'  => (float) $payment['amount'],
            'date'    => $payment['date'],
            'method'  => $payment['method'],
            'userId'  => (int) $payment['user_id'],
            'comment' => $payment['comment'],
        ];
    }
}
