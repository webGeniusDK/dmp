<?php
// Do not allow direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Represents a ZIP code database import in progress.
 *
 * @since 3.3
 */
class WPBDP_ZIPDBImport {

	const BATCH_SIZE   = 5000;
	const INSERT_BATCH = 50;

	/* Database info. */
	private $filepath = '';
	private $file     = null;

	/* Progress. */
	private $processed = 0;
	private $started   = null;
	private $updated   = null;

	public static function &get_current_import() {
		$data = get_option( 'wpbdp-zipcodesearch-db-import', null );

		try {
			if ( $data && is_array( $data ) ) {
				$import = new self( $data['filepath'] );
				return $import;
			}
		} catch ( Exception $e ) {
			wpbdp_admin_message( _x( 'A previous database import was corrupted. All import information was deleted.', 'import', 'wpbdp-zipcodesearch' ), 'error' );
			delete_option( 'wpbdp-zipcodesearch-db-import' );
			$import = null;
		}

		return $import;
	}

	public static function &create( $dbfile ) {
		if ( self::get_current_import() ) {
			$import = null;
			return $import;
		}
		try {
			$import = new self( $dbfile );
		} catch ( Exception $e ) {
			wpbdp_admin_message( $e->getMessage(), 'error' );
			$import = null;
		}
		return $import;
	}

	private function __construct( $filepath ) {
		$this->filepath = $filepath;
		try {
			$this->file = new ZIPCodeDB_File( $filepath );
		} catch ( Exception $e ) {
			throw new Exception( $e->getMessage() );
		}

		$data = get_option( 'wpbdp-zipcodesearch-db-import', null );
		if ( $data ) {
			if ( $data['filepath'] != $this->filepath ) {
				throw new Exception( _x( 'Can not import two different DB files at the same time.', 'import', 'wpbdp-zipcodesearch' ) );
			}

			$this->started   = $data['started'];
			$this->updated   = $data['updated'];
			$this->processed = $data['processed'];
		} else {
			$this->started   = time();
			$this->updated   = time();
			$this->processed = 0;
		}

		$this->persist();
	}

	private function persist() {
		update_option(
			'wpbdp-zipcodesearch-db-import',
			array(
				'filepath'  => $this->filepath,
				'started'   => $this->started,
				'updated'   => $this->updated,
				'processed' => intval( $this->processed ),
			)
		);
	}

	public function get_databases() {
		return $this->file->get_databases();
	}

	public function get_database_name() {
		return $this->file->get_database_name();
	}

	public function get_database_date() {
		return $this->file->get_date();
	}

	public function get_filepath() {
		return $this->filepath;
	}

	public function get_imported() {
		return $this->processed;
	}

	public function get_total_items() {
		return $this->file->get_no_items();
	}

	public function cleanup() {
		$this->file->close();

		// Remove original file when done.
		if ( $this->get_progress( 'r' ) == 0 ) {
			@unlink( $this->filepath );
		}
	}

	public function cancel() {
		// Cancels an import.
		$module = WPBDP_ZIPCodeSearchModule::instance();

		foreach ( $this->file->get_databases() as $d ) {
			$module->delete_database( $d );
		}

		delete_option( 'wpbdp-zipcodesearch-db-import' );

		$this->cleanup();

		if ( ! $this->file->is_sqlite() ) {
			@unlink( $this->filepath );
		}
	}

	public function get_progress( $format = '%' ) {
		$processed = $this->get_imported();
		$items     = $this->get_total_items();

		switch ( $format ) {
			case '%': // As a percentage.
				return round( 100 * $this->get_progress( 'f' ) );
				break;
			case 'f': // As a fraction.
				return round( $processed / $items, 3 );
				break;
			case 'n': // As # of items imported.
				return $processed;
				break;
			case 'r': // As # of items remaining.
				return max( 0, $items - $processed );
				break;
		}

		return 0;
	}

	public function make_progress() {
		global $wpdb;

		if ( $this->processed == 0 ) {
			$module = WPBDP_ZIPCodeSearchModule::instance();

			foreach ( $this->file->get_databases() as $d ) {
				$module->delete_database( $d );
			}
		}

		$sql_items = '';
		foreach ( $this->file->get_items( $this->processed, $this->processed + self::BATCH_SIZE - 1 ) as $k => $item ) {
			$sql_items .= $wpdb->prepare( '(%s, %s, %s, %s, %s, %s)', $item[0], $item[1], $item[2], $item[3], remove_accents( $item[4] ), remove_accents( $item[5] ) ) . ',';
			$this->processed++;

			if ( ( ( $k + 1 ) % self::INSERT_BATCH == 0 ) || ( $k == $this->file->get_no_items() - 1 ) ) {
				$this->insert_items( rtrim( $sql_items, ',' ) );
				$sql_items = '';
			}
		}

		$this->updated = time();
		$this->persist();

		if ( $this->get_progress( 'r' ) == 0 ) {
			// Add database to list.
			$databases = get_option( 'wpbdp-zipcodesearch-db-list', array() );

			foreach ( $this->get_databases() as $d ) {
				$databases[ $d ] = $this->get_database_date();
			}
			update_option( 'wpbdp-zipcodesearch-db-list', $databases );

			// Remove original file.
			@unlink( $this->filepath );
			update_option( 'wpbdp-zipcodesearch-db-nomigrate', 1 );

			// Invalidate cache items with NULL zip since this database might bring the required information.
			$module = WPBDP_ZIPCodeSearchModule::instance();
			$module->delete_listing_cache( 'NULL' );

			// Delete import info.
			delete_option( 'wpbdp-zipcodesearch-db-import' );
		}
	}

	private function insert_items( $sql_items ) {
		global $wpdb;

		$sql    = "INSERT IGNORE INTO {$wpdb->prefix}wpbdp_zipcodes(country, zip, latitude, longitude, city, state) VALUES {$sql_items};";
		$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( false == $result && $wpdb->last_error ) {
			$message = __( 'There was an error trying to insert items into the database: <database-error>', 'wpbdp-zipcodesearch' );
			$message = str_replace( '<database-error>', $wpdb->last_error, $message );

			throw new Exception( $message );
		}

		if ( 0 == $result ) {
			throw new Exception( __( 'No items were inserted into the database.', 'wpbdp-zipcodesearch' ) );
		}

		return true;
	}
}
