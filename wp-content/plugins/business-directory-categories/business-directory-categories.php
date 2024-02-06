<?php
/**
 * Plugin Name: Business Directory Plugin - Enhanced Categories Module
 * Plugin URI: http://www.businessdirectoryplugin.com
 * Version: 5.0.12
 * Author: D. Rodenbaugh
 * Description: Category goodies for Business Directory Plugin, including parent/child hierarchy navigation, images on categories and more.
 * Author URI: http://businessdirectoryplugin.com
 *
 * @package Premium-modules/Enhanced-Categories
 */

// phpcs:disable

require_once plugin_dir_path( __FILE__ ) . 'category-icons.php';

/**
 * @SuppressWarnings(PHPMD)
 */
class WPBDP_CategoriesModule {

    const VERSION             = '5.0.12';
    const REQUIRED_BD_VERSION = '5.0';

    private static $instance = null;

    private $mode = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->id                  = 'categories';
        $this->file                = __FILE__;
        $this->title               = 'Enhanced Categories Module';
        $this->required_bd_version = '5.0';
    }

    public function init() {
        add_action( 'wpbdp_register_settings', array( $this, '_register_settings' ), 10, 1 );

        add_action( 'wpbdp_before_category_page', array( $this, '_category_cats' ), 10, 1 );
        add_filter( 'wpbdp_x_render', array( $this, '_maybe_hide_listings' ), 10, 3 );
        add_action( 'pre_get_posts', array( &$this, '_remove_subcategories_from_query' ), 20 );

        add_filter( 'wpbdp_main_categories_args', array( $this, '_main_categories' ) );

        add_filter( 'wpbdp_form_field_args', array( $this, '_setup_category_field' ) );
        add_filter( 'wpbdp_render_field_inner', array( $this, '_category_field' ), 10, 4 );

        add_action( 'wp_enqueue_scripts', array( &$this, '_enqueue_scripts' ) );
        add_action( 'wp_ajax_wpbdp-categories', array( $this, '_ajax' ) );
        add_action( 'wp_ajax_nopriv_wpbdp-categories', array( $this, '_ajax' ) );

        add_action( 'wpbdp_modules_init', array( &$this, '_init_abc' ) );

        $this->category_icons = WPBDP_CategoryIconsModule::instance();
    }

    function _setup_category_field( $args ) {
        if ( 'category' == $args['association'] && wpbdp_get_option( 'categories-submit-only-in-leafs' ) ) {
            $multiple           = in_array( $args['field_type'], array( 'checkbox', 'multiselect' ) );
            $args['field_type'] = $multiple ? 'multiselect' : 'select';
        }

        return $args;
    }

    public function _category_field( $field_inner, &$field, $value, $render_context ) {
        if ( 'category' != $field->get_association() || ! wpbdp_get_option( 'categories-submit-only-in-leafs' ) || 'submit' != $render_context ) {
            return $field_inner;
        }

        if ( false !== preg_match_all( '/<option(?P<tag0>.*)value="(?P<id>\d+)">(?P<tag1>.*)<\/option>/i', $field_inner, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $m ) {
                if ( $this->is_leaf_category( $m['id'] ) ) {
                    continue;
                }

                $field_inner = preg_replace( '/<option(.*)value="' . $m['id'] . '">(.*)<\/option>/i', '<option$1 value="' . $m['id'] . '" disabled="disabled">$2</option>', $field_inner );
            }
        }

        return $field_inner;
    }


    function _init_abc() {
        if ( ! wpbdp_get_option( 'abc-filtering' ) ) {
            return;
        }

        require_once plugin_dir_path( __FILE__ ) . 'includes/class-categories-abc-filtering.php';
        $this->abc_filtering = new WPBDP_Categories_ABC_Filtering();
    }

    public function _register_settings( &$settingsapi ) {
        wpbdp_register_settings_group( 'categories_enhanced', __( 'Enhanced Categories', 'wpbdp-categories' ), 'modules' );
        wpbdp_register_setting(
            array(
				'id'      => 'abc-filtering',
				'name'    => _x( 'Enable ABC filtering?', 'settings', 'wpbdp-categories' ),
				'type'    => 'checkbox',
				'default' => false,
				'desc'    => _x( 'Displays links on top of listings for alphabetic filtering.', 'settings', 'wpbdp-categories' ),
				'group'   => 'categories_enhanced',
            )
        );

        wpbdp_register_settings_group(
            'categories_enhanced/category_mode',
            _x( 'Main Directory Behavior', 'settings', 'wpbdp-categories' ),
            'categories_enhanced',
            array( 'desc' => _x( 'Settings related to the Enhanced Categories module.', 'settings', 'wpbdp-categories' ) )
        );
        wpbdp_register_setting(
            array(
				'id'      => 'categories-mode',
				'name'    => _x( 'Operation Mode', 'settings', 'wpbdp-categories' ),
				'type'    => 'select',
				'default' => 'parent+child',
				'options' => array(
					'parent+child' => _x( 'Parent + Child categories', 'settings', 'wpbdp-categories' ),
					'parent'       => _x( 'Parent only categories', 'settings', 'wpbdp-categories' ),
				),
				'group'   => 'categories_enhanced/category_mode',
            )
        );
        wpbdp_register_setting(
            array(
				'id'      => 'categories-listings-from-subcats',
				'name'    => _x( 'Show listings from subcategories in parent categories?', 'settings', 'wpbdp-categories' ),
				'type'    => 'checkbox',
				'default' => false,
				'group'   => 'categories_enhanced/category_mode',
            )
        );
        wpbdp_register_setting(
            array(
				'id'      => 'categories-submit-only-in-leafs',
				'name'    => _x( 'Force child category selection', 'settings', 'wpbdp-categories' ),
				'type'    => 'checkbox',
				'default' => false,
				'desc'    => _x( 'Disable parent categories as \'selectable\' on the category drop-down when a child category is present.', 'settings', 'wpbdp-categories' ),
				'group'   => 'categories_enhanced/category_mode',
            )
        );

        wpbdp_register_setting(
            array(
				'id'      => 'categories-columns',
				'name'    => _x( 'Number of category columns to use', 'settings', 'wpbdp-categories' ),
				'type'    => 'select',
				'default' => '2',
				'desc'    => __( 'BD will try to honor this setting as much as possible, but custom CSS or theme code could prevent this from working.', 'wpbdp-categories' ),
				'options' => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
				),
				'group'   => 'categories_enhanced/category_mode',
            )
        );
    }

    public function _category_cats( $category ) {
        if ( ! $category ) {
            return;
        }

        // if ( $this->get_mode() != 'parent' )
        // return;
        if ( ! $this->is_leaf_category( $category ) ) {
            if ( $cats = wpbdp_list_categories(
                array(
					'parent'       => $category,
					'parent_only'  => true,
					'hide_empty'   => wpbdp_get_option( 'hide-empty-categories' ),
					'no_items_msg' => '',
                )
            ) ) {
                echo $cats;
                echo str_repeat( '<br />', 2 );
            }
        }

        $this->current_category = $category;
    }

    public function _maybe_hide_listings( $html, $template, $vars ) {
        if ( 'listings' != $template || 'category' != $vars['_parent'] || empty( $this->current_category ) ) {
            return $html;
        }

        $count1 = $this->current_category->count;
        _wpbdp_padded_count( $this->current_category );
        $count2 = $this->current_category->count;

        if ( $count2 > 0 && wpbdp_get_option( 'categories-listings-from-subcats', false ) ) {
            return $html;
        }

        if ( ! $this->is_leaf_category( $this->current_category ) && 0 == $count1 ) {
            return '';
        }

        return $html;
    }

    public function _remove_subcategories_from_query( $query ) {
        // FIXME: this doesn't work in CPT compat mode.
        if ( empty( $query->wpbdp_our_query ) || ! $query->wpbdp_is_category ) {
            return;
        }

        if ( wpbdp_get_option( 'categories-listings-from-subcats', false ) ) {
            return;
        }

        if ( $query->tax_query->queries ) {
            foreach ( $query->tax_query->queries as &$t ) {
                if ( WPBDP_CATEGORY_TAX == $t['taxonomy'] ) {
                    $t['include_children'] = false;
                }
            }

            $tax_query = $query->tax_query->queries;
        } else {
            $term = $query->get_queried_object();

            $tax_query[] = array(
                'taxonomy'         => $term->taxonomy,
                'terms'            => $term->slug,
                'include_children' => false,
            );
        }

        $query->set( 'tax_query', $tax_query );
    }

    public function _main_categories( $args ) {
        if ( $this->get_mode() != 'parent' ) {
            return $args;
        }

        return array(
			'hide_empty'  => wpbdp_get_option( 'hide-empty-categories' ),
			'parent_only' => true,
		);
    }

    private function render_selector( $depth = 0, $selected = 0, $parent = null ) {
        $ajaxurl = add_query_arg( 'action', 'wpbdp-categories', wpbdp_ajaxurl() );

        $html  = '';
        $html .= wp_dropdown_categories(
            array(
				'show_option_none' => __( '-- Select a category --', 'wpbdp-categories' ),
				'taxonomy'         => WPBDP_CATEGORY_TAX,
				'selected'         => $selected,
				'orderby'          => wpbdp_get_option( 'categories-order-by' ),
				'order'            => wpbdp_get_option( 'categories-sort' ),
				'hide_empty'       => false,
				'hierarchical'     => true,
				'depth'            => 1,
				'echo'             => false,
				'id'               => '',
				'name'             => '',
				'class'            => 'wpbdp-x-category-selector',
				'child_of'         => $parent ? ( is_object( $parent ) ? $parent->term_id : intval( $parent ) ) : 0,
            )
        );

        $html = preg_replace(
            "/\\<select(.*)name=('|\")(.*)('|\")(.*)\\>/uiUs",
            "<select data-depth=\"{$depth}\" data-url=\"{$ajaxurl}\" $1 $5 style=\"display: block;\">",
            $html
        );

        return $html;
    }

    public function _enqueue_scripts() {
        wp_enqueue_script(
            'wpbdp-categories',
            plugins_url( 'resources/categories-module.min.js', __FILE__ ),
            array( 'jquery' ),
            self::VERSION,
            true
        );
    }

    public function _ajax() {
        $category = wpbdp_getv( $_REQUEST, 'category', 0 );

        $response = array(
			'ok'   => true,
			'leaf' => false,
			'html' => '',
		);

        if ( $this->is_leaf_category( $category ) ) {
            $response['leaf'] = true;
        } else {
            $response['html'] = $this->render_selector( 0, 0, $category );
        }

        header( 'Content-Type: application/json' );
        echo json_encode( $response );
        exit;
    }

    /* API */
    public function get_mode() {
        if ( ! isset( $this->mode ) ) {
            $this->mode = wpbdp_get_option( 'categories-mode', 'parent+child' );
        }

        return $this->mode;
    }

    public function is_leaf_category( $category ) {
        return count( get_term_children( is_object( $category ) ? $category->term_id : intval( $category ), WPBDP_CATEGORY_TAX ) ) == 0;
    }

}

/**
 * @SuppressWarnings(PHPMD)
 */
final class WPBDP__Categories {
    public static function load( $modules ) {
        $instance = WPBDP_CategoriesModule::instance();
        $modules->load( $instance );
    }
}

add_action( 'wpbdp_load_modules', array( 'WPBDP__Categories', 'load' ) );
