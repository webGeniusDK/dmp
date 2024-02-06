<?php
/*
 *  smartvideo elementor support
 * */
namespace Elementor;
use \Elementor\Plugin as plugin;
class ElementorSwarmify
{
    const VERSION = '1.0';
    const MINIMUM_ELEMENTOR_VERSION = '2.0';
    const MINIMUM_PHP_VERSION = '5.6';

    private static $_instance = null;

    public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;

    }



    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    public function init() {
        //load_plugin_textdomain( 'kd-elementor-addons' );

        // Check if Elementor installed and activated
        if ( ! did_action( 'elementor/loaded' ) ) {
            add_action( 'admin_notices', [ $this, 'admin_notice_missing_main_plugin' ] );
            return;
        }

        // Check for required Elementor version
        if ( ! version_compare( ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '>=' ) ) {
            add_action( 'admin_notices', [ $this, 'admin_notice_minimum_elementor_version' ] );
            return;
        }
        // Check for required PHP version
        if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {
            add_action( 'admin_notices', [ $this, 'admin_notice_minimum_php_version' ] );
            return;
        }

        // Add Plugin actions
        add_action('elementor/widgets/widgets_registered', [ $this, 'init_widgets' ] );
        add_action('elementor/elements/categories_registered',[$this, 'SmartVideo']);
        add_action('elementor/editor/before_enqueue_scripts',[$this,'swarmify_elementor_assets']);
    }

    function swarmify_elementor_assets(){
        wp_enqueue_style('swarmify-elementor-css', plugins_url('/css/swarmify-elementor.css', __FILE__), array(), null );
    }

    public function SmartVideo($manager){
        $manager->add_category('Smart_video',[
            'title' => __('Smart Video','swarmify'),
            'icon' => 'fa fa-video'
        ]);
    }

    public function init_widgets(){
        include( plugin_dir_path( __FILE__ ) . 'elementorsmartvideo.php' );
        $class_name = __NAMESPACE__ . '\elementorsmartvideo';
        plugin::instance()->widgets_manager->register_widget_type( new $class_name() );
    }
    // Check for elementor required php version
    public function admin_notice_missing_main_plugin(){
            return false;
    }

    // Elementor version check
    public function admin_notice_minimum_elementor_version(){
        if ( isset( $_GET['activate'] ) ) unset( $_GET['activate'] );

        $message = sprintf(
        /* translators: 1: Plugin name 2: Elementor 3: Required Elementor version */
            esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'swarmify' ),
            '<strong>' . esc_html__( 'Smart Video Elementor', 'swarmify' ) . '</strong>',
            '<strong>' . esc_html__( 'Elementor', 'swarmify' ) . '</strong>',
            self::MINIMUM_ELEMENTOR_VERSION
        );

        printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );
    }

    // Check for elementor required php version
    public function admin_notice_minimum_php_version(){
        if ( isset( $_GET['activate'] ) ) unset( $_GET['activate'] );

        $message = sprintf(
        /* translators: 1: Plugin name 2: PHP 3: Required PHP version */
            esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'swarmify' ),
            '<strong>' . esc_html__( 'Elementor Test Extension', 'swarmify' ) . '</strong>',
            '<strong>' . esc_html__( 'PHP', 'swarmify' ) . '</strong>',
            self::MINIMUM_PHP_VERSION
        );

        printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );

    }

}
ElementorSwarmify::instance();