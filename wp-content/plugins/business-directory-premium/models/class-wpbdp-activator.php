<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class WPBDP_Activator {

	const DB_VERSION = '1.0';

	/**
	 * Class instance
	 *
	 * @var null
	 */
	private static $instance = null;

	/**
	 * Return the class instance
	 *
	 * @return WPBDP_Activator
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function activate() {
		$current_version = get_option( 'wpbdp-db-premium-version' );

		if ( $current_version && version_compare( self::DB_VERSION, $current_version, '=' ) ) {
			return;
		}

		$this->update_database_schema();

		// Set the database version.
		update_option( 'wpbdp-db-premium-version', self::DB_VERSION );
	}

	/**
	 * Function called on plugin activation.
	 */
	public function update_database_schema() {
		global $wpdb;

		// https://core.trac.wordpress.org/ticket/33885.
		$max_index_length = 191;

		$table_schema = "CREATE TABLE {$wpdb->prefix}wpbdp_statistics (
            `id` bigint(20) PRIMARY KEY  AUTO_INCREMENT,
            `listing_id` bigint(20) NULL DEFAULT 0,
            `page_id` bigint(20) NULL DEFAULT 0,
            `ip` VARCHAR($max_index_length) default NULL,
            `views` mediumint(8) unsigned not null default 0,
            `form_usage` mediumint(8) unsigned not null default 0,
            `date_created` datetime NOT NULL default '0000-00-00 00:00:00',
            `date_updated` datetime NOT NULL default '0000-00-00 00:00:00',
            KEY `stat_listing_id` (`listing_id` ASC ),
            KEY `statistic_ip` (`ip`($max_index_length)),
            KEY `statistic_object` (`listing_id` ASC, `id` ASC),
            KEY `statistic_object_ip` (`listing_id` ASC, `id` ASC, `ip` ASC)
        ) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $table_schema );
	}
}
