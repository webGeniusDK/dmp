<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Field icon selector.
 * Used to show selector for the different field label types to include icons.
 *
 * @since 5.3
 */

?>
<tr class="<?php echo in_array( 'nolabel', $hidden_fields, true ) ? 'wpbdp-hidden' : ''; ?>">
	<th scope="row">
		<label> <?php esc_html_e( 'Display label and/or icon', 'business-directory-plugin' ); ?></label>
	</th>
	<td>
		<label>
			<select name="field[display_flags][]" class="wpbd-field-label-select">
				<option value="fieldlabel" <?php selected( $field->has_display_flag( 'fieldlabel' ) ); ?>>
					<?php esc_html_e( 'Show Label', 'business-directory-plugin' ); ?>
				</option>
				<option value="nolabel" <?php selected( $field->has_display_flag( 'nolabel' ) ); ?>>
					<?php esc_html_e( 'Hide Label', 'business-directory-plugin' ); ?>
				</option>
				<option value="fieldlabelicon" <?php selected( $field->has_display_flag( 'fieldlabelicon' ) ); ?>>
					<?php esc_html_e( 'Show Label and Icon', 'wpbdp-pro' ); ?>
				</option>
				<option value="icon" <?php selected( $field->has_display_flag( 'icon' ) ); ?>>
					<?php esc_html_e( 'Show Icon', 'wpbdp-pro' ); ?>
				</option>
				<?php if ( $this->check_field_icon_value_types( $field ) ) : ?>
				<option value="valueicon" <?php selected( $field->has_display_flag( 'valueicon' ) ); ?>>
					<?php esc_html_e( 'Replace Value with Icon', 'wpbdp-pro' ); ?>
				</option>
				<?php endif; ?>
			</select>
		</label>
	</td>
</tr>
<tr class="if-field-icon <?php echo $show_icon_field ? '' : 'wpbdp-hidden'; ?>">
	<th scope="row">
		<label> <?php esc_html_e( 'Field Icon', 'wpbdp-pro' ); ?></label>
	</th>
	<td>
		<label>
			<?php
			$icon = '';
			$font = '';
			if ( ! empty( $field->data( 'icon' ) ) ) {
				$icon_parts = explode( '|', $field->data( 'icon' ) );
				$icon       = $icon_parts[1];
				$font       = $icon_parts[0];
			}
			?>
			<input class="regular-text" type="hidden" id="wpbdp-field-icon" name="field[field_data][icon]" value="<?php echo esc_attr( $field->data( 'icon' ) ); ?>"/>
			<div data-target="#wpbdp-field-icon" data-selected="<?php echo esc_attr( $font ); ?>" class="button wpbdp-icon-picker <?php echo esc_attr( $icon ); ?>"></div><br/>
		</label>
	</td>
</tr>
