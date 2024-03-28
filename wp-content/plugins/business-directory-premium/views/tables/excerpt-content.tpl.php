<?php
/**
 * Template listing excerpt view.
 * Include placeholders for empty values and limit the number of columns.
 *
 * @package WPBDPUserTheme
 */

if ( $images->thumbnail ) {
	$image = $images->thumbnail->html;
	if ( $image ) {
		//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $image;
	} else {
		echo '<div class="listing-thumbnail"></div>';
	}
}

global $wpbdp_columns;
$shown = 0;
foreach ( $fields->not( 'social' ) as $field ) {
	$show = $field->field->get_display_flags();
	if ( $show && $shown < wpbdp_user_max_columns() ) {
		// Add a placeholder when there is an extra column in the header.
		if ( isset( $wpbdp_columns[ $shown ] ) && $wpbdp_columns[ $shown ] !== $field->id ) {
			echo '<div></div>';
			++$shown;
		}
		++$shown;
		$html = $field->html;
		if ( ! $html ) {
			echo '<div></div>';
		} else {
			//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $html;
		}
	}
}

$social = $fields->filter( 'social' );

if ( $social ) :
	?>
<div class="social-fields cf">
	<?php
	//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $social->html;
	?>
</div>
<?php endif; ?>
