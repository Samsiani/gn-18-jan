<?php
/**
 * REST API — Product Endpoints
 *
 * GET /cig/v1/products — paginated WooCommerce product list with real stock + CIG reserved qty.
 *                        Mirrors search_products_table() from CIG_Ajax_Products including:
 *                        - SKU exclusion regex (^GN20ST) from the business rule
 *                        - Brand + attribute-based description assembly
 *                        - Variation name flattening
 *
 * @package CIG
 * @since 5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CIG_Rest_Products {

    const NAMESPACE = 'cig/v1';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    // -------------------------------------------------------------------------
    // Route registration
    // -------------------------------------------------------------------------

    public function register_routes() {

        // GET /cig/v1/products
        register_rest_route( self::NAMESPACE, '/products', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'list_products' ],
            'permission_callback' => [ $this, 'require_login' ],
        ] );
    }

    // -------------------------------------------------------------------------
    // Callbacks
    // -------------------------------------------------------------------------

    /**
     * GET /cig/v1/products
     *
     * Query params:
     *   search   — title, content, or SKU LIKE match
     *   sort     — title (default) | price | stock | sku
     *   order    — ASC (default) | DESC
     *   per_page — 1–100, default 20
     *   page     — default 1
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function list_products( WP_REST_Request $request ): WP_REST_Response {
        if ( ! class_exists( 'WC_Product' ) ) {
            return new WP_REST_Response( [
                'data' => [],
                'meta' => [ 'total' => 0, 'per_page' => 20, 'current_page' => 1, 'last_page' => 1 ],
            ], 200 );
        }

        $per_page = max( 1, min( 100, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );
        $page     = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
        $search   = sanitize_text_field( $request->get_param( 'search' ) ?? '' );
        $sort_col = sanitize_text_field( $request->get_param( 'sort' )   ?? 'title' );
        $sort_dir = strtoupper( $request->get_param( 'order' ) ?? 'ASC' ) === 'DESC' ? 'DESC' : 'ASC';

        $args = [
            'post_type'      => [ 'product', 'product_variation' ],
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'fields'         => 'ids',
        ];

        switch ( $sort_col ) {
            case 'price': $args['meta_key'] = '_price'; $args['orderby'] = 'meta_value_num'; break;
            case 'stock': $args['meta_key'] = '_stock'; $args['orderby'] = 'meta_value_num'; break;
            case 'sku':   $args['meta_key'] = '_sku';   $args['orderby'] = 'meta_value';     break;
            default:      $args['orderby']  = 'title';                                        break;
        }
        $args['order'] = $sort_dir;

        // Attach posts_clauses filter for search + GN20ST exclusion
        $filter_handler = function ( $clauses ) use ( $search ) {
            global $wpdb;
            // Join postmeta for SKU access once (aliased to avoid conflicts with other joins)
            $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS cig_sku_rest ON ( {$wpdb->posts}.ID = cig_sku_rest.post_id AND cig_sku_rest.meta_key = '_sku' ) ";
            // Exclude GN20ST SKUs (business rule: these are physical stock items managed separately)
            $clauses['where'] .= " AND ( cig_sku_rest.meta_value IS NULL OR cig_sku_rest.meta_value NOT REGEXP '^GN20ST' ) ";
            if ( ! empty( $search ) ) {
                $like = '%' . $wpdb->esc_like( $search ) . '%';
                $clauses['where'] .= $wpdb->prepare(
                    " AND ( ( {$wpdb->posts}.post_title LIKE %s ) OR ( {$wpdb->posts}.post_content LIKE %s ) OR ( cig_sku_rest.meta_value LIKE %s ) )",
                    $like, $like, $like
                );
            }
            $clauses['groupby'] = "{$wpdb->posts}.ID";
            return $clauses;
        };

        add_filter( 'posts_clauses', $filter_handler );
        $query = new WP_Query( $args );
        remove_filter( 'posts_clauses', $filter_handler );

        $products = [];
        foreach ( $query->posts as $product_id ) {
            $p = wc_get_product( $product_id );
            // Skip variable parent products — only expose simple / variation leaves
            if ( ! $p || $p->is_type( 'variable' ) ) {
                continue;
            }
            $payload = $this->build_product_payload( $product_id, $p );
            if ( $payload ) {
                $products[] = $payload;
            }
        }

        return new WP_REST_Response( [
            'data' => $products,
            'meta' => [
                'total'        => (int) $query->found_posts,
                'per_page'     => $per_page,
                'current_page' => $page,
                'last_page'    => max( 1, (int) $query->max_num_pages ),
            ],
        ], 200 );
    }

    // -------------------------------------------------------------------------
    // Product payload builder (mirrors CIG_Ajax_Products::build_product_payload)
    // -------------------------------------------------------------------------

    /**
     * Build the camelCase product payload consumed by the Vue SPA.
     *
     * @param int             $pid
     * @param \WC_Product|null $p   Pre-fetched product object (optional)
     * @return array|null
     */
    private function build_product_payload( int $pid, $p = null ): ?array {
        try {
            if ( ! $p ) {
                $p = wc_get_product( $pid );
            }
            if ( ! $p ) {
                return null;
            }

            $settings = get_option( 'cig_settings', [] );
            $brand_attr = $settings['brand_attribute'] ?? 'pa_prod-brand';
            $excludes   = (array) ( $settings['exclude_spec_attributes'] ?? [ 'pa_prod-brand', 'pa_product-condition' ] );

            // ---- Brand ----
            $brand = '';
            if ( $brand_attr ) {
                $brand_val = $p->get_attribute( $brand_attr );
                if ( $brand_val ) {
                    $brand = $brand_val;
                } else {
                    $terms = wp_get_post_terms( $p->get_parent_id() ?: $pid, $brand_attr, [ 'fields' => 'names' ] );
                    if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                        $brand = $terms[0];
                    }
                }
            }

            // ---- Description from WC attributes ----
            $lines = [];
            foreach ( $p->get_attributes() as $attr ) {
                if ( ! is_a( $attr, 'WC_Product_Attribute' ) ) {
                    continue;
                }
                $attr_name = $attr->get_name();
                $tax_slug  = taxonomy_exists( $attr_name ) ? $attr_name : sanitize_title( $attr_name );
                if ( in_array( $tax_slug, $excludes, true ) ) {
                    continue;
                }
                $val = $p->get_attribute( $attr_name );
                if ( ! empty( $val ) ) {
                    $lines[] = '• ' . wc_attribute_label( $attr_name ) . ': ' . $val;
                }
            }
            if ( ! empty( $lines ) ) {
                $description = implode( "\n", $lines );
            } else {
                $post_obj    = get_post( $p->get_parent_id() ?: $pid );
                $description = $post_obj ? wp_strip_all_tags( $post_obj->post_content ) : '';
            }

            // ---- Name (flatten variation attributes) ----
            $name = $p->get_name();
            if ( $p->is_type( 'variation' ) ) {
                $parent      = wc_get_product( $p->get_parent_id() );
                $parent_name = $parent ? $parent->get_name() : $name;
                $attrs_list  = [];
                foreach ( $p->get_variation_attributes() as $attr_key => $attr_val ) {
                    if ( empty( $attr_val ) ) {
                        continue;
                    }
                    $slug_decoded = urldecode( $attr_val );
                    $taxonomy     = str_replace( 'attribute_', '', $attr_key );
                    $term_name    = $slug_decoded;
                    if ( taxonomy_exists( $taxonomy ) ) {
                        $term = get_term_by( 'slug', $attr_val, $taxonomy );
                        if ( $term && ! is_wp_error( $term ) ) {
                            $term_name = $term->name;
                        }
                    }
                    $attrs_list[] = ! empty( $term_name ) ? $term_name : ucfirst( $slug_decoded );
                }
                $name = ! empty( $attrs_list ) ? $parent_name . ' - ' . implode( ', ', $attrs_list ) : $parent_name;
            }

            // ---- Stock & reserved quantity ----
            $stock    = $p->get_stock_quantity();
            $reserved = ( CIG()->stock ) ? CIG()->stock->get_reserved( $pid ) : 0;
            $available = ( $stock !== null && $stock !== '' ) ? max( 0, $stock - $reserved ) : null;

            // ---- Image (medium thumbnail) ----
            $img    = '';
            $img_id = $p->get_image_id();
            if ( $img_id ) {
                $src = wp_get_attachment_image_src( $img_id, 'medium' );
                if ( $src ) {
                    $img = $src[0];
                }
            }

            return [
                'id'          => $pid,
                'name'        => $name,
                'label'       => $name . ' (SKU: ' . ( $p->get_sku() ?: 'N/A' ) . ')',
                'sku'         => $p->get_sku(),
                'brand'       => $brand,
                'description' => $description,
                'price'       => (float) ( $p->get_price() ?: 0 ),
                'image'       => $img,
                'stock'       => $stock !== null ? (float) $stock : null,
                'reserved'    => (float) $reserved,
                'available'   => $available !== null ? (float) $available : null,
            ];
        } catch ( \Throwable $e ) {
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Permission callback
    // -------------------------------------------------------------------------

    public function require_login() {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'not_authenticated', __( 'You must be logged in.', 'cig' ), [ 'status' => 401 ] );
        }
        return true;
    }
}
