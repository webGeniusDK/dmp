<?php
/**
 * Template listing single view.
 *
 * @package BDP/Templates/Single
 */
$top_fields     = [];
$side_fields    = [];
$bottom_fields  = [];
$excerpt_field  = '';
$top_field_ids  = [ 31, 32, 33, 34, 35, 27 ]; // 31 = Alder, 32 = Vægt, 33 = Højde, 34 = Hårfarve, 35 = Brystmål, 27 = By
$side_field_ids = [ 11, 19, 8, 38 ]; // 11 = Services , 19 = Sikker sex, 8 = Ydelser, 38 = Priser


//Run through all fields to find the fields that are in the top_field_ids array
foreach ( $fields->not( 'social' ) as $field ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	if ( in_array( $field->id, $top_field_ids ) ) {
		$top_fields[] = $field;
	} elseif ( in_array( $field->id, $side_field_ids ) ) {
		$side_fields[] = $field;
	} else {
		$bottom_fields[] = $field;
	}
}
//Sort the top fields by the order of the top_field_ids array
usort( $top_fields, function ( $a, $b ) use ( $top_field_ids ) {
	$pos_a = array_search( $a->id, $top_field_ids );
	$pos_b = array_search( $b->id, $top_field_ids );

	return $pos_a - $pos_b;
} );

$bottom_field_ids = [ 37, 4, 26, 6, 7, 5, 30, 10, 20, 36, 39 ];

usort( $bottom_fields, function ( $a, $b ) use ( $bottom_field_ids ) {
	$pos_a = array_search( $a->id, $bottom_field_ids );
	$pos_b = array_search( $b->id, $bottom_field_ids );

	return $pos_a - $pos_b;
} );


?>

<div id="<?php echo esc_attr( $listing_css_id ); ?>" class="<?php echo esc_attr( $listing_css_class ); ?>">

    <section class="single-listing-hero">
        <div class="single-listing-hero-content">

            <div class="hero-video-background">
                <video autoplay loop muted>
					<?php $randomNumber = rand(1, 5); ?>
                    <source src="<?= get_template_directory_uri() . '/img/video-' . $randomNumber . '.mp4' ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
                <div class="hero-video-container">
                    <div class="hero-video-content">
                        <div class="container">
							<?php if ( $images->main || $images->thumbnail ) : ?>
                                <div class="single-listing-image-gallery-wrapper">
                                    <img src="<?= get_template_directory_uri() . '/img/pink-tape.png' ?>" alt="Listing Pin" class="listing-item-pin">
                                    <div class="single-listing-image-gallery <?= $images->extra ? '' : 'only-main' ?>">
										<?php


										// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										$img_id = $images->main ? $images->main->id : $images->thumbnail->id;
										$image  = wp_get_attachment_image_src( $img_id, 'large' )[0];
										?>
                                        <div class="single-listing-main-image" style="background-image: url('<?= $image ?>')"></div>
										<?php
										wpbdp_x_part( 'parts/listing-images' );

										if ( $images->extra && count( $images->extra ) >= 7 ) {
											echo '<button class="see-all-images-btn">' . insertSVG( 'full-screen', '0 0 21 21' ) . '<span>Se alle ' . count( $images->extra ) + 1 . ' billeder</span></button>';
										} else {
											echo '<button class="see-all-images-btn more">' . insertSVG( 'full-screen', '0 0 21 21' ) . ' <span>Forstør</span></button>';
										}
										?>

                                    </div>
                                    <div class="single-listing-primary-details-wrapper">

                                        <div class="single-listing-title-wrapper">
											<?php wpbdp_x_part( 'parts/listing-title' ); ?>
                                        </div>
                                        <table class="single-listing-primary-details-table">

											<?php
											$count = 1;

											foreach ( $top_fields as $field ) {

												if ( ! $field->value ) {
													$field->value = '?';
												}
												if ( $count === 1 || $count === 4 ) {
													echo "<tr>\n";
												}
												$item_class = "field-" . $field->id;
												echo "<td>\n";
												echo '<div class="single-listing-detail-item ' . $item_class . '">';
												echo '<h3 class="single-listing-detail-label">' . $field->label . '</h3>';
												echo '<div class="single-listing-detail-value">' . $field->value . '</div>';
												echo '</div>';
												echo "</td>\n";
												if ( $count === 3 || $count === 6 ) {
													echo "</tr>\n";
												}
												$count ++;
											}

											?>
                                        </table>
                                    </div>
                                </div>
							<?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="bg-video-gradient-fader "></div>
            </div>






        </div>
    </section>
	<?php
	wpbdp_x_part(
		'parts/listing-buttons',
		array(
			'listing_id' => $listing_id,
			'view'       => 'single',
		)
	);
	?>
    <section class="single-listing-content-section">
        <div class="container">
            <div class="single-listing-content">
                <div class="single-listing-column-one">
                    <div class="listing-details cf<?php echo esc_attr( ( $images->main || $images->thumbnail ) ? '' : ' wpbdp-no-thumb' ); ?>">
						<?php


						//$lastItem = array_pop( $bottom_fields );
						// Add the last element to the beginning of the array
						//array_unshift( $bottom_fields, $lastItem );

						foreach ( $bottom_fields as $field ) {
							$item_class = "field-" . $field->id;
                            $value = $field->value ? $field->value : 'Ikke angivet';
							echo '<div class="single-listing-detail-item ' . $item_class . '">';
							echo '<h3 class="single-listing-detail-label">' . $field->label . "</h3>";
							echo '<div class="single-listing-detail-value">' . $value . "</div>";
							echo '</div>';
						}
						wpbdp_x_part( 'parts/listing-socials' );
						?>
                    </div>
                </div>
                <div class="single-listing-column-two">
					<?php

					$firstItem = array_shift( $side_fields );
					// Add the first element to the end of the array
                    array_push( $side_fields, $firstItem );

					foreach ( $side_fields as $field ) {
						$item_class = "field-" . $field->id;
						if ( $field->id === 8 ) {
							$field_value = str_replace( ', ', '', $field->value );
						} else {
							$field_value = $field->value;
						}
						echo '<div class="single-listing-detail-item ' . $item_class . '">';
						echo '<h3 class="single-listing-detail-label">' . $field->label . "</h3>";
						echo '<div class="single-listing-detail-value">' . $field_value . "</div>";
						echo '</div>';
					}
					wpbdp_x_part( 'parts/listing-socials' );
					?>
                </div>
            </div>
        </div>
    </section>
</div>
