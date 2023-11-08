<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Field Icons
 * Add Field icon support
 *
 * @since 5.3
 */
class WPBDP_Field_Icon {

	/**
	 * Main class constructor
	 */
	public function __construct() {

		add_filter( 'wpbdp_display_field_label', array( &$this, 'maybe_show_field_icon' ), 10, 2 );
		add_filter( 'wpbdp_form_field_html_value', array( &$this, 'maybe_replace_field_value' ), 10, 3 );
		add_filter( 'wpbdp_form_field_data', array( &$this, 'default_icon_data' ), 10, 3 );
		// Add extra wrapper class
		add_filter( 'wpbdp_display_field_wrapper_classes', array( &$this, 'maybe_add_field_wrapper_class' ), 10, 2 );

		add_action( 'admin_enqueue_scripts', array( &$this, 'admin_resources' ) );

		add_action( 'wpbdp_admin_listing_field_section_visibility', array( &$this, 'icon_selector' ), 10, 2 );
	}

	/**
	 * Maybe show the field icon if supported.
	 * This renders the field icon in the frontend.
	 *
	 * @param string $label The current field label.
	 * @param object $field The field label object.
	 *
	 * @since 5.3
	 *
	 * @return string
	 */
	public function maybe_show_field_icon( $label, $field ) {
		if ( ! $field ) {
			return $label;
		}

		// This changes if replacing the field value with a url is supported.
		// It should be a link only. If its not supported, the label will be shown.
		$icon_value_only = $this->check_field_icon_value_supported( $field );
		if ( $icon_value_only ) {
			return '';
		}

		$show_icon = $field->has_display_flag( 'icon' );

		if ( ! $show_icon && ! $field->has_display_flag( 'fieldlabelicon' ) ) {
			return $label;
		}

		$icon = $this->get_field_icon( $field );
		if ( ! $icon ) {
			return $label;
		}

		$this->enqueue_front_resources();

		$icon_markup = $this->icon_markup( $icon );
		if ( $show_icon ) {
			return $icon_markup;
		}
		return $icon_markup . ' ' . $label;
	}

	/**
	 * Maybe replace the field value.
	 * This checks if the field value is a url and replaces it with an icon.
	 *
	 * @param string $value The current field value.
	 * @param int $post_id The current page or post id.
	 * @param $field The field value object.
	 *
	 * @since 5.3
	 *
	 * @return string
	 */
	public function maybe_replace_field_value( $value, $post_id, $field ) {
		if ( '' === $value || ! $field->has_display_flag( 'valueicon' ) ) {
			return $value;
		}

		$icon = $this->get_field_icon( $field );
		if ( ! $icon ) {
			return $value;
		}

		// Get the original value.
		$field_value = $field->value( $post_id );
		if ( '' === $field_value || array() === $field_value ) {
			return $value;
		}

		return $this->render_field_value( compact( 'field', 'field_value', 'value', 'icon', 'post_id' ) );
	}

	/**
	 * Enqueue front resources.
	 * Load fonts required for the front.
	 *
	 * @since 5.3
	 */
	private function enqueue_front_resources() {
		add_action( 'wpbdp_enqueue_scripts', array( $this, 'load_front_resources' ) );
	}

	/**
	 * Get the field icon.
	 * Returns the field icon selected.
	 *
	 * @param $field The field value object.
	 *
	 * @since 5.3
	 *
	 * @return bool|string
	 */
	private function get_field_icon( $field ) {
		if ( ! $field->data( 'icon' ) ) {
			return false;
		}

		$icon_parts = explode( '|', $field->data( 'icon' ) );
		$icon       = isset( $icon_parts[1] ) ? $icon_parts[1] : false;
		return $icon;
	}

	/**
	 * Check if the field icon value is supported.
	 * This checks for supported field types if they have supported clickable values that can be replaced as icons.
	 *
	 * @param object $fild The current form field.
	 *
	 * @since 5.3
	 *
	 * @return bool
	 */
	private function check_field_icon_value_supported( $field ) {
		return $field->has_display_flag( 'valueicon' ) && $this->check_field_icon_value_types( $field );
	}

