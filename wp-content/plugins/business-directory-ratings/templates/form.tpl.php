<?php
global $wpbdp_ratings;
?>
<div class="review-form" id="rate-listing-form">

<div class="review-form-header">
    <h4><?php esc_html_e( 'Post your review', 'wpbdp-ratings' ); ?></h4>
</div>

<div class="form">
    <?php if ( $validation_errors ) : ?>
        <ul class="validation-errors wpbdp-msg wpbdp-error">
            <?php foreach ( $validation_errors as $error_msg ) : ?>
                <li><?php echo esc_html( $error_msg ); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
        <form action="#rate-listing-form" method="POST" class="wpbdp-grid">
            <input type="hidden" name="listing_id" value="<?php echo esc_attr( $listing_id ); ?>" />

            <div class="field wpbdp-form-field">
                <label><?php esc_html_e( 'Rating', 'wpbdp-ratings' ); ?></label>
				<?php $wpbdp_ratings->get_stars( array( 'review' => isset( $review ) ? $review : false ) ); ?>
            </div>

            <?php if ( ! is_user_logged_in() ) : ?>
            <div class="field wpbdp-form-field">
                <label><?php esc_html_e( 'Name', 'wpbdp-ratings' ); ?>
                <input type="text" name="user_name" size="30" value="<?php echo esc_attr( wpbdp_get_var( array( 'param' => 'user_name' ), 'post' ) ); ?>" /></label>
            </div>
				<?php if ( $wpbdp_ratings->require_visitor_email() ) : ?>
                <div class="field wpbdp-form-field">
                    <label><?php esc_html_e( 'Email', 'wpbdp-ratings' ); ?>
                    <input type="text" name="user_email" size="30" value="<?php echo esc_attr( wpbdp_get_var( array( 'param' => 'user_emil' ), 'post' ) ); ?>" /></label>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( wpbdp_get_option( 'ratings-comments' ) != 'disabled' ) : ?>
            <div class="field wpbdp-form-field">
                <textarea name="comment" cols="50" rows="3" placeholder="<?php esc_attr_e( 'Your review.', 'wpbdp-ratings' ); ?>"
					><?php // phpcs:ignore Squiz.PHP.EmbeddedPhp
					echo esc_textarea(
						wpbdp_get_var(
							array(
								'param'    => 'comment',
								'sanitize' => 'wp_kses_post',
							),
							'post'
						)
					); // phpcs:ignore Generic.WhiteSpace.ScopeIndent.Incorrect
					// phpcs:ignore Squiz.PHP.EmbeddedPhp.ContentAfterEnd
						?></textarea>
            </div>
				<?php if ( wpbdp_get_option( 'ratings-allow-html' ) ) : ?>
					<p class="allowed-tags">
					<?php
					printf(
						/* translators: %s List of allowed tags */
						esc_html__( 'You may use these HTML tags: %s.', 'wpbdp-ratings' ),
						'<span>' . esc_html( allowed_tags() ) . '</span>'
					);
					?>
				</p>
				<?php endif; ?>
            <?php endif; ?>

            <div class="submit">
                <input type="submit" class="submit" name="rate_listing" value="<?php esc_html_e( 'Post your review', 'wpbdp-ratings' ); ?>" />
            </div>
        </form>
</div>

</div>
