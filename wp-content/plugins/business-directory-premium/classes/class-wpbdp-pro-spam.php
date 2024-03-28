<?php

/**
 * @since 5.3
 */
class WPBDP_Pro_Spam {

	/**
	 * @since 5.3
	 */
	public static function load_hooks() {
		add_filter( 'wpbdp_contact_form_validation_errors', array( __CLASS__, 'check_comment' ) );
		add_filter( 'wpbdp_listing_submit_validate_field', array( __CLASS__, 'check_field' ), 10, 4 );
	}

	/**
	 * Check values submitted in a comment form.
	 *
	 * @since 5.3
	 */
	public static function check_comment( $errors ) {
		$values = array(
			'name'    => wpbdp_get_var( array( 'param' => 'commentauthorname' ), 'post' ),
			'email'   => wpbdp_get_var( array( 'param' => 'commentauthoremail' ), 'post' ),
			'phone'   => wpbdp_get_var( array( 'param' => 'commentauthorphone' ), 'post' ),
			'message' => wpbdp_get_var( array( 'param' => 'commentauthormessage' ), 'post' ),
		);

		if ( self::blacklist_check( $values ) ) {
			$errors[] = self::get_error();
		}

		return $errors;
	}

	/**
	 * Check values submitted in a field in the listing form. A field error will
	 * prevent the form from moving forward.
	 *
	 * @param bool         $is_valid
	 * @param null|array   $field_errors
	 * @param object       $field
	 * @param string|array $value
	 *
	 * @since 5.3
	 * @return bool True if error
	 */
	public static function check_field( $is_valid, &$field_errors, $field, $value ) {
		if ( ! $is_valid ) {
			return $is_valid;
		}

		if ( ! is_object( $value ) && self::blacklist_check( $value ) ) {
			$is_valid = false;
			if ( empty( $field_errors ) ) {
				$field_errors = array();
			}
			$field_errors[] = self::get_error();
		}

		return $is_valid;
	}

	/**
	 * @since 5.3
	 *
	 * @return bool True if flagged as spam.
	 */
	private static function blacklist_check( $values ) {
		if ( ! apply_filters( 'wpbdp_check_blacklist', true, $values ) ) {
			return false;
		}

		$content = self::array_flatten( $values );
		if ( is_array( $content ) ) {
			$content = implode( ' ', $content );
		}

		if ( empty( $content ) ) {
			return false;
		}

		$user_info = self::get_spam_check_user_info( $values );

		return self::check_disallowed_words(
			$user_info['comment_author'],
			$user_info['comment_author_email'],
			$user_info['comment_author_url'],
			$content,
			wpbdp_get_client_ip_address(),
			wpbdp_get_server_value( 'HTTP_USER_AGENT' )
		);
	}

	/**
	 * Flatten the values in listing array into a string.
	 *
	 * @since 5.3
	 */
	private static function array_flatten( $array ) {
		if ( ! is_array( $array ) ) {
			return $array;
		}

		$return = array();
		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) ) {
				$return = array_merge( $return, self::array_flatten( $value ) );
			} else {
				$return[] = $value;
			}
		}

		return $return;
	}

	/**
	 * For WP 5.5 compatibility.
	 *
	 * @since 5.3
	 *
	 * @return bool True if it's block list spam.
	 */
	private static function check_disallowed_words( $author, $email, $url, $content, $ip, $user_agent ) {
		if ( function_exists( 'wp_check_comment_disallowed_list' ) ) {
			return wp_check_comment_disallowed_list( $author, $email, $url, $content, $ip, $user_agent );
		} else {
			// WP 5.4 and below.
			// phpcs:ignore WordPress.WP.DeprecatedFunctions
			return wp_blacklist_check( $author, $email, $url, $content, $ip, $user_agent );
		}
	}

	/**
	 * @since 5.3
	 */
	private static function get_spam_check_user_info( $values ) {
		$datas = array();

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();

			$datas['user_ID']              = $user->ID;
			$datas['user_id']              = $user->ID;
			$datas['comment_author']       = $user->display_name;
			$datas['comment_author_email'] = $user->user_email;
			$datas['comment_author_url']   = $user->user_url;
		} else {
			$datas['comment_author']       = '';
			$datas['comment_author_email'] = '';
			$datas['comment_author_url']   = '';

			// Value can be a string in most cases when saving data.
			if ( ! is_array( $values ) ) {
				$values = array( $values );
			}
			$values = array_filter( $values );
			foreach ( $values as $value ) {
				if ( ! is_array( $value ) ) {
					if ( $datas['comment_author_email'] == '' && strpos( $value, '@' ) && is_email( $value ) ) {
						$datas['comment_author_email'] = $value;
					} elseif ( $datas['comment_author_url'] == '' && strpos( $value, 'http' ) === 0 ) {
						$datas['comment_author_url'] = $value;
					} elseif ( $datas['comment_author'] == '' && ! is_numeric( $value ) && strlen( $value ) < 200 ) {
						$datas['comment_author'] = $value;
					}
				}
			}
		}

		return $datas;
	}

	/**
	 * @since 5.3
	 */
	private static function get_error() {
		return __( 'Your entry appears to be blocked spam!', 'wpbdp-pro' );
	}
}
