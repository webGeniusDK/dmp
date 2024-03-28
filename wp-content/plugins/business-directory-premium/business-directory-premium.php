<?php
/**
 * Plugin Name: Business Directory Premium
 * Plugin URI: https://businessdirectoryplugin.com
 * Description: One Business Directory Plugin to rule them all.
 * Version: 5.6.2
 * Author: Business Directory Team
 * Author URI: https://businessdirectoryplugin.com
 * Text Domain: wpbdp-pro
 * Domain Path: /languages/
 */

/**
 * @since 5.0.1
 */
function load_wpbdp_pro() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	$is_free_installed = function_exists( 'wpbdp' );
	if ( ! $is_free_installed ) {
		add_action( 'admin_notices', 'wpbdp_pro_incompatible_version' );
	}
}
add_action( 'plugins_loaded', 'load_wpbdp_pro', 1 );

/**
 * Show a notification if the core plugin is needed.
 *
 * @since 5.0.1
 */
function wpbdp_pro_incompatible_version() {
	$plugin_helper = new WPBDP_Installer(
		array(
			'plugin_file' => 'business-directory-plugin/business-directory-plugin.php',
		)
	);

	$ran_auto_install = get_option( 'wpbdp_ran_auto_install' );
	if ( false === $ran_auto_install ) {
		global $pagenow;

		if ( 'update.php' !== $pagenow && 'update-core.php' !== $pagenow ) {
			update_option( 'wpbdp_ran_auto_install', true, 'no' );

			$plugin_helper->maybe_install_and_activate();
		}
		return;
	}

	if ( $plugin_helper->is_installed() ) {
		$link = $plugin_helper->activate_url();
	} else {
		$link = $plugin_helper->install_url();
	}
	?>
	<div class="error wpbdp-error">
		<p><?php esc_html_e( 'Business Directory Premium requires the core Business Directory Plugin.', 'wpbdp-pro' ); ?></p>
		<p>
			<a href="<?php echo esc_url( $link ); ?>" class="button-primary">
				<?php esc_html_e( 'Install Business Directory Plugin', 'wpbdp-pro' ); ?>
			</a>
		</p>
	</div>
	<?php
}

function wpbdp_premium_load( $modules ) {
	$modules->load( new WPBDP_Premium_Module( __FILE__ ) );
}
add_action( 'wpbdp_load_modules', 'wpbdp_premium_load' );

/**
 * Inject bulk license info.
 *
 * @since 5.0
 */
function wpbdp_premium_licenses( $license_data ) {
	if ( WPBDP_Addons::is_license_valid() ) {
		// Add extra licenses.
		WPBDP_Addons::add_bulk_licenses( $license_data );
	}

	return $license_data;
}
add_filter( 'option_wpbdp_licenses', 'wpbdp_premium_licenses' );

function wpbdp_premium_load_license( $args ) {
	if ( 'permalink_settings' !== $args['slug'] ) {
		return;
	}
	new WPBDP_Pro_License();
}
add_action( 'wpbdp_register_group', 'wpbdp_premium_load_license' );

function wpbdp_premium_plugin_url() {
	return plugins_url( '', __DIR__ . '/' . basename( __FILE__ ) );
}

function wpbdp_premium_autoloader( $class_name ) {
	// Only load BD classes here
	if ( ! preg_match( '/^WPBDP.+$/', $class_name ) ) {
		return;
	}

	$classes = array(
		'WPBDP_Abandonment'       => 'classes',
		'WPBDP_ABC_Filtering'     => 'classes',
		'WPBDP_List_Layout'       => 'classes',
		'WPBDP_Premium_Module'    => 'classes',
		'WPBDP_Pro_License'       => 'classes',
		'WPBDP_Elementor'         => 'classes',
		'WPBDP_Field_Icon'        => 'classes',
		'WPBDP_Pro_Spam'          => 'classes',
		'WPBDP_Abandonment_Email' => 'models',
		'WPBDP_Addons'            => 'models',
		'WPBDP_Installer'         => 'models',
		'WPBDP_Plugin_Api'        => 'models',
		'WPBDP_Table_List'        => 'models',
		'WPBDP_Activator'         => 'models',
		'WPBDP_Statistics'        => 'models',
		'WPBDP_Tracking'          => 'classes/tracking',
		'WPBDP_MonsterInsights'   => 'classes/tracking',
		'WPBDP_IP_Helper'         => 'helper',
		'WPBDP_Premium_Helper'    => 'helper',
		'WPBDP_Icon_Helper'       => 'helper',
		'WPBDP_Elementor_Base'    => 'widgets/elementor',
		'WPBDP_Elementor_Listing' => 'widgets/elementor',
		'WPBDP_Elementor_Details' => 'widgets/elementor',
		'WPBDP_Elementor_Images'  => 'widgets/elementor',
		'WPBDP_Elementor_Section' => 'widgets/elementor',
		'WPBDP_Elementor_Buttons' => 'widgets/elementor',
		'WPBDP_Elementor_Socials' => 'widgets/elementor',
		'WPBDP_Dashboard'         => 'widgets',
	);

	if ( ! isset( $classes[ $class_name ] ) ) {
		return;
	}

	$file     = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
	$filepath = __DIR__ . '/' . $classes[ $class_name ] . '/' . $file;
	if ( file_exists( $filepath ) ) {
		require $filepath;
	}
}
spl_autoload_register( 'wpbdp_premium_autoloader' );

function wpbdp_user_max_columns() {
	return apply_filters( 'wpbdp_table_max_columns', 6 );
}
