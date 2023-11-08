<?php global $wpbdp_ratings; ?>

<div id="wpbdp-ratings-admin-post-review">

    <p><a href="#" class="add-review-link"><?php esc_html_e( 'Add Review', 'wpbdp-ratings' ); ?></a></p>

    <div class="form" style="display: none;">
        <input type="hidden" name="wpbdp_ratings_rating[listing_id]" value="<?php echo esc_attr( $listing_id ); ?>" />

        <div class="field">
            <label><?php esc_html_e( 'Author', 'wpbdp-ratings' ); ?></label>
            <input type="text" name="wpbdp_ratings_rating[user_name]" size="30" value="" />
            <span class="description"><?php esc_html_e( 'WordPress username or arbitrary username.', 'wpbdp-ratings' ); ?></span>
        </div>

        <div class="field">
            <label><?php esc_html_e( 'Rating', 'wpbdp-ratings' ); ?></label>
			<?php $wpbdp_ratings->get_stars( array( 'review' => false ) ); ?>
        </div>

        <?php if ( wpbdp_get_option( 'ratings-comments' ) !== 'disabled' ) : ?>
        <div class="field wpbdp-form-field">
            <textarea name="wpbdp_ratings_rating[comment]" cols="50" rows="3"></textarea>
        </div>
        <?php endif; ?>

        <p>
            <a href="#" class="button wpbdp-ratings-add-btn button-primary alignright save-button"><?php esc_html_e( 'Add Review', 'wpbdp-ratings' ); ?></a>
            <a href="#" class="button wpbdp-ratings-cancel-btn alignleft"><?php esc_html_e( 'Cancel', 'wpbdp-ratings' ); ?></a>
        </p>

        <br />
    </div>

</div>
