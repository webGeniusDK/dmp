<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Elementor widget to display a single listing from the directory (by slug or ID)
 *
 * @since 5.1
 */
class WPBDP_Elementor_Listing extends WPBDP_Elementor_Base {

	public function get_name() {
		return 'businessDirectoryListing';
	}

	public function get_title() {
		return __( 'Business Directory Listing', 'wpbdp-pro' );
	}

	public function get_icon() {
		return 'eicon-post-info';
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$id       = empty( $settings['post_id_slug'] ) ? get_the_ID() : $settings['post_id_slug'];
		if ( $this->is_listing( $id ) ) {
			$html = $this->get_shortcode_class()->sc_single_listing( array( 'id' => $id ) );
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
