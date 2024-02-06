<?php
/**
 * The header for our theme
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package ipdk
 */
global $current_user;
?>
<!doctype html>
<html <?php language_attributes(); ?> class="ipdk-theme">
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="https://gmpg.org/xfn/11">

	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="page" class="site">
    <a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'ipdk' ); ?></a>

    <header id="masthead" class="site-header">
        <div class="site-branding">
            <div itemscope itemtype="http://schema.org/Brand">
                <meta itemprop="name" content="intimepiger.dk"/>
                <meta itemprop="logo" content="intimepiger.dk logo image"/>
                <a class="site-branding-link link" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
                    <img src="<?php echo get_template_directory_uri() ?>/img/logo-6.svg " alt="" class="site-branding-logo-desktop">
                </a>
            </div><!-- .site-branding -->
        </div>
        <nav id="site-navigation" class="main-navigation">
            <button class="menu-toggle-button top-right-bar" role="button" aria-label="Menu Toggle"
                    aria-controls="primary-menu" aria-expanded="false">
                <div class="hamburger-button">
                    <span></span> <span></span> <span></span>
                </div>
            </button>

			<?php
			if ( is_user_logged_in() ) {
				wp_nav_menu(
					array(
						'theme_location' => 'logged-in',
						'menu_id'        => 'primary-menu-in',
					)
				); ?>
                <div class="site-header-content-right">
					<?php if ( is_user_logged_in() ) : ?>
                        <ul class="main-navigation-profile-menu">
                            <li class="site-header-top-bar-nav-link user"><span><?= insertSVG( 'icon-user', '0 0 24 28' ) ?></span> <span><?= ucfirst( $current_user->display_name ) ?></span></li>
                            <li>
								<?php
								wp_nav_menu( array(
									'theme_location' => 'account-menu-logged-in',
									'menu_id'        => 'account-menu-logged-in',
									'menu_class'     => 'account-menu-logged-in'
								) );
								?>
                            </li>
                        </ul>
					<?php endif; //if user is logged in  ?>
                </div>
				<?php
			} else { ?>
				<?php $current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
				wp_nav_menu(
					array(
						'theme_location' => 'logged-out',
						'menu_id'        => 'primary-menu-out',
					)
				);
				?>
			<?php } ?>
        </nav><!-- #site-navigation -->
    </header><!-- #masthead -->
