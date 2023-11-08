<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Elementor widget to include an extra section from a listings page like the contact form, comments, reviews, and more.
 *
 * @since 5.1
 */
class WPBDP_Elementor_Section extends WPBDP_Elementor_Base {

	public function get_name() {
		return 'businessDirectorySection';
	}

	public function get_title() {
		return __( 'Business Directory Section', 'wpbdp-pro' );
	}

	public function get_icon() {
		return 'eicon-section';
	}

	protected function additional_controls() {

		$this->add_control(
			'sections',
			array(
				'label'       => __( 'Section', 'wpbdp-pro' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'placeholder' => '',
				'description' => __( "A comma-separated list of sections from a listing to show. Options include ‘comments', ‘contact_form', ‘googlemaps', ‘reviews' and more", 'wpbdp-pro' ),
			)
		);
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$id       = empty( $settings['post_id_slug'] ) ? get_the_ID() : $settings['post_id_slug'];
		$sections = $settings['sections'];
		if ( $this->is_listing( $id ) ) {
			$html = $this->get_shortcode_class()->single_listing_section(
				array(
					'id'      => $id,
					'section' => $sections,
				)
			);
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
