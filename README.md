=== DocCheck Login ===
Contributors: doccheck
Tags: login, authentication, oauth, doccheck, medical
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 2.1.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Integrate DocCheck OAuth2 login functionality into your WordPress site.

== Description ==

The DocCheck Login plugin integrates DocCheck's OAuth2 authentication system into your WordPress site, allowing medical professionals to log in using their DocCheck credentials.

= Features =

* Adds a "Login with DocCheck" button to the WordPress login page
* Provides shortcodes for embedding login functionality anywhere:
  * [doccheck_login] - Displays the DocCheck login button
  * [dc-hide-content] - Protects content for logged-in users only
  * [dc_logout] - Provides a logout link
* Implements OAuth 2.0 Authorization Code flow with PKCE for secure authentication
* Retrieves user data from DocCheck API using access tokens
* Creates new WordPress users when a DocCheck user logs in for the first time
* Maps DocCheck user roles to WordPress roles
* Enhanced User Management with configurable fallback options for missing data
* Extended metadata mapping for all available DocCheck scopes
* Advanced logging for API endpoints with session tracking and data protection
* Template override support for protected pages
* Includes hooks for developers to customize behavior
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

= 2.1.3 =
* Added template functions for theme developers: `doccheck_is_authenticated()` and `get_doccheck_user_data()`
* Added `doccheck_is_authenticated` filter to override authentication checks
* Added `doccheck_user_data` filter to modify returned user data
* Added Template Functions documentation section in admin settings

= 1.4.0 =
* Added support for overriding the DocCheck protected content template
* Added `doccheck-protected.php` theme template support
* Added `doccheck_protected_template` filter for developers

= 1.3.0 =
* Added Enhanced User Management with fallback options for missing data
* Added configurable fallback behavior (session-only, or create with defaults)
* Enhanced metadata mapping for all available DocCheck scopes
* Added field visibility and mapping documentation in admin interface
* Added automatic splitting of full name into first and last name components
* Added special handling for address fields and complex data types

= 1.2.0 =
* Added advanced logging functionality for token and userdata endpoints
* Implemented session ID tracking for better request tracing
* Added proper obfuscation of sensitive data in logs (only first 15 chars shown)
* Logs are now stored in separate files by endpoint type and date
* Added automatic log rotation (logs older than 30 days are removed)
* Enhanced security with protected log directory

= 1.1.0 =
* Added enhanced state parameter with encrypted redirect URL support
* Added samepageredirect attribute to [doccheck_login] shortcode
* Fixed state validation in OAuth callback handling
* Improved redirect handling after authentication

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.5.0 =
This update adds global PHP template functions (`doccheck_is_authenticated()` and `get_doccheck_user_data()`) so theme developers can detect DocCheck authentication directly in PHP templates without relying on shortcodes.

= 1.4.0 =
This update allows developers to customize the appearance of protected pages by creating a `doccheck-protected.php` file in their theme or using the `doccheck_protected_template` filter.

= 1.3.0 =
This update adds Enhanced User Management with configurable fallback options for missing user data. It also improves metadata mapping for all DocCheck scopes and adds field visibility documentation in the admin interface.

= 1.2.0 =
This update adds advanced logging for API endpoints with better security, session tracking, and log rotation. Debug logs are now organized by endpoint type and include better protection of sensitive data.

= 1.1.0 =
This update adds the ability to redirect users back to the original page after login. Use the samepageredirect="1" attribute with the [doccheck_login] shortcode.

= 1.0.0 =
Initial release

== Shortcodes Usage ==

= [doccheck_login] =

This shortcode displays the DocCheck login button. It can be used on any page or post.

**Attributes:**

* `size` - The size of the login button (default: "medium")
  * Possible values: "small", "medium", "large"
* `language` - The language code for the button (default: WordPress locale)
  * Example: "en", "de"
* `state` - Custom state parameter for OAuth flow (default: auto-generated)
* `scope` - Custom scope parameter for OAuth permissions (default: from plugin settings)
* `samepageredirect` - Whether to redirect back to the current page after login (default: "0")
  * Possible values: "0" (disabled), "1" (enabled)

**Examples:**

`[doccheck_login size="large" language="en"]`

`[doccheck_login samepageredirect="1"]` - Redirects back to the current page after login

