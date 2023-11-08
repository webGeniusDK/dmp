<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Elementor widget for the edit and delete buttons for a single listing if the user has permission
 *
 * @since 5.2
 */
class WPBDP_Elementor_Buttons extends WPBDP_Elementor_Base {

	public function get_name() {
		return 'businessDirectoryButtons';
	}

	public function get_title() {
		return __( 'Business Directory Buttons', 'wpbdp-pro' );
	}

	public function get_icon() {
		return 'eicon-button';
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$id       = empty( $settings['post_id_slug'] ) ? get_the_ID() : $settings['post_id_slug'];
		if ( $this->is_listing( $id ) ) {
			$html = $this->get_shortcode_class()->single_listing_actions( array( 'id' => $id ) );

			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
