<?php
/**
 * The OAuth handler class
 *
 * @since      1.0.0
 * @package    DocCheck_Login
 */

if (! defined('ABSPATH')) {
    exit;
}

if (!class_exists('DocCheck_Login_OAuth')) {
    class DocCheck_Login_OAuth
    {

        /**
         * Plugin settings
         *
         * @since  1.0.0
         * @access private
         * @var    DocCheck_Login_Settings $settings The plugin settings.
         */
        private $settings;

        /**
         * Nonce generated for the current request, reused across multiple buttons
         *
         * @since  1.0.0
         * @access private
         * @var    string|null
         */
        private $current_nonce = null;

        /**
         * Initialize the class
         *
         * @param DocCheck_Login_Settings $settings Plugin settings.
         *
         * @since 1.0.0
         */
        public function __construct(DocCheck_Login_Settings $settings)
        {
            $this->settings = $settings;

            if (!session_id()) {
                session_start();
            }
        }

        /**
         * Generate a secure random state parameter, optionally with encrypted redirect URL
         *
         * @param string $redirect_url Optional redirect URL to include in state
         *
         * @return string State parameter
         * @since  1.0.0
         */
        public function generate_state($redirect_url = null)
        {
            // Reuse the same nonce for all buttons rendered in a single request
            // so whichever button the user clicks, the nonce is valid
            if ($this->current_nonce === null) {
                $bytes = function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16);
                $this->current_nonce = bin2hex($bytes);

                // Store in a transient (reliable across requests, unlike PHP sessions)
                set_transient('doccheck_state_' . $this->current_nonce, 1, 600);
            }

            $state = $this->current_nonce;

            // If redirect URL is provided, append it to state after encryption
            if ($redirect_url) {
                $encrypted_url = $this->encrypt_url($redirect_url);
                $state .= '--'.$encrypted_url;
            }

            return $state;
        }

        /**
         * Encrypt a URL for state parameter
         *
         * @param string $url URL to encrypt
         *
         * @return string Encrypted URL
         * @since 1.1.0
         */
        private function encrypt_url($url)
        {
            // Create a simple encryption key based on the client secret (or a static salt if not available)
            $encryption_key = $this->settings->get_client_secret() ?: DOCCHECK_LOGIN_VERSION;

            // Use WordPress' nonce system as a simple encryption method
            $encrypted = base64_encode(wp_salt('auth').':'.$url);

            // Make base64 URL-safe
            $encrypted = str_replace(['+', '/', '='], ['-', '_', ''], $encrypted);

            return $encrypted;
        }

        /**
         * Decrypt a URL from state parameter
         *
         * @param string $encrypted_url Encrypted URL
         *
         * @return string|false Decrypted URL or false on failure
         * @since 1.1.0
         */
        private function decrypt_url($encrypted_url)
        {
            try {
                $this->log_debug('Decrypting URL: ' . $encrypted_url);
                // Restore base64 characters
                $encrypted_url = str_replace(['-', '_'], ['+', '/'], $encrypted_url);

                // Decode
                $decoded = base64_decode($encrypted_url);

                if ($decoded === false) {
                    $this->log_debug('Base64 decode failed');
                    return false;
                }

                // Verify the decoded string starts with the expected salt prefix
                $salt = wp_salt('auth');
                $prefix = $salt . ':';

                if (strpos($decoded, $prefix) !== 0) {
                    $this->log_debug('Salt mismatch in decoded URL');
                    return false;
                }

                // Extract the URL after the salt and separator
                $url = substr($decoded, strlen($prefix));

                $this->log_debug('Decrypted URL: ' . $url);
                return $url;
            } catch (Exception $e) {
                $this->log_debug('Error decrypting URL: '.$e->getMessage());

                return false;
            }
        }

        /**
         * Parse state parameter to extract nonce and optional redirect URL
         *
         * @param string $state State parameter
         *
         * @return array|false Array with 'nonce' and optional 'redirect_url', or false on invalid state
         * @since 1.1.0
         */
        private function parse_state($state)
        {
            $this->log_debug('Parsing state: ' . $state);
            // Split state into nonce and encrypted redirect URL parts
            $parts = explode('--', $state);
            $nonce = $parts[0];

            $this->log_debug('Extracted nonce: ' . $nonce);

            // Verify the nonce exists as a transient (set during generate_state)
            $transient_key = 'doccheck_state_' . $nonce;
            if (!get_transient($transient_key)) {
                $this->log_debug('No valid state transient found for nonce: ' . $nonce);
                return false;
            }

            // Delete the transient so it can't be reused (one-time use)
            delete_transient($transient_key);

            $result = [
                'nonce' => $nonce,
            ];

            // If we have a second part, it's the encrypted redirect URL
            if (isset($parts[1])) {
                $redirect_url = $this->decrypt_url($parts[1]);
                if ($redirect_url) {
                    $result['redirect_url'] = $redirect_url;
                }
            }

            return $result;
        }

        /**
         * Generate a code verifier for PKCE
         *
         * @return string Code verifier.
         * @since  1.0.0
         */
        private function generate_code_verifier()
        {
            // Use openssl_random_pseudo_bytes for PHP < 7.0 compatibility
            $bytes = function_exists('random_bytes') ? random_bytes(32) : openssl_random_pseudo_bytes(32);
            $verifier = bin2hex($bytes);

            $_SESSION['doccheck_code_verifier'] = $verifier;

            return $verifier;
        }

        /**
         * Generate code challenge from verifier (PKCE)
         *
         * @param string $verifier Code verifier.
         *
         * @return string Code challenge.
         * @since  1.0.0
         */
        private function generate_code_challenge($verifier)
        {
            $challenge = base64_encode(hash('sha256', $verifier, true));
            // Base64URL encoding
            $challenge = str_replace(['+', '/', '='], ['-', '_', ''], $challenge);

            return $challenge;
        }

        /**
         * Handle OAuth callback
         *
         * @since 1.0.0
         */
        public function handle_callback()
        {
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth callback cannot have WordPress nonces.
            // Check for errors
            if (isset($_GET['error'])) {
                $this->handle_error(sanitize_text_field(wp_unslash($_GET['error'])));

                return;
            }

            // Check for state parameter
            //if (!isset($_GET['state'])) {
            //    $this->handle_error('missing_state');
            //    return;
            //}

            // Parse and verify state
            $state_data = [];
            if (isset($_GET['state'])) {
                // Sanitize for safety; state is an encoded string, sanitization should keep it intact.
                $raw_state = sanitize_text_field( wp_unslash( $_GET['state'] ) );
                $this->log_debug('Received state: ' . $raw_state);
                $state_data = $this->parse_state($raw_state);
                if (!$state_data) {
                    $this->log_debug('State verification failed for state: ' . $raw_state);
                    $this->handle_error('invalid_state');

                    return;
                }
            }

            // Check for authorization code
            if (!isset($_GET['code'])) {
                $this->handle_error('no_code');

                return;
            }

            $code = sanitize_text_field(wp_unslash($_GET['code']));
            // phpcs:enable WordPress.Security.NonceVerification.Recommended
            $code_verifier = '';  // PKCE not implemented yet

            // Exchange code for token
            $token_data = $this->get_token($code, $code_verifier);

            if (!$token_data || isset($token_data['error'])) {
                $error_msg = isset($token_data['error']) ? $token_data['error'] : 'token_request_failed';
                $this->handle_error($error_msg);

                return;
            }

            // Get user data using access token
            $user_data = $this->get_user_data($token_data['access_token']);

            if (!$user_data || isset($user_data['error'])) {
                $error_msg = isset($user_data['error']) ? $user_data['error'] : 'user_data_request_failed';
                $this->handle_error($error_msg);

                return;
            }



            // Log user in or create account if needed
            $this->authenticate_user($user_data);

            // Clear session data
            unset($_SESSION['doccheck_code_verifier']);

            // Determine redirect URL
            // First check if we have a custom redirect URL in the state parameter
            if (isset($state_data['redirect_url'])) {
                $redirect_to = $state_data['redirect_url'];
                $this->log_debug('Redirecting to URL from state parameter: '.$redirect_to);

                // Add to allowed redirect hosts to ensure wp_safe_redirect works
                add_filter('allowed_redirect_hosts', function ($hosts) use ($redirect_to) {
                    $host = wp_parse_url($redirect_to, PHP_URL_HOST);
                    if ($host) {
                        $hosts[] = $host;
                    }
                    return $hosts;
                });
            } else {
                // Otherwise use the default target page
                $default_target_page_id =
                    isset($this->settings->default_target_page) ? $this->settings->default_target_page : '';

                if (!empty($default_target_page_id)) {
                    // Get the permalink of the default target page
                    $redirect_to = get_permalink($default_target_page_id);
                    $this->log_debug('Redirecting to default target page: '.$redirect_to);
                } else {
                    // Fall back to home URL if no default target page is set
                    $redirect_to = home_url();
                    $this->log_debug('Redirecting to home URL: '.$redirect_to);
                }
            }

            wp_safe_redirect($redirect_to);
            exit;
        }

        /**
         * Get token from authorization code
         *
         * @param string $code          Authorization code.
         * @param string $code_verifier Code verifier for PKCE.
         *
         * @return array|false          Token data or false on failure.
         * @since  1.0.0
         */
        private function get_token($code, $code_verifier)
        {
            $client_id = $this->settings->get_client_id();
            $client_secret = $this->settings->get_client_secret();

            // Get auth server URL from settings
            $auth_server_url = $this->settings->get_auth_server_url();

            // Get redirect route from settings
            $redirect_route = $this->settings->get_redirect_route();

            // Get redirect URI from settings, with fallback to constructed URI
            $redirect_uri = $this->settings->get_redirect_uri();
            if (empty($redirect_uri)) {
                $redirect_uri = home_url($redirect_route);
            }

            // Internal endpoint path, not configurable by users
            $token_endpoint = $auth_server_url.'/token';

            // Log the token request (with obfuscated code)
            $this->log_endpoint_call('token', [
                'code'        => $code,
                'message'     => 'Token request initiated',
                'status_code' => 'REQUEST',
            ]);

            $args = [
                'method'      => 'POST',
                'timeout'     => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body'        => [
                    'grant_type'    => 'authorization_code',
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'code'          => $code,
                    'redirect_uri'  => $redirect_uri,
                    'code_verifier' => $code_verifier,
                ],
                'sslverify'   => false,
            ];

            $response = wp_remote_post($token_endpoint, $args);

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();

                // Log the error
                $this->log_endpoint_call('token', [
                    'code'        => $code,
                    'message'     => 'Token request failed: '.$error_message,
                    'status_code' => 'ERROR',
                ]);

                $this->log_debug('Token request failed: '.$error_message);

                return false;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!$data) {
                // Log the error
                $this->log_endpoint_call('token', [
                    'code'        => $code,
                    'message'     => 'Invalid JSON response from token endpoint',
                    'status_code' => $status_code,
                ]);

                $this->log_debug('Invalid JSON response from token endpoint');

                return false;
            }

            // Log successful token response (without exposing the token itself)
            $this->log_endpoint_call('token', [
                'code'        => $code,
                'message'     => 'Token received successfully',
                'status_code' => $status_code,
            ]);

            return $data;
        }

        /**
         * Get user data with access token
         *
         * @param string $access_token Access token.
         *
         * @return array|false          User data or false on failure.
         * @since  1.0.0
         */
        private function get_user_data($access_token)
        {
            // Get auth server URL from settings
            $auth_server_url = $this->settings->get_auth_server_url();

            // Internal endpoint path, not configurable by users
            $userdata_endpoint = $auth_server_url.'/api/users/data';

            // Log the user data request (with obfuscated token)
            $this->log_endpoint_call('userdata', [
                'access_token' => $access_token,
                'message'      => 'User data request initiated',
                'status_code'  => 'REQUEST',
            ]);

            $args = [
                'method'      => 'GET',
                'timeout'     => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => [
                    'Authorization' => 'Bearer '.$access_token,
                ],
                'sslverify'   => false,
            ];

            $response = wp_remote_get($userdata_endpoint, $args);

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();

                // Log the error
                $this->log_endpoint_call('userdata', [
                    'access_token' => $access_token,
                    'message'      => 'User data request failed: '.$error_message,
                    'status_code'  => 'ERROR',
                ]);

                $this->log_debug('User data request failed: '.$error_message);

                return false;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!$data) {
                // Log the error
                $this->log_endpoint_call('userdata', [
                    'access_token' => $access_token,
                    'message'      => 'Invalid JSON response from user data endpoint',
                    'status_code'  => $status_code,
                ]);

                $this->log_debug('Invalid JSON response from user data endpoint');

                return false;
            }

            // Log successful user data response (without the actual user data)
            $this->log_endpoint_call('userdata', [
                'access_token' => $access_token,
                'message'      => 'User data received successfully',
                'status_code'  => $status_code,
            ]);

            return $data;
        }

        /**
         * Authenticate user in WordPress
         *
         * @param array $user_data User data from DocCheck API.
         *
         * @since 1.0.0
         */
        private function authenticate_user($user_data)
        {
            // Check authentication mode from settings
            $auth_mode = $this->settings->get_authentication_mode();

            // If anonymous session mode is selected, use session-only auth
            if ($auth_mode === 'anonymous_session') {
                $this->log_debug('Using anonymous session authentication mode');
                return $this->create_session_only_auth($user_data);
            }

            // Otherwise, proceed with WordPress user creation/linking
            $this->log_debug('Using WordPress user authentication mode');

            // Check if user has denied access to required fields
            $required_fields = ['unique_id']; // unique_id is always required
            $missing_required_fields = [];

            foreach ($required_fields as $field) {
                if (empty($user_data[$field])) {
                    $missing_required_fields[] = $field;
                }
            }

            if (!empty($missing_required_fields)) {
                // User has denied access to required fields
                $this->log_debug('User denied consent for required fields: '.implode(', ', $missing_required_fields));

                $fallback = $this->settings->get_fallback_behavior();
                switch ($fallback) {
                    case 'session_only':
                        // Allow session-only authentication without WordPress user
                        return $this->create_session_only_auth($user_data);

                    default:
                        $this->handle_error('consent_denied_for_required_fields');

                        return;
                }
            }

            // Extract user data
            $doccheck_id = isset($user_data['unique_id']) ? $user_data['unique_id'] : '';

            // Try to find user by doccheck ID
            $user = $this->get_existing_dc_user($doccheck_id);

            // Create new user if none exists
            if (!$user) {
                $user = $this->create_dc_word_user($doccheck_id, $user_data);

                if (is_null($user)) {
                    return;
                }
            } else {
                // Update existing user meta
                $this->save_user_meta($user->ID, $user_data);

                do_action('doccheck_login_user_logged_in', $user->ID, $user_data);
            }


            // Login user
            wp_clear_auth_cookie();
            wp_set_auth_cookie($user->ID, true);

            // Update user meta with last login timestamp
            update_user_meta($user->ID, 'doccheck_last_login', current_time('mysql'));

            // Apply role mapping hooks
            $this->apply_role_mapping($user->ID, $user_data);
        }

        private function get_existing_dc_user(string $doccheck_id): ?WP_User
        {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Necessary for user lookup by DocCheck ID.
            $existing_users = get_users([
                'meta_key'    => 'doccheck_unique_id',
                'meta_value'  => $doccheck_id,
                'number'      => 1,
                'count_total' => false,
            ]);

            return !empty($existing_users) ? $existing_users[0] : null;
        }

        private function create_dc_word_user($doccheck_id, array $user_data): ?WP_User
        {
            $email = isset($user_data['email']) ? $user_data['email'] : '';
            $display_name = isset($user_data['name']) ? $user_data['name'] : '';

            // Generate username from DocCheck ID or email
            $username = 'doccheck_'.substr($doccheck_id, 0, 8);
            if (username_exists($username)) {
                $username .= '_'.substr(uniqid(), 0, 5);
            }

            // Generate random password
            $password = wp_generate_password(16);

            // Default role from settings
            $default_role = $this->settings->get_default_role();

            // Create user
            $user_id = wp_create_user($username, $password, $email);

            if (is_wp_error($user_id)) {
                $this->log_debug('User creation failed: '.$user_id->get_error_message());
                $this->handle_error('user_creation_failed');

                return null;
            }

            // Set user role
            $user = new WP_User($user_id);
            $user->set_role($default_role);

            // Set display name
            if (!empty($display_name)) {
                wp_update_user([
                    'ID'           => $user_id,
                    'display_name' => $display_name,
                ]);
            }

            // Save DocCheck user data as user meta
            update_user_meta($user_id, 'doccheck_unique_id', $doccheck_id);

            // Save other user data
            $this->save_user_meta($user_id, $user_data);

            do_action('doccheck_login_user_created', $user_id, $user_data);

            return $user;
        }

        /**
         * Save user meta data
         *
         * @param int   $user_id   User ID.
         * @param array $user_data User data from DocCheck API.
         *
         * @since 1.0.0
         */
        private function save_user_meta($user_id, $user_data)
        {
            // Get the selected properties from settings
            $selected_properties = $this->settings->get_selected_properties();

            // Enhanced mapping for all possible DocCheck scopes
            $meta_mapping = [
                // Basic fields
                'unique_id'       => 'doccheck_unique_id',
                'country_iso_code'         => 'doccheck_country',
                'country_id'      => 'doccheck_country_id',
                'user_language'   => 'doccheck_language',
                'profession_name' => 'doccheck_profession',
                'profession_id'   => 'doccheck_profession_id',

                // Extended fields
                'email'           => 'doccheck_email',
                'first_name'      => 'first_name',
                'last_name'       => 'last_name',
                'discipline_name' => 'doccheck_discipline_name',
                'discipline_id'   => 'doccheck_discipline_id',
                'activity_name'   => 'doccheck_activity_name',
                'activity_id'     => 'doccheck_activity_id',
                'area_code'       => 'doccheck_area_code',
                'street'          => 'doccheck_street',
                'city'            => 'doccheck_city',
                'state'           => 'doccheck_state',

                // Other fields (for backward compatibility)
                'specialty'       => 'doccheck_specialty',
                'gender'          => 'doccheck_gender',
                'title'           => 'doccheck_title',
                'dc_language'     => 'doccheck_preferred_language',
            ];

            // Process each field in the mapping
            foreach ($meta_mapping as $api_field => $meta_field) {
                // Only save fields that are selected in properties (or unique_id which is required)
                if (isset($user_data[$api_field]) &&
                    (isset($selected_properties[$api_field]) && $selected_properties[$api_field]) ||
                    $api_field === 'unique_id') {
                    // Save the field to user meta
                    update_user_meta($user_id, $meta_field, $user_data[$api_field]);
                }
            }

            // Special handling for name field
            if (isset($user_data['name']) && isset($selected_properties['first_name']) &&
                $selected_properties['first_name'] && isset($selected_properties['last_name']) &&
                $selected_properties['last_name'] && !isset($user_data['first_name']) &&
                !isset($user_data['last_name'])) {
                $name_parts = explode(' ', $user_data['name'], 2);
                if (count($name_parts) > 1) {
                    // Save first and last name separately
                    update_user_meta($user_id, 'first_name', $name_parts[0]);
                    update_user_meta($user_id, 'last_name', $name_parts[1]);
                }
            }

            // Handle address data (if it's an object/array)
            if (isset($user_data['address']) && is_array($user_data['address'])) {
                foreach ($user_data['address'] as $address_key => $address_value) {
                    $property = $address_key;
                    if (isset($selected_properties[$property]) && $selected_properties[$property]) {
                        update_user_meta($user_id, 'doccheck_address_'.$address_key, $address_value);
                    }
                }
            }

            // Always save unique_id as it's required for user identification
            if (isset($user_data['unique_id'])) {
                update_user_meta($user_id, 'doccheck_unique_id', $user_data['unique_id']);
            }

            // Save full response as serialized data only if debug mode is enabled
            if ($this->settings->is_debug_mode()) {
                update_user_meta($user_id, 'doccheck_userdata', $user_data);
            }
        }

        /**
         * Create session-only authentication without WordPress user
         *
         * @param array $user_data User data from DocCheck API.
         *
         * @return bool Success status
         *
         * @since 1.3.0
         */
        private function create_session_only_auth($user_data)
        {
            $this->log_debug('Using session-only authentication (no WordPress user creation)');

            // Store session data
            $_SESSION['doccheck_session_auth'] = true;
            $_SESSION['doccheck_session_data'] = $user_data;
            $_SESSION['doccheck_session_time'] = current_time('mysql');

            // Add hook for site owners to access DocCheck user data in anonymous session mode
            /**
             * Fires when a DocCheck user is authenticated via anonymous session mode.
             *
             * This hook allows site owners to access the DocCheck user data during the session.
             * The data is not stored persistently in WordPress, but can be used within the session.
             *
             * @param array $user_data The user data from DocCheck API.
             *
             * @since 2.1.0
             */
            do_action('doccheck_login_session_created', $user_data);

            // No user ID to apply role mapping to
            return true;
        }

        /**
         * Apply role mapping based on DocCheck user data
         *
         * @param int   $user_id   User ID.
         * @param array $user_data User data from DocCheck API.
         *
         * @since 1.0.0
         */
        private function apply_role_mapping($user_id, $user_data)
        {
            $user = new WP_User($user_id);

            /**
             * Filter to map DocCheck user data to WordPress roles
             *
             * @param string $role      Current user role.
             * @param array  $user_data DocCheck user data.
             * @param int    $user_id   The user ID.
             *
             * @return string           New user role if mapping should be applied.
             */
            $new_role = apply_filters('doccheck_login_map_role', $user->roles[0], $user_data, $user_id);

            // Update role if changed
            if ($new_role && $new_role !== $user->roles[0]) {
                $user->set_role($new_role);
            }
        }

        /**
         * Handle OAuth errors
         *
         * @param string $error Error code.
         *
         * @since 1.0.0
         */
        private function handle_error($error)
        {
            $this->log_debug('OAuth error: '.$error);

            // Redirect to login page with error
            wp_safe_redirect(home_url().'?doccheck-error='.urlencode($error));
            exit;
        }

        /**
         * Get or create a session ID for logging purposes
         *
         * @return string Session ID
         * @since 1.2.0
         */
        private function get_or_create_session_id()
        {
            if (!isset($_SESSION['doccheck_session_id'])) {
                $_SESSION['doccheck_session_id'] = uniqid('dcs_', true);
            }

            return sanitize_text_field($_SESSION['doccheck_session_id']);
        }

        /**
         * Log endpoint call with detailed information
         *
         * @param string $endpoint_type Type of endpoint ('token' or 'userdata')
         * @param array  $data          Data to log
         *
         * @since 1.2.0
         */
        private function log_endpoint_call($endpoint_type, $data)
        {
            // Only log if advanced logging is enabled
            if (!$this->settings->is_debug_mode()) {
                return;
            }

            $session_id = $this->get_or_create_session_id();
            $timestamp = current_time('Y-m-d H:i:s');

            $log_data = [
                'session_id' => $session_id,
                'endpoint'   => $endpoint_type,
                'timestamp'  => $timestamp,
            ];

            if ($endpoint_type === 'token') {
                if (!empty($data['code'])) {
                    // Obfuscate auth code - show only first 15 chars
                    $log_data['auth_code'] = substr($data['code'], 0, 15).'...';
                }
                $log_data['status_code'] = $data['status_code'] ?? '';
                $log_data['message'] = $data['message'] ?? '';
            } else {
                if ($endpoint_type === 'userdata') {
                    if (!empty($data['access_token'])) {
                        // Obfuscate token - show only first 15 chars
                        $log_data['access_token'] = substr($data['access_token'], 0, 15).'...';
                    }
                    $log_data['status_code'] = $data['status_code'] ?? '';
                    $log_data['message'] = $data['message'] ?? '';
                }
            }

            $this->write_to_log($endpoint_type, $log_data);
        }

        /**
         * Write log data to the appropriate log file
         *
         * @param string       $log_type Type of log ('token', 'userdata', or 'debug')
         * @param array|string $log_data Data to log
         *
         * @since 1.2.0
         */
        private function write_to_log($log_type, $log_data)
        {
            $uploads_dir = wp_upload_dir();
            $logs_dir = $uploads_dir['basedir'].'/doccheck-logs';

            // Create logs directory if it doesn't exist
            if (!file_exists($logs_dir)) {
                wp_mkdir_p($logs_dir);

                // Create .htaccess file to protect logs
                $htaccess_content = "# Prevent direct access to log files\n";
                $htaccess_content .= "<FilesMatch \"\\.(log|json)$\">\n";
                $htaccess_content .= "  Order Allow,Deny\n";
                $htaccess_content .= "  Deny from all\n";
                $htaccess_content .= "</FilesMatch>\n";

                file_put_contents($logs_dir.'/.htaccess', $htaccess_content);

                // Create index.php file to prevent directory listing
                file_put_contents($logs_dir.'/index.php', '<?php // Silence is golden');
            }

            $date = gmdate('Y-m-d');
            $log_file = $logs_dir.'/'.$log_type.'-'.$date.'.log';

            // Convert log data to JSON
            if (is_array($log_data)) {
                $log_entry = json_encode($log_data)."\n";
            } else {
                $log_entry = '['.gmdate('H:i:s').'] '.$log_data."\n";
            }

            // Append to log file
            file_put_contents($log_file, $log_entry, FILE_APPEND);

            // Implement log rotation - delete logs older than 30 days
            $this->rotate_logs($logs_dir);
        }

        /**
         * Rotate logs by deleting files older than 30 days
         *
         * @param string $logs_dir Directory containing logs
         *
         * @since 1.2.0
         */
        private function rotate_logs($logs_dir)
        {
            // Only run rotation check occasionally (1% chance) to avoid performance impact
            if (wp_rand(1, 100) !== 1) {
                return;
            }

            $retention_days = 30; // Default retention period
            $current_time = time();

            // Get all log files
            $log_files = glob($logs_dir.'/*.log');

            foreach ($log_files as $file) {
                $file_modified_time = filemtime($file);
                $days_old = floor(($current_time - $file_modified_time) / (60 * 60 * 24));

                // Delete files older than retention period
                if ($days_old > $retention_days) {
                    @wp_delete_file($file);
                }
            }
        }

        /**
         * Log debug messages
         *
         * @param string $message Debug message.
         *
         * @since 1.0.0
         */
        private function log_debug($message)
        {
            if ($this->settings->is_debug_mode()) {
                $this->write_to_log('debug', $message);
            }
        }

        /**
         * Check if a user is authenticated via DocCheck (either session or WordPress user)
         *
         * @return bool True if authenticated, false otherwise
         * @since 2.1.0
         */
        public function is_authenticated()
        {
            // Check for WordPress user with DocCheck ID
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $doccheck_id = get_user_meta($user_id, 'doccheck_unique_id', true);
                if (!empty($doccheck_id)) {
                    return true;
                }
            }

            // Check for DocCheck session
            if (isset($_SESSION['doccheck_session_auth']) && $_SESSION['doccheck_session_auth'] === true) {
                return true;
            }

            return false;
        }

        /**
         * Get DocCheck user data if available
         *
         * @return array User data or empty array if not available
         * @since 2.1.0
         */
        public function get_doccheck_user_data()
        {
            // For WordPress users
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $doccheck_id = get_user_meta($user_id, 'doccheck_unique_id', true);
                if (!empty($doccheck_id)) {
                    // Collect all doccheck_ prefixed meta
                    $user_data = array();
                    $user_meta = get_user_meta($user_id);
                    foreach ($user_meta as $meta_key => $meta_value) {
                        if (strpos($meta_key, 'doccheck_') === 0) {
                            $user_data[str_replace('doccheck_', '', $meta_key)] = $meta_value[0];
                        }
                    }
                    return $user_data;
                }
            }

            // For session users
            if (isset($_SESSION['doccheck_session_auth']) && $_SESSION['doccheck_session_auth'] === true) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitize after retrieval.
                $session_data = isset($_SESSION['doccheck_session_data']) ? (array) $_SESSION['doccheck_session_data'] : array();
                
                // Sanitize session data
                return array_map(function ($value) {
                    return is_string($value) ? sanitize_text_field($value) : $value;
                }, $session_data);
            }

            return array();
        }
    }
}
