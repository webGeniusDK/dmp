<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://swarmify.com/
 * @since      1.0.0
 *
 * @package    Swarmify
 * @subpackage Swarmify/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Swarmify
 * @subpackage Swarmify/admin
 * @author     Omar Kasem <omar.kasem207@gmail.com>
 */
class Swarmify_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Swarmify_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Swarmify_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name.'bootstrap', plugin_dir_url( __FILE__ ) . 'css/bootstrap.css', array(), $this->version, 'all' );


		wp_enqueue_style( $this->plugin_name.'fancybox', plugin_dir_url( __FILE__ ) . 'css/jquery.fancybox.min.css', array(), $this->version, 'all' );

		// Add the color picker css file       
		wp_enqueue_style( 'wp-color-picker' ); 

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/swarmify-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts($hook) {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Swarmify_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Swarmify_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */



		wp_enqueue_script( $this->plugin_name.'mask', plugin_dir_url( __FILE__ ) . 'js/jquery.inputmask.bundle.js', array( 'jquery' ), $this->version, false );

		wp_enqueue_script( $this->plugin_name.'fancybox', plugin_dir_url( __FILE__ ) . 'js/jquery.fancybox.min.js', array( 'jquery' ), $this->version, false );

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/swarmify-admin.js', array( 'jquery', 'wp-color-picker' ), $this->version, false );

		/** Only loaded on our admin pages */
		if( $hook != 'toplevel_page_') {
			wp_enqueue_script( $this->plugin_name.'-mt', plugin_dir_url( __FILE__ ) . 'js/mt.js', array(  ), $this->version, false );
		}

	}


	public function option_page(){
		add_menu_page('SmartVideo','SmartVideo','manage_options',$this->plugin_name.'.php',array($this, 'option_display'), plugins_url('/images/smartvideo_icon.png', __FILE__));
	}

	private function option_tabs(){
		return array(
			'dashboard'=>'Dashboard',
			'configuration' => 'Configuration',
			'settings'=>'Settings',
		);
	}

	public function option_display(){ ?>
		<div id="swarmify-iso-bootstrap">
			<div class="container-fluid">
			<div class="panel panel-default main_panel">
			  <div class="panel-body no-pad-bot">
				<div class="row">
					<div class="col-md-12">
						<a href="https://swarmify.com/" target="_blank"><img class="img-responsive" src="<?php echo plugin_dir_url( __FILE__ ).'images/smartvideo_logo.png' ?>" alt=""></a>
						<?php $active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'dashboard'; ?>
						<ul class="nav nav-tabs" role="tablist">
							<?php foreach($this->option_tabs() as $key => $value){ ?>
								<li class="<?php echo $active_tab == $key ? 'active' : ''; ?>" role="presentation"><a href="?page=<?php echo $this->plugin_name ?>.php&tab=<?php echo $key ?>"><?php echo _e($value,$this->plugin_name); ?></a></li>
							<?php } ?>
							<li class="swarmify_status">SmartVideo: <?php if(get_option('swarmify_status') === 'on'){ echo '<span>ENABLED</span>';}else{echo '<span class="red">DISABLED</span>';} ?></li>
						</ul>
					</div>
				</div>
			  </div>
			</div>
			<div class="panel panel-default">
			  <div class="panel-body">
				<?php foreach($this->option_tabs() as $key => $value){
					if($active_tab == $key){
						echo '<div class="row">';
						include_once('partials/'.$key.'-display.php');
						echo '</div>';
					}
				} ?>
			  </div>
			</div>
			</div>
		</div>
	<?php }


	public function plugin_register_settings(){
		register_setting( 'swarmify_config_settings', 'swarmify_cdn_key');
		
		register_setting( 'swarmify_settings', 'swarmify_status');
		register_setting( 'swarmify_settings', 'swarmify_toggle_youtube');
		register_setting( 'swarmify_settings', 'swarmify_toggle_youtube_cc');
		register_setting( 'swarmify_settings', 'swarmify_toggle_layout');
		register_setting( 'swarmify_settings', 'swarmify_toggle_bgvideo');
        register_setting( 'swarmify_settings', 'swarmify_theme_primarycolor' );
        register_setting( 'swarmify_settings', 'swarmify_theme_button' );
        register_setting( 'swarmify_settings', 'swarmify_toggle_uploadacceleration' );
        register_setting( 'swarmify_settings', 'swarmify_watermark' );
        register_setting( 'swarmify_settings', 'swarmify_ads_vasturl' );

		if(isset($_POST['swarmify_cdn_key']) && $_POST['swarmify_cdn_key'] !== ''){
			update_option( 'swarmify_status','on');
		}
	}


	public function load_widget() {
		register_widget( 'Swarmify_Public' );
	}

    public function add_video_button() {
        echo '<a href="" data-fancybox data-src="#swarmify-modal-content" class="button swarmify_add_button"><img src="'.plugin_dir_url(__FILE__).'images/smartvideo_icon.png" alt="">Add SmartVideo</a>';
    }

    public function add_video_lightbox_html(){

    	require('partials/add-video-lightbox-display.php');
    }

	public function smartvideo_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'src' => '',
			'poster'=>'',
			'height' => '',
			'width' => '',
			'responsive'=> '',
			'autoplay' => '',
			'muted'=> '',
			'loop'=> '',
			'controls' => '',
			'playsinline' => '',
		), $atts, 'smartvideo' );
		$swarmify_url = $atts['src'];
		$poster = ($atts['poster'] === '' ? '' : 'poster="'.$atts['poster'].'"');
		$height = ($atts['height'] !== '' ? $atts['height'] : '');
		$width = ($atts['width'] !== '' ? $atts['width'] : '');
    	$autoplay = ($atts['autoplay'] === 'true' ? 'autoplay' : '');
    	$muted = ($atts['muted'] === 'true' ? 'muted' : '');
    	$loop = ($atts['loop'] === 'true' ? 'loop' : '');
    	$controls = ($atts['controls'] === 'true' ? 'controls' : '');
    	$video_inline = ($atts['playsinline'] === 'true' ? 'playsinline' : '');
    	$unresponsive = ($atts['responsive'] === 'true' ? 'class="swarm-fluid"' : '' );

    	return '<smartvideo src="'.$swarmify_url.'" width="'.$width.'" height="'.$height.'" '.$unresponsive.' '.$poster.' '.$autoplay.' '.$muted.' '.$loop.' '.$controls.' '.$video_inline.'></smartvideo>';
	}
	


}
