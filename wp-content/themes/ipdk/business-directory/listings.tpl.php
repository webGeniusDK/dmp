<?php
/**
 * Listings display template
 *
 * @package BDP/Templates/Listings
 */

wpbdp_the_listing_sort_options();

if(count($query->posts) <= 3) {
    $count = 'low';
} elseif( count($query->posts) <= 6 ) {
	$count = 'medium';
} else {
    $count = 'high';
}
?>

<div class="wpbdp-listings-list-wrapper listings-count-<?= $count ?>">
    <div class="container">
        <div id="wpbdp-listings-list" class="listings wpbdp-listings-list equalbox-container list <?php echo esc_attr( apply_filters( 'wpbdp_listings_class', '' ) ); ?>">
			<?php
			wpbdp_x_part(
				'parts/listings-loop',
				array(
					'query' => $query,
				)
			);
			?>
        </div>
    </div>
</div>
