<?php
class WPBDP__PayPal__Gateway extends WPBDP__Payment_Gateway {

    public function get_id() {
        return 'paypal';
    }

    public function get_title() {
        return __( 'PayPal', 'wpbdp-paypal' );
    }

    public function get_logo() {
        return wpbdp_render_page( dirname( dirname( __FILE__ ) ) . '/templates/paypal-credit-cards-logo.tpl.php' );
    }

    public function get_integration_method() {
        return 'form';
    }

    public function supports_currency( $currency ) {
        return in_array(
            $currency,
			array(
				'AUD', 'BRL', 'CAD', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'ILS', 'JPY', 'MYR', 'MXN', 'NOK',
				'NZD', 'PHP', 'PLN', 'GBP', 'RUB', 'SGD', 'SEK', 'CHF', 'TWD', 'THB', 'TRY', 'USD',
			)
        );
    }

	/**
	 * Override this in the individual gateway class.
	 *
	 * @param WPBDP_Payment $payment
	 * @since 5.0.6
	 */
	public function get_payment_link( $payment ) {
		$url = 'https://www.';
		if ( isset( $payment->mode ) && 'test' === $payment->mode ) {
			$url .= 'sandbox.';
		}

		return $url . 'paypal.com/activity/payment/' . $payment->gateway_tx_id;
	}

    public function get_settings_text() {
		return sprintf(
			/* translators: %1$s is open link HTML, %2$s close link */
			__( 'For this gateway to correctly work with your site you need to %1$sconfigure an IPN listener%2$s in your PayPal account.', 'wpbdp-paypal' ),
			'<a href="https://businessdirectoryplugin.com/knowledge-base/paypal-module/" target="_blank" rel="noopener">',
			'</a>'
		);
    }

    public function get_settings() {
        return array(
            array( 'id' => 'business-email', 'name' => __( 'PayPal Business Email', 'wpbdp-paypal' ), 'type' => 'text' ),
            array( 'id' => 'merchant-id', 'name' => __( 'PayPal Merchant ID', 'wpbdp-paypal' ), 'type' => 'text' )
        );
    }

    public function process_payment( $payment ) {
        if ( $payment->has_item_type( 'recurring_plan' ) ) {
            return $this->process_payment_recurring( $payment );
        }

        $paypal = array(
            'cmd'           => '_cart',
            'upload'        => '1',
            'business'      => $this->get_option( 'business-email' ),
            'email'         => $payment->payer_email,
            'first_name'    => $payment->payer_first_name,
            'last_name'     => $payment->payer_last_name,
            'invoice'       => $payment->id,
            'no_shipping'   => '1',
            'shipping'      => '0',
            'currency_code' => $payment->currency_code,
            'charset'       => 'utf-8',
            'no_note'       => '1',
            'custom'        => json_encode( array( 'payment_id' => $payment->id, 'payment_key' => $payment->payment_key ) ),
            'rm'            => '2',
            'return'        => add_query_arg( 'gateway', $this->get_id(), $payment->get_return_url() ),
            'cancel_return' => $payment->get_cancel_url(),
            'notify_url'    => $this->get_listener_url(),
            'bn'            => 'BusinessDirectoryPlugin_Cart_US',
            'cbt'           => get_bloginfo( 'name' ),
            'item_name_1'   => $payment->summary,
            'quantity_1'    => '1',
            'amount_1'      => number_format( $payment->amount, 2, '.', '' )
        );

        $url = $this->get_paypal_url();
        $url .= http_build_query( array_filter( $paypal ), '', '&' );
        $url = str_replace( '&amp;', '&', $url );

        return array( 'result' => 'success', 'redirect' => $url );
    }

    private function process_payment_recurring( $payment ) {
        $listing = wpbdp_get_listing( $payment->listing_id );
        $total = $payment->amount;
        $recurring_item = $payment->find_item( 'recurring_plan' );

        list( $t_n, $p_n ) = $this->get_subscription_period_vars( $recurring_item['fee_days'] );

        $paypal = array(
            'business'      => $this->get_option( 'business-email' ),
            'email'         => $payment->payer_email,
            'first_name'    => $payment->payer_first_name,
            'last_name'     => $payment->payer_last_name,
            'invoice'       => $payment->id,
            'no_shipping'   => '1',
            'shipping'      => '0',
            'no_note'       => '1',
            'currency_code' => $payment->currency_code,
            'charset'       => 'utf-8',
            'custom'        => json_encode( array( 'payment_id' => $payment->id, 'payment_key' => $payment->payment_key ) ),
            'rm'            => '2',
            'return'        => add_query_arg( 'gateway', $this->get_id(), $payment->get_return_url() ),
            'cancel_return' => $payment->get_cancel_url(),
            'notify_url'    => $this->get_listener_url(),
            'cbt'           => get_bloginfo( 'name' ),
            'bn'            => 'BusinessDirectoryPlugin_Cart_US',
            'sra'           => '1',
            'src'           => '1',
            'cmd'           => '_xclick-subscriptions',
            'item_name'     => $listing->get_title() . ' - ' . $recurring_item['description']
        );

        // Regular subscription price and interval.
        $paypal = array_merge( $paypal, array(
            'a3' => $recurring_item['amount'],
            'p3' => $p_n,
            't3' => $t_n
        ) );

        // If there was a discount, apply it as a "trial" period.
        if ( $total != $recurring_item['amount'] ) {
            $paypal = array_merge( $paypal, array(
                'a1' => $total,
                'p1' => $p_n,
                't1' => $t_n
            ) );
        }

        $url = $this->get_paypal_url();
        $url .= http_build_query( $paypal );
        $url = str_replace( '&amp;', '&', $url );

        return array( 'result' => 'success', 'redirect' => $url );
    }

