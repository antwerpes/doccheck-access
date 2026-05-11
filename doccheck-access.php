<?php
/**
 * Plugin Name: DocCheck Access
 * Description: Integrates DocCheck OAuth2 Login into WordPress
 * Version: 1.0.3
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
define('DOCACC_VERSION', '1.0.3');
define('DOCACC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('DOCACC_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Get default plugin settings.
 *
 * @return array
 */
function docacc_get_default_settings() {
    return array(
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
        // Default scope selections.
        'selected_scopes' => array(
            'unique_id' => true,
            'country_iso_code' => false,
            'language' => false,
            'profession' => false,
            'address' => false,
            'occupation_detail' => false,
            'email' => false,
            'name' => false,
        ),
        // Default property selections.
        'selected_properties' => array(
            'unique_id' => true,
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
            'last_name' => false,
        ),
    );
}

/**
 * Ensure the plugin has a settings option.
 */
function docacc_ensure_settings() {
    if (false === get_option('docacc_settings', false)) {
        add_option('docacc_settings', docacc_get_default_settings());
    }
}

/**
 * Register rewrite rules for the OAuth callback.
 */
function docacc_register_rewrite_rules() {
    add_rewrite_tag('%docacc_oauth%', '([^&]+)');
    add_rewrite_rule('^doccheck/callback/?$', 'index.php?docacc_oauth=callback', 'top');
}

/**
 * Activation hook.
 */
function docacc_activate() {
    docacc_register_rewrite_rules();
    flush_rewrite_rules();

    // Add docacc_user role
    if (!get_role('docacc_user')) {
        $subscriber = get_role('subscriber');
        $capabilities = $subscriber ? $subscriber->capabilities : array('read' => true);
        add_role('docacc_user', __('DocCheck User', 'doccheck-access'), $capabilities);
    }

    docacc_ensure_settings();
}
register_activation_hook(__FILE__, 'docacc_activate');

/**
 * Register plugin query vars.
 *
 * @param array $vars Query vars.
 * @return array Modified query vars.
 */
function docacc_query_vars($vars) {
    $vars[] = 'docacc_oauth';
    return $vars;
}
add_filter('query_vars', 'docacc_query_vars');

/**
 * Deactivation hook
 */
function docacc_deactivate() {
    flush_rewrite_rules();
    // Optional: remove the custom role on deactivation
    // remove_role('docacc_user');
}
register_deactivation_hook(__FILE__, 'docacc_deactivate');

/**
 * Include required files
 */
require_once DOCACC_PLUGIN_PATH . 'includes/class-doccheck-login.php';
require_once DOCACC_PLUGIN_PATH . 'includes/class-doccheck-login-oauth.php';
require_once DOCACC_PLUGIN_PATH . 'includes/template-functions.php';

// Initialize the plugin.
function docacc_run() {
    docacc_ensure_settings();

    $plugin = new DocAcc();
    $plugin->run();
}

docacc_run();
