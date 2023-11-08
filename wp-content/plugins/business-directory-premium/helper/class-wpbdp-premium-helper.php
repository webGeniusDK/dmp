<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper
 *
 * @since 5.2
 */
class WPBDP_Premium_Helper {

	/**
	 * Check if the current user is the listing owner
	 *
	 * @param WP_User|bool $user - the current user
	 *
	 * @return bool|int
	 */
	public static function current_user_is_listing_owner( $user = false ) {
		global $wp_the_query;

		$add_admin = false === $user;
		if ( ! $user ) {
			$user = wp_get_current_user();
		}
		if ( ! $user || ! $user->exists() ) {
			return false;
		}
		$current_object = $wp_the_query->get_queried_object();
		$is_listing     = ! empty( $current_object ) && ! empty( $current_object->post_type ) && $current_object->post_type === WPBDP_POST_TYPE;
		if ( ! $is_listing ) {
			return false;
		}

		$is_listing_owner = $current_object->post_author && $current_object->post_author == $user->ID;
		if ( $is_listing_owner ) {
			return $current_object->ID;
		}

		if ( $add_admin && current_user_can( 'administrator' ) ) {
			return $current_object->ID;
		}

		return false;
	}
}
