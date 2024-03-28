<?php
/**
 * Plugin Name: Business Directory Stripe
 * Plugin URI: https://businessdirectoryplugin.com
 * Version: 5.5
 * Author: Business Directory Team
 * Description: Business Directory Payment Gateway for Stripe.  Allows you to collect payments from Business Directory Plugin listings via Stripe.
 * Author URI: https://businessdirectoryplugin.com
 * Text Domain: wpbdp-stripe
 * Domain Path: /translations/
 *
 * @package WPBDP\Stripe
 */

if ( version_compare( phpversion(), '5.6.0', '<' ) ) {

	/**
	 * @since 5.0.7
	 */
	function wpbdp_stripe_upgrade_php_warning() {
		?>
		<div class="wpbdp-notice notice notice-error">
			<p><strong>
				<?php esc_html_e( 'The Stripe module for Business Directory Plugin was deactivated because it requires PHP 5.6 or newer.', 'wpbdp-stripe' ); ?>
			</strong></p>
			<p>
				<?php esc_html_e( 'Hi, we noticed that your site is running on an outdated version of PHP. New versions of PHP are faster, more secure and include the features our module requires to support the latest version of Stripe\'s API.', 'wpbdp-stripe' ); ?>
			</p>
			<p>
				<?php echo wp_kses_post( __( 'You should upgrade to <strong>PHP 5.6</strong>, but if you want your site to also be considerable faster and even more secure, we recommend going up to <strong>PHP 7.2</strong>.', 'wpbdp-stripe' ) ); ?>
			</p>
			<p>
				<?php echo wp_kses_post( __( 'Please read <a href="https://wordpress.org/support/upgrade-php/">Upgrading PHP</a> to understand more about PHP and how to upgrade.', 'wpbdp-stripe' ) ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * @since 5.1.0
	 */
	function wpbdp_stripe_deactivate_plugin() {
		deactivate_plugins( plugin_basename( __FILE__ ) );
	}

	add_action( 'admin_notices', 'wpbdp_stripe_upgrade_php_warning' );
	add_action( 'admin_init', 'wpbdp_stripe_deactivate_plugin' );
}


require_once __DIR__ . '/includes/class-stripe.php';
add_action( 'wpbdp_load_modules', array( 'WPBDP__Stripe', 'load' ) );
