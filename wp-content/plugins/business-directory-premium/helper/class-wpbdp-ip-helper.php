<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IP Helper
 */
class WPBDP_IP_Helper {

	/**
	 * Validates that the IP that made the request is from cloudflare
	 *
	 * @param string $ip - the ip to check
	 *
	 * @return bool
	 */
	private static function _validate_cloudflare_ip( $ip ) {
		$cloudflare_ips = array(
			'199.27.128.0/21',
			'173.245.48.0/20',
			'103.21.244.0/22',
			'103.22.200.0/22',
			'103.31.4.0/22',
			'141.101.64.0/18',
			'108.162.192.0/18',
			'190.93.240.0/20',
			'188.114.96.0/20',
			'197.234.240.0/22',
			'198.41.128.0/17',
			'162.158.0.0/15',
			'104.16.0.0/12',
		);
		$is_cf_ip       = false;
		foreach ( $cloudflare_ips as $cloudflare_ip ) {
			if ( self::_cloudflare_ip_in_range( $ip, $cloudflare_ip ) ) {
				$is_cf_ip = true;
				break;
			}
		}

		return $is_cf_ip;
	}

	/**
	 * Check if the cloudflare IP is in range
	 *
	 * @param String $ip - the current IP
	 * @param String $range - the allowed range of cloudflare ips
	 *
	 * @return bool
	 */
	private static function _cloudflare_ip_in_range( $ip, $range ) {
		if ( strpos( $range, '/' ) === false ) {
			$range .= '/32';
		}

		// $range is in IP/CIDR format eg 127.0.0.1/24
		list( $range, $netmask ) = explode( '/', $range, 2 );
		$range_decimal           = ip2long( $range );
		$ip_decimal              = ip2long( $ip );
		$wildcard_decimal        = pow( 2, ( 32 - $netmask ) ) - 1;
		$netmask_decimal         = ~$wildcard_decimal;

		return ( ( $ip_decimal & $netmask_decimal ) === ( $range_decimal & $netmask_decimal ) );
	}

	/**
	 * Check if there are any cloudflare headers in the request
	 *
	 * @return bool
	 */
	private static function _cloudflare_requests_check() {

		$ip_options = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CF_IPCOUNTRY',
			'HTTP_CF_RAY',
			'HTTP_CF_VISITOR',
		);

		$flag = true;

		foreach ( $ip_options as $key ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( ! isset( $_SERVER[ $key ] ) ) {
				$flag = false;
			}
		}

		return $flag;
	}

	/**
	 * Check if the request is from cloudflare. If it is, we get the IP
	 *
	 * @return bool
	 */
	private static function is_cloudflare() {
		if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = wpbdp_get_server_value( 'HTTP_CLIENT_IP' );
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = wpbdp_get_server_value( 'HTTP_X_FORWARDED_FOR' );
		} else {
			$ip = wpbdp_get_server_value( 'REMOTE_ADDR' );
		}
		if ( isset( $ip ) ) {
			$request_check = self::_cloudflare_requests_check();
			if ( ! $request_check ) {
				return false;
			}

			$ip_check = self::_validate_cloudflare_ip( $ip );

			return $ip_check;
		}

		return false;
	}

	/**
	 * A shorhand function to get user IP
	 *
	 * @return mixed|string
	 */
	public static function get_user_ip() {
		$client  = wpbdp_get_server_value( 'HTTP_CLIENT_IP' );
		$forward = wpbdp_get_server_value( 'HTTP_X_FORWARDED_FOR' );
		$is_cf   = self::is_cloudflare(); // Check if request is from CloudFlare
		if ( $is_cf ) {
			$cf_ip = wpbdp_get_server_value( 'HTTP_CF_CONNECTING_IP' ); // We already make sure this is set in the checks
			if ( filter_var( $cf_ip, FILTER_VALIDATE_IP ) ) {
				return apply_filters( 'wpbdp_helper_user_ip', $cf_ip );
			}
		} else {
			$remote = wpbdp_get_server_value( 'REMOTE_ADDR' );
		}
		$client_real = wpbdp_get_server_value( 'HTTP_X_REAL_IP' );
		$user_ip     = $remote;
		if ( filter_var( $client, FILTER_VALIDATE_IP ) ) {
			$user_ip = $client;
		} elseif ( filter_var( $client_real, FILTER_VALIDATE_IP ) ) {
			$user_ip = $client_real;
		} elseif ( ! empty( $forward ) ) {
			$forward = explode( ',', $forward );
			$ip      = array_shift( $forward );
			$ip      = trim( $ip );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				$user_ip = $ip;
			}
		}

		return apply_filters( 'wpbdp_helper_user_ip', $user_ip );
	}
}
