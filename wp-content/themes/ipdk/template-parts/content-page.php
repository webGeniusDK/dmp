<?php
/**
 * Template part for displaying page content in page.php
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package ipdk
 */
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<?php the_content(); ?>
		<?php if ( get_edit_post_link() ) : ?>
            <footer class="entry-footer">
				<?php
				edit_post_link(
					sprintf(
						wp_kses(
						/* translators: %s: Name of current post. Only visible to screen readers */
							__( 'Edit <span class="screen-reader-text">%s</span>', 'ipdk' ),
							array(
								'span' => array(
									'class' => array(),
								),
							)
						),
						wp_kses_post( get_the_title() )
					),
					'<span class="edit-link">',
					'</span>'
				);
				?>
            </footer><!-- .entry-footer -->
		<?php endif; ?>
</article><!-- #post-<?php the_ID(); ?> -->
