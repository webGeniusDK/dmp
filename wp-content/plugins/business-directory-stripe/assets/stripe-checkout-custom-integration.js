if ( typeof jQuery !== 'undefined' ) {
    jQuery( function( $ ) {
        var $form = $( '#wpbdp-checkout-form' );

        var submitButtonSelector = '.wpbdp-checkout-submit input[type="submit"]:visible';
        var stripe = null;
        var configuration = null;

        var isStripeSelected = function() {
            if ( 'stripe' === $form.find( '[name="gateway"]:checked' ).val() ) {
                return true;
            }

            return false;
        }

        var configureStripe = function() {
            if ( ! isStripeSelected() ) {
                return;
            }

            var $script = $( '#wpbdp-stripe-checkout-configuration' );
            if ( $script.length === 0 ) {
                return;
            }
            var $submit = $form.find( submitButtonSelector );

            $submit.prop( 'disabled', true );

            if ( null === configuration ) {
                configuration = $.parseJSON( $script.attr( 'data-configuration' ) );

                if ( null === configuration ) {
                    return;
                }
            }

            if ( configuration.label ) {
                $submit.val( configuration.label );
            }

            if ( null === stripe ) {
                if ( typeof Stripe === 'undefined' ) {
                    if ( false === configuration.sessionId && ! configuration.sessionError ) {
                        $form.find( '.stripe-errors' ).html( wpbdp_checkout_stripe_js.stripeNotAvailable );
                    }

                    $form.find( '.stripe-errors' ).show();
                    $submit.hide();
                    return;
                }
                stripe = Stripe( configuration.key );
            }

            if ( false !== configuration.sessionId ) {
                $submit.prop( 'disabled', false );
                $submit.show();
                return;
            }

            $form.find( '.stripe-errors' ).show();
            $submit.hide();
        }

        $( window ).on( 'wpbdp-payment-gateway-loaded', function( event, gateway ) {
            if ( 'stripe' !== gateway ) {
                return;
            }

            configureStripe();
        });

        $form.on( 'click', submitButtonSelector, function( event ) {
            if ( isStripeSelected() && null === stripe ) {
                return false;
            }

            if ( null === stripe || null === configuration ) {
                return true;
            }
            event.preventDefault();

            stripe.redirectToCheckout({
                // Make the id field from the Checkout Session creation API response
                // available to this file, so you can provide it as parameter here
                // instead of the {{CHECKOUT_SESSION_ID}} placeholder.
                sessionId: configuration.sessionId
            }).then(function (result) {
                // If `redirectToCheckout` fails due to a browser or network
                // error, display the localized error message to your customer
                // using `result.error.message`.
            });

            return false;
        } );

        configureStripe();
    } );
}

