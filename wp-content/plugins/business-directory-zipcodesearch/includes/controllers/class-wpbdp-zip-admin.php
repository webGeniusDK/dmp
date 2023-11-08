<?php
// Do not allow direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WPBDP_ZipCodeSearchModule_Admin
 */
class WPBDP_ZipCodeSearchModule_Admin {

	private $module = null;

	public function __construct( &$module ) {
		$this->module = $module;

		add_action( 'admin_notices', array( &$this, 'admin_notices' ) );
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_wpbdp-zipcodesearch-import', array( &$this, 'import_ajax' ) );
		add_action( 'wp_ajax_wpbdp-zipcodesearch-rebuildcache', array( &$this, 'ajax_rebuild_cache' ) );
	}

	public function admin_menu( $menu_id ) {
		add_submenu_page(
			null,
			__( 'Import ZIP code Database', 'wpbdp-zipcodesearch' ),
			__( 'Import ZIP code Database', 'wpbdp-zipcodesearch' ),
			'administrator',
			'wpbdp-zipcodesearch-importdb',
			array( &$this, 'import_page' )
		);
	}

	public function enqueue_scripts() {
		$version = $this->module->get_version();
		$url     = $this->module->get_plugin_url();

		wp_register_script(
			'wpbdp-zipcodesearch-js',
			$url . 'resources/zipcodesearch.js',
			array(
				'jquery',
				'jquery-ui-autocomplete',
			),
			$version
		);

		wp_enqueue_style(
			'wpbdp-zipcodesearch-admin-css',
			$url . 'resources/admin.min.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'wpbdp-zipcodesearch-admin-js',
			$url . 'resources/admin.min.js',
			array( 'wpbdp-zipcodesearch-js' ),
			$version
		);

		wp_localize_script(
			'wpbdp-zipcodesearch-admin-js',
			'wpbdpL10n',
			array(
				'start_import'  => _x( 'Start Import', 'import', 'wpbdp-zipcodesearch' ),
				'pause_import'  => _x( 'Pause Import', 'import', 'wpbdp-zipcodesearch' ),
				'resume_import' => _x( 'Resume Import', 'import', 'wpbdp-zipcodesearch' ),
			)
		);
	}

