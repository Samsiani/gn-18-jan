<?php
/**
 * Admin Portal Handler
 * Exposes key backend functionality via frontend shortcode for Administrators.
 *
 * @package CIG
 * @since 4.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CIG_Admin_Portal {

    /**
     * Database instance
     *
     * @var mixed
     */
    private $db;

    /**
     * Constructor
     *
     * @param mixed $db Database instance for dependency injection
     */
    public function __construct( $db = null ) {
        $this->db = $db;
        add_shortcode('cig_admin_portal', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    /**
     * Enqueue frontend assets when shortcode is present
     */
    public function enqueue_frontend_assets() {
        global $post;

        // Only load on pages with the shortcode
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'cig_admin_portal')) {
            return;
        }

        // Permission check before loading assets
        if (!current_user_can('manage_options')) {
            return;
        }

        // jQuery UI for autocomplete
        wp_enqueue_script('jquery-ui-autocomplete');
        wp_enqueue_style(
            'cig-jquery-ui',
            'https://code.jquery.com/ui/1.13.3/themes/base/jquery-ui.css',
            [],
            '1.13.3'
        );

        // Chart.js for Statistics
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            [],
            '4.4.0',
            true
        );

        // Statistics CSS & JS
        wp_enqueue_style(
            'cig-statistics',
            CIG_ASSETS_URL . 'css/statistics.css',
            [],
            CIG_VERSION
        );
        wp_enqueue_script(
            'cig-statistics',
            CIG_ASSETS_URL . 'js/statistics.js',
            ['jquery', 'chartjs', 'jquery-ui-autocomplete'],
            CIG_VERSION,
            true
        );

        // Accountant CSS & JS
        wp_enqueue_style(
            'cig-accountant',
            CIG_ASSETS_URL . 'css/accountant.css',
            [],
            CIG_VERSION
        );
        wp_enqueue_script(
            'cig-accountant',
            CIG_ASSETS_URL . 'js/accountant.js',
            ['jquery'],
            CIG_VERSION,
            true
        );

        // Localize scripts
        wp_localize_script('cig-statistics', 'cigStats', [
            'ajax_url'      => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('cig_nonce'),
            'export_nonce'  => wp_create_nonce('cig_export_statistics'),
            'current_user'  => get_current_user_id(),
            'payment_types' => class_exists('CIG_Invoice') ? CIG_Invoice::get_payment_types() : [],
            'i18n' => [
                'loading'                => __('Loading...', 'cig'),
                'no_data'                => __('No data available', 'cig'),
                'error'                  => __('Error loading data', 'cig'),
            ],
            'colors' => [
                'primary' => '#50529d',
                'success' => '#28a745',
                'warning' => '#ffc107',
                'danger'  => '#dc3545',
                'info'    => '#17a2b8',
            ]
        ]);

        wp_localize_script('cig-accountant', 'cigAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('cig_nonce'),
        ]);

        // Admin Portal custom CSS
        wp_enqueue_style(
            'cig-admin-portal',
            CIG_ASSETS_URL . 'css/admin-portal.css',
            ['cig-statistics', 'cig-accountant'],
            CIG_VERSION
        );

        // Admin Portal JS for tab switching
        wp_enqueue_script(
            'cig-admin-portal',
            CIG_ASSETS_URL . 'js/admin-portal.js',
            ['jquery'],
            CIG_VERSION,
            true
        );
    }

    /**
     * Render shortcode output
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_shortcode($atts) {
        // Security check: Only administrators
        if (!current_user_can('manage_options')) {
            return '<div class="cig-notice-error" style="padding:15px;background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;border-radius:4px;">' .
                   esc_html__('Permission Denied. You must be an administrator to view this page.', 'cig') .
                   '</div>';
        }

        ob_start();
        ?>
        <div class="cig-admin-portal-wrapper">
            <!-- Navigation Cards -->
            <div class="cig-portal-nav-cards">
                <div class="cig-portal-card active" data-tab="invoices">
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                    <span class="cig-card-label"><?php esc_html_e('Invoices', 'cig'); ?></span>
                </div>
                <div class="cig-portal-card" data-tab="customers">
                    <span class="dashicons dashicons-businessperson"></span>
                    <span class="cig-card-label"><?php esc_html_e('Customers', 'cig'); ?></span>
                </div>
                <div class="cig-portal-card" data-tab="accountant">
                    <span class="dashicons dashicons-calculator"></span>
                    <span class="cig-card-label"><?php esc_html_e('Accountant', 'cig'); ?></span>
                </div>
                <div class="cig-portal-card" data-tab="stock-requests">
                    <span class="dashicons dashicons-archive"></span>
                    <span class="cig-card-label"><?php esc_html_e('Stock Requests', 'cig'); ?></span>
                </div>
                <div class="cig-portal-card" data-tab="statistics">
                    <span class="dashicons dashicons-chart-area"></span>
                    <span class="cig-card-label"><?php esc_html_e('Statistics', 'cig'); ?></span>
                </div>
            </div>

            <!-- Content Area -->
            <div class="cig-portal-content">
                <!-- Invoices Tab -->
                <div class="cig-portal-tab active" id="cig-portal-tab-invoices">
                    <?php $this->render_invoices_table(); ?>
                </div>

                <!-- Customers Tab -->
                <div class="cig-portal-tab" id="cig-portal-tab-customers">
                    <?php $this->render_customers_table(); ?>
                </div>

                <!-- Accountant Tab (Reuse existing class) -->
                <div class="cig-portal-tab" id="cig-portal-tab-accountant">
                    <?php $this->render_accountant_content(); ?>
                </div>

                <!-- Stock Requests Tab (Reuse existing class) -->
                <div class="cig-portal-tab" id="cig-portal-tab-stock-requests">
                    <?php $this->render_stock_requests_content(); ?>
                </div>

                <!-- Statistics Tab (Reuse existing class) -->
                <div class="cig-portal-tab" id="cig-portal-tab-statistics">
                    <?php $this->render_statistics_content(); ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Invoices table (Custom Post Type: invoice)
     */
    private function render_invoices_table() {
        $args = [
            'post_type'      => 'invoice',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query = new WP_Query($args);

        echo '<h3 class="cig-portal-section-title">' . esc_html__('Recent Invoices', 'cig') . '</h3>';
        
        if (!$query->have_posts()) {
            echo '<p>' . esc_html__('No invoices found.', 'cig') . '</p>';
            return;
        }

        $invoice_manager = function_exists('CIG') && CIG()->invoice_manager ? CIG()->invoice_manager : null;

        echo '<table class="cig-portal-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Invoice #', 'cig') . '</th>';
        echo '<th>' . esc_html__('Customer', 'cig') . '</th>';
        echo '<th>' . esc_html__('Date', 'cig') . '</th>';
        echo '<th>' . esc_html__('Total', 'cig') . '</th>';
        echo '<th>' . esc_html__('Status', 'cig') . '</th>';
        echo '<th>' . esc_html__('Actions', 'cig') . '</th>';
        echo '</tr></thead><tbody>';

        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();

            // Get invoice data
            $invoice_number = '';
            $customer_name = '—';
            $total = 0;

            if ($invoice_manager) {
                $invoice_data = $invoice_manager->get_invoice_by_post_id($post_id);
                $invoice = $invoice_data['invoice'] ?? [];
                $customer = $invoice_data['customer'] ?? [];
                
                $invoice_number = $invoice['invoice_number'] ?? '';
                $customer_name = $customer['name'] ?? '—';
                $total = floatval($invoice['total_amount'] ?? 0);
            }

            if (empty($invoice_number)) {
                $invoice_number = get_post_meta($post_id, '_cig_invoice_number', true);
            }
            if ($customer_name === '—') {
                $customer_name = get_post_meta($post_id, '_cig_buyer_name', true) ?: '—';
            }
            if ($total === 0) {
                $total = floatval(get_post_meta($post_id, '_cig_invoice_total', true));
            }

            $status = get_post_status($post_id);
            $status_class = 'status-' . $status;
            $status_label = ucfirst($status);

            echo '<tr>';
            echo '<td><strong>' . esc_html($invoice_number ?: 'N/A') . '</strong></td>';
            echo '<td>' . esc_html($customer_name) . '</td>';
            echo '<td>' . esc_html(get_the_date('Y-m-d H:i')) . '</td>';
            echo '<td>' . esc_html(number_format($total, 2)) . ' ₾</td>';
            echo '<td><span class="status-badge ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span></td>';
            echo '<td><a href="' . esc_url(get_permalink($post_id)) . '" class="cig-portal-view-link" target="_blank">' . esc_html__('View', 'cig') . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        wp_reset_postdata();
    }

    /**
     * Render Customers table (Custom Post Type: cig_customer)
     */
    private function render_customers_table() {
        $args = [
            'post_type'      => 'cig_customer',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query = new WP_Query($args);

        echo '<h3 class="cig-portal-section-title">' . esc_html__('Recent Customers', 'cig') . '</h3>';
        
        if (!$query->have_posts()) {
            echo '<p>' . esc_html__('No customers found.', 'cig') . '</p>';
            return;
        }

        echo '<table class="cig-portal-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Name', 'cig') . '</th>';
        echo '<th>' . esc_html__('Tax ID', 'cig') . '</th>';
        echo '<th>' . esc_html__('Phone', 'cig') . '</th>';
        echo '<th>' . esc_html__('Email', 'cig') . '</th>';
        echo '<th>' . esc_html__('Date Added', 'cig') . '</th>';
        echo '</tr></thead><tbody>';

        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();

            $name = get_the_title();
            $tax_id = get_post_meta($post_id, '_cig_customer_tax_id', true);
            $phone = get_post_meta($post_id, '_cig_customer_phone', true);
            $email = get_post_meta($post_id, '_cig_customer_email', true);

            echo '<tr>';
            echo '<td><strong>' . esc_html($name ?: '—') . '</strong></td>';
            echo '<td>' . esc_html($tax_id ?: '—') . '</td>';
            echo '<td>' . esc_html($phone ?: '—') . '</td>';
            echo '<td>' . esc_html($email ?: '—') . '</td>';
            echo '<td>' . esc_html(get_the_date('Y-m-d')) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        wp_reset_postdata();
    }

    /**
     * Render Accountant content using existing CIG_Accountant class
     */
    private function render_accountant_content() {
        if (function_exists('CIG') && isset(CIG()->accountant) && method_exists(CIG()->accountant, 'render_shortcode')) {
            // Use the existing shortcode render method
            echo CIG()->accountant->render_shortcode([]);
        } else {
            echo '<p>' . esc_html__('Accountant module is not available.', 'cig') . '</p>';
        }
    }

    /**
     * Render Stock Requests content using existing CIG_Stock_Requests class
     */
    private function render_stock_requests_content() {
        if (function_exists('CIG') && isset(CIG()->stock_requests) && method_exists(CIG()->stock_requests, 'render_page')) {
            // Use output buffering to capture the render_page output
            ob_start();
            CIG()->stock_requests->render_page();
            $content = ob_get_clean();
            
            // Remove the wrap class for frontend display
            $content = str_replace('<div class="wrap">', '<div class="cig-stock-requests-frontend">', $content);
            echo $content;
        } else {
            echo '<p>' . esc_html__('Stock Requests module is not available.', 'cig') . '</p>';
        }
    }

    /**
     * Render Statistics content using existing CIG_Statistics class
     */
    private function render_statistics_content() {
        if (function_exists('CIG') && isset(CIG()->statistics) && method_exists(CIG()->statistics, 'render_page')) {
            // Use output buffering to capture the template include
            ob_start();
            CIG()->statistics->render_page();
            $content = ob_get_clean();
            
            // Remove the wrap class for frontend display
            $content = str_replace('<div class="wrap ', '<div class="', $content);
            echo $content;
        } else {
            echo '<p>' . esc_html__('Statistics module is not available.', 'cig') . '</p>';
        }
    }
}