	/**
	 * Check if the field icon value type is supported.
	 *
	 * @param object $fild The current form field.
	 *
	 * @since 5.3
	 *
	 * @return bool
	 */
	public function check_field_icon_value_types( $field ) {
		$allowed_types = array( 'url', 'phone_number', 'textfield', 'multiselect' );
		$field_type    = $field->get_field_type();
		$field_id      = $field_type->get_id();
		if ( ! in_array( $field_id, $allowed_types, true ) ) {
			return false;
		}

		$text_url_tags = array( 'website', 'email', 'phone', 'fax' );
		if ( 'textfield' === $field_id && ! in_array( $field->get_tag(), $text_url_tags, true ) ) {
			return false;
		}

		$multi_url_tags = array( 'category', 'tags' );
		if ( 'multiselect' === $field_id && ! in_array( $field->get_association(), $multi_url_tags, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks if the value is valid for url type icon.
	 * If the value is a url, email or phone, it can be replaced by an icon url.
	 *
	 * @param array $args
	 *
	 * @since 5.3
	 *
	 * @return string
	 */
	private function render_field_value( $args ) {
		$supported = $this->check_field_icon_value_supported( $args['field'] );
		if ( ! $supported ) {
			return $args['value'];
		}

		return $this->render_field_icon_value_markup( $args );
	}

	/**
	 * Render the field icon markup, and insert it into the current HTML.
	 *
	 * @param $args
	 *
	 * @since 5.3
	 *
	 * @return string
	 */
	private function render_field_icon_value_markup( $args ) {
		$field       = $args['field'];
		$field_value = $args['field_value'];
		$value       = $args['value'];
		$icon        = $args['icon'];
		$field_type  = $field->get_field_type();
		$field_id    = $field_type->get_id();
		$icon_markup = $this->icon_markup( $icon );
		$this->enqueue_front_resources();

		if ( $field_value === $value ) {
			// Replace the value with an icon.
			return $icon_markup;
		}

		if ( 'url' === $field_id && ! empty( $field_value[0] ) ) {
			$field_value = $field_value[0];
		}

		if ( ! is_array( $field_value ) && strpos( $value, '>' . $field_value . '<' ) ) {
			return $this->replace_link_text_with_icon( compact( 'field_value', 'icon_markup', 'value' ) );
		}

		if ( 'multiselect' === $field_id ) {
			$args['icon_markup'] = $icon_markup;
			$field_association   = $field->get_association();
			if ( 'category' === $field_association ) {
				$args['tax'] = WPBDP_CATEGORY_TAX;
				$value       = $this->render_taxonomy_icons( $args );
			} elseif ( 'tags' === $field_association ) {
				$args['tax'] = WPBDP_TAGS_TAX;
				$value       = $this->render_taxonomy_icons( $args );
			}
		}

		return $value;
	}

	/**
	 * Replace the field value in a link with an icon.
	 *
	 * @param array $args
	 *
	 * @since 5.3
	 */
	private function replace_link_text_with_icon( $args ) {
		return str_replace( '>' . $args['field_value'] . '<', '>' . $args['icon_markup'] . '<', $args['value'] );
	}

	/**
	 * Add a wrapper class to fields that have an icon as the value.
	 *
	 * @param string $extra_classes The extra css classes.
	 * @param array $atts The attributes that contain the field object.
	 *
	 * @since 5.3
	 *
	 * @return string
	 */
	public function maybe_add_field_wrapper_class( $extra_classes, $atts ) {
		$field = $atts['field'];
		if ( ! $field ) {
			return $extra_classes;
		}

		$supported = $this->check_field_icon_value_supported( $field );
		if ( $supported ) {
			$extra_classes .= ' wpbdp-field-has-icon-value';
		}
		return $extra_classes;
	}

	/**
	 * Render the icon markup used in the front.
	 * Use the filter `wpbdp_field_icon_markup` to change the icon markup.
	 *
	 * @param string $icon The icon class name.
	 *
	 * @since 5.3
	 *
	 * @return string
	 */
	private function icon_markup( $icon ) {
		return apply_filters( 'wpbdp_field_icon_markup', '<span class="wpbdp-icon ' . esc_attr( $icon ) . '"></span>', $icon );
	}

	/**
	 * Render the taxonomy value icons.
	 *
	 * @param array $args
	 *              'post_id'  Post ID.
	 *              'tax' Taxonomy name.
	 *              'icon_markup' The icon markup
	 *
	 * @link https://developer.wordpress.org/reference/functions/get_the_term_list/
	 *
	 * @since 5.3
	 *
	 * @return string
	 */
	private function render_taxonomy_icons( $args ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $args['tax'],
				'hide_empty' => false,
				'include'    => implode( ',', $args['field_value'] ),
			)
		);

		$value = $args['value'];

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return $value;
		}

		foreach ( $terms as $term ) {
			$value = str_replace( '>' . $term->name . '<', ' title="' . esc_attr( $term->name ) . '">' . $args['icon_markup'] . '<', $value );
		}

		return $value;
	}

