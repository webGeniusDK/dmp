<?php

class WPBDP_Premium_Module {

	const REQUIRED_BD_VERSION = '5.9.2';

	/**
	 * The short key used to represent this plugin.
	 *
	 * @var string
	 */
	public $id = 'premium';

	/**
	 * The main plugin file for this plugin.
	 *
	 * @var string
	 */
	public $file;

	/**
	 * Name of this plugin.
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Main plugin version required.
	 *
	 * @var string
	 */
	public $required_bd_version;

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	public $version = '5.6.1';

	public function __construct( $file = false ) {
		$this->title               = 'Business Directory Premium';
		$this->required_bd_version = self::REQUIRED_BD_VERSION;

		if ( $file ) {
			$this->file = $file;
		}

		$this->load_textdomain();
		$this->init_license();
	}

	public function get_version() {
		return $this->version;
	}

	public function init() {
		if ( ! defined( 'WPBDP_VERSION' ) || version_compare( WPBDP_VERSION, $this->required_bd_version, '<' ) ) {
			return;
		}

		add_filter( 'pre_set_site_transient_update_plugins', 'WPBDP_Addons::check_update', 9 );
		add_filter( 'transient_wpbdp_updates', 'WPBDP_Addons::override_updates' );
		add_filter( 'transient_wpbdp-themes-updates', 'WPBDP_Addons::override_updates' );

		$this->init_database();
		$this->init_tracking();
		$this->init_abc();
		$this->init_abandonment();
		$this->init_layout();
		$this->init_elementor();
		$this->init_field_icons();
		$this->init_dashboard();
		WPBDP_Pro_Spam::load_hooks();

		add_filter( 'get_post_metadata', array( &$this, 'check_post_meta' ), 10, 3 );

		add_action( 'wpbdp_enqueue_scripts', array( &$this, 'enqueue_styles' ) );
	}

	private function init_database() {
		WPBDP_Activator::get_instance()->activate();
	}

	private function init_tracking() {
		new WPBDP_Tracking();
	}

	private function init_abc() {
		new WPBDP_ABC_Filtering();
	}

	private function init_abandonment() {
		new WPBDP_Abandonment();
	}

	private function init_layout() {
		new WPBDP_List_Layout();
	}

	/**
	 * Load elementor widgets
	 */
	private function init_elementor() {
		new WPBDP_Elementor();
	}

	/**
	 * Load field icons
	 */
	private function init_field_icons() {
		new WPBDP_Field_Icon();
	}

	/**
	 * Setup dashboard widget
	 */
	private function init_dashboard() {
		new WPBDP_Dashboard();
	}

	private function init_license() {
		add_action( 'wp_ajax_wpbdp_activate_main_license', array( $this, 'activate_main_license' ) );
		add_action( 'wp_ajax_wpbdp_deactivate_main_license', array( $this, 'deactivate_main_license' ) );
	}

	public function activate_main_license() {
		$nonce = wpbdp_get_var( array( 'param' => 'nonce' ), 'post' );
		if ( ! wp_verify_nonce( $nonce, 'license activation' ) ) {
			wp_die();
		}

		$l        = new WPBDP_Pro_License();
		$response = $l->active_license();
		wp_send_json( $response );
	}

	public function deactivate_main_license() {
		$license = wpbdp_get_var( array( 'param' => 'license_key' ), 'post' );
		wpbdp_delete_option( 'pro_license' );
		wpbdp_delete_option( 'license-key-module-business-directory-premium' );

		// Clear out anyplace else this license has been saved.
		$all_licenses = get_option( 'wpbdp_licenses' );
		foreach ( $all_licenses as $k => $license_info ) {
			if ( $license_info['license_key'] === $license ) {
				wpbdp_delete_option( $k );

				$setting = array(
					'licensing_item_type' => strpos( $k, 'theme-' ) === 0 ? 'theme' : 'module',
					'licensing_item'      => str_replace( array( 'theme-', 'module-' ), '', $k ),
				);
				wpbdp()->licensing->license_key_changed_callback( $setting, '', $license );
			}
		}

		wpbdp()->licensing->ajax_deactivate_license();
	}

	/**
	 * Post meta is stored with naming like _wpbdp[fields][10]
	 * This function uses more normal names and checks in the fields.
	 * Allows wpbdp-field-10 or wpbdp-field-website_address
	 */
	public function check_post_meta( $value, $object_id, $meta_key ) {
		$prefix = 'wpbdp-field-';
		if ( ! $meta_key || strpos( $meta_key, $prefix ) !== 0 ) {
			return $value;
		}

		$field_id = str_replace( $prefix, '', $meta_key );
		if ( ! is_numeric( $field_id ) ) {
			$field = WPBDP_FormField::get( $field_id );
			if ( $field ) {
				$field_id = $field->get_id();
			}
		}

		if ( is_numeric( $field_id ) ) {
			$real_meta_key = '_wpbdp[fields][' . $field_id . ']';
			return get_post_meta( $object_id, $real_meta_key, true );
		}

		return $value;
	}

	/**
	 * Load the translations.
	 */
	private function load_textdomain() {
		$languages_dir = dirname( dirname( __FILE__ ) ) . '/languages';
		load_plugin_textdomain( 'wpbdp-pro', false, $languages_dir );
	}

	public function enqueue_styles() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$url = plugins_url( '/resources/bd' . $min . '.css', dirname( __FILE__ ) );

		wp_enqueue_style( 'wpbdp-pro', $url, array(), $this->version );
	}

	/**
	 * Allow an Elementor template to control the page.
	 *
	 * @since 5.0
	 * @deprecated x.x
	 */
	public function skip_bd_template( $template ) {
		_deprecated_function( __METHOD__, '5.2.1', 'WPBDP_Elementor::skip_bd_template' );

		return $template;
	}

	/**
	 * Get module details.
	 *
	 * @since 5.3
	 *
	 * @return bool|object
	 */
	public static function get_module_details() {
		global $wpbdp;
		if ( ! method_exists( $wpbdp->modules, 'get_module_info' ) ) {
			return false;
		}
		return $wpbdp->modules->get_module_info( 'premium' );
	}
}
