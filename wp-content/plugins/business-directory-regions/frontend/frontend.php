<?php
/**
 * Frontend regions handler
 *
 * @package Business Directory Regions/Frontend/WPBDP_RegionsFrontend
 */

/**
 * Class WPBDP_RegionsFrontend
 */
class WPBDP_RegionsFrontend {

    private $selector = true;
    private $regions_shortcode = false;

    public function __construct() {
        add_action( 'widgets_init', array( &$this, 'register_widgets' ) );
        add_filter( 'wpbdp_shortcodes', array( &$this, 'add_shortcodes' ) );
        add_filter( 'pre_option_wpbdp-regions-hide-selector', array( $this, 'temp_change_selector_option' ) );

        add_filter( 'wpbdp_rewrite_rules', array( $this, 'rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'query_vars' ) );
        add_action( 'template_redirect', array( $this, 'template_redirect' ) );
        add_action( 'template_redirect', array( &$this, 'handle_widget_search' ) );

        add_filter( 'wp', array( $this, 'add_template_dir' ) );
        add_filter( 'wpbdp_view_locations', array( $this, 'view_locations' ) );
        add_filter( 'wpbdp_is_taxonomy', array( $this, 'is_taxonomy' ) );

        add_action( 'wpbdp_page_before', array( $this, 'render_selector' ), 10, 2 );
        add_filter( 'wpbdp_template_variables', array( $this, 'render_sidelist' ), 10, 2 );

        add_action( 'wp_ajax_wpbdp-regions-get-regions', array( $this, 'ajax' ) );
        add_action( 'wp_ajax_nopriv_wpbdp-regions-get-regions', array( $this, 'ajax' ) );
        add_action( 'wpbdp_rewrite_rules', array( &$this, 'main_page_rewrite_rules' ) );
        add_action( 'wpbdp_category_link', array( &$this, 'category_link' ), 10, 2 );

        add_action( 'wpbdp_before_category_page', array( $this, 'add_filter_for_category_link' ), 5 );
        add_action( 'wpbdp_before_category_page', array( $this, 'remove_filter_for_category_link' ), 20 );

        // Search integration.
        add_filter( 'wpbdp_search_query_pieces', array( $this, 'search_where' ), 10, 2 );
        add_filter( 'wpbdp_searching_request', array( &$this, 'is_regions_search' ), 10 );
        add_filter( 'wpbdp_listing_search_parse_request', array( &$this, 'quick_search_request' ), 10, 2 );
        add_action( 'wpbdp_main_box_extra_fields', array( $this, 'search_box_fields' ) );

        $listing_search = wpbdp_regions_listing_search();

        add_filter( 'wpbdp_listing_search_parse_request', array( $listing_search, 'parse_search_request' ), 10, 2 );
        add_filter( 'wpbdp_pre_configure_search', array( $listing_search, 'cofigure_region_field_search' ), 10, 4 );
        add_filter( 'wpbdp_search_query_pieces', array( $listing_search, 'filter_search_query_pieces' ), 10, 2 );

        add_filter( 'wpseo_replacements', array( $this, 'region_seo_replacements' ), 10 );

        add_filter( 'wpbdp_googlemaps_map_args', array( $this, 'region_sidelist_map_integration' ), 10 );

		if ( ! class_exists( 'WPBDP__Listing_Search' ) ) {
			require_once WPBDP_PATH . 'includes/helpers/class-listing-search.php';
		}
    }

    public function register_widgets() {
        require_once WPBDP_REGIONS_MODULE_DIR . '/frontend/widgets.php';
        register_widget( 'WPBDP_Region_Search_Widget' );
    }

    public function add_shortcodes( $shortcodes ) {
        /*
         * WordPress Shortcode:
         *  [businessdirectory-region], [business-directory-region], [wpbdp-region]
         * Used for:
         *  Displaying a set of listings from a given region.
         * Parameters:
         *  - region    (Required) The region for the listings. (Allowed Values: A valid Region name already configured under Directory Admin-> Regions).
         *  - children  Whether to include listings from children regions or not. Defaults to 1. (Allowed Values: 0 or 1)
         *  - category  Shows the listings with a certain category. (Allowed Values: Any valid category slug or ID you have configured under Directory -> Directory Categories. Can be a comma separated list too (e.g. "Dentists, Doctors" or 1,2,56).)
         * Example:
         *  - Display all listings in the USA (including its states, cities, etc.):
         *
         *    `[businessdirectory-region region="USA" children=1]`
         *
         */
        $shortcodes += array_fill_keys(
            array(
				'wpbdp-region',
				'businessdirectory-regions-region',
				'businessdirectory-region',
				'business-directory-regions-region',
				'business-directory-region',
				'business-directory-regions',
            ),
            array( &$this, 'shortcode' )
        );

        /*
         * WordPress Shortcode:
         *  [businessdirectory-regions-browser], [wpbdp_regions_browser]
         * Used for:
         *  Shows the "Regions browser", similar to what Craigslist shows on the home page when first visiting the site to pick your preferred location.
         * Parameters:
         *  - base_region (Required) What region to use as the "starting point" for the browser. Usually the parent region of some regions you want displayed. If not supplied, it will use your top-most region as the starting point. (Allowed Values: A valid Region name already configured under Directory Admin-> Regions)
         *  - breadcrumbs Whether to display the breadcrumbs while navigating children regions or not. Defaults to 1. (Allowed Values: 0 or 1)
         * Example:
         *  `[businessdirectory-regions-browser base_region="Asia"]`
         */
        $shortcodes += array_fill_keys(
            array(
				'wpbdp_regions_browser',
				'businessdirectory-regions-browser',
				'business-directory-regions-browser',
            ),
            array( &$this, 'regions_browser_shortcode' )
        );

        /*
         * WordPress Shortcode:
         *  [businessdirectory-region-subregions], [business-directory-region-subregions]
         * Used for:
         *  Showing subregions of a region (parent). This shortcode displays a list of child regions from a specific region, defined by `parent_region` parameter.
         * Parameters:
         *  - parent_region (Required) Region parent of listed subregions.  (Allowed Values: A valid Region **slug** or ID found under _Directory Admin-> Regions_)
         * Example:
         *  `[businessdirectory-region-subregions parent_region="usa"]`
         */
        $shortcodes += array_fill_keys(
            array(
				'businessdirectory-region-subregions',
				'business-directory-region-subregions',
            ),
            array( &$this, 'regions_subregions_shortcode' )
        );

        /*
         * WordPress Shortcode:
         *  [businessdirectory-region-home], [business-directory-region-home]
         * Used for:
         *  Shows the main directory page but filtered automatically with the given region.
         * Parameters:
         *  - region   What region to use as the "starting point" for the directory filter. (Allowed Values: A valid Region name already configured under Directory Admin-> Regions)
         *  - selector Whether to display the Region selector or not. Defaults to 0. (Allowed Values: 0 or 1)
         * Example:
         *  `[businessdirectory-region-home region="USA"]`
         */
        $shortcodes += array_fill_keys(
            array( 'businessdirectory-region-home', 'business-directory-region-home', 'bd-region-home' ),
            array( &$this, 'region_home_shortcode' )
        );
        return $shortcodes;
    }

    public function query_vars( $vars ) {
        array_push( $vars, 'bd-module' );
        array_push( $vars, 'bd-action' );
        array_push( $vars, 'region-id' );
        array_push( $vars, 'region_path' );
        array_push( $vars, 'region' );

        if ( wpbdp_get_option( 'disable-cpt' ) ) {
            array_push( $vars, '_' . wpbdp_get_option( 'regions-slug' ) );
        }

        return $vars;
    }

    public function rewrite_rules( $rules ) {
        global $wpdb;
        global $wp_rewrite;

        $shortcode_pages = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE (post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s) AND post_type = %s",
                '%[wpbdp_regions_browser%',
                '%[businessdirectory-regions-browser%',
                '%[business-directory-regions-browser%',
                'page'
            )
        );
        add_rewrite_tag( '%region_path%', '(.*)' );

        foreach ( $shortcode_pages as $page_id ) {
            if ( 'publish' !== get_post_status( $page_id ) ) {
                continue;
            }

            $rewrite_base           = str_replace(
                'index.php/', '',
                rtrim(
                    str_replace(
                        home_url() . '/',
                        '',
                        untrailingslashit( get_permalink( $page_id ) ) . '/(.*)'
                    ),
                    '/'
                )
            );
            $rules[ $rewrite_base ] = 'index.php?page_id=' . $page_id . '&region_path=$matches[1]';
        }

        return $rules;
    }

