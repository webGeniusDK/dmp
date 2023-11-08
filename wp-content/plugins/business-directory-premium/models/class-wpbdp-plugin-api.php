<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class WPBDP_Plugin_Api {

	protected $license = '';
	protected $cache_key = '';
	protected $cache_timeout = '+6 hours';

	/**
	 * @since 5.0
	 */
	public function __construct( $license = null ) {
		$this->set_license( $license );
		$this->set_cache_key();
	}

	/**
	 * @since 5.0
	 */
	private function set_license( $license ) {
		if ( 'auto' === $license ) {
			$license = $this->get_license_key();
		}
		$this->license = $license;
	}

	private function get_license_key() {
		return wpbdp_get_option( 'license-key-module-business-directory-premium' );
	}

	/**
	 * @since 5.0
	 * @return string
	 */
	public function get_license() {
		return $this->license;
	}

	/**
	 * @since 5.0
	 */
	protected function set_cache_key() {
		$this->cache_key = 'bdp_addons_l' . ( empty( $this->license ) ? '' : md5( $this->license ) );
	}

	/**
	 * @since 5.0
	 * @return string
	 */
	public function get_cache_key() {
		return $this->cache_key;
	}

	/**
	 * @since 5.0
	 * @return array
	 */
	public function get_api_info() {
		$url = $this->api_url();
		if ( ! empty( $this->license ) ) {
			$url .= '?l=' . urlencode( base64_encode( $this->license ) );
		}

		$addons = $this->get_cached();
		if ( ! empty( $addons ) ) {
			return $addons;
		}

		$response = wp_remote_get( $url );
		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			$addons = $response['body'];
			if ( ! empty( $addons ) ) {
				$addons = json_decode( $addons, true );

				foreach ( $addons as $k => $addon ) {
					if ( ! isset( $addon['categories'] ) ) {
						continue;
					}
					$cats = array_intersect( $this->skip_categories(), $addon['categories'] );
					if ( ! empty( $cats ) ) {
						unset( $addons[ $k ] );
					}
				}

				$this->set_cached( $addons );
			}
		}

		if ( empty( $addons ) ) {
			return array();
		}

		return $addons;
	}

	public function send_request( $request ) {
		$arg_array = array(
			'body'       => $request,
			'timeout'    => 25,
			'user-agent' => 'Business Directory; ' . get_bloginfo( 'url' ),
			'sslverify'  => false,
		);

		$resp = wp_remote_post( $this->store_url(), $arg_array );
		$body = wp_remote_retrieve_body( $resp );

		$success = false;
		$message = __( 'Your License Key was invalid', 'wpbdp-pro' );
		if ( is_wp_error( $resp ) ) {
			$link = 'https://businessdirectoryplugin.com/knowledge-base/installation-guide/';
			/* translators: %1$s: Start link HTML, %2$s: End link HTML */
			$message = sprintf( __( 'You had an error communicating with the Business Directory API. %1$sClick here%2$s for more information.', 'wpbdp-pro' ), '<a href="' . esc_url( $link ) . '" target="_blank">', '</a>' );
			$message .= ' ' . $resp->get_error_message();
		} elseif ( 'error' === $body || is_wp_error( $body ) ) {
			$message = __( 'You had an HTTP error connecting to the Business Directory API', 'wpbdp-pro' );
		} else {
			$json_res = json_decode( $body, true );
			if ( null !== $json_res ) {
				if ( is_array( $json_res ) && isset( $json_res['error'] ) ) {
					$message = $json_res['error'];
				} else {
					$message = $json_res;
					$success = true;
				}
			} elseif ( isset( $resp['response'] ) && isset( $resp['response']['code'] ) ) {
				/* translators: %1$s: Error code, %2$s: Error message */
				$message = sprintf( __( 'There was a %1$s error: %2$s', 'wpbdp-pro' ), $resp['response']['code'], $resp['response']['message'] . ' ' . $resp['body'] );
			}
		}

		return compact( 'message', 'success' );
	}

	/**
	 * @since 5.0
	 */
	protected function api_url() {
		return $this->store_url() . '/wp-json/s11edd/v1/updates/';
	}

	/**
	 * @since 5.0
	 */
	protected function store_url() {
		return 'https://businessdirectoryplugin.com/';
	}

	/**
	 * @since 5.0
	 */
	protected function skip_categories() {
		return array();
	}

	/**
	 * @since 5.0
	 *
	 * @param object $license_plugin The Addon object
	 *
	 * @return array
	 */
	public function get_addon_for_license( $license_plugin, $addons = array() ) {
		if ( empty( $addons ) ) {
			$addons = $this->get_api_info();
		}
		$download_id = $license_plugin->download_id;
		$plugin      = array();
		if ( empty( $download_id ) && ! empty( $addons ) ) {
			foreach ( $addons as $addon ) {
				if ( strtolower( $license_plugin->plugin_name ) == strtolower( $addon['title'] ) ) {
					return $addon;
				}
			}
		} elseif ( isset( $addons[ $download_id ] ) ) {
			$plugin = $addons[ $download_id ];
		}

		return $plugin;
	}

	/**
	 * @since 5.0
	 * @return array
	 */
	protected function get_cached() {
		$cache = get_option( $this->cache_key );

		if ( empty( $cache ) || empty( $cache['timeout'] ) || current_time( 'timestamp' ) > $cache['timeout'] ) {
			return false; // Cache is expired
		}

		$version     = wpbdp_get_version();
		$for_current = isset( $cache['version'] ) && $cache['version'] == $version;
		if ( ! $for_current ) {
			// Force a new check.
			return false;
		}

		return json_decode( $cache['value'], true );
	}

	/**
	 * @since 5.0
	 */
	protected function set_cached( $addons ) {
		$data = array(
			'timeout' => strtotime( $this->cache_timeout, current_time( 'timestamp' ) ),
			'value'   => json_encode( $addons ),
			'version' => wpbdp_get_version(),
		);

		update_option( $this->cache_key, $data, 'no' );
	}

	/**
	 * @since 5.0
	 */
	public function reset_cached() {
		delete_option( $this->cache_key );
	}

	/**
	 * @since 5.0
	 * @return array
	 */
	public function error_for_license() {
		$errors = array();
		if ( ! empty( $this->license ) ) {
			$errors = $this->get_error_from_response();
		}

		return $errors;
	}

	/**
	 * @since 5.0
	 * @return array
	 */
	public function get_error_from_response( $addons = array() ) {
		if ( empty( $addons ) ) {
			$addons = $this->get_api_info();
		}
		$errors = array();
		if ( isset( $addons['error'] ) ) {
			$errors[] = $addons['error']['message'];
			do_action( 'wpbdp_license_error', $addons['error'] );
		}

		return $errors;
	}
}
