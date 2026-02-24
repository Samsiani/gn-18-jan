<?php
/**
 * PHPUnit bootstrap for CIG unit tests.
 *
 * Loads the Composer autoloader, stubs every WordPress / WooCommerce
 * global that the two classes under test reference, and then requires
 * the classes themselves.  No WordPress core or database connection is
 * needed — Brain Monkey intercepts every WP function call.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

// ---------------------------------------------------------------------------
// WordPress constants expected by the classes
// ---------------------------------------------------------------------------
define('ABSPATH',                  '/tmp/');
define('DAY_IN_SECONDS',           86400);
define('HOUR_IN_SECONDS',          3600);
define('CIG_PLUGIN_DIR',           dirname(__DIR__) . '/');
define('CIG_INCLUDES_DIR',         CIG_PLUGIN_DIR . 'includes/');
define('CIG_CACHE_GROUP',          'cig_cache');
define('CIG_CACHE_EXPIRY',         900);
define('CIG_INVOICE_NUMBER_PREFIX','N');
define('CIG_INVOICE_NUMBER_BASE',  25000000);

// WordPress DB result-format constants used in $wpdb->get_row / get_results
define('ARRAY_A',  'ARRAY_A');
define('ARRAY_N',  'ARRAY_N');
define('OBJECT',   'OBJECT');
define('OBJECT_K', 'OBJECT_K');

// ---------------------------------------------------------------------------
// Minimal WP_Error stub (Brain Monkey does not ship one)
// ---------------------------------------------------------------------------
if (!class_exists('WP_Error')) {
    class WP_Error {
        public $code;
        public $message;
        public $data;

        public function __construct($code = '', $message = '', $data = '') {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = $data;
        }

        public function get_error_code()    { return $this->code; }
        public function get_error_message() { return $this->message; }
    }
}

// ---------------------------------------------------------------------------
// Require the classes under test (order matters — dependencies first)
// ---------------------------------------------------------------------------
require_once CIG_INCLUDES_DIR . 'class-cig-logger.php';
require_once CIG_INCLUDES_DIR . 'class-cig-cache.php';
require_once CIG_INCLUDES_DIR . 'class-cig-validator.php';
require_once CIG_INCLUDES_DIR . 'class-cig-invoice-manager.php';
require_once CIG_INCLUDES_DIR . 'class-cig-stock-manager.php';
