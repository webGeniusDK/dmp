<?php
global $wpbdp_ratings;
if ( ! isset( $i ) ) {
	$i = 0;
}
?>
    <tr class="rating <?php echo esc_attr( $i % 2 == 0 ? 'even' : 'odd' ); ?>" data-id="<?php echo esc_attr( $review->id ); ?>">
        <td class="authoring-info">
			<?php
			$wpbdp_ratings->get_stars(
				array(
					'review'   => $review,
					'readonly' => true,
				)
			);
			?>
            <span class="wpbdp-ratings-stars" data-readonly="readonly" data-value="<?php echo esc_attr( $review->rating ); ?>"></span><br />
            <b>
                <?php if ( $review->user_id ) : ?>
                    <?php the_author_meta( 'display_name', $review->user_id ); ?>
                    <br/>
                    <?php the_author_meta( 'user_email', $review->user_id ); ?>
                <?php else : ?>
                    <?php echo esc_attr( $review->user_name ); ?>
                    <br/>
                    <?php echo ! empty( $review->user_email ) ? esc_html( $review->user_email ) : ''; ?>
                <?php endif; ?>
            </b><br />
            <?php echo esc_html( $review->ip_address ); ?>

            <div class="row-actions edit-actions">
                <a href="#" class="edit"><?php esc_html_e( 'Edit', 'wpbdp-ratings' ); ?></a>
				<a href="#" class="cancel-button"><?php esc_attr_e( 'Cancel', 'wpbdp-ratings' ); ?></a>
                <span class="trash"><a href="#" class="delete"><?php esc_html_e( 'Delete', 'wpbdp-ratings' ); ?></a></span>
            </div>

        </td>
        <td class="comment">
            <div class="submitted-on"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), strtotime( $review->created_on ) ) ); ?></div>
            
            <div class="comment rating-comment">
                <?php echo esc_html( $review->comment ); ?>
            </div>
            <div class="rating-comment-edit comment-edit" style="display: none;">
				<div class="wpbdp-form-field">
					<textarea><?php echo esc_textarea( $review->comment ); ?></textarea>
				</div>
				<input type="button" value="<?php esc_attr_e( 'Save', 'wpbdp-ratings' ); ?>" class="save-button button-primary button" />
            </div>           
        </td>
    </tr>
