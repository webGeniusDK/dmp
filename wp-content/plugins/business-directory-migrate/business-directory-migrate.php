<?php
/*
 * Plugin Name: Business Directory Plugin - Migrator
 * Plugin URI: https://businessdirectoryplugin.com
 * Description: Allows you to move the entire content of your directory (categories, tags, listings) plus all of your BD settings to a different server.  The plugin must be installed on BOTH servers to be properly used.
 * Version: 5.0.4
 * Author: Business Directory Team
 */

require_once( plugin_dir_path( __FILE__ ) . 'class-migration-export-run.php' );
require_once( plugin_dir_path( __FILE__ ) . 'class-migration-pack.php' );


class WPBDP_Migrate_Module {

    const REQUIRED_BD_VERSION = '5.0';

    private $export_url = '';

	public function __construct() {
		$this->version             = '5.0.4';
		$this->id                  = 'migrate';
		$this->file                = __FILE__;
		$this->title               = 'Migrator';
		$this->required_bd_version = self::REQUIRED_BD_VERSION;
	}

	public function get_version() {
		return $this->version;
	}

	public function init() {
        if ( ! defined( 'WPBDP_VERSION' ) || version_compare( WPBDP_VERSION, $this->required_bd_version, '<' ) ) {
            return;
        }

        add_action( 'wpbdp_admin_menu', array( &$this, 'menu_item' ) );
        add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_scripts' ) );

        add_action( 'wp_ajax_wpbdp-migrate-export-do_work', array( &$this, 'ajax_export' ) );
        add_action( 'wp_ajax_wpbdp-migrate-export-cleanup', array( &$this, 'ajax_export_cleanup' ) );

        add_action( 'wp_ajax_wpbdp-migrate-import-do_work', array( &$this, 'ajax_import' ) );
	}

	public function initialzie() {
		_deprecated_function( __METHOD__, '5.0.4' );
		$this->init();
	}

    public function menu_item( $parent ) {
        add_submenu_page( $parent,
                          'Migrate (Export)',
                          'Migrate (Export)',
                          'administrator',
                          'wpbdp-migrate-export',
                          array( &$this, 'export_page' ) );
        add_submenu_page( $parent,
                          'Migrate (Import)',
                          'Migrate (Import)',
                          'administrator',
                          'wpbdp-migrate-import',
                          array( &$this, 'import_page' ) );
    }

    public function enqueue_scripts( $hook ) {
        if ( ! empty( $_GET['page'] ) && 'wpbdp-migrate-export' == $_GET['page'] ) {
            wp_enqueue_style(
                'wpbdp-migrate-export-css',
                plugins_url( '/css/export.css', __FILE__ ),
                array(),
                $this->version
            );

            wp_enqueue_script(
                'wpbdp-migrate-export-js',
                plugins_url( '/js/export.js', __FILE__ ),
                array(),
                $this->version
            );
        }

        if ( ! empty( $_GET['page'] ) && 'wpbdp-migrate-import' == $_GET['page'] ) {
            wp_enqueue_style(
                'wpbdp-migrate-import-css',
                plugins_url( '/css/import.css', __FILE__ ),
                array(),
                $this->version
            );

            wp_enqueue_script(
                'wpbdp-migrate-import-js',
                plugins_url( '/js/import.js', __FILE__ ),
                array(),
                $this->version
            );
        }
    }

