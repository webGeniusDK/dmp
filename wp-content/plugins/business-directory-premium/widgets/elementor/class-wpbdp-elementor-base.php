<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Base class for elementor widget
 * This holds the base functions and variables required for the plugin widgets
 * All widgets can extend this base class
 *
 * @since 5.2
 */
abstract class WPBDP_Elementor_Base extends \Elementor\Widget_Base {

	/**
	 * The shortcode class
	 * As most widgets are extensions of the shortcodes, we can use the same functions
	 *
	 * @since 5.2
	 *
	 * @var WPBDP__Shortcodes
	 */
	private $shortcode_class = null;

	/**
	 * Widget constructor
	 * Set up the shortcode class
	 */
	public function __construct( $data = array(), $args = null ) {
		parent::__construct( $data, $args );
		if ( ! class_exists( 'WPBDP__Shortcodes' ) ) {
			require_once WPBDP_INC . 'class-shortcodes.php';
		}
		if ( ! function_exists( 'wpbdp_listing_thumbnail' ) ) {
			require_once WPBDP_INC . 'helpers/functions/templates-ui.php';
		}
		$this->shortcode_class = new WPBDP__Shortcodes();
	}

	/**
	 * The Widget category
	 */
	public function get_categories() {
		return array( 'business-directory' );
	}

	protected function register_controls() {

		$this->start_controls_section(
			'content_section',
			array(
				'label' => $this->get_title(),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		if ( class_exists( '\ElementorPro\Modules\QueryControl\Module' ) ) {
			$this->add_control(
				'post_id_slug',
				array(
					'label'        => __( 'Listing', 'wpbdp-pro' ),
					'type'         => \ElementorPro\Modules\QueryControl\Module::QUERY_CONTROL_ID,
					'default'      => 0,
					'options'      => array(
						0 => __( 'Current Listing', 'wpbdp-pro' ),
					),
					'label_block'  => true,
					'autocomplete' => array(
						'object'  => \ElementorPro\Modules\QueryControl\Module::QUERY_OBJECT_POST,
						'display' => 'detailed',
						'query'   => array(
							'post_type' => 'wpbdp_listing',
						),
					),
					'placeholder'  => 'Current Listing',
					'description'  => __( 'Search for a listing. Leave blank to use current page id', 'wpbdp-pro' ),
				)
			);
		} else {
			$this->add_control(
				'post_id_slug',
				array(
					'label'       => __( 'Post ID or Slug', 'wpbdp-pro' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'placeholder' => '',
					'description' => __( 'The Post ID or Slug of the listing', 'wpbdp-pro' ),
				)
			);
		}

		// Load additional controls
		$this->additional_controls();

		$this->end_controls_section();
	}

	/**
	 * Add additional controls that may be used in the widget
	 * This is calls after the post control in the widget under `register_controls` class
	 *
	 * @since 5.2
	 */
	protected function additional_controls() {

	}

	/**
	 * Get the shortcode class
	 *
	 * @since 5.2
	 *
	 * @return WPBDP__Shortcodes
	 */
	public function get_shortcode_class() {
		return $this->shortcode_class;
	}

	/**
	 * Check if is listing
	 * This is to prevent fatal errors and rendering when nothing is selected and the widget is used in a template or page
	 * It should render when a listing id is passed ideally
	 *
	 * @since 5.2
	 *
	 * @return bool
	 */
	public function is_listing( $id ) {
		if ( is_integer( $id ) ) {
			return true;
		}

		$post_id = wpbdp_get_post_by_id_or_slug( $id, 'slug', 'id' );
		return is_integer( $post_id );
	}
}
