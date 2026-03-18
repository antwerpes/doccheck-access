=== DocCheck Login ===
Contributors: doccheck
Tags: login, authentication, oauth, doccheck, medical
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Integrate DocCheck OAuth2 login functionality into your WordPress site.

== Description ==

The DocCheck Login plugin integrates DocCheck's OAuth2 authentication system into your WordPress site, allowing medical professionals to log in using their DocCheck credentials.

= Features =

* Adds a "Login with DocCheck" button to the WordPress login page
* Provides shortcodes for embedding login functionality anywhere:
  * `[doccheck_login]` - Displays the DocCheck login button
  * `[dc-hide-content]` - Protects content for logged-in users only
  * `[dc_logout]` - Provides a logout link
* Implements OAuth 2.0 Authorization Code flow with PKCE for secure authentication
* Retrieves user data from DocCheck API using access tokens
* Creates new WordPress users when a DocCheck user logs in for the first time
* Maps DocCheck user roles to WordPress roles
* Enhanced User Management with configurable fallback options for missing data
* Extended metadata mapping for all available DocCheck scopes
* Advanced logging for API endpoints with session tracking and data protection
* Template override support for protected pages
* Hooks and filters for developers to customize behavior
* Template functions for theme developers to detect DocCheck authentication in PHP

= Requirements =

* WordPress 5.0 or higher
* PHP 5.6 or higher
* A DocCheck OAuth client ID and client secret

== Installation ==

1. Upload the `doccheck-access` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > DocCheck Login to configure your DocCheck OAuth credentials
4. Add the shortcodes to any page or post where you want to use DocCheck Login functionality

== Frequently Asked Questions ==

= How do I get DocCheck OAuth credentials? =

You need to contact DocCheck to obtain your OAuth client ID and client secret.

= Can I customize the appearance of the login button? =

Yes, the plugin uses DocCheck's official login button component which supports various size options. You can also customize the appearance with CSS.

= How does user mapping work? =

When a DocCheck user logs in for the first time, the plugin creates a new WordPress user with the default role specified in the plugin settings. The DocCheck unique ID is stored as user meta data.

= Can I map specific DocCheck user types to specific WordPress roles? =

Yes, you can use the `doccheck_login_map_role` filter to customize the role mapping based on DocCheck user data.

== Screenshots ==

1. DocCheck Login button on WordPress login page
2. Admin settings page
3. Shortcode usage example

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== Shortcodes ==

= [doccheck_login] =

Displays the DocCheck login button.

**Attributes:**

* `size` - Button size: "small", "medium" (default), "large"
* `language` - Language code, e.g. "en", "de" (default: WordPress locale)
* `state` - Custom OAuth state parameter (default: auto-generated)
* `scope` - Custom OAuth scope (default: from plugin settings)
* `samepageredirect` - Redirect back to current page after login: "0" (default) or "1"

**Examples:**

    [doccheck_login size="large" language="en"]

    [doccheck_login samepageredirect="1"]

= [dc-hide-content] =

Hides content so it is only visible to authenticated DocCheck users.

**Example:**

    [dc-hide-content]
    This content is only visible to logged-in DocCheck users.
    [/dc-hide-content]

= [dc_logout] =

Displays a logout link for authenticated users.

**Attributes:**

* `redirect` - URL to redirect to after logout (default: home page URL)

**Example:**

    [dc_logout redirect="https://example.com/thank-you"]

== Developer Documentation ==

= Hooks and Filters =

**Actions:**

* `doccheck_login_user_created` - Fires after a new user is created via DocCheck login
  * Parameters: `$user_id` (int), `$user_data` (array)
* `doccheck_login_user_logged_in` - Fires when an existing user logs in via DocCheck
  * Parameters: `$user_id` (int), `$user_data` (array)

**Filters:**

* `doccheck_login_map_role` - Customize user role based on DocCheck data
  * Parameters: `$current_role` (string), `$user_data` (array), `$user_id` (int)
* `doccheck_protected_template` - Customize the path to the protected page template
  * Parameters: `$template` (string)
* `doccheck_is_authenticated` - Override the DocCheck authentication check
  * Parameters: `$authenticated` (bool)
* `doccheck_user_data` - Modify the DocCheck user data array
  * Parameters: `$user_data` (array)

= Template Functions =

* `doccheck_is_authenticated()` - Returns `true` if the current visitor is authenticated via DocCheck
* `doccheck_get_user_data()` - Returns an associative array of DocCheck user fields, or empty array for unauthenticated visitors

**Example:**

    <?php if ( function_exists( 'doccheck_is_authenticated' ) && doccheck_is_authenticated() ) : ?>
        <div class="hcp-only">Visible only to DocCheck users</div>
    <?php else : ?>
        <p>Please log in with DocCheck.</p>
        <?php echo do_shortcode( '[doccheck_login]' ); ?>
    <?php endif; ?>

= Customizing the Protected Content Template =

1. **Theme file:** Create `doccheck-protected.php` in your active theme directory.
2. **Filter:** Use the `doccheck_protected_template` filter in `functions.php`:

    add_filter( 'doccheck_protected_template', function( $template ) {
        return get_stylesheet_directory() . '/my-custom-template.php';
    } );

= User Meta Data =

The plugin stores the following meta fields for each DocCheck user:

* `doccheck_unique_id` - Unique identifier
* `doccheck_profession` - User's profession
* `doccheck_specialty` - Medical specialty
* `doccheck_country` - Country
* `doccheck_language` - Language
* `doccheck_name` - Full name
* `doccheck_email` - Email address
* `doccheck_occupation_detail` - Detailed occupation
* `doccheck_address` - Address (serialized)
* `doccheck_gender` - Gender
* `doccheck_title` - Title (Dr., Prof., etc.)
* `doccheck_preferred_language` - Preferred language
* `doccheck_address_street`, `doccheck_address_city`, `doccheck_address_zip`, `doccheck_address_country` - Address components
* `doccheck_last_login` - Timestamp of last DocCheck login
* `doccheck_userdata` - Full serialized API response
