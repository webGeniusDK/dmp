<div class="wpbdp-star-group">
	<?php
	for ( $i = 1; $i <= 5; $i++ ) {
		$class = 'bd-star-rating';
		$half  = '';
		if ( $i <= $selected ) {
			$class .= ' bd-star-rating-on';
		}
		if ( $readonly ) {
			$class .= ' bd-star-rating-readonly';
			if ( $i > $selected && $i === absint( round( $selected ) ) ) {
				$class .= ' bd-star-rating-half bd-star-rating-on';
				$half = '<defs><linearGradient id="bd_half_grad">
<stop offset="50%" stop-color="currentColor" />
<stop offset="50%" stop-color="inherit" style="stop-opacity:0.6 !important" />
</linearGradient></defs>';
			}
			?>
			<span class="<?php echo esc_attr( $class ); ?>">
				<svg width="25" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512">
					<?php echo $half; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<path fill="currentColor" d="M259 18l-65 132-146 22c-26 3-37 36-18 54l106 103-25 146c-5 26 23 46 46 33l131-68 131 68c23 13 51-7 46-33l-25-146 106-103c19-18 8-51-18-54l-146-22-65-132a32 32 0 00-58 0z"/></svg>
			</span>
			<?php
			continue;
		}
		?>
        <input type="radio" id="score_<?php echo esc_attr( $i ); ?>" name="<?php echo isset( $field_id ) ? 'listingfields[' . esc_attr( $field_id ) . ']' : 'score'; ?>" value="<?php echo esc_attr( $i ); ?>"
            <?php checked( $selected, $i ); ?> />
		<label for="score_<?php echo esc_attr( $i ); ?>" class="<?php echo esc_attr( $class ); ?>">
			<svg width="25" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path fill="currentColor" d="M259 18l-65 132-146 22c-26 3-37 36-18 54l106 103-25 146c-5 26 23 46 46 33l131-68 131 68c23 13 51-7 46-33l-25-146 106-103c19-18 8-51-18-54l-146-22-65-132a32 32 0 00-58 0z"/></svg>
		</label>
	<?php } ?>
    <?php if ( isset( $field_id ) ) { ?>
        <span class="rating-stars-suffix"><?php esc_attr_e( '& up', 'wpbdp-ratings' ); ?></span>
    <?php } ?>
</div>
<div style="clear:both"></div>
