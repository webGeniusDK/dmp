<?php

/* add new tab called "Annoncer" */

add_filter( 'um_account_page_default_tabs_hook', 'my_custom_tab_in_um', 100 );
function my_custom_tab_in_um( $tabs ) {
	$tabs[800]['Annoncer']['icon']   = 'um-icon-android-attach';
	$tabs[800]['Annoncer']['title']  = 'Annoncer';
	$tabs[800]['Annoncer']['custom'] = true;

	return $tabs;
}

/* make our new tab hookable */

add_action( 'um_account_tab__Annoncer', 'um_account_tab__Annoncer' );
function um_account_tab__Annoncer( $info ) {
	global $ultimatemember;
	extract( $info );

	$output = $ultimatemember->account->get_tab_output( 'Annoncer' );
	if ( $output ) {
		echo $output;
	}
}

/* Finally we add some content in the tab */

add_filter( 'um_account_content_hook_Annoncer', 'um_account_content_hook_Annoncer' );
function um_account_content_hook_Annoncer( $output ) {
	ob_start();
	?>

    <div class="um-field">
		<?php
		$user_id = get_current_user_id(); // Get current user ID
		if ( $user_id ) { // Check if user is logged in
			$args = array(
				'post_type' => 'wpbdp_listing', // Replace with the actual custom post type of WPBDP listings
				'author'    => $user_id,
				// Add any other arguments you need for your query
			);

			$query = new WP_Query( $args );
		}
		?>
        <div id="um-account-tab-manage-listings" class="um-account-manage-listings-tab">
			<?php if ( $query->have_posts() ) : ?>
            <div class="manage-listings-list">
				<?php
				/** @phpstan-ignore-next-line */
				while ( $query->have_posts() ) {
					$query->the_post();
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo WPBDP_Listing_Display_Helper::excerpt();
				}

				/** @phpstan-ignore-next-line */
				wpbdp_x_part(
					'parts/pagination',
					array(
						'query' => $query,
					)
				); ?>
				<?php else : ?>

                    <p><?php esc_html_e( 'You do not currently have any listings in the directory.', 'business-directory-plugin' ); ?></p>
                    <div class="manage-listings-actions">
                        <a class="button" href="/intime-piger/?wpbdp_view=submit_listing">Tilføj annonce</a>
                        <a class="button secondary" href="/">Gå til annonceoversigten</a>
                    </div>




				<?php endif; ?>
            </div>
        </div>

    </div>

	<?php

	$output .= ob_get_contents();
	ob_end_clean();

	return $output;
}