    private function get_paypal_url() {
        if ( $this->in_test_mode() ) {
            return 'https://www.sandbox.paypal.com/cgi-bin/webscr?test_ipn=1&';
        } else {
            return 'https://www.paypal.com/cgi-bin/webscr?';
        }
    }

    private function get_subscription_period_vars( $days ) {
        $days = absint( $days );

        $periods = array(
            'D' => array( 'days' => 1, 'limit' => 90 ),
            'W' => array( 'days' => 7, 'limit' => 52 ),
            'M' => array( 'days' => 30, 'limit' => 24 ),
            'Y' => array( 'days' => 365, 'limit' => 5 )
        );

        $best_match = false;

        foreach ( $periods as $period => $_ ) {
            $days_in_period = $_['days'];

            $r = $days % $days_in_period;
            $d = round( $days / $days_in_period, 0 );

            if ( $d > $_['limit'] )
                continue;

            if ( 0 == $r ) {
                $best_match = array( $period, $d );
                break;
            }

            if ( ! $best_match ) {
                $best_match = array( $period, $d );
            } else {
                $d1 = $periods[ $best_match[0] ]['days'] * $best_match[1];
                $d2 = $d * $days_in_period;

                if ( abs( $days - $d1 ) > abs( $days -$d2 ) )
                    $best_match = array( $period, $d );
            }
        }

        if ( ! $best_match )
            wp_die( __( 'Can not create a valid PayPal subscription configuration from fee plan.', 'wpbdp-paypal' ) );

        return $best_match;
    }

