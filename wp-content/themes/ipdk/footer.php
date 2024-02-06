<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package ipdk
 */

?>

<footer id="colophon" class="site-footer">
    <div class="site-footer-content">
        <div class="container">
            <a class="site-branding-link footer" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
                <img src="<?php echo get_template_directory_uri() ?>/img/logo-4.svg " alt="" class="site-branding-logo-desktop">
            </a>
        </div>
    </div>
    <div class="site-info">
        <div class="container">
        <span>intimepiger.dk® <?= date( 'Y' ) ?>. Alle rettigheder forbeholdes</span>
        </div>
    </div><!-- .site-info -->

</footer><!-- #colophon -->


<?php wp_footer(); ?>

</body>
</html>
