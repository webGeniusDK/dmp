<?php
/**
 * Plugin Name: Business Directory Regions
 * Description: Add the ability to filter your Business Directory plugin listings by any region you can configure (city, state, county, village, etc).
 * Plugin URI: https://businessdirectoryplugin.com
 * Version: 5.4.3
 * Author: Business Directory Team
 * Author URI: https://businessdirectoryplugin.com
 * Text Domain: wpbdp-regions
 * Domain Path: /translations/
 *
 * @package Premium-Modules/Regions
 */

define( 'WPBDP_REGIONS_MODULE_BASENAME', trailingslashit( basename( dirname( __FILE__ ) ) ) );
define( 'WPBDP_REGIONS_MODULE_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WPBDP_REGIONS_MODULE_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

require_once WPBDP_REGIONS_MODULE_DIR . '/api/class-regions-listing-search.php';
require_once WPBDP_REGIONS_MODULE_DIR . '/api/form-fields.php';
require_once WPBDP_REGIONS_MODULE_DIR . '/api/regions.php';
require_once WPBDP_REGIONS_MODULE_DIR . '/admin/admin.php';
require_once WPBDP_REGIONS_MODULE_DIR . '/frontend/frontend.php';
require_once WPBDP_REGIONS_MODULE_DIR . '/installer.php';

function wpbdp_regions() {
	return WPBDP_RegionsPlugin::instance();
}

class WPBDP_RegionsPlugin {

	private static $instance = null;
	private $temp = array();

	public $settings_url = 'admin.php?page=wpbdp_settings&tab=regions';

	/**
	 * Registry of all options used.
	 * */
	public $options = array(
		// registered settings
		'wpbdp-regions-show-sidelist',

		// internal settings
		'wpbdp-regions-db-version',

		'wpbdp-regions-create-default-regions',
		'wpbdp-regions-create-fields',
		'wpbdp-regions-show-fields',

		'wpbdp-regions-create-default-regions-error',
		'wpbdp-regions-create-fields-error',

		'wpbdp-regions-flush-rewrite-rules',
		'wpbdp-regions-factory-reset',

		'wpbdp-regions-form-fields',
		'wpbdp-regions-form-fields-options',
		'wpbdp-regions-max-level',

		'wpbdp-visible-regions-children',
	);


	const REQUIRED_BD_VERSION = '5.7.6';

	const TAXONOMY = 'wpbdm-region';

	public function __construct() {
		$this->id                  = 'regions';
		$this->title               = 'Regions Module';
		$this->file                = __FILE__;
		$this->required_bd_version = self::REQUIRED_BD_VERSION;

		$this->version = '5.4.3';

		$this->installer = new WPBDP_RegionsPluginInstaller( $this );

		$file = WP_CONTENT_DIR . '/plugins/' . basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ );
		register_activation_hook( $file, array( $this->installer, 'activate' ) );
		register_deactivation_hook( $file, array( $this->installer, 'deactivate' ) );
	}

	public function get_version() {
		return $this->version;
	}

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new WPBDP_RegionsPlugin();
		}
		return self::$instance;
	}

	public function _admin_notices() {
		if ( ! current_user_can( 'administrator' ) ) {
			return;
		}

		global $wpbdp;
		if ( ! is_callable( array( $wpbdp, 'is_bd_page' ) ) || ! $wpbdp->is_bd_page() ) {
			return;
		}

		$message = get_option( 'wpbdp-regions-create-fields-error' );
		if ( $message ) {
			wpbdp_admin_message( $message, 'error' );
		}

		$errors = get_option( 'wpbdp-regions-create-default-regions-error' );
        if ( $errors ) {
			$message  = esc_html__( 'There were one or more errors trying to create the default regions:', 'wpbdp-regions' );
			$message .= '<br/><strong>' . implode( '<br/>', $errors ) . '</strong>';
			wpbdp_admin_message( $message, 'error' );
		}
    }

	public function init() {
		global $wpdb;

		add_action( 'admin_notices', array( $this, '_admin_notices' ) );

		add_action( 'wpbdp_register_settings', array( $this, 'register_settings' ) );

		// If using default urls, adjust them now.
		if ( ! wpbdp_get_option( 'regions-legacy-urls' ) ) {
			add_filter( 'wpbdp_hierarchical_links', '__return_true' );
		}

		$this->admin    = new WPBDP_RegionsAdmin();
		$this->frontend = new WPBDP_RegionsFrontend();

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		$regions = wpbdp_regions_api();
		$fields  = wpbdp_regions_fields_api();

		// WP Query integratrion
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ), 20 );

		add_action( 'post_updated', array( $this, 'post_updated' ), 10, 3 );
		add_action( 'trashed_post', array( $this, 'post_trashed' ), 10, 1 );

		// Taxonomy API integration
		add_filter( 'term_link', array( &$this, 'term_link' ), 10, 3 );
		add_filter( 'taxonomy_template', array( $this, 'taxonomy_template' ) );
		add_filter( 'get_terms', array( $this, 'get_terms' ), 10, 3 );
		add_action( 'set_object_terms', array( $this, 'set_object_terms' ), 10, 6 );

		// BD-related taxonomy filters
		add_filter( '_wpbdp_padded_count', array( $this, '_padded_count' ), 10, 2 );

		add_action( sprintf( 'created_%s', self::TAXONOMY ), array( $this, 'set_term_attributes' ) );

		add_action( sprintf( 'created_%s', self::TAXONOMY ), array( $regions, 'clean_regions_cache' ) );
		add_action( sprintf( 'edited_%s', self::TAXONOMY ), array( $regions, 'clean_regions_cache' ) );
		add_action( sprintf( 'delete_%s', self::TAXONOMY ), array( $regions, 'clean_regions_cache' ) );

		// Business Directory Form Fields API integration
		add_action( 'wpbdp_modules_init', array( &$this, 'fields_init' ) );
		add_action( 'wpbdp_modules_init', array( &$this, '_init' ) );

		// Business Directory Listings API integration
		add_action( 'WPBDP_Listing::set_field_values', array( $this, 'update_listing' ), 10, 2 );
	}

	public function fields_init() {
		$fieldsapi = wpbdp_formfields_api();
		$fieldsapi->register_association( 'region', _x( 'Post Region', 'form-fields api', 'wpbdp-regions' ), array( 'private' ) );

		$fields = wpbdp_regions_fields_api();

		add_filter( 'wpbdp_form_field_value', array( $fields, 'field_value' ), 10, 3 );
		add_filter( 'wpbdp_form_field_store_value', array( $fields, 'store_value' ), 10, 3 );
		add_filter( 'wpbdp_form_field_html_value', array( $fields, 'field_html_value' ), 10, 3 );
		add_filter( 'wpbdp_form_field_plain_value', array( $fields, 'field_plain_value' ), 10, 3 );
		add_action( 'wpbdp_form_field_pre_render', array( $fields, 'field_attributes' ), 10, 3 );
		add_filter( 'wpbdp_form_field_select_option', array( $fields, 'field_option' ), 10, 2 );

		// Field settings (admin side)
		add_action( 'wpbdp_form_field_settings', array( $fields, 'field_settings' ), 10, 2 );
		add_action( 'wpbdp_form_field_settings_process', array( $fields, 'field_settings_process' ), 10, 2 );
		add_action( 'wpbdp_form_field_before_delete', array( $fields, 'before_field_delete' ), 10, 1 );
	}

	public function _init() {
		$rewrite = array( 'slug' => wpbdp_get_option( 'regions-slug', self::TAXONOMY ) );
		if ( apply_filters( 'wpbdp_hierarchical_links', false ) ) {
			$rewrite['hierarchical'] = true;
		}

		/**
		 * @since 5.3
		 */
		$rewrite = apply_filters( 'wpbdp_regions_rewrite', $rewrite );

		register_taxonomy(
			self::TAXONOMY,
			WPBDP_POST_TYPE,
			array(
				'label'              => _x( 'Directory Regions', 'regions-module', 'wpbdp-regions' ),
				'labels'             => array(
					'name'              => _x( 'Directory Regions', 'regions-module', 'wpbdp-regions' ),
					'singular_name'     => _x( 'Region', 'regions-module', 'wpbdp-regions' ),
					'search_items'      => _x( 'Search Regions', 'regions-module', 'wpbdp-regions' ),
					'popular_items'     => _x( 'Popular Regions', 'regions-module', 'wpbdp-regions' ),
					'all_items'         => _x( 'All Regions', 'regions-module', 'wpbdp-regions' ),
					'parent_item'       => _x( 'Parent Region', 'regions-module', 'wpbdp-regions' ),
					'parent_item_colon' => _x( 'Parent Region:', 'regions-module', 'wpbdp-regions' ),
					'edit_item'         => _x( 'Edit Region', 'regions-module', 'wpbdp-regions' ),
					'update_item'       => _x( 'Update Region', 'regions-module', 'wpbdp-regions' ),
					'add_new_item'      => _x( 'Add New Region', 'regions-module', 'wpbdp-regions' ),
					'new_item_name'     => _x( 'New Region Name', 'regions-module', 'wpbdp-regions' ),
					'menu_name'         => __( 'Regions', 'wpbdp-regions' ),
				),
				'hierarchical'       => true,
				'show_in_nav_menus'  => true,
				'query_var'          => true,
				'show_in_quick_edit' => false,
				'rewrite'            => $rewrite,
			)
		);

		$this->installer->upgrade_check();

		if ( get_option( 'wpbdp-clean-regions-cache' ) ) {
			$this->clean_regions_cache();
		}

		if ( get_option( 'wpbdp-regions-create-fields' ) ) {
			$this->create_fields();
		}

		if ( get_option( 'wpbdp-regions-show-fields' ) ) {
			$this->show_fields();
		}

		if ( get_option( 'wpbdp-regions-create-default-regions' ) ) {
			$this->create_default_regions();
		}

		if ( get_option( 'wpbdp-regions-flush-rewrite-rules' ) ) {
			$this->flush_rewrite_rules();
		}

		$this->register_scripts();
	}

	/**
	 * Disable Region taxonomy UI in Quick Edit form.
	 *
	 * If you set show_ui to false for a taxonomy duing load-edit.php action,
	 * the quick edit form won't include edit UI for that taxonomy.
	 */
	public function disable_taxonomy_ui() {
		global $wp_taxonomies;
		$wp_taxonomies[ self::TAXONOMY ]->show_ui = false;
	}

	public function register_settings( $settings ) {
		$url = add_query_arg(
			array(
				'taxonomy'  => self::TAXONOMY,
				'post_type' => WPBDP_POST_TYPE,
			),
			admin_url( 'edit-tags.php' )
		);

		/* translators: %1$s: start link HTML, %2$s: end link HTML */
		$help_text  = __( 'Go to the %1$sRegions hierarchy%2$s.', 'wpbdp-regions' );
		$help_text  = sprintf( $help_text, '<a href="' . esc_url( $url ) . '">', '</a>' );

        wpbdp_register_settings_group( 'regions', _x( 'Regions', 'admin settings', 'wpbdp-regions' ), 'modules', array( 'desc' => $help_text ) );

        wpbdp_register_settings_group( 'regions/general', __( 'General Settings', 'wpbdp-regions' ), 'regions' );
        wpbdp_register_setting(
            array(
                'id'        => 'regions-slug',
                'name'      => _x( 'Regions Slug', 'admin settings', 'wpbdp-regions' ),
                'type'      => 'text',
                'default'   => 'wpbdm-region',
				'group'     => 'permalink_settings',
				'taxonomy'  => wpbdp_regions_taxonomy(),
				'validator' => 'taxonomy_slug',
			)
		);

		$legacy_urls = wpbdp_get_option( 'regions-legacy-urls' );
		wpbdp_register_setting(
			array(
				'id'        => 'regions-legacy-urls',
				'name'      => __( 'Legacy Regions URLs', 'wpbdp-regions' ),
				'desc'      => __( 'Use links that include the directory page slug.', 'wpbdp-regions' ),
				'tooltip'   => __( 'Warning: This will change the URLs for any existing regions. Once off, this cannot be turned back on.', 'wpbdp-regions' ),
				'type'      => $legacy_urls ? 'checkbox' : 'hidden',
				'class'     => $legacy_urls ? '' : 'hidden',
				'default'   => false, // This is turned on by default in migrate_to_4().
				'group'     => 'permalink_settings',
				'on_update' => array( __CLASS__, 'flush_rewrite_rules' ),
			)
		);

		wpbdp_register_setting(
			array(
				'id'      => 'regions-hide-selector',
				'name'    => _x( 'Hide Region selector?', 'admin settings', 'wpbdp-regions' ),
				'type'    => 'checkbox',
				'default' => false,
				'desc'    => _x( 'The region selector is the small bar displayed above listings that allows users to filter their listings based on location.  It is enabled by default.', 'admin settings', 'wpbdp-regions' ),
				'group'   => 'regions/general',
			)
		);
		wpbdp_register_setting(
			array(
				'id'      => 'regions-selector-open',
				'name'    => _x( 'Show region selector open by default?', 'admin settings', 'wpbdp-regions' ),
				'type'    => 'checkbox',
				'default' => false,
				'group'   => 'regions/general',
			)
		);

		wpbdp_register_setting(
			array(
				'id'      => 'regions-show-counts',
				'name'    => _x( 'Show post counts?', 'admin settings', 'wpbdp-regions' ),
				'type'    => 'checkbox',
				'default' => true,
				'group'   => 'regions/general',
			)
		);
		wpbdp_register_setting(
			array(
				'id'      => 'regions-main-box-integration',
				'name'    => _x( 'Add Regions location to quick search box?', 'admin settings', 'wpbdp-regions' ),
				'type'    => 'checkbox',
				'default' => false,
				'group'   => 'regions/general',
			)
		);

		wpbdp_register_settings_group(
			'regions/sidelist',
			_x( 'Regions Sidelist', 'admin settings', 'wpbdp-regions' ),
			'regions',
			array(
				'desc' => _x( 'The Sidelist is a list of selected Regions shown in the main Business Directory pages. The Regions to show can be configured in the Regions section.', 'region settings', 'wpbdp-regions' ),
			)
		);

        wpbdp_register_setting(
            array(
                'id'      => 'regions-show-sidelist',
                'name'    => __( 'Show the list of selected Regions in the main directory pages.', 'wpbdp-regions' ),
                'type'    => 'checkbox',
                'default' => false,
                'group'   => 'regions/sidelist',
            )
        );

        if ( ! get_option( 'wpbdp-regions-create-fields', true ) ) {
            $options    = array();
            $fields_api = wpbdp_regions_fields_api();
            $fields     = $fields_api->get_fields();

            foreach ( $fields as $level => $field_id ) {
                $field = $fields_api->get_field_by_level( $level );

                if ( ! $field ) {
                    continue;
                }

                $options[ $level ] = $field->get_label();
            }

            wpbdp_register_setting(
                array(
                    'id'           => 'regions-sidelist-min-level',
                    'name'         => _x( 'Sidelist should start display at:', 'admin settings', 'wpbdp-regions' ),
                    'type'         => 'select',
                    'options'      => $options,
                    'default'      => $fields_api->get_min_visible_level(),
                    'desc'         => _x( 'This setting will change where the Region sidelist will start to display items in the region hierarchy. For example, use this to start displaying states instead of countries on the list.', 'admin settings', 'wpbdp-regions' ),
                    'group'        => 'regions/sidelist',
                    'requirements' => array( 'regions-show-sidelist' ),
                )
            );
        }

        wpbdp_register_setting(
            array(
                'id'           => 'regions-sidelist-show-clear',
                'name'         => _x( 'Show "Clear Filter" option?', 'admin settings', 'wpbdp-regions' ),
                'type'         => 'checkbox',
                'default'      => false,
                'group'        => 'regions/sidelist',
                'requirements' => array( 'regions-show-sidelist' ),
            )
        );

        if ( function_exists( 'get_ancestors' ) ) {
            wpbdp_register_setting(
                array(
                    'id'           => 'regions-sidelist-expand-current',
                    'name'         => _x( 'Keep sidelist expanded on current region?', 'admin settings', 'wpbdp-regions' ),
                    'type'         => 'checkbox',
                    'default'      => false,
                    'group'        => 'regions/sidelist',
                    'requirements' => array( 'regions-show-sidelist' ),
                )
            );
        }

        wpbdp_register_setting(
            array(
                'id'           => 'regions-sidelist-autoexpand',
                'name'         => _x( 'Automatically expand sidelist on page load?', 'admin settings', 'wpbdp-regions' ),
                'type'         => 'checkbox',
                'default'      => false,
                'group'        => 'regions/sidelist',
                'requirements' => array( 'regions-show-sidelist' ),
            )
        );

        wpbdp_register_settings_group( 'regions/default-regions', _x( 'Actions', 'region settings', 'wpbdp-regions' ), 'regions' );
        wpbdp_register_setting(
            array(
                'id'       => 'regions-create-default-regions',
                'label'    => _x( 'Create Default Regions', 'admin settings', 'wpbdp-regions' ),
				'tooltip'  => __( 'This will attempt to create the default Regions (avoiding duplicates). Clicking the button does not remove other regions you may have created, but will restore the default regions that may have been deleted.', 'wpbdp-regions' ),
                'type'     => 'callback',
                'callback' => array( $this, '_reset_button' ),
                'group'    => 'regions/default-regions',
            )
        );

        wpbdp_register_setting(
            array(
                'id'       => 'regions-restore-fields',
                'label'    => _x( 'Restore Region Form Fields', 'admin settings', 'wpbdp-regions' ),
				'tooltip'  => __( 'Check for missing fields and restore them. Clicking the button does not remove any of the existing fields.', 'wpbdp-regions' ),
                'type'     => 'callback',
                'callback' => array( $this, '_reset_button' ),
                'group'    => 'regions/default-regions',
            )
        );

        wpbdp_register_setting(
            array(
                'id'       => 'regions-restore-defaults',
                'label'    => _x( 'Restore to Default Settings', 'admin settings', 'wpbdp-regions' ),
				'tooltip'  => __( 'All regions, fields and settings will be removed and replaced. This action cannot be undone.', 'wpbdp-regions' ),
                'type'     => 'callback',
                'callback' => array( $this, '_reset_button' ),
                'group'    => 'regions/default-regions',
            )
        );
    }

    public function _reset_button( $setting ) {
        $link  = '';
        $label = $setting['label'];

        switch ( $setting['id'] ) {
            case 'regions-create-default-regions':
                $link = wp_nonce_url( add_query_arg( 'wpbdp-regions-create-default-regions', 1 ), 'wpbdp-regions-create-default-regions' );
                break;
            case 'regions-restore-fields':
                $link = wp_nonce_url( add_query_arg( 'wpbdp-regions-create-fields', 1 ), 'wpbdp-regions-create-fields' );
                break;
            case 'regions-restore-defaults':
                $link = wp_nonce_url( add_query_arg( 'wpbdp-regions-factory-reset', 1 ), 'wpbdp-regions-factory-reset' );
                break;
        }

        if ( $link ) {
			$link = remove_query_arg( 'subtab', $link );
			echo '<a href="' . esc_url( $link ) . '" class="button">' . esc_html( $label ) . '</a> ';
			echo wpbdp()->admin->settings_admin->setting_tooltip( $setting['tooltip'] );
        }
    }


    private function register_scripts() {
        $base = WPBDP_REGIONS_MODULE_URL . '/resources';

        wp_register_style(
            'wpbdp-regions-style',
            "$base/css/style.css",
            array(),
            $this->version
        );

        wp_register_script(
            'wpbdp-regions-admin',
            "$base/js/admin.js",
            array( 'jquery-color', 'jquery-form', 'jquery-ui-tabs', 'jquery-ui-autocomplete' ),
            $this->version,
            true
        );

        wp_register_script(
            'wpbdp-regions-frontend',
            "$base/js/frontend.js",
            array( 'jquery' ),
            $this->version,
            true
        );
    }

    public function enqueue_scripts() {
        wp_enqueue_style( 'wpbdp-regions-style' );
        wp_enqueue_script( 'wpbdp-regions-frontend' );

        $regions_api = wpbdp_regions_api();

        wp_localize_script(
            'wpbdp-regions-frontend',
            "ignore = 'me'; jQuery.RegionsFrontend",
            array(
                'ajaxurl'       => wpbdp_ajaxurl(),
                'UILoadingText' => _x( 'Loading...', 'regions-module', 'wpbdp-regions' ),
                'currentRegion' => intval( $regions_api->get_active_region() ),
            )
        );
    }

    private function flush_rewrite_rules() {
        flush_rewrite_rules();
        update_option( 'wpbdp-regions-flush-rewrite-rules', false );
    }

    public function create_fields() {
        global $wpdb;

        $regions      = wpbdp_regions_api();
        $regionfields = wpbdp_regions_fields_api();

        $errors = array();
        $fields = array();

        $oldfields = $regionfields->get_fields();

        $labels = array(
            1 => 'Continent',
            2 => 'Country',
            3 => 'State',
            4 => 'City',
        );
        $levels = $regions->get_max_level();

        for ( $level = 1; $level <= $levels; $level++ ) {
            $field = isset( $oldfields[ $level ] ) ? wpbdp_get_form_field( $oldfields[ $level ] ) : null;

            if ( ! $field ) {
                $field = $regionfields->get_field_by_level( $level );
            }

            // field already exists
            if ( $field && $field->get_association() == 'region' ) {
                $fields[ $level ] = $field->get_id();
                continue;
            }

            $visible = $level > 1 ? 1 : 0;

			$field = new WPBDP_Form_Field(
				array(
					'label'         => wpbdp_getv( $labels, $level, "Regions Level $level" ),
					'association'   => 'region',
					'field_type'    => 'select',
					'validators'    => '',
					'display_flags' => $visible ? array( 'excerpt', 'listing', 'search', 'region-selector', 'regions-in-form' ) : array(),
					'field_data'    => array(
						'level' => $level,
					),
				)
			);

			$res = $field->save();
			if ( ! is_wp_error( $res ) ) {
				$fields[ $level ] = $field->get_id();
			} else {
				$msg  = __( 'There were one or more errors trying to create the Region Form Fields:', 'wpbdp-regions' );
				$msg .= '<br/><strong>' . implode( '<br/>', $res->get_error_messages() ) . '</strong>';
				update_option( 'wpbdp-regions-create-fields-error', $msg );

				return;
			}
		}

		delete_option( 'wpbdp-regions-create-fields-error' );
		update_option( 'wpbdp-regions-create-fields', false );
		delete_option( 'wpbdp-regions-ignore-warning' );

		$regionfields->update_fields( $fields );
	}

	private function show_fields() {
		wpbdp_regions_fields_api()->show_fields();
		delete_option( 'wpbdp-regions-show-fields' );
	}

	private function clean_regions_cache() {
		wpbdp_regions_api()->clean_regions_cache();
		clean_term_cache( array(), self::TAXONOMY );
		update_option( 'wpbdp-clean-regions-cache', false );
	}

	private function _create_default_regions( $name, $children, $parent = 0 ) {
		$regions  = wpbdp_regions_api();
		$taxonomy = wpbdp_regions_taxonomy();

		$term_id = $regions->exists( $name, $parent );
		if ( $term_id ) {
			$term = is_array( $term_id ) ? $term_id : array( 'term_id' => $term_id );
		} else {
			$term = wp_insert_term( $name, $taxonomy, array( 'parent' => $parent ) );
		}

		if ( is_wp_error( $term ) ) {
			$code = $term->get_error_code();
			if ( $code === 'term_exists' ) {
				return;
			}

			$errors   = get_option( 'wpbdp-regions-create-default-regions-error', array() );
			$errors[] = $term->get_error_message();
			update_option( 'wpbdp-regions-create-default-regions-error', $errors, 'no' );

			return;
		}

		if ( ! is_array( $children ) ) {
			return;
		}

		foreach ( $children as $_name => $_children ) {
			$this->_create_default_regions( $_name, $_children, $term['term_id'] );
		}
	}

	/**
	 *
	 * @return [type] [description]
	 */
	public function create_default_regions() {
		// default continents and countries
		$continents = array(
			'Africa'              => array( 'Algeria', 'Angola', 'Benin', 'Botswana', 'Burkina Faso', 'Burundi', 'Cameroon', 'Cape Verde', 'Central African Republic', 'Chad', 'Comoros', 'Côte d\'Ivoire', 'Djibouti', 'Egypt', 'Equatorial Guinea', 'Eritrea', 'Ethiopia', 'Gabon', 'Gambia', 'Ghana', 'Guinea', 'Guinea-Bissau', 'Kenya', 'Lesotho', 'Liberia', 'Libya', 'Madagascar', 'Malawi', 'Mali', 'Mauritania', 'Mauritius', 'Morocco', 'Mozambique', 'Namibia', 'Niger', 'Nigeria', 'Republic of the Congo', 'Rwanda', 'Sao Tome and Principe', 'Senegal', 'Seychelles', 'Sierra Leone', 'Somalia', 'South Africa', 'Sudan', 'Swaziland', 'Tanzania', 'Togo', 'Tunisia', 'Uganda', 'Western Sahara', 'Zambia', 'Zimbabwe' ),
			'Asia'                => array( 'Afghanistan', 'Armenia', 'Azerbaijan', 'Bahrain', 'Bangladesh', 'Bhutan', 'Brunei', 'Burma (Myanmar)', 'Cambodia', 'China', 'Georgia', 'Hong Kong', 'India', 'Indonesia', 'Iran', 'Iraq', 'Israel', 'Japan', 'Jordan', 'Kazakhstan', 'Korea, North', 'Korea, South', 'Kuwait', 'Kyrgyzstan', 'Laos', 'Lebanon', 'Malaysia', 'Maldives', 'Mongolia', 'Myanmar', 'Nepal', 'Oman', 'Pakistan', 'Philippines', 'Qatar', 'Russia', 'Saudi Arabia', 'Singapore', 'Sri Lanka', 'Syria', 'Taiwan', 'Tajikistan', 'Thailand', 'Turkey', 'Turkmenistan', 'United Arab Emirates', 'Uzbekistan', 'Vietnam', 'Yemen' ),
			'Australia & Oceania' => array( 'Australia', 'Fiji', 'Kiribati', 'Marshall Islands', 'Micronesia', 'Nauru', 'New Zealand', 'Palau', 'Papua New Guinea', 'Samoa', 'Solomon Islands', 'Tonga', 'Tuvalu', 'Vanuatu' ),
			'Europe'              => array( 'Albania', 'Andorra', 'Austria', 'Belarus', 'Belgium', 'Bosnia and Herzegovina', 'Bulgaria', 'Croatia', 'Cyprus', 'Czech Republic', 'Denmark', 'Estonia', 'Finland', 'France', 'Germany', 'Greece', 'Hungary', 'Iceland', 'Ireland', 'Italy', 'Latvia', 'Liechtenstein', 'Lithuania', 'Luxembourg', 'Macedonia', 'Malta', 'Moldova', 'Monaco', 'Montenegro', 'Netherlands', 'Norway', 'Poland', 'Portugal', 'Romania', 'Russia', 'San Marino', 'Serbia', 'Slovakia (Slovak Republic)', 'Slovenia', 'Spain', 'Sweden', 'Switzerland', 'Turkey', 'Ukraine', 'United Kingdom', 'Vatican City' ),
			'North America'       => array( 'Antigua and Barbuda', 'The Bahamas', 'Barbados', 'Belize', 'Canada', 'Costa Rica', 'Cuba', 'Dominica', 'Dominican Republic', 'El Salvador', 'Greenland (Kalaallit Nunaat)', 'Grenada', 'Guatemala', 'Haiti', 'Honduras', 'Jamaica', 'Mexico', 'Nicaragua', 'Panama', 'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Vincent and the Grenadines', 'Trinidad and Tobago', 'USA' ),
			'South America'       => array( 'Argentina', 'Bolivia', 'Brazil', 'Chile', 'Colombia', 'Ecuador', 'French Guiana', 'Guyana', 'Paraguay', 'Peru', 'Suriname', 'Uruguay', 'Venezuela' ),
		);

		$states = array( 'Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California', 'Colorado', 'Connecticut', 'Delaware', 'District of Columbia (DC)', 'Florida', 'Georgia', 'Hawaii', 'Idaho', 'Illinois', 'Indiana', 'Iowa', 'Kansas', 'Kentucky', 'Louisiana', 'Maine', 'Maryland', 'Massachusetts', 'Michigan', 'Minnesota', 'Mississippi', 'Missouri', 'Montana', 'Nebraska', 'Nevada', 'New Hampshire', 'New Jersey', 'New Mexico', 'New York', 'North Carolina', 'North Dakota', 'Ohio', 'Oklahoma', 'Oregon', 'Pennsylvania', 'Rhode Island', 'South Carolina', 'South Dakota', 'Tennessee', 'Texas', 'Utah', 'Vermont', 'Virginia', 'Washington', 'West Virginia', 'Wisconsin', 'Wyoming' );

		update_option( 'wpbdp-regions-create-default-regions-error', false );

		foreach ( $continents as $continent => $countries ) {
			$countries = array_combine( $countries, $countries );

			if ( isset( $countries['USA'] ) ) {
				$countries['USA'] = array_combine( $states, $states );
			}

			$this->_create_default_regions( $continent, $countries );
		}

		$errors = get_option( 'wpbdp-regions-create-default-regions-error', array() );
		if ( empty( $errors ) ) {
			update_option( 'wpbdp-regions-create-default-regions', false );
			delete_option( 'wpbdp-regions-create-default-regions-error' );
		}

		// After the regions have been restored, get_terms return
		// top level regions only. We need to wait until the next
		// request to be able to calculate how many regions levels
		// we have and how many fields we need to create.
		update_option( 'wpbdp-clean-regions-cache', true );
		update_option( 'wpbdp-regions-create-fields', true );
	}

	public function factory_reset() {
		// 1. delete all regions
		$terms = wpbdp_regions_api()->find(
			array(
				'get'                => 'all',
				'hide_empty'         => false,
				'wpbdp-regions-skip' => true,
			)
		);

		foreach ( $terms as $term ) {
			wp_delete_term( $term->term_id, self::TAXONOMY );
		}

		// 2. delete all fields
		wpbdp_regions_fields_api()->delete_fields();

		// 3. remove all settings
		foreach ( $this->options as $option ) {
			if ( $option !== 'wpbdp-regions-db-version' ) {
				delete_option( $option );
			}
		}

		// 4. restore everything
		$this->clean_regions_cache();
		$this->create_default_regions();
		$this->flush_rewrite_rules();
	}

	/* WP Query integration */
	public function pre_get_posts( &$query ) {
		$api    = wpbdp_regions_api();
		$region = $api->get_active_region();
		$tax    = wpbdp_regions_taxonomy();

		if ( ! $region || $query->is_admin ) {
			return;
		}

		$regions_slug = rawurlencode( wpbdp_get_option( 'regions-slug' ) );

		if ( ! wpbdp_get_option( 'disable-cpt' ) ) {
			if ( WPBDP_POST_TYPE != $query->get( 'post_type' ) || $query->get( 'post__in' ) || $query->get( 'region' ) || $query->get( '_' . $regions_slug ) || $query->get( $tax ) ) {
				return;
			}

			if ( empty( $query->wpbdp_our_query ) ) {
				return;
			}
		}

		$tax_query = $query->get( 'tax_query' );
		if ( $tax_query ) {
			foreach ( $tax_query as $q ) {
				if ( isset( $q['taxonomy'] ) && $tax == $q['taxonomy'] ) {
					return;
				}
			}
		}

		$tax_query   = array_filter( (array) $query->get( 'tax_query' ) );
		$tax_query[] = array(
			'taxonomy' => $tax,
			'field'    => 'id',
			'terms'    => $region,
		);
		$query->set( 'tax_query', $tax_query );
	}

	public function post_updated( $post_id, $post_after, $post_before ) {
		if ( $post_after->post_type != WPBDP_POST_TYPE ) {
			return;
		}

		if ( $post_after->post_status == $post_before->post_status ) {
			return;
		}

		wpbdp_regions_api()->clean_regions_count_cache();
	}

	public function post_trashed( $post_id ) {
		$post = get_post( $post_id );

		if ( $post->post_type != WPBDP_POST_TYPE ) {
			return;
		}

		$args    = array(
			'orderby' => 'id',
			'order'   => 'DESC',
		);
		$regions = wp_get_object_terms( $post_id, self::TAXONOMY, $args );

		if ( ! empty( $regions ) ) {
			return;
		}

		wpbdp_regions_api()->clean_regions_count_cache();
	}

	/* Taxonomy API integration */

	public function term_link( $link, $term, $taxonomy ) {
		if ( $taxonomy != self::TAXONOMY ) {
			return $link;
		}

		$api = wpbdp_regions_api();
		return $api->region_listings_link( $term, $link );
	}

	private function locate_template( $template ) {
		$template = $template ? (array) $template : array();

		$path = wpbdp_locate_template( $template );

		if ( $path ) {
			return $path;
		}

		foreach ( $template as $t ) {
			$path = WPBDP_REGIONS_MODULE_DIR . '/templates/' . $t . '.tpl.php';
			if ( file_exists( $path ) ) {
				return $path;
			}
		}
	}

	public function taxonomy_template( $template ) {
		if ( get_query_var( self::TAXONOMY ) && taxonomy_exists( self::TAXONOMY ) ) {
			return $this->locate_template( array( 'businessdirectory-region' ) );
		}

		return $template;
	}

	public function set_object_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		if ( $taxonomy == wpbdp_regions_taxonomy() ) {
			wpbdp_regions_api()->clean_regions_count_cache();
		}
	}

	public function set_term_attributes( $term_id ) {
		$api    = wpbdp_regions_api();
		$region = $api->find_by_id( $term_id );
		$enable = ! $region->parent || $api->is_enabled( $region->parent );

		$api->set_enabled( $term_id, $enable );
	}

	/**
	 * @param array      $terms
	 * @param array|null $taxonomies
	 * @param array      $args
	 */
	public function get_terms( $terms, $taxonomies, $args ) {
		$category = WPBDP_CATEGORY_TAX;

		if ( empty( $terms ) || empty( $taxonomies ) || ! in_array( $category, $taxonomies, true ) ) {
			return $terms;
		}

		$regions = wpbdp_regions_api();
		$region  = $regions->get_active_region();
		$region  = is_null( $region ) ? null : $regions->find_by_id( $region );

		if ( is_wp_error( $region ) || is_null( $region ) ) {
			return $terms;
		}

		$hide_empty = wpbdp_getv( $args, 'hide_empty', 0 );
		$count      = $this->get_categories_count( $region );

		$_terms = array();
		foreach ( $terms as $i => $term ) {
			if ( ! isset( $term->taxonomy ) ) {
				$_terms[] = $term;
				continue;
			}

			if ( $term->taxonomy == $category && isset( $count[ $term->term_id ] ) ) {
				$term->count = $count[ $term->term_id ]->count;
			} elseif ( $term->taxonomy == $category ) {
				$term->count = 0;
			}

			if ( ! $hide_empty || $term->count > 0 ) {
				$_terms[] = $term;
			}
		}

		return $_terms;
	}

	private function get_categories_count( $region ) {
		global $wpdb;

		$count = (array) get_option( 'wpbdp-category-regions-count', array() );
		if ( isset( $count[ $region->term_id ] ) ) {
			return $count[ $region->term_id ];
		}

		// SELECT ctax.term_id, c.term_taxonomy_id, COUNT(p.ID) count FROM wp_posts p INNER JOIN wp_term_relationships r ON ( p.ID = r.object_id AND r.term_taxonomy_id = 221 ) INNER JOIN wp_term_relationships c ON ( p.ID = c.object_id ) INNER JOIN wp_term_taxonomy ctax ON ( c.term_taxonomy_id = ctax.term_taxonomy_id AND taxonomy = 'wpbdm-category' ) WHERE post_type='wpbdp_listing' GROUP BY c.term_taxonomy_id
		// SELECT ctax.term_id, cr.term_taxonomy_id, COUNT(p.ID) count FROM wp_posts p INNER JOIN wp_term_relationships rr ON ( p.ID = rr.object_id AND rr.term_taxonomy_id = 221 ) INNER JOIN wp_term_relationships cr ON ( p.ID = cr.object_id ) INNER JOIN wp_term_taxonomy ctax ON ( cr.term_taxonomy_id = ctax.term_taxonomy_id AND taxonomy = 'wpbdm-region' ) WHERE post_type='wpbdp_listing' GROUP BY cr.term_taxonomy_id
		$query = "SELECT ctax.term_id, cr.term_taxonomy_id, COUNT(p.ID) count FROM {$wpdb->posts} p ";
		// join with table of posts associated to the given Region
		$query .= "INNER JOIN {$wpdb->term_relationships} rr ON ( p.ID = rr.object_id AND rr.term_taxonomy_id = %d ) ";
		// then join to associate remaining posts with their category
		$query .= "INNER JOIN {$wpdb->term_relationships} cr ON ( p.ID = cr.object_id ) ";
		$query .= "INNER JOIN {$wpdb->term_taxonomy} ctax ON ( cr.term_taxonomy_id = ctax.term_taxonomy_id AND taxonomy = %s ) ";
		// whe only want Listings. group by category and count.
		$query .= "WHERE post_type=%s AND post_status = 'publish' GROUP BY cr.term_taxonomy_id";

		$query = $wpdb->prepare( $query, $region->term_taxonomy_id, WPBDP_CATEGORY_TAX, WPBDP_POST_TYPE );

		$count[ $region->term_id ] = $wpdb->get_results( $query, OBJECT_K );
		update_option( 'wpbdp-category-regions-count', $count );

		return $count[ $region->term_id ];
	}

	public function _padded_count( $count, $term ) {
		$regions = wpbdp_regions_api();
		$region  = $regions->get_active_region();
		$region  = is_null( $region ) ? null : $regions->find_by_id( $region );

		if ( is_wp_error( $region ) || is_null( $region ) ) {
			return $count;
		}

		global $wpdb;

		$region_tree_ids = array_merge( array( $region->term_id ), get_term_children( $region->term_id, wpbdp_regions_taxonomy() ) );

		if ( $region_tree_ids ) {
			$region_tt_ids = $wpdb->get_col( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id IN (" . implode( ',', $region_tree_ids ) . ') AND taxonomy = %s', wpbdp_regions_taxonomy() ) );

			$category_tree_ids = array_merge( array( $term->term_id ), get_term_children( $term->term_id, WPBDP_CATEGORY_TAX ) );

			if ( $category_tree_ids ) {
				$category_tt_ids = $wpdb->get_col( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id IN (" . implode( ',', $category_tree_ids ) . ') AND taxonomy = %s', WPBDP_CATEGORY_TAX ) );

				if ( $category_tt_ids ) {
					// Query using EXISTS: SELECT r.object_id FROM wp_term_relationships r INNER JOIN wp_posts p ON p.ID = r.object_id WHERE p.post_type = 'wpbdp_listing' AND p.post_status = 'publish' AND r.term_taxonomy_id IN (276, 277, 279) AND EXISTS (SELECT 1 FROM wp_term_relationships WHERE term_taxonomy_id IN (21) AND object_id = r.object_id) GROUP BY r.object_id
					// Query using INNER JOIN: SELECT tr.object_id FROM wp_term_relationships tr INNER JOIN wp_term_relationships tr2 ON tr.object_id = tr2.object_id WHERE tr.term_taxonomy_id IN (21) AND tr2.term_taxonomy_id IN (276, 277, 279) GROUP BY tr.object_id
					$query = $wpdb->prepare(
						"SELECT COUNT(DISTINCT tr.object_id) FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_relationships} tr2 ON tr.object_id = tr2.object_id INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE p.post_status = %s AND p.post_type = %s AND tr.term_taxonomy_id IN (" . implode( ',', $region_tt_ids ) . ') AND tr2.term_taxonomy_id IN (' . implode( ',', $category_tt_ids ) . ')',
						'publish',
						WPBDP_POST_TYPE
					);
					return intval( $wpdb->get_var( $query ) );
				}
			}
		}

		return $count;
	}

	/* Business Directory Listings API integration */

	public function update_listing( $listing_id, $listingfields = null ) {
		$regions      = wpbdp_regions_api();
		$regionfields = wpbdp_regions_fields_api();
		$max          = $regions->get_max_level();

		$fields = array();
		$values = array();

		if ( is_object( $listing_id ) ) {
			$listing_id = $listing_id->get_id();
		}

		for ( $level = $max; $level > 0; $level-- ) {
			$fields[ $level ] = $regionfields->get_field_by_level( $level );

			if ( is_object( $fields[ $level ] ) && isset( $listingfields[ $fields[ $level ]->get_id() ] ) ) {
				$values[ $level ] = $listingfields[ $fields[ $level ]->get_id() ];
			} else {
				$values[ $level ] = null;
			}
		}

		for ( $level = $max; $level > 0; $level-- ) {
			$field = $fields[ $level ];

			if ( is_null( $field ) || is_null( $values[ $level ] ) ) {
				continue;
			}

			if ( is_array( $values[ $level ] ) ) {
				$value = reset( $values[ $level ] );
			} else {
				$value = $values[ $level ];
			}

			// support CSV import by allowing Region names as the value
			if ( ! is_numeric( $value ) ) {
				if ( $level > 1 ) {
					$parent_levels   = range( 1, $level - 1 );
					$name_of_parents = array_intersect_key( $values, array_flip( $parent_levels ) );
				} else {
					$name_of_parents = array();
				}

				$region    = $regions->find_by_name( $value, $level, $name_of_parents );
				$region_id = $region ? $region->term_id : 0;
			} else {
				$region_id = $value;
			}

			if ( $region_id <= 0 ) {
				continue;
			}

			$hierarchy = array();
			$regions->get_region_level( $region_id, $hierarchy );

			wp_set_post_terms( $listing_id, $hierarchy, wpbdp_regions_taxonomy(), false );

			break;
		}
	}

	/* Temporary Data Storage */

	public function set( $name, $value ) {
		$this->temp[ $name ] = $value;
	}

	public function get( $name, $default = null ) {
		return wpbdp_getv( $this->temp, $name, $default );
	}

	public function terms_clauses( $clauses ) {
		_deprecated_function( __METHOD__, '5.3' );
		return $clauses;
	}
}


final class WPBDP__Regions {
	public static function load( $modules ) {
		$regions = wpbdp_regions();
		$modules->load( $regions );
	}
}
add_action( 'wpbdp_load_modules', array( 'WPBDP__Regions', 'load' ) );
