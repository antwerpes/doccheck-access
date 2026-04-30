<?php
/**
 * Template functions for theme developers.
 *
 * Provides global helper functions to check DocCheck authentication
 * and retrieve user data from PHP theme templates.
 *
 * @package DocCheck_Login
 * @since   2.1.3
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

if ( ! function_exists( 'doccheck_maybe_start_session' ) ) {
    /**
     * Start a session only when a DocCheck session cookie already exists.
     *
     * @since 2.4.1
     *
     * @return bool True when a session is active.
     */
    function doccheck_maybe_start_session() {
        if ( session_status() === PHP_SESSION_ACTIVE ) {
            return true;
        }

        if ( headers_sent() ) {
            return false;
        }

        if ( ! isset( $_COOKIE[ session_name() ] ) ) {
            return false;
        }

        session_start();
        return session_status() === PHP_SESSION_ACTIVE;
    }
}

if ( ! function_exists( 'doccheck_is_authenticated' ) ) {
    /**
     * Check whether the current visitor is authenticated via DocCheck.
     *
     * Works in both authentication modes:
     * - wordpress_user: checks for `doccheck_unique_id` user meta
     * - anonymous_session: checks `$_SESSION['doccheck_session_auth']`
     *
     * @since 2.1.3
     *
     * @return bool True if authenticated via DocCheck, false otherwise.
     */
    function doccheck_is_authenticated() {
        $authenticated = false;

        // Check for WordPress user with DocCheck ID.
        if ( is_user_logged_in() ) {
            $user_id      = get_current_user_id();
            $doccheck_id  = get_user_meta( $user_id, 'doccheck_unique_id', true );
            if ( ! empty( $doccheck_id ) ) {
                $authenticated = true;
            }
        }

        doccheck_maybe_start_session();

        // Check for DocCheck session.
        if ( ! $authenticated && isset( $_SESSION['doccheck_session_auth'] ) && $_SESSION['doccheck_session_auth'] === true ) {
            $authenticated = true;
        }

        /**
         * Filter the DocCheck authentication result.
         *
         * @since 2.1.3
         *
         * @param bool $authenticated Whether the current user is DocCheck-authenticated.
         */
        return (bool) apply_filters( 'doccheck_is_authenticated', $authenticated );
    }
}

if ( ! function_exists( 'doccheck_get_user_data' ) ) {
    /**
     * Retrieve DocCheck user data for the current visitor.
     *
     * Returns an associative array of DocCheck fields (keys have the
     * `doccheck_` prefix stripped). Returns an empty array when the
     * visitor is not authenticated via DocCheck.
     *
     * @since 2.1.3
     *
     * @return array DocCheck user data, or empty array.
     */
    function doccheck_get_user_data() {
        $user_data = array();

        // For WordPress users.
        if ( is_user_logged_in() ) {
            $user_id     = get_current_user_id();
            $doccheck_id = get_user_meta( $user_id, 'doccheck_unique_id', true );
            if ( ! empty( $doccheck_id ) ) {
                $user_meta = get_user_meta( $user_id );
                foreach ( $user_meta as $meta_key => $meta_value ) {
                    if ( strpos( $meta_key, 'doccheck_' ) === 0 ) {
                        $user_data[ str_replace( 'doccheck_', '', $meta_key ) ] = $meta_value[0];
                    }
                }

                /** This filter is documented below. */
                return apply_filters( 'doccheck_user_data', $user_data );
            }
        }

        doccheck_maybe_start_session();

        // For session users.
        if ( isset( $_SESSION['doccheck_session_auth'] ) && $_SESSION['doccheck_session_auth'] === true ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
            $session_data = isset( $_SESSION['doccheck_session_data'] ) ? (array) $_SESSION['doccheck_session_data'] : array();

            $user_data = array_map( function ( $value ) {
                return is_string( $value ) ? sanitize_text_field( $value ) : $value;
            }, $session_data );
        }

        /**
         * Filter the DocCheck user data array.
         *
         * @since 2.1.3
         *
         * @param array $user_data DocCheck user fields (prefix-stripped keys).
         */
        return apply_filters( 'doccheck_user_data', $user_data );
    }
}
