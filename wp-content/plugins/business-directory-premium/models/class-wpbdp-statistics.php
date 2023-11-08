<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Listing statistic model
 * Handles database operations of the listing statistics
 *
 * @since 5.2
 */
class WPBDP_Statistics {

	/**
	 * Class instance
	 *
	 * @var null
	 */
	private static $instance = null;

	/**
	 * Return the class instance
	 *
	 * @return WPBDP_Statistics
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}


	/**
	 * Save or update a listing view
	 *
	 * @param int    $listing_id - the listing it
	 * @param string $ip - the user ip
	 */
	public function save_listing_view( $listing_id, $ip ) {
		global $wpdb;

		if ( self::is_bot() ) {
			return;
		}

		$today = gmdate( 'Y-m-d' );
		$args  = array( $listing_id, $today );

		if ( ! defined( 'WPBDP_LISTING_TRACK_IP' ) || ! WPBDP_LISTING_TRACK_IP ) {
			$ip = null;
		}
		if ( ! empty( $ip ) ) {
			$ip_query = ' AND `ip` = %s';
			$args[]   = $ip;
		} else {
			$ip_query = ' AND `ip` IS NULL';
		}

		$sql = "SELECT `id` FROM {$this->get_table_name()} WHERE `listing_id` = %d AND `date_created` > %s" . $ip_query;

		$prepared_sql = $wpdb->prepare( $sql, ...$args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$id = WPBDP_Utils::check_cache(
			array(
				'cache_key' => $listing_id,
				'group'     => 'wpbdp_statistics',
				'query'     => $prepared_sql,
				'type'      => 'get_var',
				'return'    => 'int',
			)
		);
		if ( $id ) {
			$this->_update( $id );
		} else {
			$this->_save( $listing_id, $ip );
		}

		// Clear cache.
		WPBDP_Utils::cache_delete_group( 'wpbdp_statistics' );
	}

	/**
	 * Find most bots based on the user agent.
	 *
	 * @since 5.5
	 */
	private static function is_bot() {
		$user_agent = wpbdp_get_server_value( 'HTTP_USER_AGENT' );

		return preg_match( '/bot|crawl|slurp|spider|mediapartners/i', $user_agent );
	}

	/**
	 * Count views
	 *
	 * @param int    $listing_id - the listing id
	 * @param string $starting_date - the start date (Y-m-d)
	 * @param string $ending_date - the end date (Y-m-d)
	 *
	 * @return int - totol views based on parameters
	 */
	public function count_views( $listing_id, $starting_date = null, $ending_date = null ) {
		return $this->_count( $listing_id, $starting_date, $ending_date );
	}

	/**
	 * Delete stats by listing id
	 *
	 * @param int $listing_id - the form id
	 */
	public function delete_by_listing_id( $listing_id ) {
		global $wpdb;
		$wpdb->delete( $this->get_table_name(), array( 'listing_id' => $listing_id ) );

		// Clear cache.
		WPBDP_Utils::cache_delete_group( 'wpbdp_statistics' );
	}


	/**
	 * Return statistics table name
	 *
	 * @return string
	 */
	private function get_table_name() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wpbdp_statistics';
		return $table_name;
	}

	/**
	 * Save Data to database
	 *
	 * @param int    $listing_id - the listing id
	 * @param string $ip - the user ip
	 */
	private function _save( $listing_id, $ip ) {
		global $wpdb;

		$wpdb->insert(
			$this->get_table_name(),
			array(
				'listing_id'   => $listing_id,
				'ip'           => $ip,
				'views'        => 1,
				'date_created' => current_time( 'mysql', 1 ),
			)
		);
	}

	/**
	 * Update views
	 *
	 * @param int $id - stat id
	 */
	private function _update( $id ) {
		global $wpdb;
		$table_name = $this->get_table_name();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name} SET `views` = `views`+1, `date_updated` = %s WHERE `id` = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'mysql', 1 ),
				$id
			)
		);
	}

	/**
	 * Count data
	 *
	 * @param int    $listing_id - the listing id
	 * @param string $starting_date - the start date (Y-m-d)
	 * @param string $ending_date - the end date (Y-m-d)
	 *
	 * @return int - totol counts based on parameters
	 */
	private function _count( $listing_id, $starting_date = null, $ending_date = null ) {
		global $wpdb;
		$date_query = $this->_generate_date_query( $starting_date, $ending_date );
		$sql        = "SELECT SUM(`views`) FROM {$this->get_table_name()} WHERE `listing_id` = %d $date_query";
		$query      = $wpdb->prepare( $sql, $listing_id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$counts     = WPBDP_Utils::check_cache(
			array(
				'cache_key' => $listing_id,
				'group'     => 'wpbdp_statistics',
				'query'     => $query,
				'type'      => 'get_var',
				'return'    => 'int',
			)
		);
		return $counts ? $counts : 0;
	}


	/**
	 * Generate the date query
	 *
	 * @param string $starting_date - the start date (dd-mm-yyy)
	 * @param string $ending_date - the end date (dd-mm-yyy)
	 *
	 * @return string $date_query
	 */
	private function _generate_date_query( $starting_date = null, $ending_date = null ) {
		global $wpdb;

		$date_query = '';

		if ( ! empty( $starting_date ) ) {
			$date_query .= $wpdb->prepare( 'AND date_created >= %s', $starting_date );
		}

		if ( ! empty( $ending_date ) ) {
			$date_query .= $wpdb->prepare( ' AND date_created <= %s', $ending_date );
		}

		return $date_query;
	}
}
