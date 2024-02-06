<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://swarmify.com/
 * @since      1.0.0
 *
 * @package    Swarmify
 * @subpackage Swarmify/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Swarmify
 * @subpackage Swarmify/public
 * @author     Omar Kasem <omar.kasem207@gmail.com>
 */
class Swarmify_Public extends WP_Widget {

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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name = '', $version = '' ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
        parent::__construct(
            'smartvideo_widget',
            __('SmartVideo', $this->plugin_name),
            array('description' => __('SmartVideo Widget', $this->plugin_name),)
        );
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/swarmify-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/swarmify-public.js', array( 'jquery' ), $this->version, false );

	}

	public function swarmify_script(){
		$cdn_key = get_option('swarmify_cdn_key');
		$swarmify_status = get_option('swarmify_status');
        $youtube = get_option('swarmify_toggle_youtube');
        $youtube_cc = get_option('swarmify_toggle_youtube_cc');
		$layout = get_option('swarmify_toggle_layout');
		$bgoptimize = get_option('swarmify_toggle_bgvideo');
		$theme_primarycolor = get_option('swarmify_theme_primarycolor');
        $theme_button = get_option('swarmify_theme_button');
        $watermark = get_option('swarmify_watermark');
        $ads_vasturl = get_option('swarmify_ads_vasturl');
		
		if($swarmify_status == 'on' && $cdn_key !== ''){
			$status = true;
		}else{
			$status = false;
		}

        // Configure `autoreplace` object
        $autoreplaceObject = new stdClass();

		if($youtube == 'on'){
            $autoreplaceObject->youtube = true;
		}else{
            $autoreplaceObject->youtube = false;
        }
        
        if($youtube_cc == 'on') {
            $autoreplaceObject->youtubecaptions = true;
        }else{
            $autoreplaceObject->youtubecaptions = false;
        }

        if($bgoptimize == 'on'){
            $autoreplaceObject->videotag = true;
		}else{
            $autoreplaceObject->videotag = false;
		}

		if($layout == 'on'){
			$layout_status = 'iframe';
		}else{
			$layout_status = 'video';
		}

        // Configure `theme` object
        $themeObject = new stdClass();

		if($theme_primarycolor) {
            $themeObject->primaryColor = $theme_primarycolor;
		}
        
        // Limit button type to `no selection` which is hexagon, `rectangle`, or `circle`
        $button_type = null;
        if($theme_button == 'rectangle') {
            $themeObject->button = $theme_button;
        }
        if($theme_button == 'circle') {
            $themeObject->button = $theme_button;
        }

        // Configure `plugins` object
        $pluginsObject = new stdClass();

        // Configure `plugins->swarmads` object
		if( $ads_vasturl && $ads_vasturl !== '' ) {
            // Create the `swarmads` subobject
            $swarmadsObject = new stdClass();
            $swarmadsObject->adTagUrl = $ads_vasturl;

            // Store the `swarmadsObject` in the `pluginsObject`
            $pluginsObject->swarmads = $swarmadsObject;
		}

        // Configure `plugins->watermark` object
		if( $watermark && $watermark !== '' ) {
            // Create the `swarmads` subobject
            $watermarkObject = new stdClass();
            $watermarkObject->file = $watermark;
            $watermarkObject->opacity = 0.75;
            $watermarkObject->xpos = 100;
            $watermarkObject->ypos = 100;

            // Store the `watermarkObject` in the `pluginsObject`
            $pluginsObject->watermark = $watermarkObject;
		}


		if($status == true){

		$output = '
			<link rel="preconnect" href="https://assets.swarmcdn.com">
			<script data-cfasync="false">
				var swarmoptions = {
					swarmcdnkey: "'.$cdn_key.'",
					autoreplace: '.json_encode($autoreplaceObject).',
                    theme: '.json_encode($themeObject).',
                    plugins: '.json_encode($pluginsObject).',
					iframeReplacement: "'.$layout_status.'"
				};
			</script>
			<script data-cfasync="false" async src="https://assets.swarmcdn.com/cross/swarmdetect.js"></script>
		';
        echo $output;
		}

	}


    // Widgets
    public function widget($args, $instance){
    	if(empty($instance)){
    		$instance= array(
    			'title' =>'',
    			'swarmify_url'=>'',
    			'swarmify_poster'=>'',
    			'swarmify_autoplay'=>'',
    			'swarmify_muted'=>'',
    			'swarmify_loop'=>'',
    			'swarmify_controls'=>'',
    			'swarmify_video_inline'=>'',
    			'swarmify_unresponsive'=>'',
    			'swarmify_height'=>'',
    			'swarmify_height'=>'',
    			'swarmify_width'=>'',
    		);
    	}
		$cdn_key = get_option('swarmify_cdn_key');
		$swarmify_status = get_option('swarmify_status');
        $title = apply_filters('widget_title', $instance['title']);
        $output = $args['before_widget'];
        if (!empty($title)){
            $output .= $args['before_title'] . $title . $args['after_title'];
        }
        $swarmify_url = $instance['swarmify_url'];

        $swarmify_poster = $instance['swarmify_poster'];
        $swarmify_autoplay = intval($instance['swarmify_autoplay']);
        $swarmify_muted = intval($instance['swarmify_muted']);
        $swarmify_loop = intval($instance['swarmify_loop']);
        $swarmify_controls = intval($instance['swarmify_controls']);
        $swarmify_video_inline = intval($instance['swarmify_video_inline']);
        $swarmify_unresponsive = intval($instance['swarmify_unresponsive']);
        $swarmify_height = intval($instance['swarmify_height']);
        $swarmify_width = intval($instance['swarmify_width']);
        $errors = array();
        if($cdn_key === ''){
        	$errors[] = 'CDN Key field is required.';
        }
        if($swarmify_status !== 'on'){
        	$errors[] = 'SmartVideo is disabled.';
        }

        if($swarmify_url === ''){
        	$errors[] = 'The Video URL is required.';
        }

        if(empty($errors)){
        	if(!empty($swarmify_poster)){
        		$poster = 'poster="'.$swarmify_poster.'"';
        	}else{
        		$poster = '';
        	}

        	$autoplay = ($swarmify_autoplay === 1 ? 'autoplay' : '');
        	$muted = ($swarmify_muted === 1 ? 'muted' : '');
        	$loop = ($swarmify_loop === 1 ? 'loop' : '');
        	$controls = ($swarmify_controls === 1 ? 'controls' : '');
        	$video_inline = ($swarmify_video_inline === 1 ? 'playsinline' : '');
        	$unresponsive = ($swarmify_unresponsive === 1 ? 'class="swarm-fluid"' : '' );

        	$output .= '<smartvideo src="'.$swarmify_url.'" width="'.$swarmify_width.'" height="'.$swarmify_height.'" '.$unresponsive.' poster="'.$swarmify_poster.'" '.$autoplay.' '.$muted.' '.$loop.' '.$controls.' '.$video_inline.'></smartvideo>';
        }else{
        	$output .= '<ul>';
        	foreach($errors as $error){
        		$output .= '<li>'.$error.'</li>';
        	}
        	$output .= '</ul>';
        }
        $output.= $args['after_widget'];
        
        $output = str_replace('et_pb_widget', '', $output);
		echo $output;
    }

    public function form($instance){
    	$title = isset($instance['title']) ? $instance['title'] : '';
    	$page = isset($instance['page']) ? $instance['page'] : '';
    	require('partials/swarmify-widget-display.php');
	}


    public function update($new_instance, $old_instance){

    	$instance = array();
    	$instance['title'] = !empty($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '';
    	$instance['swarmify_url'] = !empty($new_instance['swarmify_url']) ? $new_instance['swarmify_url'] : '';
    	$instance['swarmify_poster'] = !empty($new_instance['swarmify_poster']) ? $new_instance['swarmify_poster'] : '';
    	$instance['swarmify_autoplay'] = !empty($new_instance['swarmify_autoplay']) ? intval($new_instance['swarmify_autoplay']) : 0;
    	$instance['swarmify_muted'] = !empty($new_instance['swarmify_muted']) ? intval($new_instance['swarmify_muted']) : 0;
    	$instance['swarmify_loop'] = !empty($new_instance['swarmify_loop']) ? intval($new_instance['swarmify_loop']) : 0;
    	$instance['swarmify_controls'] = !empty($new_instance['swarmify_controls']) ? intval($new_instance['swarmify_controls']) : 0;
    	$instance['swarmify_height'] = !empty($new_instance['swarmify_height']) ? intval($new_instance['swarmify_height']) : 720;
    	$instance['swarmify_width'] = !empty($new_instance['swarmify_width']) ? intval($new_instance['swarmify_width']) : 1280;

    	if(in_array('swarmify_controls',$old_instance) && $old_instance['swarmify_controls'] === null){
    		$instance['swarmify_controls'] = 1;
    	}
    	$instance['swarmify_video_inline'] = !empty($new_instance['swarmify_video_inline']) ? intval($new_instance['swarmify_video_inline']) : 0;
    	$instance['swarmify_unresponsive'] = !empty($new_instance['swarmify_unresponsive']) ? intval($new_instance['swarmify_unresponsive']) : 0;
    	if(in_array('swarmify_unresponsive',$old_instance) && $old_instance['swarmify_unresponsive'] === null){
    		$instance['swarmify_unresponsive'] = 1;
    	}
    	return $instance;
    }


}
