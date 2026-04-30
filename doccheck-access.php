<?php
/**
 * Plugin Name: DocCheck Access
 * Description: Integrates DocCheck OAuth2 Login into WordPress
 * Version: 1.0.2
 * Author: DocCheck Agency
 * Author URI: https://doccheck.agency/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: doccheck-access
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('DOCCHECK_ACCESS_VERSION', '1.0.2');
define('DOCCHECK_ACCESS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('DOCCHECK_ACCESS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Activation hook
 */
function doccheck_access_activate() {
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
            'default_role' => 'subscriber',
            'allow_user_creation' => 'off',
            'authentication_mode' => 'anonymous_session',
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
register_activation_hook(__FILE__, 'doccheck_access_activate');

/**
 * Register doccheck query vars
 *
 * @param array $vars Query vars.
 * @return array Modified query vars.
 */
function doccheck_access_query_vars($vars) {
    $vars[] = 'doccheck';
    return $vars;
}
add_filter('query_vars', 'doccheck_access_query_vars');

/**
 * Deactivation hook
 */
function doccheck_access_deactivate() {
    flush_rewrite_rules();
    // Optional: remove the custom role on deactivation
    // remove_role('doccheck_user');
}
register_deactivation_hook(__FILE__, 'doccheck_access_deactivate');

/**
 * Include required files
 */
require_once DOCCHECK_ACCESS_PLUGIN_PATH . 'includes/class-doccheck-login.php';
require_once DOCCHECK_ACCESS_PLUGIN_PATH . 'includes/class-doccheck-login-oauth.php';
require_once DOCCHECK_ACCESS_PLUGIN_PATH . 'includes/template-functions.php';

// Initialize the plugin
function doccheck_access_run() {
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

doccheck_access_run();
