<?php
/**
 * Unit tests for CIG_Rest_Dashboard
 *
 * Covers:
 *   update_accountant_status() — field validation, DB write, rs_uploaded tracking
 *   update_accountant_note()   — DB write path
 *   get_settings()             — returns camelCase defaults / stored values
 *   save_settings()            — merges camelCase body into snake_case wp_options key
 *
 * Brain Monkey stubs all WordPress functions.
 * Mockery stubs $wpdb.
 *
 * @package CIG\Tests
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class RestDashboardTest extends \PHPUnit\Framework\TestCase {

    use MockeryPHPUnitIntegration;

    /** @var CIG_Rest_Dashboard */
    private $dashboard;

    /** @var \Mockery\MockInterface */
    private $wpdb;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        Functions\stubs([
            'add_action'          => null,
            'register_rest_route' => null,
            '__'                  => function($t) { return $t; },
            'is_user_logged_in'   => true,
            'current_user_can'    => false,
            'get_current_user_id' => 7,
            'current_time'        => '2025-06-15 10:00:00',
            'get_option'          => [],
            'update_option'       => true,
            'sanitize_text_field' => function($v) { return (string) $v; },
            'sanitize_textarea_field' => function($v) { return (string) $v; },
        ]);

        $this->wpdb         = \Mockery::mock('wpdb');
        $this->wpdb->prefix = 'wp_';
        $GLOBALS['wpdb']    = $this->wpdb;

        $this->dashboard = new CIG_Rest_Dashboard();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        \Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // update_accountant_status()
    // -------------------------------------------------------------------------

    public function test_accountant_status_rejects_invalid_field(): void {
        $request = new WP_REST_Request();
        $request->set_param('id', 1);
        $request->set_body(['field' => 'nonExistentField', 'value' => true]);

        $response = $this->dashboard->update_accountant_status($request);

        $this->assertSame(400,             $response->status);
        $this->assertSame('invalid_field', $response->data['code']);
    }

    public function test_accountant_status_updates_is_rs_uploaded(): void {
        $this->wpdb->shouldReceive('update')
            ->once()
            ->andReturn(1);

        $request = new WP_REST_Request();
        $request->set_param('id', 10);
        $request->set_body(['field' => 'isRsUploaded', 'value' => true]);

        $response = $this->dashboard->update_accountant_status($request);

        $this->assertSame(200,          $response->status);
        $this->assertSame('isRsUploaded', $response->data['data']['field']);
        $this->assertTrue($response->data['data']['value']);
    }

    public function test_accountant_status_updates_is_credit_checked(): void {
        $this->wpdb->shouldReceive('update')->once()->andReturn(1);

        $request = new WP_REST_Request();
        $request->set_param('id', 5);
        $request->set_body(['field' => 'isCreditChecked', 'value' => false]);

        $response = $this->dashboard->update_accountant_status($request);

        $this->assertSame(200, $response->status);
        $this->assertFalse($response->data['data']['value']);
    }

    public function test_accountant_status_tracks_rs_uploaded_by_when_set_true(): void {
        $capturedData = null;

        $this->wpdb->shouldReceive('update')
            ->once()
            ->andReturnUsing(function($table, $data) use (&$capturedData) {
                $capturedData = $data;
                return 1;
            });

        $request = new WP_REST_Request();
        $request->set_param('id', 20);
        $request->set_body(['field' => 'isRsUploaded', 'value' => true]);

        $this->dashboard->update_accountant_status($request);

        // The update should include rs_uploaded_by and rs_uploaded_date
        $this->assertArrayHasKey('rs_uploaded_by',   $capturedData);
        $this->assertArrayHasKey('rs_uploaded_date',  $capturedData);
        $this->assertSame(7, $capturedData['rs_uploaded_by']); // matches get_current_user_id stub
    }

    public function test_accountant_status_clears_rs_tracking_when_set_false(): void {
        $capturedData = null;

        $this->wpdb->shouldReceive('update')
            ->once()
            ->andReturnUsing(function($table, $data) use (&$capturedData) {
                $capturedData = $data;
                return 1;
            });

        $request = new WP_REST_Request();
        $request->set_param('id', 20);
        $request->set_body(['field' => 'isRsUploaded', 'value' => false]);

        $this->dashboard->update_accountant_status($request);

        $this->assertNull($capturedData['rs_uploaded_by']);
        $this->assertNull($capturedData['rs_uploaded_date']);
    }

    // -------------------------------------------------------------------------
    // update_accountant_note()
    // -------------------------------------------------------------------------

    public function test_accountant_note_updates_note_in_db(): void {
        $capturedNote = null;

        $this->wpdb->shouldReceive('update')
            ->once()
            ->andReturnUsing(function($table, $data) use (&$capturedNote) {
                $capturedNote = $data['accountant_note'];
                return 1;
            });

        $request = new WP_REST_Request();
        $request->set_param('id', 3);
        $request->set_body(['note' => 'Please review this invoice.']);

        $response = $this->dashboard->update_accountant_note($request);

        $this->assertSame(200, $response->status);
        $this->assertSame('Please review this invoice.', $capturedNote);
        $this->assertSame('Please review this invoice.', $response->data['data']['accountantNote']);
    }

    // -------------------------------------------------------------------------
    // get_settings()
    // -------------------------------------------------------------------------

    public function test_get_settings_returns_defaults_when_option_empty(): void {
        Functions\when('get_option')->justReturn([]);

        $request  = new WP_REST_Request();
        $response = $this->dashboard->get_settings($request);

        $this->assertSame(200, $response->status);

        $data = $response->data['data'];
        $this->assertArrayHasKey('companyName',  $data);
        $this->assertArrayHasKey('bankName',     $data);
        $this->assertArrayHasKey('bankAccount',  $data);
        $this->assertSame('', $data['companyName']);
        $this->assertSame('', $data['bankName']);
    }

    public function test_get_settings_returns_stored_values(): void {
        Functions\when('get_option')->justReturn([
            'company_name'   => 'GN Industrial',
            'company_tax_id' => '123456789',
            'bank_name'      => 'Bank of Georgia',
        ]);

        $request  = new WP_REST_Request();
        $response = $this->dashboard->get_settings($request);

        $data = $response->data['data'];
        $this->assertSame('GN Industrial',    $data['companyName']);
        $this->assertSame('123456789',        $data['companyTaxId']);
        $this->assertSame('Bank of Georgia',  $data['bankName']);
    }

    // -------------------------------------------------------------------------
    // save_settings()
    // -------------------------------------------------------------------------

    public function test_save_settings_merges_camel_case_to_snake_case(): void {
        $captured = null;

        Functions\when('get_option')->justReturn([]);
        // Use alias() so we can capture the value without conflicting with setUp stub
        Functions\when('update_option')->alias(function($key, $value) use (&$captured) {
            $captured = $value;
            return true;
        });

        $request = new WP_REST_Request();
        $request->set_body([
            'companyName'  => 'GN Industrial',
            'bankName'     => 'TBC Bank',
            'bankAccount'  => 'GE12TBC0000000123456',
        ]);

        $this->dashboard->save_settings($request);

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('company_name',  $captured);
        $this->assertArrayHasKey('bank_name',     $captured);
        $this->assertArrayHasKey('bank_account',  $captured);
        $this->assertSame('GN Industrial',         $captured['company_name']);
        $this->assertSame('TBC Bank',              $captured['bank_name']);
    }

    public function test_save_settings_preserves_untouched_keys(): void {
        $captured = null;

        Functions\when('get_option')->justReturn([
            'company_name'  => 'Old Name',
            'custom_secret' => 'should-be-preserved',
        ]);
        Functions\when('update_option')->alias(function($key, $value) use (&$captured) {
            $captured = $value;
            return true;
        });

        $request = new WP_REST_Request();
        $request->set_body(['companyName' => 'New Name']);

        $this->dashboard->save_settings($request);

        $this->assertIsArray($captured);
        $this->assertSame('New Name',            $captured['company_name']);
        $this->assertSame('should-be-preserved', $captured['custom_secret']);
    }
}
