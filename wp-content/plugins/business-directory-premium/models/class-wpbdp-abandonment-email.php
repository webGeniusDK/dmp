<?php
/**
 * Notification send to users when they forget to complete a payment operation
 * for a listing.
 */

/**
 * Sends abandoned payment notifications to users.
 */
class WPBDP_Abandonment_Email {

	private $settings;

	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	public function send_abandoned_payment_notifications() {
		global $wpdb;

		$threshold        = max( 1, absint( $this->settings->get_option( 'abandoned-payment-threshold' ) ) );
		$time_for_pending = wpbdp_format_time( strtotime( "-{$threshold} hours", current_time( 'timestamp' ) ), 'mysql' );
		$notified         = get_option( 'wpbdp-payment-abandonment-notified', array() );

		if ( ! is_array( $notified ) ) {
			$notified = array();
		}

		// For now, we only notify listings with pending INITIAL payments.
		$to_notify = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpbdp_payments WHERE status = %s AND payment_type = %s AND created_at < %s ORDER BY created_at",
				'pending',
				'initial',
				$time_for_pending
			)
		);

		foreach ( $to_notify as &$data ) {
			if ( in_array( $data->id, $notified ) ) {
				continue;
			}

			$payment = WPBDP_Payment::objects()->get( $data->id );
			if ( ! $payment ) {
				continue;
			}

			$listing = $payment->get_listing();

			if ( ! $listing ) {
				continue;
			}

			// Send e-mail.
			$replacements = array(
				'listing' => $listing->get_title(),
				'link'    => sprintf( '<a href="%1$s">%1$s</a>', esc_url( $payment->get_checkout_url() ) ),
			);

			$email           = wpbdp_email_from_template( 'email-templates-abandoned-payment', $replacements );
			$email->to[]     = wpbusdirman_get_the_business_email( $listing->get_id() );
			$email->template = 'businessdirectory-email';
			$email->send();

			$notified[] = $data->id;
		}

		update_option( 'wpbdp-payment-abandonment-notified', $notified );
	}
}
