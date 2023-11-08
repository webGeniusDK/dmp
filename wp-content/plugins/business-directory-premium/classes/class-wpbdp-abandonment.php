<?php
/**
 * ABC Filtering Links
 */

/**
 * This class provides all the functionality for the ABC filtering bar.
 *
 * @since 5.0
 */
class WPBDP_Abandonment {

	private $old_setting = 'payment-abandonment';

	private $setting_name = 'abandoned-payment';

	private $turned_on;

	public function __construct() {
		add_action( 'wpbdp_register_settings', array( &$this, 'register_settings' ), 20 );
		add_action( 'wpbdp_modules_init', array( &$this, 'load_hooks' ) );
	}

	public function load_hooks() {
		$this->turned_on = wpbdp_get_option( $this->setting_name );
		if ( ! $this->turned_on ) {
			return;
		}

		$this->setup_email();

		// Set up filters for payments and listings.
		add_filter( 'WPBDP_Listing::get_payment_status', array( &$this, 'abandonment_status' ), 10, 2 );
		add_filter( 'wpbdp_admin_directory_views', array( &$this, 'abandonment_admin_views' ), 10, 2 );
		add_filter( 'wpbdp_admin_directory_filter', array( &$this, 'abandonment_admin_filter' ), 10, 2 );
	}

	public function register_settings( $settings ) {
		$defaults = $this->get_default_settings();

		// Remove if the settings exists so we can include them from here.
		$settings->deregister_setting( $this->old_setting );
		$settings->deregister_setting( $this->old_setting . '-threshold' );
		$settings->deregister_setting( 'email-templates-payment-abandoned' );

		wpbdp_register_settings_group( 'abandon', __( 'Abandonment', 'wpbdp-pro' ), 'payment/main' );

		wpbdp_register_setting(
			array(
				'id'           => $this->setting_name,
				'type'         => 'toggle',
				'name'         => __( 'Ask users to come back for abandoned payments?', 'wpbdp-pro' ),
				'desc'         => __( 'Remind users to come back and complete an unfinished payment for a new listing.', 'wpbdp-pro' ),
				'default'      => $defaults['abandon'],
				'group'        => 'abandon',
			)
		);

		$link = admin_url( 'admin.php?page=wpbdp_settings&tab=email&subtab=email_templates#wpbdp-settings-email-templates-' . $this->setting_name );
		wpbdp_register_setting(
			array(
				'id'           => $this->setting_name . '-threshold',
				'type'         => 'number',
				'name'         => __( 'Listing abandonment threshold (hours)', 'wpbdp-pro' ),
				'desc'         => sprintf(
					/* translators: %1$s start link html, %2$s end link */
					__( 'Listings with pending payments are marked as abandoned after this time. You can also %1$scustomize the e-mail%2$s.', 'wpbdp-pro' ),
					'<a href="' . esc_url( $link ) . '">',
					'</a>'
				),
				'default'      => $defaults['threshold'],
				'min'          => 0,
				'step'         => 1,
				'group'        => 'abandon',
				'requirements' => array( $this->setting_name ),
			)
		);

		wpbdp_register_setting(
			array(
				'id'           => 'email-templates-' . $this->setting_name,
				'type'         => 'email_template',
				'name'         => __( 'Payment abandoned reminder message', 'wpbdp-pro' ),
				'desc'         => $this->get_email_instructions(),
				'default'      => array(
					'subject' => $defaults['subject'],
					'body'    => $defaults['email'],
				),
				'placeholders' => array(
					'listing' => __( 'Listing title', 'wpbdp-pro' ),
					'link'    => __( 'Checkout URL link', 'wpbdp-pro' ),
				),
				'group'        => 'email_templates',
			)
		);
	}