    public function setup_migration_dir( &$msg = null ) {
        $upload_dir = wp_upload_dir();

        if ( $upload_dir['error'] )
            return false;

        $migrations_dir = rtrim( $upload_dir['basedir'], DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . 'wpbdp-migrations';

        if ( ! is_dir( $migrations_dir ) && ! mkdir( $migrations_dir, 0777 ) ) {
            $msg = sprintf( __( 'Could not create working directory "%s". Please check permissions.', 'wpbdp-migrations' ), $migrations_dir );
            return false;
        }

        foreach ( array( 'import', 'export' ) as $i ) {
            $path = $migrations_dir . DIRECTORY_SEPARATOR . $i;

            if ( ! is_dir( $path ) && ! mkdir( $path, 0777 ) ) {
                $msg = sprintf( __( 'Could not create directory "%s". Please check permissions.', 'wpbdp-migrations' ), $path );
                return false;
            }
        }

        $this->export_url = untrailingslashit( $upload_dir['baseurl'] ) . '/wpbdp-migrations/export';

        return untrailingslashit( $migrations_dir );
    }

    // {{ Export.

    public function export_page() {
        $dir = $this->setup_migration_dir( $msg );

        if ( ! $dir )
            wpbdp_admin_message( $msg, 'error' );

        if ( isset( $_POST['action'] ) && 'do-import' == $_POST['action'] && ! empty( $_POST['parts'] ) ) {
            $parts_ = $_POST['parts'];

            try {
                $r = new WPBDP_Migration_Export_Run( $dir . DIRECTORY_SEPARATOR . 'export',
                                                     false,
                                                     $parts_ );

                $info = WPBDP_Migration_Export_Run::get_parts_info();
                $parts = array();

                foreach ( $r->get_parts() as $p )
                    $parts[ $p ] = $info[ $p ]['description'];

                return wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/progress.tpl.php',
                                          array( 'run' => $r,
                                                 'parts' => $parts ),
                                          true );
            } catch ( Exception $e ) {
                wpbdp_admin_message( $e->getMessage(), 'error' );
            }
        }

        return wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/setup.tpl.php',
                                  array( 'parts' => WPBDP_Migration_Export_Run::get_parts_info() ),
                                  true );
    }

    public function ajax_export() {
        $response = new WPBDP_Ajax_Response();

        if ( empty( $_POST['export_id'] ) || ! current_user_can( 'administrator' ) )
            $response->send_error();

        $dir = $this->setup_migration_dir( $msg );
        $basedir = $dir . DIRECTORY_SEPARATOR . 'export';

        try {
            $r = new WPBDP_Migration_Export_Run( $basedir, $_POST['export_id'] );
            $r->do_work();
        } catch ( Exception $e ) {
            $r->cleanup();
            $response->send_error( $e->getMessage() );
        }

        $response->add( 'done', false );
        $response->add( 'part', $r->get_current_part() );
        $response->add( 'parts_done', $r->get_parts_done() );
        $response->add( 'id', $r->get_id() );
        $response->add( 'status', $r->get_status_message() );

        if ( $r->finished() ) {
            $response->add( 'done', true );
            $response->add( 'zip', array( 'url' => str_replace( DIRECTORY_SEPARATOR,
                                                                '/',
                                                                str_replace( $basedir, $this->export_url, $r->get_zip_file() ) ),
                                          'filename' => basename( $r->get_zip_file() ),
                                          'filesize' => size_format( filesize( $r->get_zip_file() ) ) ) );
        }

        $response->send();
    }

    public function ajax_export_cleanup() {
        $response = new WPBDP_Ajax_Response();

        if ( empty( $_POST['export_id'] ) || ! current_user_can( 'administrator' ) )
            $response->send_error();

        $dir = $this->setup_migration_dir( $msg );
        $basedir = $dir . DIRECTORY_SEPARATOR . 'export';

        try {
            $r = new WPBDP_Migration_Export_Run( $basedir, $_POST['export_id'] );
            $r->cleanup();
        } catch ( Exception $e ) {
            $response->send_error();
        }

        $response->send();
    }

    // }}

    // {{ Import.
    public function _find_uploaded_exports() {
        $candidates = array();

        $dir = $this->setup_migration_dir() . DIRECTORY_SEPARATOR . 'import';
        $contents = wpbdp_scandir( $dir );

        foreach ( $contents as $c ) {
            if ( ! is_file( $dir . DIRECTORY_SEPARATOR . $c ) )
                continue;

            $path = $dir . DIRECTORY_SEPARATOR . $c;
            $info = WPBDP_Migration_Pack::obtain_info_for_file( $path );

            if ( ! $info )
                @unlink( $path );

            $candidates[] = $info;
        }

        return $candidates;
    }

    public function import_page() {
        $dir = $this->setup_migration_dir() . DIRECTORY_SEPARATOR . 'import';

        if ( isset( $_FILES['upload_file'] ) && UPLOAD_ERR_OK === $_FILES['upload_file']['error'] ) {
            $this->_handle_pack_upload();
        }

        if ( ! empty( $_POST['action'] ) && 'import' == $_POST['action'] && ! empty( $_POST['uploaded'] ) ) {
            $file = isset( $_POST['uploaded'] ) ? $_POST['uploaded'] : '';
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            $pack = null;

            try {
                $pack = new WPBDP_Migration_Pack( $path, true );

                $info = WPBDP_Migration_Export_Run::get_parts_info();
                $parts = array();

                foreach ( $pack->get_parts() as $p )
                    $parts[ $p ] = $info[ $p ]['description'];

                return wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/import-progress.tpl.php',
                                          array( 'pack' => $pack, 'parts' => $parts ),
                                          true );
            } catch (Exception $e) {
                if ( $pack )
                    $pack->cleanup( false );
                wpbdp_admin_message( $e->getMessage(), 'error' );
            }
        }

        return wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/import.tpl.php',
                                  array( 'uploaded' => $this->_find_uploaded_exports(), 'upload_dir' => 'wp-content/uploads/wpbdp-migrations/import/' ),
                                  true );
    }

    public function _handle_pack_upload() {
        $f = $_FILES['upload_file'];

        if ( ! is_uploaded_file( $f['tmp_name'] ) )
            return;

        $name = strtolower( $f['name'] );

        if ( '.zip' !== substr( $name, -4, 4 ) ) {
            wpbdp_admin_message( __( 'Uploaded file is not a ZIP file.', 'wpbdp-migrate' ), 'error' );
            return false;
        }

        $dir = $this->setup_migration_dir() . DIRECTORY_SEPARATOR . 'import';
        $dest_file = $dir . DIRECTORY_SEPARATOR . $name;

        $n = 0;
        while ( file_exists( $dest_file ) ) {
            $dest_file = $dir . DIRECTORY_SEPARATOR . $n . '_' . $name;
            $n++;
        }

        if ( ! move_uploaded_file( $f['tmp_name'], $dest_file ) )
            wpbdp_admin_message( __( 'Could not upload file.', 'wpbdp-migrate' ), 'error' );

        wpbdp_admin_message( sprintf( __( 'Migration pack "%s" was uploaded successfully.', 'wpbdp-migrate' ), basename( $dest_file ) ) );
    }

    public function ajax_import() {
        $response = new WPBDP_Ajax_Response();

        $id = ! empty( $_POST['import_id'] ) ? base64_decode( $_POST['import_id'] ) : '';

        if ( ! $id || ! current_user_can( 'administrator' ) )
            $response->send_error();

        $dir = $this->setup_migration_dir();
        $basedir = $dir . DIRECTORY_SEPARATOR . 'import';
        $path = $basedir . DIRECTORY_SEPARATOR . $id;
        $pack = null;

        try {
            $pack = new WPBDP_Migration_Pack( $path );
            $pack->do_work();
        } catch ( Exception $e ) {
            if ( $pack )
                $pack->cleanup();
            $response->send_error( $e->getMessage() );
        }

        $response->add( 'done', false );
        $response->add( 'part', $pack->get_current_part() );
        $response->add( 'parts_done', $pack->get_parts_done() );
        $response->add( 'id', $pack->get_id() );

        if ( $msg = $pack->get_status_message() )
            $response->set_message( $pack->get_status_message() );
        else
            $response->set_message( __( 'Working...', 'wpbdp-migrate' ) );

        if ( $pack->finished() ) {
            $response->set_message( __( 'Import finished successfully.', 'wpbdp-migrate' ) );
            $response->add( 'done', true );
            $pack->cleanup( true );
        }

        $response->send();
    }
    // }}
}

final class WPBDP_Migrate {
	public static function load( $modules ) {
		$modules->load( new WPBDP_Migrate_Module() );
	}
}

add_action( 'wpbdp_load_modules', array( 'WPBDP_Migrate', 'load' ) );
