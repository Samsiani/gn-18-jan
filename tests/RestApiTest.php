<?php
/**
 * Unit tests for CIG_Rest_API
 *
 * Covers: format_user(), get_avatar_initials(), get_cig_role(),
 *         get_me(), login(), logout() permission callbacks.
 *
 * Brain Monkey stubs all WordPress function calls.
 * No database or WordPress installation is needed.
 *
 * @package CIG\Tests
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class RestApiTest extends \PHPUnit\Framework\TestCase {

    use MockeryPHPUnitIntegration;

    /** @var CIG_Rest_API */
    private $api;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        Functions\stubs([
            'add_action'          => null,
            'register_rest_route' => null,
            '__'                  => function($t) { return $t; },
            'is_user_logged_in'   => false,
            'wp_create_nonce'     => 'test-nonce-123',
            'get_user_meta'       => '',
            'user_can'            => false,
            'sanitize_text_field' => function($v) { return $v; },
            'is_ssl'              => false,
        ]);

        $GLOBALS['wpdb']         = \Mockery::mock('wpdb');
        $GLOBALS['wpdb']->prefix = 'wp_';

        $this->api = new CIG_Rest_API();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        \Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helper: make a WP_User-like object
    // -------------------------------------------------------------------------
    private function makeUser(int $id, string $name, string $email = '', array $roles = []): WP_User {
        $user               = new WP_User();
        $user->ID           = $id;
        $user->display_name = $name;
        $user->user_email   = $email;
        $user->roles        = $roles;
        return $user;
    }

    // -------------------------------------------------------------------------
    // format_user() + avatar initials
    // -------------------------------------------------------------------------

    public function test_format_user_returns_expected_shape(): void {
        Functions\when('get_user_meta')->justReturn('sales');

        $user   = $this->makeUser(5, 'Giorgi Nozadze', 'giorgi@example.com', []);
        $result = $this->api->format_user($user);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id',     $result);
        $this->assertArrayHasKey('name',   $result);
        $this->assertArrayHasKey('email',  $result);
        $this->assertArrayHasKey('avatar', $result);
        $this->assertArrayHasKey('role',   $result);

        $this->assertSame(5,                   $result['id']);
        $this->assertSame('Giorgi Nozadze',    $result['name']);
        $this->assertSame('giorgi@example.com',$result['email']);
    }

    public function test_format_user_avatar_two_words_takes_initials(): void {
        Functions\when('get_user_meta')->justReturn('sales');

        $user   = $this->makeUser(1, 'Giorgi Nozadze', '', []);
        $result = $this->api->format_user($user);

        $this->assertSame('GN', $result['avatar']);
    }

    public function test_format_user_avatar_single_word_takes_first_two_chars(): void {
        Functions\when('get_user_meta')->justReturn('admin');

        $user   = $this->makeUser(2, 'Admin', '', ['administrator']);
        $result = $this->api->format_user($user);

        $this->assertSame('AD', $result['avatar']);
    }

    // -------------------------------------------------------------------------
    // get_cig_role(): priority chain
    // -------------------------------------------------------------------------

    public function test_get_cig_role_uses_explicit_meta_first(): void {
        // Meta override should win over WP roles
        Functions\when('get_user_meta')->justReturn('sales');

        $user        = $this->makeUser(3, 'Test User', '', ['administrator']);
        $result      = $this->api->format_user($user);

        // Meta says 'sales', even though user has administrator role
        $this->assertSame('sales', $result['role']);
    }

    public function test_get_cig_role_returns_admin_for_administrator(): void {
        // No meta override → fall through to role detection
        Functions\when('get_user_meta')->justReturn('');  // empty = no meta

        $user   = $this->makeUser(4, 'Admin User', '', ['administrator']);
        $result = $this->api->format_user($user);

        $this->assertSame('admin', $result['role']);
    }

    public function test_get_cig_role_returns_manager_for_shop_manager(): void {
        Functions\when('get_user_meta')->justReturn('');

        $user   = $this->makeUser(5, 'Shop Mgr', '', ['shop_manager']);
        $result = $this->api->format_user($user);

        $this->assertSame('manager', $result['role']);
    }

    public function test_get_cig_role_returns_accountant_for_cig_accountant_role(): void {
        Functions\when('get_user_meta')->justReturn('');
        Functions\when('user_can')->justReturn(false);

        $user   = $this->makeUser(6, 'Accountant', '', ['cig_accountant']);
        $result = $this->api->format_user($user);

        $this->assertSame('accountant', $result['role']);
    }

    public function test_get_cig_role_falls_back_to_sales(): void {
        // No meta, no admin/manager/accountant role → sales
        Functions\when('get_user_meta')->justReturn('');
        Functions\when('user_can')->justReturn(false);

        $user   = $this->makeUser(7, 'Sales Person', '', ['subscriber']);
        $result = $this->api->format_user($user);

        $this->assertSame('sales', $result['role']);
    }

    // -------------------------------------------------------------------------
    // GET /cig/v1/me
    // -------------------------------------------------------------------------

    public function test_get_me_returns_not_logged_in_when_unauthenticated(): void {
        Functions\when('is_user_logged_in')->justReturn(false);

        $request  = new WP_REST_Request();
        $response = $this->api->get_me($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200,   $response->status);
        $this->assertFalse($response->data['isLoggedIn']);
        $this->assertNull($response->data['user']);
    }

    public function test_get_me_returns_user_data_when_logged_in(): void {
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('get_user_meta')->justReturn('admin');
        Functions\when('wp_create_nonce')->justReturn('abc123');

        $user = $this->makeUser(1, 'Giorgi Nozadze', 'g@gn.ge', ['administrator']);
        Functions\when('wp_get_current_user')->justReturn($user);

        $request  = new WP_REST_Request();
        $response = $this->api->get_me($request);

        $this->assertSame(200,    $response->status);
        $this->assertTrue($response->data['isLoggedIn']);
        $this->assertSame('abc123', $response->data['nonce']);
        $this->assertIsArray($response->data['user']);
        $this->assertSame(1, $response->data['user']['id']);
    }

    // -------------------------------------------------------------------------
    // POST /cig/v1/auth/login
    // -------------------------------------------------------------------------

    public function test_login_returns_400_on_empty_password(): void {
        $request = new WP_REST_Request();
        $request->set_param('username', 'testuser');
        $request->set_body(['username' => 'testuser', 'password' => '']);

        $response = $this->api->login($request);

        $this->assertSame(400, $response->status);
        $this->assertSame('empty_password', $response->data['code']);
    }

    public function test_login_returns_401_on_wrong_credentials(): void {
        $wpError = new WP_Error('incorrect_password', 'Wrong password.');
        Functions\when('wp_signon')->justReturn($wpError);

        $request = new WP_REST_Request();
        $request->set_param('username', 'testuser');
        $request->set_body(['username' => 'testuser', 'password' => 'wrong']);

        $response = $this->api->login($request);

        $this->assertSame(401, $response->status);
        $this->assertFalse($response->data['success']);
        $this->assertSame('incorrect_password', $response->data['code']);
    }

    public function test_login_returns_200_on_valid_credentials(): void {
        $user = $this->makeUser(1, 'Giorgi Nozadze', 'g@gn.ge', ['administrator']);
        Functions\when('wp_signon')->justReturn($user);
        Functions\when('wp_set_current_user')->justReturn(null);
        Functions\when('wp_create_nonce')->justReturn('fresh-nonce');
        Functions\when('get_user_meta')->justReturn('admin');

        $request = new WP_REST_Request();
        $request->set_param('username', 'giorgi');
        $request->set_body(['username' => 'giorgi', 'password' => 'gn2024']);

        $response = $this->api->login($request);

        $this->assertSame(200,          $response->status);
        $this->assertTrue($response->data['success']);
        $this->assertSame('fresh-nonce', $response->data['nonce']);
        $this->assertSame(1,             $response->data['user']['id']);
    }

    // -------------------------------------------------------------------------
    // POST /cig/v1/auth/logout
    // -------------------------------------------------------------------------

    public function test_logout_clears_session_and_returns_success(): void {
        Functions\expect('wp_logout')->once();

        $request  = new WP_REST_Request();
        $response = $this->api->logout($request);

        $this->assertSame(200,  $response->status);
        $this->assertTrue($response->data['success']);
    }
}