    public function process_postback() {
        if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] != 'POST' ) {
            return;
        }

        if ( ! $this->validate_ipn() )
            return;

        $payment_id = ! empty( $_POST['invoice'] ) ? $_POST['invoice'] : 0;
        $payment = false;

        if ( ! $payment_id && ! empty( $_POST['custom'] ) ) {
            $custom = json_decode( $_POST['custom'] );

            if ( ! empty( $custom->payment_id ) )
                $payment_id = absint( $custom->payment_id );
        }

        if ( ! $payment_id && ( ! empty( $_POST['txn_id'] ) || ! empty( $_POST['parent_txn_id'] ) ) ) {
            if ( ! empty( $_POST['parent_txn_id'] ) ) {
                $payment = WPBDP_Payment::objects()->get( array( 'gateway' => 'paypal', 'gateway_tx_id' => $_POST['parent_txn_id'] ) );
            } else {
                $payment = WPBDP_Payment::objects()->get( array( 'gateway' => 'paypal', 'gateway_tx_id' => $_POST['txn_id'] ) );
            }
        }

        if ( $payment_id ) {
            $payment = wpbdp_get_payment( $payment_id );
        }

        if ( ! $payment ) {
            return;
        }

        // When the user returns from PayPal's website, the payment is not associated
        // with a gateway yet.
        if ( ! $payment->gateway ) {
            $payment->gateway = 'paypal';
            $payment->save();
        }

        // The payment was somehow already processed by a different gateway. Abort!
        if ( 'paypal' != $payment->gateway ) {
            return;
        }

        if ( ! $payment->gateway_tx_id ) {
            $payment->gateway_tx_id = ! empty( $_POST['txn_id'] ) ? $_POST['txn_id'] : '';
            $payment->save(); // Save txn id.
        }

        $txn_type = ! empty( $_POST['txn_type'] ) ? $_POST['txn_type'] : '';

        if ( method_exists( $this, 'process_postback_' . $txn_type ) )
            return call_user_func( array( $this, 'process_postback_' . $txn_type ), $payment );
        else
            return $this->process_postback_default( $payment );
    }

    private function process_postback_default( $payment ) {
        $paypal_status = strtolower( $_POST['payment_status'] );

        if ( in_array( $paypal_status, array( 'refunded', 'reversed' ) ) ) {
            return $this->process_postback_refund( $payment );
        }

        if ( 'completed' == $payment->status ) {
            return;
        }

        // Set customer data.
        $payment->payer_first_name      = ( ! $payment->payer_first_name && ! empty( $_POST['first_name'] ) ) ? $_POST['first_name'] : '';
        $payment->payer_last_name       = ( ! $payment->payer_last_name && ! empty( $_POST['last_name'] ) ) ? $_POST['last_name'] : '';
        $payment->payer_email           = ( ! $payment->payer_email && ! empty( $_POST['payer_email'] ) ) ? $_POST['payer_email'] : '';
        $payment->payer_data['address'] = ! empty( $_POST['address_street'] ) ? $_POST['address_street'] : '';
        $payment->payer_data['city']    = ! empty( $_POST['city'] ) ? $_POST['city'] : '';
        $payment->payer_data['state']   = ! empty( $_POST['address_state'] ) ? $_POST['address_state'] : '';
        $payment->payer_data['country'] = ! empty( $_POST['address_country_code'] ) ? $_POST['address_country_code'] : '';
        $payment->payer_data['zip']     = ! empty( $_POST['address_street'] ) ? $_POST['address_street'] : '';

        if ( 'completed' == $paypal_status ) {
            $payment->status = 'completed';
        } elseif ( 'pending' == $paypal_status && ! empty( $_POST['pending_reason'] ) ) {
            $payment->status = 'on-hold';
            $payment->log( sprintf( __( 'PayPal has the payment on hold. Reason given: %s', 'wpbdp-paypal' ), $_POST['pending_reason'] ) );
        } else {
            $payment->status = 'failed';
            $payment->log( sprintf( __( 'PayPal rejected the payment. PayPal Status: %s', 'wpbdp-paypal' ), $paypal_status ) );
        }

        $payment->save();
    }

    private function process_postback_subscr_signup( $payment ) {
        if ( 'completed' == $payment->status ) {
            return;
        }

        $payment->status = 'completed';
        $payment->log( sprintf( __( 'PayPal Subscription ID: %s', 'wpbdp-paypal' ), $_POST['subscr_id'] ) );
        $payment->save();

        // Register subscription.
        $subscription = $payment->get_listing()->get_subscription();
        $subscription->set_subscription_id( $_POST['subscr_id'] );
        $subscription->record_payment( $payment );
    }

    private function process_postback_subscr_payment( $payment ) {
        $listing = wpbdp_get_listing( $payment->listing_id );

        if ( ! $listing || ! $listing->has_subscription() )
            return;

        $subscription = $payment->get_listing()->get_subscription();

        $date1 = date( 'Y-n-d', strtotime( $payment->created_at ) );
        $date2 = date( 'Y-n-d', strtotime( $_POST['payment_date'] ) );
        $same_day = $date1 == $date2;
        $first_payment = ( $subscription->get_subscription_id() ? false : true );

        if ( $first_payment ) {
            if ( empty( $payment->gateway_tx_id ) ) {
                $payment->gateway_tx_id = $_POST['txn_id'];
                $payment->save();
            }

            if ( 'completed' != $payment->status ) {
                $this->process_postback_subscr_signup( $payment );
                return;
            }
        }

        // Do not process the first payment twice.
        if ( $same_day ) {
            return;
        }

        $subscription->record_payment( array( 'gateway_tx_id' => $_POST['txn_id'], 'amount' => $_POST['mc_gross'] ) );
        $subscription->renew();
    }

    private function process_postback_subscr_cancel( $payment ) {
        return $this->process_postback_subscr_eot( $payment );
    }

    private function process_postback_subscr_eot( $payment ) {
        $listing = $payment->get_listing();
        $subscription = $listing->get_subscription();
        $subscription->cancel();
    }

    private function process_postback_subscr_failed( $payment ) {
        // Do nothing for now.
    }

    private function validate_ipn() {
        $post_data = array();

        if ( ini_get( 'allow_url_fopen' ) ) {
            parse_str( file_get_contents( 'php://input' ), $post_data );

            if ( function_exists( 'get_magic_quotes_gpc' ) && get_magic_quotes_gpc() ) {
                $post_data = stripslashes_deep( $post_data );
            }
        }

        if ( ! $post_data ) {
            // XXX: Why?
            @ini_set( 'post_max_size', '12M' );
            $post_data = stripslashes_deep( $_POST );
        }

        if ( ! $post_data ) {
            return false;
        }

        $post_data_array['cmd'] = '_notify-validate';

        foreach ( $post_data as $key => $value ) {
            $post_data_array[ $key ] = $value;
        }

        if ( $this->in_test_mode() ) {
            $url = 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';
        } else {
            $url = 'https://ipnpb.paypal.com/cgi-bin/webscr';
        }

        $request_args = array(
            'method' => 'POST',
            'timeout' => 60,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'sslverify' => false,
            'body' => $post_data_array
        );
        $response = wp_remote_post( $url, $request_args );

        if ( is_wp_error( $response ) || ! $response )
            return false;

        if ( 'VERIFIED' !== wp_remote_retrieve_body( $response ) )
            return false;

        return true;
    }
}
