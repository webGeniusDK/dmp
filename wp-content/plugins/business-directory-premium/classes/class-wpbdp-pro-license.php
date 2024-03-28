<?php

class WPBDP_Pro_License {

	private $deregister_me = array();

	private $license_id = 'module-business-directory-premium';

	public function __construct() {
		$this->register_settings();
		add_filter( 'wpbdp_setting_type_pro_license', array( &$this, 'show_license' ), 10, 2 );
		add_filter( 'wpbdp_get_option', array( &$this, 'maybe_set_license_key' ), 10, 2 );
		add_action( 'wpbdp_register_settings', array( &$this, 'deregister_settings' ), 20 );
	}

	/**
	 * This can be removed later. It's only needed fo <v5.9.1.
	 */
	private function register_settings() {
		wpbdp_register_settings_group( 'upgrade', __( 'License Key', 'business-directory-plugin' ), 'general/main' );

		wpbdp_register_setting(
			array(
				'id'    => 'pro_license',
				'name'  => '',
				'type'  => 'pro_license',
				'group' => 'upgrade',
			)
		);
	}

	public function show_license( $setting, $value ) {
		remove_filter( 'wpbdp_setting_type_pro_license', array( wpbdp()->admin->settings_admin, 'no_license' ), 20, 2 );

		$item_type = 'module';
		$item_id   = 'business-directory-premium';
		$api       = new WPBDP_Plugin_Api( 'auto' );
		$value     = $api->get_license();

		$license_status      = $this->get_license_status();
		$licensing_info_attr = wp_json_encode(
			array(
				'setting'   => 'license-key-module-' . $item_id,
				'item_type' => $item_type,
				'item_id'   => $item_id,
				'status'    => $license_status,
				'nonce'     => wp_create_nonce( 'license activation' ),
			)
		);

		ob_start();
		include dirname( __DIR__ ) . '/views/license/license-box.php';
		$html = ob_get_contents();
		ob_end_clean();

		wp_enqueue_script( 'wpbdp_premium', wpbdp_premium_plugin_url() . '/resources/admin.js', array( 'jquery' ), 1 );
		return $html;
	}

	private function get_license_status() {
		$is_valid = WPBDP_Addons::is_license_valid();
		if ( ! $is_valid ) {
			return 'invalid';
		}

		// Make sure it's still active.
		$main_license = $this->get_saved_license_key( true );
		if ( empty( $main_license ) ) {
			return 'invalid';
		}

		return 'valid';
	}

	public function active_license() {
		$action   = 'activate';
		$response = $this->license_request( 'activate' );
		if ( ! is_array( $response ) ) {
			return array(
				'success' => false,
				'error'   => 'An unknown error occured ' . $response,
			);
		}

		$is_valid = false;
		if ( 'valid' === $response['message'] ) {
			$is_valid            = 'valid';
			$response['success'] = true;
		}

		$messages = $this->get_messages();
		if ( ! empty( $response['message'] ) && is_string( $response['message'] ) && isset( $messages[ $response['message'] ] ) ) {
			$response['message'] = $messages[ $response['message'] ];
		} elseif ( ! empty( $response['error'] ) ) {
			$response['error'] = wp_kses_post( $response['error'], array( 'a' ) );
		} elseif ( ! empty( $response['message'] ) ) {
			$response['message'] = wp_kses_post( $response['message'], array( 'a' ) );
		}

		return $response;
	}

	private function license_request( $action = 'activate' ) {
		$key        = wpbdp_get_var( array( 'param' => 'license_key' ), 'post' );
		$item_type  = wpbdp_get_var( array( 'param' => 'item_type' ), 'post' );
		$item_id    = wpbdp_get_var( array( 'param' => 'item_id' ), 'post' );

		$response = array(
			'success' => false,
			'message' => '',
		);

		if ( ! $item_type || ! $item_id ) {
			$response['error'] = esc_html__( 'Missing data. Please reload this page and try again.', 'business-directory-plugin' );
			return $response;

		}

		if ( ! $key ) {
			$response['error'] = esc_html__( 'Please enter a license key.', 'business-directory-plugin' );
			return $response;
		}

		global $wpbdp;
		$items = $wpbdp->licensing->get_items();

		$request = array(
			'edd_action' => $action . '_license',
			'license'    => $key,
			'url'        => home_url(),
			'item_name'  => rawurlencode( $items[ $item_id ]['name'] ),
		);

		try {
			$api      = new WPBDP_Plugin_Api();
			$response = $api->send_request( $request );
			$license_data = $response['message'];

			if ( is_array( $license_data ) ) {
				if ( in_array( $license_data['license'], array( 'valid', 'invalid' ), true ) ) {
					$response['success'] = true;
					$response['message'] = $license_data['license'];

					$this->save_license( $item_id, $key, $license_data['license'] );
				}
			} else {
				$response['error'] = $response['message'];
			}
		} catch ( Exception $e ) {
			$response['error'] = $e->getMessage();
		}

		return $response;
	}

