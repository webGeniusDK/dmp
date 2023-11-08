<?php
/**
 * Admin dashboard widget view of recent listings
 */

?>
<table class="wp-list-table widefat fixed striped posts">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Name', 'wpbdp-pro' ); ?></th>
			<th><?php esc_html_e( 'Status', 'wpbdp-pro' ); ?></th>
			<th><?php esc_html_e( 'Price', 'wpbdp-pro' ); ?></th>
			<th><?php esc_html_e( 'Date', 'wpbdp-pro' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach ( $recent_listings as $listing ) {
			$listing_id     = $listing['ID'];
			$listing_object = wpbdp_get_listing( $listing_id );
			$post_status    = get_post_status_object( $listing['post_status'] );
			?>
			<tr>
				<td>
					<?php
						printf(
							'<a href="%1$s">%2$s</a>',
							esc_url( get_permalink( $listing_id ) ),
							esc_html( apply_filters( 'the_title', $listing['post_title'], $listing_id ) )
						);
					?>
				</td>
				<td><?php echo esc_html( $listing_object->get_status_label() ); ?></td>
				<td>
					<?php
						$plan = $listing_object->get_fee_plan();
					if ( ! $plan ) {
						esc_html_e( 'No Fee Plan', 'wpbdp-pro' );
					} else {
						if ( 0.0 == $plan->fee_price ) {
							esc_html_e( 'Free', 'wpbdp-pro' );
						} else {
							echo esc_html( $plan->fee_price );
						}
					}
					?>
				</td>
				<td><?php echo get_the_date( '', $listing_id ); ?></td>
			</tr>
			<?php
		}
		?>
	</tbody>
</table>
