<?php
/**
 * Stripe Gateway
 *
 * @package Stripe/Includes/Stripe Gateway
 */

/**
 * Class WPBDP__Stripe__Gateway
 */
class WPBDP__Stripe__Gateway extends WPBDP__Payment_Gateway {

	/**
	 * @var string $module_version
	 */
	private $module_version;

	/**
	 * @param string $module_version Installed module version.
	 */
	public function __construct( $module_version ) {
		$this->module_version = $module_version;

		add_action( 'wp_ajax_stripe_verify_payment', array( $this, 'stripe_verify_payment' ) );
		add_action( 'wp_ajax_nopriv_stripe_verify_payment', array( $this, 'stripe_verify_payment' ) );

		add_action( 'wpbdp_hourly_events', array( $this, 'remove_expired_invoice_items' ) );
	}

	public function get_id() {
		return 'stripe';
	}

	public function get_title() {
		return __( 'Stripe', 'wpbdp-stripe' );
	}

	public function get_logo() {
		return wpbdp_render_page( dirname( __DIR__ ) . '/templates/stripe-credit-cards-logo.tpl.php' );
	}

	/**
	 * @param string $currency Currency code.
	 */
	public function supports_currency( $currency ) {
		// List taken from https://stripe.com/docs/currencies#charge-currencies.
		return in_array(
			$currency,
			array(
				'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN',
				'BAM', 'BBD', 'BDT', 'BGN', 'BIF', 'BMD', 'BND', 'BOB', 'BRL', 'BSD',
				'BWP', 'BYN', 'BZD',
				'CAD', 'CDF', 'CHF', 'CLP', 'CNY', 'COP', 'CRC', 'CVE', 'CZK',
				'DJF', 'DKK', 'DOP', 'DZD',
				'EGP', 'ETB', 'EUR',
				'FJD', 'FKP',
				'GBP', 'GEL', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD',
				'HKD', 'HNL', 'HRK', 'HTG', 'HUF',
				'IDR', 'ILS', 'INR', 'ISK',
				'JMD', 'JPY',
				'KES', 'KGS', 'KHR', 'KMF', 'KRW', 'KYD', 'KZT',
				'LAK', 'LBP', 'LRD', 'LSL',
				'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRO', 'MUR', 'MVR',
				'MWK', 'MXN', 'MYR', 'MZN',
				'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD',
				'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG',
				'QAR',
				'RON', 'RSD', 'RUB', 'RWF',
				'SAR', 'SBD', 'SCR', 'SEK', 'SGD', 'SHP', 'SLL', 'SOS', 'SRD', 'STD', 'SZL',
				'THB', 'TJS', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS',
				'UAH', 'UGX', 'USD', 'UYU', 'UZS',
				'VND', 'VUV',
				'WST',
				'XAF', 'XCD', 'XOF', 'XPF',
				'YER',
				'ZAR', 'ZMW',
			),
			true
		);
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'stripe', 'https://js.stripe.com/v3/', array(), '3', false );
		wp_enqueue_script( 'wpbdp-stripe-checkout' );

