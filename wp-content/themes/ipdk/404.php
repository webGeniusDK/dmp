<?php
/**
 * The template for displaying 404 pages (not found)
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package ipdk
 */

get_header();
?>

    <main id="primary" class="site-main">
        <div class="container">
            <section class="error-404 not-found">
                <header class="page-header">
                    <h1 class="page-title"><?php esc_html_e( 'Oops! That page can&rsquo;t be found.', 'ipdk' ); ?></h1>
                    <a href="/">Gå til forsiden</a>
                </header><!-- .page-header -->
                <div class="page-content">


                </div><!-- .page-content -->
            </section><!-- .error-404 -->
        </div>
    </main><!-- #main -->
    </div><!-- #page -->
<?php
get_footer();
