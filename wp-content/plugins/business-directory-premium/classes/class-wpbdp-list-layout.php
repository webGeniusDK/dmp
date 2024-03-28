<?php

class WPBDP_List_Layout {

	public function __construct() {
		add_action( 'wpbdp_register_settings', array( &$this, 'register_settings' ) );
	}

	public function register_settings( $settings ) {
		$images = plugins_url( '/images/', __DIR__ );
		wpbdp_register_setting(
			array(
				'id'      => 'list-layout',
				'name'    => __( 'Listings layout', 'wpbdp-pro' ),
				'type'    => 'radio',
				'default' => '',
				'options' => array(
					''        => '<span><img src="' . esc_url( $images ) . 'list.svg" class="wpbdp-img-opt" alt="single column" />' . __( 'Default', 'wpbdp-pro' ) . '</span>',
					'two-col' => '<span><img src="' . esc_url( $images ) . 'list-column.svg" class="wpbdp-img-opt" alt="2 column" />' . __( 'Two column', 'wpbdp-pro' ) . '</span>',
					'table'   => '<span><img src="' . esc_url( $images ) . 'table.png" class="wpbdp-img-opt" alt="table" />' . __( 'Table', 'wpbdp-pro' ) . '</span>',
				),
				'group'   => 'themes',
			)
		);

		$this->maybe_load_layout();
	}

	public function maybe_load_layout() {
		$layout = wpbdp_get_option( 'list-layout' );
		if ( ! $layout ) {
			return;
		}

		if ( $layout === 'table' ) {
			new WPBDP_Table_List();
		} elseif ( $layout === 'two-col' ) {
			add_filter( 'wpbd_column_count', array( &$this, 'set_column_count' ), 10, 2 );
		}
	}

	/**
	 * Set a 2-column layout.
	 */
	public function set_column_count( $count, $atts ) {
		if ( $atts['display'] === 'excerpt' ) {
			$count = 2;
		}
		return $count;
	}
}