= [dc-hide-content] =

This shortcode hides content so that it's only visible to logged-in users. Content between the opening and closing shortcode tags will be protected.

**Example:**

`[dc-hide-content]
This content is only visible to logged-in DocCheck users.
[/dc-hide-content]`

= [dc_logout] =

This shortcode displays a logout link for users who are currently logged in.

**Attributes:**

* `redirect` - URL to redirect to after logout (default: home page URL)

**Example:**

`[dc_logout redirect="https://example.com/thank-you"]`

== Developer Documentation ==

= Hooks and Filters =

The following hooks and filters are available for developers:

**Actions:**

* `doccheck_login_user_created` - Fires after a new user is created via DocCheck login
  * Parameters: `$user_id` (int), `$user_data` (array)
* `doccheck_login_user_logged_in` - Fires when an existing user logs in via DocCheck
  * Parameters: `$user_id` (int), `$user_data` (array)

**Filters:**

* `doccheck_login_map_role` - Filter to customize user role based on DocCheck data
  * Parameters: `$current_role` (string), `$user_data` (array), `$user_id` (int)
  * Return: New role (string) or current role if no change
* `doccheck_protected_template` - Filter to customize the path to the template used for protected pages
  * Parameters: `$template` (string) - Full path to the current template file
  * Return: New template path (string)
* `doccheck_is_authenticated` - Filter to override the DocCheck authentication check
  * Parameters: `$authenticated` (bool)
  * Return: Boolean
* `doccheck_user_data` - Filter to modify the DocCheck user data array
  * Parameters: `$user_data` (array)
  * Return: Modified array

== Customizing Protected Content Template ==

The plugin provides a default template for pages that are DocCheck protected. You can customize this in two ways:

1. **Theme Template File**:
Create a file named `doccheck-protected.php` in your active theme's root directory. The plugin will automatically use this file instead of the default one.

2. **WordPress Filter**:
Use the `doccheck_protected_template` filter in your theme's `functions.php` to programmatically change the path to the template file.

**Example:**
```php
add_filter('doccheck_protected_template', function($template) {
    // Return custom path to your template
    return get_stylesheet_directory() . '/my-custom-protection-template.php';
});
```

= Template Functions =

The following global PHP functions are available for use in theme templates:

* `doccheck_is_authenticated()` - Returns `true` if the current visitor is authenticated via DocCheck (works in both wordpress_user and anonymous_session modes)
* `doccheck_get_user_data()` - Returns an associative array of DocCheck user fields (prefix-stripped_keys), or an empty array for unauthenticated visitors

**Example:**
```php
<?php if ( function_exists( 'doccheck_is_authenticated' ) && doccheck_is_authenticated() ) : ?>
    <div class="hcp-only">Visible only to DocCheck users</div>
<?php else : ?>
    <p>Please log in with DocCheck.</p>
    <?php echo do_shortcode( '[doccheck_login]' ); ?>
<?php endif; ?>
```

= User Meta Data =

The plugin stores the following user meta data for each DocCheck user:

**Basic Fields:**
* `doccheck_unique_id` - Unique identifier for the DocCheck user
* `doccheck_profession` - User's profession
* `doccheck_specialty` - User's medical specialty
* `doccheck_country` - User's country
* `doccheck_language` - User's language

**Extended Fields:**
* `doccheck_name` - User's full name
* `doccheck_email` - User's email address
* `doccheck_occupation_detail` - Detailed occupation information
* `doccheck_address` - User's address (serialized)
* `doccheck_gender` - User's gender
* `doccheck_title` - User's title (Dr., Prof., etc.)
* `doccheck_preferred_language` - User's preferred language

**WordPress Core Fields (automatically mapped):**
* `first_name` - Extracted from full name when possible
* `last_name` - Extracted from full name when possible

**Address Components (when address data is available):**
* `doccheck_address_street` - Street address
* `doccheck_address_city` - City
* `doccheck_address_zip` - ZIP/Postal code
* `doccheck_address_country` - Country code

**Special Fields:**
* `doccheck_last_login` - Timestamp of the user's last login via DocCheck
* `doccheck_created_with_defaults` - Indicates if user was created with default values
* `doccheck_userdata` - Full serialized response from the DocCheck API