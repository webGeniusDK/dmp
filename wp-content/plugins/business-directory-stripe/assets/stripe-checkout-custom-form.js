jQuery(function($) {

    stripe = Stripe( wpbdp_checkout_stripe_js.publishable_key );
    var $form      = $( '#wpbdp-checkout-form' );
    var $submit_btn = $form.find( '.wpbdp-payment-gateway-stripe-form-fields .wpbdp-checkout-submit input[type="submit"]' );
    const elements = stripe.elements();
    var cardElement = null;

    var configureStripe = function() {
        if ( ! cardElement ) {
            cardElement = elements.create( 'card', {hidePostalCode: true} );
        }
        // Add an instance of the card UI component into the `card-element` <div>
        cardElement.mount( '#card-element' );
    }

    $( '#wpbdp-checkout-form' ).on( 'submit', async ( e ) => {
        var $form = $( '#wpbdp-checkout-form' );

        if ( 'stripe' != $form.find( '[name="gateway"]:checked' ).val() ) {
            return true;
        }

        e.preventDefault();

        $submit_btn = $form.find( '.wpbdp-payment-gateway-stripe-form-fields .wpbdp-checkout-submit input[type="submit"]' );
        var $errors = $form.find( '.wpbdp-checkout-errors' );
        var $orgLabel = $submit_btn.val();
        $submit_btn.attr( 'disabled', true );
        $submit_btn.addClass( 'processing_payment' );
        $submit_btn.val( 'Processing Payment' );
        
        $errors.html( '' );

        var email = $( '[name="payer_email"]', $form ).val();
        var name  = $( '[name="payer_name"]', $form ).val();

        var address_data = {
            line1:       $( '[name="payer_address"]', $form ).val(),
            line2:       $( '[name="payer_address_2"]', $form ).val(),
            city:        $( '[name="payer_city"]', $form ).val(),
            postal_code: $( '[name="payer_zip"]', $form ).val(),
            country:     $( '[name="payer_country"]', $form ).val(),
            state:       $( '[name="payer_state"]', $form ).val()
        };

        $( '.stripe-errors', $form ).hide();
        const { paymentMethod, error } = await stripe.createPaymentMethod( 'card', cardElement, {
            billing_details: {
                name: name,
                email: email,
                address: address_data
            }
        } );

        if ( error ) {
            $( '.stripe-errors', $form ).text( error.message ).show();
            $submit_btn.removeAttr( 'disabled' );
            $submit_btn.removeClass( 'processing_payment' );
            $submit_btn.val( $orgLabel );
        } else {

            // Send paymentMethod.id to your server (see Step 2)
            var data = {
                payment_method_id: paymentMethod.id,
                form:    $form.serialize(),
            }

            const response = await fetch( wpbdp_checkout_stripe_js.ajaxurl + '?action=stripe_manual_integration', {
                method: 'POST',
                body: JSON.stringify( data )
            });

            const json = await response.json();

            // Handle server response (see Step 3)
            handleServerResponse( json, paymentMethod );
        }
    });

    $( window ).on( 'wpbdp-payment-gateway-loaded', function( event, gateway ) {
        if ( 'stripe' !== gateway ) {
            return;
        }

        configureStripe();
    });

    const handleServerResponse = async ( response, paymentMethod ) => {
        if ( response.error ) {
            $( '.stripe-errors', $form ).html( '' );
            $( '.stripe-errors', $form ).html( response.error ).show();
            $submit_btn.removeAttr( 'disabled' );
            $submit_btn.removeClass( 'processing_payment' );
        } else if ( response.requires_action ) {
            // Use Stripe.js to handle the required card action
            if ( response.is_recurring_payment ) {
                var { error: errorAction, paymentIntent } = await stripe.handleCardPayment( response.payment_intent_client_secret );
            } else {
                var { error: errorAction, paymentIntent } = await stripe.handleCardAction( response.payment_intent_client_secret );
            }

            if ( errorAction ) {
                // Show error from Stripe.js in payment form
                $( '.stripe-errors', $stripeForm ).text( errorAction.message ).show();
                $submit_btn.removeAttr( 'disabled' );
                $submit_btn.removeClass( 'processing_payment' );
            } else {
                /*
                The card action has been handled
                The PaymentIntent can be confirmed again on the server
                Build formData object.
                */
               var data = {
                    payment_intent_id: paymentIntent.id,
                    form:    $form.serialize(),
                }
               const serverResponse = await fetch( wpbdp_checkout_stripe_js.ajaxurl + '?action=stripe_manual_integration', {
                    method: 'POST',
                    body: JSON.stringify( data )
                });

                handleServerResponse( await serverResponse.json() );
            }
        } else {
            $submit_btn.val( 'Verifying Payment' );

            if ( response.success ) {
                $submit_btn.val( 'Payment Completed' );
                $submit_btn.removeClass( 'processing_payment' );
                $submit_btn.addClass( 'payment_completed' );

                setTimeout( function() {
                    location.reload(true);
                }, 1000 );
            }

            const serverResponse = await fetch( wpbdp_checkout_stripe_js.ajaxurl + '?action=stripe_verify_payment', {
                method: 'POST',
                body: JSON.stringify( {payment_id: response.payment_id } )
            });

            await timeout( 5000 );
            handleServerResponse( await serverResponse.json() );
        }
    }

    function timeout(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

});
