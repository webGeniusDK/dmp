<?php
// Do not allow direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WPBDP_ZIPCodeSearchModule
 */
class WPBDP_ZIPCodeSearchModule {

	const REQUIRED_BD_VERSION = '5.7.5';
	const DB_VERSION          = '0.5';

	const EARTH_RADIUS = 6372.797; // in km.
	const KM_TO_MI     = 0.621371192;
	const MI_TO_KM     = 1.60934400061469;

	private static $instance = null;

	private $plugin_url = '';

	private $plugin_path = '';

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		$this->id      = 'zipcodesearch';
		$this->title   = 'ZIP Search Module';
		$this->file    = dirname( dirname( dirname( __FILE__ ) ) ) . '/business-directory-zipcodesearch.php';
		$this->version = '5.4.2';

		$this->required_bd_version = '5.7.5';
	}

	/**
	 * @since 5.0.6
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Get the plugin url.
	 *
	 * @since 5.4
	 *
	 * @return string
	 */
	public function get_plugin_url() {
		if ( ! $this->plugin_url ) {
			$this->plugin_url = plugin_dir_url( $this->file );
		}

		return $this->plugin_url;
	}

	/**
	 * Get the plugin path.
	 *
	 * @since 5.4
	 *
	 * @return string
	 */
	public function get_plugin_path() {
		if ( ! $this->plugin_path ) {
			$this->plugin_path = plugin_dir_path( $this->file );
		}

		return $this->plugin_path;
	}

	public function init() {
		$this->admin = new WPBDP_ZipCodeSearchModule_Admin( $this );

		$this->install_or_update(); // Install or update.
		add_action( 'wpbdp_register_settings', array( $this, '_register_settings' ), 10, 1 ); // Register settings.

		if ( ! $this->check_db() ) {
			return;
		}

		add_shortcode( 'bd-zip', array( &$this, '_shortcode' ) );

		add_action( 'widgets_init', array( &$this, '_register_widgets' ) );

		add_action( 'before_delete_post', array( &$this, 'delete_listing_cache' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Search integration.
		add_action( 'wp_ajax_wpbdp-zipcodesearch-code-search', array( $this, 'ajax_code_search' ) );
		add_action( 'wp_ajax_nopriv_wpbdp-zipcodesearch-code-search', array( $this, 'ajax_code_search' ) );
		add_action( 'wpbdp_main_box_extra_fields', array( &$this, 'search_box_fields' ) );
		add_filter( 'wpbdp_searching_request', array( &$this, 'is_zipcode_search' ), 10 );
		add_filter( 'wpbdp_listing_search_parse_request', array( &$this, 'quick_search_request' ), 10, 2 );
		add_filter( 'wpbdp_form_field_is_empty_value', array( $this, 'field_is_empty_value' ), 10, 3 );

		// Form Fields integration.
		add_action( 'wpbdp_form_field_pre_render', array( $this, 'zip_field_attrs' ), 10, 3 );
		add_filter( 'wpbdp_render_field_inner', array( &$this, 'zip_field_country_hint' ), 20, 5 );
		add_filter( 'wpbdp_fields_text_input_name', array( $this, 'zip_field_input_name' ), 10, 5 );
		add_filter( 'wpbdp_form_field_html_value', array( $this, 'zip_field_normalize_input_value' ), 10, 3 );
		add_filter( 'wpbdp_fields_text_value_for_rendering', array( $this, 'zip_field_normalize_input_value' ), 10, 3 );
		add_filter( 'wpbdp_form_field_value', array( $this, 'zip_field_get_value' ), 10, 3 );
		add_filter( 'wpbdp_form_field_pre_convert_input', array( $this, 'zip_field_convert_input' ), 10, 3 );
		add_filter( 'wpbdp_form_field_store_value_override', array( $this, 'zip_field_store_value_override' ), 10, 4 );

		add_filter( 'wpbdp_render_field_inner', array( &$this, 'search_form_integration' ), 10, 5 );
		add_filter( 'wpbdp_pre_configure_search', array( $this, 'configure_search' ), 10, 4 );
		add_filter( 'wpbdp_form_field_pre_convert_input', array( $this, 'search_convert_input' ), 10, 3 );
		add_filter( 'wpbdp_search_query_posts_args', array( &$this, 'change_search_order' ), 10, 2 );
	}

	private function install_or_update() {
		$installed_db_version = get_option( 'wpbdp-zipcodesearch-db-version', '0.0' );

		if ( $installed_db_version == '0.0' ) {
			$this->install( self::DB_VERSION );
		} elseif ( version_compare( $installed_db_version, self::DB_VERSION ) < 0 ) {
			$this->upgrade( $installed_db_version, self::DB_VERSION );
		}
	}

	private function install( $new_version ) {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$database_helper = wpbdp_database_helper();

		$sql = "CREATE TABLE {$wpdb->prefix}wpbdp_zipcodes (
               zip varchar(10) CHARACTER SET <charset> COLLATE <collate> NOT NULL,
               latitude FLOAT NOT NULL,
               longitude FLOAT NOT NULL,
               country varchar(2) CHARACTER SET <charset> COLLATE <collate> NOT NULL,
               city varchar(100) CHARACTER SET <charset> COLLATE <collate> NULL,
               state varchar(100) CHARACTER SET <charset> COLLATE <collate> NULL,
               KEY (zip)
           ) DEFAULT CHARSET=<charset> COLLATE=<collate>;";
		$sql = $database_helper->replace_charset_and_collate( $sql );

		dbDelta( $sql );

		$sql = "CREATE TABLE {$wpdb->prefix}wpbdp_zipcodes_listings (
               listing_id bigint(20),
               zip varchar(10) CHARACTER SET <charset> COLLATE <collate> NULL,
               latitude FLOAT NULL,
               longitude FLOAT NULL,
               PRIMARY KEY (listing_id)
           ) DEFAULT CHARSET=<charset> COLLATE=<collate>;";
		$sql = $database_helper->replace_charset_and_collate( $sql );

		dbDelta( $sql );

		update_option( 'wpbdp-zipcodesearch-db-version', $new_version );
	}

	private function upgrade( $old_version, $new_version ) {
		$upgrade_routines = array(
			'0.4' => 'convert_character_set_and_collation',
			'0.5' => 'add_prefix_to_tables',
		);

		foreach ( $upgrade_routines as $version => $routines ) {
			if ( version_compare( $old_version, $version ) >= 0 ) {
				continue;
			}

			foreach ( (array) $routines as $routine ) {
				if ( method_exists( $this, $routine ) ) {
					$this->{$routine}( $old_version );
				}
			}
		}

		update_option( 'wpbdp-zipcodesearch-db-version', $new_version );
	}

	private function convert_character_set_and_collation( $old_version ) {
		global $wpdb;

		$database_helper = wpbdp_database_helper();

		$charset = $database_helper->get_charset();
		$collate = $database_helper->get_collate();

		if ( $charset != 'utf8_general_ci' ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}wpbdp_zipcodes CONVERT TO CHARACTER SET $charset COLLATE $collate" );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}wpbdp_zipcodes_listings CONVERT TO CHARACTER SET $charset COLLATE $collate" );
		}
	}

	private function add_prefix_to_tables( $old_version ) {
		global $wpdb;

		$prefix          = $wpdb->prefix;
		$tables          = array( 'wpbdp_zipcodes_listings', 'wpbdp_zipcodes' );
		$needs_reinstall = false;

		if ( '' == $prefix ) {
			return;
		}

		foreach ( $tables as $t ) {
			$no_prefix_table           = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) );
			$non_prefixed_table_exists = strcasecmp( $no_prefix_table, $t ) === 0;
			$prefixed                  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $prefix . $t ) );
			$prefixed_table_exists     = strcasecmp( $prefixed, $prefix . $t ) === 0;

			if ( $prefixed_table_exists ) {
				continue;
			}

			if ( ! $non_prefixed_table_exists ) {
				$needs_reinstall = true;
				break;
			}

			$query = sprintf( 'RENAME TABLE %s TO %s', $t, $prefix . $t );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( false === $wpdb->query( $query ) ) {
				$needs_reinstall = true;
				break;
			}
		}

		if ( $needs_reinstall ) {
			$this->install( self::DB_VERSION );
			delete_option( 'wpbdp-zipcodesearch-db-list' );
		}
	}

	public function enqueue_scripts() {
		wp_enqueue_style(
			'wpbdp-zip-module',
			$this->get_plugin_url() . 'resources/styles.css',
			array(),
			$this->version
		);

		wpbdp_enqueue_jquery_ui_style();

		wp_register_script( 'wpbdp-zipcodesearch-js', $this->get_plugin_url() . 'resources/zipcodesearch.js', array( 'jquery', 'jquery-ui-autocomplete' ), $this->version );
		wp_enqueue_script( 'wpbdp-zipcodesearch-js' );
	}

	public function _register_widgets() {
		register_widget( 'WPBDP_ZIPSearchWidget' );
	}

	public function _register_settings( &$settingsapi ) {
		wpbdp_register_settings_group( 'zipsearch/general', __( 'ZIP Search', 'wpbdp-zipcodesearch' ), 'search_settings' );

		$fields      = array();
		$fields['0'] = __( '-- Select a field --', 'wpbdp-zipcodesearch' );
		foreach ( wpbdp_get_form_fields( 'association=meta' ) as $f ) {
			$fields[ $f->get_id() ] = esc_attr( $f->get_label() );
		}

		wpbdp_register_setting(
			array(
				'id'        => 'zipcode-field',
				'name'      => _x( 'Use this field for postal code/ZIP code information', 'settings', 'wpbdp-zipcodesearch' ),
				'type'      => 'select',
				'options'   => $fields,
				'on_update' => array( $this, 'validate_zip_field_setting' ),
				'group'     => 'zipsearch/general',
				'class'     => 'wpbdp-half',
			)
		);
		wpbdp_register_setting(
			array(
				'id'      => 'zipcode-units',
				'name'    => __( 'Units', 'wpbdp-zipcodesearch' ),
				'type'    => 'select',
				'default' => 'miles',
				'options' => wpbdp_zipcodesearch_unit_options(),
				'group'   => 'zipsearch/general',
				'class'   => 'wpbdp-half',
			)
		);

		wpbdp_register_setting(
			array(
				'id'      => 'zipcode-fixed-radius',
				'name'    => _x( 'Use custom distance options for searches with postal codes', 'settings', 'wpbdp-zipcodesearch' ),
				'type'    => 'checkbox',
				'default' => false,
				'group'   => 'zipsearch/general',
			)
		);
		wpbdp_register_setting(
			array(
				'id'      => 'zipcode-radius-options',
				'name'    => _x( 'Custom radius options', 'settings', 'wpbdp-zipcodesearch' ),
				'type'    => 'textarea',
				'default' => '1,5,10,20',
				'desc'    => _x( 'Comma separated list', 'settings', 'wpbdp-zipcodesearch' ),
				'group'   => 'zipsearch/general',
				'requirements' => array( 'zipcode-fixed-radius' ),
			)
		);

		wpbdp_register_setting(
			array(
				'id'      => 'zipcode-force-order',
				'name'    => _x( 'Sort listings from closest to farthest in the search results?', 'settings', 'wpbdp-zipcodesearch' ),
				'type'    => 'checkbox',
				'default' => true,
				'group'   => 'zipsearch/general',
			)
		);

		wpbdp_register_setting(
			array(
				'id'        => 'zipcode-available-search-modes',
				'name'      => __( 'Available search modes', 'wpbdp-zipcodesearch' ),
				'desc'      => __( 'Specific ZIP Code mode allows users to search listings associated with a particular ZIP code. Distance mode allows users to search listings near the area defined by a particular ZIP code.', 'wpbdp-zipcodesearch' ),
				'type'      => 'multicheck',
				'default'   => array(
					'zip',
					'distance',
				),
				'options'   => array(
					'zip'      => __( 'Specific ZIP code', 'wpbdp-zipcodesearch' ),
					'distance' => __( 'Distance', 'wpbdp-zipcodesearch' ),
				),
				'group'     => 'zipsearch/general',
				'validator' => 'required',
			)
		);

		wpbdp_register_setting(
			array(
				'id'      => 'zipcode-main-box-integration',
				'name'    => __( 'Add ZIP location to quick search', 'wpbdp-zipcodesearch' ),
				'type'    => 'checkbox',
				'default' => false,
				'group'   => 'zipsearch/general',
				'class'   => 'wpbdp-half',
			)
		);

		wpbdp_register_setting(
			array(
				'id'      => 'zipcode-quick-search-radius',
				'name'    => __( 'Default search radius', 'wpbdp-zipcodesearch' ),
				'type'    => 'text',
				'default' => '5',
				'desc'    => _x( 'In the same units specified above.', 'settings', 'wpbdp-zipcodesearch' ),
				'group'   => 'zipsearch/general',
				'class'   => 'wpbdp-half',
				'requirements' => array( 'zipcode-main-box-integration' ),
			)
		);

		wpbdp_register_settings_group( 'zipsearch/database', __( 'ZIP Database', 'wpbdp-zipcodesearch' ), 'search_settings' );
		wpbdp_register_setting(
			array(
				'id'       => 'zipsearch-database-in-use',
				'name'     => _x( 'Installed ZIP/Postal Code databases', 'admin settings', 'wpbdp-zipcodesearch' ),
				'type'     => 'callback',
				'callback' => array( $this->admin, 'manage_databases' ),
				'group'    => 'zipsearch/database',
			)
		);
		wpbdp_register_setting(
			array(
				'id'       => 'zipsearch-cache-status',
				'name'     => _x( 'Cache Status', 'admin settings', 'wpbdp-zipcodesearch' ),
				'type'     => 'callback',
				'callback' => array( $this->admin, 'cache_status' ),
				'group'    => 'zipsearch/database',
			)
		);
	}

	public function _shortcode( $atts ) {
		$a = shortcode_atts(
			array(
				'zip'           => null,
				'distance'      => 0.0,
				'distance_paid' => null,
				'listings'      => null,
				'max_paid'      => null,
				'featured'      => 'top',
			),
			$atts
		);

		if ( ! $a['zip'] ) {
			return;
		}

		$radius = max( 0.0, floatval( $a['distance'] ) );
		if ( is_numeric( $a['distance_paid'] ) ) {
			$radius = max( floatval( $a['distance_paid'] ), $radius );
		}

		$results  = $this->find_listings(
			array(
				'center' => $a['zip'],
				'radius' => $radius,
			)
		);
		$post_ids = $this->sort_results( $results, $a );

		$html = '';

		$posts = get_posts(
			array(
				'numberposts'      => -1,
				'post__in'         => $post_ids ? $post_ids : array( -1 ),
				'post_type'        => WPBDP_POST_TYPE,
				'orderby'          => 'post__in',
				'suppress_filters' => false,
			)
		);

		$html  = '';
		$html .= '<div id="wpbdp-view-listings-page" class="wpbdp-view-listings-page wpbdp-page">';
		$html .= '<div class="wpbdp-page-content">';

		if ( ! $posts ) {
			$html .= _x( 'No listings found.', 'templates', 'wpbdp-zipcodesearch' );
		} else {
			$html .= '<div class="listings">';

			foreach ( $posts as &$p ) {
				$html .= wpbdp_render_listing( $p->ID, 'excerpt' );
			}

			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	public function validate_zip_field_setting( $setting, $newvalue, $oldvalue = null ) {
		if ( $newvalue != $oldvalue ) {
			$this->delete_listing_cache( 'all' );
		}

		return $newvalue;
	}

	public function delete_listing_cache( $postidordb ) {
		global $wpdb;

		if ( is_numeric( $postidordb ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpbdp_zipcodes_listings WHERE listing_id = %d", $postidordb ) );
		} else {
			if ( $postidordb == 'all' ) {
				$wpdb->query( "DELETE FROM {$wpdb->prefix}wpbdp_zipcodes_listings" );
			} elseif ( $postidordb == 'null' || $postidordb == 'NULL' ) {
				$wpdb->query( "DELETE FROM {$wpdb->prefix}wpbdp_zipcodes_listings WHERE zip IS NULL" );
			} else {
				// Delete cache for everything since being DB-specific takes a lot of time.
				return $this->delete_listing_cache( 'all' );
			}
		}
	}

	/**
	 * @since 5.1
	 */
	public function zip_field_get_value( $value, $post_id, $field ) {
		if ( empty( $value ) || $this->get_zip_field_id() != $field->get_id() ) {
			return $value;
		}
		$value = array(
			'zip'     => $value,
			'country' => get_post_meta( $post_id, '_wpbdp[fields][' . $field->get_id() . '][country]', true ),
		);
		return $value;
	}
	/**
	 * @since 5.1
	 */
	public function zip_field_convert_input( $_, $input, $field ) {
		if ( $this->get_zip_field_id() != $field->get_id() ) {
			return null;
		}
		return $input;
	}
	/**
	 * @since 5.1
	 */
	public function zip_field_store_value_override( $override, $field, $post_id, $value ) {
		if ( $this->get_zip_field_id() != $field->get_id() ) {
			return false;
		}
		if ( ! is_array( $value ) ) {
			$value = array(
				'zip'     => $value,
				'country' => '',
			);
		}

		$zip     = trim( $value['zip'] );
		$country = trim( $value['country'] );
		if ( ! $zip ) {
			update_post_meta( $post_id, '_wpbdp[fields][' . $field->get_id() . ']', '' );
			delete_post_meta( $post_id, '_wpbdp[fields][' . $field->get_id() . '][country]' );
			// This deletes and set the cache again to prevent the notification.
			$this->cache_listing_zipcode( $post_id );
			return true;
		}
		update_post_meta( $post_id, '_wpbdp[fields][' . $field->get_id() . ']', $zip );
		update_post_meta( $post_id, '_wpbdp[fields][' . $field->get_id() . '][country]', $country );
		$this->cache_listing_zipcode( $post_id );
		return true;
	}
	/**
	 * @since 5.1
	 */
	public function zip_field_attrs( $field, $value, $display_context ) {
		if ( $this->get_zip_field_id() != $field->get_id() || ! $this->get_installed_databases() || ! in_array( $display_context, array( 'submit', 'admin-submit', 'search' ) ) ) {
			return;
		}
		$field->css_classes[]                   = 'wpbdp-zipcodesearch-autocomplete';
		$field->html_attributes['data-ajaxurl'] = esc_url( add_query_arg( 'action', 'wpbdp-zipcodesearch-code-search', wpbdp_ajaxurl() ) );
	}
	/**
	 * @since 5.1
	 */
	public function zip_field_input_name( $name, $field, $context, $extra, $settings ) {
		if ( $this->get_zip_field_id() != $field->get_id() ) {
			return $name;
		}
		$name = 'listingfields[' . $field->get_id() . '][zip]';
		return $name;
	}
	/**
	 * @since 5.1
	 */
	public function zip_field_normalize_input_value( $value, $listing_id, $field ) {
		if ( $this->get_zip_field_id() != $field->get_id() || ! is_array( $value ) ) {
			return $value;
		}
		return $value['zip'];
	}
	/**
	 * @since 5.1
	 */
	public function zip_field_country_hint( $field_inner, &$field, $value, $render_context, &$extra = null ) {
		$html = $field_inner;
		if ( $this->get_zip_field_id() != $field->get_id() || ! $this->get_installed_databases() ) {
			return $html;
		}

		$country = ( is_array( $value ) && isset( $value['country'] ) ) ? $value['country'] : '';

		$searched = wpbdp_get_var( array( 'param' => 'zipcodesearch' ), 'request' );
		if ( ! $country && ! empty( $searched['country'] ) ) {
			$country = $searched['country'];
		}

		if ( in_array( $render_context, array( 'submit', 'admin-submit', 'search' ) ) ) {
			$html .= '<input type="hidden" class="country-hint" name="listingfields[' . $field->get_id() . '][country]" value="' . $country . '" />';
		}

		if ( 'search_box' === $render_context ) {
			$html .= '<input type="hidden" class="country-hint" name="zipcodesearch[country]" value="' . $country . '" />';
		}

		return $html;
	}

	public function search_form_integration( $field_inner, &$field, $value, $render_context, &$extra = null ) {
		$field_id = intval( wpbdp_get_option( 'zipcode-field', 0 ) );

		if ( $render_context !== 'search' || ! $field_id || $field_id != $field->get_id() || ! $this->get_installed_databases() ) {
			return $field_inner;
		}

		$html = '';

		$search_modes = wpbdp_get_option( 'zipcode-available-search-modes', array() );
		$args         = array(
			'zip'          => '',
			'search_modes' => $search_modes,
			'mode'         => reset( $search_modes ),
			'radius'       => wpbdp_get_option( 'zipcode-quick-search-radius', 0 ),
			'id'           => $field->get_id(),
		);

		$searched     = wpbdp_get_var( array( 'param' => 'zipcodesearch' ), 'request' );
		$field_values = wpbdp_get_var( array( 'param' => 'listingfields' ), 'request' );

		if ( in_array( "$field_id", wpbdp_get_option( 'quick-search-fields' ), true ) ) {
			$args['zip'] = wpbdp_get_var( array( 'param' => 'kw' ), 'request' );
		}

		if ( ! empty( $searched['zip'] ) ) {
			$args['zip'] = $searched['zip'];
		}

		if ( ! empty( $field_values[ $field_id ] ) ) {
			$args = array_merge( $args, $field_values[ $field_id ] );
		}

		$html .= '<input type="text" id="wpbdp-field-' . esc_attr( $args['id'] ) . '" name="listingfields[' . esc_attr( $args['id'] ) . '][zip]" value="' . esc_attr( $args['zip'] ) . '" size="5" class="zipcode-search-zip" />';

		$html .= $this->show_zip_search( $args );

		return $html;
	}

	/**
	 * Show the toggle between exact zip and distance.
	 *
	 * @since 5.4.2
	 */
	public function show_zip_search( $args ) {
		$html = '';
		if ( in_array( 'zip', $args['search_modes'], true ) && in_array( 'distance', $args['search_modes'], true ) ) {
			$html .= '<label class="wpbdp-display-block"><input type="radio" name="listingfields[' . esc_attr( $args['id'] ) . '][mode]" value="zip" ' . checked( $args['mode'], 'zip', false ) . ' onchange="if (this.checked){ jQuery(\'.zipcode-search-distance-fields\').hide(); } " /> ' . _x( ' Only this ZIP', 'settings', 'wpbdp-zipcodesearch' ) . '</label>';
			$html .= '<label class="wpbdp-display-block"><input type="radio" name="listingfields[' . esc_attr( $args['id'] ) . '][mode]" value="distance" ' . checked( $args['mode'], 'distance', false ) . ' onchange="if (this.checked){ jQuery(\'.zipcode-search-distance-fields\').show(); } " /> ' . esc_html__( 'Distance search', 'wpbdp-zipcodesearch' ) . '</label>';
		} elseif ( in_array( 'zip', $args['search_modes'], true ) ) {
			$html .= '<input type="hidden" name="listingfields[' . esc_attr( $args['id'] ) . '][mode]" value="zip" />';
		} elseif ( in_array( 'distance', $args['search_modes'], true ) ) {
			$html .= '<input type="hidden" name="listingfields[' . esc_attr( $args['id'] ) . '][mode]" value="distance" />';
		}

		if ( in_array( 'distance', $args['search_modes'], true ) ) {
			$html .= $this->zip_search_inputs( $args );
		}

		if ( ! empty( $args['echo'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $html;
		}

		return $html;
	}

	/**
	 * Show the distance from zip search field.
	 *
	 * @since 5.4.2
	 */
	private function zip_search_inputs( $args ) {
		$html    = '<div class="zipcode-search-distance-fields" style="' . ( $args['mode'] === 'zip' ? 'display: none;' : '' ) . '">';
		$html_id = 'wpbdp-field-' . $args['id'] . '-radius';

		if ( count( $args['search_modes'] ) < 2 ) {
			$html .= '<div class="wpbdp-search-field-label">';
			$html .= '<label for="' . esc_attr( $html_id ) . '">';
			$html .= esc_html__( 'Find listings within ', 'wpbdp-zipcodesearch' );
			$html .= '</label>';
			$html .= '</div>';
		}

		if ( wpbdp_get_option( 'zipcode-fixed-radius' ) && '' != wpbdp_get_option( 'zipcode-radius-options' ) ) {
			$html .= '<select name="listingfields[' . esc_attr( $args['id'] ) . '][radius]" id="' . esc_attr( $html_id ) . '" class="wpdbp-auto-width">';

			foreach ( explode( ',', wpbdp_get_option( 'zipcode-radius-options' ) ) as $r_ ) {
				$r     = round( floatval( $r_ ), 1 );
				$html .= '<option value="' . esc_attr( $r ) . '" ' . ( $r == floatval( $args['radius'] ) ? 'selected="selected"' : '' ) . '>' . $r . '</option>';
			}

			$html .= '</select>';
		} else {
			$html .= '<input type="text" name="listingfields[' . esc_attr( $args['id'] ) . '][radius]" id="' . esc_attr( $html_id ) . '" value="' . ( round( floatval( $args['radius'] ), 1 ) ) . '" size="5" class="wpdbp-auto-width" />';
		}

		$html .= esc_attr( ' ' . strtolower( wpbdp_zipcodesearch_radius_units_name() ) );
		$html .= '</div>';

		return $html;
	}

	public function configure_search( $res, $field, $query, $search ) {
		if ( $this->get_zip_field_id() != $field->get_id() ) {
			return $res;
		}

		if ( ! ( isset( $_GET['dosrch'] ) || $search->is_quick_search() ) ) {
			return $res;
		}

		if ( $search->is_quick_search() && ! wpbdp_get_option( 'zipcode-main-box-integration' ) ) {
			return $res;
		}

		if ( is_string( $query ) ) {
			$query = array(
				'zip'    => $query,
				'radius' => wpbdp_get_option( 'zipcode-quick-search-radius' ),
				'mode'   => 'distance',
			);
		}

		$res  = array();
		$args = array(
			'zip'     => trim( $query['zip'] ),
			'radius'  => max( 0.0, floatval( $query['radius'] ) ),
			'units'   => wpbdp_get_option( 'zipcode-units', 'miles' ),
			'mode'    => $query['mode'],
			'country' => trim( isset( $query['country'] ) ? $query['country'] : '' ),
		);
		if ( 'zip' == $args['mode'] ) {
			$args['radius'] = 0.0;
		}

		if ( ! $args['zip'] ) {
			return array();
		}

		$radius = ( 'kilometers' == $args['units'] ) ? $args['radius'] * 0.621371192 : $args['radius'];

		// TODO: for even faster queries we could JOIN with the correct tables instead of calculating post_ids first.
		$listings = $this->find_listings(
			array(
				'center'  => $args['zip'],
				'radius'  => $radius,
				'country' => $args['country'],
				'fields'  => 'ids',
			)
		);

		if ( ! $listings ) {
			$res['where'] = '1=0';
		} else {
			global $wpdb;
			$res['where'] = sprintf( "{$wpdb->posts}.ID IN (%s)", implode( ',', $listings ) );
		}

		return $res;
	}

	/**
	 * According to the modifictions referenced below, search_convert_input()
	 * should always return the the original value of the ZIP field.
	 *
	 * Changeset: https://github.com/drodenbaugh/BusinessDirectoryPlugin/compare/be3f16f1...ea5ceeb7
	 */
	public function search_convert_input( $converted_input, $val, $field ) {
		if ( $this->get_zip_field_id() != $field->get_id() ) {
			return $converted_input;
		}

		return $val;
	}

	public function field_is_empty_value( $is_empty, $value, $field ) {
		if ( $this->get_zip_field_id() == $field->get_id() && is_array( $value ) && empty( $value['zip'] ) ) {
			$is_empty = true;
		}

		return $is_empty;
	}

	public function change_search_order( $args, $search ) {
		if ( ! wpbdp_get_option( 'zipcode-force-order' ) || ! $this->get_zip_field_id() ) {
			return $args;
		}

		if ( ! isset( $args['post__in'] ) || ! $args['post__in'] || ( 1 == count( $args['post__in'] ) && 0 == $args['post__in'][0] ) ) {
			return $args;
		}

		$terms = $search->terms_for_field( $this->get_zip_field_id() );
		$term  = array_pop( $terms );

		if ( ! $term ) {
			return $args;
		}

		$center = $this->get_zipcode( is_string( $term ) ? $term : $term['zip'] );
		if ( ! $center ) {
			return $args;
		}

		$data = array();

		foreach ( $args['post__in'] as $pid ) {
			$zip = $this->get_zipcode( $this->get_listing_zipcode( $pid ) );

			if ( ! $zip ) {
				continue;
			}

			$data[ $pid ] = $zip;
		}

		if ( ! $data ) {
			return $args;
		}

		$this->sort_by_distance( $data, $center );

		$args['post__in'] = array_keys( $data );
		$args['orderby']  = 'post__in';
		$args['order']    = 'ASC';

		return $args;
	}

	/**
	 * @since 5.2.2
	 */
	public function is_zipcode_search( $searching ) {
		return $searching || ( ! empty( $_GET ) && ( ! empty( $_GET['location'] ) || ! empty( $_GET['zipcodesearch'] ) ) );
	}

	/**
	 * @since 5.0
	 */
	public function quick_search_request( $search, $request ) {
		$field_id = $this->get_zip_field_id();

		// No quick search or integration not enabled. Nothing to do.
		if ( ! isset( $request['zipcodesearch'] ) || ! $field_id || ! wpbdp_get_option( 'zipcode-main-box-integration' ) ) {
			return $search;
		}

		// Remove this field from search terms.
		$search = WPBDP__Listing_Search::tree_remove_field( $search, $field_id );

		// Add ZIP search.
		if ( ! empty( $request['zipcodesearch']['zip'] ) ) {
			$search[] = array( $field_id, $request['zipcodesearch'] );
		}

		return $search;
	}

	/**
	 * @since 5.0
	 */
	public function render_field_for_search( $args = null ) {
		static $uid = 0;
		$uid++;

		$defaults = array(
			'before'                        => '',
			'start_tag'                     => '<div class="wpbdp-zipcodesearch-search-unit">',
			'end_tag'                       => '</div>',
			'parts'                         => array( 'zip_field', 'mode_zip', 'mode_distance', 'distance_fields' ),
			'name'                          => array(
				'zip'    => '_x[zs_zip]',
				'mode'   => '_x[zs_mode]',
				'radius' => '_x[zs_radius]',
			),
			'before_radius_options'         => __( 'Find listings within ', 'wpbdp-zipcodesearch' ),
			'after_radius_options'          => strtolower( wpbdp_zipcodesearch_radius_units_name() ),
			'placeholder'                   => _x( 'ZIP Code', 'search field', 'wpbdp-zipcodesearch' ),
			'size'                          => 10,
			'zip'                           => '',
			'radius'                        => wpbdp_get_option( 'zipcode-quick-search-radius', 0 ),
			'mode'                          => 'zip',
			'radius_options'                => ( wpbdp_get_option( 'zipcode-fixed-radius' ) && '' != wpbdp_get_option( 'zipcode-radius-options' ) ) ? explode( ',', wpbdp_get_option( 'zipcode-radius-options' ) ) : '',
			'after'                         => '',
			'distance_fields_container'     => '',
			'distance_fields_container_end' => '',
			'echo'                          => false,
		);
		$args     = wp_parse_args( $args, $defaults );

		// Build parts array.
		$parts                     = array();
		$parts['zip_field']        = sprintf(
			'<input type="text" name="%s" value="%s" size="%d" class="wpbdp-zipcodesearch-zip" placeholder="%s"/>',
			is_array( $args['name'] ) ? $args['name']['zip'] : $args['name'] . '[zip]',
			$args['zip'],
			$args['size'],
			esc_attr( $args['placeholder'] )
		);
		$parts['mode_zip']         = sprintf(
			'<input type="radio" id="%s" class="mode-radio" name="%s" value="zip" %s />',
			'wpbdp-zipcodesearch-' . $uid . '-mode-zip',
			is_array( $args['name'] ) ? $args['name']['mode'] : $args['name'] . '[mode]',
			'zip' == $args['mode'] ? 'checked="checked"' : ''
		);
		$parts['mode_zip']        .= sprintf(
			'<label for="%s">' . _x( ' Only this ZIP', 'settings', 'wpbdp-zipcodesearch' ) . '</label>',
			'wpbdp-zipcodesearch-' . $uid . '-mode-zip'
		);
		$parts['mode_distance']    = sprintf(
			'<input type="radio" id="%s" name="%s" class="mode-radio" value="distance" %s />',
			'wpbdp-zipcodesearch-' . $uid . '-mode-distance',
			is_array( $args['name'] ) ? $args['name']['mode'] : $args['name'] . '[mode]',
			'distance' == $args['mode'] ? 'checked="checked"' : ''
		);
		$parts['mode_distance']   .= sprintf(
			'<label for="%s">' . esc_html__( 'Distance search', 'wpbdp-zipcodesearch' ) . '</label>',
			'wpbdp-zipcodesearch-' . esc_attr( $uid ) . '-mode-distance'
		);
		$parts['distance_fields']  = $args['distance_fields_container'] ? $args['distance_fields_container'] : sprintf(
			'<div class="wpbdp-zipcodesearch-distance-fields %s">',
			'zip' == $args['mode'] ? 'hidden' : ''
		);
		$parts['distance_fields'] .= $args['before_radius_options'];

		if ( $args['radius_options'] ) {
			$parts['distance_fields'] .= sprintf(
				'<select name="%s">',
				is_array( $args['name'] ) ? $args['name']['radius'] : $args['name'] . '[radius]'
			);

			foreach ( $args['radius_options'] as $r_ ) {
				$r                         = round( floatval( $r_ ), 1 );
				$parts['distance_fields'] .= '<option value="' . $r . '" ' . ( $r == floatval( $args['radius'] ) ? 'selected="selected"' : '' ) . '>' . $r . '</option>';
			}

			$parts['distance_fields'] .= '</select>';
		} else {
			$parts['distance_fields'] .= sprintf(
				'<input type="text" name="%s" value="%s" size="5" />',
				is_array( $args['name'] ) ? $args['name']['radius'] : $args['name'] . '[radius]',
				round( floatval( $args['radius'] ), 1 )
			);
		}
		$parts['distance_fields'] .= ' ' . $args['after_radius_options'] . ' ';
		$parts['distance_fields'] .= $args['distance_fields_container_end'] ? $args['distance_fields_container_end'] : '</div>';

		// Build final output.
		$html  = '';
		$html .= $args['before'];
		$html .= $args['start_tag'];

		foreach ( $args['parts'] as $part_name ) {
			$html .= ! empty( $args[ 'before_' . $part_name ] ) ? $args[ 'before_' . $part_name ] : '';
			$html .= $parts[ $part_name ];
			$html .= ! empty( $args[ 'after_' . $part_name ] ) ? $args[ 'after_' . $part_name ] : '';
		}

		if ( ! in_array( 'mode_zip', $args['parts'], true ) || ! in_array( 'mode_distance', $args['parts'], true ) ) {
			$html .= sprintf(
				'<input type="hidden" name="%s" value="%s" />',
				esc_attr( is_array( $args['name'] ) ? $args['name']['mode'] : $args['name'] . '[mode]' ),
				esc_attr( $args['mode'] )
			);
		}

		$html .= $args['end_tag'];
		$html .= $args['after'];

		if ( $args['echo'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $html;
		}
		return $html;
	}

	/**
	 * @since 5.1
	 */
	public function ajax_code_search() {
		$code = trim( wpbdp_get_var( array( 'param' => 'term' ), 'request' ) );
		if ( ! $code ) {
			wp_send_json_error();
		}
		$databases = $this->get_supported_databases();
		$codes     = array();
		foreach ( $this->find_zipcodes( $code ) as $c ) {
			$codes[] = array(
				'label'   => $c->zip . ' (' . $databases[ $c->country ][0] . ')',
				'value'   => $c->zip,
				'country' => $c->country,
			);
		}
		wp_send_json( $codes );
	}


	/**
	 * @since 5.0
	 */
	public function search_box_fields() {
		$showing_regions = function_exists( 'wpbdp_regions' ) && wpbdp_get_option( 'regions-main-box-integration' );
		if ( $showing_regions || ! wpbdp_get_option( 'zipcode-main-box-integration' ) ) {
			return;
		}

		$zip_field = $this->get_zip_field();

		if ( ! $zip_field || ! $this->get_installed_databases() ) {
			return;
		}

		$args = array(
			'start_tag'                     => '<div class="box-col wpbdp-zipcodesearch-search-unit"><div class="box-row cols-2">',
			'end_tag'                       => '</div></div>',
			'name'                          => 'zipcodesearch',
			'placeholder'                   => __( 'ZIP Code', 'wpbdp-zipcodesearch' ),
			'parts'                         => array( 'distance_fields', 'zip_field' ),
			'mode'                          => 'distance',
			'before_radius_options'         => '',
			'after_radius_options'          => '',
			'before_zip_field'              => '<div class="box-col zip-field wpbdp-zipcodesearch-autocomplete" data-ajaxurl="' . esc_url( add_query_arg( 'action', 'wpbdp-zipcodesearch-code-search', wpbdp_ajaxurl() ) ) . '">',
			'after_zip_field'               => $this->zip_field_country_hint( '', $zip_field, array( 'country' => '' ), 'search_box' ) . '</div>',
			'distance_fields_container'     => '<div class="box-col distance-field-wrapper"><div class="box-row cols-2"><div class="box-col distance-field">',
			'distance_fields_container_end' => '</div><div class="box-col unit-label">' .
				sprintf(
					/* translators: %s radius unit name */
					esc_html__( ' %s of ', 'wpbdp-zipcodesearch' ),
					wpbdp_zipcodesearch_radius_units_name()
				) .
				'</div></div></div>',
			'echo'                          => true,
		);

			// 'after_radius_options' => sprintf( _x( '%s of ', 'main box', 'wpbdp-zipcodesearch' ), wpbdp_get_option( 'zipcode-units' ) )
		$this->render_field_for_search( $args );
	}

	/*
	 * API
	 */

	/**
	 * @since 5.0
	 */
	public function get_zip_field_id() {
		return intval( wpbdp_get_option( 'zipcode-field', 0 ) );
	}

	/**
	 * @since 5.0
	 */
	public function get_zip_field() {
		$id = $this->get_zip_field_id();

		if ( ! $id ) {
			return false;
		}

		$field = WPBDP_Form_Field::get( $id );

		if ( ! $field ) {
			return false;
		}

		return $field;
	}

	public function check_db() {
		if ( ! $this->get_installed_databases() ) {
			return false;
		}

		return true;
	}

	public function get_no_cached_listings() {
		global $wpdb;
		return intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpbdp_zipcodes_listings" ) );
	}

	// TODO: this function should be more correct since not all listings have zip codes or not even a database is installed.
	public function is_cache_valid() {
		global $wpdb;
		// $invalid_cache = intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != %s", WPBDP_POST_TYPE, 'auto-draft' ) ) ) > $this->get_no_cached_listings();
		$query  = $wpdb->prepare( "SELECT 1 AS invalid FROM {$wpdb->posts} p WHERE p.ID NOT IN (SELECT listing_id FROM {$wpdb->prefix}wpbdp_zipcodes_listings) AND p.post_type = %s AND p.post_status = %s LIMIT 1", WPBDP_POST_TYPE, 'publish' );
		$result = $wpdb->get_var( $query ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $wpdb->last_error ) {
			return false;
		}

		return intval( $result ) == 0;
	}

	public function cache_listing_zipcode( $post_id ) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpbdp_zipcodes_listings WHERE listing_id = %d", $post_id ) );
		$field = $this->get_zip_field();

		if ( ! $field ) {
			return null;
		}

		$zipcode      = null;
		$zip_plain    = $field ? $field->plain_value( $post_id ) : '';
		$country_hint = get_post_meta( $post_id, '_wpbdp[fields][' . $field->get_id() . '][country]', true );
		if ( $zip_plain ) {
			$zipcode = $this->get_zipcode( $zip_plain, $country_hint );
		}

		$data               = array();
		$data['listing_id'] = $post_id;

		if ( $zipcode ) {
			$data['zip']       = $zipcode->zip;
			$data['latitude']  = $zipcode->latitude;
			$data['longitude'] = $zipcode->longitude;
		}

		$wpdb->insert( $wpdb->prefix . 'wpbdp_zipcodes_listings', $data );
		update_post_meta( $post_id, '_wpbdp[fields][' . $field->get_id() . '][country]', $zipcode ? $zipcode->country : '' );

		return true;
	}

	public function get_listing_zipcode( $post_id ) {
		global $wpdb;

		if ( $this->is_cache_valid() ) {
			return $wpdb->get_var( $wpdb->prepare( "SELECT zip FROM {$wpdb->prefix}wpbdp_zipcodes_listings WHERE listing_id = %d", $post_id ) );
		} else {
			$field = wpbdp_get_formfield( intval( wpbdp_get_option( 'zipcode-field', 0 ) ) );

			if ( $field ) {
				return $field->plain_value( $post_id );
			}
		}

		return null;
	}

	public function get_latlng_distance( $p1, $p2, $miles = true ) {
		if ( ! is_object( $p1 ) || ! is_object( $p2 ) ) {
			return null;
		}

		$lat1 = deg2rad( $p1->latitude );
		$lng1 = deg2rad( $p1->longitude );
		$lat2 = deg2rad( $p2->latitude );
		$lng2 = deg2rad( $p2->longitude );

		$r    = self::EARTH_RADIUS; // mean radius of Earth in km.
		$dlat = $lat2 - $lat1;
		$dlng = $lng2 - $lng1;
		$a    = sin( $dlat / 2 ) * sin( $dlat / 2 ) + cos( $lat1 ) * cos( $lat2 ) * sin( $dlng / 2 ) * sin( $dlng / 2 );
		$c    = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
		$km   = $r * $c;

		return ( $miles ? ( $km * 0.621371192 ) : $km );
	}

	/**
	 * @param string $zip
	 */
	public function find_zipcodes( $zip ) {
		global $wpdb;
		if ( ! $zip ) {
			return array();
		}
		$zip = trim( strtolower( str_replace( ' ', '', $zip ) ) );
		$zip = trim( $zip, '-' );
		if ( preg_match( '/(\d{5})-\d{4}/', $zip, $matches ) ) {
			$zip = $matches[1];
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpbdp_zipcodes WHERE zip = %s", $zip ) );
	}

	/**
	 * Returns information for a given zip code from the database.
	 * Spaces and case are ignored.
	 *
	 * @param  string $zip the ZIP code.
	 * @return object an object with ZIP code information as properties (zip, latitude, longitude, country, city, state) or NULL if nothing was found.
	 */
	public function get_zipcode( $zip, $country_hint = '' ) {
		global $wpdb;

		if ( ! $zip ) {
			return null;
		}

		$zip = trim( strtolower( str_replace( ' ', '', $zip ) ) );
		$zip = trim( $zip, '-' );

		// Special treatment for USA's ZIP+4 codes.
		if ( preg_match( '/(\d{5})-\d{4}/', $zip, $matches ) ) {
			$zip = $matches[1];
		}

		// TODO: Databses should be extensions for the ZIP Code Search module
		// using hooks to register themselves and match codes according to each
		// contry's rules.
		//
		// The database file would not be included, but could be downloaded with
		// the click of a button.
		// Special treatment for BR's Postal Codes.
		if ( preg_match( '/\d{5}-\d{3}/', $zip ) ) {
			$zip = str_replace( '-', '', $zip );
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpbdp_zipcodes WHERE zip = %s AND country = %s", $zip, $country_hint ) );

		if ( ! $row ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpbdp_zipcodes WHERE zip = %s", $zip ) );
		}
		return $row;
	}

	/**
	 * Clever algorithm to determine area around a given location on earth based
	 * on http://mathforum.org/library/drmath/view/66987.html
	 *
	 * @param $radius float The search radius in miles.
	 */
	public function get_lat_long_rect( $p0, $radius ) {
		$max_radius = asin( cos( deg2rad( $p0->latitude ) ) ) * self::EARTH_RADIUS;
		$radius_km  = min( $radius * self::MI_TO_KM, $max_radius - 0.00000000001 );

		// max_lon = lon1 + arcsin(sin(D/R)/cos(lat1)).
		$max_lon = rad2deg( deg2rad( $p0->longitude ) + asin( sin( $radius_km / self::EARTH_RADIUS ) / cos( deg2rad( $p0->latitude ) ) ) );

		// min_lon = lon1 - arcsin(sin(D/R)/cos(lat1)).
		$min_lon = rad2deg( deg2rad( $p0->longitude ) - asin( sin( $radius_km / self::EARTH_RADIUS ) / cos( deg2rad( $p0->latitude ) ) ) );

		// max_lat = lat1 + (180/pi)(D/R).
		$max_lat = $p0->latitude + ( 180.0 / M_PI ) * ( $radius_km / self::EARTH_RADIUS );

		// min_lat = lat1 - (180/pi)(D/R).
		$min_lat = $p0->latitude - ( 180.0 / M_PI ) * ( $radius_km / self::EARTH_RADIUS );

		// Add some tolerance.
		$min_lon = round( $min_lon - 0.05, 2 );
		$max_lon = round( $max_lon + 0.05, 2 );
		$min_lat = round( $min_lat - 0.05, 2 );
		$max_lat = round( $max_lat + 0.05, 2 );

		return (object) array(
			'longitude' => array( $min_lon, $max_lon ),
			'latitude'  => array( $min_lat, $max_lat ),
		);
	}

	// Radius in MI.
	public function find_listings( $args ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'center'  => null,
				'radius'  => 0.0,
				'fields'  => 'all',
				'country' => '',
			)
		);
		extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		$results = array();

		$field_id = intval( wpbdp_get_option( 'zipcode-field', 0 ) );

		if ( ! $field_id ) {
			return $results;
		}

		$center = $this->get_zipcode( $center, $country );
		if ( ! $center ) {
			return $results;
		}

		if ( $radius == 0.0 ) {
			if ( $this->is_cache_valid() ) {
				if ( $fields == 'ids' ) {
					$results = $wpdb->get_col( $wpdb->prepare( "SELECT listing_id FROM {$wpdb->prefix}wpbdp_zipcodes_listings WHERE zip = %s", $center->zip ) );
				} else {
					$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpbdp_zipcodes_listings WHERE zip = %s", $center->zip ) );
				}
			} else {
				if ( $fields == 'ids' ) {
					$results = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->prefix}wpbdp_zipcodes zc ON pm.meta_value = zc.zip WHERE pm.meta_key = %s AND zc.zip = %s",
							'_wpbdp[fields][' . $field_id . ']',
							$center->zip
						)
					);
				} else {
					$results = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT DISTINCT pm.post_id AS listing_id, zc.zip, zc.latitude, zc.longitude FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->prefix}wpbdp_zipcodes zc ON pm.meta_value = zc.zip WHERE pm.meta_key = %s AND zc.zip = %s",
							'_wpbdp[fields][' . $field_id . ']',
							$center->zip
						)
					);
				}
			}
		} else {
			$rect = $this->get_lat_long_rect( $center, $radius );

			if ( $this->is_cache_valid() ) {
				// Use cache (faster).
				if ( $fields == 'ids' ) {
					$query   = $wpdb->prepare( "SELECT listing_id FROM {$wpdb->prefix}wpbdp_zipcodes_listings WHERE zip IS NOT NULL AND longitude IS NOT NULL AND latitude IS NOT NULL AND longitude >= %f AND longitude <= %f AND latitude >= %f AND latitude <= %f", $rect->longitude[0], $rect->longitude[1], $rect->latitude[0], $rect->latitude[1] );
					$results = $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				} else {
					$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpbdp_zipcodes_listings WHERE zip IS NOT NULL AND longitude IS NOT NULL AND latitude IS NOT NULL AND longitude >= %f AND longitude <= %f AND latitude >= %f AND latitude <= %f", $rect->longitude[0], $rect->longitude[1], $rect->latitude[0], $rect->latitude[1] ) );
				}
			} else {
				// Perform slower query.
				$rect = $this->get_lat_long_rect( $center, $radius );

				if ( $fields == 'ids' ) {
					$results = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->prefix}wpbdp_zipcodes zc ON pm.meta_value = zc.zip WHERE pm.meta_key = %s AND zc.latitude >= %f AND zc.latitude <= %f AND zc.longitude >= %f AND zc.longitude <= %f",
							'_wpbdp[fields][' . $field_id . ']',
							$rect->latitude[0],
							$rect->latitude[1],
							$rect->longitude[0],
							$rect->longitude[1]
						)
					);
				} else {
					$results = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT DISTINCT pm.post_id AS listing_id, zc.zip, zc.latitude, zc.longitude FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->prefix}wpbdp_zipcodes zc ON pm.meta_value = zc.zip WHERE pm.meta_key = %s AND zc.latitude >= %f AND zc.latitude <= %f AND zc.longitude >= %f AND zc.longitude <= %f",
							'_wpbdp[fields][' . $field_id . ']',
							$rect->latitude[0],
							$rect->latitude[1],
							$rect->longitude[0],
							$rect->longitude[1]
						)
					);
				}
			}
		}

		// Filter results checking accuracy.
		$def_results = array();

		foreach ( $results as $r ) {
			if ( 0.0 != $radius ) {
				$zipcode = $this->get_zipcode( $this->get_listing_zipcode( $r ), $country );

				if ( ! $zipcode ) {
					continue;
				}

				if ( $this->get_latlng_distance( $center, $zipcode ) > $radius ) {
					continue;
				}
			}

			if ( 0.0 == $radius ) {
				if ( $country && $country != get_post_meta( $r, '_wpbdp[fields][' . $this->get_zip_field_id() . '][country]', true ) ) {
					continue;
				}
			}

			$def_results[] = $r;
		}

		$results = $def_results;

		return $results;
	}

	private function sort_results( $results, $args ) {
		if ( ! $results ) {
			return array();
		}

		$res    = array();
		$paid   = array();
		$normal = array();

		$zip = $this->get_zipcode( $args['zip'] );

		foreach ( $results as $r ) {
			$is_paid = $this->listing_is( $r->listing_id, 'paid' );

			if ( $is_paid ) {
				if ( $args['distance_paid'] && ( $this->get_latlng_distance( $zip, $r ) > $args['distance_paid'] ) ) {
					continue;
				}

				$r->normal   = false;
				$r->paid     = true;
				$r->featured = $this->listing_is( $r->listing_id, 'sticky' );

				$paid[] = $r;
			} else {
				if ( $this->get_latlng_distance( $zip, $r ) > $args['distance'] ) {
					continue;
				}

				$r->normal   = true;
				$r->paid     = false;
				$r->featured = false;

				$normal[] = $r;
			}
		}

		// sort by distance.
		$this->sort_by_distance( $paid, $zip );
		$this->sort_by_distance( $normal, $zip );

		$max_paid = intval( $args['max_paid'] );
		if ( $max_paid > 0 ) {
			$paid = array_slice( $paid, 0, $max_paid );
		}

		// handle 'featured attribute'.
		if ( $args['featured'] == 'top' || $args['featured'] == 'bottom' ) {
			// sort paid: featured first.
			$listings_f = array();
			$listings_p = array();

			foreach ( $paid as &$p ) {
				if ( $p->featured ) {
					$listings_f[] = $p;
				} else {
					$listings_p[] = $p;
				}
			}

			if ( $args['featured'] == 'top' ) {
				$listings = array_merge( $listings_f, $listings_p, $normal );
			} elseif ( $args['featured'] == 'bottom' ) {
				$listings = array_merge( $listings_p, $normal, $listings_f );
				wpbdp_debug( $listings );
			}
		} elseif ( $args['featured'] == 'inline' ) {
			$listings = array_merge( $paid, $normal );
			$this->sort_by_distance( $listings, $zip );
		}

		foreach ( $listings as $p ) {
			$res[] = intval( $p->listing_id );
		}

		return array_slice( $res, 0, $args['listings'] );
	}

	public function sort_by_distance( &$listings, $center ) {
		$sorter              = new _WPBDP_DistanceSorter();
		$sorter->center      = $center;
		$sorter->distance_cb = array( $this, 'get_latlng_distance' );

		uasort( $listings, array( $sorter, 'sort' ) );
	}

	public function listing_is( $listing_id, $condition = 'sticky' ) {
		$listing = wpbdp_get_listing( $listing_id );

		if ( $condition == 'sticky' ) {
			return $listing->get_sticky_status() == 'sticky';
		} elseif ( $condition == 'non-sticky' ) {
			return $listing->get_sticky_status() == 'normal';
		} elseif ( $condition == 'paid' ) {
			if ( $this->listing_is( $listing_id, 'sticky' ) ) {
				return true;
			}

			$fee = $listing->get_fee_plan();
			return $fee->fee_price > 0.0;
		}

		return false;
	}

	public function get_db_name( $dbid ) {
		$dbid      = strtolower( $dbid );
		$databases = $this->get_supported_databases();

		return isset( $databases[ $dbid ] ) ? $databases[ $dbid ][0] : $dbid;
	}

	public function get_supported_databases() {
		$databases       = array();
		$databases['au'] = array( _x( 'Australia', 'databases', 'wpbdp-zipcodesearch' ), '20160803' );
		$databases['at'] = array( _x( 'Austria', 'databases', 'wpbdp-zipcodesearch' ), '20141103' );
		$databases['be'] = array( _x( 'Belgium', 'databases', 'wpbdp-zipcodesearch' ), '20150109' );
		$databases['br'] = array( _x( 'Brazil', 'databases', 'wpbdp-zipcodesearch' ), '20171223' );
		$databases['ca'] = array( _x( 'Canada', 'databases', 'wpbdp-zipcodesearch' ), '20131218' );
		$databases['cz'] = array( _x( 'Czech Republic', 'databases', 'wpbdp-zipcodesearch' ), '20160803' );
		$databases['ee'] = array( _x( 'Estonia', 'databases', 'wpbdp-zipcodesearch' ), '20160825' );
		$databases['fi'] = array( _x( 'Finland', 'databases', 'wpbdp-zipcodesearch' ), '20160825' );
		$databases['fr'] = array( _x( 'France', 'databases', 'wpbdp-zipcodesearch' ), '20150529' );
		$databases['de'] = array( _x( 'Germany', 'databases', 'wpbdp-zipcodesearch' ), '20140820' );
		$databases['in'] = array( __( 'India', 'wpbdp-zipcodesearch' ), '20220729' );
		$databases['ie'] = array( _x( 'Ireland', 'databases', 'wpbdp-zipcodesearch' ), '20190914' );
		$databases['it'] = array( _x( 'Italy', 'databases', 'wpbdp-zipcodesearch' ), '20160621' );
		$databases['jp'] = array( _x( 'Japan', 'databases', 'wpbdp-zipcodesearch' ), '20180918' );
		$databases['li'] = array( _x( 'Liechtenstein', 'databases', 'wpbdp-zipcodesearch' ), '20150109' );
		$databases['lu'] = array( _x( 'Luxembourg', 'databases', 'wpbdp-zipcodesearch' ), '20181122' );
		$databases['my'] = array( _x( 'Malaysia', 'databases', 'wpbdp-zipcodesearch' ), '20150107' );
		$databases['mx'] = array( _x( 'Mexico', 'databases', 'wpbdp-zipcodesearch' ), '20160803' );
		$databases['nl'] = array( _x( 'Netherlands', 'databases', 'wpbdp-zipcodesearch' ), '20170421' );
		$databases['nz'] = array( _x( 'New Zealand', 'databases', 'wpbdp-zipcodesearch' ), '20160803' );
		$databases['no'] = array( _x( 'Norway', 'databases', 'wpbdp-zipcodesearch' ), '20230814' );
		$databases['ph'] = array( _x( 'Philippines', 'databases', 'wpbdp-zipcodesearch' ), '20180918' );
		$databases['ro'] = array( _x( 'Romania', 'databases', 'wpbdp-zipcodesearch' ), '20170222' );
		$databases['za'] = array( _x( 'South Africa', 'databases', 'wpbdp-zipcodesearch' ), '20170228' );
		$databases['es'] = array( _x( 'Spain', 'databases', 'wpbdp-zipcodesearch' ), '20151105' );
		$databases['ch'] = array( _x( 'Switzerland', 'databases', 'wpbdp-zipcodesearch' ), '20150109' );
		$databases['uk'] = array( _x( 'United Kingdom (Great Britain)', 'databases', 'wpbdp-zipcodesearch' ), '20160803' );
		$databases['us'] = array( _x( 'United States', 'databases', 'wpbdp-zipcodesearch' ), '20161220' );

		return $databases;
	}

	public function get_installed_databases() {
		return get_option( 'wpbdp-zipcodesearch-db-list', array() );
	}

	public function delete_database( $db ) {
		global $wpdb;

		$databases = get_option( 'wpbdp-zipcodesearch-db-list', array() );
		unset( $databases[ $db ] );
		update_option( 'wpbdp-zipcodesearch-db-list', $databases );

		$this->delete_listing_cache( $db ); // Delete cache associated to this database.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpbdp_zipcodes WHERE country = %s", $db ) );
	}

}
