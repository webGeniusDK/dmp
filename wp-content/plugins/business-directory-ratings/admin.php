<?php

class BusinessDirectory_RatingsModuleAdmin {

	public function __construct() {
		add_action( 'admin_init', array( $this, '_add_admin_metabox' ) );
		add_action( 'wpbdp_admin_menu', array( $this, '_admin_menu' ) );
		add_filter( 'wpbdp_tab_content', array( &$this, 'add_menu_icon' ) );

		add_action( 'wp_ajax_wpbdp-ratings-add', array( &$this, 'ajax_add_rating' ) );
	}

	/*
	 * Admin.
	 */

	public function _admin_menu( $menu ) {
		add_submenu_page(
			$menu,
			__( 'Ratings', 'wpbdp-ratings' ),
			__( 'Ratings', 'wpbdp-ratings' ),
			'administrator',
			'wpbdp-ratings-pending-review',
			array( $this, '_admin_ratings_review' )
		);
	}

	/**
	 * Add an icon in the settings menu.
	 *
	 * @since 5.3
	 * @param array $tabs
	 *
	 * @return array
	 */
	public function add_menu_icon( $tabs ) {
		$plugin_file = dirname( __FILE__ ) . '/business-directory-ratings.php';
		$icon        = plugins_url( '/resources/images/star.svg', $plugin_file );
		$menus       = array( 'wpbdp-ratings-pending-review', 'ratings' );

		foreach ( $menus as $menu ) {
			if ( isset( $tabs[ $menu ] ) ) {
				$tabs[ $menu ]['icon_url'] = $icon;
			}
		}

		return $tabs;
	}

	public function _admin_ratings_review() {
		if ( isset( $_GET['action'] ) && isset( $_GET['id'] ) ) {
			global $wpdb;

			switch ( $_GET['action'] ) {
				case 'approve':
					$rating_id = intval( $_GET['id'] );
					$review = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpbdp_ratings WHERE id = %d", $rating_id ) );

					if ( $review ) {
						$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wpbdp_ratings SET approved = %d WHERE id = %d", 1, $rating_id ) );
						do_action( 'wpbdp_ratings_rating_approved', $review );
					}

					wpbdp()->admin->messages[] = __( 'The rating was approved.', 'wpbdp-ratings' );
					break;
				case 'delete':
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpbdp_ratings WHERE id = %d", intval( $_GET['id'] ) ) );
					wpbdp()->admin->messages[] = __( 'The rating was deleted.', 'wpbdp-ratings' );
					break;
			}
		}

		wpbdp_admin_header( array( 'echo' => true ) );
		wpbdp_admin_notices();

		echo '<div id="wpbdp-ratings-pending-review">';

		$table = new BusinessDirectory_RatingsReviewTable();
		$table->prepare_items();
		$table->views();

		?>
		<form id="posts-filter" method="get">
			<input type="hidden" name="page" value="wpbdp-ratings-pending-review" />
		<?php
		$table->search_box( __( 'Search', 'wpbdp-ratings' ), 'wpbdp-search' );
		$table->display();

		echo '</form>';
		echo '</div>';

		wpbdp_admin_footer( 'echo' );
	}

	public function _add_admin_metabox() {
		add_meta_box(
			'wpbdp-ratings',
			__( 'Listing Ratings', 'wpbdp-ratings' ),
			array( $this, '_ratings_metabox' ),
			'wpbdp_listing',
			'normal',
			'low'
		);
	}

	public function _ratings_metabox( $listing ) {
		$listing_id = $listing->ID;

		// WPML support.
		if ( ! empty( $GLOBALS['sitepress'] ) ) {
			global $sitepress;
			$def_lang = $sitepress->get_default_language();
			$listing_id = icl_object_id( $listing_id, WPBDP_POST_TYPE, true, $def_lang );
		}

		$reviews = wpbdp_ratings()->get_reviews( $listing_id );

		wpbdp_render_page(
			plugin_dir_path( __FILE__ ) . 'templates/admin-post-review.tpl.php',
			array( 'listing_id' => $listing_id ),
			true
		);

		wpbdp_render_page(
			plugin_dir_path( __FILE__ ) . 'templates/admin-ratings.tpl.php',
			array( 'reviews' => $reviews ),
			true
		);
	}

	public function ajax_add_rating() {
		if ( ! current_user_can( 'administrator' ) ) {
			die();
		}

		global $wpdb;

		$param  = array(
			'param'    => 'rating',
			'sanitize' => 'sanitize_textarea_field',
		);
		$rating = wpbdp_get_var( $param, 'post' );

		if ( ! $rating || empty( $rating['user_name'] ) || empty( $rating['rating'] ) ) {
			echo wp_json_encode(
				array(
					'errormsg' => __( 'Please fill in all required fields for the rating', 'wpbdp-ratings' ),
				),
			);
			wp_die();
		}

		$rating['user_id'] = 0;

		$user = get_user_by( 'login', $rating['user_name'] );
		if ( $user ) {
			$rating['user_id']   = $user->ID;
			$rating['user_name'] = '';
		}

		$rating['rating']     = intval( $rating['rating'] );
		$rating['approved']   = 1;
		$rating['created_on'] = current_time( 'mysql' );

		if ( ! wpbdp_get_option( 'ratings-allow-html' ) ) {
			$rating['comment'] = wp_filter_nohtml_kses( $rating['comment'] );
		} else {
			$rating['comment'] = wp_filter_post_kses( $rating['comment'] );
		}
		$rating['comment'] = wp_encode_emoji( $rating['comment'] );
		$response = array();

		$result = $wpdb->insert( $wpdb->prefix . 'wpbdp_ratings', $rating );
		if ( $result ) {
			$review = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpbdp_ratings WHERE id = %d", $wpdb->insert_id ) );
			if ( ! $review ) {
				echo wp_json_encode(
					array(
						'errormsg' => __( 'There was an error retrieving your rating', 'wpbdp-ratings' ),
					),
				);
				wp_die();
			}

			$response['ok']   = true;
			$response['html'] = wpbdp_render_page(
				plugin_dir_path( __FILE__ ) . 'templates/admin-rating-row.tpl.php',
				array( 'review' => $review )
			);
		} else {
			$response['errormsg'] = __( 'There was an error saving your rating', 'wpbdp-ratings' );
		}

		echo wp_json_encode( $response );

		wp_die();
	}

}