		wp_localize_script(
			'wpbdp-stripe-checkout',
			'wpbdp_checkout_stripe_js',
			array(
				'stripeNotAvailable' => __( 'Stripe gateway is not currently available. Please reload this page or select another gateway (if available).', 'wpbdp-stripe' ),
			)
		);
	}

	private function set_stripe_info() {
		if ( ! class_exists( 'Stripe\Stripe' ) ) {
			require_once trailingslashit( dirname( plugin_dir_path( __FILE__ ) ) ) . 'vendors/stripe-php/init.php';
		}

		\Stripe\Stripe::setAppInfo(
			'WordPress Business Directory Stripe Module',
			$this->module_version,
			'https://businessdirectoryplugin.com/'
		);

		\Stripe\Stripe::setApiVersion( '2020-08-27' );
		\Stripe\Stripe::setApiKey( $this->get_secret_key() );
	}

	private function get_publishable_key() {
		return $this->in_test_mode() ? $this->get_option( 'test-publishable-key' ) : $this->get_option( 'live-publishable-key' );
	}

	private function get_secret_key() {
		return $this->in_test_mode() ? $this->get_option( 'test-secret-key' ) : $this->get_option( 'live-secret-key' );
	}

	public function get_integration_method() {
		return 'direct';
	}

	/**
	 * Override this in the individual gateway class.
	 *
	 * @param WPBDP_Payment $payment Payment object.
	 * @since 5.2
	 */
	public function get_payment_link( $payment ) {
		$url = 'https://dashboard.stripe.com/';
		if ( isset( $payment->mode ) && $payment->mode === 'test' ) {
			$url .= 'test/';
		}
		return $url . 'payments/' . $payment->gateway_tx_id;
	}

	public function get_settings_text() {
		$msg = sprintf(
			/* translators: %1$s Start link html, %2$s end link html, %3$s open link html */
			__( 'For this gateway to correctly work with your site you need to %1$sspecify a webhook URL%2$s in your %3$sStripe Account Settings%2$s.', 'wpbdp-stripe' ),
			'<a href="https://stripe.com/docs/webhooks" target="_blank" rel="noopener">',
			'</a>',
			'<a href="https://dashboard.stripe.com/webhooks" target="_blank" rel="noopener">'
		);

		$msg .= '<br/>';
		$msg .= sprintf(
			/* translators: %s the site url */
			__( 'Please use %s as the webhook URL Stripe will use to contact your site.', 'wpbdp-stripe' ),
			'<tt>' . $this->get_listener_url() . '</tt>'
		);

		return $msg;
	}

	public function get_settings() {
		return array(
			array(
				'id'      => 'checkout-title',
				'name'    => __( 'Checkout Window Title', 'wpbdp-stripe' ),
				'type'    => 'text',
				'default' => '',
			),
			array(
				'id'   => 'test-publishable-key',
				'name' => __( 'TEST Publishable Key', 'wpbdp-stripe' ),
				'type' => 'text',
			),
			array(
				'id'   => 'test-secret-key',
				'name' => __( 'TEST Secret Key', 'wpbdp-stripe' ),
				'type' => 'text',
			),
			array(
				'id'   => 'live-publishable-key',
				'name' => __( 'LIVE Publishable Key', 'wpbdp-stripe' ),
				'type' => 'text',
			),
			array(
				'id'   => 'live-secret-key',
				'name' => __( 'LIVE Secret Key', 'wpbdp-stripe' ),
				'type' => 'text',
			),
			array(
				'id'      => 'billing-address-check',
				'name'    => __( 'Verify billing address during checkout?', 'wpbdp-stripe' ),
				'type'    => 'checkbox',
				'default' => false,
			),
		);
	}

	public function validate_settings() {
		$errors = array();

		foreach ( array( 'secret-key', 'publishable-key' ) as $k ) {
			$option_name  = $this->in_test_mode() ? 'test-' . $k : 'live-' . $k;
			$option_value = $this->get_option( $option_name );

			if ( ! $option_value ) {
				/* translators: %s is the setting name */
				$errors[] = sprintf( __( '%s is missing.', 'wpbdp-stripe' ), ucwords( str_replace( '-', ' ', $k ) ) );
			}
		}

		return $errors;
	}

	/**
	 * @param array $form Form data.
	 */
	public function validate_form( $form ) {
		return array();
	}

	/**
	 * @param WPBDP_Payment $payment Payment object.
	 * @param array         $errors  Errors.
	 */
	public function render_form( $payment, $errors = array() ) {
		$stripe = $this->configure_stripe( $payment );

		$content = '<div class="wpbdp-msg wpbdp-error stripe-errors" style="display:none;">';
		if ( ! $stripe['sessionId'] ) {
			$content .= $stripe['sessionError'] ? $stripe['sessionError'] : __( 'There was an error while configuring Stripe gateway', 'wpbdp-stripe' );
		}

		$content .= '</div>';

		$custom_script = '<script id="wpbdp-stripe-checkout-configuration" type="text/javascript" data-configuration="%s"></script>';
		$content      .= sprintf( $custom_script, esc_attr( (string) wp_json_encode( $stripe ) ) );

		return $content;
	}

	/**
	 * @param WPBDP_Payment $payment Payment object.
	 */
	private function configure_stripe( $payment ) {
		$this->set_stripe_info();

		$stripe = array(
			'key'            => $this->get_publishable_key(),
			'amount'         => $this->formated_amount( $payment->amount ),
			'name'           => empty( $this->get_option( 'checkout-title' ) ) ? get_bloginfo( 'name' ) : $this->get_option( 'checkout-title' ),
			'description'    => $payment->summary,
			'currency'       => strtolower( $payment->currency_code ),
			'billingAddress' => $this->get_option( 'billing-address-check' ) ? true : false,
			'label'          => __( 'Pay now via Stripe', 'wpbdp-stripe' ),
			'locale'         => 'auto',
			'paymentId'      => $payment->id,
		);

		$session = $this->create_stripe_session( $payment );

		if ( is_wp_error( $session ) ) {
			$stripe['sessionId']    = false;
			$stripe['sessionError'] = $session->get_error_message();

			return $stripe;
		}

		$stripe['sessionId'] = $session->id;

		if ( $payment->has_item_type( 'discount_code' ) ) {
			$this->maybe_configure_stripe_discount( $payment, $session );
		}

		return $stripe;
	}

	public function process_manual_integration() {
		_deprecated_function( __METHOD__, 'x.x' );
		wp_die();
	}

	/**
	 * Return a json error and die.
	 *
	 * @param string $error Error message.
	 * @return void
	 */
	private function handle_exception( $error ) {
		echo wp_json_encode(
			array(
				'error' => $error,
			)
		);
		wp_die();
	}

	public function process_payment( $payment ) {
		$token        = wpbdp_get_var( array( 'param' => 'stripeToken' ), 'post' );
		$stripe_email = wpbdp_get_var( array( 'param' => 'stripeEmail' ), 'post' );
		if ( ! $token || ! $stripe_email ) {
			return array(
				'result' => 'failure',
				'error'  => __( 'No Stripe token was generated.', 'wpbdp-stripe' ),
			);
		}
		// Use token.
		$this->set_stripe_info();
		$payment->payer_first_name      = wpbdp_get_var( array( 'param' => 'stripeBillingName' ), 'post' );
		$payment->payer_email           = $stripe_email;
		$payment->payer_data['address'] = wpbdp_get_var( array( 'param' => 'stripeBillingAddressLine1' ), 'post' );
		$payment->payer_data['state']   = wpbdp_get_var( array( 'param' => 'stripeBillingAddressState' ), 'post' );
		$payment->payer_data['city']    = wpbdp_get_var( array( 'param' => 'stripeBillingAddressCity' ), 'post' );
		$payment->payer_data['country'] = wpbdp_get_var( array( 'param' => 'stripeBillingAddressCountry' ), 'post' );
		$payment->payer_data['zip']     = wpbdp_get_var( array( 'param' => 'stripeBillingAddressZip' ), 'post' );

		try {
			if ( ! $payment->has_item_type( 'recurring_plan' ) ) {
				// Regular payment.
				$charge = \Stripe\Charge::create(
					array(
						'amount'      => $this->formated_amount( $payment->amount ),
						'currency'    => strtolower( $payment->currency_code ),
						'source'      => $token,
						'description' => $payment->summary,
					)
				);

				$payment->gateway_tx_id = $charge->id;
				$payment->status        = 'completed';
				$payment->save();
			} else {
				// Subscription.
				$item     = $payment->find_item( 'recurring_plan' );
				$response = array(
					'result' => 'failure',
				);
				$customer = $this->get_stripe_customer( $payment );
				if ( ! $customer ) {
					$response['error'] = __( 'Stripe Customer couldn\'t be retrieved.', 'wpbdp-stripe' );
					return $response;
				}

				$plan = $this->get_stripe_plan( $payment );
				if ( ! $plan ) {
					$response['error'] = __( 'Stripe Plan couldn\'t be retrieved.', 'wpbdp-stripe' );
					return $response;
				}

				$balance = 0.0;
				if ( $payment->amount < $item['amount'] ) {
					$balance = ( $payment->amount - $item['amount'] ) * 100;
				}
				if ( $balance != 0.0 ) {
					$customer->account_balance = $balance;
					$customer->save();
				}

				$response = $customer->subscriptions->create(
					array(
						'plan'     => $plan->id,
						'card'     => $token,
						'metadata' => array(
							'payment_id'       => $payment->id,
							'wpbdp_payment_id' => $payment->id,
						),
					)
				);

				$payment->status = 'completed';
				$payment->save();
				$subscription = $payment->get_listing()->get_subscription();
				$subscription->set_subscription_id( $response->id );
				$subscription->record_payment( $payment );
			}
			return array( 'result' => 'success' );
		} catch ( \Stripe\Exception\CardException $e ) {
			return array(
				'result' => 'failure',
				'error'  => __( 'Your payment was declined (due to incorrect credit card information).', 'wpbdp-stripe' ),
			);
		} catch ( \Stripe\Exception\InvalidRequestException $e ) {
			$message = __( 'Invalid request: <error-message>.', 'wpbdp-stripe' );
			$message = str_replace( '<error-message>', $e->getMessage(), $message );
			return array(
				'result' => 'failure',
				'error'  => $message,
			);
		} catch ( Exception $e ) {
			return array(
				'result' => 'failure',
				'error'  => $e->getMessage(),
			);
		}

		return array( 'result' => 'failure' );
	}

	private function generatePaymentResponse( $payment, $intent = null ) {
		$payment->gateway = $this->get_id();

		if ( $intent->status === 'requires_source_action' && $intent->next_action->type === 'use_stripe_sdk' ) {
			$payment->save();
			// Tell the client to handle the action.
			wp_send_json(
				array(
					'requires_action'              => true,
					'payment_intent_client_secret' => $intent->client_secret,
					'is_recurring_payment'         => 'automatic' === $intent->confirmation_method && $payment->has_item_type( 'recurring_plan' ),
				),
				200
			);

			wp_die();
		}

		/*
		The payment didn’t need any additional actions and completed!
		Handle post-payment fulfillment.
		*/
		if ( $intent->status === 'succeeded' ) {
			if ( ! $payment->has_item_type( 'recurring_plan' ) ) {
				$payment->status        = 'completed';
				$payment->gateway_tx_id = $intent->id;
				$this->save_payer_address( $payment, $intent->charges->data[0]->billing_details );
				$payment->save();
			}
			echo wp_json_encode(
				array(
					'payment_id' => $payment->id,
				)
			);
		} else {
			// Invalid status.
			$payment->status = 'failed';
			$payment->save();
			echo wp_json_encode( array( 'error' => 'Invalid PaymentIntent status' ), 500 );
		}
		wp_die();
	}

	public function stripe_verify_payment() {
		$post = stripslashes_deep( $this->get_posted_json() );

		$payment  = wpbdp_get_payment( $post->payment_id );
		$response = array( 'payment_id' => $post->payment_id );

		if ( 'completed' === $payment->status ) {
			$response['success'] = true;
		}

		echo wp_json_encode( $response );
		wp_die();
	}

	/**
	 * @return void
	 */
	public function process_postback() {
		$json = $this->get_posted_json();

		if ( ! isset( $json->id ) ) {
			wp_die( 'Not a valid Stripe notification' );
		}

		$this->set_stripe_info();

		try {
			header( 'HTTP/1.1 200 OK' );
			$event = \Stripe\Event::retrieve( $json->id );
		} catch ( Exception $e ) {
			wp_die( esc_html( $e->getMessage() ) );
		}

		$invoice = $event->data->object;

		try {
			$subscription   = new WPBDP__Listing_Subscription( 0, isset( $invoice->subscription ) ? $invoice->subscription : 0 );
			$parent_payment = $subscription->get_parent_payment();
		} catch ( Exception $e ) {
			$subscription   = null;
			$parent_payment = null;
		}

		switch ( $event->type ) {
			case 'invoice.payment_failed':
				if ( $parent_payment && $this->get_id() === $parent_payment->gateway ) {
					try {
						$this->cancel_subscription( wpbdp_get_listing( $parent_payment->listing_id ), $subscription );
					} catch ( Exception $e ) {
						$subscription->cancel();
					}
				}
				break;
			case 'invoice.payment_succeeded':
				if ( ! $subscription ) {
					$subscription = $this->maybe_create_listing_subscription( $invoice );

					if ( $subscription ) {
						$parent_payment = $subscription->get_parent_payment();
					}
				}

				$this->process_payment_succeeded( $subscription, $parent_payment, $invoice );

				break;
			case 'payment_intent.succeeded':
				$this->process_payment_intent( $event->data );
				break;
			case 'customer.subscription.deleted':
				if ( $subscription ) {
					$subscription->cancel();
				}
				break;
		}
	}

	/**
	 * @since 5.2
	 *
	 * @throws Exception If the response is not valid.
	 */
	private function get_posted_json() {
		$input = file_get_contents( 'php://input' );
		if ( false === $input ) {
			throw new Exception( 'Invalid input' );
		}
		return json_decode( $input );
	}

	/**
	 * @since 5.2
	 */
	private function process_payment_succeeded( $subscription, $parent_payment, $invoice ) {
		if ( ! $parent_payment || $this->get_id() !== $parent_payment->gateway ) {
			return;
		}

		$today = gmdate( 'Y-n-d', strtotime( $parent_payment->created_at ) ) == gmdate( 'Y-n-d', $invoice->created );

		// Is this the first payment?
		if ( $today ) {
			$parent_payment->gateway_tx_id = $invoice->charge;
			$parent_payment->gateway       = $this->get_id();
			$parent_payment->save();
			return;
		}

		$exists = WPBDP_Payment::objects()->get(
			array(
				'gateway_tx_id' => $invoice->charge,
				'gateway'       => $this->get_id(),
			)
		);

		if ( $exists ) {
			return;
		}

		// An installment.
		$subscription->record_payment(
			array(
				'amount'        => $invoice->total / 100.0,
				'gateway_tx_id' => $invoice->charge,
				'created_at'    => gmdate( 'Y-m-d H:i:s', $invoice->created ),
			)
		);
		$subscription->renew();
	}

	private function process_payment_intent( $event ) {
		if ( empty( $event->object->id ) || 'manual' === $event->object->confirmation_method ) {
			return;
		}

		$checkout = $this->verify_transaction( $event->object );

		if ( ! $checkout ) {
			return;
		}

		$checkout = array_shift( $checkout );
		$payment  = wpbdp_get_payment( $checkout->data->object->client_reference_id );

		if ( ! $payment || 'completed' == $payment->status ) {
			return;
		}

		$payment->gateway = $this->get_id();
		$payment->status  = 'completed';

		if ( ! empty( $event->object->charges ) && ! empty( $event->object->charges->data[0] ) ) {
			$charge = $event->object->charges->data[0];
			$this->save_payer_address( $payment, $charge->billing_details );
			$payment->gateway_tx_id = $charge->id;
		} elseif ( ! empty( $event->object->latest_charge ) ) {
			// Fallback to get the charge id from the invoice.
			$payment->gateway_tx_id = $event->object->latest_charge;
		}

		$payment->save();
	}

	private function maybe_create_listing_subscription( $invoice ) {
		foreach ( $invoice->lines->data as $invoice_item ) {
			if ( 'subscription' === $invoice_item->type ) {
				$payment = wpbdp_get_payment( $invoice_item->metadata->wpbdp_payment_id );
				break;
			}
		}

		if ( ! $payment ) {
			return null;
		}

		if ( $invoice->charge ) {

			try {
				$charge = \Stripe\Charge::retrieve( $invoice->charge );
			} catch ( Exception $e ) {
				$charge = null;
			}

			if ( $charge ) {
				$this->save_payer_address( $payment, $charge->billing_details );
			}
		}

		if ( ! $charge ) {

			try {
				$subscription   = \Stripe\Subscription::retrieve( $invoice->subscription );
				$payment_method = \Stripe\PaymentMethod::retrieve( $subscription->default_payment_method );
			} catch ( Exception $e ) {
				$subscription   = null;
				$payment_method = null;
			}

			if ( $payment_method ) {
				$this->save_payer_address( $payment, $payment_method->billing_details );
			}
		}

		$payment->gateway       = $this->get_id();
		$payment->gateway_tx_id = $invoice->id;
		$payment->status        = 'completed';

		$payment->save();

		$this->set_listing_stripe_customer( $payment->listing_id, $invoice->customer );
		$subscription = $payment->get_listing()->get_subscription();

		if ( ! $subscription ) {
			return null;
		}

		$subscription->set_subscription_id( $invoice->subscription );
		$subscription->record_payment( $payment );

		return $subscription;
	}

	private function get_stripe_customer( $payment, $create = true ) {
		$customer = null;

		$user_ids              = $this->get_possible_user_ids( $payment );
		$possible_customer_ids = $user_ids['possible_customer_ids'];
		$user_ids              = $user_ids['user_ids'];
		$this_user             = 0;

		foreach ( $possible_customer_ids as $uid => $sid ) {
			try {
				$customer = \Stripe\Customer::retrieve( $sid );

				if ( ! $customer || ! is_object( $customer ) || ( isset( $customer->deleted ) && $customer->deleted ) ) {
					$customer = null;
				}
			} catch ( Exception $e ) {
				$customer = null;
			}

			if ( $customer ) {
				$this_user = $uid;
				break;
			}

			if ( $uid ) {
				// Remove the user meta if the customer doesn't exist.
				delete_user_meta( $uid, $this->customer_meta_name() );
			}
		}

		if ( $customer ) {
			return $customer;
		}

		if ( ! $create ) {
			return $customer;
		}

		try {
			$customer = \Stripe\Customer::create( $this->new_customer_data( $payment ) );
		} catch ( Exception $e ) {
			$customer = null;
		}
		if ( $customer ) {
			$this->set_listing_stripe_customer( $payment->listing_id, $customer->id );

			if ( ! $this_user ) {
				$this_user = reset( $user_ids );
			}

			if ( $this_user ) {
				update_user_meta( $this_user, $this->customer_meta_name(), $customer->id );
			}
		}

		return $customer;
	}

	/**
	 * Return the user ids in order of priority.
	 * The customer ID will include the last used payment method, so use carefully.
	 *
	 * @since 5.2
	 *
	 * @param WPBDP_Payment $payment Payment object.
	 * @return array
	 */
	private function get_possible_user_ids( $payment ) {
		$user_ids = array();
		if ( is_user_logged_in() ) {
			// The default user is only allowed here.
			$user_ids[] = get_current_user_id();
		}

		$default_author = (int) wpbdp_get_option( 'default-listing-author' );
		$post           = get_post( $payment->listing_id );
		if ( empty( $user_ids ) && $post->post_author && $default_author && $default_author !== (int) $post->post_author ) {
			$user_ids[] = $post->post_author;
		}

		$possible_customer_ids   = array();
		$possible_customer_ids[] = get_post_meta( $payment->listing_id, $this->customer_meta_name(), true );

		$user_ids = array_filter( array_unique( $user_ids ) );
		foreach ( $user_ids as $user_id ) {
			$possible_customer_ids[ $user_id ] = get_user_meta( $user_id, $this->customer_meta_name(), true );
		}
		$possible_customer_ids = array_filter( array_unique( $possible_customer_ids ) );

		return compact( 'user_ids', 'possible_customer_ids' );
	}

	/**
	 * The name of the post or user meta, depending on test or live mode.
	 *
	 * @since 5.5
	 *
	 * @return string
	 */
	private function customer_meta_name() {
		return '_wpbdp_stripe_customer_id' . ( $this->in_test_mode() ? '_test' : '' );
	}

	/**
	 * @since 5.2
	 */
	private function new_customer_data( $payment ) {
		$details = $payment->get_payer_details();
		if ( is_user_logged_in() ) {
			// Use the account email instead of the email on the listing if logged in.
			$user             = wp_get_current_user();
			$details['email'] = $user->user_email;
			if ( empty( $details['first_name'] ) ) {
				$details['first_name'] = $user->user_firstname;
			}
			if ( empty( $details['last_name'] ) ) {
				$details['last_name'] = $user->user_lastname;
			}
			if ( empty( $details['first_name'] ) ) {
				$details['first_name'] = $user->display_name;
			}
		}

		$new_customer = array(
			'email'   => $details['email'],
			'address' => array(),
			'name'    => trim( $details['first_name'] . ' ' . $details['last_name'] ),
		);

		$fill = array( 'city', 'state', 'country', 'postal_code' => 'zip' );
		foreach ( $fill as $k => $f ) {
			if ( is_numeric( $k ) ) {
				// Set the key to the Stripe naming.
				$k = $f;
			}
			if ( ! empty( $details[ $f ] ) ) {
				$new_customer['address'][ $k ] = $details[ $f ];
			}
		}

		return $new_customer;
	}

	private function set_listing_stripe_customer( $listing_id, $customer_id ) {
		if ( $listing_id && ! empty( $customer_id ) ) {
			update_post_meta( $listing_id, $this->customer_meta_name(), $customer_id );
		}
	}

	private function get_session_parameters( $payment ) {
		$parameters = array(
			'billing_address_collection' => $this->get_option( 'billing-address-check' ) ? 'required' : 'auto',
			'payment_method_types'       => array( 'card' ),
			'client_reference_id'        => $payment->id,
			'success_url'                => $payment->get_return_url(),
			'cancel_url'                 => $payment->get_cancel_url(),
		);

		$parameters['customer'] = $this->get_stripe_customer( $payment )->id;

		if ( $payment->has_item_type( 'recurring_plan' ) ) {
			$plan = $this->get_stripe_plan( $payment );

			$parameters['subscription_data'] = array(
				'items'    => array(
					array(
						'plan' => $plan->id,
					),
				),
				'metadata' => array(
					'wpbdp_payment_id' => $payment->id,
				),
			);
		} else {
			$parameters['line_items'] = array(
				array(
					'name'        => esc_attr( get_bloginfo( 'name' ) ),
					'description' => $payment->summary,
					'amount'      => $this->formated_amount( $payment->amount ),
					'currency'    => strtolower( $payment->currency_code ),
					'quantity'    => 1,
				),
			);
		}

		return $parameters;
	}

	private function get_stripe_plan( $payment ) {
		$recurring = $payment->find_item( 'recurring_plan' );

		$recurring_plan_fingerprint = $this->get_recurring_plan_fingerprint( $recurring, $payment );

		$previous_id = 'bd-fee-id' . $recurring['fee_id'] . '-d' . $recurring['fee_days'];
		$plan_id     = 'bd-fee-id-' . $recurring['fee_id'] . '-' . $recurring_plan_fingerprint;

		foreach ( array( $previous_id, $plan_id ) as $id ) {
			$plan = $this->try_to_get_stripe_plan_with_id( $id );

			if ( is_null( $plan ) ) {
				continue;
			}

			$stripe_plan_fingerprint = $this->get_stripe_plan_fingerprint( $plan );

			if ( $stripe_plan_fingerprint === $recurring_plan_fingerprint ) {
				return $plan;
			}
		}

		return $this->create_stripe_plan( $plan_id, $recurring, $payment );
	}

	private function get_recurring_plan_fingerprint( $recurring, $payment ) {
		$params = array(
			'amount'         => $this->formated_amount( $recurring['amount'] ),
			'currency'       => strtolower( $payment->currency_code ),
			'interval'       => 'day',
			'interval_count' => intval( $recurring['fee_days'] ),
		);

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		return hash( 'crc32b', serialize( $params ) );
	}

	private function try_to_get_stripe_plan_with_id( $id ) {
		try {
			$plan = \Stripe\Plan::retrieve( $id );
		} catch ( Exception $e ) {
			$plan = null;
		}

		return $plan;
	}

	private function get_stripe_plan_fingerprint( $plan ) {
		$params = array(
			'amount'         => floatval( $plan->amount ),
			'currency'       => $plan->currency,
			'interval'       => 'day',
			'interval_count' => intval( $plan->interval_count ),
		);

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		return hash( 'crc32b', serialize( $params ) );
	}

	private function create_stripe_plan( $id, $recurring, $payment ) {
		return \Stripe\Plan::create(
			array(
				'amount'         => $this->formated_amount( $recurring['amount'] ),
				'currency'       => strtolower( $payment->currency_code ),
				'interval'       => 'day',
				'interval_count' => $recurring['fee_days'],
				'product'        => array(
					'name' => $recurring['description'],
				),
				'id'             => $id,
			)
		);
	}

	private function maybe_configure_stripe_discount( $payment, $session = null ) {
		$discount = $payment->find_item( 'discount_code' );

		if ( ! $discount ) {
			return;
		}

		$customer_id   = $session->customer;
		$pending_items = (array) get_option( 'wpbdm-stripe-pending-items', array() );

		if ( $pending_items ) {

			if ( array_key_exists( $customer_id, $pending_items ) && $this->is_valid_discount( $discount, $pending_items[ $customer_id ] ) ) {
				return;
			}

			unset( $pending_items[ $customer_id ] );
		}

		$discount_item = $this->set_stripe_discount( $payment, $customer_id );

		if ( $discount_item ) {
			$pending_items[ $customer_id ] = array(
				'item_id' => $discount_item->id,
				'date'    => $discount_item->date,
			);
		}

		update_option( 'wpbdm-stripe-pending-items', $pending_items );
	}

	private function is_valid_discount( $discount, $pending_discount ) {
		try {
			$discount_item = \Stripe\InvoiceItem::retrieve( $pending_discount['item_id'] );
			if ( ! $discount_item ) {
				return false;
			}

			if ( (int) $this->formated_amount( $discount['amount'] ) !== $discount_item->amount ) {
				$discount_item->delete();
				return false;
			}

			if ( time() - $discount_item->date > HOUR_IN_SECONDS ) {
				$discount_item->delete();
				return false;
			}
		} catch ( Exception $e ) {
			return false;
		}

		return true;
	}


	private function set_stripe_discount( $payment, $customer_id ) {
		$discount = $payment->find_item( 'discount_code' );

		if ( ! $discount ) {
			return null;
		}

		try {
			if ( $payment->has_item_type( 'recurring_plan' ) ) {
				$discount_item = \Stripe\InvoiceItem::create(
					array(
						'amount'      => $this->formated_amount( $discount['amount'] ),
						'currency'    => $payment->currency_code,
						'customer'    => $customer_id,
						'description' => $discount['description'],
					)
				);
			} else {
				$discount_item = '';
			}
		} catch ( Exception $e ) {
			return '';
		}

		return $discount_item;
	}

	/**
	 * @param WPBDP_Listing               $listing      Listing object.
	 * @param WPBDP__Listing_Subscription $subscription Subscription object.
	 * @since 5.0.5
	 * @throws Exception If the subscription can't be canceled.
	 */
	public function cancel_subscription( $listing, $subscription ) {
		$this->set_stripe_info();

		try {
			$sub = \Stripe\Subscription::retrieve( $subscription->get_subscription_id() );
			if ( ! $sub ) {
				/* translators: %1$s is the listing ID */
				$message = sprintf( __( 'An error occurred while trying to get customer information for listing with ID #%1$s. Please try again later or contact the site administrator.', 'wpbdp-stripe' ), $listing->get_id() );

				throw new Exception( $message );
			}

			if ( current_user_can( 'manage_options' ) ) {
				$cancel = $sub->cancel();
			} else {
				$customer = $this->get_stripe_customer( $subscription->get_parent_payment(), false );
				if ( is_object( $customer ) && $sub->customer == $customer->id ) {
					$cancel = $sub->cancel();
				} else {
					$cancel = false;
				}
			}
		} catch ( Exception $e ) {
			$message = __( 'An error occurred while trying to cancel your subscription. Please try again later or contact the site administrator.', 'wpbdp-stripe' );
			$cancel  = false;
			throw new Exception( esc_html( $message . ' ' . $e->getMessage() ) );
		}

		if ( $cancel && ( $cancel->status === 'canceled' || $cancel->cancel_at_period_end == true ) ) {
			// Mark as canceled in BD.
			$subscription->cancel();
		}
	}

	/**
	 * @param object $payment Payment object.
	 *
	 * @throws \Stripe\Exception\ApiErrorException Stripe api error.
	 * @return array|false The payment if found otherwise false.
	 */
	public function verify_transaction( $payment ) {
		try {
			$events = \Stripe\Event::all(
				array(
					'type'    => 'checkout.session.completed',
					'created' => array(
						// Check for events created in the last 24 hours.
						'gte' => time() - 24 * 60 * 60,
					),
				)
			);
		} catch ( Exception $e ) {
			return false;
		}

		$completed = array_filter(
			$events->data,
			function ( $event ) use ( $payment ) {
				if ( $event->data->object->payment_intent === $payment->id ) {
					return true;
				}
			}
		);

		if ( ! empty( $completed ) ) {
			return $completed;
		}

		return false;
	}

	public function save_payer_address( &$payment, $billing_details ) {
		$payment->payer_first_name      = $billing_details->name;
		$payment->payer_email           = $billing_details->email;
		$payment->payer_data['address'] = $billing_details->address->line1 . ( $billing_details->address->line2 ? ', ' . $billing_details->address->line2 : '' );
		$payment->payer_data['state']   = $billing_details->address->state;
		$payment->payer_data['city']    = $billing_details->address->city;
		$payment->payer_data['country'] = $billing_details->address->country;
		$payment->payer_data['zip']     = $billing_details->address->postal_code;
	}

	public function save_payer_address_from_form( &$payment, $form ) {
		_deprecated_function( __METHOD__, 'x.x' );
	}

	private function create_stripe_session( $payment ) {
		$payment->gateway = $this->get_id();

		try {
			$session = \Stripe\Checkout\Session::create( $this->get_session_parameters( $payment ) );
			if ( empty( $session->id ) ) {
				return new WP_Error( 'stripe_no_session', $session );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'stripe_no_session', $e->getMessage() );
		}

		return $session;
	}

	public function remove_expired_invoice_items() {
		$pending_items = get_option( 'wpbdm-stripe-pending-items', array() );

		if ( ! $pending_items ) {
			return;
		}

		$this->set_stripe_info();

		$pending_items = is_array( $pending_items ) ? $pending_items : array( $pending_items );
		$items         = array();

		foreach ( $pending_items as $customer_id => $data ) {
			if ( time() - $data['date'] < HOUR_IN_SECONDS ) {
				$items[ $customer_id ] = $data;
				continue;
			}

			try {
				$expired_item = \Stripe\InvoiceItem::retrieve( $data['item_id'] );
			} catch ( Exception $e ) {
				$expired_item = null;
			}

			if ( $expired_item ) {
				$expired_item->delete();
				continue;
			}

			$items[ $customer_id ] = $data;
		}

		update_option( 'wpbdm-stripe-pending-items', $items );
	}

	/**
	 * @since 5.5
	 *
	 * @param float $amount Amount to be formatted.
	 * @return float
	 */
	private function formated_amount( $amount ) {
		return round( $amount * 100, 0 );
	}
}
