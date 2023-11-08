<?php

class WPBDP_Table_List {

	/**
	 * Load any hooks here.
	 */
	public function __construct() {
		add_filter( 'wpbdp_listings_class', array( &$this, 'add_container_class' ) );
		add_filter( 'wpbdp_before_excerpts', array( &$this, 'add_table_header' ), 99, 2 );
		add_filter( 'wpbdp_listing_sort_options', array( &$this, 'remove_sort_options' ), 99 );
		add_filter( 'wpbdp_use_template_excerpt_content', array( &$this, 'use_selected_template' ) );
	}

	/**
	 * @param string $classes
	 */
	public function add_container_class( $classes ) {
		$classes .= ' wpbdp-is-table ';
		return $classes;
	}

	/**
	 * Add the table header row for fields in the excerpt view.
	 *
	 * @param string $content - The content to show.
	 * @param array  $vars - Includes images and fields.
	 */
	public function add_table_header( $content, $vars ) {
		global $wpbdp_columns;

		$content .= '<div class="wpbdp-listing wpbdp-listing-table-header">';
		if ( $vars['images']->thumbnail ) {
			$content .= '<div class="listing-thumbnail"></div>';
		}

		$sort_options = $this->get_sort_options();

		$shown = 0;
		foreach ( $vars['fields']->not( 'social' ) as $field ) {
			$show = $field->field->display_in( 'excerpt' );
			if ( $show && $shown < $this->max_columns() ) {
				$label    = $this->maybe_add_sort( $sort_options, $field );
				$content .= '<div>' . $label . '</div>';
				++ $shown;

				// Track the columns that are shown with the field id.
				$wpbdp_columns[] = $field->id;
			}
		}

		if ( $vars['fields']->filter( 'social' ) ) {
			$content .= '<div class="social-fields cf"></div>';
		}

		$content .= '</div>';
		return $content;
	}

	/**
	 * Get the fields selected for sorting.
	 */
	private function get_sort_options() {
		$sort_options = array();
		if ( wpbdp_get_option( 'listings-sortbar-enabled' ) ) {
			$sort_options = apply_filters( 'wpbdp_listing_sort_options', array() );
		}
		return $sort_options;
	}

	/**
	 * Compare the field id to the sorting id.
	 *
	 * @param array  $sort_options - A list of fields to sort by.
	 * @param object $field - WPBDP_Form_Field.
	 */
	private function maybe_add_sort( $sort_options, $field ) {
		$sort_id = $this->get_field_sort_id( $field->field, $sort_options );

		if ( ! isset( $sort_options[ $sort_id ] ) ) {
			return esc_html( $field->field->get_label() );
		}

		$current_sort = wpbdp_get_current_sort_option();
		$sort_option  = $sort_options[ $sort_id ];

		$default_order = ! empty( $sort_option[2] ) ? strtoupper( $sort_option[2] ) : 'ASC';

		if ( $current_sort && $current_sort->option == $sort_id ) {
			$link  = add_query_arg( 'wpbdp_sort', ( $current_sort->order === 'ASC' ? '-' : '' ) . $sort_id );
			$arrow = $current_sort->order === 'ASC' ? '↑' : '↓';
		} else {
			$link  = add_query_arg( 'wpbdp_sort', ( $default_order === 'DESC' ? '-' : '' ) . $sort_id );
			$arrow = '<span class="wpbdp-show-hover">' . ( $default_order === 'DESC' ? '↓' : '↑' ) . '</span>';
		}

		return sprintf(
			'<span class="%s %s"><a href="%s" title="%s" rel="nofollow">%s %s</a></span>',
			$sort_id,
			( $current_sort && $current_sort->option == $sort_id ) ? 'current' : '',
			esc_url( $link ),
			esc_attr( ! empty( $sort_option[1] ) ? $sort_option[1] : $sort_option[0] ),
			$sort_option[0],
			$arrow
		);
	}

	/**
	 * Compare the field id to the sorting id.
	 *
	 * @param object $field - WPBDP_Form_Field.
	 * @param array  $sort_options - A list of fields to sort by.
	 */
	private function get_field_sort_id( $field, $sort_options ) {
		$sort_id = 'field-' . $field->get_id();

		if ( ! isset( $sort_options[ $sort_id ] ) ) {
			$sort_rating = isset( $sort_options['rating'] );
			$is_rating   = $sort_rating && $field->get_field_type_id() === 'ratings';
			if ( $is_rating ) {
				$sort_id = 'rating';
			}
		}

		return $sort_id;
	}

	/**
	 * Exclude options from the sorting dropdown if they'll be included in the table.
	 *
	 * @param array $options - A list of fields to sort by.
	 */
	public function remove_sort_options( $options ) {
		$original_options = $options;

		$fields = wpbdp_formfields_api()->get_fields();
		$shown  = 0;

		foreach ( $fields as $field ) {
			if ( ! $field->display_in( 'excerpt' ) || $shown >= $this->max_columns() ) {
				continue;
			}
			++ $shown;

			$sort_id = $this->get_field_sort_id( $field, $options );
			if ( isset( $options[ $sort_id ] ) ) {
				unset( $options[ $sort_id ] );
			}
		}

		// If the sort dropdown will show, include all options.
		if ( ! empty( $options ) ) {
			$options = $original_options;
		}

		remove_filter( 'wpbdp_listing_sort_options', array( $this, 'remove_sort_options' ), 99 );
		return $options;
	}

	/**
	 * Get the number of columns to show in the table.
	 *
	 * @since 5.1
	 *
	 * @return int
	 */
	private function max_columns() {
		return apply_filters( 'wpbdp_table_max_columns', 6 );
	}

	/**
	 * Override other selected templates.
	 *
	 * @since 5.1
	 */
	public function use_selected_template( $path ) {
		return dirname( dirname( __FILE__ ) ) . '/views/tables/excerpt-content.tpl.php';
	}
}

function wpbdp_user_max_columns() {
	return apply_filters( 'wpbdp_table_max_columns', 6 );
}
