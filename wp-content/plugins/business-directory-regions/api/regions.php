<?php
/**
 * @package Premium-Modules/Regions/API/RegionsAPI
 */

function wpbdp_regions_taxonomy() {
    return WPBDP_RegionsPlugin::TAXONOMY;
}

function wpbdp_regions_api() {
    return WPBDP_RegionsAPI::instance();
}

class WPBDP_RegionsAPI {

    public $session = array();

    private static $instance = null;
    private $max_level       = null;
    private $active_region   = null;

    private $counts_cache = array();


    const META_TYPE = 'term';

    private function __construct() {
        add_action( 'wpbdp_query_flags', array( &$this, 'determine_active_region' ), 11, 1 );
    }

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new WPBDP_RegionsAPI();
        }
        return self::$instance;
    }

    /* Metadata API */

    public function update_meta( $region_id, $meta_key, $meta_value, $prev_value = '' ) {
		if ( $meta_key === 'enabled' && $meta_value ) {
			// Only save disabled flag.
			return $this->delete_meta( $region_id, $meta_key );
		}
        return update_metadata( self::META_TYPE, $region_id, $meta_key, $meta_value, $prev_value );
    }

    public function delete_meta( $region_id, $meta_key, $meta_value = '', $delete_all = false ) {
        return delete_metadata( self::META_TYPE, $region_id, $meta_key, $meta_value, $delete_all );
    }

    public function get_meta( $region_id, $meta_key = '', $single = false ) {
        return get_metadata( self::META_TYPE, $region_id, $meta_key, $single );
    }

    /* Set/Get methods */

    public function set_enabled( $region_id, $enabled, &$regions = array() ) {
        $result = false;

        if ( empty( $regions ) ) {
            // disable child regions too
            if ( $enabled === false ) {
                $_regions = get_term_children( $region_id, wpbdp_regions_taxonomy() );
                $regions  = array_merge( (array) $regions, array( $region_id ), $_regions );
            }

            // enable parent regions too
            if ( $enabled === true ) {
                $this->get_region_level( $region_id, $regions );
            }
        }

        foreach ( $regions as $region ) {
            $result = $this->update_meta( $region, 'enabled', (int) $enabled ) || $result;
        }

        $this->clean_visible_regions_cache();

        return $result;
    }

    public function is_enabled( $region_id ) {
        $enabled = $this->get_meta( $region_id, 'enabled', true );
        return $enabled === '' ? true : (bool) $enabled;
    }

    /**
	 * Turns out that if there is another term, in a different taxonomy,
	 * which has the same slug as one of the Regions, but a strictly
	 * different name, the generated Region will have a slug with
	 * with a number as suffix to remove the conflict.
	 *
	 * The problem is, the next time this function is called, it will
	 * create duplicate Regions, because WP is unable to find the already
	 * created Region due to the slightly different slug.
	 *
	 * That's why we need to try to find the Region by its name, instead
	 * of using the slug (which is what WP does), and skip the insert
	 * step if a Region is found in the desired level.
	 */
    public function exists( $name, $parent = 0 ) {
		$term_id = term_exists( $name );
		if ( $term_id ) {
			$term_id = term_exists( $name, wpbdp_regions_taxonomy(), $parent );
			if ( $term_id ) {
				return $term_id;
			}
        }
        return false;
    }

    public function find( $args ) {
        static $regions = array();

		$args['taxonomy'] = wpbdp_regions_taxonomy();

		/**
		 * Modify the args sent to get_terms to include or exclude extra regions.
		 *
		 * @since 5.2
		 */
		$args = apply_filters( 'wpbdp_region_find_args', $args );

        $key             = md5( serialize( $args ) );
		$regions[ $key ] = isset( $regions[ $key ] ) ? $regions[ $key ] : get_terms( $args );

        return $regions[ $key ];
    }

    public function find_by_id( $region_id ) {
		if ( empty( $region_id ) ) {
			return 0;
		}

        $taxonomy = wpbdp_regions_taxonomy();
        return get_term( $region_id, $taxonomy );
    }

    public function find_by_name( $region, $level_hint = 0, $parents = array() ) {
        $options = array();
        $results = get_terms(
            wpbdp_regions_taxonomy(), array(
				'hide_empty' => 0,
				'name__like' => $region,
            )
        );

        foreach ( $results as $x ) {
            if ( $x->name != $region ) {
                continue;
            }

            $options[] = $x;
        }

        if ( ! $options ) {
            return false;
        }

        if ( $level_hint ) {
            foreach ( $options as $region ) {
                $region->region_level = $this->get_region_level( $region->term_id );
            }

            usort(
                $options,
				function( $a, $b ) {
					return abs( $a->region_level - ' . $level_hint . ' ) - abs( $b->region_level - ' . $level_hint . ' );
				}
            );
        }

        if ( ! $parents ) {
            return $options[0];
        }

        $taxonomy = wpbdp_regions_taxonomy();

        foreach ( $options as $option ) {
            $option_parents          = get_ancestors( $option->term_id, $taxonomy );
            $option_parents_by_level = array();
            $skip_option             = false;

            foreach ( $option_parents as $index => $region_id ) {
                $option_parents_by_level[ $option->region_level - $index - 1 ] = get_term_by( 'id', $region_id, $taxonomy );
            }

            foreach ( $parents as $parent_level => $parent_name ) {
                if ( ! isset( $option_parents_by_level[ $parent_level ] ) ) {
                    $skip_option = true;
                    break;
                }

                if ( $parent_name[0] != $option_parents_by_level[ $parent_level ]->name ) {
                    $skip_option = true;
                    break;
                }
            }

            if ( $skip_option ) {
                continue;
            }

            return $option;
        }

        return $options[0];
    }

    public function find_by_slug( $region ) {
        $taxonomy = wpbdp_regions_taxonomy();
        return get_term_by( 'slug', $region, $taxonomy );
    }

    public function find_top_level_regions( $args = array() ) {
        $args = wp_parse_args(
            $args, array(
				'parent'             => 0,
				'hide_empty'         => false,
				'get'                => 'all',
				'orderby'            => 'id',
				'fields'             => 'ids',
				'wpbdp-regions-skip' => true,
            )
        );
        return $this->find( $args );
    }

    /**
     * Find Regions by level in the hierarchy.
     *
     * @param $hierarchy    array    holds the ID of all ancestors of the Regions returned
     */
    private function _find_regions_by_level( $level = 1, $terms = array(), $parents = null, &$hierarchy = array() ) {
        if ( $parents || is_array( $parents ) ) {
            $parents = (array) $parents;

            if ( empty( $parents ) ) {
                return array();
            }

            $k = $this->get_region_level( $parents[0] );

            if ( $k === $level ) {
                return $parents;
            }
            if ( $k > $level ) {
                return array();
            }

            $regions = $parents;
        } else {
            $k       = 1;
            $regions = $this->find_top_level_regions();
        }

        // if $level is false, we check as many levels as possible
        while ( $level === false ? ! empty( $regions ) : $k < $level ) {
			++$k;

            $_regions = array();
            foreach ( $regions as $region ) {
                if ( ! empty( $region ) && is_array( $region ) ) {
                    $region_key = array_keys( $region )[0];
                } else {
                    $region_key = $region;
                }
                if ( is_array( $terms ) && $region_key && ! empty( $terms[ $region_key ] ) && is_array( $terms[ $region_key ] ) ) {
                    $_regions = array_merge( $_regions, $terms[ $region ] );
                    // store the ID of the ancestors of the Regions being returned
                    $hierarchy[] = $region;
                }
            }

            $regions = $_regions;
        }

        if ( $level === 1 ) {
            // use array_values to reset key indexes
            $regions = array_values( array_intersect( $regions, array_keys( $terms ) ) );
        }

        $this->max_level = $k;

        return $regions;
    }

    public function find_regions_by_level( $level ) {
        return $this->_find_regions_by_level( $level, $this->get_regions_hierarchy() );
    }

    public function find_visible_regions_by_level( $level, $parent = null ) {
        return $this->_find_regions_by_level( $level, $this->get_visible_regions_hierarchy(), $parent );
    }

    public function find_sidelisted_regions_by_level( $level ) {
        return $this->_find_regions_by_level( $level ? $level : 1, $this->get_sidelisted_regions_hierarchy() );
    }

    private function _get_hierarchy( $option, $args = array() ) {
        $option   = apply_filters( 'wpbdp_regions__get_hierarchy_option', $option, $args );
        $children = get_option( $option );
        if ( is_array( $children ) ) {
			return $children;
        }

		$default = array(
			'orderby'            => 'id',
			'fields'             => 'id=>parent',
			'wpbdp-regions-skip' => true,
		);

		$terms = $this->find( wp_parse_args( $args, $default ) );

        $children = array();
        foreach ( $terms as $term_id => $parent ) {
            if ( $parent > 0 ) {
                $children[ $parent ][] = $term_id;
            } elseif ( ! isset( $children[ $term_id ] ) ) {
                // also save top-level regions with no children
                $children[ $term_id ] = array();
            }
        }

        update_option( $option, $children, 'no' );

        return $children;
    }

    public function get_regions_hierarchy() {
        return _get_term_hierarchy( wpbdp_regions_taxonomy() );
    }

    public function get_sidelisted_regions_hierarchy() {
		return $this->_get_hierarchy( 'wpbdp-visible-regions-children' );
    }

    public function get_visible_regions_hierarchy() {
        return $this->_get_hierarchy(
			'wpbdp-visible-regions-children',
			array(
				'meta_query' => array(
					array(
						'key'     => 'enabled',
						'value'   => 0,
						'compare' => 'NOT EXISTS',
					),
				),
				'hide_empty' => false,
            )
        );
    }

    private function set_regions_cookie( $value = '', $expiration = 1209600 ) {
		$user = wp_get_current_user();
		if ( $user ) {
            $cookies[] = sprintf( 'wpbdp-regions-active-regions-%d', $user->ID );
        }
        $cookies[] = sprintf( 'wpbdp-regions-active-regions', $user->ID );

        $expire = time() + $expiration;
        $secure = is_ssl();

        foreach ( $cookies as $cookie ) {
            setcookie( $cookie, $value, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure, true );
            if ( COOKIEPATH != SITECOOKIEPATH ) {
                setcookie( $cookie, $value, $expire, SITECOOKIEPATH, COOKIE_DOMAIN, $secure, true );
            }
        }
    }

    public function set_active_region( $region ) {
        if ( is_object( $region ) ) {
            $this->active_region = $region;
        }

        if ( is_numeric( $region ) ) {
            $this->set_active_region( $this->find_by_id( (int) $region ) );
        } elseif ( is_string( $region ) ) {
            $this->set_active_region( $this->find_by_slug( $region ) );
        }

        $this->set_active_region = null;
    }

    public function clear_active_region() {
        $this->active_region = null;
        $this->set_regions_cookie( '', -3600 );
    }

    public function get_active_region() {
        if ( $this->active_region ) {
            return $this->active_region->term_id;
        }

        return null;
    }

    public function get_active_region_by_level( $level ) {
        $current = $this->get_active_region();

        if ( ! $current ) {
            return null;
        }

        $hierarchy     = array();
        $active        = array();
        $current_level = $this->get_region_level( $current, $hierarchy );

        for ( $i = 1; $i <= $current_level; $i++ ) {
            $active[ $i ] = $hierarchy[ $current_level - $i ];
        }

        return wpbdp_getv( $active, $level, null );
    }

    public function get_region_level( $region_id, &$hierarchy = array() ) {
        $taxonomy = wpbdp_regions_taxonomy();
        $level    = 0;

        do {
            $term = get_term( $region_id, $taxonomy );
            if ( is_null( $term ) || is_wp_error( $term ) ) {
                break;
            }

            array_push( $hierarchy, $region_id );

            $region_id = $term->parent;
            $level++;
        } while ( $region_id > 0 );

        return $level;
    }

    public function get_max_level() {
        $max = get_option( 'wpbdp-regions-max-level', null );

        if ( is_null( $max ) ) {
            $this->find_regions_by_level( false );
            $max = $this->max_level - 1;
            update_option( 'wpbdp-regions-max-level', $max );
        }

        return $max;
    }

    /* Cache */

    public function clean_regions_cache() {
        delete_option( 'wpbdp-regions-max-level' );
        $this->clean_regions_count_cache();
        $this->clean_visible_regions_cache();

        // trigger fields detection
        update_option( 'wpbdp-regions-create-fields', true );
    }

    public function clean_regions_count_cache() {
        delete_option( 'wpbdp-category-regions-count' );
        do_action( 'wpbdp_regions_clean_cache', 'wpbdp-category-regions-count' );
    }

    public function clean_visible_regions_cache() {
        delete_option( 'wpbdp-visible-regions-children' );

        do_action( 'wpbdp_regions_clean_cache', 'wpbdp-visible-regions-children' );
    }

    /* Taxonomy API integration */

    public function insert( $name, $parent = 0 ) {
        return wp_insert_term( trim( $name ), wpbdp_regions_taxonomy(), array( 'parent' => $parent ) );
    }

    public function determine_active_region( $wp_query ) {
        if ( $this->active_region ) {
            return;
        }

        $region = null;

        if ( isset( $wp_query->query_vars[ wpbdp_regions_taxonomy() ] ) ) {
            $region = $this->find_by_slug( $wp_query->query_vars[ wpbdp_regions_taxonomy() ] );
        }

        if ( isset( $wp_query->query_vars['region'] ) ) {
            $region = $this->find_by_slug( $wp_query->query_vars['region'] );
        }

        $regions_slug = urlencode( wpbdp_get_option( 'regions-slug' ) );
        if ( isset( $wp_query->query_vars[ '_' . $regions_slug ] ) ) {
            $region = $this->find_by_slug( $wp_query->query_vars[ '_' . $regions_slug ] );
        }

        $this->set_active_region( $region );

        // Other adjustments.
        if ( $region && ! $wp_query->get( WPBDP_CATEGORY_TAX ) && empty( $wp_query->wpbdp_is_main_page ) && ! $wp_query->wpbdp_is_shortcode ) {
            $wp_query->wpbdp_view      = 'show_region';
            $wp_query->wpbdp_our_query = true;
        }
    }

    // {{{ Link generation
    public function remove_url_filter( $url ) {
        $slug = wpbdp_get_option( 'regions-slug' );

        if ( wpbdp_rewrite_on() ) {
            $newurl = preg_replace( '/(' . $slug . '\\/[^\\/]*)/ui', '', $url );

            if ( false === strpos( $newurl, '?' ) ) {
                $newurl = trailingslashit( $newurl );
            }
        }

        $newurl = remove_query_arg( $slug, $newurl );

        return $newurl;
    }

    public function region_link( $region, $smart = false, $origin = array() ) {
		$new_link = $this->regions_default_link( $region );
		if ( ! is_array( $new_link ) ) {
			return $new_link;
		}

		$action = $origin ? $origin['action'] : wpbdp_current_view();
		$is_cat = in_array( $action, array( 'browsecategory', 'show_category' ), true );

		$region = $new_link['region'];

		// This has a value when the normal WP links are used.
		if ( ! empty( $new_link['new_link'] ) ) {
			$simple_link = $new_link['new_link'];

			if ( $is_cat && $smart ) {
				// Filter by region + category
				$this->add_category_to_region_link( $simple_link );
			}

			return $simple_link;
		}

		// This is only reached when the old permalinks are used.
		$base_page = wpbdp_get_page_link( 'main' );
		$main_page = $base_page;

        if ( $origin && ! empty( $origin['referer'] ) ) {
            $base_page = $origin['referer'];
        }

		$actions = array( 'browsecategory', 'show_category', 'all_listings', 'main' );

		if ( ! $smart || ! wpbdp_rewrite_on() || ! in_array( $action, $actions, true ) ) {
			$url = add_query_arg( wpbdp_regions_taxonomy(), $region->slug, $base_page );
			return apply_filters( 'wpbdp_region_link', $url, $region, $smart, $origin );
		}

		$region_slug = wpbdp_get_option( 'regions-slug' );

		if ( $is_cat ) {
			$this->base_page_url( $origin, $base_page );

			$slug    = wpbdp_get_option( 'permalinks-category-slug' );
			$pattern = '/(?<category>' . $slug . '\\/[^\\/]*)/ui';

			if ( false == preg_match( $pattern, $base_page, $matches ) ) {
				return $base_page;
			}

			// Remove current region from URI.
			$base_page = preg_replace( '/(' . $region_slug . '\\/[^\\/]*)/ui', '', $base_page );

			$url = preg_replace(
				$pattern,
				$matches['category'] . '/' . $region_slug . '/' . $region->slug,
				untrailingslashit( $base_page )
			);
		} else {
			$url = untrailingslashit( $main_page ) . '/' . $region_slug . '/' . $region->slug . '/';
		}

		return apply_filters( 'wpbdp_region_link', $url, $region, $smart, $origin );
	}

	/**
	 * @since 5.3.1
	 */
	private function base_page_url( $origin, &$base_page ) {
		global $wp_rewrite;

		$base_page = $origin ? $origin['referer'] : wpbdp_get_server_value( 'REQUEST_URI' );

		// Remove pagination from base page.
		$pagination_slug = $wp_rewrite->pagination_base;
		$base_page       = preg_replace( "/(\/{$pagination_slug}\/[0-9]+)/uis", '', $base_page );
		$base_page       = str_replace( '//', '/', $base_page );
	}

	/**
	 * Add current category to region url to get listings
	 * in both the region and category.
	 *
	 * @since 5.3.1
	 */
	private function add_category_to_region_link( &$link ) {
		$category = get_queried_object();
		if ( ! empty( $category->term_id ) ) {
			$link = add_query_arg( wpbdp_get_option( 'permalinks-category-slug' ), $category->slug, $link );
		}
	}

    public function region_home( $region ) {
        $region = is_object( $region ) ? $region : get_term( $region, wpbdp_regions_taxonomy() );

        if ( ! $region ) {
            return '';
        }

        $main_page = wpbdp_get_page_link( 'main' );
        $url       = '';

        if ( wpbdp_rewrite_on() ) {
            $url = untrailingslashit( $main_page ) . '/' . wpbdp_get_option( 'regions-slug' ) . '/' . $region->slug . '/';
        } else {
            $url = add_query_arg( 'region', $region->slug, $main_page );
        }

        return apply_filters( 'wpbdp_region_home_link', $url, $region );
    }

	public function region_listings_link( $region, $link = '' ) {
		$new_link = $this->regions_default_link( $region, $link );
		if ( ! is_array( $new_link ) ) {
			return $new_link;
		}

		if ( ! empty( $new_link['new_link'] ) ) {
			return $new_link['new_link'];
		}

		// This is only reached when the old permalinks are used.
		$region = $new_link['region'];
		$url    = $new_link['default_link'];

		if ( wpbdp_rewrite_on() ) {
			$url = wpbdp_url( '/' . wpbdp_get_option( 'regions-slug' ) . '/' . $region->slug . '/' );
		} elseif ( empty( $url ) ) {
			$url = add_query_arg( array( wpbdp_get_option( 'regions-slug' ) => $region->slug ), home_url( '/' ) );
		}

		return apply_filters( 'wpbdp_region_listings_link', $url, $region );
	}

	/**
	 * @since 5.3.1
	 */
	private function regions_default_link( $region, $link = '' ) {
		$region = is_object( $region ) ? $region : get_term( $region, wpbdp_regions_taxonomy() );

		if ( ! $region || is_wp_error( $region ) ) {
			return '';
		}

		$default_link = $link ? $link : get_term_link( $region );
		$new_link     = '';

		/**
		 * Use the unmodified WP permalinks.
		 *
		 * @since 5.3
		 */
		if ( apply_filters( 'wpbdp_hierarchical_links', false ) ) {
			$new_link = apply_filters( 'wpbdp_region_listings_link', $default_link, $region );
		}

		return compact( 'region', 'default_link', 'new_link' );
	}

    // }}}
    public function fix_regions_count( &$regions, $category_id = 0 ) {
        $include_subregions    = true;
        $include_subcategories = true;
        $use_cache             = true;
        $taxonomy              = WPBDP_CATEGORY_TAX;

        if ( ! $category_id && ( ! function_exists( 'wpbdp_current_category_id' ) || ! function_exists( 'wpbdp_current_tag_id' ) ) ) {
            return;
        }

        $tax_id = $category_id ? $category_id : wpbdp_current_category_id();

        if ( ! $tax_id ) {
            $taxonomy = WPBDP_TAGS_TAX;
            $tax_id   = wpbdp_current_tag_id();

            if ( ! $tax_id ) {
                return;
            }
        }

        foreach ( $regions as &$r ) {
            $cnt      = $this->count_listings( $r->term_id, $tax_id, $include_subregions, $include_subcategories, $use_cache, $taxonomy );
            $r->count = $cnt;
        }

    }

    public function count_listings( $region_id, $category_id = 0, $include_subregions = true, $include_subcategories = true, $use_cache = true, $taxonomy = WPBDP_CATEGORY_TAX ) {
        global $wpdb;

        $hash = $region_id . '-' . $category_id . '-' . intval( $include_subregions ) . '-' . intval( $include_subcategories );

        if ( $use_cache && isset( $this->counts_cache[ $hash ] ) ) {
            return intval( $this->counts_cache[ $hash ] );
        }

        $region_tree_ids = array( $region_id );
        if ( $include_subregions ) {
            $region_tree_ids = array_merge( $region_tree_ids, get_term_children( $region_id, wpbdp_regions_taxonomy() ) );
        }

        $category_tree_ids = array();
        if ( $category_id ) {
            $category_tree_ids[] = $category_id;

            if ( $include_subcategories ) {
                $category_tree_ids = array_merge( $category_tree_ids, get_term_children( $category_id, $taxonomy ) );
            }
        }

        // Convert term_ids into taxonomy_term_ids
        $region_tt_ids   = $wpdb->get_col( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id IN (" . implode( ',', $region_tree_ids ) . ') AND taxonomy = %s', wpbdp_regions_taxonomy() ) );
        $category_tt_ids = $category_tree_ids ? $wpdb->get_col( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id IN (" . implode( ',', $category_tree_ids ) . ') AND taxonomy = %s', $taxonomy ) ) : array();

        if ( $category_tt_ids ) {
            $query = $wpdb->prepare(
                "SELECT COUNT(DISTINCT tr.object_id) FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_relationships} tr2 ON tr.object_id = tr2.object_id INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE p.post_status = %s AND p.post_type = %s AND tr.term_taxonomy_id IN (" . implode( ',', $region_tt_ids ) . ') AND tr2.term_taxonomy_id IN (' . implode( ',', $category_tt_ids ) . ')',
                'publish',
                WPBDP_POST_TYPE
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT COUNT(DISTINCT tr.object_id) FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE p.post_status = %s AND p.post_type = %s AND tr.term_taxonomy_id IN (" . implode( ',', $region_tt_ids ) . ')',
                'publish',
                WPBDP_POST_TYPE
            );
        }

        $count                       = intval( $wpdb->get_var( $query ) );
        $this->counts_cache[ $hash ] = $count;

        return $count;
    }

	/**
	 * @deprecated 5.4
	 */
	public function add_meta( $region_id, $meta_key, $meta_value, $unique = false ) {
		_deprecated_function( __METHOD__, '5.4', 'WPBDP_RegionsAPI->update_meta' );
		return $this->update_meta( $region_id, $meta_key, $meta_value );
	}

	/**
	 * @deprecated 5.2
	 */
    public function set_sidelist_status( $region_id, $on_sidelist, &$regions = array() ) {
		_deprecated_function( __METHOD__, '5.2' );

        $result = false;

        if ( empty( $regions ) ) {
            if ( $on_sidelist === false ) {
                $_regions = get_term_children( $region_id, wpbdp_regions_taxonomy() );
                $regions  = array_merge( (array) $regions, array( $region_id ), $_regions );
            }

            if ( $on_sidelist === true ) {
                $this->get_region_level( $region_id, $regions );
            }
        }

        foreach ( $regions as $region ) {
            $result = $this->update_meta( $region, 'sidelist', (int) $on_sidelist ) || $result;
        }

        // adding a region to the sidelist enables it automatically
        if ( $result && $on_sidelist ) {
            $this->set_enabled( $region_id, true, $regions );
        }

        $this->clean_sidelisted_regions_cache();

        return $result;
    }

	/**
	 * @deprecated 5.3.1
	 */
    public function region_term_link( $region, $term ) {
		_deprecated_function( __METHOD__, '5.3.1' );
    }

	/**
	 * @deprecated 5.2
	 */
    public function on_sidelist( $region_id ) {
		_deprecated_function( __METHOD__, '5.2' );
        return true;
    }

	/**
	 * @deprecated 5.2
	 */
	public function clean_sidelisted_regions_cache() {
		_deprecated_function( __METHOD__, '5.2' );

		delete_option( 'wpbdp-sidelisted-regions-children' );

		do_action( 'wpbdp_regions_clean_cache', 'wpbdp-sidelisted-regions-children' );
	}
}