	/**
	 * Set Abandonment status.
	 *
	 * @param string $status The current listing status.
	 * @param int $listing_id The listing id.
	 *
	 * @since 5.4
	 *
	 * @return string
	 */
	public function abandonment_status( $status, $listing_id ) {
		// For now, we only consider abandonment if it involves listings with pending INITIAL payments.
		if ( 'pending' !== $status || ! $listing_id ) {
			return $status;
		}

		$last_pending = WPBDP_Payment::objects()->filter(
			array(
				'listing_id' => $listing_id,
				'status'     => 'pending',
			)
		)->order_by( '-created_at' )->get();

		if ( ! $last_pending || 'initial' !== $last_pending->payment_type ) {
			return $status;
		}

		$threshold     = $this->get_payment_abandonment_threshhold();
		$hours_elapsed = ( current_time( 'timestamp' ) - strtotime( $last_pending['created_at'] ) ) / ( 60 * 60 );

		if ( $hours_elapsed <= 0 ) {
			return $status;
		}

		if ( $hours_elapsed >= ( 2 * $threshold ) ) {
			return 'payment-abandoned';
		} elseif ( $hours_elapsed >= $threshold ) {
			return 'pending-abandonment';
		}

		return $status;
	}

	/**
	 * Add Abandonment view filters in the admin listings view.
	 *
	 * @param array $views The current page views.
	 * @param string $post_statuses The post statuses comma separated.
	 *
	 * @since 5.4
	 *
	 * @return array
	 */
	public function abandonment_admin_views( $views, $post_statuses ) {
		global $wpdb;
		$params = $this->get_admin_view_count_params();

		$pending_query = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT COUNT(*) FROM {$wpdb->prefix}wpbdp_payments ps LEFT JOIN {$wpdb->posts} p ON p.ID = ps.listing_id WHERE ps.created_at > %s AND ps.created_at <= %s AND ps.status = %s AND ps.payment_type = %s AND p.post_status IN ({$post_statuses})",
			$params['within_abandonment'],
			$params['within_pending'],
			'pending',
			'initial'
		);

