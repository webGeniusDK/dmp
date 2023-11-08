<?php
/**
 * Class WPBDP_RegionsAdmin
 *
 * @package Premium-Modules/Regions/Admin
 */

class WPBDP_RegionsAdmin {

    private $screen;
    private $table;

    public function __construct() {
        $this->screen = sprintf( 'edit-%s', wpbdp_regions_taxonomy() );

        $taxonomy  = wpbdp_regions_taxonomy();
        $post_type = WPBDP_POST_TYPE;

        add_action( 'parent_file', array( $this, 'parent_file' ) );

        add_action( 'admin_init', array( $this, 'admin_init' ) );
        add_action( 'admin_init', array( $this, 'setup' ) );

        add_action( 'admin_notices', array( $this, 'admin_notices' ) );

        if ( current_user_can( 'administrator' ) ) {
			add_filter( 'wpbdp_admin_menu_items', array( $this, 'menu' ) );
        }

        add_action( 'load-post.php', array( $this, 'enqueue_scripts' ) );
        add_action( 'load-post-new.php', array( $this, 'enqueue_scripts' ) );
        add_action( 'load-edit-tags.php', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_footer', array( $this, 'admin_footer' ) );

        add_filter( "edit_{$taxonomy}_per_page", array( $this, 'get_items_per_page' ), 10, 2 );
        add_filter( 'set-screen-option', array( $this, 'set_screen_option' ), 10, 3 );

        add_action( "add_meta_boxes_{$post_type}", array( $this, 'add_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_meta_box' ) );

        add_action( 'wp_ajax_wpbdp-regions-enable', array( $this, 'ajax' ) );
        add_action( 'wp_ajax_wpbdp-regions-disable', array( $this, 'ajax' ) );

		add_filter( 'wpseo_primary_term_taxonomies', array( $this, 'no_yoast_regions' ), 10, 2 );
    }

    /**
     * Overwrite $parent_file global variable so Regions sub menu (which
     * is actually the menu for Edit Region Taxonomy screen) appears to be
     * a sub menu of the Directory Admin menu, instead of a sub menu of Directory.å
     */
    public function parent_file( $parent_file ) {
        global $submenu_file;

        $_submenu_file = $this->tags_url();

        if ( strcmp( $submenu_file, $_submenu_file ) === 0 ) {
            return 'wpbdp_admin';
        }
        return $parent_file;
    }

	/**
	 * Move Regions sub menu lower in the Directory content.
	 */
    public function menu( $items ) {
		$items[ $this->tags_url() ] = array(
			'title' => __( 'Regions', 'wpbdp-regions' ),
		);
		return $items;
    }

	/**
	 * Get the url for the regions tags.
	 *
	 * @since 5.4.1
	 */
	private function tags_url() {
		$url = 'edit-tags.php?taxonomy=%s&amp;post_type=%s';
		return sprintf( $url, wpbdp_regions_taxonomy(), WPBDP_POST_TYPE );
	}

    /**
     * Handle bulk, settings actions.
     *
     * WP redirects if the action is not one of the standard
     * edit-tags actions. This function checks for posted data before the
     * redirection occurs.
     */
    public function admin_init() {
		add_filter( 'wpbdp_is_bd_page', array( $this, 'is_bd_page' ) );

        $reset_type = $this->reset_type();

        // handle reset buttons
        if ( $reset_type ) {
            check_admin_referer( $reset_type );
            $plugin = wpbdp_regions();

            switch ( $reset_type ) {
                case 'wpbdp-regions-create-default-regions':
                    $plugin->create_default_regions();
                    break;
                case 'wpbdp-regions-create-fields':
                    $plugin->create_fields();
                    break;
                case 'wpbdp-regions-factory-reset':
                    $plugin->factory_reset();
                    break;
                case 'wpbdp-regions-ignore-warning':
                    update_option( 'wpbdp-regions-ignore-warning', true );
                    break;
            }

            wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
        }

        // further checks only apply if we are editing tags
		$script = wpbdp_get_server_value( 'SCRIPT_FILENAME' );
        if ( strcmp( substr( $script, - strlen( 'edit-tags.php' ) ), 'edit-tags.php' ) !== 0 ) {
			return;
        }

        $nonce = wpbdp_get_var( array( 'param' => '_wpnonce' ), 'post' );
        if ( ! $nonce ) {
			return;
        }

        $api = wpbdp_regions_api();

		if ( wp_verify_nonce( $nonce, 'add-multiple-regions' ) ) {
			$this->add_multiple_regions();
			return;
		}

		if ( ! wp_verify_nonce( $nonce, 'bulk-tags' ) ) {
			return;
		}

		// handle bulk-actions
		$action  = wpbdp_get_var( array( 'param' => 'action', 'default' => -1 ), 'post' );
		$action2 = wpbdp_get_var( array( 'param' => 'action2' ), 'post' );
		if ( $action == -1 && $action2 ) {
			$action = $action2;
		}

            switch ( $action ) {
                case 'bulk-enable':
                    $fn = array( 'set_enabled', true );
                    break;
                case 'bulk-disable':
                    $fn = array( 'set_enabled', false );
                    break;
                case 'delete': // bulk-delete
                    // Force wp_get_referer() to return an URL instead of false. If false
                    // is returned edit-tags.php will drop all URL parameters like 'children'
                    // or 'filter' when redirecting.
                    //
                    // wp_get_referer() documentation says:
                    // Retrieve referer from '_wp_http_referer' or HTTP referer.
                    // If it's the same as the current request URL, will return false.
					$_SERVER['REQUEST_URI'] = add_query_arg( 'timestamp', current_time( 'timestamp' ), wpbdp_get_server_value( 'REQUEST_URI' ) );
					return;
                default:
                    // one of the standard actions, skip
                    return;
            }

		$regions = wpbdp_get_var( array( 'param' => 'delete_tags', 'default' => array() ), 'post' );
		foreach ( $regions as $region ) {
			call_user_func_array( array( $api, 'set_enabled' ), array( $region, $fn[1] ) );
		}
	}

	/**
	 * Find out which button was clicked.
	 *
	 * @since 5.2
	 */
	private function reset_type() {
		$buttons = array( 'wpbdp-regions-create-default-regions', 'wpbdp-regions-create-fields', 'wpbdp-regions-factory-reset', 'wpbdp-regions-ignore-warning' );
		foreach ( $buttons as $button ) {
			$var = wpbdp_get_var( array( 'param' => $button ), 'request' );
			if ( $var ) {
				return $button;
			}
		}

		return '';
	}

	/**
	 * @since 5.2
	 */
	private function add_multiple_regions() {
		$args   = array( 'param' => 'tag-name', 'sanitize' => 'sanitize_textarea_field' );
		$posted = wpbdp_get_var( $args, 'post' );
		$names  = explode( "\n", $posted );
		if ( count( $names ) < 2 ) {
			$names = explode( ',', $posted );
		}
		$parent = wpbdp_get_var( array( 'param' => 'parent', 'default' => 0 ), 'post' );

		$api = wpbdp_regions_api();
		foreach ( $names as $name ) {
			$api->insert( trim( $name ), $parent );
			unset( $name );
		}
	}

	/**
	 * @since 5.2.2
	 */
	public function is_bd_page( $is_page ) {
		$is_taxonomy = wpbdp_get_var( array( 'param' => 'taxonomy' ) ) === 'wpbdm-region';
		return $is_taxonomy || $is_page;
	}

    private function config_notices( $in_manage_page = false ) {
		global $wpdb, $wpbdp;

		if ( ! is_callable( array( $wpbdp, 'is_bd_page' ) ) || ! $wpbdp->is_bd_page() ) {
			return;
		}

		$current_page = wpbdp_get_var( array( 'param' => 'page' ) );
		if ( ( $in_manage_page || $current_page == 'wpbdp_admin_formfields' ) && ! get_option( 'wpbdp-regions-ignore-warning', false ) ) {
            $levels_missing = array();

            $rf_api = wpbdp_regions_fields_api();
            $fields = $rf_api->get_fields();

            foreach ( $fields as $l => $field_id ) {
                if ( ! $field_id || ! wpbdp_get_form_field( $field_id ) ) {
                    $levels_missing[] = $l;
                }
            }

            if ( $levels_missing ) {
                $error = sprintf(
					/* translators: %1$d: level count, %2$s: level list */
					__( 'Your Business Directory Regions hierarchy contains %1$d levels, but you are missing Region fields for some of them (level %2$s).', 'wpbdp-regions' ),
                    count( $fields ),
                    implode( ',', $levels_missing )
                );
				$error .= '<p><a href="' . wp_nonce_url( add_query_arg( 'wpbdp-regions-create-fields', 1 ), 'wpbdp-regions-create-fields' ) . '" class="button-primary">' .
					'<b>' . _x( 'Restore Region Form Fields', 'regions-module', 'wpbdp-regions' ) . '</b>' .
					'</a></p>';

				wpbdp_admin_message(
					$error,
					'notice-error is-dismissible',
					array( 'dismissible-id' => 'wpbdp-regions-ignore-warning' ),
				);
           }
		}
    }

    public function admin_notices() {
        global $pagenow;

		$current_tax = wpbdp_get_var( array( 'param' => 'taxonomy' ), 'request' );
		if ( $pagenow != 'edit-tags.php' || $current_tax != wpbdp_regions_taxonomy() ) {
            $this->config_notices( false );
            return;
        }

        $this->config_notices( true );

        $this->messages = isset( $this->messages ) ? $this->messages : array();

		$parent = $this->get_parent();
		if ( $parent ) {
            $region = wpbdp_regions_api()->find_by_id( $parent );

            if ( ! is_null( $region ) ) {
                $url              = esc_url( add_query_arg( 'children', 0 ) );
                $this->messages[] = sprintf(
                    /* translators: %1$s: Region Name, %2$s: opening <a> tag, %3$s: closing </a> tag */
                    esc_html__( 'You are currently seeing sub-regions of %1$s only. %2$sSee all regions%3$s.', 'wpbdp-regions' ),
                    '<strong>' . esc_html( $region->name ) . '</strong>',
                    '<a href="' . esc_url( $url ) . '">',
                    '</a>'
                );
            }
        }

        foreach ( $this->messages as $message ) {
			wpbdp_admin_message( $message, 'success' );
        }
    }

    public function setup() {
        $taxonomy = wpbdp_regions_taxonomy();
        add_filter( "manage_edit-{$taxonomy}_columns", array( $this, 'manage_columns' ) );
        add_filter( "manage_edit-{$taxonomy}_sortable_columns", array( $this, 'manage_sortable_columns' ) );
        add_filter( "manage_{$taxonomy}_custom_column", array( $this, 'manage_custom_column' ), 10, 3 );
        add_filter( "{$taxonomy}_row_actions", array( $this, 'row_actions' ), 10, 2 );

        add_filter( 'get_terms_args', array( $this, 'get_terms_args' ), 10, 2 );

        $this->setup_autocomplete();
    }

    public function enqueue_scripts() {
        global $typenow;

        if ( get_current_screen()->id === $this->screen ) {
            wp_enqueue_style( 'wpbdp-regions-style' );
            wp_enqueue_script( 'wpbdp-regions-admin' );
        }

        if ( $typenow === WPBDP_POST_TYPE ) {
            wp_enqueue_style( 'wpbdp-regions-style' );
        }
    }

    private function in_region_edit_screen() {
        $current_screen = get_current_screen();
        return ( $this->screen == $current_screen->id );
    }

    public function admin_footer() {
        if ( ! $this->in_region_edit_screen() ) {
            return;
        }

        // there are not enough hooks to add the features we need. I'm using
        // jQuery to create the required UI in Directory Regions screen
        wp_localize_script(
            'wpbdp-regions-admin',
            "ignore = 'me'; jQuery.RegionsData",
            array(
				'templates' => $this->templates(),
            )
        );
    }

    private function get_filter() {
		$args = array(
			'param'   => 'filter',
			'default' => 'all',
		);
        return wpbdp_get_var( $args, 'request' );
    }

    private function get_parent() {
        return (int) wpbdp_get_var( array( 'param' => 'children' ), 'request' );
    }

    public function get_views( $views ) {
        $filter    = $this->get_filter();
        $templates = array( '<a href="%1$s">%2$s</a>', '<strong>%2$s</strong>' );

        $_views = array(
            'enabled'         => _x( 'Enabled', 'regions-module', 'wpbdp-regions' ),
            'disabled'        => _x( 'Disabled', 'regions-module', 'wpbdp-regions' ),
            'all'             => _x( 'All', 'regions-module', 'wpbdp-regions' ),
        );

        foreach ( $_views as $id => $label ) {
            $views[ $id ] = sprintf( $templates[ $filter == $id ], esc_url( add_query_arg( 'filter', $id ) ), $label );
        }

        return $views;
    }

    private function get_edit_link( $region_id ) {
        return get_edit_term_link( $region_id, wpbdp_regions_taxonomy(), WPBDP_POST_TYPE );
    }

    public function row_actions( $_actions, $region ) {
        $actions = array();

        // no view, for now
        unset( $_actions['view'] );

		// Quick Edit is the desired way of editing a Region
        $query_args          = array(
            'taxonomy'  => wpbdp_regions_taxonomy(),
            'post_type' => WPBDP_POST_TYPE,
            'children'  => $region->term_id,
        );

        $actions = array_merge( $actions, $_actions );

		$actions['add-child wpbdp-add-taxonomy-form'] = '<a href="#">' . esc_html__( 'Add Child', 'wpbdp-regions' ) . '</a>';

		// This is used to change the link to a single page.
		$url  = add_query_arg( $query_args, admin_url( 'edit-tags.php' ) );
		$link = sprintf( '<a href="%s" class="hidden">%s</a>', esc_url( $url ), __( 'Show Sub-Regions', 'wpbdp-regions' ) );

		$actions['children'] = $link;

        return $actions;
    }

    public function manage_columns( $columns ) {
        unset( $columns['description'] );

        $columns['posts']          = _x( 'Listings in Region', 'regions-module', 'wpbdp-regions' );
        $columns['enabled']        = _x( 'Enabled', 'regions-module', 'wpbdp-regions' );
        $columns['frontend-links'] = _x( 'Frontend Links', 'regions-module', 'wpbdp-regions' );

        return $columns;
    }

    public function manage_sortable_columns( $columns ) {
        unset( $columns['description'] );

        return $columns;
    }

    public function manage_custom_column( $value, $column, $region_id ) {

        $actions = array();
        $regions = wpbdp_regions_api();

        switch ( $column ) {
            case 'enabled':
                if ( $regions->is_enabled( $region_id ) ) {
                    $actions['disable'] = _x( 'Disable', 'regions-module', 'wpbdp-regions' );
                    $value              = _x( 'Yes', 'regions-module', 'wpbdp-regions' );
                } else {
                    $actions['enable'] = _x( 'Enable', 'regions-module', 'wpbdp-regions' );
                    $value             = _x( 'No', 'regions-module', 'wpbdp-regions' );
                }
                break;
            case 'frontend-links':
                if ( ! $regions->is_enabled( $region_id ) ) {
                    break;
                }

				$output  = '<a href="' . esc_url( $regions->region_link( $region_id ) ) . '" class="display-link">' .
							esc_html__( 'Region home page', 'wpbdp-regions' ) .
							'</a><br />';
				$output .= '<a href="' . esc_url( $regions->region_listings_link( $region_id ) ) . '" class="display-link">' .
							esc_html__( 'Region listings', 'wpbdp-regions' ) .
							'</a>';

                return $output;
                break;

		}

        foreach ( $actions as $action => $label ) {
            $url                = add_query_arg( 'action', $action, $this->get_edit_link( $region_id ) );
            $actions[ $action ] = '<a href="' . esc_url( $url ) . '" >' . $label . '</a>';
        }

        $output  = "<span>$value</span> <br />";
        $output .= $this->get_regions_table()->row_actions( $actions );

        return $output;
    }

    /* Queries and other stuff */

    /**
     * See get_items_per_page at class-wp-list-table.php
     */
    public function get_items_per_page( $option, $default = 20 ) {
        $taxonomy = wpbdp_regions_taxonomy();

		$per_page = get_user_option( "edit_{$taxonomy}_per_page" );
		if ( $per_page ) {
            $per_page = (int) $per_page;
        } else {
            $option   = sprintf( 'edit_%s_per_page', str_replace( '-', '_', $taxonomy ) );
            $per_page = (int) get_user_option( $option );
        }

        if ( empty( $per_page ) || $per_page < 1 ) {
            $per_page = $default;
        }

        return $per_page;
    }

    /**
     * See set_screen_option at wp-admin/includes/misc.php
     */
    public function set_screen_option( $sanitized, $option, $value ) {
        $taxonomy = wpbdp_regions_taxonomy();
        $taxonomy = str_replace( '-', '_', $taxonomy );

		if ( $option !== "edit_{$taxonomy}_per_page" ) {
			return $sanitized;
		}

		$value = (int) $value;
		if ( $value < 1 || $value > 999 ) {
			return $sanitized;
		}
		return $value;
    }

    public function get_terms_args( $args, $taxonomies ) {
        static $_args = null;

        // internal affairs
        if ( isset( $args['wpbdp-regions-skip'] ) ) {
			return $args;
        }

        $screen = get_current_screen();
        if ( is_null( $screen ) || $screen->id != $this->screen ) {
            return $args;
        }

        // out of jurisdiction
        $taxonomy = wpbdp_regions_taxonomy();
        if ( ! in_array( $taxonomy, $taxonomies ) ) {
			return $args;
        }

        // most likely called from wp_dropdown_categories(), skip
        if ( isset( $args['class'] ) && $args['class'] === 'postform' ) {
            return $args;
        }

        // most likely called from _get_term_hierarchy, skip or
        // enjoy the infinte recursion!
        $children = get_option( "{$taxonomy}_children" );
        if ( ! is_array( $children ) ) {
			return $args;
        }

        // there is no need to calculate $_args more than once, because they
        // depend on the request data only
        if ( is_array( $_args ) ) {
			return array_merge( $args, $_args );
        }

        $regions = wpbdp_regions_api();
        $user    = wp_get_current_user();
        $_args   = array();
		$filter  = $this->get_filter();

		if ( in_array( $filter, array( 'enabled', 'disabled' ), true ) ) {
			if ( empty( $args['meta_query'] ) ) {
				$args['meta_query'] = array();
			}

			$_args['meta_query'][] = array(
				'key'     => 'enabled',
				'value'   => 0,
				'compare' => $filter === 'disabled' ? '=' : 'NOT EXISTS',
			);
        }

		$parent = $this->get_parent();
		if ( $parent ) {
            $hierarchy = array();
            $level     = $regions->get_region_level( $parent, $hierarchy );

            $params = array(
				'fields'             => 'ids',
				'hide_empty'         => false,
				'wpbdp-regions-skip' => true,
				'child_of'           => $parent,
			);

            $children = get_terms( $taxonomy, $params );

            array_splice( $children, 0, 0, $hierarchy );

            $_args['include'] = $children;

        }

        return array_merge( $args, $_args );
    }

    /* Additional UI */

    private function get_bulk_actions() {
        $actions                 = array();
        $actions['bulk-enable']  = _x( 'Enable', 'regions-module', 'wpbdp-regions' );
        $actions['bulk-disable'] = _x( 'Disable', 'regions-module', 'wpbdp-regions' );

        return $actions;
    }

    private function bulk_actions() {

        $options = array();
        foreach ( $this->get_bulk_actions() as $name => $title ) {
            $options[] = "\t<option value='$name'>$title</option>\n";
        }

        return join( '', $options );
    }

    private function regions_form() {
        ob_start();
            include WPBDP_REGIONS_MODULE_DIR . '/templates/form-add-regions.tpl.php';
            $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    private function views() {
        $table = $this->get_regions_table();

        if ( ! $table ) {
            return;
        }

        ob_start();
            add_filter( "views_{$this->screen}", array( $this, 'get_views' ) );
            $table->views();
            $views = ob_get_contents();
            remove_filter( "views_{$this->screen}", array( $this, 'get_views' ) );
        ob_end_clean();

        $html = '<div class="wpbdp-regions-views wp-clearfix"><span>%s</span>%s</div>';

        return sprintf( $html, __( 'Show Regions:', 'wpbdp-regions' ), $views );
    }

    public function templates() {
        return array(
            'bulk-actions'     => $this->bulk_actions(),
            // 'localize-form' => $this->localize_form(),
            'add-regions-form' => $this->regions_form(),
            'views'            => $this->views(),
        );
    }

    /* Ajax functions */

    public function ajax() {
		$region_id = wpbdp_get_var( array( 'param' => 'region', 'default' => 0 ), 'request' );
		$action    = wpbdp_get_var( array( 'param' => 'action' ), 'request' );
        $action    = str_replace( 'wpbdp-regions-', '', $action );

        $regions = wpbdp_regions_api();
        $updated = array();

        switch ( $action ) {
            case 'enable':
                $columns = array( 'enabled' );
                $result  = $regions->set_enabled( $region_id, true, $updated );
                break;

            case 'disable':
                $columns = array( 'enabled' );
                $result  = $regions->set_enabled( $region_id, false, $updated );
                break;
        }

        if ( $result ) {
            foreach ( $columns as $column ) {
                $html[ $column ] = $this->manage_custom_column( '', $column, $region_id );
            }
            $response = array(
				'success' => true,
				'html'    => $html,
				'updated' => $updated,
			);
        } else {
            $response = array();
        }

        header( 'Content-Type: application/json' );
        echo json_encode( $response );
        exit();
    }

    private function get_regions_table() {
        global $wp_list_table;

        if ( is_object( $wp_list_table ) ) {
            $this->table = $wp_list_table;
        }

        if ( ! is_object( $this->table ) ) {
            set_current_screen( $this->screen );
            $this->table = _get_list_table( 'WP_Terms_List_Table', array( 'screen' => $this->screen ) );
        }

        return $this->table;
    }

    /* Meta Box */

    public function add_meta_box() {
        $taxonomy  = wpbdp_regions_taxonomy();
        $post_type = WPBDP_POST_TYPE;

        // remove standard meta box for Regions taxonomy
        remove_meta_box( $taxonomy . 'div', $post_type, 'side' );

        add_meta_box(
            $taxonomy . '-meta-box',
            _x( 'Listing Region', 'regions meta box', 'wpbdp-regions' ),
            array( $this, 'meta_box' ),
            $post_type,
            'side',
            'core'
        );
    }

    public function meta_box() {
        global $post;

        wp_enqueue_script( 'wpbdp-regions-frontend' );

        $wpbdp_regions = wpbdp_regions();
        $regionfields  = wpbdp_regions_fields_api();

        $value = null;
        foreach ( $regionfields->get_visible_fields() as $field ) {
            $value = $regionfields->field_value( null, $post->ID, $field, false );
            echo $field->render( $value );
        }

		wp_nonce_field( 'regions_box', 'regions_nonce' );
    }

    public function save_meta_box( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        $post_type = WPBDP_POST_TYPE;
		$current_post = wpbdp_get_var( array( 'param' => 'post_type' ), 'post' );

		if ( $post_type !== $current_post ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

		$nonce = wpbdp_get_var( array( 'param' => 'regions_nonce' ), 'request' );
		if ( ! wp_verify_nonce( $nonce, 'regions_box' ) ) {
			return;
		}

        if ( ! isset( $_POST['listingfields'] ) ) {
            return;
        }

		// TODO: Sanitize this in a way that won't break fields with html and textareas.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        wpbdp_regions()->update_listing( $post_id, $_POST['listingfields'] );
    }

    // Autocomplete support. {{{
    private function setup_autocomplete() {

        // We use auto-complete if there are lots of regions. (More than 200).
        add_filter( 'taxonomy_parent_dropdown_args', array( &$this, '_return_nothing_for_dropdown' ), 10, 3 );
        add_action( wpbdp_regions_taxonomy() . '_add_form_fields', array( &$this, '_insert_autocomplete_field' ) );

        add_action( 'wp_ajax_wpbdp-regions-admin-autocomplete', array( &$this, '_ajax_parent_autocomplete' ) );
    }

    private function label_for_autocomplete( $region ) {
        $fields_api = wpbdp_regions_fields_api();
        $api        = wpbdp_regions_api();

        $level = $api->get_region_level( $region->term_id );
        $field = $fields_api->get_field_by_level( $level );

        $label = $region->name;

        if ( $field ) {
            $label .= ' (' . $field->get_label() . ')';
        }

		// $parent = '';
		// $parent_id = $region->parent;
		// if ( $parent_id > 0 ) {
		// $t = $api->find_by_id( $parent_id );
		// $parent .= sprintf( _x( ' (in %s)', 'autocomplete', 'wpbdp-regions' ), $t->name );
		// }
		//
		// $label = $label . ' ' . $parent;
        return wp_specialchars_decode( $label );
    }

    public function _ajax_parent_autocomplete() {
        $api  = wpbdp_regions_api();
        $term = trim( wpbdp_get_var( array( 'param' => 'term' ) ) );

        $items = $api->find(
            array(
				'hide_empty' => false,
				'name__like' => esc_html( $term ),
				'number'     => 20,
            )
        );

        foreach ( $items as &$i ) {
            $i->label = $this->label_for_autocomplete( $i );
            if ( $i->parent ) {
                $parent = $api->find_by_id( $i->parent );
                if ( $parent ) {
                    $i->parent_name = $parent->name;
                }
            }
        }

        $res = new WPBDP_Ajax_Response();
        $res->add( 'items', $items );
        $res->send();
    }

    public function _return_nothing_for_dropdown( $args, $taxonomy, $context = 'new' ) {
        if ( $taxonomy != wpbdp_regions_taxonomy() || 'new' != $context ) {
            return $args;
        }

        $args['name']    = '_parent';
        $args['include'] = array( 1 );
        $args['number']  = 1;

        return $args;
    }

    public function _insert_autocomplete_field() {
        echo '<div class="form-field term-parent-wrap">';
        echo '<label for="parent">';
		esc_html_e( 'Parent', 'wpbdp-regions' );
        echo '</label>';
        echo '<input class="required" name="parent" type="hidden" value="0" />';
        echo '<input name="parent_name" type="text" id="parent" />';
		echo '<span>';
		esc_html_e( 'Leave blank for top-level regions.', 'wpbdp-regions' );
		echo '</span>';
        echo '</div>';
    }

    public function is_admin_search() {
        if ( wpbdp_regions_taxonomy() !== wpbdp_get_var( array( 'param' => 'taxonomy' ) ) ) {
            return false;
        }

		if ( WPBDP_POST_TYPE !== wpbdp_get_var( array( 'param' => 'post_type' ) ) ) {
            return false;
        }

        return ! empty( $_GET['s'] );
    }

    // }}}

	/**
	 * When there are a lot of regions, Yoast fetches all at once and prevents the
	 * page from loading. This prevents regions from running through Yoast for link suggestions.
	 *
	 * @since 5.2.2
	 */
	public function no_yoast_regions( $all_taxonomies, $post_type ) {
		if ( $post_type === 'wpbdp_listing' && isset( $all_taxonomies['wpbdm-region'] ) ) {
			unset( $all_taxonomies['wpbdm-region'] );
		}
		return $all_taxonomies;
	}
}
