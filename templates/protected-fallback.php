<?php
/**
 * Global Fallback Template for protected pages
 *
 * This template is used when the current page is DocCheck protected
 * and the theme does not provide a custom 'doccheck-protected.php' template.
 *
 * @package DocCheck_Login
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header(); ?>

<div id="doccheck-protected-outer" class="doccheck-login-fallback-wrapper">
    <main id="main" class="site-main">
        <article class="page type-page status-publish hentry doccheck-protected-article">
            <header class="entry-header">
                <h1 class="entry-title"><?php the_title(); ?></h1>
            </header>

            <div class="entry-content">
                <div class="doccheck-protection-notice">
                    <p><?php esc_html_e('This content is protected. Please log in with your DocCheck account to continue.', 'doccheck-access'); ?></p>
                </div>
                <?php
                // We use the shortcode logic to render the button
                $doccheck_login = new DocCheck_Login();
                echo wp_kses(
                    $doccheck_login->login_shortcode( [ 'samepageredirect' => '1' ] ),
                    [
                        'div'             => [
                            'class' => true,
                        ],
                        'dc-login-button' => [
                            'size'          => true,
                            'language'      => true,
                            'loginclientid' => true,
                            'redirecturi'   => true,
                            'scope'         => true,
                            'base'          => true,
                            'state'         => true,
                        ],
                    ]
                );
                ?>
            </div>
        </article>
    </main>
</div>

<?php get_footer(); ?>
