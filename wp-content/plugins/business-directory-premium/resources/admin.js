var BDPConnect = window.BDPConnect || ( function( document, window, $ ) {

	/*global ajaxurl */

	return {
		init: function() {
			$( '.wpbdp-main-license .wpbdp-license-key-activate-btn, .wpbdp-main-license .wpbdp-license-key-deactivate-btn' ).click( function( e ) {
				var $button, $setting, $msg, activate, $field, data;
				e.preventDefault();

				$button  = $( this );
				$setting = $button.parents( '.wpbdp-license-key-activation-ui' );
				$msg     = $setting.find( '.wpbdp-license-key-activation-status-msg' );
				activate = $button.is( '.wpbdp-license-key-activate-btn' );
				$field   = $setting.find( 'input.wpbdp-license-key-input' );
				data     = $setting.data( 'licensing' );

				$msg.hide();
				$button.data( 'original_label', $( this ).val() );
				$button.val( $( this ).data( 'working-msg' ) );
				$button.prop( 'disabled', true );

				if ( activate ) {
					data.action = 'wpbdp_activate_main_license';
				} else {
					data.action = 'wpbdp_deactivate_main_license';
				}

				data.license_key = $field.val();

				$.post(
					ajaxurl,
					data,
					function( res ) {
						var classes;

						if ( res.success ) {
							$msg.removeClass( 'status-error' ).addClass( 'status-success' ).html( res.message ).show();

							if ( activate ) {
								classes = $setting.attr( 'class' ).split( ' ' ).filter( function( item ) {
									var className = item.trim();

									if ( 0 === className.length ) {
										return false;
									}

									if ( className.match( /^wpbdp-license-status/ ) ) {
										return false;
									}

									return true;
								});

								classes.push( 'wpbdp-license-status-valid' );

								$setting.attr( 'class', classes.join( ' ' ) );
								$field.addClass( 'hidden' );
							} else {
								$setting.removeClass( 'wpbdp-license-status-valid' ).addClass( 'wpbdp-license-status-invalid' );
								$field.val( '' );
								$field.attr( 'type', 'text' );
							}

							$field.prop( 'readonly', activate ? true : false );
						} else {
							$msg.removeClass( 'status-success' ).addClass( 'status-error' ).html( res.error ).show();
							$setting.removeClass( 'wpbdp-license-status-valid' ).addClass( 'wpbdp-license-status-invalid' );
							$field.prop( 'readonly', false );
						}

						$button.val( $button.data( 'original_label' ) );
						$button.prop( 'disabled', false );
					},
					'json'
				);
			});
		}
	};
}( document, window, jQuery ) );

BDPConnect.init();
