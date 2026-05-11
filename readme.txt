=== DocCheck Access ===
Contributors: DocCheck agency
Tags: DocCheck, login, medical, hcp, authentication
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.3
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrate DocCheck OAuth2 login functionality into your WordPress site.

== Description ==

The DocCheck Access plugin integrates DocCheck's OAuth2 authentication system into your WordPress site, allowing medical professionals to log in using their DocCheck credentials.

= Features =

* Adds a DocCheck login button via shortcode or automatic page-level protection
* OAuth 2.0 Authorization Code flow with PKCE for secure authentication
* Two authentication modes: Anonymous Session and WordPress User creation
* Per-page and global content protection with role-based access control
* Configurable scope and user metadata mapping
* Template override support for protected pages
* Hooks and filters for developers to customize behavior

= External Services =

This plugin connects to the following external services:

**DocCheck OAuth Server** (`https://auth.doccheck.com`)

Used to exchange the OAuth authorization code for an access token and to retrieve the authenticated user's profile data. This connection is only made when a visitor actively clicks the DocCheck login button. Please refer to the [DocCheck Privacy Policy](https://www.doccheck.com/privacy) and [DocCheck Terms of Service](https://www.doccheck.com/terms).

**DocCheck CDN** (`https://dccdn.de`)

The DocCheck login button is a web component whose script is served from DocCheck's CDN. It is loaded only on pages where the `[docacc_login]` shortcode or page-level protection is active — not on every page. Please refer to the [DocCheck Privacy Policy](https://www.doccheck.com/privacy).

No data is transmitted to any other third-party service.

= Requirements =

* WordPress 5.0 or higher
* PHP 7.2 or higher
* A DocCheck OAuth client ID and client secret (obtainable from DocCheck)

= General Settings =

Go to **Settings > DocCheck Login** in your WordPress admin to configure the plugin.

**OAuth Credentials**

* **Client ID** — Your DocCheck OAuth Client ID.
* **Client Secret** — Your DocCheck OAuth Client Secret.
* **Redirect URI** — Auto-generated based on your site URL. Copy this value into your DocCheck application settings.

**Redirection & Debug**

* **Default Target Page** — The page users land on after a successful login.
* **Debug Mode** — Logs detailed API and authentication information. Disable on production sites.

**Content Protection**

* **Make all Pages Private** — Requires DocCheck login for every page on the site.
* **Auto-assign Parent Configurations** — Child pages automatically inherit their parent page's protection status.
* **Login Button Version** — Pin a specific component version (e.g. `3.2.7`) or use `@latest` to always load the most recent version.

= User Management =

**Authentication Modes**

* **Anonymous Session** — Users are authenticated via DocCheck but no WordPress user account is created. Data is held only for the duration of the PHP session and is not stored permanently.
* **WordPress User** — A WordPress user account is created or linked on the visitor's first DocCheck login. Allows persistent storage of user properties and role-based access control.

**Role & Metadata**

* **Default User Role** — The WordPress role assigned to newly created DocCheck users. Only low-privilege roles (those without `manage_options` or `edit_others_posts` capabilities) are available for selection. Administrator and Editor roles cannot be assigned to DocCheck users.
* **Automatic User Creation** — Disabled by default. In WordPress User mode, local user creation for first-time DocCheck logins must be explicitly enabled by an administrator.
* **Scope & Property Selection** — Choose which DocCheck scopes to request and which user properties to store as WordPress user metadata.

= Shortcodes =

**[docacc_login]**

Displays the DocCheck login button.

Attributes:

* `size` — Button size: `small`, `medium` (default), `large`
* `language` — Language code, e.g. `en`, `de` (default: WordPress locale)
* `state` — Custom app-state value, passed back as `?state=` after login
* `scope` — OAuth scope override (default: from plugin settings)
* `samepageredirect` — Redirect back to the current page after login: `0` (default) or `1`

Examples:

    [docacc_login size="large" language="en"]
    [docacc_login samepageredirect="1"]

**[docacc_hide_content]**

Hides content so it is only visible to authenticated DocCheck users.

    [docacc_hide_content]
    This content is only visible to logged-in DocCheck users.
    [/docacc_hide_content]

**[docacc_logout]**

Displays a logout link for authenticated users.

Attribute:

* `redirect` — URL to redirect to after logout (default: home page)

    [docacc_logout redirect="https://example.com/thank-you"]

**[docacc_sitemap]**

Renders an HTML sitemap that automatically hides protected pages from unauthenticated visitors.

Attributes:

* `post_type` — Comma-separated post type slugs (default: all public types)
* `show_protected` — `yes`, `no`, or `auto` (default: based on current authentication status)
* `depth` — Hierarchy depth limit, `0` = unlimited (default)
* `exclude` — Comma-separated post IDs to exclude

== Installation ==

1. Upload the `doccheck-access` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings > DocCheck Login** and enter your DocCheck OAuth credentials.
4. Copy the displayed **Redirect URI** into your DocCheck application settings.
5. Add `[docacc_login]` to any page or post where you want the login button to appear.

== Frequently Asked Questions ==

= How do I get DocCheck OAuth credentials? =

Contact DocCheck to register your application and obtain a client ID and client secret.

= Can I customize the appearance of the login button? =

Yes. The `[docacc_login]` shortcode accepts a `size` attribute (`small`, `medium`, `large`). You can also apply custom CSS to the `dc-login-button` element.

= How does user creation work? =

