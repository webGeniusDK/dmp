<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Listing action
 * Handles various listing actions
 *
 * @since 5.2
 */
class WPBDP_Tracking {


	public function __construct() {
		add_filter( 'wpbdp_admin_directory_columns', array( $this, 'add_column' ) );
		add_action( 'wpbdp_admin_directory_column_views', array( $this, 'column_views' ) );

		add_action( 'wpbdp_register_settings', array( $this, 'register_settings' ) );

		add_action( 'delete_post', array( $this, 'listing_deleted' ), 10, 2 );

		add_action( 'wp', array( $this, 'track_views' ) );

		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 999 ); // Admin menu

		new WPBDP_MonsterInsights();
	}

	/**
	 * Add an admin column
	 *
	 * @param array $columns
	 *
	 * @return array $columns
	 */
	public function add_column( $columns ) {
		// Views for statistics.
		if ( wpbdp_get_option( 'listings-stats-enabled' ) ) {
			$columns['views'] = __( 'Views', 'wpbdp-pro' );
		}
		return $columns;
	}

	/**
	 * Return the column views
	 *
	 * @param int $post_id
	 */
	public function column_views( $post_id ) {
		$statistics = WPBDP_Statistics::get_instance();
		echo esc_html( $statistics->count_views( $post_id ) );
	}

	/**
	 * Register admin settings.
	 */
	public function register_settings() {
		wpbdp_register_settings_group( 'listings/stats', __( 'Statistics', 'wpbdp-pro' ), 'listings' );

		wpbdp_register_setting(
			array(
				'id'      => 'listings-stats-enabled',
				'type'    => 'checkbox',
				'name'    => __( 'Track Page Views', 'wpbdp-pro' ),
				'desc'    => __( 'Count the number of times a listing is viewed', 'wpbdp-pro' ),
				'default' => false,
				'group'   => 'listings/stats',
			)
		);

		wpbdp_register_setting(
			array(
				'id'           => 'listings-stats-admin-enabled',
				'type'         => 'checkbox',
				'name'         => __( 'Track Administrators', 'wpbdp-pro' ),
				'desc'         => __( 'Include Admins in the page view counts', 'wpbdp-pro' ),
				'default'      => false,
				'group'        => 'listings/stats',
				'requirements' => array( 'listings-stats-enabled' ),
			)
		);
	}

	/**
	 * Delete listing statistic
	 *
	 * @param int     $id - the post id.
	 * @param WP_POST $post - the post. Param added in WP 5.5.
	 */
	public function listing_deleted( $id, $post ) {
		if ( $post && $post->post_type === WPBDP_POST_TYPE ) {
			$statistics = WPBDP_Statistics::get_instance();
			$statistics->delete_by_listing_id( $id );
		}
	}

	/**
	 * Track views of a single listing.
	 */
	public function track_views() {
		if ( ! is_singular( WPBDP_POST_TYPE ) || ! wpbdp_get_option( 'listings-stats-enabled' ) ) {
			return;
		}

		$can_save = ! current_user_can( 'administrator' ) || wpbdp_get_option( 'listings-stats-admin-enabled' );
		if ( $can_save ) {
			$listing    = get_post();
			$ip         = WPBDP_IP_Helper::get_user_ip();
			$statistics = WPBDP_Statistics::get_instance();
			$statistics->save_listing_view( $listing->ID, $ip );
		}
	}

	/**
	 * Add admin bar
	 */
	public function admin_bar_menu() {
		if ( is_admin() || ! wpbdp_get_option( 'listings-stats-enabled' ) ) {
			return;
		}

		$owner_listing_id = WPBDP_Premium_Helper::current_user_is_listing_owner();
		if ( ! $owner_listing_id ) {
			return;
		}

		global $wp_admin_bar;
		if ( ! method_exists( $wp_admin_bar, 'add_menu' ) ) {
			return;
		}

		$args = array(
			'id'    => 'wpbdp_frontend_statistics',
			'title' => sprintf(
				/* translators: %s: icon */
				esc_html__( '%s Statistics', 'wpbdp-pro' ),
				'<span class="ab-icon dashicons-before dashicons-chart-bar"></span>'
			),
			'href'  => '#',
			'meta'  => array(
				'class' => 'wpbdp_frontend_statistics',
			),
		);

		$statistics = WPBDP_Statistics::get_instance();
		$wp_admin_bar->add_menu( $args );

		$args = array(
			'parent' => 'wpbdp_frontend_statistics',
			'id'     => 'wpbdp_frontend_statistics_details',
			'title'  => sprintf(
				/* translators: %s: div div counts */
				esc_html__( '%1$sListing Views%2$s %3$s', 'wpbdp-pro' ),
				'<div class="wpbdp-statistic-title">',
				'</div>',
				'<div class="counts">' . $statistics->count_views( $owner_listing_id ) . '</div>'
			),
			'href'   => '',
			'meta'   => array(
				'class' => 'wpbdp_frontend_statistic_detail',
			),
		);
		$wp_admin_bar->add_node( $args );
	}
}