	public function admin_notices() {
		if ( ! current_user_can( 'administrator' ) ) {
			return;
		}

		$page = wpbdp_get_var( array( 'param' => 'page' ) );

		if ( 'wpbdp-zipcodesearch-importdb' === $page ) {
			return;
		}

		if ( ! $this->module->check_db() ) {
			if ( WPBDP_ZIPDBImport::get_current_import() ) {
				wpbdp_admin_message(
					printf(
						/* translators: %1$s open link, %2$s close link */
						esc_html__( 'Business Directory has detected an unfinished ZIP code database import. Please go to %1$sImport ZIP code database%2$s and either resume or cancel the import. If you are aware the import is in progress you can ignore this message.', 'wpbdp-zipcodesearch' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=wpbdp-zipcodesearch-importdb' ) ) . '">',
						'</a>'
					),
					'error'
				);
			} else {
				wpbdp_admin_message(
					sprintf(
						/* translators: %1$s open link, %2$s close link */
						esc_html__( 'Business Directory ZIP Search is active, but no valid ZIP code database available. You must first download and configure a ZIP code database for your region. Please %1$simport a ZIP code database file%2$s.', 'wpbdp-zipcodesearch' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=wpbdp-zipcodesearch-importdb' ) ) . '">',
						'</a>'
					),
					'error'
				);
			}

			return;
		}

		if ( ! $this->module->is_cache_valid() ) {
			wpbdp_admin_message(
				sprintf(
					/* translators: %1$s open link, %2$s close link */
					esc_html__( 'Settings for Business Directory - ZIP Code Search Module have been recently changed and a cache rebuild is needed. Go to %1$sZIP Search settings%2$s and click "Rebuild Cache". Not doing this results in slow searches.', 'wpbdp-zipcodesearch' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=wpbdp_settings&tab=listings&subtab=search_settings#wpbdp-settings-zipsearch-cache-status' ) ) . '">',
					'</a>'
				),
				'notice-error is-dismissible',
				array( 'dismissible-id' => 'zip_rebuild' )
			);
		}
	}

	/*
	 * Settings.
	 */
	public function manage_databases() {
		$db = wpbdp_get_var( array( 'param' => 'deletedb' ) );
		if ( $db ) {
			$this->module->delete_database( $db );
		}

		$databases = $this->module->get_installed_databases();

		if ( ! $databases ) {
			esc_html_e( 'No valid ZIP code database found.', 'wpbdp-zipcodesearch' );
			echo '<br />';
			printf(
				/* translators: %1$s open link, %2$s close link */
				esc_html__( 'Go to %1$sImport ZIP code database%2$s and follow installation instructions.', 'wpbdp-zipcodesearch' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=wpbdp-zipcodesearch-importdb' ) ) . '">',
				'</a>'
			);
			return;
		}

		echo '<div class="wpbdp-settings-multicheck-options wpbdp-grid">';
		foreach ( $this->module->get_supported_databases() as $dbid => $dbdata ) {
			echo '<div class="dbline wpbdp-settings-multicheck-option wpbdp-half">';
			$html_id = 'wpbdp-settings-multicheck-option-no-' . $dbid;
			printf(
				'<input type="checkbox" disabled="disabled" id="%s" %s />',
				esc_attr( $html_id ),
				array_key_exists( $dbid, $databases ) ? 'checked="checked"' : ''
			);

			echo ' <label for="' . esc_attr( $html_id ) . '">' . esc_html( $dbdata[0] ) . '</label> ';

			if ( isset( $databases[ $dbid ] ) ) {
				printf( '(ver %s)', esc_html( $databases[ $dbid ] ) );

				if ( version_compare( $databases[ $dbid ], $dbdata[1], '<' ) ) {
					printf(
						'<a href="%s" class="update-available" target="_blank" rel="noopener">%s</a>',
						'https://businessdirectoryplugin.com/zip-databases/',
						esc_html__( 'Update available!', 'wpbdp-zipcodesearch' )
					);
				}

				echo '<span class="delete-db">';
				echo '<span class="dashicons dashicons-no"></span>';
				printf(
					' <a href="%s">%s</a>',
					esc_url( add_query_arg( 'deletedb', $dbid ) ),
					sprintf(
						/* translators: %s database name */
						esc_html__( 'Delete %s database', 'wpbdp-zipcodesearch' ),
						'<i>' . esc_html( $dbdata[0] ) . '</i>'
					)
				);
				echo '</span>';
			}
			echo '</div>';
		}

		echo '</div>';
		echo '<br />';
		printf(
			/* translators: %1$s open link, %2$s close link */
			esc_html__( 'To install additional databases go to %1$sImport ZIP code database%2$s.', 'wpbdp-zipcodesearch' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=wpbdp-zipcodesearch-importdb' ) ) . '">',
			'</a>'
		);
	}

	public function cache_status() {
		echo '<div class="zipcodesearch-cache">';

		if ( $this->module->is_cache_valid() ) {
			echo '<span class="status ok">' . esc_html__( 'OK', 'wpbdp-zipcodesearch' ) . '</span>';
		} else {
			echo '<span class="status notok"><span class="msg">' . esc_html__( 'Invalid cache. Please rebuild.', 'wpbdp-zipcodesearch' ) . '</span><span class="progress"></span></span><br />';
			printf(
				'<a href="%s" class="button rebuild-cache">%s</a>',
				esc_url( add_query_arg( 'action', 'wpbdp-zipcodesearch-rebuildcache', admin_url( 'admin-ajax.php' ) ) ),
				esc_html__( 'Rebuild cache', 'wpbdp-zipcodesearch' )
			);
		}

		echo '</div>';
	}

	public function ajax_rebuild_cache() {
		global $wpdb;

		$response               = array();
		$response['done']       = false;
		$response['status']     = '';
		$response['statusText'] = '';

		$field_id = intval( wpbdp_get_option( 'zipcode-field', 0 ) );

		if ( ! $field_id ) {
			$response['done']       = true;
			$response['status']     = 'fail';
			$response['statusText'] = __( 'Postal Code/Zip Code field is unassigned. Please assign it above and try again.', 'wpbdp-zipcodesearch' );

			echo wp_json_encode( $response );

			die();
		}

		$pending = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID from {$wpdb->posts} p WHERE p.post_type = %s AND p.ID NOT IN (SELECT zc.listing_id FROM {$wpdb->prefix}wpbdp_zipcodes_listings zc) LIMIT 50", WPBDP_POST_TYPE ) );

		if ( $pending ) {
			foreach ( $pending as $post_id ) {
				$this->module->cache_listing_zipcode( $post_id );
			}
		} else {
			$response['done'] = true;
		}

		$remaining = max( 0, intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", WPBDP_POST_TYPE ) ) ) - intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpbdp_zipcodes_listings" ) ) );
		/* translators: %d count */
		$response['statusText'] = sprintf( _x( 'Please wait. Rebuilding cache... %d listings remaining.', 'cache', 'wpbdp-zipcodesearch' ), $remaining );

		echo wp_json_encode( $response );

		die();
	}

	/*
	 * DB Import process.
	 */

	public function import_page() {
		$import = WPBDP_ZIPDBImport::get_current_import();

		if ( $import && isset( $_GET['cancel_import'] ) && $_GET['cancel_import'] == 1 ) {
			$import->cancel();
			$import = null;
			wpbdp_admin_message( _x( 'The import was canceled.', 'import', 'wpbdp-zipcodesearch' ) );
		}

		if ( isset( $_GET['nomigrate'] ) && $_GET['nomigrate'] == 1 ) {
			update_option( 'wpbdp-zipcodesearch-db-nomigrate', 1 );
		}

		$nomigrate        = get_option( 'wpbdp-zipcodesearch-db-nomigrate', 0 );
		$old_style_db     = $this->module->get_plugin_path() . 'db' . DIRECTORY_SEPARATOR . 'zipcodes.db';
		$upgrade_possible = ( ! $nomigrate && ! $import && file_exists( $old_style_db ) && is_readable( $old_style_db ) ) ? true : false;

		if ( $upgrade_possible && isset( $_GET['migrate'] ) && $_GET['migrate'] == 1 ) {
			$import = WPBDP_ZIPDBImport::create( $old_style_db );
		}

		if ( ! $import && isset( $_FILES['dbfile'] ) ) {
			$import = $this->handle_file_upload( $import );
		} elseif ( ! $import && isset( $_POST['uploaded_dbfile'] ) ) {
			$import = $this->handle_manual_upload( $import );
		}

		// Check "db/" directory for FTP/manually uploaded databases.
		$dbfiles = $this->find_uploaded_databases();

		$_SERVER['REQUEST_URI'] = remove_query_arg(
			array( 'cancel_import', 'migrate', 'nomigrate' ),
			wpbdp_get_server_value( 'REQUEST_URI' )
		);

		$path = $this->module->get_plugin_path();
		wpbdp_render_page(
			$path . 'templates/admin-import.tpl.php',
			array(
				'import'           => $import,
				'upgrade_possible' => $upgrade_possible,
				'dbpath'           => $path . 'db',
				'dbfiles'          => $dbfiles,
			),
			true
		);
	}

	/**
	 * Handle the file upload
	 *
	 * @since 5.3
	 */
	private function handle_file_upload( $import ) {
		if ( ! check_admin_referer( 'wpbdp_zipcode_file_upload' ) ) {
			wpbdp()->admin->messages[] = _x( 'You are not allowed to do this', 'import', 'wpbdp-zipcodesearch' );
			return $import;
		}

		if ( ! isset( $_FILES['dbfile']['name'] ) ) {
			wpbdp()->admin->messages[] = _x( 'Could not upload database file: Invalid file', 'import', 'wpbdp-zipcodesearch' );
			return $import;
		}

		$filename = sanitize_file_name( wp_unslash( $_FILES['dbfile']['name'] ) );
		if ( ! $this->validate_uploaded_file( $filename ) ) {
			wpbdp()->admin->messages[] = _x( 'Could not upload database file: Invalid file type', 'import', 'wpbdp-zipcodesearch' );
			return $import;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$upload = wpbdp_media_upload( $_FILES['dbfile'], false, false, array(), $upload_error );

		if ( ! $upload ) {
			/* translators: %s error message */
			wpbdp()->admin->messages[] = sprintf( _x( 'Could not upload database file: %s.', 'import', 'wpbdp-zipcodesearch' ), $upload_error );
			return $import;
		}

		$import = WPBDP_ZIPDBImport::create( $upload['file'] );

		if ( $import ) {
			wpbdp_admin_message( _x( 'Database file uploaded. Please proceed with the import.', 'import', 'wpbdp-zipcodesearch' ) );
		}

		return $import;
	}

	/**
	 * Local file manual upload
	 *
	 * @since 5.3
	 */
	private function handle_manual_upload( $import ) {
		if ( ! check_admin_referer( 'wpbdp_zipcode_manual_upload' ) ) {
			wpbdp()->admin->messages[] = _x( 'You are not allowed to do this', 'import', 'wpbdp-zipcodesearch' );
			return $import;
		}

		if ( ! isset( $_POST['uploaded_dbfile'] ) ) {
			return $import;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$uploaded_dbfile = sanitize_text_field( $_POST['uploaded_dbfile'] );
		if ( ! file_exists( $uploaded_dbfile ) || ! is_readable( $uploaded_dbfile ) ) {
			return $import;
		}
		$import = WPBDP_ZIPDBImport::create( $uploaded_dbfile );
		wpbdp_admin_message(
			sprintf(
				/* translators: %s file path */
				_x( 'Using database file "%s". Please proceed with the import.', 'import', 'wpbdp-zipcodesearch' ),
				basename( $import->get_filepath() )
			)
		);
		return $import;
	}

	/**
	 * Validate file extension for the uploaded file
	 *
	 * @param string $filename
	 *
	 * @since 5.3
	 */
	private function validate_uploaded_file( $filename ) {
		$allowed  = array( 'gz' ); // Allowed file extensions end with .gz extension.
		$ext = pathinfo( $filename, PATHINFO_EXTENSION );
		return in_array( $ext, $allowed );
	}

	private function find_uploaded_databases() {
		$dbfiles = array();

		$files = wpbdp_scandir( $this->module->get_plugin_path() . 'db' . DIRECTORY_SEPARATOR );
		foreach ( $files as &$filename ) {
			// Exclude php files as the directory has a index.php which causes a fatal error.
			$file_parts = pathinfo( $filename );
			if ( $file_parts['extension'] === 'php' || $file_parts['extension'] === 'csv' || $filename === '.DS_Store' ) {
				continue;
			}
			$dbfile = new ZIPCodeDB_File( $this->module->get_plugin_path() . 'db' . DIRECTORY_SEPARATOR . $filename );

			$dbnames = array();
			foreach ( $dbfile->get_databases() as $d ) {
				$dbnames[] = $this->module->get_db_name( $d );
			}

			$dbfiles[] = array(
				'filepath' => $dbfile->get_filepath(),
				'database' => implode( ', ', $dbnames ),
				'date'     => $dbfile->get_date(),
			);
			$dbfile->close();
		}

		return $dbfiles;
	}

	public function import_ajax() {
		$response = $this->get_current_import_and_make_progress();

		echo wp_json_encode( $response );
		die();
	}

	private function get_current_import_and_make_progress() {
		$response = array(
			'progress'   => 0,
			'finished'   => false,
			'statusText' => '',
			'error'      => '',
		);

		$import = WPBDP_ZIPDBImport::get_current_import();

		if ( ! $import ) {
			$response['error'] = $e->getMessage();

			return $response;
		}

		try {
			$import->make_progress();
			$import->cleanup();

			/* translators: %d item count */
			$response['statusText'] = sprintf( _x( 'Importing database... %d items remaining.', 'import', 'wpbdp-zipcodesearch' ), $import->get_progress( 'r' ) );
		} catch ( Exception $e ) {
			$response['error'] = $e->getMessage();
		}

		$response['progress']  = $import->get_progress( '%' );
		$response['finished']  = $import->get_progress( 'r' ) == 0 ? true : false;
		$response['processed'] = number_format( $import->get_progress( 'n' ) );

		return $response;
	}
}
