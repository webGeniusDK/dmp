<?php

/**
 *
 * @link              https://swarmify.com/
 * @since             1.0.0
 * @package           Swarmify
 *
 * @wordpress-plugin
 * Plugin Name:       SmartVideo
 * Plugin URI:        https://swarmify.com/wordpress-plugin/
 * Description:       SmartVideo makes building a beautiful, professional video experience for your site effortless.
 * Version:           2.0.25
 * Author:            Swarmify
 * Author URI:        https://swarmify.com/
 * License:           AGPL-3.0
 * License URI:       https://www.gnu.org/licenses/agpl.txt
 * Text Domain:       swarmify
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'SWARMIFY_PLUGIN_VERSION', '2.0.25' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-swarmify-activator.php
 */
function activate_swarmify() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-swarmify-activator.php';
	Swarmify_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-swarmify-deactivator.php
 */
function deactivate_swarmify() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-swarmify-deactivator.php';
	Swarmify_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_swarmify' );
register_deactivation_hook( __FILE__, 'deactivate_swarmify' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-swarmify.php';

/**
 * Page builder - elementor
 */
require plugin_dir_path( __FILE__ ) . 'page-builders/elementor/class-elementor-swarmify.php';

/**
 * Page builder - Gutenberg
 */
require_once plugin_dir_path( __FILE__ ) . 'page-builders/gutenberg/src/init.php';

/** 
 * Page builder - beaver builder
 */
function my_load_module_examples() {
    if ( class_exists( 'FLBuilder' ) ) {
		require plugin_dir_path( __FILE__ ) . 'page-builders/beaverbuilder/class-beaverbuilder-smartvideo.php';
    }
}
add_action( 'init', 'my_load_module_examples' );

/** 
 * divi
 * 
*/

if ( ! function_exists( 'smartvideo_initialize_extension' ) ):
	/**
	 * Creates the extension's main class instance.
	 *
	 * @since 1.0.0
	 */
	function smartvideo_initialize_extension() {
		require_once plugin_dir_path( __FILE__ ) . 'page-builders/divi-builder/includes/DiviBuilder.php';
	}
	add_action( 'divi_extensions_init', 'smartvideo_initialize_extension' );
	endif;
	
/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_swarmify() {

	$plugin = new Swarmify();
	$plugin->run();

}
run_swarmify();


