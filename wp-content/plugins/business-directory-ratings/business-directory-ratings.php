<?php
/*
Plugin Name: Business Directory Ratings
Plugin URI: https://businessdirectoryplugin.com
Version: 5.3
Author: Business Directory Team
Description: Allows your users to rate businesses, search by rating, and enter comments about listings.
Author URI: https://businessdirectoryplugin.com
Text Domain: wpbdp-ratings
Domain Path: /translations
*/

define( 'WPBDP_RATINGS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once WPBDP_RATINGS_PLUGIN_DIR . 'class-ratings-table.php';
require_once WPBDP_RATINGS_PLUGIN_DIR . 'admin.php';
require_once WPBDP_RATINGS_PLUGIN_DIR . 'includes/class-ratings-settings.php';
require_once WPBDP_RATINGS_PLUGIN_DIR . 'includes/class-ratings-privacy-policy.php';

/**
 * Class BusinessDirectory_RatingsModule
 */
class BusinessDirectory_RatingsModule {

    const DB_VERSION = '0.8';

    private $settings;

    public function __construct() {
        $this->id                  = 'ratings';
        $this->file                = __FILE__;
        $this->title               = 'Ratings Module';
        $this->required_bd_version = '5.8';

        $this->version = '5.3';
    }

    /*
     * Activation
     */
    public function init() {
        if ( is_admin() ) {
            $this->admin = new BusinessDirectory_RatingsModuleAdmin();
        }

        $this->_install_or_update();

        $this->settings = new WPBDP_Ratings_Settings();
        $this->privacy  = new WPBDP_Ratings_Privacy_Policy();

        // Load i18n.
        load_plugin_textdomain( 'wpbdp-ratings', false, trailingslashit( basename( dirname( __FILE__ ) ) ) . 'translations/' );

        add_action( 'wpbdp_register_settings', array( $this->settings, 'register_settings' ) );

        add_action( 'wpbdp_register_fields', array( $this, 'register_fields' ) );
		add_filter( 'wpbdp_hidden_field_settings', array( $this, 'hide_field_setting' ), 10, 2 );

        add_filter( 'wpbdp_template_variables__single', array( &$this, '_single_template_reviews' ) );

        add_filter( 'wpbdp_search_query_pieces', array( $this, '_search_where' ), 10, 2 );

		add_shortcode( 'wpbdp_reviews', array( &$this, 'reviews_shortcode' ) );

        // Sort options.
        add_filter( 'wpbdp_listing_sort_options', array( $this, '_sort_options' ), 20, 1 );
        add_filter( 'wpbdp_query_fields', array( $this, '_query_fields' ) );
        add_filter( 'wpbdp_query_orderby', array( $this, '_query_orderby' ) );

        add_action( 'wp_ajax_wpbdp-ratings', array( $this, '_handle_ajax' ) );

        // Notifications.
        add_action( 'wpbdp_ratings_rating_submitted', array( &$this, 'send_rating_for_review_notification' ) );
        add_action( 'wpbdp_ratings_rating_approved', array( &$this, 'send_new_rating_notification' ) );
		add_action( 'wpbdp_enqueue_scripts', array( &$this, '_enqueue_scripts' ) );
    }

    public function &get_ratings_field() {
        $ratings_field = wpbdp_get_form_fields( 'field_type=ratings&unique=1' );
        return $ratings_field;
    }

    public function register_fields( $api ) {
        require_once plugin_dir_path( __FILE__ ) . 'class-ratings-field.php';
        $api->register_field_type( 'WPBDP_Ratings_Field', 'ratings' );

        // Create field (if needed).
        $ratings_field = $this->get_ratings_field();
        if ( ! $ratings_field ) {
            $display = array( 'listing' );
            if ( get_option( 'wpbdp-ratings-display-in-excerpt', true ) ) {
                $display[] = 'excerpt';
            }

            if ( get_option( 'wpbdp-ratings-display-in-search', true ) ) {
                $display[] = 'search';
            }

            $f = new WPBDP_FormField(
                array(
                    'label'         => __( 'Rating (average)', 'wpbdp-ratings' ),
                    'field_type'    => 'ratings',
                    'association'   => 'custom',
                    'display_flags' => $display,
                    'weight'        => 20,
                )
            );
            $f->save();
        }
    }

	/**
	 * Hide settings without a point in a ratings field.
	 *
	 * @param array $hidden A list of fields to hide.
	 * @param array $atts Includes $atts['field'] object.
	 *
	 * @since 5.2
	 */
	public function hide_field_setting( $hidden, $atts ) {
		$field = $atts['field'];
		$type  = $field->get_field_type()->get_id();
		if ( $type === 'ratings' ) {
			$hidden[] = 'private_field';
		}
		return $hidden;
	}

    public function _install_or_update() {
        global $wpdb;

        $db_version = get_option( 'wpbdp-ratings-db-version', '0.0' );

        if ( $db_version != self::DB_VERSION ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            $sql = "CREATE TABLE {$wpdb->prefix}wpbdp_ratings (
                id bigint(20) PRIMARY KEY  AUTO_INCREMENT,
                listing_id bigint(20) NOT NULL,
                rating tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
                user_id bigint(20) NOT NULL DEFAULT 0,
                user_name varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
                user_email varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
                ip_address varchar(255) NOT NULL,
                comment text CHARACTER SET utf8 COLLATE utf8_general_ci,
                created_on datetime NOT NULL,
                approved tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
                INDEX listing_id_index (listing_id)
            ) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";

            dbDelta( $sql );
        }

        // Upgrade an option in >= 1.6.
        if ( version_compare( $db_version, '0.5', '<' ) ) {
            if ( wpbdp_get_option( 'ratings-require-comment' ) ) {
                wpbdp_set_option( 'ratings-comments', 'required' );
            } else {
                wpbdp_set_option( 'ratings-comments', 'optional' );
            }
        }

        update_option( 'wpbdp-ratings-db-version', self::DB_VERSION );
    }

	/**
	 * Load the scripts and styles.
	 */
    public function _enqueue_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        wp_enqueue_style( 'wpbdp-ratings', plugins_url( '/resources/wpbdp-ratings' . $suffix . '.css', __FILE__ ), array(), $this->version );

		$handle = 'wpbdp-ratings';
		wp_enqueue_script(
			$handle,
			plugins_url( '/resources/wpbdp-ratings' . $suffix . '.js', __FILE__ ),
			array( 'jquery' ),
			$this->version,
			true
		);

        wp_localize_script(
            $handle,
            'WPBDP_ratings',
            array(
                '_config' => array(
                    'ajaxurl' => admin_url( 'admin-ajax.php' ),
                ),
            )
        );
    }

    /*
     * Sort by ratings
     */
    public function _sort_options( $options ) {
		$use_ratings = isset( $options['field-ratings'] ) || isset( $options['field-ratings_count'] );
		if ( ! $options || ! $use_ratings ) {
            return $options;
        }

		$order = $this->ratings_sort_order();

        if ( isset( $options['field-ratings'] ) ) {
            unset( $options['field-ratings'] );
            $options['rating'] = array( __( 'Rating', 'wpbdp-ratings' ), '', $order );
        }

        if ( isset( $options['field-ratings_count'] ) ) {
            unset( $options['field-ratings_count'] );
            $options['rating_count'] = array( __( 'Rating Count', 'wpbdp-ratings' ), '', $order );
        }

        return $options;
    }

	/**
	 * Use the global sort order only if ratings is the selected field.
	 *
	 * @since 5.2.1
	 *
	 * @return string - ASC or DESC
	 */
	private function ratings_sort_order() {
		$default = 'DESC';
		$order = wpbdp_get_option( 'listings-sort', $default );
		if ( $order === $default ) {
			return $order;
		}

		$order_by = wpbdp_get_option( 'listings-order-by' );
		if ( $order_by !== 'ratings' ) {
			$order = $default;
		}

		return $order;
	}

    public function _query_fields( $fields ) {
        global $wpdb;

        $sort = $this->get_ratings_sort_option();

        if ( ! $sort ) {
            return $fields;
        }

		if ( $sort->option === 'rating' || $sort->option === 'rating_count' ) {
			$rating_query  = "(SELECT IFNULL(AVG(rating),0) FROM {$wpdb->prefix}wpbdp_ratings WHERE listing_id = {$wpdb->posts}.ID) AS wpbdp_rating";
			$rating_query .= ",(SELECT COUNT(rating) FROM {$wpdb->prefix}wpbdp_ratings WHERE listing_id = {$wpdb->posts}.ID) AS wpbdp_rating_count";

			return $fields . ', ' . $rating_query;
		}

        return $fields;
    }

    public function _query_orderby( $orderby ) {
        $sort = $this->get_ratings_sort_option();

        if ( ! $sort ) {
            return $orderby;
        }

		if ( $sort->option === 'rating' ) {
			return ( $orderby ? $orderby . ', ' : '' ) . 'wpbdp_rating ' . $sort->order . ' , wpbdp_rating_count ' . $sort->order;
		} elseif ( $sort->option === 'rating_count' ) {
			return ( $orderby ? $orderby . ', ' : '' ) . 'wpbdp_rating_count ' . $sort->order . ' , wpbdp_rating ' . $sort->order;
		}

        return $orderby;
    }

    // FIXME: use the wpbdp_search_where filter while we work this into the actual FormField class.
    public function _search_where( $pieces, $search ) {
        $field = $this->get_ratings_field();

        if ( ! $field->has_display_flag( 'search' ) ) {
            return $pieces;
        }

        $terms      = $search->terms_for_field( $field );
        $min_rating = absint( array_pop( $terms ) );
        if ( $min_rating > 0 ) {
            global $wpdb;

            $subquery         = $wpdb->prepare( "SELECT listing_id FROM {$wpdb->prefix}wpbdp_ratings GROUP BY listing_id HAVING AVG(rating) >= %d", $min_rating );
            $pieces['where'] .= " AND {$wpdb->posts}.ID IN ({$subquery}) ";
        }

        return $pieces;
    }


    /*
     * Ratings
     */
	public function enabled() {
		_deprecated_function( __METHOD__, '5.3' );
		return true;
	}

    public function get_rating_info( $listing_id ) {
        global $wpdb;

		$query = $wpdb->prepare( "SELECT COUNT(*) AS count, AVG(rating) AS average FROM {$wpdb->prefix}wpbdp_ratings WHERE listing_id = %d AND approved = %d", $listing_id, 1 );

		if ( is_callable( 'WPBDP_Utils::check_cache' ) ) {
			$info = WPBDP_Utils::check_cache(
				array(
					'cache_key' => $listing_id . 'ave',
					'group'     => 'bdrating',
					'query'     => $query,
					'type'      => 'get_row',
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$info = $wpdb->get_row( $query );
		}

        $info->average = round( $info->average, 2 );
        $info->count   = intval( $info->count );

        return $info;
    }

    public function get_reviews( $listing_id, $only_approved = true ) {
        global $wpdb;

        if ( $only_approved ) {
			$query = $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpbdp_ratings WHERE listing_id = %d AND approved = %d ORDER BY id DESC",
				$listing_id,
				1
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpbdp_ratings WHERE listing_id = %d ORDER BY id DESC",
				$listing_id
			);
        }

		if ( is_callable( 'WPBDP_Utils::check_cache' ) ) {
			return WPBDP_Utils::check_cache(
				array(
					'cache_key' => $listing_id . ( $only_approved ? 'approved' : '' ),
					'group'     => 'bdrating',
					'query'     => $query,
					'type'      => 'get_results',
				)
			);
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $query );
    }

	public function can_post_review( $listing_id, &$reason = null ) {
        if ( current_user_can( 'administrator' ) ) {
            return true;
        }

        $reason = 'already-rated';

        if ( ! wpbdp_get_option( 'ratings-allow-unregistered' ) && ! is_user_logged_in() ) {
            $reason = 'not-logged-in';
            return false;
        }

        $user_id = get_current_user_id();
        $post    = get_post( $listing_id );

        if ( $user_id && $user_id == $post->post_author ) {
            $reason = 'listing-owner';
            return false;
        }

		if ( apply_filters( 'wpbdp_listing_ratings_enabled', true, $listing_id ) === false ) {
			$reason = 'restricted-field';
			return false;
		}

        global $wpdb;
        $ip_address = wpbdp_get_client_ip_address();

        if ( $user_id ) {
            return intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpbdp_ratings WHERE (user_id = %d OR ip_address = %s) AND listing_id = %d", $user_id, $ip_address, $listing_id ) ) ) == 0;
        } else {
            return intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpbdp_ratings WHERE ip_address = %s AND listing_id = %d", $ip_address, $listing_id ) ) ) == 0;
        }
    }

	/**
	 * If there is a custom login page in the BD settings, use it.
	 *
	 * @since 5.3
	 * @return string
	 */
	public function login_url() {
		$redirect_to = site_url( wpbdp_get_server_value( 'REQUEST_URI' ) );
		$login_url   = trim( wpbdp_get_option( 'login-url' ) );
		if ( $login_url ) {
			$login_url = add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $login_url );
		} else {
			$login_url = wp_login_url( $redirect_to );
		}

		return $login_url;
	}

	/**
	 * Clear the ratings cache for a listing.
	 *
	 * @since 5.2.1
	 */
	private function clear_cache( $listing_id ) {
		wp_cache_delete( $listing_id, 'bdrating' );
		wp_cache_delete( $listing_id . 'ave', 'bdrating' );
		wp_cache_delete( $listing_id . 'approved', 'bdrating' );
	}

    public function _process_form( $listing_id ) {
        global $wpdb;

        $this->_form_state                      = array();
        $this->_form_state['success']           = false;
        $this->_form_state['listing_id']        = $listing_id;
        $this->_form_state['validation_errors'] = $this->validate_form();

        if ( ! $this->can_post_review( $listing_id ) ) {
            return;
        }

        if ( ! isset( $_POST['rate_listing'] ) || $this->_form_state['validation_errors'] ) {
			return;
		}

		$listing_id = intval( wpbdp_get_var( array( 'param' => 'listing_id' ), 'post' ) );

		$review = array(
			'user_id'    => get_current_user_id(),
			'user_name'  => wpbdp_get_var( array( 'param' => 'user_name' ), 'post' ),
			'user_email' => wpbdp_get_var( array( 'param' => 'user_email' ), 'post' ),
			'ip_address' => wpbdp_get_client_ip_address(),
			'listing_id' => $listing_id,
			'rating'     => intval( wpbdp_get_var( array( 'param' => 'score' ), 'post' ) ),
			'comment'    => $this->get_sanitized_comment(),
			'created_on' => current_time( 'mysql' ),
			'approved'   => wpbdp_get_option( 'ratings-require-approval' ) ? 0 : 1,
		);

		if ( ! $wpdb->insert( "{$wpdb->prefix}wpbdp_ratings", $review ) ) {
			return;
		}

		$this->clear_cache( $listing_id );

		$this->_form_state['success'] = true;

		$review['id'] = $wpdb->insert_id;

		$action = 'wpbdp_ratings_rating_' . ( $review['approved'] == 1 ? 'approved' : 'submitted' );
		do_action( $action, (object) $review );

		// Reset the POST so it doesn't show in the review form.
		$_POST = array();
    }

    private function validate_form() {
        $errors = array();

        if ( ! isset( $_POST['rate_listing'] ) ) {
			return $errors;
		}

		if ( ! is_user_logged_in() && empty( $_POST['user_name'] ) ) {
			$errors[] = __( 'Please enter your name.', 'wpbdp-ratings' );
		}

		$email = wpbdp_get_var( array( 'param' => 'user_email' ), 'post' );
		if ( ! is_user_logged_in() && ! trim( $email ) && $this->require_visitor_email() ) {
			$errors[] = __( 'Please enter your email.', 'wpbdp-ratings' );
		}

		$rating = wpbdp_get_var(
			array(
				'param'    => 'score',
				'default'  => 0,
				'sanitize' => 'intval',
			),
			'post'
		);
		if ( $rating <= 0 || $rating > 5 ) {
			$errors[] = __( 'Please select a valid rating.', 'wpbdp-ratings' );
		}

		if ( wpbdp_get_option( 'ratings-comments' ) == 'required' && empty( $_POST['comment'] ) ) {
			$errors[] = __( 'Please enter a comment.', 'wpbdp-ratings' );
		}

        return $errors;
    }

	/**
	 * Should the rating form include an email field when logged out?
	 *
	 * @since 5.3
	 * @return bool
	 */
	public function require_visitor_email() {
		return apply_filters( 'wpbdp_listing_require_email', true );
	}

	/**
	 * @since 5.2
	 */
	public function get_stars( $atts ) {
		$review = $atts['review'];

		if ( is_array( $review ) && isset( $review['score'] ) ) {
			$selected = $review['score'];
		} elseif ( is_object( $review ) ) {
			$selected = $review->rating;
		} else {
			$selected = (float) $review;
		}

		$this->_enqueue_scripts();

		$readonly = isset( $atts['readonly'] );
		include plugin_dir_path( __FILE__ ) . 'templates/stars.php';
	}

	public function reviews_shortcode() {
		global $post;
		if ( ! is_singular( WPBDP_POST_TYPE ) ) {
			return '';
		}

		$listing_id = $post->ID;
		return $this->get_reviews_for_listing( $listing_id );
	}

    /*
     * Views.
     */
    public function _single_template_reviews( $vars ) {
        $listing_id = $vars['listing_id'];

		$out = $this->get_reviews_for_listing( $listing_id );

		if ( $out ) {
			$vars['#reviews']       = array(
				'position' => 'after',
				'value'    => $out,
				'weight'   => 1,
			);
			$vars['listing_rating'] = $this->get_rating_info( $listing_id );
		}

		return $vars;
	}

	/**
	 * @since 5.2.1
	 *
	 * @param int $listing_id
	 *
	 * @return string
	 */
	private function get_reviews_for_listing( $listing_id ) {

        // WPML support.
        if ( ! empty( $GLOBALS['sitepress'] ) ) {
            global $sitepress;
            $def_lang   = $sitepress->get_default_language();
            $listing_id = icl_object_id( $listing_id, WPBDP_POST_TYPE, true, $def_lang );
        }
        $this->_process_form( $listing_id );

        $out = '';

        ob_start();
        $this->_reviews_and_form( $listing_id );
        $out = ob_get_contents();
        ob_end_clean();

		return $out;
	}

    public function _reviews_and_form( $listing_id ) {
		if ( apply_filters( 'wpbdp_listing_ratings_enabled', true, $listing_id ) === false ) {
			return;
		}

        $vars                = array();
        $vars['listing_id']  = $listing_id;
        $vars['review_form'] = $this->can_post_review( $listing_id, $reason ) ? $this->get_template( 'form', $this->_form_state ) : '';
        $vars['reason']      = $reason;
        $vars['success']     = $this->_form_state['success'];
        $vars['ratings']     = $this->get_reviews( $listing_id );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $this->get_template( 'ratings', $vars );
    }

	/**
	 * @since 5.2
	 */
	private function get_template( $file, $vars ) {
		return wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/' . $file . '.tpl.php', $vars );
	}

    /*
     * AJAX
     */

    public function _handle_ajax() {
        global $wpdb;

        $res = array(
            'success' => false,
            'msg'     => __( 'An unknown error occurred', 'wpbdp-ratings' ),
        );

		$rating_id = wpbdp_get_var( array( 'param' => 'id' ), 'post' );

		$review = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpbdp_ratings WHERE id = %d", $rating_id )
		);

		$action = wpbdp_get_var( array( 'param' => 'a' ), 'post' );
		switch ( $action ) {
            case 'info':
				$listing_id     = wpbdp_get_var( array( 'param' => 'listing_id' ), 'post' );
                $res['info']    = (array) $this->get_rating_info( $listing_id );
                $res['success'] = true;
                break;
            case 'edit':
                if ( $review ) {
                    if ( ( $review->user_id && $review->user_id == get_current_user_id() ) || current_user_can( 'administrator' ) ) {
						$original_comment = $review->comment;
						$review->comment  = $this->get_sanitized_comment();

						if ( $original_comment === $review->comment ) {
							// When the comment doesn't change, don't show an error.
							$res['success'] = true;
						}

                        if ( $wpdb->update( "{$wpdb->prefix}wpbdp_ratings", (array) $review, array( 'id' => $review->id ) ) ) {
                            $res['comment'] = $review->comment;
                            $res['html']    = wpautop( $review->comment );
                            $res['success'] = true;
							$this->clear_cache( $review->listing_id );
                        }
                    }
                }
                break;

            case 'delete':
                if ( $review ) {
                    if ( ( $review->user_id && $review->user_id == get_current_user_id() ) || current_user_can( 'administrator' ) ) {
                        if ( $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpbdp_ratings WHERE id = %d", $rating_id ) ) !== false ) {
                            $res['success'] = true;
							$this->clear_cache( $review->listing_id );
                        }
                    }
                }
                break;
            default:
                break;
        }

        if ( $res['success'] == true ) {
            $res['msg'] = '';
        }

        print wp_json_encode( $res );
        exit;
    }

	/**
	 * Sanitize the posted review comment, based on the allowed HTML setting.
	 *
	 * @since 5.2
	 */
	private function get_sanitized_comment() {
		$allow_html = wpbdp_get_option( 'ratings-allow-html' );

		$comment = wpbdp_get_var(
			array(
				'param'    => 'comment',
				'sanitize' => $allow_html ? 'wp_kses_post' : 'sanitize_textarea_field',
			),
			'post'
		);
		$comment = wp_encode_emoji( $comment );
		return trim( $comment );
	}

    public function send_new_rating_notification( $review ) {
        if ( ! wpbdp_get_option( 'ratings-notify-owner' ) && ! wpbdp_get_option( 'ratings-notify-admin' ) ) {
            return;
        }

        $email_placeholders = $this->get_notification_placeholders_from_rating( $review );
        $email              = wpbdp_email_from_template( 'ratings-notification-email-template', $email_placeholders );

        if ( ! is_object( $email ) ) {
            return;
        }

        if ( wpbdp_get_option( 'ratings-notify-owner' ) ) {
            $email->to[] = wpbusdirman_get_the_business_email( $review->listing_id );
        }

        if ( wpbdp_get_option( 'ratings-notify-admin' ) ) {
            $email->to[] = get_bloginfo( 'admin_email' );
        }

        $email->send();
    }

    private function get_notification_placeholders_from_rating( $rating ) {
        return array(
            'listing'        => sprintf( '<a href="%s">%s</a>', get_permalink( $rating->listing_id ), get_the_title( $rating->listing_id ) ),
            'rating_author'  => $this->get_rating_autor( $rating ),
            'rating_comment' => $rating->comment,
            'rating_rating'  => $rating->rating . ' / 5',
            'date'           => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $rating->created_on ) ),
        );
    }

    private function get_rating_autor( $rating ) {
        if ( $rating->user_id ) {
            $rating_author = get_the_author_meta( 'display_name', $rating->user_id ) . ' (' . get_the_author_meta( 'user_login', $rating->user_id ) . ')';
        } else {
            $rating_author = 'IP ' . $rating->ip_address;
        }

        return $rating_author;
    }

    public function send_rating_for_review_notification( $review ) {
        if ( ! wpbdp_get_option( 'ratings-notify-admin' ) ) {
            return;
        }

        $url = admin_url( 'admin.php?page=wpbdp-ratings-pending-review#review-' . $review->id );

        $email_placeholders = array_merge(
            $this->get_notification_placeholders_from_rating( $review ),
            array( 'url' => '<a href="' . esc_url( $url ) . '">' . $url . '</a>' )
        );

        $email = wpbdp_email_from_template( 'ratings-pending-approval-notification-email-template', $email_placeholders );

        if ( ! is_object( $email ) ) {
            return;
        }

        $email->to[] = get_bloginfo( 'admin_email' );
        $email->send();
    }

    private function get_ratings_sort_option() {
		$obj = wpbdp_get_current_sort_option();
		if ( $obj ) {
            return $obj;
        }

        if ( 'ratings' !== wpbdp_get_option( 'listings-order-by', '' ) ) {
            return $obj;
        }

        $obj         = new StdClass();
        $obj->option = 'rating';
        $obj->order  = wpbdp_get_option( 'listings-sort', 'DESC' );

        return $obj;

    }

}

function wpbdp_ratings() {
    global $wpbdp_ratings;
    return $wpbdp_ratings;
}

final class WPBDP__Ratings {
    public static function load( $modules ) {
        global $wpbdp_ratings;
        $wpbdp_ratings = new BusinessDirectory_RatingsModule();
        $modules->load( $wpbdp_ratings );
    }

    public static function _sort_options( $options ) {
        $options                  = self::_add_ratings_sort_option( $options );
        $options['ratings_count'] = __( 'Ratings count', 'wpbdp-ratings' );

        return $options;
    }

    public static function _add_ratings_sort_option( $options ) {
        $options['ratings'] = __( 'Ratings', 'wpbdp-ratings' );

        return $options;
    }
}
add_action( 'wpbdp_load_modules', array( 'WPBDP__Ratings', 'load' ) );
add_filter( 'wpbdp_sort_options', array( 'WPBDP__Ratings', '_add_ratings_sort_option' ) );
add_filter( 'wpbdp_sortbar_get_field_options', array( 'WPBDP__Ratings', '_sort_options' ) );
