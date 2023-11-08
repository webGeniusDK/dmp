<?php
/**
 * The template for displaying all pages
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site may use a
 * different template.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package dmp
 */

get_header();


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
$listings  = new WP_Query( $listing_query_args );

?>

    <section class="favorite-listings">
        <div class="container">
            <h2 class="section-title">Favoritliste</h2>
            <ul class="favorite-list">
                    <?php while ( $listings->have_posts() ) : $listings->the_post(); ?>

                        <li class="favorite-list-item">
                            <figure style="background-image:url(<?=  wp_get_attachment_image_src( get_post_thumbnail_id( get_the_ID() ), 'medium' )[0] ?>)"></figure>
                            <h3 class="listing-title"><?php the_title(); ?></h3>
                            <div class="listing-excerpt">
                                <?php the_excerpt(); ?>
                            </div>
                            <div class="listing-link">
                                <a href="<?php the_permalink(); ?>" class="button">Læs mere</a>
                            </div>
                        </li>

                    <?php endwhile; ?>
            </ul>
			<?php wp_reset_query(); ?>
        </div>
    </section>

    <main id="primary" class="site-main">
		<?php
		while ( have_posts() ) :
			the_post();

			get_template_part( 'template-parts/content', 'page' );

			// If comments are open or we have at least one comment, load up the comment template.
			if ( comments_open() || get_comments_number() ) :
				comments_template();
			endif;

		endwhile; // End of the loop.
		?>

    </main><!-- #main -->

    </div><!-- #page -->
<?php

get_footer();
