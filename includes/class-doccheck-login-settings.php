<?php
/**
 * Class for encapsulating plugin settings
 *
 * @since      1.0.0
 * @package    DocCheck_Login
 */

if (!class_exists('DocCheck_Login_Settings')) {
    class DocCheck_Login_Settings
    {
        /**
         * DocCheck OAuth Client ID
         *
         * @since  1.0.0
         * @access private
         * @var    string $client_id Client ID for OAuth authentication.
         */
        private $client_id = '';

        /**
         * DocCheck OAuth Client Secret
         *
         * @since  1.0.0
         * @access private
         * @var    string $client_secret Client Secret for OAuth authentication.
         */
        private $client_secret = '';

        /**
         * DocCheck Authentication Server URL
         *
         * @since  1.0.0
         * @access private
         * @var    string $auth_server_url Authentication server URL.
         */
        private $auth_server_url = 'https://auth.doccheck.com';

        /**
         * Redirect Route for OAuth callback
         *
         * @since  1.0.0
         * @access private
         * @var    string $redirect_route Route for OAuth callback.
         */
        private $redirect_route = '/doccheck/callback';

        /**
         * Redirect URI for OAuth callback (legacy support)
         *
         * @since  1.0.0
         * @access private
         * @var    string $redirect_uri Full URI for OAuth callback.
         */
        private $redirect_uri = '';

        /**
         * Default User Role for new users
         *
         * @since  1.0.0
         * @access private
         * @var    string $default_role Default WordPress role for new users.
         */
        private $default_role = 'subscriber';

        /**
         * Default OAuth scopes
         *
         * @since  1.0.0
         * @access private
         * @var    string $default_scopes Whitespace separated list of scopes.
         */
        private $default_scopes = 'openid profile';

        /**
         * Default Target Page ID
         *
         * @since  1.0.0
         * @access private
         * @var    int $default_target_page Page ID where users will be redirected after login.
         */
        private $default_target_page = '';

        /**
         * Debug Mode
         *
         * @since  1.0.0
         * @access private
         * @var    string $debug_mode Whether debug logging is enabled ('on' or 'off').
         */
        private $debug_mode = 'off';

        /**
         * Fallback Behavior for Insufficient User Data
         *
         * @since  1.3.0
         * @access private
         * @var    string $fallback_behavior How to handle missing user data ('session_only')
         */
        private $fallback_behavior = 'session_only';

        /**
         * Authentication Mode
         *
         * @since  2.1.0
         * @access private
         * @var    string $authentication_mode Authentication mode ('anonymous_session' or 'wordpress_user')
         */
        private $authentication_mode = 'anonymous_session';

        /**
         * Whether first-time DocCheck logins may create local WordPress users.
         *
         * @since  2.4.1
         * @access private
         * @var    string $allow_user_creation 'on' or 'off'.
         */
        private $allow_user_creation = 'off';

        /**
         * Global protection: Make all Pages Private
         *
         * @since  2.1.2
         * @access private
         * @var    string $make_all_pages_private Whether all pages are private by default ('on' or 'off').
         */
        private $make_all_pages_private = 'off';

        /**
         * Auto-assign Parent Configurations to all Child Pages
         *
         * @since  2.1.2
         * @access private
         * @var    string $auto_assign_parent_config Whether child pages inherit protection from parents ('on' or 'off').
         */
        private $auto_assign_parent_config = 'off';

        /**
         * DocCheck Login Button Version
         *
         * @since  2.1.3
         * @access private
         * @var    string $login_button_version Version for DocCheck Login Button ('@latest' or version string).
         */
        private $login_button_version = '@latest';

        /**
         * Selected scopes configuration
         *
         * @since  2.0.0
         * @access private
         * @var    array $selected_scopes Associative array of scope => true/false
         */
        private $selected_scopes = [
            'unique_id' => true,  // Required, cannot be disabled
            'country_iso_code' => false,
            'language' => false,
            'profession' => false,
            'address' => false,
            'occupation_detail' => false,
            'email' => false,
            'name' => false
        ];

        /**
         * Selected properties configuration
         *
         * @since  2.0.0
         * @access private
         * @var    array $selected_properties Associative array of property => true/false
         */
        private $selected_properties = [
            // Default selections for properties
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
        ];

        /**
         * Initialize the settings class
         *
         * @param array $settings Optional. Plugin settings array from WordPress options.
         *
         * @since 1.0.0
         */
        public function __construct($settings = [])
        {
            // Load settings from WordPress options or use defaults
            $this->load_settings($settings);
        }

        /**
         * Load settings from array
         *
         * @param array $settings Settings array from WordPress options.
         *
         * @since 1.0.0
         */
        private function load_settings($settings)
        {
            if (!empty($settings)) {
                // For each property in this class, check if it exists in the settings array
                foreach (get_object_vars($this) as $property => $default_value) {
                    if (isset($settings[$property])) {
                        // For login_button_version, ensure it's not empty string
                        if ($property === 'login_button_version' && empty($settings[$property])) {
                            continue;
                        }

                        $this->$property = $settings[$property];
                    }
                }
            }

            //TODO: remove later => only needed because settings cannot be reverted
            $this->redirect_route = '/doccheck/callback';
            $this->auth_server_url = 'https://auth.doccheck.com';

            // Set the redirect_uri if empty but redirect_route is set
            if (empty($this->redirect_uri) && !empty($this->redirect_route)) {
                $this->redirect_uri = home_url($this->redirect_route);
            }
        }

        /**
         * Get Client ID
         *
         * @return string Client ID.
         * @since 1.0.0
         */
        public function get_client_id()
        {
            return $this->client_id;
        }

        /**
         * Set Client ID
         *
         * @param string $client_id Client ID.
         *
         * @since 1.0.0
         */
        public function set_client_id($client_id)
        {
            $this->client_id = $client_id;
        }

        /**
         * Get Client Secret
         *
         * @return string Client Secret.
         * @since 1.0.0
         */
        public function get_client_secret()
        {
            return $this->client_secret;
        }

        /**
         * Set Client Secret
         *
         * @param string $client_secret Client Secret.
         *
         * @since 1.0.0
         */
        public function set_client_secret($client_secret)
        {
            $this->client_secret = $client_secret;
        }

        /**
         * Get Auth Server URL
         *
         * @return string Auth Server URL.
         * @since 1.0.0
         */
        public function get_auth_server_url()
        {
            return $this->auth_server_url;
        }

        /**
         * Set Auth Server URL
         *
         * @param string $auth_server_url Auth Server URL.
         *
         * @since 1.0.0
         */
        public function set_auth_server_url($auth_server_url)
        {
            $this->auth_server_url = $auth_server_url;
        }

        /**
         * Get Redirect Route
         *
         * @return string Redirect Route.
         * @since 1.0.0
         */
        public function get_redirect_route()
        {
            return $this->redirect_route;
        }

        /**
         * Set Redirect Route
         *
         * @param string $redirect_route Redirect Route.
         *
         * @since 1.0.0
         */
        public function set_redirect_route($redirect_route)
        {
            $this->redirect_route = $redirect_route;
            // Update redirect_uri to match the new route
            $this->redirect_uri = home_url($redirect_route);
        }

        /**
         * Get Redirect URI
         *
         * @return string Redirect URI.
         * @since 1.0.0
         */
        public function get_redirect_uri()
        {
            return $this->redirect_uri;
        }

        /**
         * Set Redirect URI
         *
         * @param string $redirect_uri Redirect URI.
         *
         * @since 1.0.0
         */
        public function set_redirect_uri($redirect_uri)
        {
            $this->redirect_uri = $redirect_uri;
        }

        /**
         * Get Default Role
         *
         * @return string Default Role.
         * @since 1.0.0
         */
        public function get_default_role()
        {
            return $this->default_role;
        }

        /**
         * Set Default Role
         *
         * @param string $default_role Default Role.
         *
         * @since 1.0.0
         */
        public function set_default_role($default_role)
        {
            $this->default_role = $default_role;
        }

        /**
         * Get Default Scopes
         *
         * @return string Default Scopes.
         * @since 1.0.0
         */
        public function get_default_scopes()
        {
            return $this->default_scopes;
        }

        /**
         * Set Default Scopes
         *
         * @param string $default_scopes Default Scopes.
         *
         * @since 1.0.0
         */
        public function set_default_scopes($default_scopes)
        {
            $this->default_scopes = $default_scopes;
        }

        /**
         * Get Default Target Page
         *
         * @return int Default Target Page ID.
         * @since 1.0.0
         */
        public function get_default_target_page()
        {
            return $this->default_target_page;
        }

        /**
         * Set Default Target Page
         *
         * @param int $default_target_page Default Target Page ID.
         *
         * @since 1.0.0
         */
        public function set_default_target_page($default_target_page)
        {
            $this->default_target_page = $default_target_page;
        }

        /**
         * Get Debug Mode
         *
         * @return string Debug Mode ('on' or 'off').
         * @since 1.0.0
         */
        public function get_debug_mode()
        {
            return $this->debug_mode;
        }

        /**
         * Set Debug Mode
         *
         * @param string $debug_mode Debug Mode ('on' or 'off').
         *
         * @since 1.0.0
         */
        public function set_debug_mode($debug_mode)
        {
            $this->debug_mode = $debug_mode;
        }

        /**
         * Check if Debug Mode is enabled
         *
         * @return bool True if debug mode is enabled, false otherwise.
         * @since 1.0.0
         */
        public function is_debug_mode()
        {
            return $this->debug_mode === 'on';
        }

        /**
         * Get Fallback Behavior
         *
         * @return string Fallback behavior ('deny', 'session_only', or 'create_default').
         * @since 1.3.0
         */
        public function get_fallback_behavior()
        {
            return $this->fallback_behavior;
        }

        /**
         * Set Fallback Behavior
         *
         * @param string $fallback_behavior Fallback behavior.
         *
         * @since 1.3.0
         */
        public function set_fallback_behavior($fallback_behavior)
        {
            $this->fallback_behavior = $fallback_behavior;
        }

        /**
         * Get Authentication Mode
         *
         * @return string Authentication mode ('anonymous_session' or 'wordpress_user').
         * @since 2.1.0
         */
        public function get_authentication_mode()
        {
            return $this->authentication_mode;
        }

        /**
         * Set Authentication Mode
         *
         * @param string $authentication_mode Authentication mode.
         *
         * @since 2.1.0
         */
        public function set_authentication_mode($authentication_mode)
        {
            $this->authentication_mode = $authentication_mode;
        }

        /**
         * Check whether automatic local user creation is enabled.
         *
         * @return bool
         * @since 2.4.1
         */
        public function get_allow_user_creation()
        {
            return $this->allow_user_creation === 'on';
        }

        /**
         * Set automatic local user creation setting.
         *
         * @param string $value 'on' or 'off'
         * @since 2.4.1
         */
        public function set_allow_user_creation($value)
        {
            $this->allow_user_creation = $value;
        }

        /**
         * Get Make All Pages Private setting
         *
         * @return bool Whether all pages are private.
         * @since 2.1.2
         */
        public function get_make_all_pages_private()
        {
            return $this->make_all_pages_private === 'on';
        }

        /**
         * Set Make All Pages Private setting
         *
         * @param string $value 'on' or 'off'
         * @since 2.1.2
         */
        public function set_make_all_pages_private($value)
        {
            $this->make_all_pages_private = $value;
        }

        /**
         * Get Auto-assign Parent Config setting
         *
         * @return bool
         * @since 2.1.2
         */
        public function get_auto_assign_parent_config()
        {
            return $this->auto_assign_parent_config === 'on';
        }

        /**
         * Set Auto-assign Parent Config setting
         *
         * @param string $value 'on' or 'off'
         * @since 2.1.2
         */
        public function set_auto_assign_parent_config($value)
        {
            $this->auto_assign_parent_config = $value;
        }

        /**
         * Get DocCheck Login Button Version
         *
         * @return string Version string (e.g., '3.2.7' or '@latest').
         * @since 2.1.3
         */
        public function get_login_button_version()
        {
            if (empty($this->login_button_version)) {
                return '@latest';
            }
            return $this->login_button_version;
        }

        /**
         * Set DocCheck Login Button Version
         *
         * @param string $login_button_version Version string (e.g., '3.2.7' or '@latest').
         * @since 2.1.3
         */
        public function set_login_button_version($login_button_version)
        {
            $this->login_button_version = $login_button_version;
        }

        /**
         * Get Selected Scopes
         *
         * @return array Selected scopes configuration.
         * @since 2.0.0
         */
        public function get_selected_scopes()
        {
            return $this->selected_scopes;
        }

        /**
         * Set Selected Scopes
         *
         * @param array $selected_scopes Selected scopes configuration.
         *
         * @since 2.0.0
         */
        public function set_selected_scopes($selected_scopes)
        {
            $this->selected_scopes = $selected_scopes;
            // Always ensure unique_id is selected as it's required
            $this->selected_scopes['unique_id'] = true;
        }

        /**
         * Get Selected Properties
         *
         * @return array Selected properties configuration.
         * @since 2.0.0
         */
        public function get_selected_properties()
        {
            return $this->selected_properties;
        }

        /**
         * Set Selected Properties
         *
         * @param array $selected_properties Selected properties configuration.
         *
         * @since 2.0.0
         */
        public function set_selected_properties($selected_properties)
        {
            $this->selected_properties = $selected_properties;
            // Always ensure unique_id is selected as it's required
            $this->selected_properties['unique_id'] = true;
        }

        /**
         * Get scopes string for OAuth request
         *
         * @return string Space-separated string of selected scopes
         * @since 2.0.0
         */
        public function get_scopes_string()
        {
            $scopes = [];
            foreach ($this->selected_scopes as $scope => $selected) {
                // In anonymous session mode, we don't send the unique_id scope as part of the request,
                // even though it's technically mandatory and selected in the plugin settings.
                if ($this->authentication_mode === 'anonymous_session' && $scope === 'unique_id') {
                    continue;
                }

                if ($selected) {
                    $scopes[] = $scope;
                }
            }
            return implode(' ', $scopes);
        }

        /**
         * Convert settings object to array
         *
         * @return array Settings as array.
         * @since 1.0.0
         */
        public function to_array()
        {
            return get_object_vars($this);
        }

        /**
         * Magic getter for settings (array access compatibility)
         *
         * @param string $name Property name.
         *
         * @return mixed Property value or null if not found.
         * @since 1.0.0
         */
        public function __get($name)
        {
            if (property_exists($this, $name)) {
                return $this->$name;
            }
            return null;
        }

        /**
         * Magic isset for settings (array access compatibility)
         *
         * @param string $name Property name.
         *
         * @return bool Whether the property exists.
         * @since 1.0.0
         */
        public function __isset($name)
        {
            return property_exists($this, $name) && isset($this->$name);
        }
    }
}
