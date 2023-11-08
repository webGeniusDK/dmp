<?php

class WPBDP_Ratings_Settings {

    public function register_settings( $api ) {
        wpbdp_register_settings_group( 'ratings', __( 'Ratings', 'wpbdp-ratings' ), 'modules' );

        wpbdp_register_settings_group( 'ratings-general', __( 'General Settings', 'wpbdp-ratings' ), 'ratings' );

        wpbdp_register_setting(
			array(
				'id' => 'ratings-min-ratings',
				'name' => __( 'Ratings threshold', 'wpbdp-ratings' ),
				'type' => 'text',
				'default' => '0',
				'desc' => __( 'Minimum number of reviews before ratings are displayed on a listing', 'wpbdp-ratings' ),
				'group' => 'ratings-general',
			)
		);
        wpbdp_register_setting(
			array(
				'id' => 'ratings-allow-unregistered',
				'name' => __( 'Allow unregistered users to post reviews?', 'wpbdp-ratings' ),
				'type' => 'checkbox',
				'default' => false,
				'group' => 'ratings-general',
			)
		);
        wpbdp_register_setting(
			array(
				'id'      => 'ratings-allow-html',
				'name'    => __( 'Allow (some) HTML in ratings comments?', 'wpbdp-ratings' ),
				'type'    => 'checkbox',
				'default' => false,
				'group'   => 'ratings-general',
			)
		);
        wpbdp_register_setting(
			array(
				'id' => 'ratings-comments',
				'name' => __( 'Rating comments', 'wpbdp-ratings' ),
				'type' => 'select',
				'default' => 'required',
				'desc' => __( 'Decide whether rating comments should be required, optional or not used at all.', 'wpbdp-ratings' ),
				'options' => array(
					'required' => __( 'Required', 'wpbdp-ratings' ),
					'optional' => __( 'Optional', 'wpbdp-ratings' ),
					'disabled' => __( 'Disabled', 'wpbdp-ratings' ),
				),
				'group' => 'ratings-general',
        	)
		);

        wpbdp_register_setting(
			array(
				'id'        => 'ratings-require-approval',
				'name'      => __( 'Admin must approve reviews?', 'wpbdp-ratings' ),
				'type'      => 'checkbox',
				'default'   => false,
				'on_update' => array( $this, 'approval_settting_changed' ),
				'group'     => 'ratings-general',
			)
		);

        wpbdp_register_settings_group( 'ratings-email-settings', __( 'Email Settings', 'wpbdp-ratings' ), 'ratings' );
        wpbdp_register_setting(
			array(
				'id' => 'ratings-notify-owner',
				'name' => __( 'Notify listing owner of new ratings?', 'wpbdp-ratings' ),
				'type' => 'checkbox',
				'default' => false,
				'group' => 'ratings-email-settings',
			)
		);
        wpbdp_register_setting(
			array(
				'id'      => 'ratings-notify-admin',
				'name'    => __( 'Notify site admin of approved/submitted ratings?', 'wpbdp-ratings' ),
				'type'    => 'checkbox',
				'default' => false,
				'group'   => 'ratings-email-settings',
        	)
		);

        $this->register_email_settings( $api );
    }

    private function register_email_settings( $api ) {
        // ratings-notification-email-template setting replaces the old ratings-notification-email setting.
        $template = get_option( WPBDP_Settings::PREFIX . 'ratings-notification-email' );

		wpbdp_register_setting(
			array(
				'id'      => 'ratings-notification-email-template',
				'name'    => _x( 'New rating posted email message', 'admin settings', 'wpbdp-ratings' ),
				'type'    => 'email_template',
				'default' => array(
					'subject' => _x( '[[site-title]] New rating posted', 'email subject', 'wpbdp-ratings' ),
					'body' => $template ? $template : __(
						'A new rating has been posted to the listing [listing]. The rating details are below.

Posted on: [date]
Posted by: [rating_author]
Rating: [rating_rating]
Comments: [rating_comment]
',
						'wpbdp-ratings'
					),
				),
				'desc' => _x( 'Sent when a new rating has been posted and already visible in the listing.', 'settings', 'wpbdp-ratings' ),
				'placeholders' => array(
					'listing'        => _x( 'Listing\'s name (with link)', 'settings', 'wpbdp-ratings' ),
					'rating_author'  => _x( 'The name of author of the rating, or the IP address used when it was posted', 'settings', 'wpbdp-ratings' ),
					'rating_comment' => _x( 'The comment included with the rating', 'settings', 'wpbdp-ratings' ),
					'rating_rating'  => _x( 'The numeric rating', 'settings', 'wpbdp-ratings' ),
					'date'           => _x( 'The date the rating was posted', 'settings', 'wpbdp-ratings' ),
				),
				'group' => 'ratings-email-settings',
			)
		);

        wpbdp_register_setting(
			array(
				'id'      => 'ratings-pending-approval-notification-email-template',
				'name'    => __( 'Rating pending approval email message', 'wpbdp-ratings' ),
				'type'    => 'email_template',
				'default' => array(
					'subject' => _x( '[[site-title]] Rating pending approval', 'email subject', 'wpbdp-ratings' ),
					'body' => __(
						'Dear admin,

A new rating has been submitted to the listing [listing] and is pending approval. You can see the listing and take care of approving it or rejecting it by visiting [url].
Rating details are below:

Posted on: [date]
Posted by: [rating_author]
Rating: [rating_rating]
Comments: [rating_comment]
',
						'wpbdp-ratings'
					),
				),
				'desc' => _x( 'Sent when a new rating has been posted and is pending approval.', 'settings', 'wpbdp-ratings' ),
				'placeholders' => array(
					'listing'        => _x( 'Listing\'s name (with link)', 'settings', 'wpbdp-ratings' ),
					'rating_author'  => _x( 'The name of author of the rating, or the IP address used when it was posted', 'settings', 'wpbdp-ratings' ),
					'rating_comment' => _x( 'The comment included with the rating', 'settings', 'wpbdp-ratings' ),
					'rating_rating'  => _x( 'The numeric rating', 'settings', 'wpbdp-ratings' ),
					'date'           => _x( 'The date the rating was posted', 'settings', 'wpbdp-ratings' ),
					'url'            => _x( 'A link to the detailed view of the rating in the admin dashboard', 'settings', 'wpbdp-ratings' ),
				),
				'group' => 'ratings-email-settings',
        	)
		);
    }

    public function approval_settting_changed( $setting, $newvalue, $oldvalue = null ) {
        global $wpdb;

        if ( ! $newvalue ) {
            $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wpbdp_ratings SET approved = %d", 1 ) );
        }
    }
}
