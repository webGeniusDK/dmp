<?php

namespace Elementor;

Class elementorsmartvideo extends \Elementor\Widget_Base{

    public function get_name() {
        return "smartvideo";
    }

    public function get_title() {
        return esc_html__( 'SmartVideo', 'swarmify' );
    }

    public function get_icon() {
        return "smartvideo-icon";
    }

    public function get_categories() {
        return ['basic'];
    }

    public function get_keywords() {
		return [ 'video', 'player', 'embed', 'youtube', 'vimeo', 'smartvideo' ];
	}

    protected function _register_controls() {

        $this->start_controls_section(
			'section_video',
			[
				'label' => __( 'Video', 'swarmify' ),
			]
		);

		$this->add_control(
			'video_type',
			[
				'label' => __( 'Source', 'swarmify' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'media_library',
				'options' => [
					'media_library' => __( 'Media library', 'swarmify' ),
					'youtube' => __( 'YouTube', 'swarmify' ),
					'vimeo' => __( 'Vimeo', 'swarmify' ),
					'another_source' => __( 'Another source', 'swarmify' ),
				],
			]
        );

        $this->add_control(
			'media_library',
			[
				'label' => __( 'Choose File', 'swarmify' ),
				'type' => Controls_Manager::MEDIA,
				'media_type' => 'video',
				'condition' => [
					'video_type' => 'media_library',
                ],
                'dynamic' => [
					'active' => true,
				],
                'default' => [
                    'id' => '',
                    'url' => 'https://swarmify.com/wp-content/uploads/SmartVideoIntroMain.mp4'
                ]
			]
		);

		$this->add_control(
			'youtube',
			[
				'label' => __( 'Link', 'swarmify' ),
				'type' => Controls_Manager::TEXT,
                'placeholder' => __( 'YouTube URL', 'swarmify' ) . ' (YouTube)',
                'dynamic' => [
					'active' => true,
				],
				'default' => 'https://www.youtube.com/watch?v=XHOmBV4js_E',
				'label_block' => true,
				'condition' => [
					'video_type' => 'youtube',
				],
			]
		);

		$this->add_control(
			'vimeo',
			[
				'label' => __( 'Link', 'swarmify' ),
				'type' => Controls_Manager::TEXT,
                'placeholder' => __( 'Vimeo URL', 'swarmify' ) . ' (Vimeo)',
                'dynamic' => [
					'active' => true,
				],
				'default' => 'https://vimeo.com/235215203',
				'label_block' => true,
				'condition' => [
					'video_type' => 'vimeo',
				],
			]
        );
        
        $this->add_control(
			'another_source',
			[
				'label' => __( 'URL', 'swarmify' ),
				'type' => Controls_Manager::URL,
				'autocomplete' => false,
				'show_external' => false,
				'label_block' => true,
				'show_label' => false,
				'media_type' => 'video',
                'placeholder' => __( 'Enter your URL', 'swarmify' ),
                'dynamic' => [
					'active' => true,
				],
				'condition' => [
					'video_type' => 'another_source',
				],
			]
        );

        // Poster options
        $this->add_control(
			'poster_options',
			[
				'label' => __( 'Poster', 'swarmify' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
        );
        
        $this->add_control(
			'poster',
			[
				'label' => __( 'Source', 'swarmify' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'none',
				'options' => [
					'media_library' => __( 'Media library', 'swarmify' ),
					'another_source' => __( 'Another source', 'swarmify' ),
					'none' => __( 'None', 'swarmify' ),
				],
			]
        );
        
        $this->add_control(
			'poster_media_library',
			[
				'label' => __( 'Choose Poster Image', 'swarmify' ),
				'type' => Controls_Manager::MEDIA,
				'media_type' => 'image',
				'condition' => [
					'poster' => 'media_library',
                ],
                'dynamic' => [
					'active' => true,
				],
			]
        );
        
        $this->add_control(
			'poster_another_src',
			[
				'label' => __( 'Poster URL', 'swarmify' ),
				'type' => Controls_Manager::URL,
				'autocomplete' => false,
				'show_external' => false,
				'label_block' => true,
				'show_label' => false,
				'media_type' => 'image',
				'placeholder' => __( 'Enter Poster URL', 'swarmify' ),
				'condition' => [
					'poster' => 'another_source',
                ],
                'dynamic' => [
					'active' => true,
				],
			]
        );

        // height & width
        $this->add_control(
            'video_height',
            [
                'label' => __('Height', 'swarmify'),
                'type' => Controls_Manager::TEXT,
                'default' => '720',
            ]
        );

        $this->add_control(
            'video_width',
            [
                'label' => __('Width', 'swarmify'),
                'type' => Controls_Manager::TEXT,
                'default' => '1280',
            ]
        );
        
        $this->end_controls_section();

        // basic settings
        $this->start_controls_section(
            'Basic_setting',
            [
                'label' => __( 'Basic options', 'swarmify' ),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control('autoplay',[
            'label' => __('Autoplay:','swarmify'),
            'type' => Controls_Manager::SWITCHER,
            'label_on' => __( 'Yes', 'swarmify' ),
            'label_off' => __( 'No', 'swarmify' ),
            'return_value' => 'yes',
            'default' => 'no',
        ]);

        $this->add_control('muted',[
            'label' => __('Muted:','swarmify'),
            'type' => Controls_Manager::SWITCHER,
            'label_on' => __( 'Yes', 'swarmify' ),
            'label_off' => __( 'No', 'swarmify' ),
            'return_value' => 'yes',
            'default' => 'no',

        ]);
        $this->add_control('loop',[
            'label' => __('Loop:','swarmify'),
            'type' => Controls_Manager::SWITCHER,
            'label_on' => __( 'Yes', 'swarmify' ),
            'label_off' => __( 'No', 'swarmify' ),
            'return_value' => 'yes',
            'default' => 'no',
        ]);

        $this->end_controls_section();

        // Advance options
        $this->start_controls_section(
            'advance_setting',
            [
                'label' => __( 'Advanced options', 'swarmify' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control('controls',[
            'label' => __('Controls:','swarmify'),
            'type' => Controls_Manager::SWITCHER,
            'label_on' => __( 'Yes', 'swarmify' ),
            'label_off' => __( 'No', 'swarmify' ),
            'return_value' => 'yes',
            'default' => 'yes',
        ]);

        $this->add_control('playsinline',[
            'label' => __('Play video inline:','swarmify'),
            'type' => Controls_Manager::SWITCHER,
            'label_on' => __( 'Yes', 'swarmify' ),
            'label_off' => __( 'No', 'swarmify' ),
            'return_value' => 'yes',
            'default' => 'no',

        ]);
        $this->add_control('responsive',[
            'label' => __('Responsive:','swarmify'),
            'type' => Controls_Manager::SWITCHER,
            'label_on' => __( 'Yes', 'swarmify' ),
            'label_off' => __( 'No', 'swarmify' ),
            'return_value' => 'yes',
            'default' => 'yes',

        ]);
        $this->end_controls_section();
    }
    /*
     * End style section
     * */
    protected function render() {
        $settings = $this->get_settings_for_display();
        if( $settings['media_library'] ){
            $swarmify_url = $settings['media_library']['url'];
        } else if( $settings['youtube']) {
            $swarmify_url = Embed::get_embed_url($settings['youtube']);
        } else if( $settings['vimeo'] ){
            $swarmify_url = Embed::get_embed_url($settings['vimeo']);
        } else if( $settings['another_source']) {
            $swarmify_url = $settings['another_source']['url'];
        }

        if ( empty( $swarmify_url ) ) {
			return;
        }
        
        $height= $settings['video_height'];
        $width = $settings['video_width'];
        $responsive = 'yes' === $settings['responsive'] ? 'class="swarm-fluid"':'';
        $poster_url  = $settings['poster_media_library'] ? $settings['poster_media_library']['url'] : ($settings['poster_another_src'] ? $settings['poster_another_src']['url'] : null) ;
        $poster = !empty($poster_url) ? sprintf('poster="%s"', $poster_url) : '';

        $autoplay = 'yes' === $settings['autoplay']  ? 'autoplay':'';
        $muted  = 'yes' === $settings['muted'] ? 'muted':'';
        $loop = 'yes' === $settings['loop'] ? 'loop':'';
        $controls = 'yes' === $settings['controls'] ?'controls':'';
        $playsinline  = 'yes' === $settings['playsinline'] ?'playsinline':'';

        printf('<smartvideo src="%s" width="%s" height="%s" %s %s %s %s %s %s %s></smartvideo>', $swarmify_url, $width, $height, $poster, $responsive, $autoplay, $muted, $loop, $controls, $playsinline );
    }

}