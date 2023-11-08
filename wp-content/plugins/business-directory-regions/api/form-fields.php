<?php

function wpbdp_regions_fields_api() {
    return WPBDP_RegionFieldsAPI::instance();
}

class WPBDP_RegionFieldsAPI {

    private static $instance = null;

    public function __construct() {}

    public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new WPBDP_RegionFieldsAPI();
		}
        return self::$instance;
    }

    /* Business Directory Form Fields API integration */
    /**
     * Handler for the wpbdp_form_field_settings hook.
     */
	public function field_settings( &$field, $association ) {
		if ( ! $field || $association != 'region' ) {
            return;
		}

        $settings = array();

        $label = _x( 'Display field in Region selector?', 'field settings', 'wpbdp-regions' );
		$html = '<input type="checkbox" name="field[x_display_in_region_selector]" ' . ( $field->has_display_flag( 'region-selector' ) ? 'checked="checked"' : '' ) . ' value="1" />';
        $settings[] = array( $label, $html );

		echo WPBDP_Form_Field_Type::render_admin_settings( $settings );
    }

    /**
     * Handler for the wpbdp_form_field_settings_process hook.
     */
    public function field_settings_process( &$field ) {
        if ( $field->get_association() !== 'region' ) {
            return;
		}

		$nonce = wpbdp_get_var( array( 'param' => '_wpnonce' ), 'request' );
		if ( ! wp_verify_nonce( $nonce, 'editfield' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'wpbdp-regions' ) );
		}

		$display_in_selector = isset( $_POST['field']['x_display_in_region_selector'] ) ? sanitize_text_field( wp_unslash( $_POST['field']['x_display_in_region_selector'] ) ) : '';
		if ( $display_in_selector == 1 ) {
			$field->add_display_flag( 'region-selector' );
		} else {
			$field->remove_display_flag( 'region-selector' );
		}

		$display_in_form = isset( $_POST['field']['x_display_in_form'] ) ? sanitize_text_field( wp_unslash( $_POST['field']['x_display_in_form'] ) ) : '';
		if ( $display_in_form == 1 ) {
			$field->add_display_flag( 'regions-in-form' );
		} else {
			$field->remove_display_flag( 'regions-in-form' );
		}
    }

    /**
     * Handler for the wpbdp_form_field_store_value hook.
     *
     * @param  [type]  $field   [description]
     * @param  [type]  $listing_id [description]
     * @param  [type]  $value   [description]
     */
    public function store_value( $field, $listing_id, $value ) {
		if ( $field && 'region' !== $field->get_association() ) {
            return;
        }

        delete_post_meta( $listing_id, '_wpbdp[fields][' . $field->get_id() . ']' );
    }

    /**
     * Handler for the wpbdp_form_field_value hook.
     *
     * @param  [type]  $value   [description]
     * @param  [type]  $listing_id [description]
     * @param  [type]  $field   [description]
     * @param  boolean $use_active_region  use active region if no value is found
     * @return int the ID of the selected region for this field.
     */
	public function field_value( $value, $listing_id, $field, $use_active_region = false ) {
        if ( $field->get_association() != 'region' )
            return $value;

		$value = ( is_array( $value ) && isset( $value[0] ) ) ? $value[0] : $value;

        if ( ! empty( $value ) ) {
			return $value;
		}

		$level = $this->get_field_level( $field );

		if ( is_null( $level ) ) {
			return $value;
		}

		list( $value, $parent ) = $this->_field_value( $field, $level, $listing_id );

		if ( $use_active_region && absint( $value ) === 0 ) {
			$value = wpbdp_regions_api()->get_active_region_by_level( $level );
		}

        // necessary so field_attributes() knows we find a parent region
        // for this field, otherwise, field_attributes() may hide the field.
		if ( absint( $parent ) > 0 ) {
			wpbdp_regions()->set( 'parent-for-' . $field->get_id(), $parent );
		}

		if ( ! $value ) {
            return array();
		}

        return $value;
    }

    /**
     * Finds the ID of the selected region for the given field and the ID of
     * the selected region for the parent field, if any.
     *
     * If $listing is given, the ID of the selected region will be the ID
     * of one of the terms associated that listing. Otherwise, the function
     * will look into the posted data to see if a values was submitted for
     * the given field. The same applies for the ID selected for the parent
     * field.
     *
     * @param  [type] $field   [description]
     * @param  [type] $level   [description]
     * @param  [type] $listing [description]
     * @return array
     */
	private function _field_value( $field, $level, $listing_id = 0, $display_context = '' ) {
        $contexts = array('search', 'submit_listing', 'edit_listing');
		$value    = null;
		$parent   = null;

		if ( $listing_id ) {
			$regions = $this->get_listing_regions( $listing_id );
        } elseif ( in_array( wpbdp_current_view(), $contexts, true ) || 'search' == $display_context ) {
            $regions = $this->get_submitted_regions();
        } else {
            $regions = array();
        }

		if ( ! empty( $regions ) ) {
			$total = count( $regions );
			if ( isset( $regions[ $total - ( $level - 1 ) ] ) ) {
				$parent = $regions[ $total - ( $level - 1 ) ];
            }
			if ( isset( $regions[ $total - $level ] ) ) {
				$value = $regions[ $total - $level ];
            }
        }

		if ( absint( $parent ) === 0 ) {
            // FIXME: hack related to https://github.com/drodenbaugh/BusinessDirectoryPlugin/issues/2773.
            if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || 'search' != $display_context ) {
				$parent = wpbdp_regions()->get( 'parent-for-' . $field->get_id() );
            }
        }

		if ( $level > 1 ) {
			$ancestor = $this->get_field_by_level( $level - 1 );
            // we assume that if a field is not being shown, all fields associated
            // to higher levels in the regions hieararchy (the parent fields)
            // are also not being shown.
            $parent_visible = $ancestor ? $ancestor->has_display_flag( 'region-selector' ) : false;
        } else {
            $parent_visible = false;
        }

        // force first visible field to show all options available
        $parent = $parent_visible ? $parent : null;

        return array($value, $parent);
    }

	private function get_listing_regions( $listing_id ) {
        static $cache = array();

		if ( isset( $cache[ $listing_id ] ) ) {
			return $cache[ $listing_id ];
		}

        $args = array('orderby' => 'id', 'order' => 'DESC', 'fields' => 'ids');
		$regions = wp_get_object_terms( $listing_id, wpbdp_regions_taxonomy(), $args );
        $hierarchy = array();

        $api = wpbdp_regions_api();

		foreach ( $regions as $id ) {
			$api->get_region_level( $id, $hierarchy );
			if ( count( array_diff( $regions, $hierarchy ) ) === 0 ) {
                break;
            }

            $hierarchy = array();
        }

		$cache[ $listing_id ] = $hierarchy;

        return $hierarchy;
    }

    /**
     * Return the hierarchy of the regions submitted by the user
     * while adding/editing a new listing or searching for listings.
     */
    private function get_submitted_regions() {
        static $cache = null;

		if ( is_array( $cache ) ) {
			return $cache;
		}

        $regions = wpbdp_regions_api();
        $formfields = wpbdp()->formfields;

		$data   = wpbdp_get_var( array( 'param' => 'listingfields', 'default' => array() ), 'request' );
        $fields = $this->get_fields();
		arsort( $fields );

        $hierarchy = array();
		foreach ( $fields as $level => $id ) {
			$field = $formfields->get_field( $id );

			if ( ! $field ) {
				continue;
			}
			$region = (int) wpbdp_getv( $data, $field->get_id() );

            if ($region <= 0) continue;

            $hierarchy = array();
			$regions->get_region_level( $region, $hierarchy );

            break;
        }

        $cache = $hierarchy;

        return $hierarchy;
    }

	public function field_attributes( &$field, $selected, $display_context ) {
		$level = $this->get_field_level( $field );

        // not a region field
		if ( is_null( $level ) ) {
			return;
		}

        $field->css_classes[] = 'wpbdp-region-field';
		$field->html_attributes['data-region-level']    = $level;
		$field->html_attributes['data-display-context'] = $display_context;

		list( $_, $parent ) = $this->_field_value( $field, $level, 0, $display_context );

		$min = $this->get_min_visible_level( $display_context );

        // do not render field options if there is no parent region selected.
        // this field will be hidden until a parent region is selected so
        // there is no need to spent time building the list of options
		$in_listing_form = $display_context === 'submit';
		$should_hide = ( $in_listing_form && $level > $min && absint( $selected ) === 0 && absint( $parent ) === 0 );
        // do not render field options if the settings say the field should be hidden
		$should_hide = $should_hide || ( $in_listing_form && ! $field->has_display_flag( 'region-selector' ) );
        // do not render field options if the field's level is below the
        // min visible level
        $should_hide = $should_hide || ( 'widget' != $display_context && $level < $min );

        if ( 'widget' == $display_context )
            $should_hide = false;

        if ( $should_hide ) {
            $field->css_classes[] = 'wpbdp-regions-hidden';
            $show_empty_option = false;
        } else if ( 'widget' == $display_context || $level >= $min ) {
            $options = $this->_field_options( $field, $level, $selected, $parent, $display_context );
            $show_empty_option = isset( $options[''] ) ? false : true;

			$field->set_data( 'options', $options );
        }

        $field->set_data( 'show_empty_option', $show_empty_option );
        $field->set_data( 'empty_option_label', _x( 'Select a Region', 'region-selector', 'wpbdp-regions' ) );
    }

    public function field_option( $option, $field ) {
		$level = $this->get_field_level( $field );

        if ( is_null( $level ) || 0 == $option['value'] )
            return $option;

        $regions = wpbdp_regions_api();
        $option['attributes']['data-url'] = esc_url( $regions->region_link( $option['value'], true ) );
        return $option;
    }

	public function field_html_value( $value, $listing_id, $field ) {
        if ( $field->get_association() != 'region' )
            return $value;

		if ( ! $value ) {
            return '';
		}

        $value = is_array( $value ) ? $value[0] : $value;
        if ( ! absint( $value ) ) {
			return $value;
		}

		$level = $this->get_field_level( $field );

		if ( is_null( $level ) ) {
			return $value;
		}

		$region = wpbdp_regions_api()->find_by_id( $value );

		if ( is_null( $region ) || is_wp_error( $region ) ) {
			return $value;
		}

        return $region->name;
    }

	public function field_plain_value( $value, $listing_id, $field ) {
        if ( $field->get_association() != 'region' )
            return $value;

        return $this->field_html_value( $value, $listing_id, $field );
    }

	private function _field_options( $field, $level, $selected, $parent, $display_context = '' ) {
        $api = wpbdp_regions_api();

        // get visible regions for this level, filtering by parent selected region, if any
        $results = $api->find_visible_regions_by_level( $level, $parent );

        // build options array
        if ( empty( $results ) ) {
			return array( '' => _x( 'No Regions available', 'region-selector', 'wpbdp-regions' ) );
		}

		$is_listing_form = 'submit' === $display_context;
		$hide_empty      = ! $is_listing_form;
		$show_counts     = $is_listing_form ? false : wpbdp_get_option( 'regions-show-counts' );

		$results = $api->find( array( 'include' => $results, 'hide_empty' => 0 ) );

		if ( ( wp_doing_ajax() || ! is_admin() ) && ( $show_counts || $hide_empty ) ) {
			$api->fix_regions_count(
				$results,
				isset( $api->session['category_id'] ) ? $api->session['category_id'] : 0
			);
		}

		$regions = array();

		foreach ( $results as $item ) {
			if ( $hide_empty && 0 == $item->count ) {
				continue;
			}

			$regions[ $item->term_id ] = $show_counts ? sprintf( '%s (%s)', $item->name, $item->count ) : $item->name;
		}

        return $regions;
    }

    /* API */

	public function get_fields( $sort = false ) {
		$fields = get_option( 'wpbdp-regions-form-fields', array() );

		if ( $sort === 'asc' ) {
			ksort( $fields );
		} elseif ( $sort === 'desc' ) {
			krsort( $fields );
		}
        return $fields;
    }

	public function update_fields( $fields = array() ) {
		update_option( 'wpbdp-regions-form-fields', $fields, 'no' );
	}

	public function get_field_level( $field ) {
		foreach ( $this->get_fields() as $level => $id ) {
            if ($id == $field->get_id())
                return $level;
        }
        return $field->data( 'level', null );
    }

	public function get_field_by_level( $level = 1 ) {
		$id = wpbdp_getv( $this->get_fields(), $level, null );
		if ( ! is_null( $id ) ) {
			return wpbdp()->formfields->get_field( $id );
        }
        $fields = wpbdp_get_form_fields( array( 'association' => 'region' ) );

		foreach ( $fields as $field ) {
            if ( $field->data( 'level', null ) == $level ) {
                return $field;
            }
        }
        return null;
    }

	public function get_visible_fields( $context = '' ) {
        $regionfields = wpbdp_regions_fields_api();
        $max = wpbdp_regions_api()->get_max_level();

        $fields = array();
		for ( $level = 1; $level <= $max; $level++ ) {
			$field = $regionfields->get_field_by_level( $level );

			if ( is_null( $field ) ) {
				continue;
			}

			$in_selector = $context !== 'search' && $field->has_display_flag( 'region-selector' );
			if ( ! $in_selector ) {
                continue;
			}

            $fields[] = $field;
        }

        return $fields;
    }

	public function get_min_visible_level( $context = '' ) {
		$fields = $this->get_visible_fields( $context );
		if ( empty( $fields ) ) {
            return null;
		}
		return $this->get_field_level( $fields[0] );
    }

    public function delete_fields() {
		$fields = wpbdp_get_form_fields( 'association=region' );

		foreach ( $fields as &$f ) {
            $f->delete();
        }

		delete_option( 'wpbdp-regions-form-fields' );
    }

    public function show_fields() {
		$options = get_option( 'wpbdp-regions-form-fields-options', array() );
		foreach ( $options as $id => $display_options ) {
			$field = wpbdp_get_form_field( $id );

            if ( ! $field ) {
				continue;
			}

            $field->set_display_flags( $display_options );
            $field->save();
        }

		delete_option( 'wpbdp-regions-form-fields-options' );
    }

    public function hide_fields() {
        // if we already have options stored, return to avoid overwriting stored data
		$options = get_option( 'wpbdp-regions-form-fields-options', array() );
		if ( ! empty( $options ) ) {
			return;
		}

		if ( ! function_exists( 'wpbdp' ) ) {
            return;
		}

		foreach ( $this->get_fields() as $level => $id ) {
			$field = wpbdp_get_form_field( $id );

			if ( ! $field ) {
				continue;
			}

			$options[ $id ] = $field->get_display_flags();
            $field->remove_display_flag( array( 'excerpt', 'listing', 'search', 'regions-in-form' ) );
            $field->save();
        }

		update_option( 'wpbdp-regions-form-fields-options', $options, 'no' );
    }

    public function before_field_delete( &$field ) {
        update_option( 'wpbdp-clean-regions-cache', true );
    }

}
