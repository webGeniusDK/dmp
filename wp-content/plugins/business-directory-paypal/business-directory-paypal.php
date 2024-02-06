<?php
/**
 * Plugin Name: Business Directory PayPal
 * Plugin URI: https://businessdirectoryplugin.com
 * Version: 5.0.6
 * Author: Business Directory Team
 * Description: Business Directory Payment Gateway for PayPal.  Allows you to collect payments from Business Directory Plugin listings via PayPal.
 * Author URI: https://businessdirectoryplugin.com
 * Text Domain: wpbdp-paypal
 * Domain Path: /translations
 *
 * @package PayPal Gateway Module
 */

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// This module is not included in the core of Business Directory Plugin. It is a separate add-on premium module and is not subject
// to the terms of the GPL license  used in the core package
// This module cannot be redistributed or resold in any modified versions of the core Business Directory Plugin product
// If you have this module in your possession but did not purchase it via businessdirectoryplugin.com or otherwise obtain it through businessdirectoryplugin.com
// please be aware that you have obtained it through unauthorized means and cannot be given technical support through businessdirectoryplugin.com.
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


final class WPBDP__PayPal {

    public static function load( $modules ) {
        $modules->load( new self );
    }

    public function __construct() {
        $this->file = __FILE__;
        $this->id = 'paypal';
        $this->title = 'PayPal Gateway Module';
        $this->required_bd_version = '5.1.3';
    }

    public function init() {
        add_filter( 'wpbdp_payment_gateways', array( $this, '_add_gateway' ) );
    }

    public function _add_gateway( $gateways ) {
        require_once( plugin_dir_path( __FILE__ ) . 'includes/class-paypal-gateway.php' );
        $gateways[] = new WPBDP__PayPal__Gateway();
        return $gateways;
    }

}


add_action( 'wpbdp_load_modules', array( 'WPBDP__PayPal', 'load' ) );

function wpbdp_paypal_aed_not_supported( $aed_restricted_gateways ) {
    if ( wpbdp_get_option( 'paypal', false ) ) {
        $aed_restricted_gateways[] = 'PayPal';
    }

    return $aed_restricted_gateways;
}
add_filter( 'wpbdp_aed_not_supported', 'wpbdp_paypal_aed_not_supported');
