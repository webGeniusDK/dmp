<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Base elementor integration
 * This class handles Elemtor integration and loads all widgets
 * It only activates once the plugin is detected as active
 *
 * @since 5.2
 */
class WPBDP_Elementor {

	/**
	 * Main plugin constructor
	 */
	public function __construct() {

		// Load classes only after plugins are loaded
		// The class is already called once plugins are loaded
		$this->load_classes();
	}

	/**
	 * Checks if elementor is active and loads the classes
	 * This checks if the elementor action is called before loading the extensions
	 *
	 * @since 5.2
	 */
	public function load_classes() {
		add_filter( 'template_include', array( &$this, 'skip_bd_template' ), 12 ); // 12 = after Elementor.

		if ( did_action( 'elementor/loaded' ) ) {

			add_action( 'elementor/elements/categories_registered', array( $this, 'add_elementor_category' ) );
			add_action( 'elementor/widgets/widgets_registered', array( $this, 'init_widgets' ) );
		}

		add_filter( 'body_class', array( $this, 'preview_body_class' ) );
	}

	/**
	 * Allow an Elementor template to control the page.
	 *
	 * @since 5.3
	 */
	public function skip_bd_template( $template ) {
		global $wp_query;

		if ( ! $wp_query->wpbdp_our_query || ! is_singular( WPBDP_POST_TYPE ) ) {
			return $template;
		}

		$is_elementor = strpos( $template, '/elementor/' );
		if ( $is_elementor ) {
			add_filter( 'wpbdp_allow_template_override', '__return_false' );
		}

		return $template;
	}

	/**
	 * Create a new category that will be used for the widgets
	 *
	 * @since 5.2
	 */
	public function add_elementor_category( $elements_manager ) {

		// Add the business directory
		$elements_manager->add_category(
			'business-directory',
			array(
				'title' => __( 'Business Directory', 'wpbdp-pro' ),
				'icon'  => 'fas fa-address-card',
			)
		);
	}

	/**
	 * Initialize the widgets
	 *
	 * @since 5.2
	 */
	public function init_widgets() {

		\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new WPBDP_Elementor_Listing() );
		\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new WPBDP_Elementor_Details() );
		\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new WPBDP_Elementor_Images() );
		\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new WPBDP_Elementor_Section() );
		\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new WPBDP_Elementor_Buttons() );
		\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new WPBDP_Elementor_Socials() );
	}

	/**
	 * Add a class for the Elementor preview.
	 *
	 * @since 5.3
	 */
	public function preview_body_class( $class ) {
		global $post;

		$is_elementor = $post && $post->post_type === 'elementor_library';
		if ( $is_elementor ) {
			$class[] = 'single-wpbdp_listing';
		}

		return $class;
	}
}
