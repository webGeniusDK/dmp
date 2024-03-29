<?php
/**
 * Template listing excerpt view.
 *
 * @package BDP/Templates/Excerpt
 */

$__template__ = array( 'blocks' => array( 'before', 'after' ) );
?>
<div id="<?php echo esc_attr( $listing_css_id ); ?>" class="listing-item" data-breakpoints-class-prefix="wpbdp-listing-excerpt">

	<?php
	echo $blocks['before'];
	if ( in_array( 'excerpt', wpbdp_get_option( 'display-sticky-badge' ) ) ) {
		echo $sticky_tag;
	}

	wpbdp_x_part( 'excerpt_content' );
	echo $blocks['after'];


    echo wpbdp_the_listing_actions();
	?>
</div>
