<?php
/**
 * REST API Foundation
 * Registers all CIG REST endpoints under the cig/v1 namespace.
 *
 * Authentication strategy: WordPress Cookie + REST Nonce (same-domain).
 * The Vue SPA is served on the same domain via a shortcode page, so the
 * browser sends WP auth cookies automatically with every request. Clients
 * must also attach X-WP-Nonce: <nonce> on all non-public requests.
 *
 * @package CIG
 * @since 5.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CIG_Rest_API {

    /**
     * REST API namespace for all CIG endpoints.
     */
    const NAMESPACE = 'cig/v1';

    /**
     * CIG roles that are valid for storage in user meta.
     */
    private const VALID_ROLES = ['admin', 'manager', 'sales', 'accountant'];

    /**
     * Register the rest_api_init hook.
     */
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register all routes for this class.
     */
    public function register_routes() {

        // GET /cig/v1/me
        // Public endpoint — returns isLoggedIn: false if no session exists.
        // Used by the Vue SPA on boot to hydrate the auth store without a round-trip login.
        register_rest_route(self::NAMESPACE, '/me', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_me'],
            'permission_callback' => '__return_true',
        ]);

        // POST /cig/v1/auth/login
        // Public endpoint — accepts username + password, calls wp_signon() so WordPress
        // sets its own auth cookies in the browser response.
        register_rest_route(self::NAMESPACE, '/auth/login', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'login'],
            'permission_callback' => '__return_true',
            'args'                => [
                'username' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                // Intentionally no sanitize_callback for password — sanitization
                // functions like sanitize_text_field strip characters that are
                // valid in passwords (e.g. <, >, &). The value is passed
                // directly to wp_signon() which handles it securely.
                'password' => [
                    'required' => true,
                    'type'     => 'string',
                ],
            ],
        ]);

        // POST /cig/v1/auth/logout
        // Calls wp_logout() which clears the WP auth cookies server-side.
        register_rest_route(self::NAMESPACE, '/auth/logout', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'logout'],
            'permission_callback' => '__return_true',
        ]);
    }

    // -------------------------------------------------------------------------
    // Callbacks
    // -------------------------------------------------------------------------

    /**
     * GET /cig/v1/me
     *
     * If no WP session is active, returns { isLoggedIn: false }.
     * If a session exists, returns the user object and a fresh nonce so the
     * Vue SPA can immediately start making authenticated API calls.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_me(WP_REST_Request $request): WP_REST_Response {
        if (!is_user_logged_in()) {
            return new WP_REST_Response([
                'isLoggedIn' => false,
                'user'       => null,
            ], 200);
        }

        return new WP_REST_Response([
            'isLoggedIn' => true,
            'user'       => $this->format_user(wp_get_current_user()),
            'nonce'      => wp_create_nonce('wp_rest'),
        ], 200);
    }

    /**
     * POST /cig/v1/auth/login
     *
     * Authenticates the user via wp_signon() which:
     *   1. Validates credentials against wp_users.
     *   2. Calls wp_set_auth_cookie() so the browser receives a Set-Cookie
     *      header with the WordPress auth cookie.
     * Returns the user object and a fresh `wp_rest` nonce for subsequent
     * X-WP-Nonce headers.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function login(WP_REST_Request $request): WP_REST_Response {
        $username = $request->get_param('username');

        // Retrieve the raw password from the JSON body to avoid any
        // sanitization stripping valid password characters.
        $body     = $request->get_json_params();
        $password = isset($body['password']) ? (string) $body['password'] : '';

        if ('' === $password) {
            return new WP_REST_Response([
                'success' => false,
                'code'    => 'empty_password',
                'message' => __('Password is required.', 'cig'),
            ], 400);
        }

        $user = wp_signon([
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => true,
        ], is_ssl());

        if (is_wp_error($user)) {
            return new WP_REST_Response([
                'success' => false,
                'code'    => $user->get_error_code(),
                'message' => $user->get_error_message(),
            ], 401);
        }

        // Establish the user in the current request context so that
        // wp_create_nonce() generates a nonce bound to this user.
        wp_set_current_user($user->ID);

        return new WP_REST_Response([
            'success' => true,
            'user'    => $this->format_user($user),
            'nonce'   => wp_create_nonce('wp_rest'),
        ], 200);
    }

    /**
     * POST /cig/v1/auth/logout
     *
     * Calls wp_logout() which destroys the session and instructs the browser
     * to clear the WP auth cookie via Set-Cookie headers.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function logout(WP_REST_Request $request): WP_REST_Response {
        wp_logout();

        return new WP_REST_Response([
            'success' => true,
        ], 200);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Format a WP_User object into the camelCase shape the Vue auth store expects.
     *
     * @param WP_User $user
     * @return array
     */
    public function format_user(WP_User $user): array {
        return [
            'id'     => $user->ID,
            'name'   => $user->display_name,
            'email'  => $user->user_email,
            'avatar' => $this->get_avatar_initials($user->display_name),
            'role'   => $this->get_cig_role($user),
        ];
    }

    /**
     * Derive 2-character uppercase initials from a display name.
     * Mirrors the `avatar` field convention used in the Vue SPA.
     *
     * @param string $name
     * @return string  e.g. "Giorgi Nozadze" → "GN", "Admin" → "AD"
     */
    private function get_avatar_initials(string $name): string {
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        if (count($words) >= 2) {
            return mb_strtoupper(
                mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1)
            );
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    }

    /**
     * Map a WordPress user to one of the four CIG roles:
     *   admin | manager | sales | accountant
     *
     * Priority order:
     *   1. Explicit `_cig_role` user meta (set via CIG admin panel).
     *   2. WP `administrator` role  → admin.
     *   3. WC `shop_manager` role   → manager.
     *   4. Custom `cig_accountant` WP role or `cig_accountant_access` cap → accountant.
     *   5. Any user with `manage_woocommerce` capability → sales.
     *
     * @param WP_User $user
     * @return string
     */
    private function get_cig_role(WP_User $user): string {
        // 1. Explicit override stored by a CIG admin.
        $meta_role = get_user_meta($user->ID, '_cig_role', true);
        if (in_array($meta_role, self::VALID_ROLES, true)) {
            return $meta_role;
        }

        // 2. WordPress administrator.
        if (in_array('administrator', (array) $user->roles, true)) {
            return 'admin';
        }

        // 3. WooCommerce shop manager.
        if (in_array('shop_manager', (array) $user->roles, true)) {
            return 'manager';
        }

        // 4. Dedicated CIG accountant role or capability.
        if (
            in_array('cig_accountant', (array) $user->roles, true) ||
            user_can($user, 'cig_accountant_access')
        ) {
            return 'accountant';
        }

        // 5. Default: any user who can interact with WooCommerce is a sales consultant.
        return 'sales';
    }
}