		$abandoned_query = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT COUNT(*) FROM {$wpdb->prefix}wpbdp_payments ps LEFT JOIN {$wpdb->posts} p ON p.ID = ps.listing_id WHERE ps.created_at <= %s AND ps.status = %s AND ps.payment_type = %s AND p.post_status IN ({$post_statuses})",
			$params['within_abandonment'],
			'pending',
			'initial'
		);

		$count_pending = WPBDP_Utils::check_cache(
			array(
				'cache_key' => 'count_pending_' . wp_json_encode( $params ),
				'group'     => 'wpbdp_payments',
				'query'     => $pending_query,
				'type'      => 'get_var',
			)
		);

		$count_abandoned = WPBDP_Utils::check_cache(
			array(
				'cache_key' => 'count_abandoned_' . wp_json_encode( $params ),
				'group'     => 'wpbdp_payments',
				'query'     => $abandoned_query,
				'type'      => 'get_var',
			)
		);

		$filter = wpbdp_get_var( array( 'param' => 'wpbdmfilter' ), 'request' );
		$url    = add_query_arg( 'wpbdmfilter', $filter, remove_query_arg( 'listing_status' ) );

		$views['pending-abandonment'] = sprintf(
			'<a href="%s" class="%s">%s</a> <span class="count">(%s)</span></a>',
			esc_url( add_query_arg( 'wpbdmfilter', 'pending-abandonment', $url ) ),
			'pending-abandonment' === $filter ? 'current' : '',
			esc_html__( 'Pending Abandonment', 'wpbdp-pro' ),
			esc_html( number_format_i18n( $count_pending ) )
		);
		$views['abandoned'] = sprintf(
			'<a href="%s" class="%s">%s</a> <span class="count">(%s)</span></a>',
			esc_url( add_query_arg( 'wpbdmfilter', 'abandoned', $url ) ),
			'abandoned' === $filter ? 'current' : '',
			esc_html__( 'Abandoned', 'wpbdp-pro' ),
			esc_html( number_format_i18n( $count_abandoned ) )
		);

		return $views;
	}

	/**
	 * Abandonment process different filters.
	 *
	 * @param array $pieces The query param filters.
	 * @param string $filter The current page filter.
	 *
	 * @since 5.4
	 *
	 * @return array
	 */
	public function abandonment_admin_filter( $pieces, $filter = '' ) {
		if ( ! in_array( $filter, array( 'abandoned', 'pending-abandonment' ), true ) ) {
			return $pieces;
		}

		global $wpdb;

		$params = $this->get_admin_view_count_params();

		$pieces['join'] .= " LEFT JOIN {$wpdb->prefix}wpbdp_payments ps ON {$wpdb->posts}.ID = ps.listing_id";
		$pieces['where'] .= $wpdb->prepare( ' AND ps.payment_type = %s AND ps.status = %s ', 'initial', 'pending' );

		switch ( $filter ) {
			case 'abandoned':
				$pieces['where'] .= $wpdb->prepare( ' AND ps.created_at <= %s ', $params['within_abandonment'] );
				break;

			case 'pending-abandonment':
				$pieces['where'] .= $wpdb->prepare( ' AND ps.created_at > %s AND ps.created_at <= %s ', $params['within_abandonment'], $params['within_pending'] );
				break;
		}

		return $pieces;
	}

	private function get_email_instructions() {
		$desc = __( 'Sent some time after a pending payment is abandoned by users.', 'wpbdp-pro' );
		if ( ! $this->turned_on ) {
			$desc .= '<br/>' . sprintf(
				/* translators: %1$s start link html, %2$s end link */
				__( 'This email will not be sent until %1$sabandonment is turned on%2$s.' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=wpbdp_settings&tab=payment' ) ) . '">',
				'</a>'
			);
		}
		return $desc;
	}

	/**
	 * If the old setting was turned on, migrate the settings over to the new ones.
	 */
	private function get_default_settings() {
		$email_content = 'Hi there,

We noticed that you tried submitting a listing on [site-link] but didn\'t finish
the process.  If you want to complete the payment and get your listing
included, just click here to continue:

[link]

If you have any issues, please contact us directly by hitting reply to this
email!

Thanks,
- The Administrator of [site-title]';

		$defaults = array(
			'abandon'   => false,
			'threshold' => '24',
			'email'     => $email_content,
			'subject'   => '[[site-title]] Pending payment for "[listing]"',
		);

		$this->merge_old_settings( $defaults );

		return $defaults;
	}

	/**
	 * Since the old settings are no longer used, merge them in as default values.
	 */
	private function merge_old_settings( &$defaults ) {
		$old_setting = wpbdp_get_option( $this->old_setting );
		if ( ! $old_setting ) {
			return;
		}

		$defaults['abandon'] = true;

		// If the old setting is on and the current is off, merge them.
		$defaults['threshold'] = wpbdp_get_option( $this->old_setting . '-threshold' );

		$old_email = wpbdp_get_option( 'email-templates-payment-abandoned' );
		if ( is_array( $old_email ) && isset( $old_email['body'] ) ) {
			$defaults['email']   = $old_email['body'];
			$defaults['subject'] = $old_email['subject'];
		}

		// Now turn off the old setting so it doesn't get used in the future.
		wpbdp_set_option( $this->old_setting, false );

		// Save the defaults as settings so we don't lose them.
		wpbdp_set_option( $this->setting_name, true );
		wpbdp_set_option( $this->setting_name . '-threshold', $defaults['threshold'] );
		wpbdp_set_option( 'email-templates-' . $this->setting_name, $old_email );
	}

	private function setup_email() {
		$abandoned_payment_notification = new WPBDP_Abandonment_Email( wpbdp()->settings );
		add_action( 'wpbdp_hourly_events', array( $abandoned_payment_notification, 'send_abandoned_payment_notifications' ) );
	}

	/**
	 * Get the payment abandonment threshold from the settings.
	 *
	 * @since 5.4
	 *
	 * @return int
	 */
	private function get_payment_abandonment_threshhold() {
		return max( 1, absint( wpbdp_get_option( $this->setting_name . '-threshold' ) ) );
	}

	/**
	 * Get the admin view count parameters based on settings.
	 * This gets the parameters that the threshold queries will be based on.
	 *
	 * @since 5.4
	 *
	 * @return array
	 */
	private function get_admin_view_count_params() {
		$threshold = $this->get_payment_abandonment_threshhold();
		$now       = current_time( 'timestamp' );

		$within_pending     = wpbdp_format_time( strtotime( sprintf( '-%d hours', $threshold ), $now ), 'mysql' );
		$within_abandonment = wpbdp_format_time( strtotime( sprintf( '-%d hours', $threshold * 2 ), $now ), 'mysql' );
		return compact( 'within_pending', 'within_abandonment' );
	}
}
