<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Dashboard widget
 *
 * @since 5.3
 */
class WPBDP_Dashboard {

	/**
	 * Class constructor
	 */
	public function __construct() {
		if ( is_admin() ) {
			add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
		}
	}

	/**
	 * Add dashboard widgets
	 */
	public function add_dashboard_widget() {
		wp_add_dashboard_widget( 'wpbdp_recent_listings', __( 'Recent Directory Listings', 'wpbdp-pro' ), array( $this, 'recent_listings' ) );
	}

	/**
	 * Generate widget output
	 */
	public function recent_listings() {
		$recent_listings = wp_get_recent_posts(
			array(
				'numberposts' => 4,
				'post_status' => array( 'publish', 'pending_payment', 'complete', 'incomplete' ),
				'post_type'   => WPBDP_POST_TYPE,
			)
		);
		if ( $recent_listings ) {
			include dirname( dirname( __FILE__ ) ) . '/views/widgets/dashboard-widget.php';
		}
	}
}
