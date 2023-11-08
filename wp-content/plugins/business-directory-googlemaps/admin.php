<?php
/**
 * The admin area settings.
 *
 * @package WPBDP\GoogleMaps
 */

/**
 * Add settings to BD.
 *
 * @since 5.0
 */
class WPBDP__Google_Maps__Admin {

	/**
	 * The BD plugin object.
	 *
	 * @var object $plugin The BD plugin.
	 */
    private $plugin;

    public function __construct( $plugin ) {
        $this->plugin = $plugin;

        add_action( 'admin_init', array( $this, 'admin_init' ) );
        add_action( 'wpbdp_admin_notices', array( $this, 'admin_notices' ) );
		add_filter( 'wpbdp_tab_content', array( &$this, 'add_menu_icon' ) );
    }

    public function admin_init() {
        if ( wpbdp_getv( $_REQUEST, 'wpbdp-check-googlemaps-api-key', 0 ) ) {
            $this->verify_googlemaps_api_key_status();
        }
    }

    private function verify_googlemaps_api_key_status() {
        $this->plugin->verify_apikey_status();

        if ( ! $this->plugin->has_warning( 'request-denied' ) ) {
            update_option( 'wpbdp-googlemaps-api-key-verified', true );
        }

        if ( wp_redirect( wp_get_referer() ? wp_get_referer() : admin_url() ) ) {
            exit;
        }
    }

    public function admin_notices() {

        if ( ! wpbdp_get_option( 'googlemaps-apikey' ) && ! get_user_meta( get_current_user_id(), 'wpbdp_notice_dismissed[googlemaps-apikey]', true ) ) {
			$msg  = '<b>' . esc_html__( 'Business Directory Google Maps: API key required.', 'wpbdp-googlemaps' ) . '</b><br />';
            $msg .= __( 'Google requires an API key to use their geocoding system.', 'wpbdp-googlemaps' ) . '<br />';
            $msg .= '<br /><br />';
            $msg .= '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=wpbdp_settings&tab=googlemaps#googlemaps-apikey' ) ) . '">';
			$msg .= __( 'Add an API key', 'wpbdp-googlemaps' );
			$msg .= '</a>';

            wpbdp_admin_message( $msg, 'error dismissible', array( 'dismissible-id' => 'googlemaps-apikey' ) );
        }

        if ( get_option( 'wpbdp-googlemaps-api-key-verified' ) ) {
            $message = __( 'The Google Maps API key was succesfully verified.', 'wpbdp-googlemaps' );

            wpbdp_admin_message( $message, 'notice notice-info dismissible' );

            delete_option( 'wpbdp-googlemaps-api-key-verified' );
        }
    }

	/**
	 * Add an icon in the settings menu.
	 *
	 * @param array $tabs
	 *
	 * @return array
	 */
	public function add_menu_icon( $tabs ) {
		if ( isset( $tabs['googlemaps'] ) ) {
			$plugin_file = dirname( __FILE__ ) . '/business-directory-googlemaps.php';

			$tabs['googlemaps']['icon_url'] = plugins_url( '/resources/images/location.svg', $plugin_file );
		}
		return $tabs;
	}
}
