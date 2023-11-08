<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Elementor widget to show the info in the listings fields for a single listing. This does not include social fields.
 *
 * @since 5.1
 */
class WPBDP_Elementor_Details extends WPBDP_Elementor_Base {

	public function get_name() {
		return 'businessDirectoryDetails';
	}

	public function get_title() {
		return __( 'Business Directory Details', 'wpbdp-pro' );
	}

	public function get_icon() {
		return 'eicon-kit-details';
	}

	protected function additional_controls() {
		$this->add_control(
			'exclude',
			array(
				'label'       => __( 'Fields To Exclude', 'wpbdp-pro' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'placeholder' => '',
				'description' => __( "A comma-separated list of fields to exclude. Options include ‘social', any field id, or short key for a field.", 'wpbdp-pro' ),
			)
		);
	}


	protected function render() {
		$settings = $this->get_settings_for_display();
		$id       = empty( $settings['post_id_slug'] ) ? get_the_ID() : $settings['post_id_slug'];
		$exclude  = $settings['exclude'];
		if ( $this->is_listing( $id ) ) {
			$html = $this->get_shortcode_class()->single_listing_details(
				array(
					'id'      => $id,
					'exclude' => $exclude,
				)
			);
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
