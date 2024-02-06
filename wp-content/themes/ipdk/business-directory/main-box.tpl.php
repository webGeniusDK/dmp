<?php
/**
 * BD Main Box
 *
 * @package BDP/Templates/Main Box
 */

?>
<div id="wpbdp-main-box" class="wpbdp-main-box"
     data-breakpoints='{"tiny": [0,320], "small": [320,480], "medium": [480,768], "large": [768,999999]}'
     data-breakpoints-class-prefix="wpbdp-main-box">
    <div class="container">
		<?php if ( wpbdp_get_option( 'show-search-listings' ) || $in_shortcode ) : ?>
            <div class="main-fields box-row cols-2">
                <form action="<?php echo esc_url( $search_url ); ?>" method="get">
                    <input type="hidden" name="wpbdp_view" value="search"/>
					<?php echo $hidden_fields; ?>
					<?php if ( ! wpbdp_rewrite_on() ) : ?>
                        <input type="hidden" name="page_id" value="<?php echo wpbdp_get_page_id(); ?>"/>
					<?php endif; ?>
                    <div class="box-col search-fields">
                        <div class="box-row cols-<?php echo $no_cols; ?>">
                            <div class="box-col main-input">
                                <div class="text-field">
                        <span class="text-field-wrapper">
                           <input type="text" id="wpbdp-main-box-keyword-field"
                                  title="<?php esc_attr_e( 'Quick search keywords', 'ipdk' ) ?>" class="keywords-field"
                                  name="kw" placeholder=" "/>
                            <label for="wpbdp-main-box-keyword-field"><?php esc_attr_e( 'Search Listings', 'business-directory-plugin' ); ?></label>
                        </span>
                                </div>
                            </div>
							<?php echo $extra_fields; ?>
                        </div>
                    </div>
                    <div class="box-col submit-btn">
                        <input class="button large" type="submit"
                               value="<?php echo esc_attr_x( 'Find Listings', 'main box', 'business-directory-plugin' ); ?>"/><br/>
                    </div>
                </form>
            </div>
        <div class="search-links">
            <a class="advanced-search-link" href="<?php echo esc_url( $search_url ); ?>"><?php echo esc_attr_x( 'Advanced Search', 'main box', 'business-directory-plugin' ); ?></a>

            <div class="separator"></div>
            <?php $main_links = wpbdp_main_links( $buttons ); ?>
            <?php if ( $main_links ) : // have added ! to remove these buttons ?>
                <div class="search-actions"><?php echo $main_links; ?></div>
            <?php endif; ?>
            </div>
		<?php endif; ?>
    </div>
</div>