    public function template_redirect() {
        $module = get_query_var( 'bd-module' );
        $action = get_query_var( 'bd-action' );

        if ( $module != 'wpbdp-regions' ) {
			return;
        }
        if ( $action != 'set-location' ) {
			return;
        }

        $regions  = wpbdp_regions_api();
		$redirect = wpbdp_get_var( array( 'param' => 'redirect' ), 'request' );
		if ( ! $redirect ) {
			$redirect = wp_get_referer();
		}

		$origin = wpbdp_get_var( array( 'param' => 'origin' ), 'post' );
		$origin_data = array();
		parse_str( urldecode( base64_decode( $origin ) ), $origin_data );

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_POST['set-location'] ) ) {
            $regionfields = wpbdp_regions_fields_api();
			$data         = wpbdp_get_var( array( 'param' => 'listingfields', 'default' => array() ), 'post' );
            $region       = false;

            foreach ( $regionfields->get_fields( 'desc' ) as $level => $id ) {
                if ( isset( $data[ $id ] ) && $data[ $id ] > 0 ) {
                    $region = $data[ $id ];
                    break;
                }
            }

            if ( $region && $origin_data ) {
                $redirect = $regions->region_link( $region, true, $origin_data );
            }
		} elseif ( isset( $_POST['clear-location'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $redirect = get_page_link( wpbdp_get_page_id( 'main' ) );
            $regions->clear_active_region();
        }

		if ( empty( $redirect ) ) {
			return;
		}

        wp_redirect( esc_url_raw( $redirect ) );
        exit();
    }

    /**
	 * Don't require nonce since this is the front-end.
	 *
     * @since 3.6
     */
    public function handle_widget_search() {
        $module = get_query_var( 'bd-module' );
        $action = get_query_var( 'bd-action' );

        if ( 'regions' != $module ) {
            return;
        }

        if ( 'widget-search' != $action ) {
            return;
        }

		// phpcs:ignore WordPress.Security.NonceVerification
        $limit = isset( $_POST['numberposts'] ) ? intval( $_POST['numberposts'] ) : 0;

        $regions_api        = wpbdp_regions_api();
        $regions_fields_api = wpbdp_regions_fields_api();
        $region_id          = 0;
        $region             = null;

        foreach ( $regions_fields_api->get_fields( 'desc' ) as $level => $field_id ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			$region_id = isset( $_POST['listingfields'][ $field_id ] ) ? sanitize_text_field( wp_unslash( $_POST['listingfields'][ $field_id ] ) ) : 0;
			if ( $region_id && $region_id > 0 ) {
				break;
			}
        }

        $region = $regions_api->find_by_id( $region_id );

        if ( ! $region ) {
            die(); // TODO: maybe 404?
        }

        $redirect = $regions_api->region_listings_link( $region );
        $redirect = add_query_arg( 'limit', $limit, $redirect );

        wp_redirect( esc_url_raw( $redirect ) );
        die();
    }

    public function search_where( $query_pieces, $search ) {
        global $wpdb;

        $api    = wpbdp_regions_fields_api();
		$fields = $api->get_visible_fields();
        $terms  = array();

        foreach ( $fields as $field ) {
            $terms_ = $search->terms_for_field( $field );
            $terms_ = array_pop( $terms_ );

            if ( ! empty( $terms_ ) ) {
                $terms = $terms_;
            }
        }

        $terms = array_filter( array_map( 'intval', (array) $terms ) );

        if ( ! $terms ) {
            return $query_pieces;
        }

        $subq  = "SELECT rp.ID FROM {$wpdb->posts} AS rp ";
        $subq .= "JOIN {$wpdb->term_relationships} AS rtr ON (rp.ID = rtr.object_id) ";
        $subq .= "JOIN {$wpdb->term_taxonomy} AS rtt ON (rtr.term_taxonomy_id = rtt.term_taxonomy_id AND rtt.term_id IN (%s))";
        $subq  = sprintf( $subq, implode( ', ', $terms ) );

        $query_pieces['where'] .= sprintf( " AND {$wpdb->posts}.ID IN (%s)", $subq );

        return $query_pieces;
    }

    /**
     * @since 5.2.2
     */
    public function is_regions_search( $searching ) {
		return $searching || ( ! empty( $_GET ) && ( ! empty( $_GET['location'] ) || ! empty( $_GET['wpbdp_location'] ) ) );
    }

    /**
     * @since 5.0
     */
    public function quick_search_request( $search, $request ) {
        global $wpdb;

        // 'location' was the name of the query arg used until Regions 4.1.2
        if ( isset( $request['location'] ) ) {
            $location = $request['location'];
        } elseif ( isset( $request['wpbdp_location'] ) ) {
            $location = $request['wpbdp_location'];
        } elseif ( isset( $request['wpbdm-region'] ) ) {
            $location = $request['wpbdm-region'];
        } else {
            $location = null;
        }

        if ( ( empty( $location ) || ! wpbdp_get_option( 'regions-main-box-integration' ) ) && ! isset( $request['wpbdm-region'] ) ) {
            return $search;
        }

        $api = wpbdp_regions_fields_api();
        $fields = $api->get_fields();
		$visible_fields = $api->get_visible_fields();

        if ( empty( $fields ) || empty( $visible_fields ) ) {
            return $search;
        }

        if ( ! empty( $location ) ) {
            $location = array_map( 'strtolower', array_map( 'trim', explode( ',', $location ) ) );
            $location = array_shift( $location );
        }

        if ( ! $location ) {
            return $search;
        }

        foreach ( $fields as $field_id ) {
            $search = WPBDP__Listing_Search::tree_remove_field( $search, $field_id );
        }

        $location = str_replace( ' ', '-', $location );

        $regions_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT t.term_id FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy = %s AND LOWER(t.slug) LIKE '%%%s%%'",
                wpbdp_regions_taxonomy(),
                $location
            )
        );

        if ( $regions_ids ) {
            $children = $regions_ids;

            foreach ( $regions_ids as $region_id ) {
                $children = array_merge( $children, get_term_children( $region_id, wpbdp_regions_taxonomy() ) );
            }
        } else {
            $children = array( -1 );
        }

        $last_field = array_pop( $visible_fields );

        $search[] = array( $last_field->get_id(), $children );

        return $search;
    }

	public function url() {
		return apply_filters(
			'wpbdp_regions_filter_url',
			home_url( 'index.php?bd-module=wpbdp-regions&bd-action=set-location' )
		);
	}

    public function origin_hash() {
        global $wpbdp;
        $data = array(
			'action'  => wpbdp_current_view(),
			'referer' => wpbdp_get_server_value( 'REQUEST_URI' ),
		);
        return base64_encode( http_build_query( $data ) );
    }

    private function get_current_location() {
        $regions   = wpbdp_regions_api();
        $active    = $regions->get_active_region();
        $hierarchy = array();

        $level = $regions->get_region_level( $active, $hierarchy );
        $min   = wpbdp_regions_fields_api()->get_min_visible_level();

		/* translators: listing location */
        $text = _x( 'Displaying listings from %s.', 'region-selector', 'wpbdp-regions' );

        if ( is_null( $active ) || $level < $min ) {
            return sprintf( $text, _x( 'all locations', 'region-selector', 'wpbdp-regions' ) );
        }

        $names = array();
        for ( $i = $min; $i <= $level; $i++ ) {
            $names[] = $regions->find_by_id( $hierarchy[ $level - $i ] )->name;
        }

        return sprintf( $text, sprintf( '<strong>%s</strong>', join( '&nbsp;&#8594;&nbsp;', $names ) ) );
    }

    private function _render_region_sidelist( $regions, $children, $args = array() ) {
        $open_by_default = isset( $args['open_by_default'] ) ? $args['open_by_default'] : array();

        $api         = wpbdp_regions_api();
        $show_counts = wpbdp_get_option( 'regions-show-counts' );

		$item = '<a href="#" data-url="%s" data-region-id="%d">%s</a>';
        if ( $show_counts ) {
			$item .= ' (%d)';
        }

        $baseurl = $this->url();

        if ( ! empty( $regions ) ) {
            $regions = $api->find(
                array(
					'include' => $regions,
                )
            );
            $api->fix_regions_count( $regions );
        }

        $html = '';
        foreach ( $regions as $region ) {
			if ( $region->count == 0 ) {
                continue;
            }

            $url = add_query_arg( 'region-id', $region->term_id, $baseurl );
            if ( is_paged() ) {
                $url = add_query_arg( 'redirect', get_pagenum_link( 1, true ), $url );
            }

            $url = $api->region_link( $region, true );

            $html .= '<li>';
            $html .= $show_counts ? sprintf( $item, esc_url( $url ), $region->term_id, $region->name, intval( $region->count ) ) : sprintf( $item, esc_url( $url ), $region->term_id, $region->name );

            if ( isset( $children[ $region->term_id ] ) && is_array( $children[ $region->term_id ] ) ) {
				$html .= '<a class="js-handler bd-caret" href="#" title="' . esc_attr__( 'Hide or show', 'wpbdp-regions' ) . '"><span></span></a>';
                $html .= sprintf(
                    '<ul data-collapsible="true" data-collapsible-default-mode="%s">%s</ul>',
                    wpbdp_get_option( 'regions-sidelist-autoexpand' ) || in_array( $region->term_id, $open_by_default, true ) ? 'open' : '',
                    $this->_render_region_sidelist( $children[ $region->term_id ], $children, $args )
                );
            }

            $html .= '</li>';
        }

        return $html;
    }

    /**
     * @since 5.0
     */
    public function search_box_fields() {
        if ( ! wpbdp_get_option( 'regions-main-box-integration' ) ) {
            return;
        }

		echo '<div class="box-col"><input type="text" name="wpbdp_location" value="" placeholder="' . esc_attr__( 'Location', 'wpbdp-regions' ) . '" /></div>';
    }

    // XXX: this is a hack, FIXME before themes release
    public function render_selector( $template ) {
        static $rendered = false;

        if ( ! $this->selector || $rendered || wpbdp_get_option( 'regions-hide-selector' ) ) {
            return;
        }

        $excluded_templates = array(
            'submit_listing',
            'checkout',
            'checkout-confirmation',
        );

        if ( in_array( $template, $excluded_templates, true ) ) {
            return;
        }

        $formfields    = wpbdp()->formfields;
        $region_fields = wpbdp_regions_fields_api();

        $fields = array();
        $value  = null;

        foreach ( wpbdp_regions_fields_api()->get_visible_fields() as $field ) {
            if ( ! is_null( $value ) ) {
                wpbdp_regions()->set( 'parent-for-' . $field->get_id(), $value );
            }

             // get active region for this field
            $value    = $region_fields->field_value( null, null, $field, true );
            $fields[] = $field->render( $value, 'page' );
        }

		include WPBDP_REGIONS_MODULE_DIR . '/templates/region-selector.tpl.php';

        $rendered = true;
    }

    public function render_sidelist( $vars_, $id ) {
        static $rendered = false;

        if ( $this->regions_shortcode ) {
            add_filter( 'wpbdp_get_option_regions-show-sidelist', '__return_false' );
        }

		if ( ! wpbdp_get_option( 'regions-show-sidelist' ) || ( isset( $vars_['_parent'] ) && 'search' === $vars_['_parent'] && ! isset( $_REQUEST['kw'] ) ) ) {
            return $vars_;
        }

        if ( ( 'search' == $id && $vars_['searching'] ) || ( 'main_page' == $id && ! empty( $vars_['listings'] ) ) ) {
            $vars_['_class'] = ' with-region-sidelist ';
            return $vars_;
        }

        if ( $rendered || ! in_array( $id, array( 'listings', 'tag', 'category', 'main_page' ), true ) || ! wpbdp_get_option( 'regions-show-sidelist' ) ) {
            return $vars_;
        }

        if ( in_array( $vars_['_id'], array( 'tag', 'category', 'main_page', 'all_listings', 'listings' ), true ) ) {
            $vars_['_class'] .= ' with-region-sidelist ';
        }

        if ( 'listings' == $vars_['_template'] ) {
            $vars_['#regions_sidelist'] = array(
				'position' => 'before',
				'value'    => $this->render_region_sidelist(),
            );

            $rendered = true;
        }

        return $vars_;
    }

    public function render_region_sidelist() {
        $regions_api = wpbdp_regions_api();

        $level   = wpbdp_regions_fields_api()->get_min_visible_level();
        $level   = wpbdp_get_option( 'regions-sidelist-min-level', $level );
        $regions = $regions_api->find_sidelisted_regions_by_level( $level );

        if ( ! $regions ) {
            return '';
        }

        $children = $regions_api->get_sidelisted_regions_hierarchy();
        $current  = $regions_api->get_active_region();

        $args = array();

        if ( wpbdp_get_option( 'regions-sidelist-expand-current' ) && function_exists( 'get_ancestors' ) && $current ) {
            $args['open_by_default'] = array_merge( array( $current ), get_ancestors( $current, wpbdp_regions_taxonomy() ) );
        }

        $html  = '';
        $html .= '<div class="wpbdp-region-sidelist-wrapper">';
        $html .= '<input type="button" class="sidelist-menu-toggle button" value="' . _x( 'Regions Menu', 'sidelist', 'wpbdp-regions' ) . '" />';
        $html .= '<ul class="wpbdp-region-sidelist">%s</ul>';
        $html .= '</div>';

        $sidelist = '';

        if ( wpbdp_get_option( 'regions-sidelist-show-clear' ) && $current ) {
			$sidelist .= '<li class="clear-filter"><a href="' . esc_url( $regions_api->remove_url_filter( wpbdp_get_server_value( 'REQUEST_URI' ) ) ) . '">' . _x( 'Clear Filter', 'sidelist', 'wpbdp-regions' ) . '</a></li>';
        }

        $sidelist .= $this->_render_region_sidelist( $regions, $children, $args );

        $html = sprintf( $html, $sidelist );
        return $html;
    }

    public function shortcode( $attrs ) {
        require_once WPBDP_PATH . 'includes/views/all_listings.php';

        $sc_atts = shortcode_atts(
                array(
					'region'     => false,
                    'children'   => true,
                    'category'   => '',
                    'categories' => '',
                    'title'      => '',
                ), $attrs
            );

        if ( '' == $sc_atts['region'] ) {
            return _x( 'Please specify the id, name or slug of a region.', 'region shortcode', 'wpbdp-regions' );
        }

        if ( is_numeric( $sc_atts['region'] ) ) {
            $region = wpbdp_regions_api()->find_by_id( $sc_atts['region'] );
        } else {
            $region = wpbdp_regions_api()->find_by_name( $sc_atts['region'] );

            if ( ! $region ) {
                $region = wpbdp_regions_api()->find_by_slug( $sc_atts['region'] );
            }
        }

        if ( ! $region || is_null( $region ) ) {
            return _x( "The specified Region doesn't exist.", 'region shortcode', 'wpbdp-regions' );
        }

        $tax_query[] = array(
            'taxonomy'         => wpbdp_regions_taxonomy(),
            'field'            => 'id',
            'terms'            => array( $region->term_id ),
            'include_children' => $sc_atts['children'],
            'operator'         => 'IN',
        );

        if ( $sc_atts['category'] || $sc_atts['categories'] ) {
            $requested_categories = array();

            if ( $sc_atts['category'] )
                $requested_categories = array_merge( $requested_categories, explode( ',', $sc_atts['category'] ) );

            if ( $sc_atts['categories'] )
                $requested_categories = array_merge( $requested_categories, explode( ',', $sc_atts['categories'] ) );

            $categories = array();

            foreach ( $requested_categories as $cat ) {
                $term = null;
				if ( ! is_numeric( $cat ) ) {
                    $term = get_term_by( 'slug', $cat, WPBDP_CATEGORY_TAX );
				}

				if ( ! $term && is_numeric( $cat ) ) {
                    $term = get_term_by( 'id', $cat, WPBDP_CATEGORY_TAX );
				}

                if ( $term )
                    $categories[] = $term->term_id;
            }

            $tax_query[] = array(
                'taxonomy' => WPBDP_CATEGORY_TAX,
                'field'    => 'id',
				'terms'    => $categories,
            );
        }

		$page     = get_query_var( 'page' );
		$paged    = $page ? $page : get_query_var( 'paged', 1 );
		$per_page = wpbdp_get_option( 'listings-per-page' );

        $query_args = array(
            'post_type'        => WPBDP_POST_TYPE,
			'posts_per_page'   => $per_page > 0 ? $per_page : -1,
            'post_status'      => 'publish',
            'paged'            => intval( $paged ),
            'orderby'          => wpbdp_get_option( 'listings-order-by', 'date' ),
            'order'            => wpbdp_get_option( 'listings-sort', 'ASC' ),
            'tax_query'        => $tax_query,
            'wpbdp_main_query' => true,
        );

        // disable region selector
        $this->selector = false;

        $q = new WP_Query( $query_args );

        wpbdp_push_query( $q );

        $template_args = array(
            '_id'      => 'regions',
            'title'    => '',
            'region'   => $region->term_id,
			'_bar'     => true,
			'_wrapper' => 'page',
			'query'    => $q,
        );
        if ( ! function_exists( 'wp_pagenavi' ) && is_front_page() ) {
            global $paged;

			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            $paged = $q->query['paged'];
        }

        $html = wpbdp_render_page( WPBDP_REGIONS_MODULE_DIR . '/templates/region.tpl.php', $template_args );
        wp_reset_postdata();
        wpbdp_pop_query( $q );

        return $html;
    }

    /*
     * Regions browser shortcode.
     */

    public function regions_browser_shortcode( $args ) {
        $args = wp_parse_args(
			$args,
			array(
				'parent_region' => null,
				'base_region'   => null,
				'breadcrumbs'   => 1,
				'show_empty'    => 0,
				'shortcode'     => 'browser',
			)
		);

        $api       = wpbdp_regions_api();
        $forms_api = wpbdp_regions_fields_api();

		$this->set_base_region( $args );

		if ( empty( $args['base_region'] ) && $args['base_region'] !== '0' ) {
			return '';
		}

		$base_level = is_object( $args['base_region'] ) ? $api->get_region_level( $args['base_region']->term_id ) : 0;

        $base_uri = get_permalink();

		$region_path  = $this->get_region_path( $args['base_region'] );
        $region_path_ = explode( '/', $region_path );

        $current_region = get_term_by( 'slug', $region_path_[ count( $region_path_ ) - 1 ], wpbdp_regions_taxonomy() );

		if ( $current_region ) {
			$current_region->link = $this->regions_browser_link( $current_region, 'current' );
			$current_id           = $current_region->term_id;
			$level                = $api->get_region_level( $current_id );
		} else {
			$current_id = 0;
			$level      = 0;
		}

        $api_max_level    = $api->get_max_level();
        $next_level_field = $level >= $api_max_level ? null : $forms_api->get_field_by_level( $level + 1 );

		$ids     = $api->find_top_level_regions( array( 'parent' => $current_id ) );
        $regions = $api->find(
			array(
				'include'    => $ids ? $ids : array( -1 ),
				'orderby'    => 'name',
				'hide_empty' => isset( $args['show_empty'] ) ? ! $args['show_empty'] : 1,
			)
        );

        $api->fix_regions_count( $regions );

        foreach ( $regions as &$r ) {
            $r->children = count( get_term_children( $r->term_id, wpbdp_regions_taxonomy() ) );
			$r->link     = $this->regions_browser_link( $r );
        }

        if ( $level > $base_level ) {
            $regions = $this->regions_browser_classify( $regions );
        }

		$breadcrumbs_text = $args['breadcrumbs'] ? $this->regions_browser_breadcrumb( $region_path_ ) : '';

        return wpbdp_render_page(
            WPBDP_REGIONS_MODULE_DIR . '/templates/regions-browser.tpl.php',
            array(
				'breadcrumbs'    => $breadcrumbs_text,
				'current_region' => $current_region,
				'regions'        => $regions,
				'field'          => $next_level_field,
				'alphabetically' => $level > $base_level,
				'shortcode'      => $args['shortcode'],
            )
        );
    }

	/**
	 * @since 5.3
	 */
	private function set_base_region( &$atts ) {
		if ( ! empty( $atts['parent_region'] ) ) {
			$atts['base_region'] = $atts['parent_region'];
		}

		if ( ! empty( $atts['base_region'] ) ) {
			$get_by              = is_numeric( $atts['base_region'] ) ? 'id' : 'name';
			$region = get_term_by( $get_by, $atts['base_region'], wpbdp_regions_taxonomy() );
			if ( $get_by === 'name' && empty( $region ) ) {
				// Check the slug if doesn't exist by name.
				$region = get_term_by( 'slug', $atts['base_region'], wpbdp_regions_taxonomy() );
			}
			$atts['base_region'] = $region;
		}
	}

	/**
	 * @since 5.3
	 */
	private function get_region_path( $base_region ) {
		$region_path = wpbdp_get_var( array( 'param' => 'region_path' ), 'request' );
		if ( ! $region_path ) {
			$region_path = get_query_var( 'region_path' );
			if ( ! $region_path && $base_region ) {
				$region_path = $base_region->slug;
			}
		}

		return untrailingslashit( ltrim( $region_path, '/' ) );
	}

    public function regions_subregions_shortcode( $args ) {
        $args = wp_parse_args(
			$args, array(
				'parent_region' => null,
				'base_region'   => null,
				'breadcrumbs'   => 0,
				'shortcode'     => 'subregions',
				'show_empty'    => 0,
			)
        );

        return $this->regions_browser_shortcode( $args );
    }

    private function regions_browser_classify( $regions = array() ) {
        $c = array();

        foreach ( $regions as &$r ) {
            $first_char = $r->name[0];

            if ( ! isset( $c[ $first_char ] ) ) {
                $c[ $first_char ] = array();
            }

            $c[ $first_char ][] = $r;
        }

        return $c;
    }

	private function regions_browser_breadcrumb( $region_path ) {
        $api    = wpbdp_regions_api();
        $parts_ = $region_path;
        $parts  = array();

        $path = '';
        foreach ( $parts_ as $region_slug ) {
			$term = $api->find_by_slug( $region_slug );

			$link = $api->region_listings_link( $term );

			$parts[] = sprintf( '<a href="%s">%s</a>', esc_url( $link ), esc_html( $term->name ) );
        }

        return implode( ' &raquo; ', $parts );
    }

	private function regions_browser_link( $region, $custom = '' ) {
        $api = wpbdp_regions_api();

        if ( 'current' != $custom && $region->children > 0 ) {
			return $api->region_listings_link( $region );
        }
		return $api->region_home( $region );
    }

    public function region_home_shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
				'region'   => false,
				'selector' => false,
            ), $atts
        );

		$region   = $atts['region'];
		$selector = $atts['selector'];

        if ( ! $region ) {
            return '';
        }

        $term = false;
        foreach ( array( 'id', 'name', 'slug' ) as $field ) {
			$term = get_term_by( $field, $region, wpbdp_regions_taxonomy() );
			if ( $term ) {
                break;
            }
        }

        if ( ! $term ) {
            return '';
        }

        global $wpbdp;

        $regions_api = wpbdp_regions_api();
        $regions_api->set_active_region( $term );

        $this->_shortcode_hide_selector = $selector ? false : true;

        $v    = wpbdp_load_view( 'main' );
        $html = $v->dispatch();

        unset( $this->_shortcode_hide_selector );

        return $html;
    }

    public function temp_change_selector_option( $val ) {
        if ( ! isset( $this->_shortcode_hide_selector ) ) {
            return $val;
        }

        if ( $this->_shortcode_hide_selector ) {
            return true;
        }
    }

    public function ajax() {
		$parent          = wpbdp_get_var( array( 'param' => 'parent', 'default' => 0 ), 'request' );
		$level           = wpbdp_get_var( array( 'param' => 'level', 'default' => false ), 'request' );
		$field           = wpbdp_get_var( array( 'param' => 'field', 'default' => false ), 'request' );
		$category_id     = absint( wpbdp_get_var( array( 'param' => 'category_id', 'default' => 0 ), 'request' ) );
		$display_context = wpbdp_get_var( array( 'param' => 'display_context', 'default' => 'page' ), 'request' );

        if ( $category_id ) {
            // Make Regions believe there's a current region set.
            $api                         = wpbdp_regions_api();
            $api->session['category_id'] = $category_id;
        }

        // no support for searching by multiple parents
        $parent = is_array( $parent ) ? array_shift( $parent ) : $parent;

        $formfields = wpbdp()->formfields;
        $field      = $formfields->get_field( $field );

        wpbdp_regions()->set( 'parent-for-' . $field->get_id(), $parent );

        $html = $field->render( null, $display_context );

        $response = array(
			'status' => 'ok',
			'html'   => $html,
		);

        header( 'Content-Type: application/json' );
        echo json_encode( $response );
        exit();
    }

    public function main_page_rewrite_rules( $rules0 ) {
        global $wp_rewrite;

        $page_id   = wpbdp_get_page_id( 'main' );
        $page_link = wpbdp_get_page_link( 'main' );
        $page_link = preg_replace( '/\?.*/', '', $page_link ); // Remove querystring from page link.

        $page_link = apply_filters( 'wpbdp_url_base_url', $page_link, $page_id );

        $home_url = home_url();
        $home_url = preg_replace( '/\?.*/', '', $home_url ); // Remove querystring from home URL.

        $rewrite_base = str_replace( 'index.php/', '', rtrim( str_replace( trailingslashit( $home_url ), '', $page_link ), '/' ) );

        $regions_slug   = urlencode( wpbdp_get_option( 'regions-slug' ) );
        $directory_slug = urlencode( wpbdp_get_option( 'permalinks-directory-slug' ) );
        $category_slug  = urlencode( wpbdp_get_option( 'permalinks-category-slug' ) );
        $tags_slug      = urlencode( wpbdp_get_option( 'permalinks-tags-slug' ) );
        $pagination     = $wp_rewrite->pagination_base;
        $tax            = wpbdp_regions_taxonomy();

        $rules = array();

        // All listings in region.
        if ( wpbdp_get_option( 'disable-cpt' ) ) {
            $rules[ "($rewrite_base)/$regions_slug/(.+?)/$pagination/?([0-9]{1,})/?\$" ] = 'index.php?page_id=' . $page_id . '&wpbdp_view=all_listings&_' . $regions_slug . '=$matches[2]&paged=$matches[3]';
            $rules[ "($rewrite_base)/$regions_slug/(.+?)/?\$" ]                          = 'index.php?page_id=' . $page_id . '&wpbdp_view=all_listings&_' . $regions_slug . '=$matches[2]';
        } else {
            $rules[ "($rewrite_base)/$regions_slug/(.+?)/$pagination/?([0-9]{1,})/(feed|rdf|rss|rss2|atom)/?\$" ] = 'index.php?' . $tax . '=$matches[2]&paged=$matches[3]&feed=$matches[4]';
            $rules[ "($rewrite_base)/$regions_slug/(.+?)/$pagination/?([0-9]{1,})/?\$" ]                          = 'index.php?' . $tax . '=$matches[2]&paged=$matches[3]';
			$rules[ "($rewrite_base)/$regions_slug/(.+?)/(feed|rdf|rss|rss2|atom)/?\$" ] = 'index.php?' . $tax . '=$matches[2]&feed=$matches[3]';
            $rules[ "($rewrite_base)/$regions_slug/(.+?)/?\$" ] = 'index.php?' . $tax . '=$matches[2]';
        }

        // Region + category.
        if ( ! wpbdp_get_option( 'disable-cpt' ) ) {
            $rules[ "($rewrite_base)/$regions_slug/(.+?)/$category_slug/(.+?)/$pagination/?([0-9]{1,})/?\$" ] = 'index.php?wpbdp_category=$matches[3]&wpbdm-region=$matches[2]&paged=$matches[4]';
            $rules[ "($rewrite_base)/$regions_slug/(.+?)/$category_slug/(.+?)/?\$" ]                          = 'index.php?wpbdp_category=$matches[3]&wpbdm-region=$matches[2]';
            $rules[ "($rewrite_base)/$category_slug/(.+?)/$regions_slug/(.+?)/$pagination/?([0-9]{1,})/?\$" ] = 'index.php?wpbdp_category=$matches[2]&wpbdm-region=$matches[3]&paged=$matches[4]';
            $rules[ "($rewrite_base)/$category_slug/(.+?)/$regions_slug/(.+?)/?\$" ]                          = 'index.php?wpbdp_category=$matches[2]&wpbdm-region=$matches[3]';
        } else {
            $rules[ "($rewrite_base)/$regions_slug/(.+?)/$category_slug/(.+?)/$pagination/?([0-9]{1,})/?\$" ] = 'index.php?page_id=' . $page_id . '&_' . $category_slug . '=$matches[3]&_' . $regions_slug . '=$matches[2]&paged=$matches[4]';
            $rules[ "($rewrite_base)/$regions_slug/(.+?)/$category_slug/(.+?)/?\$" ]                          = 'index.php?page_id=' . $page_id . '&_' . $category_slug . '=$matches[3]&_' . $regions_slug . '=$matches[2]';
            $rules[ "($rewrite_base)/$category_slug/(.+?)/$regions_slug/(.+?)/$pagination/?([0-9]{1,})/?\$" ] = 'index.php?page_id=' . $page_id . '&_' . $category_slug . '=$matches[2]&_' . $regions_slug . '=$matches[3]&paged=$matches[4]';
            $rules[ "($rewrite_base)/$category_slug/(.+?)/$regions_slug/(.+?)/?\$" ]                          = 'index.php?page_id=' . $page_id . '&_' . $category_slug . '=$matches[2]&_' . $regions_slug . '=$matches[3]';
        }

        // Region home-page.
        if ( ! wpbdp_get_option( 'disable-cpt' ) ) {
            $rules[ "($rewrite_base)/$regions_slug/(.+?)/$pagination/?([0-9]{1,})/?\$" ] = 'index.php?' . $tax . '=$matches[2]&paged=$matches[3]';
            $rules[ "($rewrite_base)/$regions_slug/(.+?)/?\$" ]                          = 'index.php?' . $tax . '=$matches[2]';
        } else {
            $rules[ "($rewrite_base)/$regions_slug/(.+?)/$pagination/?([0-9]{1,})/?\$" ] = 'index.php?page_id=' . $page_id . '&region=$matches[2]&paged=$matches[3]';
            $rules[ "($rewrite_base)/$regions_slug/(.+?)/?\$" ]                          = 'index.php?page_id=' . $page_id . '&region=$matches[2]';
        }

        return $rules + $rules0;
    }

    public function category_link( $link, $category ) {
        global $post;

        // Do not append 'region' information to links inside listing views.
        if ( $post && isset( $post->post_type ) && WPBDP_POST_TYPE == $post->post_type ) {
            return $link;
        }

        return $this->filter_category_link( $link, $category );
    }

    /**
     * Adds a filter to include region informatin in category links.
     *
     * This filter will work even if $post is a BD Listing. However, it will
     * only be active on Category pages, before listings are rendered.
     *
     * @since 4.1.2
     */
    public function add_filter_for_category_link() {
        add_filter( 'wpbdp_category_link', array( $this, 'filter_category_link' ), 10, 2 );
        remove_action( 'wpbdp_category_link', array( &$this, 'category_link' ), 10, 2 );
    }

    /**
     * @since 4.1.2
     */
    public function remove_filter_for_category_link() {
        remove_filter( 'wpbdp_category_link', array( $this, 'filter_category_link' ), 10, 2 );
        add_action( 'wpbdp_category_link', array( &$this, 'category_link' ), 10, 2 );
    }

    /**
     * @since 4.1.2
     */
    public function filter_category_link( $link, $category ) {
        $api       = wpbdp_regions_api();
        $region_id = $api->get_active_region();

        if ( ! $region_id ) {
            return $link;
        }

        $region = get_term( $region_id, wpbdp_regions_taxonomy() );

        if ( ! $region ) {
            return $link;
        }

        if ( wpbdp_rewrite_on() ) {
            $query_string = '';

			if ( false !== preg_match( '/\\?(?<querystring>.*)/ui', wpbdp_get_server_value( 'REQUEST_URI' ), $matches ) ) {
                if ( ! empty( $matches['querystring'] ) ) {
                    $query_string = $matches['querystring'];
                }
            }

            $link_x = untrailingslashit( str_replace( wpbdp_get_page_link( 'main' ), '', $link ) );

            $link  = untrailingslashit( wpbdp_get_page_link( 'main' ) );
            $link .= '/' . ltrim( $link_x, '/' );
            $link .= '/' . wpbdp_get_option( 'regions-slug' ) . '/' . $region->slug;
            $link .= '/';
            $link .= $query_string ? '?' . $query_string : '';
        } else {
            $link = add_query_arg( wpbdp_regions_taxonomy(), $region->slug, $link );
        }

        return $link;
    }

    public function add_template_dir() {
        wpbdp_add_template_dir( WPBDP_REGIONS_MODULE_DIR . '/templates/' );
    }

    public function view_locations( $locations ) {
        $locations[] = WPBDP_REGIONS_MODULE_DIR . '/frontend/views/';

        return $locations;
    }

    public function is_taxonomy( $is_taxonomy ) {
        global $wp_query;

        if ( $is_taxonomy || ! wpbdp_regions_api()->get_active_region() ) {
            return $is_taxonomy;
        }

        $taxonomy = wpbdp_regions_taxonomy();

        if ( ! isset( $wp_query->query_vars[ $taxonomy ] ) ) {
            return $is_taxonomy;
        }

        return (bool) $wp_query->query_vars[ $taxonomy ];
    }

    /**
     * @since 5.0.9
     */
    public function region_seo_replacements( $replacements ) {
        if ( ! in_array( wpbdp_current_view(), array( 'show_category', 'show_region' ), true ) ) {
            return $replacements;
        }

        $region_id = wpbdp_regions_api()->get_active_region();

        if ( ! $region_id ) {
            return $replacements;
        }

		$region_tax = get_term( $region_id );

        if ( ! $region_tax ) {
            return $replacements;
        }

        $replacements['%%ct_wpbdm-region%%'] = $region_tax->name;
        return $replacements;
    }

    public function region_sidelist_map_integration( $args ) {
        if ( ! wpbdp_get_option( 'regions-show-sidelist' ) ) {
            return $args;
        }

        $args['position']['element']   = '.wpbdp-region-sidelist-wrapper';
        $args['position']['insertpos'] = 'before';

        return $args;
    }
}

function wpbdp_regions_region_page_title() {
    $term = null;

    if ( get_query_var( 'taxonomy' ) == wpbdp_regions_taxonomy() ) {
        $id   = get_query_var( 'term_id' );
        $slug = get_query_var( 'term' );

        if ( $id ) {
            $term = wpbdp_regions_api()->find_by_id( $id );
        } elseif ( $slug ) {
            $term = wpbdp_regions_api()->find_by_slug( $slug );
        }
    }

    return is_null( $term ) ? '' : esc_attr( $term->name );
}
