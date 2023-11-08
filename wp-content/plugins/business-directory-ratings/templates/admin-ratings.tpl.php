<table id="wpbdp-ratings" class="widefat fixed listing-ratings" cellspacing="0">
<tbody>
    <tr class="no-items" style="<?php echo esc_attr( $reviews ? 'display: none;' : '' ); ?>">
        <td colspan="2"><?php esc_html_e( 'This listing has not been rated yet.', 'wpbdp-ratings' ); ?></td>
    </tr>
	<?php
	foreach ( $reviews as $i => $review ) {
		wpbdp_render_page(
			plugin_dir_path( __FILE__ ) . 'admin-rating-row.tpl.php',
			array( 'review' => $review ),
			true
		);
	}
	?>
</tbody>
</table>
