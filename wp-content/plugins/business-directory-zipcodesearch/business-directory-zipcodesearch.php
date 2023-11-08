<?php
/**
 * Plugin Name: Business Directory ZIP Search
 * Description: Add the search your Business Directory plugin listings by ZIP or Postal Code within a given radius.
 * Plugin URI: https://businessdirectoryplugin.com
 * Version: 5.4.2
 * Author: Business Directory Team
 * Author URI: https://businessdirectoryplugin.com
 * Text Domain: wpbdp-zipcodesearch
 * Domain Path: /translations/
 */

// Do not allow direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/functions.php';

/**
 * @since 5.4
 */
function wpbdp_zipsearch_autoloader( $class_name ) {

	$classes = array(
		'WPBDP_ZipCodeSearchModule_Admin' => 'controllers/class-wpbdp-zip-admin.php',
		'WPBDP_ZIPDBImport'               => 'models/class-wpbdp-zip-import.php',
		'WPBDP_ZIPCodeSearchModule'       => 'models/class-wpbdp-zip-module.php',
		'_WPBDP_DistanceSorter'           => 'helpers/class-wpbdp-zip-distance-sorter.php',
		'ZIPCodeDB_File'                  => 'helpers/class-wpbdp-zip-db-file.php',
		'ZIPCodeDB_IntervalItemIterator'  => 'helpers/class-wpbdp-zip-interval-iterator.php',
		'WPBDP_ZIPSearchWidget'           => 'widgets/class-wpbdp-zip-search-widget.php',
	);

	if ( ! isset( $classes[ $class_name ] ) ) {
		return;
	}

	$filepath = dirname( __FILE__ ) . '/includes/' . $classes[ $class_name ];
	if ( file_exists( $filepath ) ) {
		require $filepath;
	}
}
spl_autoload_register( 'wpbdp_zipsearch_autoloader' );

/**
 * Class WPBDP__ZIP_Code_Search
 */
final class WPBDP__ZIP_Code_Search {

	public static function load( $modules ) {
		$instance = WPBDP_ZIPCodeSearchModule::instance();
		$modules->load( $instance );
	}

}

add_action( 'wpbdp_load_modules', array( 'WPBDP__ZIP_Code_Search', 'load' ) );