	/**
	 * Save the license response.
	 *
	 * @since 5.2
	 */
	private function save_license( $item_id, $key, $status ) {
		wpbdp_set_option( 'license-key-module-' . $item_id, $key );

		$all_licenses = $this->get_saved_licenses();
		$all_licenses[ $this->license_id ] = array(
			'license_key' => $key,
			'status'      => $status,
		);
		update_option( 'wpbdp_licenses', $all_licenses );
	}

	private function get_messages() {
		return array(
			'valid'               => __( 'Your license has been activated. Enjoy!', 'wpbdp-pro' ),
			'invalid'             => __( 'That license key is invalid', 'wpbdp-pro' ),
			'expired'             => __( 'That license is expired', 'wpbdp-pro' ),
			'revoked'             => __( 'That license has been refunded', 'wpbdp-pro' ),
			'no_activations_left' => __( 'That license has been used on too many sites', 'wpbdp-pro' ),
			'invalid_item_id'     => __( 'Oops! That is the wrong license key for this plugin.', 'wpbdp-pro' ),
			'missing'             => __( 'That license key is invalid', 'wpbdp-pro' ),
		);
	}

	/**
	 * @since 5.2
	 */
	private function get_saved_license_key( $filter = false ) {
		$all_licenses = $this->get_saved_licenses( $filter );
		return isset( $all_licenses[ $this->license_id ] ) ? $all_licenses[ $this->license_id ] : array();
	}

	/**
	 * If the main license covers it, use it for modules.
	 */
	public function maybe_set_license_key( $value, $setting_id ) {
		if ( strpos( $setting_id, 'license-key-' ) !== 0 ) {
			return $value;
		}

		// Check if this module has an available download url.
		$main_license = $this->get_saved_license_key();
		if ( empty( $main_license['status'] ) || $main_license['status'] !== 'valid' ) {
			return $value;
		}

		$all_licenses = $this->get_saved_licenses();
		$setting = str_replace( 'license-key-', '', $setting_id );
		if ( isset( $all_licenses[ $setting ] ) && $all_licenses[ $setting ]['status'] === 'valid' && $all_licenses[ $setting ]['license_key'] === $main_license['license_key'] ) {
			$value = $all_licenses[ $setting ]['license_key'];

			// Hide the license keys.
			$this->deregister_me[] = $setting_id;
		}

		return $value;
	}


	/**
	 * Get unfiltered licenses.
	 *
	 * @since 5.0
	 */
	private function get_saved_licenses( $filter = false ) {
		if ( $filter ) {
			return get_option( 'wpbdp_licenses', array() );
		}

		remove_filter( 'wpbdp_get_option', array( &$this, 'maybe_set_license_key' ), 10, 2 );
		$all_licenses = get_option( 'wpbdp_licenses', array() );
		add_filter( 'wpbdp_get_option', array( &$this, 'maybe_set_license_key' ), 10, 2 );
		return $all_licenses;
	}

	/**
	 * Remove the license key settings since they aren't needed.
	 */
	public function deregister_settings( $settings ) {
		if ( empty( $this->deregister_me ) ) {
			return;
		}

		$page = wpbdp_get_var( array( 'param' => 'page' ) );
		if ( $page !== 'wpbdp_settings' ) {
			// Only process if it'll show.
			return;
		}

		// Hide the license keys.
		$groups = array();
		$all_settings = $settings->get_registered_settings();
		foreach ( $this->deregister_me as $id ) {
			if ( ! empty( $all_settings[ $id ]['group'] ) ) {
				$groups[ $all_settings[ $id ]['group'] ] = $all_settings[ $id ]['group'];
			}

			$settings->deregister_setting( $id );
		}

		if ( is_callable( array( $settings, 'deregister_empty_group' ) ) ) {
			foreach ( $groups as $g ) {
				$settings->deregister_empty_group( $g );
			}
		}
	}
}
