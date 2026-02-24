<?php
/**
 * Unit tests for CIG_Stock_Manager
 *
 * Brain Monkey mocks WordPress functions; Mockery mocks WooCommerce objects.
 *
 * @package CIG\Tests
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class StockManagerTest extends \PHPUnit\Framework\TestCase {

    use MockeryPHPUnitIntegration;

    /** Shared "now" datetime used across tests */
    private const NOW = '2025-01-15 12:00:00';

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Stub every WP function the constructor and methods call.
        // NOTE: get_post_meta is intentionally NOT stubbed here so that
        // per-test Functions\when() / Functions\expect() calls win cleanly.
        Functions\stubs([
            'add_action'          => null,
            'add_filter'          => null,
            'sanitize_text_field' => function($v) { return $v; },
            // update_post_meta and delete_post_meta are intentionally NOT
            // stubbed globally so that per-test Functions\expect() calls work.
            '__'                  => function($t) { return $t; },
        ]);

        $GLOBALS['wpdb'] = \Mockery::mock('wpdb');
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        \Mockery::close();
        parent::tearDown();
    }

    private function makeManager(): CIG_Stock_Manager {
        return new CIG_Stock_Manager(null, null, null);
    }

    // -----------------------------------------------------------------------
    // Test: get_reserved() sums only non-expired entries and ignores expired
    // -----------------------------------------------------------------------
    public function test_get_reserved_sums_meta_correctly(): void {
        $future = '2099-12-31 23:59:59';
        $past   = '2000-01-01 00:00:00';

        $meta = [
            101 => ['qty' => 3, 'expires' => $future], // valid  → counted
            102 => ['qty' => 5, 'expires' => $past],   // expired → skipped
            103 => ['qty' => 2, 'expires' => $future], // valid  → counted
        ];

        Functions\when('get_post_meta')
            ->justReturn($meta);

        Functions\when('current_time')
            ->justReturn(self::NOW);

        $manager = $this->makeManager();
        $total   = $manager->get_reserved(99);

        // Only entries 101 (3) and 103 (2) should be summed = 5
        $this->assertSame(5.0, (float) $total);
    }

    // -----------------------------------------------------------------------
    // Test: get_available() = stock_qty - reserved (excluding a given invoice)
    // -----------------------------------------------------------------------
    public function test_get_available_subtracts_reserved(): void {
        $productId      = 77;
        $excludeInvoice = 55;

        // WC product: stock = 20
        $product = \Mockery::mock('WC_Product');
        $product->shouldReceive('get_stock_quantity')->andReturn(20);

        Functions\when('wc_get_product')->justReturn($product);

        // Reserved: invoice 55 → 4 units (excluded); invoice 66 → 6 units
        $meta = [
            $excludeInvoice => ['qty' => 4, 'expires' => '2099-01-01 00:00:00'],
            66              => ['qty' => 6, 'expires' => '2099-01-01 00:00:00'],
        ];

        Functions\when('get_post_meta')->justReturn($meta);
        Functions\when('current_time')->justReturn(self::NOW);

        $manager   = $this->makeManager();
        $available = $manager->get_available($productId, $excludeInvoice);

        // 20 (stock) - 6 (non-excluded) = 14
        $this->assertSame(14.0, (float) $available);
    }

    // -----------------------------------------------------------------------
    // Test: validate_stock() returns error strings when qty > available
    // -----------------------------------------------------------------------
    public function test_validate_stock_returns_errors_when_insufficient(): void {
        $product = \Mockery::mock('WC_Product');
        $product->shouldReceive('get_stock_quantity')->andReturn(5);

        Functions\when('wc_get_product')->justReturn($product);
        Functions\when('get_post_meta')->justReturn([]);   // no reservations
        Functions\when('current_time')->justReturn(self::NOW);
        Functions\when('update_post_meta')->justReturn(true);

        $items = [[
            'product_id' => 50,
            'qty'        => 10,         // requesting more than 5 in stock
            'status'     => 'reserved',
            'name'       => 'Test Sofa',
            'sku'        => 'GN-001',
        ]];

        $manager = $this->makeManager();
        $errors  = $manager->validate_stock($items);

        $this->assertNotEmpty($errors);
        $this->assertIsArray($errors);
        $this->assertStringContainsString('Test Sofa', $errors[0]);
    }

    // -----------------------------------------------------------------------
    // Test: validate_stock() returns empty array when stock is sufficient
    // -----------------------------------------------------------------------
    public function test_validate_stock_passes_when_stock_sufficient(): void {
        $product = \Mockery::mock('WC_Product');
        $product->shouldReceive('get_stock_quantity')->andReturn(50);

        Functions\when('wc_get_product')->justReturn($product);
        Functions\when('get_post_meta')->justReturn([]);
        Functions\when('current_time')->justReturn(self::NOW);
        Functions\when('update_post_meta')->justReturn(true);

        $items = [[
            'product_id' => 51,
            'qty'        => 3,
            'status'     => 'reserved',
            'name'       => 'Test Chair',
            'sku'        => 'GN-002',
        ]];

        $manager = $this->makeManager();
        $errors  = $manager->validate_stock($items);

        $this->assertEmpty($errors);
    }

    // -----------------------------------------------------------------------
    // Test: update_reservation_meta() calls update_post_meta with the correct
    //       _cig_reserved_stock structure (qty + expires keys present)
    // -----------------------------------------------------------------------
    public function test_update_reservation_meta_writes_postmeta(): void {
        $productId  = 10;
        $invoiceId  = 20;
        $qty        = 3.0;

        // The method reads existing meta first, then writes the updated value
        Functions\when('get_post_meta')->justReturn([]);

        // current_time('timestamp') is called to compute the expiry date
        Functions\when('current_time')->justReturn(time());

        // Capture what update_post_meta receives
        $capturedMeta = null;
        Functions\expect('update_post_meta')
            ->once()
            ->andReturnUsing(function($pid, $key, $value) use (&$capturedMeta) {
                $capturedMeta = $value;
                return true;
            });

        $manager = $this->makeManager();
        // reservation_days = 0 → no expiry date; quantity > 0 → entry is stored
        $manager->update_reservation_meta($productId, $invoiceId, $qty, 0);

        $this->assertIsArray($capturedMeta);
        $this->assertArrayHasKey($invoiceId, $capturedMeta);
        $this->assertSame($qty, (float) $capturedMeta[$invoiceId]['qty']);
    }

    // -----------------------------------------------------------------------
    // Test: validate_stock() skips items whose status is 'none' (fictive)
    // -----------------------------------------------------------------------
    public function test_validate_stock_skips_none_status_items(): void {
        // wc_get_product must NOT be called for 'none' items
        Functions\expect('wc_get_product')->never();

        $items = [[
            'product_id' => 99,
            'qty'        => 999,
            'status'     => 'none',
            'name'       => 'Fictive Item',
            'sku'        => 'GN-FICTIVE',
        ]];

        $manager = $this->makeManager();
        $errors  = $manager->validate_stock($items);

        $this->assertEmpty($errors);
    }
}
