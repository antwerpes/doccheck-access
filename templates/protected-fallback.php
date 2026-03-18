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
                echo wp_kses_post( $doccheck_login->login_shortcode( [ 'samepageredirect' => '1' ] ) );
                ?>
            </div>
        </article>
    </main>
</div>

<style>
    .doccheck-login-fallback-wrapper {
        padding: 4rem 1rem;
        max-width: 1200px;
        margin: 0 auto;
        min-height: 50vh;
    }
    .doccheck-protected-article {
        max-width: 800px;
        margin: 0 auto;
        background: #fff;
        padding: 2rem;
        border-radius: 8px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    .doccheck-protection-notice {
        background-color: #f3e8ff;
        border-left: 4px solid #a855f7;
        padding: 1rem;
        margin-bottom: 2rem;
        color: #7e22ce;
        font-weight: 500;
    }
    .entry-header {
        margin-bottom: 2rem;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 1rem;
    }
    .entry-title {
        font-size: 2.25rem;
        font-weight: 700;
        color: #111827;
        margin: 0;
    }
</style>

<?php get_footer(); ?>
