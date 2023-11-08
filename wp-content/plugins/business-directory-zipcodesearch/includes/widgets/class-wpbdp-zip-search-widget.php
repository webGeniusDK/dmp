<?php
// Do not allow direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WPBDP_ZIPSearchWidget
 */
class WPBDP_ZIPSearchWidget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			false,
			_x( 'Business Directory - Location Search', 'widget', 'wpbdp-zipcodesearch' ),
			array( 'description' => _x( 'Searches the Business Directory listings by ZIP code.', 'widget', 'wpbdp-zipcodesearch' ) )
		);
	}

	public function form( $instance ) {
		$instance = $this->get_default_settings( $instance );

		$title = $instance['title'];
		$label = $instance['field_label'];
		$units = $instance['units'];

		$hide_mode_selector     = $instance['hide_mode_selector'];
		$hide_category_selector = $instance['hide_category_selector'];
		$default_search_mode    = $instance['default_search_mode'];

		printf(
			'<p><label for="%s">%s</label> <input class="widefat" id="%s" name="%s" type="text" value="%s" /></p>',
			esc_attr( $this->get_field_id( 'title' ) ),
			esc_html__( 'Title:', 'wpbdp-zipcodesearch' ),
			esc_attr( $this->get_field_id( 'title' ) ),
			esc_attr( $this->get_field_name( 'title' ) ),
			esc_attr( $title )
		);

		printf(
			'<p><label for="%s">%s</label> <input class="widefat" id="%s" name="%s" type="text" value="%s" /></p>',
			esc_attr( $this->get_field_id( 'field_label' ) ),
			esc_html__( 'Field Label:', 'wpbdp-zipcodesearch' ),
			esc_attr( $this->get_field_id( 'field_label' ) ),
			esc_attr( $this->get_field_name( 'field_label' ) ),
			esc_attr( $label )
		);

		echo '<p>';
		printf( '<label for="%s">%s</label>', esc_attr( $this->get_field_id( 'units' ) ), esc_html__( 'Units:', 'wpbdp-zipcodesearch' ) );
		printf( '<select id="%s" name="%s">', esc_attr( $this->get_field_id( 'units' ) ), esc_attr( $this->get_field_name( 'units' ) ) );
		foreach ( wpbdp_zipcodesearch_unit_options() as $opt => $l ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $opt ),
				selected( $units, $opt, false ),
				esc_html( $l )
			);
		}

		echo '</select>';
		echo '</p>';

		echo '<p><label>' . esc_html__( 'Display Settings:', 'wpbdp-zipcodesearch' ) . '</label><br />';

		printf(
			'<p><input class="widefat" id="%s" name="%s" type="checkbox" %s /> <label for="%s">%s</label></p>',
			esc_attr( $this->get_field_id( 'hide_mode_selector' ) ),
			esc_attr( $this->get_field_name( 'hide_mode_selector' ) ),
			esc_html( $hide_mode_selector ? 'checked' : '' ),
			esc_attr( $this->get_field_id( 'hide_mode_selector' ) ),
			esc_html__( 'Hide search mode selector', 'wpbdp-zipcodesearch' )
		);

		printf(
			'<p><input class="widefat" id="%s" name="%s" type="checkbox" %s /> <label for="%s">%s</label></p>',
			esc_attr( $this->get_field_id( 'hide_category_selector' ) ),
			esc_attr( $this->get_field_name( 'hide_category_selector' ) ),
			$hide_category_selector ? 'checked' : '',
			esc_attr( $this->get_field_id( 'hide_category_selector' ) ),
			esc_html__( 'Hide category field selector', 'wpbdp-zipcodesearch' )
		);

		echo '<p><label>' . esc_html__( 'Default Search Mode:', 'wpbdp-zipcodesearch' ) . '</label><br />';

		printf(
			'<label><input type="radio" id="%s" name="%s" value="zip" %s /> %s </label>',
			esc_attr( $this->get_field_id( 'default_search_mode' ) ),
			esc_attr( $this->get_field_name( 'default_search_mode' ) ),
			checked( 'zip', $default_search_mode, false ),
			/* translators: %s field label */
			esc_html( sprintf( __( 'Specific %s', 'wpbdp-zipcodesearch' ), $label ) )
		);
		echo '<br />';
		printf(
			'<label><input type="radio" id="%s" name="%s" value="distance" %s /> %s</label>',
			esc_attr( $this->get_field_id( 'default_search_mode' ) ),
			esc_attr( $this->get_field_name( 'default_search_mode' ) ),
			checked( 'distance', $default_search_mode, false ),
			esc_html__( 'Distance search', 'wpbdp-zipcodesearch' )
		);

	}

	private function get_default_settings( $instance ) {
		$defaults = array(
			'title'                  => _x( 'Location Search', 'widgets', 'wpbdp-zipcodesearch' ),
			'hide_mode_selector'     => false,
			'hide_category_selector' => false,
			'default_search_mode'    => 'distance',
		);
		$instance = array_merge( $defaults, $instance );

		if ( ! isset( $instance['field_label'] ) ) {
			$instance['field_label'] = '';

			$field_id = wpbdp_get_option( 'zipcode-field' );
			if ( $field_id ) {
				$field = wpbdp_get_form_field( $field_id );
				if ( $field ) {
					$instance['field_label'] = $field->get_label();
				}
			}
		}

		if ( ! isset( $instance['units'] ) ) {
			$instance['units'] = wpbdp_get_option( 'zipcode-units' );
		}

		return $instance;
	}

	public function update( $new_instance, $old_instance ) {
		$new_instance['title'] = strip_tags( $new_instance['title'] );
		$new_instance['label'] = strip_tags( $new_instance['label'] );

		if ( isset( $new_instance['units'] ) && in_array( $new_instance['units'], array( 'miles', 'kilometers' ), true ) ) {
			wpbdp_set_option( 'zipcode-units', $new_instance['units'] );
		}

		return $new_instance;
	}

	public function widget( $args, $instance ) {
		extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		$instance = $this->get_default_settings( $instance );

		$title = apply_filters( 'widget_title', $instance['title'] );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $before_widget;
		if ( ! empty( $title ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $before_title . $title . $after_title;
		}

		printf( '<form action="%s" method="get">', esc_url( wpbdp_url( 'search' ) ) );

		if ( ! wpbdp_rewrite_on() ) {
			printf( '<input type="hidden" name="page_id" value="%s" />', esc_attr( wpbdp_get_page_id( 'main' ) ) );
		}

		echo '<input type="hidden" name="wpbdp_view" value="search" />';
		echo '<input type="hidden" name="dosrch" value="1" />';

		$zip_field_id = wpbdp_get_option( 'zipcode-field' );
		if ( $zip_field_id ) {
			$field = wpbdp_get_form_field( $zip_field_id );
			if ( $field ) {
				$label = trim( $instance['field_label'] );
				if ( ! $label ) {
					$label = $field->get_label();
				}

				echo '<div class="wpbdp-zipcodesearch-widget-field zip-field wpbdp-zipcodesearch-autocomplete" data-ajaxurl="' . esc_url( add_query_arg( 'action', 'wpbdp-zipcodesearch-code-search', wpbdp_ajaxurl() ) ) . '">';
				echo '<label>' . esc_html( $label ) . '</label><br />';
				echo '<input type="text" name="listingfields[' . esc_attr( $zip_field_id ) . '][zip]" value="" size="5" class="zipcode-search-zip" /><br />';
				echo '<input type="hidden" name="listingfields[' . esc_attr( $zip_field_id ) . '][country]" value="" class="country-hint" />';
				echo '</div>';
				echo '<div class="invalid-msg">Please enter a valid ZIP code.</div>';

				if ( ! $instance['hide_mode_selector'] ) {
					$module = WPBDP_ZIPCodeSearchModule::instance();
					$module->show_zip_search(
						array(
							'search_modes' => $instance['hide_mode_selector'] ? array() : array( 'zip', 'distance' ),
							'mode'         => $instance['default_search_mode'],
							'radius'       => wpbdp_get_option( 'zipcode-quick-search-radius', 0 ),
							'id'           => $zip_field_id,
							'echo'         => true,
						)
					);

					echo '<br/>';
				} else {
					echo sprintf(
						'<input type="hidden" name="listingfields[%s][mode]" value="%s" />',
						esc_attr( $zip_field_id ),
						esc_attr( $instance['default_search_mode'] )
					);

					echo '<div class="zipcode-search-distance-fields" style="display: none;">';

					echo sprintf(
						'<input type="hidden" name="listingfields[%s][radius]" value="%s" />',
						esc_attr( $zip_field_id ),
						esc_attr( 'distance' === $instance['default_search_mode'] ? wpbdp_get_option( 'zipcode-quick-search-radius' ) : 0 )
					);

					echo '</div>';
				}

				echo sprintf(
					'<div class="zipcode-search-category-field" style="%s">',
					esc_html( $instance['hide_category_selector'] ? 'display:none' : '' )
				);

				$cfield = wpbdp_get_form_fields(
					array(
						'association' => 'category',
						'unique'      => true,
					)
				);

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $cfield->render( null, 'search' );
				echo '</div>';
			}
		}

		printf(
			'<p><input type="submit" value="%s" class="submit wpbdp-search-widget-submit" /></p>',
			esc_attr__( 'Search', 'wpbdp-zipcodesearch' )
		);
		echo '</form>';
		?>
		<script type="text/javascript">
		jQuery(function($) {
			$('.widget_wpbdp_zipsearchwidget input[type="radio"]').change(function(){
				var $widget = $(this).parents( '.widget' );
				var mode = $(this).val();

				if ( 'distance' == mode ) {
					$( '.zipcode-search-distance-fields', $widget ).fadeIn( 'fast' );
					$( '.zipcode-search-distance-fields input' ).focus();
				} else if ( 'zip' == mode ) {
					$( '.zipcode-search-distance-fields', $widget ).fadeOut( 'fast' );
					$( 'input.zipcode-search-zip', $widget ).focus();
				}
			});

			$('.widget_wpbdp_zipsearchwidget input[type="submit"]').click(function(e) {
				var $form = $(this).parents('form');
				var $widget = $(this).parents('.widget');
				var $zip = $( 'input.zipcode-search-zip', $form );
				var zip = $.trim( $zip.val() );
				var mode = $( 'input[type="radio"]:checked' ).val();
				var $distance = $( '.zipcode-search-distance-fields input, .zipcode-search-distance-fields select', $form );
				var distance = parseFloat( $distance.val() );

				var validation_errors = false;

				if ( ! zip ) {
					$zip.addClass( 'invalid' );
//                    $zip.siblings('.invalid-msg').show();
					validation_errors = true;
				}

				if ( '' === distance || distance < 0 || isNaN( distance ) ) {
					$distance.addClass( 'invalid' );
//                    $distance.siblings('.invalid-msg').show();
					validation_errors = true;
				}

				if ( validation_errors )
					return false;

				return true;
			});

			//If the distance is selected, trigger the change event
			var $widget_distance_button = $( '.widget_wpbdp_zipsearchwidget :input[value="distance"]' );
			if ( $widget_distance_button.is(':checked' ) ) {
				$widget_distance_button.trigger( 'change' );
			}
		});
		</script>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $after_widget;
	}

}