In WordPress User mode, a new account is created on the visitor's first DocCheck login. The DocCheck unique ID is stored as user meta (`docacc_unique_id`) and used to match subsequent logins to the same account.

= Can I map DocCheck user types to specific WordPress roles? =

Yes. Use the `docacc_map_role` filter:

    add_filter( 'docacc_map_role', function( $role, $user_data, $user_id ) {
        if ( isset( $user_data['profession'] ) && $user_data['profession'] === 'physician' ) {
            return 'editor';
        }
        return $role;
    }, 10, 3 );

= How do I protect a single page? =

Edit the page in the WordPress admin. A **DocCheck Protection** metabox appears in the sidebar — check **Protect this page** and save.

= Can I protect all pages at once? =

Yes. Enable **Make all Pages Private** under **Settings > DocCheck Login**.

== Other Notes ==

= Developer Hooks =

**Actions**

* `docacc_user_created` — Fires after a new WordPress user is created via DocCheck login.
  Parameters: `$user_id` (int), `$user_data` (array)

* `docacc_user_logged_in` — Fires when an existing user logs in via DocCheck.
  Parameters: `$user_id` (int), `$user_data` (array)

* `docacc_session_created` — Fires when a user is authenticated in anonymous session mode.
  Parameters: `$user_data` (array)

**Filters**

* `docacc_map_role` — Customize role assignment based on DocCheck user data.
  Parameters: `$current_role` (string), `$user_data` (array), `$user_id` (int)
  Note: roles with `manage_options` or `edit_others_posts` capabilities are silently rejected for security reasons.

* `docacc_protected_template` — Override the template used for protected pages.
  Parameters: `$template` (string)

* `docacc_is_authenticated` — Override the authentication check result.
  Parameters: `$authenticated` (bool)

* `docacc_user_data` — Modify the DocCheck user data array before it is used.
  Parameters: `$user_data` (array)

= Template Functions =

    // Check if the current visitor is authenticated via DocCheck
    docacc_is_authenticated(); // returns bool

    // Get the authenticated user's DocCheck profile fields
    docacc_get_user_data(); // returns array, empty if not authenticated

Example in a theme template:

    <?php if ( docacc_is_authenticated() ) : ?>
        <div class="hcp-content">Visible only to DocCheck users.</div>
    <?php else : ?>
        <?php echo do_shortcode( '[docacc_login]' ); ?>
    <?php endif; ?>

= Custom Protected Page Template =

Create `doccheck-protected.php` in your active theme directory — the plugin uses it automatically. Or override via filter:

    add_filter( 'docacc_protected_template', function( $template ) {
        return get_stylesheet_directory() . '/my-protected-template.php';
    } );

= User Metadata Stored =

In WordPress User mode, the following meta fields are stored per user (subject to selected scopes):

* `docacc_unique_id` — DocCheck unique identifier (always stored)
* `docacc_profession` — Profession name
* `docacc_country` — Country ISO code
* `docacc_language` — Interface language
* `first_name`, `last_name` — Name fields
* `docacc_email` — Email address
* `docacc_discipline_name` — Medical discipline
* `docacc_activity_name` — Activity type
* `docacc_area_code`, `docacc_street`, `docacc_city`, `docacc_state` — Address fields
* `docacc_last_login` — Timestamp of last DocCheck login

== Changelog ==

= 1.0.3 =
* Review fix: Renamed plugin-owned global identifiers to the unique `docacc` prefix, including functions, classes, constants, options, hooks, transients, session keys, user meta keys, role slug, and shortcodes.
* Review fix: Replaced shortcodes with `[docacc_login]`, `[docacc_hide_content]`, `[docacc_logout]`, and `[docacc_sitemap]`.
* Review fix: Removed plugin-owned `class_exists()` and `function_exists()` wrappers to avoid silent conflicts with other plugins or themes.
* Review fix: Updated the OAuth callback query var, settings option, admin documentation, developer hooks, and examples to use the `docacc` prefix consistently.
* Compatibility: Added idempotent settings initialization so the renamed settings option is created safely during updates as well as new activations.

= 1.0.2 =
* Security: Restricted the Default User Role dropdown to low-privilege roles only (excludes roles with `manage_options` or `edit_others_posts`).
* Security: Added server-side validation in `validate_settings()` to reject high-privilege roles even if submitted directly.
* Security: The `docacc_map_role` filter result is now validated before `set_role()` is called, preventing privilege escalation via custom filter callbacks.
* Security: Added explicit opt-in for automatic local user creation (`allow_user_creation`), defaulted to off, and defaulted new installs to Anonymous Session mode.

= 1.0.1 =
* Review fix: Replaced inline `<script>` and `<style>` output with proper WordPress enqueue APIs.
* Added admin JavaScript through `admin_enqueue_scripts` + `wp_add_inline_script()` for settings tabs, scope/property matrix behavior, redirect URI copy button, and metabox role toggle.
* Moved matrix CSS and protected fallback template CSS into enqueued stylesheet assets.
* Review fix: Updated `register_setting()` arguments and adjusted `client_secret` sanitization to use a dedicated secret-safe callback instead of generic text-field sanitization.
* Review fix: Escaped shortcode callback return output for `docacc_logout` and sanitized rendered `docacc_hide_content` output with `wp_kses_post()`.
* Review fix: Removed global session start behavior and introduced lazy, cookie-aware session initialization only in DocCheck authentication/session contexts.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.3 =
Shortcodes and developer-facing identifiers now use the `docacc` prefix. Update any content or custom code that referenced older shortcode or hook names.

= 1.0.0 =
Initial release.
