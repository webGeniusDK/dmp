<?php
/**
 * Plugin Name: Business Directory Google Maps
 * Description: Adds support for Google Maps for display in a directory listing. Map any set of fields to the address for use by Google Maps.
 * Plugin URI: https://businessdirectoryplugin.com
 * Version: 5.2.2
 * Author: Business Directory Team
 * Author URI: https://businessdirectoryplugin.com
 * Text Domain: wpbdp-googlemaps
 * Domain Path: /translations/
 *
 * @package WPBDP\GoogleMaps
 */

/**
 * Register and initialize the plugin.
 */
class WPBDP__Google_Maps {

    const GOOGLE_MAPS_JS_URL = 'https://maps.google.com/maps/api/js';

	/**
	 * Javascript file names.
	 *
	 * @var string
	 */
    private $maps_handle           = '';

	/**
	 * Scripts to remove.
	 *
	 * @var array
	 */
    private $maps_handles_remove   = array();

	/**
	 * Has map been initialized.
	 *
	 * @var bool
	 */
    private $maps_already_enqueued = false;

	/**
	 * Info to include on the map.
	 *
	 * @var array
	 */
    private $javascript_data = array();

	/**
	 * The locations to show on a map.
	 *
	 * @var array
	 */
    private $map_locations = array();

	/**
	 * Is map on.
	 *
	 * @var bool
	 */
    private $doing_map     = false;

	/**
	 * Show the settings link on the modules page.
	 *
	 * @var string
	 */
	public $settings_url = 'admin.php?page=wpbdp_settings&tab=googlemaps';

    public static function load( $modules ) {
        $modules->load( new self() );
    }

    public function __construct() {
        $this->id                  = 'googlemaps';
        $this->file                = __FILE__;
        $this->title               = 'Google Maps Module';
        $this->required_bd_version = '5.8';

		$this->version = '5.2.2';
    }

    public function init() {
        add_action( 'wpbdp_register_settings', array( &$this, 'register_settings' ) );
        add_action( 'wpbdp_modules_init', array( $this, '_setup_actions' ) );
        add_action( 'admin_notices', array( $this, '_admin_notices' ) );
        add_action( 'wpbdp_submit_listing_enqueue_resources', array( $this, 'enqueue_scripts' ) );
        add_filter( 'wpbdp_shortcodes', array( $this, '_register_shortcode' ) );

        add_action( 'wp_ajax_wpbdp_get_address_from_state', array( $this, 'get_address_from_state' ) );
        add_action( 'wp_ajax_nopriv_wpbdp_get_address_from_state', array( $this, 'get_address_from_state' ) );
    }

    public function fix_scripts_src( $src, $handle ) {
        global $wp_scripts, $wpbdp;

        if ( is_admin() || ! $this->is_google_maps_api_script( $src ) || ( wpbdp_is_request( 'frontend' ) && method_exists( $wpbdp, 'is_plugin_page' ) && ! $wpbdp->is_plugin_page() ) ) {
            return $src;
        }

        // Load dummy JS for other instances of Google Maps API, as to not break dependencies.
        if ( $this->maps_already_enqueued ) {
            return plugins_url( '/resources/dummy.js', __FILE__ );
        }

        if ( $this->maps_handle && $handle !== $this->maps_handle ) {
            return plugins_url( '/resources/dummy.js', __FILE__ );
        }

        $this->maps_already_enqueued = true;

        // Make sure the original src was used (no args removed, etc).
        $src = $wp_scripts->registered[ $handle ]->src;
        $src = str_replace( '&amp;', '&', $src );
        $src = remove_query_arg( 'libraries', $src );

        return $this->add_google_maps_api_query_args( $src );
    }

    private function is_google_maps_api_script( $src ) {
        if ( false !== stripos( $src, 'callback' ) ) {
            return false;
        }

        if ( false !== stripos( $src, 'maps.google.com/maps/api' ) ) {
            return true;
        }

        if ( false !== stripos( $src, 'maps.googleapis.com/maps/api' ) ) {
            return true;
        }

        return false;
    }

    private function add_google_maps_api_query_args( $url ) {
        $url = add_query_arg( 'v', '3', $url );

		$key = wpbdp_get_option( 'googlemaps-apikey' );
		if ( $key ) {
            $url = add_query_arg( 'key', $key, $url );
        }

		$map_id = $this->get_map_id();
		if ( $map_id ) {
			$url = add_query_arg( 'map_ids', $map_id, $url );
		}

        return $url;
    }

	/**
	 * Use the styling from the Google maps account by using the Map ID here.
	 *
	 * @since 5.2
	 */
	private function get_map_id() {

		/**
		 * Add the id of the map to take over styling.
		 *
		 * @since 5.2
		 */
		return apply_filters( 'wpbdp_map_id', '' );
	}

    public function fix_conflict_with_wp_google_maps_plugin() {
        if ( ! function_exists( 'wpgmza_deregister_scripts' ) ) {
            return;
        }

        remove_action( 'wp_enqueue_scripts', 'wpgmza_deregister_scripts', 999 );
        remove_action( 'wp_head', 'wpgmza_deregister_scripts', 999 );
        remove_action( 'init', 'wpgmza_deregister_scripts', 999 );
        remove_action( 'wp_footer', 'wpgmza_deregister_scripts', 999 );
        remove_action( 'wp_print_scripts', 'wpgmza_deregister_scripts', 999 );
    }

    public function _setup_actions() {
        if ( $this->maybe_deactivate() ) {
            return;
        }

        if ( is_admin() ) {
            require_once plugin_dir_path( __FILE__ ) . '/admin.php';
            $this->admin = new WPBDP__Google_Maps__Admin( $this );
        }

        $this->fix_conflict_with_wp_google_maps_plugin();

        add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_scripts' ), 9999 ); // We run with a huge priority in order to be last.
        add_action( 'script_loader_src', array( &$this, 'fix_scripts_src' ), 9999, 2 );
        add_action( 'wp_print_footer_scripts', array( $this, 'print_footer_scripts' ), 0 );
        add_action( 'save_post', array( $this, 'update_listing_geolocation' ), 20, 1 );
        add_action( 'wpbdp_save_listing', array( &$this, 'update_listing_geolocation' ), 20, 1 );

        add_filter( 'wpbdp_template_variables__single', array( &$this, 'single_template_variables' ) );
        add_filter( 'wpbdp_template_variables__listings', array( &$this, 'listings_template_variables' ) );
        add_filter( 'wpbdp_submit_section_googlemaps_place_chooser', array( &$this, '_show_place_chooser' ), 999, 2 );
        add_filter( 'wpbdp_submit_sections', array( &$this, '_submit_place_chooser' ), 999, 2 );
    }

	/**
	 * If the plugin is turned off, deactivate it and delete the setting.
	 *
	 * @since 5.2
	 *
	 * @return bool - true if turned off.
	 */
	private function maybe_deactivate() {
		$off = wpbdp_get_option( 'googlemaps-on' ) === 0;
		if ( ! $off ) {
			return false;
		}

		if ( function_exists( 'wpbdp_set_option' ) ) {
			wpbdp_set_option( 'googlemaps-on', true );
			deactivate_plugins( plugin_basename( __FILE__ ) );
		}

		return true;
	}

