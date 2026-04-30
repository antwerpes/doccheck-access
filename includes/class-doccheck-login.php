<?php
/**
 * The main plugin class
 *
 * @since      1.0.0
 * @package    DocCheck_Login
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('DocCheck_Login')) {
    class DocCheck_Login
    {

        /**
         * Plugin settings
         *
         * @since  1.0.0
         * @access protected
         * @var    DocCheck_Login_Settings $settings The plugin settings.
         */
        protected $settings;

        /**
         * OAuth handler instance
         *
         * @since  1.0.0
         * @access protected
         * @var    DocCheck_Login_OAuth $oauth The OAuth handler.
         */
        protected $oauth;

        /**
         * Per-request cache for protection results keyed by post ID
         *
         * @since  2.2.0
         * @access private
         * @var    array<int, bool>
         */
        private $protection_cache = [];

        /**
         * Initialize the class
         *
         * @since 1.0.0
         */
        public function __construct()
        {
            // Load dependencies first to ensure settings class is available
            $this->load_dependencies();

            // Initialize settings from WordPress options
            $this->settings = new DocCheck_Login_Settings(get_option('doccheck_login_settings', []));

            // Initialize OAuth handler
            $this->oauth = new DocCheck_Login_OAuth($this->settings);
        }

        /**
         * Load required dependencies
         *
         * @since 1.0.0
         */
        private function load_dependencies()
        {
            // Load the settings class
            require_once DOCCHECK_ACCESS_PLUGIN_PATH . 'includes/class-doccheck-login-settings.php';

            // Load admin functionality in admin area
            if (is_admin()) {
                require_once DOCCHECK_ACCESS_PLUGIN_PATH . 'admin/class-doccheck-login-admin.php';
            }
        }

        /**
         * Run the plugin
         *
         * @since 1.0.0
         */
        public function run()
        {
            // Register hooks
            $this->define_public_hooks();
            $this->define_admin_hooks();

            // Register privacy policy content
            add_action('admin_init', [$this, 'add_privacy_policy_content']);

            // Set up rewrite rules for OAuth callback
            add_action('init', [$this, 'add_rewrite_rules']);

            // Handle OAuth callback
            add_action('parse_request', [$this, 'handle_oauth_callback']);

            // Clear DocCheck session on logout
            add_action('wp_logout', [$this, 'clear_doccheck_session']);
        }

        /**
         * Define admin hooks
         *
         * @since 1.0.0
         */
        private function define_admin_hooks()
        {
            if (is_admin()) {
                // Pass settings object to admin class
                $admin = new DocCheck_Login_Admin($this->settings);
                add_action('admin_menu', [$admin, 'add_admin_menu']);
                add_action('admin_init', [$admin, 'register_settings']);
            }
        }

        /**
         * Define public hooks
         *
         * @since 1.0.0
         */
        private function define_public_hooks()
        {
            // Register shortcodes
            add_shortcode('doccheck_login', [$this, 'login_shortcode']);
            add_shortcode('dc-hide-content', [$this, 'hide_content_shortcode']);
            add_shortcode('dc_logout', [$this, 'logout_shortcode']);
            add_shortcode('dc_sitemap', [$this, 'sitemap_shortcode']);

            // Script is enqueued on demand inside login_shortcode() and protect_page_content().

            // Protect pages if configured
            add_filter('the_content', [$this, 'protect_page_content']);
            add_action('template_redirect', [$this, 'template_redirect_protection']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_protected_fallback_styles']);

            // Exclude protected pages from WordPress XML sitemap
            add_filter('wp_sitemaps_posts_query_args', [$this, 'filter_xml_sitemap_posts'], 10, 2);

            // Ensure logout uses requested redirect_to when present
            add_filter('logout_redirect', [$this, 'handle_logout_redirect'], 10, 3);
        }

        /**
         * Add rewrite rules for OAuth callback
         *
         * @since 1.0.0
         */
        public function add_rewrite_rules()
        {
            // macht get_query_var('doccheck') öffentlich
            add_rewrite_tag('%doccheck%', '([^&]+)');

            // Regex mit Endanker ($) – matcht /doccheck/callback und /doccheck/callback/
            add_rewrite_rule('^doccheck/callback/?$', 'index.php?doccheck=callback', 'top');

            register_activation_hook(__FILE__, function () {
                do_action('doccheck_login_init');        // stellt sicher, dass Regeln registriert sind
                flush_rewrite_rules();    // schreibt sie in die DB
            });
        }

        /**
         * Handle OAuth callback
         *
         * @param WP $wp Current WordPress instance.
         *
         * @since 1.0.0
         */
        public function handle_oauth_callback($wp)
        {
            if (isset($wp->query_vars['doccheck']) && $wp->query_vars['doccheck'] === 'callback') {
                $this->oauth->handle_callback();
                exit;
            }
        }


        /**
         * Get sanitized UTM and tracking parameters from the current request URL.
         *
         * Captures common tracking parameters (UTM, DocCheck-specific, and app state),
         * sanitizes their values, and returns them as a key-value array ready for
         * re-appending to the post-login redirect URL.
         *
         * A nonce check is performed when the parameters arrive via a form submission
         * (utm_nonce present). When parameters are plain URL query args (the usual case
         * for tracking links) no nonce is required and processing continues normally.
         *
         * @return array Associative array of sanitized parameter names → escaped values.
         * @since 2.4.0
         */
        public function dcl_get_sanitized_utm_parameters()
        {
            $utm_parameters = array(
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_term',
                'utm_content',
                'route',
                'uadpub',
                'cmpid',
                'ucampaign',
                'source',
                'umedium',
                'ucreative',
                'outcome',
                'uplace',
                'state',
            );

            $sanitized_utm = array();

            // Verify nonce only when this is a form submission that includes one.
            // Tracking parameters arriving via plain URL do not carry a nonce, which is expected.
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $is_valid_nonce = isset( $_GET['utm_nonce'] )
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                ? wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['utm_nonce'] ) ), 'dcl_utm_parameters' )
                : false;

            foreach ( $utm_parameters as $param ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- UTM params are read-only tracking data from URL; nonce is not applicable for standard tracking links.
                $value = isset( $_GET[ $param ] ) ? sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) : '';

                if ( $value !== '' ) {
                    $sanitized_utm[ $param ] = esc_html( $value );
                }
            }

            return $sanitized_utm;
        }

        /**
         * Shortcode for embedding login button
         *
         * @param array $atts Shortcode attributes.
         *
         * @return string Rendered HTML.
         * @since 1.0.0
         */
        public function login_shortcode($atts)
        {
            // If user is logged in, don't show the button
            if ($this->oauth->is_authenticated()) {
                return '';
            }

            // Enqueue the login button script only when the button is actually rendered.
            $this->enqueue_scripts();

            // Parse shortcode attributes
            $atts = shortcode_atts(array(
                'size' => 'medium',
                'language' => '',
                'state' => '',
                'scope' => '',
                'samepageredirect' => '0',  // New attribute for same-page redirect
            ), $atts);

            $client_id = $this->settings->get_client_id();
            $auth_server_url = $this->settings->get_auth_server_url();
            $redirect_uri = $this->settings->get_redirect_uri();

            $html = '<div class="doccheck-access-container">';

            // Get the two-letter language code from the WordPress locale if not provided
            if (empty($atts['language'])) {
                $locale = get_locale();
                $lang_code = substr($locale, 0, 2); // Extract first two characters (e.g., "de" from "de_DE")
            } else {
                $lang_code = $atts['language'];
            }

            // Get scopes from settings if not provided
            $scopes = !empty($atts['scope']) ? $atts['scope'] : $this->settings->get_scopes_string();

            // Capture tracking/UTM parameters present on the current page so they can
            // be re-attached to the destination URL after the OAuth round-trip.
            $utm_params = $this->dcl_get_sanitized_utm_parameters();

            // Custom app-state from the shortcode attribute (e.g. [doccheck_login state="bereich_orange"]).
            // Per the Anleitung this value is used for post-login routing or context passing.
            // It is ALWAYS routed through the nonce system – never used as the raw OAuth state –
            // so the CSRF nonce transient is always created and the callback never rejects it.
            $app_state = sanitize_text_field($atts['state']);

            // Handle redirect URL in state parameter if samepageredirect is enabled
            if ($atts['samepageredirect'] === '1') {
                // Build current page URL and embed UTM params so they survive the round-trip
                global $wp;
                $current_url = home_url(add_query_arg($utm_params, $wp->request));

                // Generate state with encrypted redirect URL (UTMs are already in the URL)
                $state = $this->oauth->generate_state($current_url, [], $app_state);
            } else {
                // Always generate a nonce-based state carrying UTM params and optional app-state.
                // Passing a raw custom value directly as the OAuth state would bypass nonce
                // validation and cause the callback to reject it with invalid_state.
                $state = $this->oauth->generate_state(null, $utm_params, $app_state);
            }

            // Keep the original dc-login-button
            $html .= '<dc-login-button
                size="' . esc_attr($atts['size']) . '"
                language="' . esc_attr($lang_code) . '"
                loginClientId="' . esc_attr($client_id) . '"
                redirectUri="' . esc_attr($redirect_uri) . '"
                scope="' . esc_attr($scopes) . '"
                base="' . esc_attr($auth_server_url) . '"
                state="' . esc_attr($state) . '">
            </dc-login-button>';
            $html .= '</div>';

            return $html;
        }

        /**
         * Shortcode to hide content for non-logged-in users
         *
         * @param array $atts Shortcode attributes.
         * @param string $content The content to protect.
         *
         * @return string Rendered HTML or empty string.
         * @since 1.0.0
         */
        public function hide_content_shortcode($atts, $content = null)
        {
            if ($this->oauth->is_authenticated()) {
                if (null === $content) {
                    return '';
                }

                return wp_kses_post(do_shortcode($content));
            }
            return '';
        }

        /**
         * Shortcode for embedding logout link
         *
         * @param array $atts Shortcode attributes.
         *
         * @return string Rendered HTML.
         * @since 1.0.0
         */
        public function logout_shortcode($atts)
        {
            // If user is not logged in, don't show the button
            if (!$this->oauth->is_authenticated()) {
                return '';
            }

            $atts = shortcode_atts(array(
                'redirect' => home_url(),
            ), $atts);

            $redirect = esc_url_raw($atts['redirect']);

            return '<a href="' . esc_url(wp_logout_url($redirect)) . '">' . esc_html__('Logout', 'doccheck-access') . '</a>';
        }

        /**
         * Enqueue the DocCheck login button script.
         *
         * Called on demand (from login_shortcode / protect_page_content) rather than
         * unconditionally on every page, so the CDN is only contacted when the login
         * button is actually rendered.
         *
         * @since 1.0.0
         */
        public function enqueue_scripts()
        {
            if ( wp_script_is( 'doccheck-access-button', 'enqueued' ) ) {
                return;
            }

            $version    = $this->settings->get_login_button_version();
            $script_url = sprintf( 'https://dccdn.de/static.doccheck.com/components/login-button/%s/main.js', $version );

            wp_enqueue_script(
                'doccheck-access-button',
                $script_url,
                [],
                DOCCHECK_ACCESS_VERSION,
                true // Load in footer to avoid blocking page render.
            );
        }

        /**
         * Enqueue fallback template styles when plugin fallback template is used.
         *
         * @since 2.4.1
         * @return void
         */
        public function enqueue_protected_fallback_styles()
        {
            if (!$this->is_current_page_protected()) {
                return;
            }

            if (locate_template(['doccheck-protected.php'])) {
                return;
            }

            wp_enqueue_style(
                    'doccheck-access-protected-fallback-style',
                    DOCCHECK_ACCESS_PLUGIN_URL . 'assets/css/doccheck-login-protected-fallback.css',
                    array(),
                    DOCCHECK_ACCESS_VERSION
            );
        }

        /**
         * Protect page content if it's marked as protected
         *
         * @param string $content The page content.
         * @return string The modified content.
         * @since 2.1.0
         */
        public function protect_page_content($content)
        {
            if (is_singular()) {
                if ($this->is_current_page_protected()) {
                    // Page/Post is protected and user is not authenticated
                    // Show login button instead of content
                    return $this->login_shortcode(['samepageredirect' => '1']);
                }
            }

            return $content;
        }

        /**
         * Global protection check via template_redirect.
         * This ensures that pages built with ACF or other page builders are also protected,
         * even if they don't use the_content filter.
         *
         * @since 2.1.1
         */
        public function template_redirect_protection()
        {
            if ($this->is_current_page_protected()) {
                // We want to make sure that even if the theme doesn't use the_content(),
                // we still protect the page.

                // For Sage and other modern themes using template_include:
                add_filter('template_include', function ($template) {
                    if (!$this->is_current_page_protected()) {
                        return $template;
                    }

                    // 1. Try to find a template in the theme first
                    $template_name = 'doccheck-protected.php';
                    $theme_template = locate_template([$template_name]);

                    // 2. Fallback to plugin default
                    $fallback_template = DOCCHECK_ACCESS_PLUGIN_PATH . 'templates/protected-fallback.php';
                    $target_template = $theme_template ? $theme_template : $fallback_template;

                    return apply_filters('doccheck_protected_template', $target_template);
                }, 99);
            }
        }

        /**
         * Helper method to check if current page is DocCheck protected
         * Useful for manual checks in templates (e.g. for ACF fields)
         *
         * @return bool
         * @since 2.1.1
         */
        public function is_current_page_protected()
        {
            if (!is_singular()) {
                return false;
            }

            $post_id = get_the_ID();

            // Return cached result — this method is called up to 3x per request
            if (isset($this->protection_cache[$post_id])) {
                return $this->protection_cache[$post_id];
            }

            // Step 1: Check if this page requires protection at all (cheapest first)
            if (!$this->is_page_marked_protected($post_id)) {
                $this->protection_cache[$post_id] = false;
                return false;
            }

            // Step 2: Page is protected — check if the current user has access
            $result = !$this->current_user_has_access($post_id);
            $this->protection_cache[$post_id] = $result;
            return $result;
        }

        /**
         * Check whether a post is marked as protected (per-page, global, or inherited)
         *
         * @param int $post_id Post ID.
         * @return bool
         * @since 2.2.0
         */
        public function is_page_marked_protected($post_id)
        {
            if ($this->settings->get_make_all_pages_private()) {
                return true;
            }

            if (get_post_meta($post_id, '_doccheck_protected', true)) {
                return true;
            }

            if ($this->settings->get_auto_assign_parent_config()) {
                foreach (get_post_ancestors($post_id) as $ancestor_id) {
                    if (get_post_meta($ancestor_id, '_doccheck_protected', true)) {
                        return true;
                    }
                }
            }

            return false;
        }

        /**
         * Check whether the current user has access to a protected post
         *
         * @param int $post_id Post ID.
         * @return bool True if user has access, false otherwise.
         * @since 2.2.0
         */
        private function current_user_has_access($post_id)
        {
            $auth_mode = $this->settings->get_authentication_mode();
            $is_dc_authenticated = $this->oauth->is_authenticated();
            $is_wp_user = ($auth_mode === 'wordpress_user' && is_user_logged_in());

            if (!$is_dc_authenticated && !$is_wp_user) {
                return false;
            }

            // Role restrictions only apply in wordpress_user mode
            if ($auth_mode !== 'wordpress_user') {
                return true;
            }

            $allowed_roles = $this->get_effective_allowed_roles($post_id);

            if (empty($allowed_roles)) {
                return true;
            }

            if (!is_user_logged_in()) {
                return false;
            }

            return !empty(array_intersect(wp_get_current_user()->roles, $allowed_roles));
        }

        /**
         * Get the effective allowed roles for a post, considering inheritance
         *
         * @param int $post_id Post ID.
         * @return array Allowed role slugs (empty = all roles allowed).
         * @since 2.2.0
         */
        private function get_effective_allowed_roles($post_id)
        {
            $allowed_roles = get_post_meta($post_id, '_doccheck_allowed_roles', true);
            if (is_array($allowed_roles) && !empty($allowed_roles)) {
                return $allowed_roles;
            }

            if ($this->settings->get_auto_assign_parent_config()) {
                foreach (get_post_ancestors($post_id) as $ancestor_id) {
                    $ancestor_roles = get_post_meta($ancestor_id, '_doccheck_allowed_roles', true);
                    if (is_array($ancestor_roles) && !empty($ancestor_roles)) {
                        return $ancestor_roles;
                    }
                }
            }

            return [];
        }

        /**
         * Clear DocCheck session data on logout
         *
         * @since 2.1.0
         */
        public function clear_doccheck_session()
        {
            if (session_status() === PHP_SESSION_ACTIVE) {
                unset($_SESSION['doccheck_session_auth']);
                unset($_SESSION['doccheck_session_data']);
                unset($_SESSION['doccheck_session_time']);
                return;
            }

            // Start only when a session already exists, so we don't create a new one on logout.
            if (!headers_sent() && isset($_COOKIE[session_name()])) {
                session_start();
                unset($_SESSION['doccheck_session_auth']);
                unset($_SESSION['doccheck_session_data']);
                unset($_SESSION['doccheck_session_time']);
            }
        }

        /**
         * Register plugin privacy policy content for the Privacy Policy Guide.
         *
         * @since 1.0.0
         */
        public function add_privacy_policy_content()
        {
            if (!function_exists('wp_add_privacy_policy_content')) {
                return;
            }

            $content = '<h2>' . esc_html__('DocCheck Login', 'doccheck-access') . '</h2>'
                . '<p>' . esc_html__(
                    'When a visitor logs in via DocCheck, this site receives profile data from the DocCheck API (such as unique ID, profession, country, and — if consented to — name, email address, and further fields). This data is stored as WordPress user meta and is used solely for authentication and access control.',
                    'doccheck-access'
                ) . '</p>'
                . '<p>' . esc_html__(
                    'In anonymous-session mode no persistent WordPress user record is created; data is held only for the duration of the PHP session.',
                    'doccheck-access'
                ) . '</p>'
                . '<p>' . esc_html__(
                    'Debug logging (when enabled) writes truncated OAuth tokens and session IDs to files in the uploads directory. Log files are automatically deleted after 30 days.',
                    'doccheck-access'
                ) . '</p>';

            wp_add_privacy_policy_content(
                __('DocCheck Login', 'doccheck-access'),
                wp_kses_post($content)
            );
        }

        /**
         * Honor requested redirect_to on logout when provided
         *
         * @param string $redirect_to The redirect URL WordPress plans to use.
         * @param string $requested_redirect_to The raw redirect_to param from the request.
         * @param WP_User|int $user User object or ID.
         * @return string Final redirect URL after logout.
         * @since 2.2.0
         */
        public function handle_logout_redirect($redirect_to, $requested_redirect_to, $user)
        {
            if (!empty($requested_redirect_to)) {
                // Validate against allowed hosts before using the caller-supplied URL.
                $validated = wp_validate_redirect($requested_redirect_to, '');
                if ($validated) {
                    return $validated;
                }
            }

            // Fallback to WordPress' computed target (often login screen)
            return $redirect_to;
        }

        /**
         * Shortcode that renders an HTML sitemap, hiding protected pages from unauthenticated visitors
         *
         * @param array $atts Shortcode attributes.
         * @return string Rendered HTML.
         * @since 2.3.0
         */
        public function sitemap_shortcode($atts)
        {
            $atts = shortcode_atts([
                'post_type'      => '',
                'show_protected' => '',
                'depth'          => 0,
                'exclude'        => '',
            ], $atts);

            // Determine whether to show protected pages
            if ($atts['show_protected'] === 'yes') {
                $show_protected = true;
            } elseif ($atts['show_protected'] === 'no') {
                $show_protected = false;
            } else {
                $show_protected = $this->oauth->is_authenticated();
            }

            // Determine post types to display
            if (!empty($atts['post_type'])) {
                $post_types = array_map('trim', explode(',', $atts['post_type']));
            } else {
                $post_types = get_post_types(['public' => true], 'names');
                unset($post_types['attachment']);
            }

            $exclude_ids = !empty($atts['exclude'])
                ? array_map('intval', explode(',', $atts['exclude']))
                : [];

            $depth = intval($atts['depth']);
            $html  = '<div class="dc-sitemap">';

            foreach ($post_types as $post_type) {
                $type_obj = get_post_type_object($post_type);
                if (!$type_obj) {
                    continue;
                }

                // Build exclusion list: user-specified + protected (when hidden)
                $type_exclude = $exclude_ids;
                if (!$show_protected) {
                    $type_exclude = array_merge($type_exclude, $this->get_protected_post_ids($post_type));
                }
                $type_exclude = array_unique($type_exclude);

                if ($type_obj->hierarchical) {
                    $pages_args = [
                        'post_type' => $post_type,
                        'title_li'  => '',
                        'echo'      => false,
                        'depth'     => $depth,
                    ];
                    if (!empty($type_exclude)) {
                        $pages_args['exclude'] = implode(',', $type_exclude);
                    }

                    $list = wp_list_pages($pages_args);
                    if (!empty($list)) {
                        $html .= '<h3>' . esc_html($type_obj->labels->name) . '</h3>';
                        $html .= '<ul>' . $list . '</ul>';
                    }
                } else {
                    $query_args = [
                        'post_type'      => $post_type,
                        'posts_per_page' => -1,
                        'orderby'        => 'title',
                        'order'          => 'ASC',
                        'post_status'    => 'publish',
                    ];
                    if (!empty($type_exclude)) {
                        $query_args['post__not_in'] = $type_exclude;
                    }

                    $query = new WP_Query($query_args);
                    if ($query->have_posts()) {
                        $html .= '<h3>' . esc_html($type_obj->labels->name) . '</h3>';
                        $html .= '<ul>';
                        while ($query->have_posts()) {
                            $query->the_post();
                            $html .= '<li><a href="' . esc_url(get_permalink()) . '">'
                                   . esc_html(get_the_title()) . '</a></li>';
                        }
                        $html .= '</ul>';
                        wp_reset_postdata();
                    }
                }
            }

            $html .= '</div>';
            return $html;
        }

        /**
         * Filter WordPress XML sitemap to exclude protected posts
         *
         * @param array  $args      Query arguments for the sitemap.
         * @param string $post_type The post type being queried.
         * @return array Modified query arguments.
         * @since 2.3.0
         */
        public function filter_xml_sitemap_posts($args, $post_type)
        {
            $protected_ids = $this->get_protected_post_ids($post_type);

            if (!empty($protected_ids)) {
                if (!isset($args['post__not_in'])) {
                    $args['post__not_in'] = [];
                }
                $args['post__not_in'] = array_unique(
                    array_merge($args['post__not_in'], $protected_ids)
                );
            }

            return $args;
        }

        /**
         * Get all post IDs of a given type that are marked as protected
         *
         * @param string $post_type Post type slug.
         * @return int[] Array of protected post IDs.
         * @since 2.3.0
         */
        private function get_protected_post_ids($post_type)
        {
            $all_posts = get_posts([
                'post_type'      => $post_type,
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'fields'         => 'ids',
            ]);

            if (empty($all_posts)) {
                return [];
            }

            // If all pages are private, every post is protected
            if ($this->settings->get_make_all_pages_private()) {
                return $all_posts;
            }

            $protected = [];
            foreach ($all_posts as $post_id) {
                if ($this->is_page_marked_protected($post_id)) {
                    $protected[] = $post_id;
                }
            }

            return $protected;
        }

    }
}
