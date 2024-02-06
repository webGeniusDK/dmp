<?php

/**
 * Fired during plugin activation
 *
 * @link       https://swarmify.com/
 * @since      1.0.0
 *
 * @package    Swarmify
 * @subpackage Swarmify/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Swarmify
 * @subpackage Swarmify/includes
 * @author     Omar Kasem <omar.kasem207@gmail.com>
 */
class Swarmify_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		add_option( 'swarmify_toggle_youtube','on');
		add_option( 'swarmify_toggle_youtube_cc','off');
		add_option( 'swarmify_toggle_layout','on');
        add_option( 'swarmify_toggle_bgvideo', 'off');
        add_option( 'swarmify_theme_button', 'default' );
        add_option( 'swarmify_toggle_uploadacceleration', 'on');
	    if (is_plugin_active('swarm-cdn/swarmcdn.php') )
	    {
	        deactivate_plugins('swarm-cdn/swarmcdn.php');
	    }
	}

}
