<?php
/**
 * Unit tests for CIG_Invoice_Manager
 *
 * Uses Brain Monkey to mock WordPress functions and Mockery to mock $wpdb.
 * No real database or WordPress installation is required.
 *
 * @package CIG\Tests
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class InvoiceManagerTest extends \PHPUnit\Framework\TestCase {

    use MockeryPHPUnitIntegration;

    /** @var \Mockery\MockInterface — stands in for the global $wpdb */
    private $wpdb;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Stub every WP function that the constructor or methods call
        Functions\stubs([
            'add_action'         => null,
            'current_time'       => '2025-01-15 10:00:00',
            'get_current_user_id'=> 1,
            'sanitize_text_field'=> function($v) { return $v; },
            'sanitize_textarea_field' => function($v) { return $v; },
            'esc_url_raw'        => function($v) { return $v; },
            '__'                 => function($t) { return $t; },
            'get_option'         => false,
            'update_option'      => true,
            'get_post_meta'      => '',
            'get_post_type'      => 'post',
            'get_post_field'     => '',
        ]);

        // Build a Mockery mock for $wpdb and inject it as the global
        $this->wpdb         = \Mockery::mock('wpdb');
        $this->wpdb->prefix = 'wp_';
        $GLOBALS['wpdb']    = $this->wpdb;
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        \Mockery::close();
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Helper: build a CIG_Invoice_Manager with a fresh $wpdb mock
    // -----------------------------------------------------------------------
    private function makeManager(): CIG_Invoice_Manager {
        // The constructor reads $wpdb->prefix to set table names
        return new CIG_Invoice_Manager();
    }

    // -----------------------------------------------------------------------
    // Test: create_invoice() returns the inserted int ID on success
    // -----------------------------------------------------------------------
    public function test_create_invoice_returns_id(): void {
        $this->wpdb->shouldReceive('query')->andReturn(true); // START TRANSACTION + COMMIT

        $this->wpdb->shouldReceive('insert')
            ->once()
            ->withArgs(function($table) {
                return strpos($table, 'cig_invoices') !== false;
            })
            ->andReturn(1);

        $this->wpdb->insert_id = 42;

        // Payments and items are empty so no further inserts happen;
        // recalculate_paid_amount fires a get_var + update.
        $this->wpdb->shouldReceive('get_var')->andReturn('0');
        $this->wpdb->shouldReceive('update')->andReturn(1);
        $this->wpdb->shouldReceive('prepare')
            ->andReturnUsing(function($query) { return $query; });

        $manager = $this->makeManager();
        $result  = $manager->create_invoice([
            'invoice_number' => 'N25000001',
            'customer_id'    => 5,
            'status'         => 'standard',
        ]);

        $this->assertSame(42, $result);
    }

    // -----------------------------------------------------------------------
    // Test: create_invoice() returns WP_Error when $wpdb->insert fails
    // -----------------------------------------------------------------------
    public function test_create_invoice_returns_wp_error_on_db_failure(): void {
        $this->wpdb->shouldReceive('query')->andReturn(true); // START TRANSACTION + ROLLBACK
        $this->wpdb->shouldReceive('insert')->andReturn(false);
        $this->wpdb->last_error = 'Duplicate entry';

        $manager = $this->makeManager();
        $result  = $manager->create_invoice([
            'invoice_number' => 'N25000002',
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('db_insert_error', $result->get_error_code());
    }

    // -----------------------------------------------------------------------
    // Test: create_invoice() returns WP_Error when invoice_number is empty
    // -----------------------------------------------------------------------
    public function test_create_invoice_requires_invoice_number(): void {
        $manager = $this->makeManager();
        $result  = $manager->create_invoice([]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('missing_invoice_number', $result->get_error_code());
    }

    // -----------------------------------------------------------------------
    // Test: update_invoice() calls $wpdb->update on the invoices table
    // -----------------------------------------------------------------------
    public function test_update_invoice_calls_wpdb_update(): void {
        // get_invoice() is called internally — mock the DB reads it needs
        $fakeInvoice = [
            'id'               => 7,
            'invoice_number'   => 'N25000007',
            'status'           => 'standard',
            'lifecycle_status' => 'active',
            'customer_id'      => 3,
            'total_amount'     => 1000.0,
            'paid_amount'      => 500.0,
        ];

        $this->wpdb->shouldReceive('query')->andReturn(true); // START TRANSACTION + COMMIT

        $this->wpdb->shouldReceive('prepare')
            ->andReturnUsing(function($q) { return $q; });

        // ARRAY_A mode → wpdb returns associative arrays, not objects
        $this->wpdb->shouldReceive('get_row')
            ->andReturn($fakeInvoice);

        $this->wpdb->shouldReceive('get_results')
            ->andReturn([]);

        // The actual update call — we verify it targets the invoices table
        $this->wpdb->shouldReceive('update')
            ->atLeast()->once()
            ->withArgs(function($table) {
                return strpos($table, 'cig_invoices') !== false;
            })
            ->andReturn(1);

        $this->wpdb->shouldReceive('get_var')->andReturn('500');

        $manager = $this->makeManager();
        $result  = $manager->update_invoice(7, ['total_amount' => 1500.0]);

        $this->assertTrue($result);
    }

    // -----------------------------------------------------------------------
    // Test: delete_invoice() removes from all three tables in order
    // -----------------------------------------------------------------------
    public function test_delete_invoice_cascades(): void {
        $deletedTables = [];

        $this->wpdb->shouldReceive('delete')
            ->times(3)
            ->andReturnUsing(function($table) use (&$deletedTables) {
                $deletedTables[] = $table;
                return 1;
            });

        $manager = $this->makeManager();
        $result  = $manager->delete_invoice(10);

        $this->assertTrue($result);

        // Payments must be deleted before items, items before the invoice row
        $this->assertCount(3, $deletedTables);
        $this->assertStringContainsString('cig_payments',      $deletedTables[0]);
        $this->assertStringContainsString('cig_invoice_items', $deletedTables[1]);
        $this->assertStringContainsString('cig_invoices',      $deletedTables[2]);
    }

    // -----------------------------------------------------------------------
    // Test: get_invoice() returns an array with the four expected keys
    // -----------------------------------------------------------------------
    public function test_get_invoice_returns_structured_array(): void {
        $fakeInvoice = ['id' => 3, 'customer_id' => 2, 'invoice_number' => 'N25000003'];
        $fakeItems   = [['id' => 1, 'invoice_id' => 3]];
        $fakePayment = [['id' => 1, 'invoice_id' => 3, 'amount' => 200.0]];
        $fakeCustomer = ['id' => 2, 'name' => 'Acme Corp'];

        $this->wpdb->shouldReceive('prepare')
            ->andReturnUsing(function($q) { return $q; });

        // ARRAY_A mode → wpdb returns associative arrays, not objects
        // First get_row → invoice; second get_row → customer
        $this->wpdb->shouldReceive('get_row')
            ->andReturn($fakeInvoice, $fakeCustomer);

        // First get_results → items; second → payments
        $this->wpdb->shouldReceive('get_results')
            ->andReturn($fakeItems, $fakePayment);

        $manager = $this->makeManager();
        $data    = $manager->get_invoice(3);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('invoice',  $data);
        $this->assertArrayHasKey('items',    $data);
        $this->assertArrayHasKey('payments', $data);
        $this->assertArrayHasKey('customer', $data);
    }

    // -----------------------------------------------------------------------
    // Test: delete_invoice() returns WP_Error for invalid (zero) ID
    // -----------------------------------------------------------------------
    public function test_delete_invoice_returns_error_for_invalid_id(): void {
        $manager = $this->makeManager();
        $result  = $manager->delete_invoice(0);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invalid_id', $result->get_error_code());
    }
}
