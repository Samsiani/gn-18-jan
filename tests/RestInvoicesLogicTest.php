<?php
/**
 * Unit tests for CIG_Rest_Invoices — pure business-logic helpers
 *
 * All methods under test are private. PHPUnit ReflectionMethod is used to
 * access them directly without touching the database or calling CIG().
 *
 * Covered helpers:
 *   determine_status()           — payment presence → standard|fictive
 *   calculate_lifecycle_status() — item statuses → completed|reserved|unfinished
 *   calculate_total()            — sum excluding canceled items
 *   parse_buyer_input()          — camelCase validation + snake_case output
 *   parse_items_input()          — status enforcement, image sanitization
 *   parse_payments_input()       — skip zero, allow negative refunds
 *   get_latest_payment_date()    — pick most recent date
 *   items_to_stock_format()      — camelCase items → stock-manager format
 *   db_items_to_stock_format()   — DB rows → stock-manager format
 *
 * @package CIG\Tests
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class RestInvoicesLogicTest extends \PHPUnit\Framework\TestCase {

    use MockeryPHPUnitIntegration;

    /** @var CIG_Rest_Invoices */
    private $invoices;

    /** @var array<string, ReflectionMethod> */
    private static $refs = [];

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        Functions\stubs([
            'add_action'              => null,
            'register_rest_route'     => null,
            'sanitize_text_field'     => function($v) { return $v; },
            'sanitize_email'          => function($v) { return filter_var($v, FILTER_SANITIZE_EMAIL); },
            'sanitize_textarea_field' => function($v) { return $v; },
            'esc_url_raw'             => function($v) { return $v; },
            'current_time'            => '2025-06-01',
            'get_current_user_id'     => 1,
            '__'                      => function($t) { return $t; },
        ]);

        $wpdb         = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $wpdb;

        $this->invoices = new CIG_Rest_Invoices();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        \Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helper: call a private method via reflection
    // -------------------------------------------------------------------------
    private function call(string $method, array $args = []) {
        if (!isset(self::$refs[$method])) {
            $ref = new ReflectionMethod(CIG_Rest_Invoices::class, $method);
            $ref->setAccessible(true);
            self::$refs[$method] = $ref;
        }
        return self::$refs[$method]->invoke($this->invoices, ...$args);
    }

    // =========================================================================
    // determine_status()
    // =========================================================================

    public function test_determine_status_fictive_when_no_payments(): void {
        $this->assertSame('fictive', $this->call('determine_status', [[]]));
    }

    public function test_determine_status_fictive_when_all_payments_zero(): void {
        $payments = [
            ['amount' => 0],
            ['amount' => '0.00'],
        ];
        $this->assertSame('fictive', $this->call('determine_status', [$payments]));
    }

    public function test_determine_status_standard_when_payment_exists(): void {
        $payments = [['amount' => 500]];
        $this->assertSame('standard', $this->call('determine_status', [$payments]));
    }

    public function test_determine_status_standard_with_first_zero_then_positive(): void {
        $payments = [['amount' => 0], ['amount' => 100]];
        $this->assertSame('standard', $this->call('determine_status', [$payments]));
    }

    // =========================================================================
    // calculate_lifecycle_status()
    // =========================================================================

    public function test_lifecycle_fictive_always_returns_unfinished(): void {
        $items = [['item_status' => 'sold'], ['item_status' => 'reserved']];
        $this->assertSame('unfinished', $this->call('calculate_lifecycle_status', ['fictive', $items]));
    }

    public function test_lifecycle_all_sold_returns_completed(): void {
        $items = [
            ['item_status' => 'sold'],
            ['item_status' => 'sold'],
        ];
        $this->assertSame('completed', $this->call('calculate_lifecycle_status', ['standard', $items]));
    }

    public function test_lifecycle_all_reserved_returns_reserved(): void {
        $items = [
            ['item_status' => 'reserved'],
            ['item_status' => 'reserved'],
        ];
        $this->assertSame('reserved', $this->call('calculate_lifecycle_status', ['standard', $items]));
    }

    public function test_lifecycle_mixed_sold_reserved_returns_unfinished(): void {
        $items = [
            ['item_status' => 'sold'],
            ['item_status' => 'reserved'],
        ];
        $this->assertSame('unfinished', $this->call('calculate_lifecycle_status', ['standard', $items]));
    }

    public function test_lifecycle_canceled_items_excluded_from_active_count(): void {
        // All non-canceled items are sold → should be 'completed'
        $items = [
            ['item_status' => 'sold'],
            ['item_status' => 'canceled'],
        ];
        $this->assertSame('completed', $this->call('calculate_lifecycle_status', ['standard', $items]));
    }

    public function test_lifecycle_all_none_status_returns_unfinished(): void {
        // None-status items don't count as active → 0 active → unfinished
        $items = [['item_status' => 'none']];
        $this->assertSame('unfinished', $this->call('calculate_lifecycle_status', ['standard', $items]));
    }

    // =========================================================================
    // calculate_total()
    // =========================================================================

    public function test_calculate_total_sums_non_canceled_items(): void {
        $items = [
            ['item_status' => 'reserved', 'qty' => 2, 'price' => 100, 'total' => 200],
            ['item_status' => 'canceled', 'qty' => 1, 'price' => 50,  'total' => 50],
        ];
        // Only the reserved item (200) should be counted
        $this->assertSame(200.0, $this->call('calculate_total', [$items]));
    }

    public function test_calculate_total_includes_none_status_for_fictive(): void {
        $items = [
            ['item_status' => 'none', 'qty' => 3, 'price' => 100, 'total' => 300],
        ];
        $this->assertSame(300.0, $this->call('calculate_total', [$items]));
    }

    public function test_calculate_total_uses_qty_times_price_when_total_absent(): void {
        // 'total' key is intentionally missing — triggers the ?? fallback to qty * price
        $items = [
            ['item_status' => 'reserved', 'qty' => 4, 'price' => 250],
        ];
        $this->assertSame(1000.0, $this->call('calculate_total', [$items]));
    }

    // =========================================================================
    // parse_buyer_input()
    // =========================================================================

    public function test_parse_buyer_error_when_name_missing(): void {
        $result = $this->call('parse_buyer_input', [['taxId' => '123', 'phone' => '555']]);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('missing_buyer_fields', $result->get_error_code());
    }

    public function test_parse_buyer_error_when_tax_id_missing(): void {
        $result = $this->call('parse_buyer_input', [['name' => 'Acme', 'phone' => '555']]);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('missing_buyer_fields', $result->get_error_code());
    }

    public function test_parse_buyer_error_when_phone_missing(): void {
        $result = $this->call('parse_buyer_input', [['name' => 'Acme', 'taxId' => '123']]);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('missing_buyer_fields', $result->get_error_code());
    }

    public function test_parse_buyer_returns_snake_case_array_when_valid(): void {
        $raw    = ['name' => 'Acme Corp', 'taxId' => '123456789', 'phone' => '995555123456', 'email' => 'acme@test.com'];
        $result = $this->call('parse_buyer_input', [$raw]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name',   $result);
        $this->assertArrayHasKey('tax_id', $result);
        $this->assertArrayHasKey('phone',  $result);
        $this->assertSame('123456789', $result['tax_id']);
    }

    public function test_parse_buyer_accepts_snake_case_tax_id_key(): void {
        $raw    = ['name' => 'Corp', 'tax_id' => '987', 'phone' => '123'];
        $result = $this->call('parse_buyer_input', [$raw]);
        $this->assertIsArray($result);
        $this->assertSame('987', $result['tax_id']);
    }

    // =========================================================================
    // parse_items_input()
    // =========================================================================

    public function test_parse_items_fictive_forces_item_status_to_none(): void {
        $raw = [[
            'name'       => 'Sofa',
            'qty'        => 1,
            'price'      => 1000,
            'itemStatus' => 'reserved',
        ]];
        $result = $this->call('parse_items_input', [$raw, 'fictive']);

        $this->assertCount(1, $result);
        $this->assertSame('none', $result[0]['item_status']);
        $this->assertSame(0,      $result[0]['reservation_days']);
    }

    public function test_parse_items_standard_promotes_none_to_reserved(): void {
        $raw = [[
            'name'       => 'Chair',
            'qty'        => 2,
            'price'      => 200,
            'itemStatus' => 'none',
        ]];
        $result = $this->call('parse_items_input', [$raw, 'standard']);

        $this->assertSame('reserved', $result[0]['item_status']);
    }

    public function test_parse_items_standard_preserves_explicit_sold_status(): void {
        $raw = [[
            'name'       => 'Table',
            'qty'        => 1,
            'price'      => 500,
            'itemStatus' => 'sold',
        ]];
        $result = $this->call('parse_items_input', [$raw, 'standard']);

        $this->assertSame('sold', $result[0]['item_status']);
    }

    public function test_parse_items_skips_items_without_name(): void {
        $raw = [
            ['name' => '',     'qty' => 1, 'price' => 100],
            ['name' => 'Sofa', 'qty' => 1, 'price' => 500],
        ];
        $result = $this->call('parse_items_input', [$raw, 'standard']);

        $this->assertCount(1, $result);
        $this->assertSame('Sofa', $result[0]['name']);
    }

    public function test_parse_items_rejects_non_http_image_url(): void {
        $raw = [[
            'name'  => 'Lamp',
            'qty'   => 1,
            'price' => 50,
            'image' => 'javascript:alert(1)',
        ]];
        $result = $this->call('parse_items_input', [$raw, 'standard']);

        $this->assertSame('', $result[0]['image']);
    }

    public function test_parse_items_keeps_valid_https_image(): void {
        // esc_url_raw is stubbed to return the input unchanged in tests
        $raw = [[
            'name'  => 'Lamp',
            'qty'   => 1,
            'price' => 50,
            'image' => 'https://cdn.example.com/lamp.jpg',
        ]];
        $result = $this->call('parse_items_input', [$raw, 'standard']);

        $this->assertSame('https://cdn.example.com/lamp.jpg', $result[0]['image']);
    }

    // =========================================================================
    // parse_payments_input()
    // =========================================================================

    public function test_parse_payments_skips_zero_amount(): void {
        $raw = [
            ['amount' => 0,   'date' => '2025-01-01', 'method' => 'cash'],
            ['amount' => 500, 'date' => '2025-01-02', 'method' => 'cash'],
        ];
        $result = $this->call('parse_payments_input', [$raw]);

        $this->assertCount(1, $result);
        $this->assertSame(500.0, (float) $result[0]['amount']);
    }

    public function test_parse_payments_allows_negative_refund_amounts(): void {
        $raw = [['amount' => -200, 'date' => '2025-02-01', 'method' => 'refund']];
        $result = $this->call('parse_payments_input', [$raw]);

        $this->assertCount(1, $result);
        $this->assertSame(-200.0, (float) $result[0]['amount']);
        $this->assertSame('refund', $result[0]['method']);
    }

    public function test_parse_payments_uses_current_time_when_date_missing(): void {
        $raw = [['amount' => 100, 'method' => 'cash']]; // no 'date' key
        $result = $this->call('parse_payments_input', [$raw]);

        $this->assertCount(1, $result);
        $this->assertSame('2025-06-01', $result[0]['date']); // matches current_time stub
    }

    // =========================================================================
    // get_latest_payment_date()
    // =========================================================================

    public function test_get_latest_payment_date_returns_most_recent(): void {
        $payments = [
            ['date' => '2025-01-10'],
            ['date' => '2025-03-15'],
            ['date' => '2025-02-01'],
        ];
        $result = $this->call('get_latest_payment_date', [$payments]);

        $this->assertSame('2025-03-15', $result);
    }

    public function test_get_latest_payment_date_returns_null_when_empty(): void {
        $this->assertNull($this->call('get_latest_payment_date', [[]]));
    }

    public function test_get_latest_payment_date_skips_empty_dates(): void {
        $payments = [
            ['date' => ''],
            ['date' => '2025-05-20'],
        ];
        $this->assertSame('2025-05-20', $this->call('get_latest_payment_date', [$payments]));
    }

    // =========================================================================
    // items_to_stock_format()
    // =========================================================================

    public function test_items_to_stock_format_maps_keys_correctly(): void {
        $items = [[
            'product_id'       => 42,
            'qty'              => 3,
            'item_status'      => 'reserved',
            'reservation_days' => 14,
        ]];
        $result = $this->call('items_to_stock_format', [$items]);

        $this->assertCount(1, $result);
        $row = $result[0];
        $this->assertArrayHasKey('product_id',       $row);
        $this->assertArrayHasKey('qty',              $row);
        $this->assertArrayHasKey('status',           $row);
        $this->assertArrayHasKey('reservation_days', $row);
        $this->assertSame(42,         $row['product_id']);
        $this->assertSame(3.0,        (float) $row['qty']);
        $this->assertSame('reserved', $row['status']);
        $this->assertSame(14,         $row['reservation_days']);
    }

    // =========================================================================
    // db_items_to_stock_format()
    // =========================================================================

    public function test_db_items_to_stock_format_maps_db_keys_correctly(): void {
        $db_items = [[
            'product_id'       => 99,
            'quantity'         => 5,      // DB uses 'quantity', not 'qty'
            'item_status'      => 'sold', // DB uses 'item_status', not 'status'
            'reservation_days' => 0,
        ]];
        $result = $this->call('db_items_to_stock_format', [$db_items]);

        $this->assertCount(1, $result);
        $row = $result[0];
        $this->assertSame(99,     $row['product_id']);
        $this->assertSame(5.0,    (float) $row['qty']);
        $this->assertSame('sold', $row['status']);
    }
}
