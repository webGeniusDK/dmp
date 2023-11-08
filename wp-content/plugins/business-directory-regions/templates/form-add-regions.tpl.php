<div id="wpbdp-regions-tabs">

    <div id="wpbdp-add-multiple-regions">
        <form class="validate" action="" method="post" id="addtag">
			<input type="hidden" value="<?php echo esc_attr( wpbdp_regions_taxonomy() ); ?>" name="taxonomy">
			<input type="hidden" value="<?php echo esc_attr( WPBDP_POST_TYPE ); ?>" name="post_type">
            <?php wp_nonce_field( 'add-multiple-regions', '_wpnonce' ); ?>

            <div class="form-field form-required">
				<label for="tag-name"><?php esc_html_e( 'Name', 'wpbdp-regions' ); ?></label>
                <textarea aria-required="true" id="tag-name" name="tag-name" style="min-height:100px"></textarea>
				<p><?php esc_html_e( 'The name of the Regions, one region per line.', 'wpbdp-regions' ); ?></p>
            </div>

            <?php $this->_insert_autocomplete_field(); ?>

			<p class="submit">
				<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Add Regions', 'wpbdp-regions' ); ?>" class="button" name="submit">
			</p>
        </form>
    </div>
	<div class="clear"></div>
</div>
