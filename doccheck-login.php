<?php
/**
 * Plugin Name: DocCheck Login
 * Plugin URI: https://www.doccheck.com/
 * Description: Integrates DocCheck OAuth2 Login into WordPress
 * Version: 1.0.0
 * Author: DocCheck
 * Author URI: https://www.doccheck.com/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: doccheck-access
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('DOCCHECK_LOGIN_VERSION', '1.0.0');
define('DOCCHECK_LOGIN_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('DOCCHECK_LOGIN_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Activation hook
 */
function doccheck_login_activate() {
    // Ensure our custom endpoint is registered
    add_rewrite_endpoint('doccheck', EP_ROOT);
    flush_rewrite_rules();

    // Add doccheck_user role
    if (!get_role('doccheck_user')) {
        $subscriber = get_role('subscriber');
        $capabilities = $subscriber ? $subscriber->capabilities : array('read' => true);
        add_role('doccheck_user', __('DocCheck User', 'doccheck-access'), $capabilities);
    }

    // Set default options if they don't exist
    if (!get_option('doccheck_login_settings')) {
        $default_settings = array(
            'client_id' => '',
            'client_secret' => '',
            'auth_server_url' => 'https://auth.doccheck.com',
            'redirect_uri' => home_url('doccheck/callback'),
            'redirect_route' => 'doccheck/callback',
            'default_role' => 'doccheck_user',
            'default_scopes' => 'openid profile',
            'debug_mode' => 'off',
            // Default scope selections
            'selected_scopes' => array(
                'unique_id' => true,  // Required, cannot be disabled
                'country_iso_code' => false,
                'language' => false,
                'profession' => false,
                'address' => false,
                'occupation_detail' => false,
                'email' => false,
                'name' => false
            ),
            // Default property selections
            'selected_properties' => array(
                'unique_id' => true,  // Required, cannot be disabled
                'country_iso_code' => false,
                'country_id' => false,
                'user_language' => false,
                'profession_name' => false,
                'profession_id' => false,
                'area_code' => false,
                'street' => false,
                'city' => false,
                'state' => false,
                'discipline_name' => false,
                'discipline_id' => false,
                'activity_name' => false,
                'activity_id' => false,
                'email' => false,
                'first_name' => false,
                'last_name' => false
            ),
            // Endpoint settings are now handled internally
        );

        add_option('doccheck_login_settings', $default_settings);
    }
}
register_activation_hook(__FILE__, 'doccheck_login_activate');

/**
 * Register doccheck query vars
 *
 * @param array $vars Query vars.
 * @return array Modified query vars.
 */
function doccheck_login_query_vars($vars) {
    $vars[] = 'doccheck';
    return $vars;
}
add_filter('query_vars', 'doccheck_login_query_vars');

/**
 * Deactivation hook
 */
function doccheck_login_deactivate() {
    flush_rewrite_rules();
    // Optional: remove the custom role on deactivation
    // remove_role('doccheck_user');
}
register_deactivation_hook(__FILE__, 'doccheck_login_deactivate');

/**
 * Include required files
 */
require_once DOCCHECK_LOGIN_PLUGIN_PATH . 'includes/class-doccheck-login.php';
require_once DOCCHECK_LOGIN_PLUGIN_PATH . 'includes/class-doccheck-login-oauth.php';
require_once DOCCHECK_LOGIN_PLUGIN_PATH . 'includes/template-functions.php';

// Initialize the plugin
function doccheck_login_run() {
    // Start session if not already started
    if (!session_id() && !headers_sent()) {
        session_start();
    }
    $plugin = new DocCheck_Login();
    $plugin->run();
}

add_action('template_redirect', function () {
    $route = get_query_var('doccheck');
    if ($route === 'callback') {
        // Redirection logic is handled by the DocCheck_Login class via parse_request hook.
        // This is a placeholder for potential future template-based handling.
    }
});

doccheck_login_run();