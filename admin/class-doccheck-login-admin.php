<?php
/**
 * The admin-specific functionality of the plugin
 *
 * @since      1.0.0
 * @package    DocCheck_Login
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('DocCheck_Login_Admin')) {

    class DocCheck_Login_Admin
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
         * Initialize the class
         *
         * @param DocCheck_Login_Settings $settings Plugin settings.
         * @since 1.0.0
         */
        public function __construct($settings)
        {
            $this->settings = $settings;
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        }

        /**
         * Enqueue admin styles
         */
        public function enqueue_admin_styles($hook)
        {
            if ('settings_page_doccheck-access' !== $hook) {
                return;
            }

            wp_enqueue_style(
                    'doccheck-access-admin-style',
                    DOCCHECK_LOGIN_PLUGIN_URL . 'assets/css/doccheck-login-admin.css',
                    array(),
                    DOCCHECK_LOGIN_VERSION
            );

        }

        /**
         * Add admin menu
         *
         * @since 1.0.0
         */
        public function add_admin_menu()
        {
            add_options_page(
                    __('DocCheck Login', 'doccheck-access'),
                    __('DocCheck Login', 'doccheck-access'),
                    'manage_options',
                    'doccheck-access',
                    array($this, 'display_options_page')
            );
        }

        /**
         * Register plugin settings
         *
         * @since 1.0.0
         */
        public function register_settings()
        {
            register_setting(
                    'doccheck_login_settings',
                    'doccheck_login_settings',
                    array($this, 'validate_settings')
            );

            // Add metabox for pages
            add_action('add_meta_boxes_page', array($this, 'add_page_protection_metabox'));
            add_action('save_post_page', array($this, 'save_page_protection_meta'));

            // Add metabox for posts
            add_action('add_meta_boxes_post', array($this, 'add_page_protection_metabox'));
            add_action('save_post_post', array($this, 'save_page_protection_meta'));

            // Add columns to pages list
            add_filter('manage_page_posts_columns', array($this, 'add_protected_column'));
            add_action('manage_page_posts_custom_column', array($this, 'render_protected_column'), 10, 2);

            // Add columns to posts list
            add_filter('manage_post_posts_columns', array($this, 'add_protected_column'));
            add_action('manage_post_posts_custom_column', array($this, 'render_protected_column'), 10, 2);

            // ----- GENERAL SETTINGS TAB -----

            // General section
            add_settings_section(
                    'doccheck_login_general_section',
                    __('General Settings', 'doccheck-access'),
                    array($this, 'display_general_section'),
                    'doccheck_login_general_settings'
            );

            // OAuth Client settings
            add_settings_field(
                    'client_id',
                    __('Client ID', 'doccheck-access'),
                    array($this, 'render_text_field'),
                    'doccheck_login_general_settings',
                    'doccheck_login_general_section',
                    array(
                            'id' => 'client_id',
                            'description' => __('Your DocCheck OAuth Client ID', 'doccheck-access')
                    )
            );

            add_settings_field(
                    'client_secret',
                    __('Client Secret', 'doccheck-access'),
                    array($this, 'render_password_field'),
                    'doccheck_login_general_settings',
                    'doccheck_login_general_section',
                    array(
                            'id' => 'client_secret',
                            'description' => __('Your DocCheck OAuth Client Secret', 'doccheck-access')
                    )
            );

            // Add redirect URI display field (not editable, with copy functionality)
            add_settings_field(
                    'redirect_uri_display',
                    __('Redirect URI', 'doccheck-access'),
                    array($this, 'render_redirect_uri_display'),
                    'doccheck_login_general_settings',
                    'doccheck_login_general_section',
                    array(
                            'description' => __('Use this Redirect URI in your DocCheck Application settings.', 'doccheck-access')
                    )
            );

            add_settings_field(
                    'default_target_page',
                    __('Default Target Page', 'doccheck-access'),
                    array($this, 'render_page_select'),
                    'doccheck_login_general_settings',
                    'doccheck_login_general_section',
                    array(
                            'id' => 'default_target_page',
                            'description' => __('The page where users will be redirected after successful login (if no specific redirect is provided via shortcode).', 'doccheck-access')
                    )
            );

            add_settings_field(
                    'debug_mode',
                    __('Debug Mode', 'doccheck-access'),
                    array($this, 'render_checkbox_field'),
                    'doccheck_login_general_settings',
                    'doccheck_login_general_section',
                    array(
                            'id' => 'debug_mode',
                            'label' => __('Enable debug logging', 'doccheck-access'),
                            'description' => __('When enabled, debug information will be logged to the error log', 'doccheck-access')
                    )
            );

            add_settings_field(
                    'make_all_pages_private',
                    __('Global Settings for all Pages', 'doccheck-access'),
                    array($this, 'render_checkbox_field'),
                    'doccheck_login_general_settings',
                    'doccheck_login_general_section',
                    array(
                            'id' => 'make_all_pages_private',
                            'label' => __('Make all Pages Private', 'doccheck-access'),
                            'description' => __('When enabled, all pages will require DocCheck login by default.', 'doccheck-access')
                    )
            );

            add_settings_field(
                    'auto_assign_parent_config',
                    __('Inheritance', 'doccheck-access'),
                    array($this, 'render_checkbox_field'),
                    'doccheck_login_general_settings',
                    'doccheck_login_general_section',
                    array(
                            'id' => 'auto_assign_parent_config',
                            'label' => __('Auto-assign Parent Configurations to all Child Pages', 'doccheck-access'),
                            'description' => __('When enabled, child pages will inherit the protection status from their parent page.', 'doccheck-access')
                    )
            );

            add_settings_field(
                    'login_button_version',
                    __('Login Button Version', 'doccheck-access'),
                    array($this, 'render_text_field'),
                    'doccheck_login_general_settings',
                    'doccheck_login_general_section',
                    array(
                            'id' => 'login_button_version',
                            'description' => __('Specify the version of the DocCheck Login Button (e.g., 3.2.7). Use "@latest" for the most recent version.', 'doccheck-access') . '<br><br><strong>' . __('Note:', 'doccheck-access') . '</strong><br>⚠️ ' . __('Entering an incorrect version may prevent the script from loading and the login from working.', 'doccheck-access') . '<br>ℹ️ ' . __('By default, the latest version (@latest) is always loaded.', 'doccheck-access')                    )
            );

            // ----- USER MANAGEMENT TAB -----

            // User Management Settings
            add_settings_section(
                    'doccheck_login_user_management_section',
                    __('User Management', 'doccheck-access'),
                    array($this, 'display_user_management_section'),
                    'doccheck_login_user_settings'
            );

            // Moved from General section to User Management
            add_settings_field(
                    'authentication_mode',
                    __('Authentication Mode', 'doccheck-access'),
                    array($this, 'render_select_field'),
                    'doccheck_login_user_settings',
                    'doccheck_login_user_management_section',
                    array(
                            'id' => 'authentication_mode',
                            'options' => array(
                                    'anonymous_session' => __('Anonymous Session - No WordPress user created', 'doccheck-access'),
                                    'wordpress_user' => __('WordPress User - Create/link WordPress users', 'doccheck-access')
                            ),
                            'description' => __('Choose how to handle user authentication with DocCheck Login.', 'doccheck-access')
                    )
            );

            add_settings_field(
                    'default_role',
                    __('Default User Role', 'doccheck-access'),
                    array($this, 'render_role_select'),
                    'doccheck_login_user_settings',
                    'doccheck_login_user_management_section',
                    array(
                            'id' => 'default_role',
                            'description' => __('The default WordPress role for new users created via DocCheck Login', 'doccheck-access'),
                            'wrapper_class' => 'default-role-field'
                    )
            );

            add_settings_field(
                    'scope_property_matrix',
                    __('Scope & Property Selection', 'doccheck-access'),
                    array($this, 'render_scope_property_matrix'),
                    'doccheck_login_user_settings',
                    'doccheck_login_user_management_section',
                    array(
                            'description' => __('Select which scopes to request and which properties to store as user metadata.', 'doccheck-access'),
                            'wrapper_class' => 'scope-property-field'
                    )
            );


        }

        /**
         * Display the options page
         *
         * @since 1.0.0
         */
        public function display_options_page()
        {
            // Get active tab from URL, default to 'general'
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab switching is for UI state only, no data processed.
            $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'general';

            // Nonce verification for processing tab changes if they were submitted via form
            // However, tab switching here is primarily via GET for display.
            // If we were processing data, we would need check_admin_referer.
            ?>
            <div class="wrap doccheck-login-admin-wrap">
                <div class="doccheck-login-header">
                    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                </div>

                <!-- Tab Navigation -->
                <h2 class="nav-tab-wrapper">
                    <a href="#general-tab"
                       data-tab="general"
                       class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                        <?php esc_html_e('General settings', 'doccheck-access'); ?>
                    </a>
                    <a href="#user-tab"
                       data-tab="user"
                       class="nav-tab <?php echo $active_tab === 'user' ? 'nav-tab-active' : ''; ?>">
                        <?php esc_html_e('User Management', 'doccheck-access'); ?>
                    </a>
                    <a href="#document-tab"
                       data-tab="document"
                       class="nav-tab <?php echo $active_tab === 'document' ? 'nav-tab-active' : ''; ?>">
                        <?php esc_html_e('Documentation', 'doccheck-access'); ?>
                    </a>
                </h2>

                <div class="doccheck-login-content">
                    <form method="post" action="options.php">
                        <?php settings_fields('doccheck_login_settings'); ?>

                        <!-- General Tab Content -->
                        <div id="general-tab"
                             class="tab-content" <?php echo $active_tab !== 'general' ? 'style="display:none"' : ''; ?>>
                            <?php do_settings_sections('doccheck_login_general_settings'); ?>
                        </div>

                        <!-- User Management Tab Content -->
                        <div id="user-tab"
                             class="tab-content" <?php echo $active_tab !== 'user' ? 'style="display:none"' : ''; ?>>
                            <?php do_settings_sections('doccheck_login_user_settings'); ?>
                        </div>

                        <!-- Documentation Tab Content - No form content needed -->
                        <div id="document-tab"
                             class="tab-content" <?php echo $active_tab !== 'document' ? 'style="display:none"' : ''; ?>>
                            <div class="doccheck-access-docs">
                                <h3><?php esc_html_e('Authentication Modes Explained', 'doccheck-access'); ?></h3>
                                <div style="margin-bottom: 20px; border-left: 4px solid #cc0000; padding: 10px 15px; background-color: #fff5f5;">
                                    <p><strong><?php esc_html_e('Anonymous Session', 'doccheck-access'); ?>:</strong>
                                        <?php esc_html_e('Users are authenticated via DocCheck but no WordPress user is created. User data is available only during the session and not stored permanently. Data can be accessed via the doccheck_login_session_created hook.', 'doccheck-access'); ?>
                                    </p>
                                    <p><strong><?php esc_html_e('WordPress User', 'doccheck-access'); ?>:</strong>
                                        <?php esc_html_e('A WordPress user is created for each DocCheck user on first login. Selected user properties are stored in WordPress user metadata for future use.', 'doccheck-access'); ?>
                                    </p>
                                </div>

                                <h3><?php esc_html_e('Shortcode Usage', 'doccheck-access'); ?></h3>

                                <h4><code>[doccheck_login]</code></h4>
                                <p><?php esc_html_e('Displays the DocCheck login button. Can be used on any page or post.', 'doccheck-access'); ?></p>
                                <p><strong><?php esc_html_e('Attributes:', 'doccheck-access'); ?></strong></p>
                                <ul>
                                    <li><code>size</code>
                                        - <?php esc_html_e('The size of the login button (default: "medium"). Possible values: "small", "medium", "large"', 'doccheck-access'); ?>
                                    </li>
                                    <li><code>language</code>
                                        - <?php esc_html_e('The language code for the button (default: WordPress locale). Example: "en", "de"', 'doccheck-access'); ?>
                                    </li>
                                    <li><code>state</code>
                                        - <?php esc_html_e('Custom state parameter for OAuth flow (default: auto-generated)', 'doccheck-access'); ?>
                                    </li>
                                    <li><code>scope</code>
                                        - <?php esc_html_e('Custom scope parameter for OAuth permissions (default: from plugin settings)', 'doccheck-access'); ?>
                                    </li>
                                    <li><code>samepageredirect</code>
                                        - <?php esc_html_e('Whether to redirect back to the current page after login (default: "0"). Possible values: "0" (disabled), "1" (enabled)', 'doccheck-access'); ?>
                                    </li>
                                </ul>
                                <p><strong><?php esc_html_e('Examples:', 'doccheck-access'); ?></strong></p>
                                <code>[doccheck_login size="large" language="en"]</code><br>
                                <code>[doccheck_login samepageredirect="1"]</code>
                                - <?php esc_html_e('Redirects back to the current page after login', 'doccheck-access'); ?>

                                <h4><code>[dc-hide-content]</code></h4>
                                <p><?php esc_html_e('Hides content so that it is only visible to logged-in DocCheck users. Wrap the protected content between the opening and closing shortcode tags.', 'doccheck-access'); ?></p>
                                <p><?php esc_html_e('Example:', 'doccheck-access'); ?></p>
                                <pre><code>[dc-hide-content]
<?php esc_html_e('This content is only visible to logged-in DocCheck users.', 'doccheck-access'); ?>

[/dc-hide-content]</code></pre>

                                <h4><code>[dc_logout]</code></h4>
                                <p><?php esc_html_e('Displays a logout link for users who are currently logged in.', 'doccheck-access'); ?></p>
                                <p><strong><?php esc_html_e('Attributes:', 'doccheck-access'); ?></strong></p>
                                <ul>
                                    <li><code>redirect</code>
                                        - <?php esc_html_e('URL to redirect to after logout (default: home page URL)', 'doccheck-access'); ?>
                                    </li>
                                </ul>
                                <p><?php esc_html_e('Example:', 'doccheck-access'); ?></p>
                                <code>[dc_logout redirect="https://example.com/thank-you"]</code>

                                <h4><code>[dc_sitemap]</code></h4>
                                <p><?php esc_html_e('Renders an HTML sitemap that automatically hides protected pages from unauthenticated visitors. Also filters the WordPress XML sitemap to exclude protected pages from search engines.', 'doccheck-access'); ?></p>
                                <p><strong><?php esc_html_e('Attributes:', 'doccheck-access'); ?></strong></p>
                                <ul>
                                    <li><code>post_type</code>
                                        - <?php esc_html_e('Comma-separated list of post types to include (default: all public post types)', 'doccheck-access'); ?>
                                    </li>
                                    <li><code>show_protected</code>
                                        - <?php esc_html_e('Override visibility of protected pages. "yes" = always show, "no" = always hide (default: auto — shows if authenticated, hides if not)', 'doccheck-access'); ?>
                                    </li>
                                    <li><code>depth</code>
                                        - <?php esc_html_e('Maximum hierarchy depth for pages (default: 0 = unlimited)', 'doccheck-access'); ?>
                                    </li>
                                    <li><code>exclude</code>
                                        - <?php esc_html_e('Comma-separated post IDs to exclude from the sitemap', 'doccheck-access'); ?>
                                    </li>
                                </ul>
                                <p><strong><?php esc_html_e('Examples:', 'doccheck-access'); ?></strong></p>
                                <code>[dc_sitemap]</code>
                                - <?php esc_html_e('Shows all public post types, hides protected pages for unauthenticated users', 'doccheck-access'); ?><br>
                                <code>[dc_sitemap post_type="page" depth="2"]</code>
                                - <?php esc_html_e('Shows only pages, max 2 levels deep', 'doccheck-access'); ?><br>
                                <code>[dc_sitemap show_protected="yes"]</code>
                                - <?php esc_html_e('Always shows protected pages regardless of login status', 'doccheck-access'); ?><br>
                                <code>[dc_sitemap post_type="page,post" exclude="42,99"]</code>
                                - <?php esc_html_e('Shows pages and posts, excludes specific IDs', 'doccheck-access'); ?>

                                <div class="doccheck-developer-section">
                                    <h3><span class="doccheck-dev-badge"><?php esc_html_e('For Developer', 'doccheck-access'); ?></span> <?php esc_html_e('Customization Hooks', 'doccheck-access'); ?></h3>
                                    <p><?php esc_html_e('Developers can use these hooks to customize the plugin behavior:', 'doccheck-access'); ?></p>
                                    <ul>
                                        <li><code>doccheck_login_user_created</code>
                                            - <?php esc_html_e('Fires after a new user is created', 'doccheck-access'); ?>
                                        </li>
                                        <li><code>doccheck_login_user_logged_in</code>
                                            - <?php esc_html_e('Fires when an existing user logs in', 'doccheck-access'); ?>
                                        </li>
                                        <li><code>doccheck_login_session_created</code>
                                            - <?php esc_html_e('Fires when a user is authenticated via anonymous session mode', 'doccheck-access'); ?>
                                        </li>
                                        <li><code>doccheck_login_map_role</code>
                                            - <?php esc_html_e('Filter to customize user role based on DocCheck data', 'doccheck-access'); ?>
                                        </li>
                                        <li><code>doccheck_protected_template</code>
                                            - <?php esc_html_e('Filter to customize the path to the template used for protected pages', 'doccheck-access'); ?>
                                        </li>
                                        <li><code>doccheck_is_authenticated</code>
                                            - <?php esc_html_e('Filter to override the result of doccheck_is_authenticated()', 'doccheck-access'); ?>
                                        </li>
                                        <li><code>doccheck_user_data</code>
                                            - <?php esc_html_e('Filter to modify the array returned by doccheck_get_user_data()', 'doccheck-access'); ?>
                                        </li>
                                    </ul>

                                    <h3><span class="doccheck-dev-badge"><?php esc_html_e('For Developer', 'doccheck-access'); ?></span> <?php esc_html_e('Template Functions', 'doccheck-access'); ?></h3>
                                    <p><?php esc_html_e('Use these global PHP functions in your theme templates to detect DocCheck authentication:', 'doccheck-access'); ?></p>
                                    <ul>
                                        <li><code>doccheck_is_authenticated()</code>
                                            - <?php esc_html_e('Returns true if the current visitor is authenticated via DocCheck (works in both authentication modes)', 'doccheck-access'); ?>
                                        </li>
                                        <li><code>doccheck_get_user_data()</code>
                                            - <?php esc_html_e('Returns an associative array of DocCheck user fields, or an empty array for unauthenticated visitors', 'doccheck-access'); ?>
                                        </li>
                                    </ul>
                                    <p><?php esc_html_e('Example usage in a theme template:', 'doccheck-access'); ?></p>
                                    <pre><code>&lt;?php if ( function_exists( 'doccheck_is_authenticated' ) &amp;&amp; doccheck_is_authenticated() ) : ?&gt;
    &lt;div class="hcp-only"&gt;Visible only to DocCheck users&lt;/div&gt;
&lt;?php else : ?&gt;
    &lt;p&gt;Please log in with DocCheck.&lt;/p&gt;
    &lt;?php echo do_shortcode( '[doccheck_login]' ); ?&gt;
&lt;?php endif; ?&gt;</code></pre>

                                    <h3><span class="doccheck-dev-badge"><?php esc_html_e('For Developer', 'doccheck-access'); ?></span> <?php esc_html_e('Customizing Protected Content Template', 'doccheck-access'); ?></h3>
                                    <p><?php esc_html_e('The plugin provides a default template for pages that are DocCheck protected. You can customize this in two ways:', 'doccheck-access'); ?></p>
                                    <ol>
                                        <li>
                                            <strong><?php esc_html_e('Theme Template File', 'doccheck-access'); ?>
                                                :</strong><br>
                                            <?php esc_html_e('Create a file named doccheck-protected.php in your active theme\'s root directory. The plugin will automatically use this file instead of the default one.', 'doccheck-access'); ?>
                                        </li>
                                        <li>
                                            <strong><?php esc_html_e('WordPress Filter', 'doccheck-access'); ?>
                                                :</strong><br>
                                            <?php esc_html_e('Use the doccheck_protected_template filter in your theme\'s functions.php to programmatically change the path to the template file.', 'doccheck-access'); ?>
                                        </li>
                                    </ol>

                                    <h3><span class="doccheck-dev-badge"><?php esc_html_e('For Developer', 'doccheck-access'); ?></span> <?php esc_html_e('XML Sitemap Filter Hook', 'doccheck-access'); ?></h3>
                                    <p><?php esc_html_e('The plugin uses the wp_sitemaps_posts_query_args filter (available since WordPress 5.5) to exclude protected posts from the XML sitemap. You can further customize the behavior by adding your own filter at a later priority:', 'doccheck-access'); ?></p>
                                    <pre><code>add_filter( 'wp_sitemaps_posts_query_args', function( $args, $post_type ) {
    // Example: also exclude specific post IDs from the sitemap
    $args['post__not_in'] = array_merge(
        $args['post__not_in'] ?? [],
        [ 42, 99 ]
    );
    return $args;
}, 20, 2 );</code></pre>
                                    <p><?php esc_html_e('The plugin registers its filter at priority 10. Use a higher priority (e.g. 20) to run after the plugin and extend the exclusion list.', 'doccheck-access'); ?></p>
                                </div>

                                <h3><?php esc_html_e('Field Visibility and Mapping', 'doccheck-access'); ?></h3>
                                <p><?php esc_html_e('The following fields are mapped from DocCheck to WordPress user meta:', 'doccheck-access'); ?></p>
                                <?php $this->render_fields_info_content(); ?>
                            </div>
                        </div>

                        <?php submit_button(); ?>
                    </form>
                </div>


                <script type="text/javascript">
                    jQuery(document).ready(function ($) {
                        // Tab switching functionality
                        $('.nav-tab-wrapper a').on('click', function (e) {
                            e.preventDefault();

                            // Get the tab to show
                            var tabId = $(this).attr('href');
                            var tabName = $(this).data('tab');

                            // Hide all tabs
                            $('.tab-content').hide();
                            $('.nav-tab').removeClass('nav-tab-active');

                            // Show the selected tab and set active class
                            $(tabId).show();
                            $(this).addClass('nav-tab-active');

                            // Update URL without reloading the page (for bookmarking)
                            if (history.pushState) {
                                var url = new URL(window.location.href);
                                url.searchParams.set('tab', tabName);
                                window.history.pushState({path: url.href}, '', url.href);
                            }
                        });

                        // Function to toggle the visibility of conditional fields based on Authentication Mode
                        function toggleConditionalFields() {
                            var authMode = $('#authentication_mode').val();

                            if (authMode === 'wordpress_user') {
                                // Show fields relevant to WordPress user mode
                                $('.default-role-field, .scope-property-field').closest('tr').show();
                            } else {
                                // Hide fields not applicable in anonymous session mode
                                $('.default-role-field, .scope-property-field').closest('tr').hide();

                                // Uncheck all optional scope and property checkboxes in the UI
                                // This provides immediate feedback, though the server-side validation also handles it.
                                $('.doccheck-matrix-table input[type="checkbox"]').each(function() {
                                    var $checkbox = $(this);
                                    // Don't uncheck the unique_id (it's often disabled/required)
                                    if ($checkbox.attr('name').indexOf('[unique_id]') === -1) {
                                        $checkbox.prop('checked', false);
                                    }
                                });
                            }
                        }

                        // Run on page load
                        toggleConditionalFields();

                        // Run when authentication mode changes
                        $('#authentication_mode').on('change', toggleConditionalFields);
                    });
                </script>

            </div>
            <?php
        }

        /**
         * Display the general section info
         *
         * @since 1.0.0
         */
        public function display_general_section()
        {
            ?>
            <div class="notice notice-warning inline" style="margin: 20px 0;">
                <p>
                    <strong><?php esc_html_e('⚠️ LICENSE REQUIREMENT', 'doccheck-access'); ?></strong><br>
                    <?php esc_html_e('This plugin only works with an Economy or Business license.', 'doccheck-access'); ?><br>
                    <?php esc_html_e('The OAuth2 state parameter is required and is not included in the Basic license.', 'doccheck-access'); ?>
                </p>
            </div>
            <?php
            echo '<p>' . esc_html__('Configure your DocCheck OAuth credentials and general settings.', 'doccheck-access') . '</p>';
        }

        // Endpoints section removed - endpoints are now handled internally

        /**
         * Display the advanced section info
         *
         * @since 1.0.0
         */
        public function display_advanced_section()
        {
            echo '<p>' . esc_html__('Advanced settings for troubleshooting and customization.', 'doccheck-access') . '</p>';
        }

        /**
         * Display the user management section info
         *
         * @since 1.3.0
         */
        public function display_user_management_section()
        {
            echo '<p>' . esc_html__('Settings for user creation and management.', 'doccheck-access') . '</p>';
        }


        /**
         * Render text field
         *
         * @param array $args Field arguments.
         * @since 1.0.0
         */
        public function render_text_field($args)
        {
            $id = $args['id'];
            $description = isset($args['description']) ? $args['description'] : '';
            $value = isset($this->settings->$id) ? $this->settings->$id : '';

            ?>
            <input type="text" id="<?php echo esc_attr($id); ?>"
                   name="doccheck_login_settings[<?php echo esc_attr($id); ?>]"
                   value="<?php echo esc_attr($value); ?>"
                   class="regular-text"/>
            <?php if ($description) : ?>
            <p class="description"><?php echo wp_kses_post($description); ?></p>
        <?php endif; ?>
            <?php
        }

        /**
         * Render password field
         *
         * @param array $args Field arguments.
         * @since 1.0.0
         */
        public function render_password_field($args)
        {
            $id = $args['id'];
            $description = isset($args['description']) ? $args['description'] : '';
            $value = isset($this->settings->$id) ? $this->settings->$id : '';

            ?>
            <input type="password" id="<?php echo esc_attr($id); ?>"
                   name="doccheck_login_settings[<?php echo esc_attr($id); ?>]"
                   value="<?php echo esc_attr($value); ?>"
                   class="regular-text"/>
            <?php if ($description) : ?>
            <p class="description"><?php echo wp_kses_post($description); ?></p>
        <?php endif; ?>
            <?php
        }

        /**
         * Render checkbox field
         *
         * @param array $args Field arguments.
         * @since 1.0.0
         */
        public function render_checkbox_field($args)
        {
            $id = $args['id'];
            $label = isset($args['label']) ? $args['label'] : '';
            $description = isset($args['description']) ? $args['description'] : '';
            $value = isset($this->settings->$id) ? $this->settings->$id : '';

            // Check if it's 'on' or true (for legacy/new checkbox fields)
            $checked = ($value === 'on' || $value === true || $value === 1);

            ?>
            <label for="<?php echo esc_attr($id); ?>">
                <input type="checkbox" id="<?php echo esc_attr($id); ?>"
                       name="doccheck_login_settings[<?php echo esc_attr($id); ?>]"
                        <?php checked($checked); ?> />
                <?php echo esc_html($label); ?>
            </label>
            <?php if ($description) : ?>
            <p class="description"><?php echo wp_kses_post($description); ?></p>
        <?php endif; ?>
            <?php
        }

        /**
         * Render role select field
         *
         * @param array $args Field arguments.
         * @since 1.0.0
         */
        public function render_role_select($args)
        {
            $id = $args['id'];
            $description = isset($args['description']) ? $args['description'] : '';
            $value = isset($this->settings->$id) ? $this->settings->$id : 'subscriber';
            $wrapper_class = isset($args['wrapper_class']) ? $args['wrapper_class'] : '';

            // Get available roles
            $roles = wp_roles()->get_names();

            // If wrapper class is provided, wrap the field
            if (!empty($wrapper_class)) {
                echo '<div class="' . esc_attr($wrapper_class) . '">';
            }
            ?>
            <select id="<?php echo esc_attr($id); ?>"
                    name="doccheck_login_settings[<?php echo esc_attr($id); ?>]">
                <?php foreach ($roles as $role_id => $role_name) : ?>
                    <option value="<?php echo esc_attr($role_id); ?>"
                            <?php selected($value, $role_id); ?>>
                        <?php echo esc_html($role_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($description) : ?>
            <p class="description"><?php echo wp_kses_post($description); ?></p>
        <?php endif;

            // Close the wrapper div if needed
            if (!empty($wrapper_class)) {
                echo '</div>';
            }
            ?><?php
        }

        /**
         * Render select field with options
         *
         * @param array $args Field arguments.
         * @since 1.3.0
         */
        public function render_select_field($args)
        {
            $id = $args['id'];
            $options = isset($args['options']) ? $args['options'] : array();
            $description = isset($args['description']) ? $args['description'] : '';
            $value = isset($this->settings->$id) ? $this->settings->$id : '';
            $wrapper_class = isset($args['wrapper_class']) ? $args['wrapper_class'] : '';

            // If wrapper class is provided, wrap the field
            if (!empty($wrapper_class)) {
                echo '<div class="' . esc_attr($wrapper_class) . '">';
            }
            ?>
            <select id="<?php echo esc_attr($id); ?>"
                    name="doccheck_login_settings[<?php echo esc_attr($id); ?>]">
                <?php foreach ($options as $option_value => $option_label) : ?>
                    <option value="<?php echo esc_attr($option_value); ?>"
                            <?php selected($value, $option_value); ?>>
                        <?php echo esc_html($option_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($description) : ?>
            <p class="description"><?php echo wp_kses_post($description); ?></p>
        <?php endif;

            // Close the wrapper div if needed
            if (!empty($wrapper_class)) {
                echo '</div>';
            }
            ?>
            <?php
        }

        /**
         * Render page select dropdown
         *
         * @param array $args Field arguments.
         * @since 1.0.0
         */
        public function render_page_select($args)
        {
            $id = $args['id'];
            $description = isset($args['description']) ? $args['description'] : '';
            $value = isset($this->settings->$id) ? $this->settings->$id : '';

            $dropdown_args = array(
                    'name' => 'doccheck_login_settings[' . esc_attr($id) . ']',
                    'id' => esc_attr($id),
                    'selected' => $value,
                    'show_option_none' => esc_html__('Select a page', 'doccheck-access'),
            );

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core function outputs HTML.
            wp_dropdown_pages($dropdown_args);

            if ($description) {
                echo '<p class="description">' . wp_kses_post($description) . '</p>';
            }
        }

        /**
         * Render scope and property selection matrix
         *
         * @param array $args Field arguments.
         * @since 2.0.0
         */
        public function render_scope_property_matrix($args)
        {
            $description = isset($args['description']) ? $args['description'] : '';
            $wrapper_class = isset($args['wrapper_class']) ? $args['wrapper_class'] : '';
            if (!empty($wrapper_class)) {
                echo '<div class="' . esc_attr($wrapper_class) . '">';
            }

            // Get scope and property selections
            $selected_scopes = isset($this->settings->selected_scopes) ?
                    $this->settings->selected_scopes : [];

            $selected_properties = isset($this->settings->selected_properties) ?
                    $this->settings->selected_properties : [];

            // Define scope mappings with license information and properties
            $scope_mappings = [
                    'unique_id' => [
                            'label' => __('Unique ID', 'doccheck-access'),
                            'description' => __('DocCheck unique user identifier', 'doccheck-access'),
                            'license' => 'Economy',
                            'required' => true,
                            'properties' => ['unique_id']
                    ],
                    'country' => [
                            'label' => __('Country', 'doccheck-access'),
                            'description' => __('User\'s country information', 'doccheck-access'),
                            'license' => 'Economy',
                            'properties' => ['country_iso_code', 'country_id']
                    ],
                    'language' => [
                            'label' => __('Language', 'doccheck-access'),
                            'description' => __('User\'s preferred language', 'doccheck-access'),
                            'license' => 'Economy',
                            'properties' => ['user_language']
                    ],
                    'profession' => [
                            'label' => __('Profession', 'doccheck-access'),
                            'description' => __('User\'s professional information', 'doccheck-access'),
                            'license' => 'Economy',
                            'properties' => ['profession_name', 'profession_id']
                    ],
                    'address' => [
                            'label' => __('Address', 'doccheck-access'),
                            'description' => __('User\'s address details', 'doccheck-access'),
                            'license' => 'Business',
                            'properties' => ['area_code', 'street', 'city', 'country_iso_code', 'state']
                    ],
                    'occupation_detail' => [
                            'label' => __('Occupation Detail', 'doccheck-access'),
                            'description' => __('Detailed occupational information', 'doccheck-access'),
                            'license' => 'Business',
                            'properties' => ['discipline_name', 'discipline_id', 'activity_name', 'activity_id']
                    ],
                    'email' => [
                            'label' => __('Email', 'doccheck-access'),
                            'description' => __('User\'s email address', 'doccheck-access'),
                            'license' => 'Business',
                            'properties' => ['email']
                    ],
                    'name' => [
                            'label' => __('Name', 'doccheck-access'),
                            'description' => __('User\'s full name', 'doccheck-access'),
                            'license' => 'Business',
                            'properties' => ['first_name', 'last_name']
                    ],
            ];

            // Define property data types
            $property_types = [
                    'unique_id' => 'string',
                    'country_iso_code' => 'string',
                    'country_id' => 'integer',
                    'user_language' => 'string',
                    'profession_name' => 'string',
                    'profession_id' => 'integer',
                    'area_code' => 'string',
                    'street' => 'string',
                    'city' => 'string',
                    'state' => 'string',
                    'discipline_name' => 'string',
                    'discipline_id' => 'integer',
                    'activity_name' => 'string',
                    'activity_id' => 'integer',
                    'email' => 'string',
                    'first_name' => 'string',
                    'last_name' => 'string',
            ];

            if ($description) {
                echo '<p class="description">' . wp_kses_post($description) . '</p>';
            }

            // Add data privacy notice
            echo '<div class="notice notice-warning inline"><p>';
            echo esc_html__('Note: If you select and store personal data, you must ensure your privacy policy complies with relevant regulations. The site operator is responsible for the legal basis of data storage.', 'doccheck-access');
            echo '</p></div>';

            // Add explanation about the matrix
            echo '<div class="doccheck-matrix-explanation">';
            echo '<p>' . esc_html__('This matrix allows you to select which data to request from DocCheck and which properties to store in WordPress:', 'doccheck-access') . '</p>';
            echo '<ul style="list-style: disc; margin-left: 20px;">';
            echo '<li>' . esc_html__('The "Request from DocCheck" column determines which scopes are requested during OAuth authentication.', 'doccheck-access') . '</li>';
            echo '<li>' . esc_html__('The "Properties to Store" column only applies when using the "WordPress User" authentication mode.', 'doccheck-access') . '</li>';
            echo '<li>' . esc_html__('Even if properties are not stored, they can still be accessed during the session via hooks.', 'doccheck-access') . '</li>';
            echo '</ul>';
            echo '</div>';

            // Matrix Table
            echo '<div class="doccheck-scopes-matrix-wrapper">';
            echo '<table class="widefat doccheck-scopes-matrix doccheck-matrix-table">';

            // Table header
            echo '<thead>';
            echo '<tr>';
            echo '<th class="scope-checkbox">' . esc_html__('Request from DocCheck', 'doccheck-access') . '</th>';
            echo '<th class="scope-name">' . esc_html__('Scope', 'doccheck-access') . '</th>';
            echo '<th class="scope-description">' . esc_html__('Description', 'doccheck-access') . '</th>';
            echo '<th class="scope-license">' . esc_html__('License', 'doccheck-access') . '</th>';
            echo '<th class="scope-properties">' . esc_html__('Properties to Store in WordPress', 'doccheck-access') . '</th>';
            echo '</tr>';
            echo '</thead>';

            // Table body
            echo '<tbody>';
            foreach ($scope_mappings as $scope_key => $scope_data) {
                $scope_checked = isset($selected_scopes[$scope_key]) ? $selected_scopes[$scope_key] : false;
                $is_required = isset($scope_data['required']) && $scope_data['required'];

                echo '<tr class="scope-row">';

                // Checkbox column
                echo '<td class="scope-checkbox">';
                echo '<input 
                    type="checkbox" 
                    name="doccheck_login_settings[selected_scopes][' . esc_attr($scope_key) . ']" 
                    id="scope_' . esc_attr($scope_key) . '" 
                    value="1" ' .
                        checked($scope_checked || $is_required, true, false) . ' ' .
                        ($is_required ? 'disabled' : '') . '>';
                if ($is_required) {
                    echo '<input type="hidden" name="doccheck_login_settings[selected_scopes][' . esc_attr($scope_key) . ']" value="1">';
                }
                echo '</td>';

                // Scope name column
                echo '<td class="scope-name">';
                echo '<label for="scope_' . esc_attr($scope_key) . '">' .
                        esc_html($scope_data['label']) .
                        ($is_required ? ' <span class="required">*</span>' : '') .
                        '</label>';
                echo '</td>';

                // Description column
                echo '<td class="scope-description">';
                echo esc_html($scope_data['description']);
                echo '</td>';

                // License column
                echo '<td class="scope-license">';
                echo esc_html($scope_data['license']);
                echo '</td>';

                // Properties column
                echo '<td class="scope-properties">';
                echo '<div class="property-list' . ($scope_checked || $is_required ? '' : ' disabled') . '">';

                foreach ($scope_data['properties'] as $property) {
                    $property_checked = isset($selected_properties[$property]) ? $selected_properties[$property] : false;
                    $property_disabled = !$scope_checked && !$is_required;

                    echo '<div class="property-item">';
                    echo '<input 
                        type="checkbox" 
                        name="doccheck_login_settings[selected_properties][' . esc_attr($property) . ']" 
                        id="property_' . esc_attr($property) . '" 
                        value="1" ' .
                            checked($property_checked, true, false) . ' ' .
                            ($property_disabled ? 'disabled' : '') . ' ' .
                            ($property === 'unique_id' && $is_required ? 'disabled' : '') . '>';

                    if ($property === 'unique_id' && $is_required) {
                        echo '<input type="hidden" name="doccheck_login_settings[selected_properties][' . esc_attr($property) . ']" value="1">';
                    }

                    echo '<label for="property_' . esc_attr($property) . '">';
                    echo esc_html($property) . ' <small>(' . esc_html($property_types[$property]) . ')</small>';
                    echo '</label>';
                    echo '</div>';
                }

                echo '</div>';
                echo '</td>';

                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '</div>';

            // Add JavaScript to enable/disable properties based on scope selection
            echo '<script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Toggle properties when scope is toggled
                    $(".scope-checkbox input[type=checkbox]").on("change", function() {
                        var $row = $(this).closest("tr");
                        var isChecked = $(this).is(":checked");
                    
                        if (isChecked) {
                            $row.find(".property-list").removeClass("disabled");
                            $row.find(".property-list input[type=checkbox]").prop("disabled", false);
                        } else {
                            $row.find(".property-list").addClass("disabled");
                            $row.find(".property-list input[type=checkbox]").prop("disabled", true);
                        }
                    });
                
                    // Initialize on load
                    $(".scope-row input[type=checkbox]").trigger("change");
                });
            </script>';

            // Add some CSS to make the matrix look better
            echo '<style>
                .doccheck-scopes-matrix {
                    margin-top: 10px;
                }
                .doccheck-scopes-matrix .scope-checkbox {
                    width: 60px;
                    text-align: center;
                }
                .doccheck-scopes-matrix .scope-name {
                    width: 120px;
                }
                .doccheck-scopes-matrix .scope-license {
                    width: 100px;
                }
                .doccheck-scopes-matrix .required {
                    color: #d63638;
                }
                .property-list.disabled {
                    opacity: 0.5;
                }
                .property-item {
                    margin-bottom: 5px;
                }
                .property-item label small {
                    color: #666;
                    font-style: italic;
                }
            </style>';

            if (!empty($wrapper_class)) {
                echo '</div>';
            }
        }

        /**
         * Render the fields info content without form context
         *
         * @since 1.3.1
         */
        public function render_fields_info_content()
        {
            // Define the fields mapping
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- keys in static array.
            $field_mappings = array(
                    'unique_id' => array(
                            'label' => __('Unique ID', 'doccheck-access'),
                            'meta_key' => 'doccheck_unique_id',
                            'description' => __('Required for user identification', 'doccheck-access'),
                            'required' => true,
                    ),
                    'profession' => array(
                            'label' => __('Profession', 'doccheck-access'),
                            'meta_key' => 'doccheck_profession',
                            'description' => __('User\'s medical profession', 'doccheck-access'),
                    ),
                    'specialty' => array(
                            'label' => __('Specialty', 'doccheck-access'),
                            'meta_key' => 'doccheck_specialty',
                            'description' => __('User\'s medical specialty', 'doccheck-access'),
                    ),
                    'email' => array(
                            'label' => __('Email', 'doccheck-access'),
                            'meta_key' => 'doccheck_email',
                            'description' => __('User\'s email address', 'doccheck-access'),
                    ),
                    'name' => array(
                            'label' => __('Name', 'doccheck-access'),
                            'meta_key' => 'doccheck_name',
                            'description' => __('Full name (also splits into first_name, last_name)', 'doccheck-access'),
                    ),
                    'occupation_detail' => array(
                            'label' => __('Occupation Detail', 'doccheck-access'),
                            'meta_key' => 'doccheck_occupation_detail',
                            'description' => __('Detailed information about occupation', 'doccheck-access'),
                    ),
                    'address' => array(
                            'label' => __('Address', 'doccheck-access'),
                            'meta_key' => 'doccheck_address',
                            'description' => __('User\'s address (sub-fields stored separately)', 'doccheck-access'),
                    ),
                    'country' => array(
                            'label' => __('Country', 'doccheck-access'),
                            'meta_key' => 'doccheck_country',
                            'description' => __('User\'s country', 'doccheck-access'),
                    ),
                    'language' => array(
                            'label' => __('Language', 'doccheck-access'),
                            'meta_key' => 'doccheck_language',
                            'description' => __('User\'s language', 'doccheck-access'),
                    ),
                    'gender' => array(
                            'label' => __('Gender', 'doccheck-access'),
                            'meta_key' => 'doccheck_gender',
                            'description' => __('User\'s gender', 'doccheck-access'),
                    ),
                    'title' => array(
                            'label' => __('Title', 'doccheck-access'),
                            'meta_key' => 'doccheck_title',
                            'description' => __('User\'s title (Dr., Prof., etc.)', 'doccheck-access'),
                    ),
            );
            // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key

            echo '<div class="doccheck-fields-table-wrapper">';
            echo '<table class="widefat fixed doccheck-fields-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . esc_html__('DocCheck Field', 'doccheck-access') . '</th>';
            echo '<th>' . esc_html__('WordPress Meta Key', 'doccheck-access') . '</th>';
            echo '<th>' . esc_html__('Description', 'doccheck-access') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($field_mappings as $field_key => $field_data) {
                echo '<tr>';
                echo '<td>' . esc_html($field_data['label']) .
                        (isset($field_data['required']) && $field_data['required'] ? ' <span style="color: red;">*</span>' : '') . '</td>';
                echo '<td><code>' . esc_html($field_data['meta_key']) . '</code></td>';
                echo '<td>' . esc_html($field_data['description']) . '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';

            echo '<p class="description">' .
                    esc_html__('Note: All fields are also stored as serialized data in meta key `doccheck_userdata`.', 'doccheck-access') .
                    '</p>';

            // Add note about required scopes
            echo '<div class="doccheck-scopes-info" style="margin-top: 15px;">';
            echo '<p><strong>' . esc_html__('Required Scopes to create WP-User', 'doccheck-access') . ':</strong></p>';
            echo '<ul style="list-style: disc; margin-left: 20px;">';
            echo '<li><code>unique_id</code> - ' . esc_html__('Required for user identification', 'doccheck-access') . '</li>';
            echo '<li><code>email</code> - ' . esc_html__('For WordPress user email (recommended)', 'doccheck-access') . '</li>';
            echo '</ul>';
            echo '</div>';
        }

        /**
         * Render redirect URI display with copy functionality
         *
         * @param array $args Field arguments.
         * @since 1.0.0
         */
        public function render_redirect_uri_display($args)
        {
            $description = isset($args['description']) ? $args['description'] : '';
            $redirect_uri = home_url($this->settings->redirect_route);

            ?>
            <div class="doccheck-redirect-uri-wrapper" style="margin-bottom: 10px;">
                <input type="text" id="doccheck_redirect_uri"
                       value="<?php echo esc_attr($redirect_uri); ?>"
                       class="regular-text" readonly
                       style="background-color: #f0f0f1;"/>
                <button type="button" class="button" id="copy-redirect-uri">
                    <?php esc_html_e('Copy', 'doccheck-access'); ?>
                </button>
            </div>

            <?php if ($description) : ?>
            <p class="description"><?php echo wp_kses_post($description); ?></p>
        <?php endif; ?>

            <script>
                jQuery(document).ready(function ($) {
                    $('#copy-redirect-uri').on('click', function () {
                        var copyText = document.getElementById("doccheck_redirect_uri");
                        copyText.select();
                        copyText.setSelectionRange(0, 99999); // For mobile devices

                        try {
                            navigator.clipboard.writeText(copyText.value).then(function () {
                                var $btn = $('#copy-redirect-uri');
                                var originalText = $btn.text();
                                $btn.text('<?php esc_html_e('Copied!', 'doccheck-access'); ?>');
                                setTimeout(function () {
                                    $btn.text(originalText);
                                }, 2000);
                            });
                        } catch (err) {
                            // Fallback for older browsers
                            document.execCommand("copy");
                            var $btn = $('#copy-redirect-uri');
                            var originalText = $btn.text();
                            $btn.text('<?php esc_html_e('Copied!', 'doccheck-access'); ?>');
                            setTimeout(function () {
                                $btn.text(originalText);
                            }, 2000);
                        }
                    });
                });
            </script>
            <?php
        }

        /**
         * Validate settings
         *
         * @param array $input Input values.
         * @return array Sanitized values.
         * @since 1.0.0
         */
        public function validate_settings($input)
        {
            $output = array();
            $errors = false;
            $required_fields = array(
                    'client_id' => __('Client ID is required', 'doccheck-access'),
                    'client_secret' => __('Client Secret is required', 'doccheck-access')
            );

            // Check required fields
            foreach ($required_fields as $field => $error_message) {
                if (empty($input[$field])) {
                    add_settings_error(
                            'doccheck_login_settings',
                            'required_' . $field,
                            $error_message,
                            'error'
                    );
                    $errors = true;
                }
            }

            // Text fields
            $text_fields = array(
                    'client_id',
                    'client_secret',
                    'auth_server_url',
                    'redirect_route',
                    'redirect_uri', // Keep for backwards compatibility
                    'default_scopes',
                    'login_button_version'
            );

            foreach ($text_fields as $field) {
                $output[$field] = isset($input[$field]) ? sanitize_text_field($input[$field]) : '';
            }

            // Role select
            $output['default_role'] = isset($input['default_role']) ? sanitize_text_field($input['default_role']) : 'doccheck_user';

            // Make sure the role exists, otherwise default to doccheck_user or subscriber
            if (!get_role($output['default_role'])) {
                $output['default_role'] = get_role('doccheck_user') ? 'doccheck_user' : 'subscriber';
            }

            // Default target page
            $output['default_target_page'] = isset($input['default_target_page']) ? absint($input['default_target_page']) : '';

            // Checkbox
            $output['debug_mode'] = isset($input['debug_mode']) ? 'on' : 'off';
            $output['make_all_pages_private'] = isset($input['make_all_pages_private']) ? 'on' : 'off';
            $output['auto_assign_parent_config'] = isset($input['auto_assign_parent_config']) ? 'on' : 'off';

            // Authentication Mode
            $valid_auth_modes = array('anonymous_session', 'wordpress_user');
            $output['authentication_mode'] = isset($input['authentication_mode']) && in_array($input['authentication_mode'], $valid_auth_modes)
                    ? $input['authentication_mode']
                    : 'wordpress_user';

            // Add doccheck_user role if wordpress_user mode is selected
            if ($output['authentication_mode'] === 'wordpress_user') {
                if (!get_role('doccheck_user')) {
                    $subscriber = get_role('subscriber');
                    $capabilities = $subscriber ? $subscriber->capabilities : array('read' => true);
                    add_role('doccheck_user', __('DocCheck User', 'doccheck-access'), $capabilities);
                }
            }


            // Validate scope and property selections
            $output['selected_scopes'] = [];
            if (isset($input['selected_scopes']) && is_array($input['selected_scopes'])) {
                foreach ($input['selected_scopes'] as $scope => $value) {
                    $output['selected_scopes'][$scope] = (bool)$value;
                }
            }

            // If authentication mode is anonymous session, we don't need additional scopes
            // and we should only keep the mandatory unique_id scope.
            if ($output['authentication_mode'] === 'anonymous_session') {
                $output['selected_scopes'] = [];
            }

            // Ensure unique_id is always selected
            $output['selected_scopes']['unique_id'] = true;

            $output['selected_properties'] = [];
            if (isset($input['selected_properties']) && is_array($input['selected_properties'])) {
                foreach ($input['selected_properties'] as $property => $value) {
                    $output['selected_properties'][$property] = (bool)$value;
                }
            }

            // If authentication mode is anonymous session, we don't need properties
            // and we should only keep the mandatory unique_id property.
            if ($output['authentication_mode'] === 'anonymous_session') {
                $output['selected_properties'] = [];
            }

            // Ensure unique_id property is always selected
            $output['selected_properties']['unique_id'] = true;

            // Set default auth server URL if empty
            if (empty($output['auth_server_url'])) {
                $output['auth_server_url'] = 'https://auth.doccheck.com';
            }

            // Set default redirect route if empty
            if (empty($output['redirect_route'])) {
                $output['redirect_route'] = 'doccheck/callback';
            }

            // Set default login button version if empty
            if (empty($output['login_button_version'])) {
                $output['login_button_version'] = '@latest';
            }

            // Set default redirect URI if empty (for backwards compatibility)
            if (empty($output['redirect_uri'])) {
                $output['redirect_uri'] = home_url($output['redirect_route']);
            }

            // If we encountered errors, return original settings to prevent loss of data
            if ($errors) {
                // Get existing options
                $existing_options = get_option('doccheck_login_settings');
                return $existing_options;
            }

            // Endpoints are now handled internally in the OAuth class

            return $output;
        }

        /**
         * Add metabox for page protection
         *
         * @since 2.1.0
         */
        public function add_page_protection_metabox()
        {
            $post_types = get_post_types(array('public' => true), 'names');

            foreach ($post_types as $post_type) {
                add_meta_box(
                        'doccheck_page_protection',
                        __('DocCheck Page Protection', 'doccheck-access'),
                        array($this, 'render_page_protection_metabox'),
                        $post_type,
                        'side',
                        'default'
                );
            }
        }

        /**
         * Add a new column to the backend pages list
         *
         * @param array $columns Existing columns.
         * @return array Modified columns.
         * @since 2.1.0
         */
        public function add_protected_column($columns)
        {
            $columns['Private'] = __('Page restricted from non-logged in users', 'doccheck-access');
            return $columns;
        }

        /**
         * Render the content for the protected column
         *
         * @param string $column_name Column name.
         * @param int $post_id Post ID.
         * @since 2.1.0
         */
        public function render_protected_column($column_name, $post_id)
        {
            if ($column_name === 'Private') {
                $is_protected = get_post_meta($post_id, '_doccheck_protected', true);
                if ($is_protected) {
                    echo '<span class="dashicons dashicons-lock" title="' . esc_attr__('Restricted', 'doccheck-access') . '"></span>';
                } else {
                    echo '<span class="dashicons dashicons-unlock" title="' . esc_attr__('Public', 'doccheck-access') . '"></span>';
                }
            }
        }

        /**
         * Render the page protection metabox
         *
         * @param WP_Post $post Current post object.
         * @since 2.1.0
         */
        public function render_page_protection_metabox($post)
        {
            // Use nonces for verification
            wp_nonce_field('doccheck_save_page_protection', 'doccheck_page_protection_nonce');

            $is_protected = get_post_meta($post->ID, '_doccheck_protected', true);
            $auth_mode = $this->settings->get_authentication_mode();

            echo '<p>';
            echo 'Limit access to Logged in users.';
            echo '</p>';
            echo '<p>';
            echo '<label for="doccheck_protected">';
            echo '<input type="checkbox" id="doccheck_protected" name="doccheck_protected" value="1" ' . checked(1, $is_protected, false) . ' />';
            echo ' ' . esc_html__(' Require Doccheck Login', 'doccheck-access');
            echo '</label>';
            echo '</p>';

            // Show role restriction options only in wordpress_user mode
            if ($auth_mode === 'wordpress_user') {
                $allowed_roles = get_post_meta($post->ID, '_doccheck_allowed_roles', true);
                if (!is_array($allowed_roles)) {
                    $allowed_roles = ['doccheck_user'];
                }

                $all_roles = wp_roles()->roles;

                echo '<div id="doccheck-role-restriction" style="margin-top: 10px;' . ($is_protected ? '' : ' display:none;') . '">';
                echo '<p><strong>' . esc_html__('Restrict to specific roles', 'doccheck-access') . '</strong></p>';
                echo '<p class="description">' . esc_html__('If none are selected, all authenticated users can access this content.', 'doccheck-access') . '</p>';
                echo '<fieldset style="margin-top: 5px;">';

                foreach ($all_roles as $role_slug => $role_data) {
                    $is_checked = in_array($role_slug, $allowed_roles, true);
                    echo '<label style="display: block; margin-bottom: 4px;">';
                    echo '<input type="checkbox" name="doccheck_allowed_roles[]" value="' . esc_attr($role_slug) . '" ' . checked(true, $is_checked, false) . ' />';
                    echo ' ' . esc_html(translate_user_role($role_data['name']));
                    echo '</label>';
                }

                echo '</fieldset>';
                echo '</div>';

                // Toggle role list visibility based on the protection checkbox
                echo '<script>
                    document.getElementById("doccheck_protected").addEventListener("change", function() {
                        document.getElementById("doccheck-role-restriction").style.display = this.checked ? "" : "none";
                    });
                </script>';
            }

        }

        /**
         * Save the page protection meta
         *
         * @param int $post_id Post ID.
         * @since 2.1.0
         */
        public function save_page_protection_meta($post_id)
        {
            // Check if nonce is set.
            if (!isset($_POST['doccheck_page_protection_nonce'])) {
                return;
            }

            // Verify that the nonce is valid.
            $nonce = isset($_POST['doccheck_page_protection_nonce']) ? sanitize_text_field(wp_unslash($_POST['doccheck_page_protection_nonce'])) : '';
            if (!wp_verify_nonce($nonce, 'doccheck_save_page_protection')) {
                return;
            }

            // If this is an autosave, our form has not been submitted, so we don't want to do anything.
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            // Check if settings are an object (should be DocCheck_Login_Settings)
            if (is_array($this->settings)) {
                $is_protected = isset($_POST['doccheck_protected']) ? 1 : 0;
                update_post_meta($post_id, '_doccheck_protected', $is_protected);
                return;
            }

            // Check the user's permissions.
            $post_type = get_post_type($post_id);
            $post_type_object = get_post_type_object($post_type);

            if (!$post_type_object || !current_user_can($post_type_object->cap->edit_post, $post_id)) {
                return;
            }

            // Update the meta field in the database.
            $is_protected = isset($_POST['doccheck_protected']) ? 1 : 0;
            update_post_meta($post_id, '_doccheck_protected', $is_protected);

            // Save allowed roles (only relevant in wordpress_user mode)
            if ($this->settings->get_authentication_mode() === 'wordpress_user') {
                $allowed_roles = [];
                if (isset($_POST['doccheck_allowed_roles']) && is_array($_POST['doccheck_allowed_roles'])) {
                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via array_map and sanitize_text_field.
                    $raw_roles     = (array) wp_unslash($_POST['doccheck_allowed_roles']);
                    $allowed_roles = array_map('sanitize_text_field', $raw_roles);
                }
                update_post_meta($post_id, '_doccheck_allowed_roles', $allowed_roles);
            }
        }
    }
}