    /**
	 * Which shortcodes should be processed.
	 *
     * @since 3.6.5
     */
    public function _register_shortcode( $shortcodes ) {
        /*
         * WordPress Shortcode:
         *  [businessdirectory-map], [business-directory-map]
         * Used for:
         *  Shows the Map with markers for listings on it.
         * Parameters:
         *  - category What category to use for filtering. (Allowed Values: A valid Region name already configured under Directory Admin -> Manage Regions)
         *  - region   What category to use for filtering. (Allowed Values: A valid Region name already configured under Directory Admin -> Manage Regions)
         * Example:
         *  - Display a map of restaurants in New York:
         *
         *    `[businessdirectory-map region="New York" category="Restaurants"]`
         *
         */
        $shortcodes['bd-map']                 = array( $this, 'map_shortcode' );
        $shortcodes['businessdirectory-map']  = array( $this, 'map_shortcode' );
        $shortcodes['business-directory-map'] = array( $this, 'map_shortcode' );

        return $shortcodes;
    }

    /**
	 * Process the maps shortcode.
	 *
     * @since 3.6.5
     */
    public function map_shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'category' => false,
                'region'   => false,
            ),
            $atts
        );

        $term        = false;
        $region_term = false;
		$category    = $atts['category'];
		$region      = $atts['region'];

        if ( $category ) {
            foreach ( array( 'id', 'name', 'slug' ) as $field ) {
				$term = get_term_by( $field, $category, WPBDP_CATEGORY_TAX );
				if ( $term ) {
                    break;
                }
            }
        }

        if ( ! function_exists( 'wpbdp_regions_taxonomy' ) ) {
            $region = false;
        }

        if ( $region ) {
            foreach ( array( 'id', 'name', 'slug' ) as $field ) {
				$region_term = get_term_by( $field, $region, wpbdp_regions_taxonomy() );
				if ( $region_term ) {
                    break;
                }
            }
        }

        if ( ( $region && ! $region_term ) || ( $category && ! $term ) ) {
            return '';
        }

        $args = array(
            'post_type'        => WPBDP_POST_TYPE,
            'fields'           => 'ids',
            'posts_per_page'   => -1,
            'post_status'      => 'publish',
            'tax_query'        => array(),
            'suppress_filters' => false,
        );

        if ( $term && $region_term ) {
            $args['tax_query']['relation'] = 'AND';
        }

        if ( $term ) {
            $args['tax_query'][] = array(
                'taxonomy'         => WPBDP_CATEGORY_TAX,
                'field'            => 'term_id',
                'terms'            => (int) $term->term_id,
                'include_children' => true,
            );
        }

        if ( $region_term ) {
            $args['tax_query'][] = array(
                'taxonomy'         => wpbdp_regions_taxonomy(),
                'field'            => 'term_id',
                'terms'            => (int) $region_term->term_id,
                'include_children' => true,
            );
        }
        $listings = get_posts( $args );

        $this->_doing_map_on();
        foreach ( $listings as $post_id ) {
            $this->add_listing_to_map( $post_id );
        }

        $html = $this->map();

        return $html;
    }

    public function enqueue_scripts() {
        global $wpbdp;

        if ( method_exists( $wpbdp, 'is_plugin_page' ) && ! $wpbdp->is_plugin_page() ) {
            return;
        }

		$key     = wpbdp_get_option( 'googlemaps-apikey' );
		$cluster = wpbdp_get_option( 'googlemaps-marker-cluster' );

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
        wp_enqueue_style(
            'wpbdp-googlemaps-css',
            plugins_url( '/resources/googlemaps' . $min . '.css', __FILE__ ),
            array(),
            $this->version
        );

        $maps_handle = $this->obtain_google_maps_handle();

        if ( ! $maps_handle ) {
            wp_register_script(
                'googlemaps-api',
                $this->add_google_maps_api_query_args( self::GOOGLE_MAPS_JS_URL ),
                array(),
				1,
				true
            );
            $maps_handle = 'googlemaps-api';
        }

		wp_register_script(
			'oms-js',
			plugins_url( '/resources/oms.min.js', __FILE__ ),
			array( $maps_handle ),
			'1.0.3',
			true
		);

		wp_register_script(
			'mc-js',
			plugins_url( '/resources/marker-clusterer/marker-clusterer.min.js', __FILE__ ),
			array( $maps_handle ),
			'1.0.3',
			true
		);

		$requires = array( 'jquery', 'oms-js' );
		if ( $cluster ) {
			$requires[] = 'mc-js';
		}
        wp_enqueue_script(
            'wpbdp-googlemaps-js',
            plugins_url( '/resources/googlemaps' . $min . '.js', __FILE__ ),
            $requires,
            $this->version,
            true
        );

        if ( wpbdp_get_option( 'googlemaps-fields-latlong-enabled' ) ) {
            wp_enqueue_style(
                'wpbdp-googlemaps-place-chooser-css',
                plugins_url( '/resources/place-chooser' . $min . '.css', __FILE__ ),
                array(),
                $this->version
            );

            wp_enqueue_script(
                'wpbdp-googlemaps-place-chooser-js',
                plugins_url( '/resources/place-chooser' . $min . '.js', __FILE__ ),
                array( 'jquery' ),
                $this->version,
                true
            );

            wp_localize_script(
                'wpbdp-googlemaps-place-chooser-js',
                'WPBDP_googlemaps_place_chooser',
                array(
                    'l10n'    => array(
                        'address'      => __( 'Address', 'wpbdp-googlemaps' ),
                        'search'       => __( 'Search', 'wpbdp-googlemaps' ),
                        'return'       => __( 'Return', 'wpbdp-googlemaps' ),
                        'latitude'     => __( 'Lat.', 'wpbdp-googlemaps' ),
                        'longitude'    => __( 'Long.', 'wpbdp-googlemaps' ),
                        'set_location' => __( 'Set Location', 'wpbdp-googlemaps' ),
                    ),
                    'ajaxurl' => admin_url( 'admin-ajax.php' ),
                )
            );

        }

        if ( wpbdp_get_option( 'googlemaps-show-directions' ) ) {
            wp_localize_script(
                'wpbdp-googlemaps-js',
                'WPBDP_googlemaps_directions_l10n',
                array(
                    'submit_normal'   => __( 'Show Directions', 'wpbdp-googlemaps' ),
                    'submit_working'  => __( 'Working...', 'wpbdp-googlemaps' ),

					/* translators: %s the address */
                    'titles_driving'  => __( 'Driving directions to "%s"', 'wpbdp-googlemaps' ),

					/* translators: %s the address */
                    'titles_cycling'  => __( 'Cycling directions to "%s"', 'wpbdp-googlemaps' ),

					/* translators: %s the address */
                    'titles_transit'  => __( 'Public Transit directions to "%s"', 'wpbdp-googlemaps' ),

					/* translators: %s the address */
                    'titles_walking'  => __( 'Walking directions to "%s"', 'wpbdp-googlemaps' ),
                    'errors_no_route' => __( 'Could not find a route from your location.', 'wpbdp-googlemaps' ),
                )
            );
            add_thickbox();
        }

		$this->localize_script( $cluster );
    }

	/**
	 * Add parameters for use in scripts.
	 *
	 * @since 5.2
	 */
	private function localize_script( $cluster ) {
		$markers = apply_filters( 'wpbdp_map_markers', plugins_url( '/resources/marker-clusterer/markers/m', __FILE__ ) );
		$js_args = array(
			'is_marker_cluster_enabled' => $cluster,
			'markers_path'              => $markers,
		);

        wp_localize_script( 'wpbdp-googlemaps-js', 'WPBDP_googlemaps_marker_cluster', $js_args );
	}

    public function print_footer_scripts() {
        if ( $this->javascript_data ) {
            foreach ( $this->javascript_data as $variable => $value ) {
                wp_localize_script( 'wpbdp-googlemaps-js', $variable, $value );
            }
        }
    }

    private function obtain_google_maps_handle() {
        global $wp_scripts;
        $candidates = array();

        foreach ( $wp_scripts->registered as $script ) {
            if ( ( ( false !== stripos( $script->src, 'maps.google.com/maps/api' ) ||
                     false !== stripos( $script->src, 'maps.googleapis.com/maps/api' ) ) &&
                     false === stripos( $script->src, 'callback' ) ) &&
                    in_array( $script->handle, $wp_scripts->queue ) ) {
                $candidates[] = $script->handle;
            }
        }

        if ( $candidates ) {
            $this->maps_handle         = array_shift( $candidates );
            $this->maps_handles_remove = $candidates;
        }

        return $this->maps_handle;
    }

    public function register_settings( $settingsapi ) {
        wpbdp_register_settings_group( 'googlemaps', _x( 'Google Maps', 'settings', 'wpbdp-googlemaps' ), 'modules' );

		// General settings.
        wpbdp_register_settings_group( 'googlemaps/general', _x( 'General Settings', 'settings', 'wpbdp-googlemaps' ), 'googlemaps' );

        wpbdp_register_setting(
            array(
                'id'        => 'googlemaps-apikey',
                'name'      => _x( 'Google Maps API Key', 'settings', 'wpbdp-googlemaps' ),
                'type'      => 'text',
                'desc'      => str_replace( '<a>', '<a href="https://developers.google.com/maps/documentation/javascript/get-api-key">', '<br />' . _x( 'Google requires that you use an API key to get geocoding or driving directions. You can get the <a>key here</a>.', 'settings', 'wpbdp-googlemaps' ) ),
                'group'     => 'googlemaps/general',
                'on_update' => array( $this, 'schedule_api_key_verification' ),
                'validator' => array( 'trim' ),
            )
        );

        wpbdp_register_setting(
            array(
                'id'      => 'googlemaps-show-category-map',
                'name'    => __( 'Show listings map on:', 'wpbdp-googlemaps' ),
                'type'    => 'checkbox',
				'desc'    => __( 'Categories', 'wpbdp-googlemaps' ),
                'default' => false,
                'group'   => 'googlemaps/general',
				'class'   => 'wpbdp-collapse-row-first',
            )
        );
        wpbdp_register_setting(
            array(
                'id'      => 'googlemaps-show-viewlistings-map',
				'desc'    => __( '"View Listings"', 'wpbdp-googlemaps' ),
                'type'    => 'checkbox',
                'default' => false,
                'group'   => 'googlemaps/general',
				'class'   => 'wpbdp-collapse-row',
            )
        );
        wpbdp_register_setting(
            array(
                'id'      => 'googlemaps-show-search-map',
				'desc'    => __( 'Search results', 'wpbdp-googlemaps' ),
                'type'    => 'checkbox',
                'default' => false,
                'group'   => 'googlemaps/general',
				'class'   => 'wpbdp-collapse-row-last',
            )
        );
        wpbdp_register_setting(
            array(
                'id'      => 'googlemaps-listings-on-page',
                'name'    => _x( 'Current page listings to show on map', 'settings', 'wpbdp-googlemaps' ),
                'type'    => 'radio',
                'default' => 'all',
                'options' => array(
                    'all'  => _x( 'All listings', 'settings', 'wpbdp-googlemaps' ),
                    'page' => _x( 'Only visible listings on page', 'settings', 'wpbdp-googlemaps' ),
                ),
                'group'   => 'googlemaps/general',
            )
        );
        wpbdp_register_setting(
            array(
                'id'      => 'googlemaps-show-directions',
                'name'    => _x( 'Allow visitors to get directions to listings?', 'settings', 'wpbdp-googlemaps' ),
                'type'    => 'checkbox',
                'default' => false,
                'group'   => 'googlemaps/general',
				'tooltip' => _x( "Please note that getting directions to listings from the visitor's current location works if your website is accessed through HTTPS only. For non-HTTPS websites, visitors can still get directions, but they have to type in a specific address.", 'settings', 'wpbdp-googlemaps' ),
            )
        );
        wpbdp_register_setting(
            array(
                'id'      => 'googlemaps-fields-latlong-enabled',
                'name'    => _x( 'Allow users to manually adjust the location of their listings?', 'settings', 'wpbdp-googlemaps' ),
                'type'    => 'checkbox',
                'default' => false,
				'tooltip' => __( 'Allow users to grab the pin and physically place it where the actual location is, rather than rely on Google\'s geolocation. Helpful for rural addresses.', 'wpbdp-googlemaps' ),
                'group'   => 'googlemaps/general',
            )
        );
        wpbdp_register_setting(
            array(
                'id'           => 'googlemaps-default-address',
                'name'         => _x( 'Default Address/Location to use if address can\'t be geocoded', 'settings', 'wpbdp-googlemaps' ),
                'type'         => 'text',
                'default'      => '',
                'placeholder'  => _x( 'USA', 'settings', 'wpbdp-googlemaps' ),
                'desc'         => _x( 'Starting point for the pin used to manually adjust the location of the listings.', 'settings', 'wpbdp-googlemaps' ),
                'group'        => 'googlemaps/general',
                'requirements' => array( 'googlemaps-fields-latlong-enabled' ),
            )
        );

		$this->field_settings();
		$this->appearance_settings();
		$this->size_settings();
    }

	private function field_settings() {
        $fields_api = wpbdp_formfields_api();
        wpbdp_register_settings_group(
            'googlemaps-fields',
            __( 'Listing Locations', 'wpbdp-googlemaps' ),
            'googlemaps',
            array( 'desc' => _x( 'Please select at least one field from your listings to use for location information that maps can use to find a pin on Google Maps.  The more fields you use, the more accurate the pin\'s location.', 'settings', 'wpbdp-googlemaps' ) )
        );

        $choices      = array();
        $choices['0'] = _x( '-- None --', 'settings', 'wpbdp-googlemaps' );
        foreach ( $fields_api->get_fields( true ) as $field ) {
            $choices[ $field->id ] = esc_attr( $field->label );
        }

        foreach ( array(
            'googlemaps-fields-address' => _x( 'address', 'settings', 'wpbdp-googlemaps' ),
            'googlemaps-fields-city'    => _x( 'city', 'settings', 'wpbdp-googlemaps' ),
            'googlemaps-fields-state'   => _x( 'state', 'settings', 'wpbdp-googlemaps' ),
            'googlemaps-fields-zip'     => _x( 'ZIP code', 'settings', 'wpbdp-googlemaps' ),
            'googlemaps-fields-country' => _x( 'country', 'settings', 'wpbdp-googlemaps' ),
        ) as $k => $v ) {
            wpbdp_register_setting(
                array(
                    'id'      => $k,

					/* translators: %s the part of the address */
                    'name'    => sprintf( __( '%s field', 'wpbdp-googlemaps' ), ucfirst( $v ) ),
                    'type'    => 'select',
                    'default' => '0',
                    'options' => $choices,
                    'group'   => 'googlemaps-fields',
                )
            );
        }

		$line_sep        = "\r\n";
		$address_format  = '<a href="[url]"><b>[name]</b></a>';
		$address_format .= '[address]' . $line_sep;
		$address_format .= '[city], [state] [zip] [country]';

        wpbdp_register_setting(
            array(
                'id'      => 'googlemaps-content',
                'name'    => __( 'Tooltip Content', 'wpbdp-googlemaps' ),
				'desc'    => __( 'Customize the content shown when a marker is selected. Allowed values include field ids, field shortnames, url, address, city, state, zip, and country.', 'wpbdp-googlemaps' ),
                'type'    => 'textarea',
                'default' => $address_format,
                'group'   => 'googlemaps-fields',
            )
        );
	}

	private function appearance_settings() {
        wpbdp_register_settings_group( 'googlemaps/appearance', _x( 'Appearance', 'settings', 'wpbdp-googlemaps' ), 'googlemaps' );

        wpbdp_register_setting(
            array(
                'id'      => 'googlemaps-marker-cluster',
                'name'    => _x( 'Use marker cluster indicators', 'settings', 'wpbdp-googlemaps' ),
                'type'    => 'checkbox',
                'default' => false,
                'group'   => 'googlemaps/appearance',
            )
        );

        wpbdp_register_setting(
            array(
                'id'      => 'googlemaps-position',
                'name'    => _x( 'Display Map position', 'settings', 'wpbdp-googlemaps' ),
                'type'    => 'radio',
                'default' => 'bottom',
                'desc'    => _x( 'Applies only to category, "View Listings" and search results maps.', 'settings', 'wpbdp-googlemaps' ),
                'options' => array(
                    'top'    => _x( 'Above all listings', 'settings', 'wpbdp-googlemaps' ),
                    'bottom' => _x( 'Below all listings', 'settings', 'wpbdp-googlemaps' ),
                ),
                'group'   => 'googlemaps/appearance',
            )
        );

        $zoom_levels = array( 'auto' => _x( 'Automatic', 'settings zoom', 'wpbdp-googlemaps' ) );
        for ( $i = 1; $i <= 15; $i++ ) {
            $zoom_levels[ $i ] = $i;
        }
        wpbdp_register_setting(
            array(
                'id'      => 'googlemaps-zoom',
                'name'    => _x( 'Zoom Level', 'settings', 'wpbdp-googlemaps' ),
                'type'    => 'select',
                'default' => 'auto',
                'options' => $zoom_levels,
                'group'   => 'googlemaps/appearance',
            )
        );

        wpbdp_register_setting(
            array(
                'id'      => 'googlemaps-maptype',
                'name'    => _x( 'Map Type', 'settings', 'wpbdp-googlemaps' ),
                'type'    => 'radio',
                'options' => array(
                    'roadmap'   => _x( 'Roadmap', 'settings', 'wpbdp-googlemaps' ),
                    'satellite' => _x( 'Satellite', 'settings', 'wpbdp-googlemaps' ),
                    'hybrid'    => _x( 'Hybrid', 'settings', 'wpbdp-googlemaps' ),
                    'terrain'   => _x( 'Terrain', 'settings', 'wpbdp-googlemaps' ),
                ),
                'group'   => 'googlemaps/appearance',
            )
        );
        wpbdp_register_setting(
            array(
                'id'      => 'googlemaps-animate-marker',
                'name'    => _x( 'Animate markers', 'settings', 'wpbdp-googlemaps' ),
                'type'    => 'checkbox',
                'default' => false,
                'group'   => 'googlemaps/appearance',
            )
        );
	}

	private function size_settings() {
		wpbdp_register_settings_group( 'googlemaps/size', __( 'Size', 'wpbdp-googlemaps' ), 'googlemaps' );

        wpbdp_register_setting(
            array(
                'id'      => 'googlemaps-size',
                'name'    => _x( 'Map Size', 'settings', 'wpbdp-googlemaps' ),
                'type'    => 'select',
                'default' => 'auto',
                'options' => array(
					'auto'   => _x( 'Automatic', 'settings', 'wpbdp-googlemaps' ),
                    'small'  => _x( 'Small map (250x250px)', 'settings', 'wpbdp-googlemaps' ),
                    'large'  => _x( 'Large map (400x600px)', 'settings', 'wpbdp-googlemaps' ),
                    'custom' => _x( 'Custom size', 'settings', 'wpbdp-googlemaps' ),
                ),
                'group'   => 'googlemaps/size',
            )
        );
        wpbdp_register_setting(
            array(
                'id'      => 'googlemaps-size-custom-w',
                'name'    => _x( 'Custom map size width (px)', 'settings', 'wpbdp-googlemaps' ),
                'type'    => 'text',
                'default' => '250',
                'desc'    => _x( 'Applies only to the "Custom size" map size', 'settings', 'wpbdp-googlemaps' ),
                'group'   => 'googlemaps/size',
            )
        );
        wpbdp_register_setting(
            array(
                'id'      => 'googlemaps-size-custom-h',
                'name'    => _x( 'Custom map size height (px)', 'settings', 'wpbdp-googlemaps' ),
                'type'    => 'text',
                'default' => '250',
                'desc'    => _x( 'Applies only to the "Custom size" map size', 'settings', 'wpbdp-googlemaps' ),
                'group'   => 'googlemaps/size',
            )
        );
        wpbdp_register_setting(
            array(
                'id'      => 'googlemaps-size-auto',
				'name'    => __( 'Auto-resize', 'wpbdp-googlemaps' ),
				'tooltip' => _x( 'Auto-resize map when container is stretched (makes Maps responsive)', 'settings', 'wpbdp-googlemaps' ),
                'type'    => 'checkbox',
                'default' => false,
                'group'   => 'googlemaps/size',
            )
        );
	}

    /**
     * Builds the address for a given listing using the current settings.
     *
     * @param int  $listing_id the listing ID
     * @param bool $pretty whether to pretty-format the address or not (defaults to FALSE)
     * @return string the listing full address
     */
	public function get_listing_address( $listing_id, $pretty = false ) {
		$settingsapi = wpbdp_settings_api();
		$fieldsapi   = wpbdp_formfields_api();

		$address       = $this->get_address_format( $listing_id, $pretty );
		$address_parts = $this->get_address_parts( $address );
		$check_setting = $this->address_setting_ids();

		foreach ( $address_parts as $field_name ) {
			$value    = '';
			$field_id = $field_name;

			if ( in_array( $field_name, $check_setting, true ) ) {
				$field_id = wpbdp_get_option( 'googlemaps-fields-' . $field_name );
			}

			if ( $field_id === 'url' ) {
				$value = esc_url_raw( get_permalink( $listing_id ) );
			} elseif ( $field_id === 'name' ) {
				$value = get_the_title( $listing_id );
			} elseif ( $field_id ) {
				$field = $fieldsapi->get_field( $field_id );
				if ( $field ) {
					$value = $field->plain_value( $listing_id );
				}
			}

			$address = $this->replace_value_in_address( $field_name, $value, $address );
		}

		return trim( $address );
	}

	/**
	 * Replace the shortcode in the address.
	 *
	 * @since 5.2
	 */
	private function replace_value_in_address( $part, $value, $address ) {
		$replace = '[' . $part . ']';
		if ( $value === '' ) {
			$code    = $replace;
			$replace = array( $code . ', ', $code . ' ', $code );
		}

		return str_replace( $replace, $value, $address );
	}

	/**
	 * Allow the format in the map info to be customized.
	 *
	 * @since 5.2
	 */
	private function get_address_format( $listing_id, $pretty ) {
		if ( $pretty ) {
			$address_format = wp_kses_post( wpbdp_get_option( 'googlemaps-content' ) );
		} else {
			$address_format = '[address], [city], [state] [zip] [country]';
		}

		return apply_filters( 'wpbdp_address_format', $address_format, compact( 'listing_id', 'pretty' ) );
	}

	/**
	 * Allow custom fields to be included in the address box.
	 *
	 * @since 5.2
	 */
	private function get_address_parts( $address ) {
		preg_match_all( '/\[(.*?)\]/s', $address, $shortcodes, PREG_PATTERN_ORDER );
		return isset( $shortcodes[1] ) ? $shortcodes[1] : array();
	}

    /**
	 * Needs info.
	 *
     * @since 5.0.2 Not used until we figure out how to fully integrate the place
     *              chooser with the Submit Listing process on 5.0.
     * @since 3.5.1
     */
    public function get_address_from_state() {
		$listing_fields = wpbdp_get_var( array( 'param' => 'listingfields' ), 'post' );

        if ( ! $listing_fields ) {
            return;
        }

        $address = '';

        $res = array(
            'address' => '',
        );

		foreach ( $this->address_setting_ids() as $field_name ) {
            $field_id = wpbdp_get_option( 'googlemaps-fields-' . $field_name );

            if ( ! $field_id ) {
                continue;
            }

            $value = ! empty( $listing_fields[ $field_id ] ) ? $listing_fields[ $field_id ] : '';

            if ( $value ) {
                $field = wpbdp_get_form_field( $field_id );

                if ( ! $field ) {
                    continue;
                }

                switch ( $field->get_association() ) {
                    case 'region':
                        $terms    = get_terms(
                            wpbdp_regions_taxonomy(),
                            array(
                                'hide_empty' => 0,
                                'include'    => $value,
                                'fields'     => 'names',
                            )
                        );
                        $address .= implode( ', ', $terms );
                        break;
                    case 'category':
                        $value = get_terms(
                            WPBDP_CATEGORY_TAX,
                            array(
                                'hide_empty' => 0,
                                'include'    => array_keys( $value ),
                                'fields'     => 'names',
                            )
                        );
						$address .= implode( ', ', $value );
						break;
                    case 'tags':
                        $address .= implode( ', ', $value );
                        break;
                    default:
                        if ( in_array( $field->get_field_type_id(), array( 'checkbox', 'select', 'multiselect' ), true ) ) {
                            $value = is_array( $value ) ? implode( ', ', $value ) : $value;
                        }

                        $address .= $value;
                        break;
                }
                $address .= ',';
            }
        }

        $res['address'] = trim( esc_attr( substr( $address, 0, -1 ) ) );

        print json_encode( $res );
        die();
    }

    /**
     * Returns a hash code used to verify that our location cache is kept current.
     *
     * @return string
     */
    public function field_hash() {
        $hash = '';

        foreach ( $this->address_setting_ids() as $field_name ) {
            $field_id = wpbdp_get_option( 'googlemaps-fields-' . $field_name );
            $field_id = ! $field_id ? 0 : $field_id;
            $hash    .= $field_id . '-';
        }

        return substr( $hash, 0, -1 );
    }

    /**
     * Returns the latitude & longitude for the address of a given listing.
     *
     * @param int  $listing_id the listing ID.
     * @param bool $nocache wheter to bypass the cache or not. Default is FALSE.
     * @return bool|object an object with lat (latitude) & lng (longitude) keys or FALSE if geolocation fails.
     * @since 1.4
     */
    public function listing_geolocate( $listing_id, $nocache = false ) {
        if ( ! $listing_id ) {
            return false;
        }

        $address = $this->get_listing_address( $listing_id );
        if ( ! $address ) {
            return false;
        }

        $location = ! $nocache ? get_post_meta( $listing_id, '_wpbdp[googlemaps][geolocation]', true ) : '';
        if ( $location && ( ! isset( $location->field_hash ) || $location->field_hash != $this->field_hash() ) ) {
            return $this->listing_geolocate( $listing_id, true );
        }

        if ( $location && isset( $location->lat ) && isset( $location->lng ) ) {
            return $location;
        }

        $location = $this->geolocate( $address );

        if ( ! $location ) {
            return false;
        }

        $location->field_hash = $this->field_hash();

        update_post_meta( $listing_id, '_wpbdp[googlemaps][geolocation]', $location );
        return $location;
    }

    /**
	 * Does listing location have an override?
	 *
     * @since 3.5.1
     */
    public function listing_geolocation_override( $listing_id ) {
        if ( ! $listing_id ) {
            return false;
        }

        $override = get_post_meta( $listing_id, '_wpbdp[googlemaps][geolocation_override]', true );

        if ( ! $override || ( $override->field_hash != $this->field_hash() ) ) {
            return false;
        }

        return $override;
    }

    /**
	 * Save the warning to show in admin.
	 *
     * @since 3.5.1
     */
    private function toggle_warning( $name = '', $warn = true ) {
		if ( 'all' === $name ) {
            $this->toggle_warning( 'over-query-limit', $warn );
            $this->toggle_warning( 'request-denied', $warn );
            return;
        }

		if ( $warn && $name === 'API keys with referer restrictions cannot be used with this API.' ) {
			// This message isn't applicable. The maps work with restrictions.
			return;
		}

        $warnings = get_option( 'wpbdp-googlemaps-warnings', array() );
        $key      = array_search( $name, is_array( $warnings ) ? $warnings : array(), true );

        if ( $warn ) {
            if ( false === $key ) {
                $warnings[] = $name;
            }
		} elseif ( false !== $key ) {
			unset( $warnings[ $key ] );
        }

        update_option( 'wpbdp-googlemaps-warnings', $warnings );
    }

    /**
	 * Save runtime warnings to show in the admin area.
	 *
     * @since 3.5.1
     */
    private function get_warnings() {
        $warnings = get_option( 'wpbdp-googlemaps-warnings', array() );

        if ( ! is_array( $warnings ) ) {
            return array();
        }

        $texts = array();

        foreach ( $warnings as $warning_name ) {

            switch ( $warning_name ) {
                case 'over-query-limit':
                    $txt = $this->over_limit_warning();
                    break;
                case 'request-denied':
                    $txt = $this->request_denied_warning();
                    break;
				default:
					$txt = $warning_name;
            }

            if ( $txt ) {
                $texts[] = $txt;
            }
        }

        return $texts;
    }

	private function over_limit_warning() {
		$txt = '<b>' . esc_html__( 'Business Directory Google Maps has detected some issues while trying to contact the Google Maps API.', 'wpbdp-googlemaps' ) . '</b><br />';
		$txt .= __( 'This usually happens because Google imposes a daily limit on the number of requests a site can make. If you have been seeing this warning for more than 24 hours it could be because:', 'wpbdp-googlemaps' );
		$txt .= '<br />';
		$txt .= __( '- You have a huge number of listings that need to be geocoded. If this is the case you might need to wait several days before Business Directory has cached all the locations.', 'wpbdp-googlemaps' );
		$txt .= '<br />';
		$txt .= __( '- You are on a shared hosting and other sites are using up the request allowance for your IP.', 'wpbdp-googlemaps' );
		$txt .= '<br />';
		$txt .= __( '- The number of requests or Google map views in use by your site really exceeds the Google Maps API limits.', 'wpbdp-googlemaps' );
		$txt .= '<br /><br />';
		$txt .= str_replace(
			'<a>',
			'<a href="https://businessdirectoryplugin.com/knowledge-base/google-maps-module/" target="_blank" rel="noopener">',
			__( 'You might need to apply for an API key with Google. Please read <a>our documentation on the subject</a>.', 'wpbdp-googlemaps' )
		);
		return $txt;
	}

	private function request_denied_warning() {
		$googlemaps_settings_url = admin_url( $this->settings_url );

		$txt = '<b>' . esc_html__( 'Business Directory Google Maps: Invalid API Key.', 'wpbdp-googlemaps' ) . '</b><br />';
		$txt .= sprintf(
			/* translators: %s: url-open %s: url-close */
			__( 'The Google Maps %1$sAPI key%2$s is invalid. Maps on directory pages will not appear until this issue is addressed.', 'wpbdp-googlemaps' ),
			'<a href="' . esc_url( $googlemaps_settings_url ) . '#googlemaps-apikey">',
			'</a>'
		) . '<br />';
		$txt .= sprintf(
			/* translators: %s: url-open %s: url-close */
			__( 'Please visit the %1$sGoogle API Console%2$s and make sure that:', 'wpbdp-googlemaps' ),
			'<a href="https://console.developers.google.com" target="_blank" rel="noopener">',
			'</a>'
		) . '<br/><br />';
		$txt .= sprintf(
			/* translators: %s: url-open %s: url-close */
			__( '1. You have enabled the %1$sGoogle Maps Geocoding API%2$s in your project.', 'wpbdp-googlemaps' ),
			'<a href="https://console.developers.google.com/apis/library/geocoding-backend.googleapis.com" target="_blank" rel="noopener">',
			'</a>'
		) . '<br >';
		$txt .= sprintf(
			/* translators: %s: url-open %s: url-close */
			__( '2. You have enabled the %1$sGoogle Maps JavaScript API v3%2$s in your project.', 'wpbdp-googlemaps' ),
			'<a href="https://console.developers.google.com/apis/library/maps-backend.googleapis.com" target="_blank" rel="noopener">',
			'</a>'
		) . '<br >';
		$txt .= sprintf(
			/* translators: %s: url-open %s: url-close */
			__( '3. You have enabled the %1$sGoogle Maps Embed API%2$s in your project.', 'wpbdp-googlemaps' ),
			'<a href="https://console.developers.google.com/apis/library/maps-embed-backend.googleapis.com" target="_blank" rel="noopener">',
			'</a>'
		) . '<br ><br />';
		$txt .= sprintf(
			/* translators: %s: url-open %s: url-close */
			__( 'For additional information on how to correctly setup your API key, visit %1$sthis link%2$s.', 'wpbdp-googlemaps' ),
			'<a href="https://businessdirectoryplugin.com/knowledge-base/google-maps-module/" target="_blank" rel="noopener">',
			'</a>'
		);
		$txt .= '<br /><br /><hr />';
		$txt .= sprintf(
			/* translators: %s: url-open %s: url-close */
			__( '%1$sClick here to verify your API key%2$s again after you adjust the settings in the Google API Console.', 'wpbdp-googlemaps' ),
			'<a href="' . esc_url( add_query_arg( 'wpbdp-check-googlemaps-api-key', true, $googlemaps_settings_url ) ) . '">',
			'</a>'
		);
		$txt .= '<br />';
		return $txt;
	}

    public function has_warning( $warning_name ) {
        $warnings = get_option( 'wpbdp-googlemaps-warnings', array() );
        return in_array( $warning_name, $warnings, true );
    }

    /**
     * Obtains the latitude & longitude for a given plain text address.
     *
     * @param string $address the address.
     * @return bool|object an object with lat (latitude) & lng (longitude) keys or FALSE if geolocation fails.
     * @since 1.4
     */
    public function geolocate( $address = '' ) {
        $address = trim( $address );

        if ( ! $address ) {
            return false;
        }

        $key = wpbdp_get_option( 'googlemaps-apikey' );

        $response = wp_remote_get(
            'https://maps.googleapis.com/maps/api/geocode/json?' . ( $key ? 'key=' . $key . '&' : '' ) . 'sensor=false&address=' . urlencode( $address ),
            array( 'timeout' => 15 )
        );

        if ( is_wp_error( $response ) ) {
            return false;
        }

		// Delete warnings that may be old.
		delete_option( 'wpbdp-googlemaps-warnings' );

        $response = json_decode( $response['body'] );

        if ( ! $response || is_null( $response ) ) {
            return false;
        }

        if ( 'OVER_QUERY_LIMIT' == $response->status ) {
            $this->toggle_warning( 'over-query-limit', true );
        } elseif ( 'REQUEST_DENIED' == $response->status ) {
			$error = empty( $response->error_message ) ? 'request-denied' : $response->error_message;
			$this->toggle_warning( $error, true );
        } else {
            $this->toggle_warning( 'all', false );
        }

        if ( 'ZERO_RESULTS' == $response->status ) {
            $result         = new stdClass();
            $result->lat    = 0.0;
            $result->lng    = 0.0;
            $result->status = 'ZERO_RESULTS';
            return $result;
        }

        if ( 'OK' != $response->status ) {
            return false;
        }

        return $response->results[0]->geometry->location;
    }

	public function single_template_variables( $vars ) {
        $vars['#googlemaps']         = array(
            'position' => 'after',
            'value'    => $this->add_map_to_listing( '', $vars['listing_id'] ),
            'weight'   => 5,
        );
        $vars['listing_coordinates'] = $this->listing_geolocate( $vars['listing_id'] );

        return $vars;
    }

	public function listings_template_variables( $vars ) {
		$show_cat = wpbdp_get_option( 'googlemaps-show-category-map' );
		$show_map = 'category' === $vars['_parent'] && $show_cat;
		$show_map = $show_map || ( 'listings' == $vars['_template'] && ( ! $vars['_parent'] || 'region' === $vars['_parent'] ) && wpbdp_get_option( 'googlemaps-show-viewlistings-map' ) );
		$show_map = $show_map || ( 'search' == $vars['_parent'] && wpbdp_get_option( 'googlemaps-show-search-map' ) );

        if ( ! $show_map || ! $this->query_locations() ) {
            return $vars;
        }

        $vars['#googlemaps'] = array(
            'position' => 'after',
            'value'    => $this->map(),
        );

        return $vars;
    }

    public function add_map_to_listing( $html, $listing_id ) {
        $show_google_maps = apply_filters( 'wpbdp_show_google_maps', true, $listing_id );

        if ( ! $show_google_maps ) {
            return $html;
        }

        $this->add_listing_to_map( $listing_id );
        return $html . $this->map(
            array(
                'listingID'       => $listing_id,
                'show_directions' => wpbdp_get_option( 'googlemaps-show-directions' ),
            )
        );
    }

    public function map( $args = array() ) {
        static $uid = 0;

		$position = wpbdp_get_option( 'googlemaps-position', 'bottom' );

        $args = wp_parse_args(
            $args,
            array(
                'map_uid'         => $uid,
                'map_type'        => wpbdp_get_option( 'googlemaps-maptype', 'roadmap' ),
                'animate_markers' => wpbdp_get_option( 'googlemaps-animate-marker', false ),
                'map_size'        => wpbdp_get_option( 'googlemaps-size', 'small' ),
                'map_style_attr'  => wpbdp_get_option( 'googlemaps-size' ) == 'custom' ? sprintf( 'width: %dpx; height: %dpx;', wpbdp_get_option( 'googlemaps-size-custom-w' ), wpbdp_get_option( 'googlemaps-size-custom-h' ) ) : '',
                'position'        => array(
                    'location'  => $position,
                    'element'   => '#wpbdp-listings-list',
                    'insertpos' => 'inside',
                ),
                'auto_resize'     => wpbdp_get_option( 'googlemaps-size-auto', 0 ),
                'show_directions' => false,
                'listingID'       => 0,
                'zoom_level'      => wpbdp_get_option( 'googlemaps-zoom' ),
            )
        );

		$args['styles'] = array(
			array(
				'featureType' => 'poi.business',
				'elementType' => 'labels',
				'stylers'     => array(
					array(
						'visibility' => 'off',
					),
				),
			),
		);

		$map_id = $this->get_map_id();
		if ( $map_id ) {
			$args['map_id'] = $map_id;
		}

        if ( ! $this->map_locations ) {
            return '';
        }

		++$uid;

        $locations           = $this->map_locations;
        $this->map_locations = array();

        $locations = apply_filters( 'wpbdp_googlemaps_map_locations', $locations, $args );
        $args      = apply_filters( 'wpbdp_googlemaps_map_args', $args, $locations );

        $args['with_directions'] = ( $args['listingID'] > 0 && $args['show_directions'] );

        $this->javascript_data['WPBDP_googlemaps_data'][ 'map_' . $args['map_uid'] ] = array(
            'settings'  => $args,
            'locations' => $locations,
        );

        return wpbdp_render_page(
            plugin_dir_path( __FILE__ ) . '/templates/map.tpl.php',
            array(
                'settings'        => $args,
                'with_directions' => $args['with_directions'],
            )
        );
    }

    public function _doing_map_on() {
        $this->doing_map     = true;
        $this->map_locations = array();
    }

    public function query_locations( $query = null ) {
        global $wp_query;

        if ( ! $query && function_exists( 'wpbdp_current_query' ) ) {
            $query = wpbdp_current_query();
        } else {
            $query = $wp_query;
        }

        $args = array_merge( array_filter( $query->query_vars ), array() ); // Use array_merge() to copy the args.

        $args['post_type']        = WPBDP_POST_TYPE;
        $args['post_status']      = 'publish';
        $args['fields']           = 'ids';
        $args['suppress_filters'] = false;
        $args['wpbdp_main_query'] = true;

        $args = $this->maybe_update_query_to_retrieve_all_listings( $args );

        $listings = get_posts( $args );

        array_walk( $listings, array( &$this, 'add_listing_to_map' ) );

        return ! empty( $this->map_locations );
    }

    private function maybe_update_query_to_retrieve_all_listings( $query = array() ) {
        if ( 'all' == wpbdp_get_option( 'googlemaps-listings-on-page' ) ) {
            $query['posts_per_page'] = -1;
            unset( $query['paged'] );
        }

        return $query;
    }


    /**
     * Adds a listing to the current map locations.
     *  @param int $post_id listing ID.
     */
    public function add_listing_to_map( $post_id ) {
        $address     = $this->get_listing_address( $post_id );
        $geolocation = $this->listing_geolocate( $post_id );
        $override    = $this->listing_geolocation_override( $post_id );

        if ( $override ) {
            $geolocation = $override;
        }

        if ( ! $address && ! $geolocation ) {
            return;
        }

        if ( isset( $geolocation->status ) && $geolocation->status == 'ZERO_RESULTS' ) {
            return;
        }

        $this->map_locations[] = array(
            'address'     => $address,
            'geolocation' => $geolocation,
            'title'       => get_the_title( $post_id ), // deprecated.
            'url'         => esc_url_raw( get_permalink( $post_id ) ), // deprecated.
            'content'     => $this->get_listing_address( $post_id, true ),
        );
    }

    public function _category_map( $category ) {
        if ( ! $category ) {
            return;
        }

        global $wp_query;
        $q = function_exists( 'wpbdp_current_query' ) ? wpbdp_current_query() : $wp_query;

		// Try to respect the query as much as we can to be compatible with Regions and other plugins.
        $args                = array_merge( $q ? $q->query : array(), array() );
        $args['post_type']   = WPBDP_POST_TYPE;
        $args['post_status'] = 'publish';

        $args = $this->maybe_update_query_to_retrieve_all_listings( $args );

        if ( ! isset( $args['tax_query'] ) ) {
            $args['tax_query'][] = array(
                'taxonomy' => WPBDP_CATEGORY_TAX,
                'field'    => 'id',
                'terms'    => $category->term_id,
            );
        }
        $args['fields']           = 'ids';
        $args['suppress_filters'] = false;

		$this->show_post_map( $args );
    }

    public function _search_map() {
        global $wp_query;

        if ( ! $wp_query ) {
            return;
        }

        $posts = $wp_query->query['post__in'];
        if ( ! $posts ) {
            return;
        }

        array_walk( $posts, array( $this, 'add_listing_to_map' ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $this->map();
        $this->map_locations = array();
    }

    public function _view_listings_map() {
        global $wp_query;

        $q = function_exists( 'wpbdp_current_query' ) ? wpbdp_current_query() : $wp_query;

		// Try to respect the query as much as we can to be compatible with Regions and other plugins.
        $args                     = array_merge( $q ? $q->query : array(), array() );
        $args['post_type']        = WPBDP_POST_TYPE;
        $args['post_status']      = 'publish';
        $args['fields']           = 'ids';
        $args['suppress_filters'] = false;

        $args = $this->maybe_update_query_to_retrieve_all_listings( $args );

		$this->show_post_map( $args );
    }

	private function show_post_map( $args ) {
		$listings = get_posts( $args );
		if ( $listings ) {
			array_walk( $listings, array( $this, 'add_listing_to_map' ) );

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->map();
			$this->map_locations = array();
		}
	}

    public function update_listing_geolocation( $listing ) {
        $listing_id = is_object( $listing ) ? $listing->get_id() : $listing;

        if ( ! $listing_id || wp_is_post_revision( $listing_id ) ) {
            return;
        }

        if ( get_post_type( $listing_id ) != WPBDP_POST_TYPE ) {
            return;
        }

        global $wpbdp;
        if ( isset( $wpbdp->_importing_csv ) && $wpbdp->_importing_csv ) {
            return;
        }

        $this->listing_geolocate( $listing_id, true );
    }

    /* Activation */
    public function _admin_notices() {
		$tab = wpbdp_get_var( array( 'param' => 'page' ), 'get' );
		if ( ! current_user_can( 'administrator' ) || 'wpbdp_settings' !== $tab ) {
            return;
        }

        $warnings = $this->get_warnings();
        foreach ( $warnings as &$w ) {
			wpbdp_admin_message( wp_kses_post( make_clickable( $w ) ), 'error' );
        }
    }

    /**
	 * Show settings in listing form.
	 *
     * @since 3.5.1
     */
	public function _show_place_chooser( $section, $submit ) {
		if ( ! wpbdp_get_option( 'googlemaps-fields-latlong-enabled' ) ) {
            return;
        }

        $listing            = $submit->get_listing();
        $submitted_location = $this->get_submitted_location();
        $location_override  = ! empty( $_POST['enable_location_override'] ) || ( ! empty( $this->listing_geolocation_override( $listing->get_id() ) ) && $submit->editing() );
        $auto_located       = $location_override && ! empty( $_POST['done_location_override'] );

        if ( $location_override ) {
            $location = $submitted_location;

            if ( ! $submitted_location && $submit->editing() ) {
				$new_location = $this->listing_geolocation_override( $listing->get_id() );
				if ( $new_location ) {
					$location = $new_location;
				}
            }

            if ( $submit->saving() ) {
                $this->save_location_override( $listing, $location );
            }
        } else {
            delete_post_meta( $listing->get_id(), '_wpbdp[googlemaps][geolocation_override]' );
            $location = $this->get_place_chooser_location( $listing );
        }

        $maps_fields = array();

        foreach ( $this->address_setting_ids() as $field_name ) {
			$field_id = wpbdp_get_option( 'googlemaps-fields-' . $field_name );
            if ( $field_id ) {
                $maps_fields[ $field_name ] = $field_id;
            }
        }

        $vars = compact( 'location', 'location_override', 'maps_fields', 'auto_located' );

        $section['html']  = wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/adjust-location.tpl.php', $vars );
        $section['state'] = 'enabled';

        return $section;
    }

	private function address_setting_ids() {
		return array( 'address', 'city', 'state', 'zip', 'country' );
	}

    private function get_submitted_location() {
        if ( empty( $_POST['location_override']['lat'] ) || empty( $_POST['location_override']['lng'] ) ) {
            return null;
        }

        return (object) array(
            'lat' => floatval( $_POST['location_override']['lat'] ),
            'lng' => floatval( $_POST['location_override']['lng'] ),
        );
    }

    private function get_place_chooser_location( $listing ) {
        $location = false;

		$editing = wpbdp_get_var( array( 'param' => 'editing' ), 'post' );
        if ( 'edit_listing' === wpbdp_current_view() || $editing ) {
            $location = $this->get_listing_location( $listing );
        }

        if ( $location ) {
            return $location;
        }

        $default_location = wpbdp_get_option( 'googlemaps-default-address' );

        if ( $default_location ) {
            return $this->geolocate( $default_location );
        }

        return $this->get_empty_location();
    }

    /**
	 * Get location from the listing settings.
	 *
     * @param object $listing The listing object.
     * @return bool|mixed|object
     */
    private function get_listing_location( $listing ) {
        $override = $this->listing_geolocation_override( $listing->get_id() );

        if ( $override ) {
            $location = $override;
        } else {
            $location = $this->geolocate( $this->get_listing_address( $listing->get_id() ) );
        }

        return $location;
    }

    private function get_empty_location() {
        return (object) array(
            'lat' => 0.0,
            'lng' => 0.0,
        );
    }

    /**
	 * Save new location with listing.
	 *
     * @since 5.0.2
     */
	public function save_location_override( $listing, $location ) {
		if ( $location ) {
			$location->field_hash = $this->field_hash();
		}

        update_post_meta( $listing->get_id(), '_wpbdp[googlemaps][geolocation_override]', $location );
    }

    /**
	 * Should the api key be checked?
	 *
     * @param mixed $setting    Array or object with the setting's definition.
     * @param mixed $new_value  The new value for the settings.
     * @param mixed $old_value  The old value for the settings.
     * @since 5.0.7
     */
    public function schedule_api_key_verification( $setting, $new_value, $old_value ) {
        if ( $new_value !== $old_value ) {
            add_action( 'update_option_wpbdp_settings', array( $this, 'verify_apikey_status' ) );
        }
    }

    /**
     * Force the module to use the API Key to ask Google Maps for the exact
     * location of Automattic's office.
     *
     * @since 5.0.7     Removed all parameters.
     */
    public function verify_apikey_status() {
        $this->geolocate( "132 Hawthorne Street\nSan Francisco, CA 94107\nUnited States of America" );
    }

    /**
	 * Add map page to listing form.
	 *
     * @since 5.0.2
     */
	public function _submit_place_chooser( $sections, $submit ) {
        $listing    = $submit->get_listing();
        $listing_id = $listing->get_id();
        $plan_id    = $listing->get_fee_plan() ? $listing->get_fee_plan()->fee_id : ( ! empty( $_POST['listing_plan'] ) ? absint( $_POST['listing_plan'] ) : 0 );

        $show_place_chooser = apply_filters( 'wpbdp_show_google_maps', wpbdp_get_option( 'googlemaps-fields-latlong-enabled' ), $listing_id, $plan_id );

        if ( $show_place_chooser ) {
            $sections['googlemaps_place_chooser'] = array(
                'title' => __( 'Select Listing Location', 'wpbdp-googlemaps' ),
            );
        }

        return $sections;
    }
}

add_action( 'wpbdp_load_modules', array( 'WPBDP__Google_Maps', 'load' ) );
