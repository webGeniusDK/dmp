<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Elementor widget to show extra listings section for a single listing
 *
 * @since 5.1
 */
class WPBDP_Elementor_Images extends WPBDP_Elementor_Base {

	public function get_name() {
		return 'businessDirectoryImages';
	}

	public function get_title() {
		return __( 'Business Directory Images', 'wpbdp-pro' );
	}

	public function get_icon() {
		return 'eicon-image';
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$id       = empty( $settings['post_id_slug'] ) ? get_the_ID() : $settings['post_id_slug'];
		if ( $this->is_listing( $id ) ) {
			$html = $this->get_shortcode_class()->single_listing_images( array( 'id' => $id ) );
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
