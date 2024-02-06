<?php
$listing_query_args = array(
	'post_type'      => 'wpbdp_listing',
	'posts_per_page' => 3,
	/*'meta_query'     => array(
		array(
			'value'   => $user->user_login,
			'compare' => '=',
			'key'     => 'cpr_nr'
		),
	)*/
);
$listings           = new WP_Query( $listing_query_args );
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var videoElement = document.querySelector('.hero-video-background video');
        if (videoElement) {
            videoElement.play();
        }
   });
</script>
<section class="page-hero favorite-listings">
	<div class="section-features">

        <div class="hero-video-background">
            <video autoplay loop muted>
	            <?php $randomNumber = rand(1, 5); ?>
                <source src="<?= get_template_directory_uri() . '/img/video-' . $randomNumber . '.mp4' ?>" type="video/mp4">
                Your browser does not support the video tag.
            </video>
            <div class="hero-video-container">
                <div class="hero-video-content">
                    <div class="container">
                        <h2 class="section-title">Favoritpiger</h2>
                        <ul class="favorite-list equalbox-container ipdk-slider">
				            <?php while ( $listings->have_posts() ) : $listings->the_post();
					            $field = WPBDP_Form_Field::get( 27 ); // 27 = Område field ID

					            if ( $field ) {
						            $area = $field->value( get_the_ID() );
					            } else {
						            $area = 'Ukendt';
					            }
					            $img_lg = wp_get_attachment_image_src( get_post_thumbnail_id( get_the_ID() ), 'large' )[0];  ?>
                                <li class="listing-item favorite">

                                    <div class="listing-item-image-wrapper">
                                        <a href="<?php the_permalink(); ?>" class="listing-item-link">
                                            <figure class="listing-item-thumbnail profil-image" style="background-image:url('<?= $img_lg ?>')">
                                                <div class="polaroid-glass"></div>
                                            </figure>
                                        </a>
                                        <div class="listing-item-actions">
                                            <a href="#link-til-valideret-script" class="button validated-style">Valideret</a>
                                            <a href="#link-til-favorite-script" class="button favorite-style">Fav</a>
                                        </div>
							            <?php $randomNumber = rand(1, 5); ?>
                                    </div>
                                    <img src="<?= get_template_directory_uri() . '/img/pink-tape.png' ?>" alt="Listing tape" class="listing-item-pin">
                                    <div class="listing-details<?php echo esc_attr( $img_lg ? '' : ' wpbdp-no-thumb' ); ?>">
                                        <a href="<?php the_permalink(); ?>" class="listing-title-link equal-box-1 link"><h3 class="excerpt-title favorite"><?php echo mb_strimwidth( strip_tags( get_the_title()), 0, 40, ' ...' );  ?></h3></a>
                                        <div class="omraade-text"><?= $area ?></div>
                                    </div>
                                </li>
				            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="bg-video-gradient-fader "></div>
        </div>





	</div>
</section>