<?php
/**
 * Listing Contact Form template
 *
 * @package Ratings/Templates/Rate Listing Form
 */

global $wpbdp_ratings;

?>
<div class="wpbdp-ratings-reviews" id="ratings">
    <h3><?php esc_html_e( 'Ratings', 'wpbdp-ratings' ); ?></h3>
    <p class="no-reviews-message" style="<?php echo esc_attr( $ratings ? 'display: none;' : '' ); ?>">
		<?php esc_html_e( 'There are no reviews yet.', 'wpbdp-ratings' ); ?>
	</p>
    <?php if ( $ratings ) : ?>
    <div class="listing-ratings">
        <?php foreach ( $ratings as $i => $rating ) : ?>
        <div class="rating <?php echo esc_attr( $i % 2 == 0 ? 'odd' : 'even' ); ?>" data-id="<?php echo esc_attr( $rating->id ); ?>" data-listing-id="<?php echo esc_attr( $listing_id ); ?>">
            <div class="edit-actions">
                <?php if ( ( $rating->user_id > 0 && $rating->user_id == get_current_user_id() ) || current_user_can( 'administrator' ) ) : ?>
                <a href="#" class="edit">Edit</a>
				<a href="#" class="cancel-button"><?php esc_html_e( 'Cancel', 'wpbdp-ratings' ); ?></a>
				<a href="#" class="delete">Delete</a>
                <?php endif; ?>
            </div>

            <?php
			$wpbdp_ratings->get_stars(
				array(
					'review'   => $rating,
					'readonly' => true,
				)
			);

			$allow_html = wpbdp_get_option( 'ratings-allow-html' );
            ?>
            <div class="rating-comment">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo wpautop( wp_kses( $rating->comment, $allow_html ? 'post' : 'strip' ) );
				?>
            </div>
            <?php if ( ( $rating->user_id > 0 && $rating->user_id == get_current_user_id() ) || current_user_can( 'administrator' ) ) : ?>
            <div class="rating-comment-edit" style="display: none;">
				<div class="wpbdp-form-field">
					<textarea><?php echo esc_textarea( $rating->comment ); ?></textarea>
				</div>
				<input type="button" value="<?php esc_attr_e( 'Save', 'wpbdp-ratings' ); ?>" class="submit save-button" />
            </div>
            <?php endif; ?>

            <?php
            $author = ( $rating->user_id == 0 ) ? trim( $rating->user_name ) : trim( get_the_author_meta( 'display_name', $rating->user_id ) );
            ?>
            <div class="rating-authoring-info">
                <?php
                printf(
                    '<span class="author">%s</span>  |  <span class="date" content="%s">%s</span>',
                    esc_html( $author ? $author : __( 'Anonymous', 'wpbdp-ratings' ) ),
					esc_html( $rating->created_on ),
					esc_html( date_i18n( get_option( 'date_format' ), strtotime( $rating->created_on ) ) )
                );
                ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php
	if ( $review_form ) :
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $review_form;
	else :
		if ( $success ) :
			?>
        <div class="wpbdp-msg">
            <?php if ( wpbdp_get_option( 'ratings-require-approval' ) ) : ?>
                <?php esc_html_e( 'Your review has been saved and is waiting for approval.', 'wpbdp-ratings' ); ?>
            <?php else : ?>
                <?php esc_html_e( 'Your review has been saved.', 'wpbdp-ratings' ); ?>
            <?php endif; ?>
        </div>
        <?php elseif ( $reason !== 'listing-owner' ) : ?>
            <div class="wpbdp-msg">
			<?php
			if ( $reason === 'already-rated' ) {
				esc_html_e( 'You have already rated this listing.', 'wpbdp-ratings' );
			} else {
				printf(
					/* translators: %1$s start link HTML, %2$s end link */
					esc_html__( 'Please %1$slogin%2$s to leave a review.', 'wpbdp-ratings' ),
					'<a href="' . esc_url( $wpbdp_ratings->login_url() ) . '">',
					'</a>'
				);
			}
			?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>
