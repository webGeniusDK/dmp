<?php
/**
 * ipdk functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package ipdk
 */

if ( ! defined( '_S_VERSION' ) ) {
	// Replace the version number of the theme on each release.
	define( '_S_VERSION', '1.0.0' );
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function ipdk_setup() {
	/*
		* Make theme available for translation.
		* Translations can be filed in the /languages/ directory.
		* If you're building a theme based on ipdk, use a find and replace
		* to change 'ipdk' to the name of your theme in all the template files.
		*/
	load_theme_textdomain( 'ipdk', get_template_directory() . '/languages' );

	// Add default posts and comments RSS feed links to head.
	add_theme_support( 'automatic-feed-links' );

	/*
		* Let WordPress manage the document title.
		* By adding theme support, we declare that this theme does not use a
		* hard-coded <title> tag in the document head, and expect WordPress to
		* provide it for us.
		*/
	add_theme_support( 'title-tag' );

	/*
		* Enable support for Post Thumbnails on posts and pages.
		*
		* @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
		*/
	add_theme_support( 'post-thumbnails' );

	/*
		* Switch default core markup for search form, comment form, and comments
		* to output valid HTML5.
		*/
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);

	// Set up the WordPress core custom background feature.
	add_theme_support(
		'custom-background',
		apply_filters(
			'ipdk_custom_background_args',
			array(
				'default-color' => 'ffffff',
				'default-image' => '',
			)
		)
	);

	// Add theme support for selective refresh for widgets.
	add_theme_support( 'customize-selective-refresh-widgets' );

	/**
	 * Add support for core custom logo.
	 *
	 * @link https://codex.wordpress.org/Theme_Logo
	 */
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 250,
			'width'       => 250,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);
    $bp_xs = '480px';
    $bp_sm = '768px';
    $bp_xl = '2560px';
    $bp_xxl = '3000px';


    add_image_size('smallest', $bp_xs, 9999);
    add_image_size('small', $bp_sm, 9999);
    add_image_size('larger', $bp_xl, 9999);
    add_image_size('largest', $bp_xxl, 9999);

	register_nav_menus(
		array(
			'logged-in'              => esc_html__( 'Primary - Logged In', 'dsfstudio' ),
			'logged-out'             => esc_html__( 'Primary - Logged Out', 'dsfstudio' ),
			'account-menu-logged-in' => esc_html__( 'My Account Menu - Logged In', 'dsfstudio' ),
		)
	);
}
add_action( 'after_setup_theme', 'ipdk_setup' );

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function ipdk_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'ipdk_content_width', 1260 );
}
add_action( 'after_setup_theme', 'ipdk_content_width', 0 );

/**
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
function ipdk_widgets_init() {
	register_sidebar(
		array(
			'name'          => esc_html__( 'Sidebar', 'ipdk' ),
			'id'            => 'sidebar-1',
			'description'   => esc_html__( 'Add widgets here.', 'ipdk' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'ipdk_widgets_init' );

/**
 * Enqueue scripts and styles.
 */
function ipdk_scripts() {
	wp_enqueue_style( 'ipdk-fonts', 'https://fonts.googleapis.com/css2?family=Yanone+Kaffeesatz:wght@200..700&family=Rubik:wght@300;400;500;600&display=swap', array(), null );
    function choose_file_type() {
        if (in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1'))) {
            $file_type = '';
        } else {
            $file_type = '.min';
        }
        return $file_type;
    }

	wp_enqueue_style( 'ipdk-style', get_template_directory_uri() . '/style' . choose_file_type() . '.css', array(),202329121 );
	wp_style_add_data( 'ipdk-style', 'rtl', 'replace' );

	wp_enqueue_script( 'ipdk-navigation',get_template_directory_uri() . '/js/navigation' . choose_file_type() . '.js', array(), _S_VERSION, true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
	wp_enqueue_script('ipdk-modal', 'https://cdnjs.cloudflare.com/ajax/libs/lity/2.4.1/lity.min.js', array('jquery'), '202329122', true);
	wp_enqueue_script( 'ipdk-sliders', '//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js', array('jquery'), '20240301', true );


    $jtime = filemtime(get_stylesheet_directory() . '/js/custom.min.js');
    wp_enqueue_script('ipdk-custom-scripts', get_template_directory_uri() . '/js/custom' . choose_file_type() . '.js', array('jquery','ipdk-modal','ipdk-sliders'), $jtime, true);
    $site_parameters = array(
        'site_url' => get_site_url(),
        'theme_directory' => get_template_directory_uri()
    );
    wp_localize_script('ipdk-custom-scripts', 'SiteParameters', $site_parameters);

	//wpbdp styles
	wp_enqueue_style( 'single-listing-styles', get_template_directory_uri() . '/css/pages/single-listing.min.css', array(), '20240901', 'screen', );
}
add_action( 'wp_enqueue_scripts', 'ipdk_scripts', 5 );

function my_theme_enqueue_styles() {
	// Dequeue your theme's existing stylesheet if it's already enqueued
	wp_dequeue_style('ipdk-style');

	// Re-enqueue your theme's stylesheet, now with the plugin's stylesheet as a dependency
	wp_enqueue_style('ipdk-style', get_stylesheet_uri(), array('wpbdp-base-css-css', 'um_styles-css', 'um_default_css-css'));
}
add_action('wp_enqueue_scripts', 'my_theme_enqueue_styles', 50);


/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Functions which enhance the theme by hooking into WordPress.
 */
require get_template_directory() . '/inc/template-functions.php';

/**
 * Add custom content for Ultimate Member Account page
 */
require get_template_directory() . '/inc/um-account-page-custom-tabs.php';



/**
 * Load Jetpack compatibility file.
 */
if ( defined( 'JETPACK__VERSION' ) ) {
	require get_template_directory() . '/inc/jetpack.php';
}
function add_container_start() {
	echo '<div class="container">';
}
add_action('wpbdp_before_submit_listing_page', 'add_container_start', 20);
function add_container_end() {
	echo '</div">';
}
add_action('wpbdp_after_submit_listing_page', 'add_container_end', 20);