	/**
	 * Get default field icon.
	 * This checks if the field has a default icon set.
	 * If there is no icon, it defaults to dashicons of font awesome archive.
	 *
	 * @param string $res The result of the file data check.
	 * @param string $key The field option key.
	 * @param object $field The current field.
	 *
	 * @since 5.3
	 *
	 * @return bool
	 */
	public function default_icon_data( $res, $key, $field ) {
		if ( ! $res && 'icon' === $key ) {
			if ( WPBDP_FA_Compat::is_enabled() ) {
				return 'fa|fas fa-archive';
			}
			return 'dashicons|dashicons dashicons-archive';
		}
		return $res;
	}

	/**
	 * Load admin resources
	 *
	 * @since 5.3
	 */
	public function admin_resources() {
		if ( ! WPBDP_App_Helper::is_admin_page( 'wpbdp_admin_formfields' ) ) {
			return;
		}
		$module     = WPBDP_Premium_Module::get_module_details();
		$version    = $module ? $module->version : '';
		$plugin_url = untrailingslashit( wpbdp_premium_plugin_url() );
		$min        = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script(
			'wpbdp-pro-admin-field-icon',
			$plugin_url . '/resources/admin-field-icon' . $min . '.js',
			array( 'jquery' ),
			$version,
			true
		);
		wp_localize_script(
			'wpbdp-pro-admin-field-icon',
			'WPBDPIcon',
			array(
				'search'        => __( 'Search', 'business-directory-plugin' ),
				'has_fa_plugin' => WPBDP_FA_Compat::is_enabled(),
				'icons'         => array(
					'dash' => WPBDP_Icon_Helper::list_dashicons(),
					'fa'   => WPBDP_Icon_Helper::list_fontawesome_icons(),
				),
			)
		);

		$url = $plugin_url . '/resources/bd.admin' . $min . '.css';

		wp_enqueue_style( 'wpbdp-pro', $url, array( 'dashicons' ), $version );
	}

	/**
	 * The icon selector for the field.
	 * This replaces the default selector for the field to a drop down.
	 *
	 * @param object $fild The current field beind edited.
	 * @param array $hidden_fields The list of hidden fields.
	 *
	 * @since 5.3
	 *
	 * @return string
	 */
	public function icon_selector( $field, $hidden_fields ) {
		$show_icon_field = $field->has_display_flag( 'icon' ) || $field->has_display_flag( 'fieldlabelicon' ) || $field->has_display_flag( 'valueicon' );

		include dirname( dirname( __FILE__ ) ) . '/views/fields/icon-selector.php';
	}

	/**
	 * Load front resources.
	 * This loads fonts needed on the front end.
	 *
	 * @since 5.3
	 */
	public function load_front_resources() {
		wp_enqueue_style( 'dashicons' );
	}
}
