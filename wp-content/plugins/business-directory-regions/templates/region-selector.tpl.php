<?php
/**
 * Regions selector filter template
 *
 * @package Regions
 */

?>
<div class="wpbdp-region-selector cf">
    <form action="<?php echo $this->url(); ?>" method="post">
		<input type="hidden" name="redirect" value="<?php echo esc_attr( is_paged() ? get_pagenum_link( 1 ) : '' ); ?>" />
		<input type="hidden" name="origin" value="<?php echo esc_attr( $this->origin_hash() ); ?>" />

		<?php if ( function_exists( 'wpbdp_current_category_id' ) ) : ?>
		<input type="hidden" name="category_id" value="<?php echo esc_attr( wpbdp_current_category_id() ); ?>" />
        <?php endif; ?>

        <p class="legend">
            <?php echo $this->get_current_location(); ?>
            <a href="#" class="js-handler bd-caret" title="<?php esc_attr_e( 'Hide or show', 'wpbdp-regions' ); ?>"><span></span></a>
        </p>

		<div class="wpbdp-region-selector-inner" data-collapsible="true" data-collapsible-default-mode="<?php echo esc_attr( wpbdp_get_option( 'regions-selector-open' ) ? 'open' : 'closed' ); ?>">
            <div class="wpbdp-hide-on-mobile">
            <p>
				<?php esc_html_e( 'Use the fields below to filter listings for a particular country, city, or state.  Start by selecting the top most region, the other fields will be automatically updated to show available locations.', 'wpbdp-regions' ); ?>
            </p>
            <p>
                <?php esc_html_e( 'Use the Clear Filter button if you want to start over.', 'wpbdp-regions' ); ?>
            </p>
            </div>

			<?php foreach ( $fields as $field ) : ?>
                <?php echo $field; ?>
            <?php endforeach ?>

            <div class="form-submit">
                <input type="submit" value="<?php esc_attr_e( 'Clear Filter', 'wpbdp-regions' ); ?>" name="clear-location" class="button" />
				<input type="submit" value="<?php esc_attr_e( 'Set Filter', 'wpbdp-regions' ); ?>" name="set-location" class="button" disabled="disabled" style="display: none;" />
            </div>
        </div>
    </form>
</div>
