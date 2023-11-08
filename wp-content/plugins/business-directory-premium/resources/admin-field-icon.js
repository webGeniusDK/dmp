/* Icon Picker */

( function( $ ) {

	/* global WPBDPIcon */

	$.fn.wpbdpIconPicker = function() {
		var icons, $target, $button,
			options = [ 'dashicons', 'fa' ], // default font set
			$list = $( '' );

		function fontSet() {
			if ( options[0] === 'dashicons' ) {
				icons      = WPBDPIcon.icons.dash;
				options[1] = 'dashicons';
			} else if ( options[0] === 'fa' ) {
				icons      = WPBDPIcon.icons.fa;
				options[1] = 'fa';
				if ( icons.length === 0 ) {
					options[0] = 'dashicons';
					fontSet();
				}
			}
		}

		fontSet();

		function buildList( $popup, $button, clear ) {
			var i, item, iconspan, link;

			$list = $popup.find( '.wpbdp-icon-picker-list' );
			if ( clear == 1 ) {
				$list.empty(); // clear list //
			}
			for ( i in icons ) {
				item = $( '<li></li>' ).attr( 'data-icon', icons[i]);
				iconspan = $( '<span></span>' ).addClass( icons[i]);
				link = $( '<a href="#"></a>' ).attr( 'title', icons[i]).append( iconspan );
				$list.append( item.append( link ) );
			}
			$( 'a', $list ).click( function( e ) {
				var title = $( this ).attr( 'title' );
				e.preventDefault();
				$target.val( options[0] + '|' + title );
				$button.removeClass().addClass( 'button wpbdp-icon-picker ' + title );
				$button.attr( 'data-selected', options[0]);
				removePopup();
			});
		}

		function removePopup() {
			$( '.wpbdp-icon-picker-container' ).remove();
		}

		$button = $( '.wpbdp-icon-picker' );
		$button.each(
			function() {
				$( this ).on( 'click.wpbdpIconPicker', function() {
					createPopup( $( this ) );
				});
			}
		);

		function createPopup( $button ) {
			var $control, $searchField, $popup,
				useFA = WPBDPIcon.has_fa_plugin;

			$target    = $( $button.data( 'target' ) );
			options[0] = $button.attr( 'data-selected' );

			$popup     = $(
				'<div class="wpbdp-icon-picker-container' + ( useFA ? ' wpbdp-with-fa' : '' ) + '"> ' +
					'<div class="wpbdp-icon-picker-control"></div>' +
					'<ul class="wpbdp-icon-picker-list"></ul>' +
				'</div>'
			).css(
				{
					'top': $button.offset().top,
					'left': $button.offset().left
				}
			);

			fontSet();
			buildList( $popup, $button, 0 );
			$control     = $popup.find( '.wpbdp-icon-picker-control' );
			$searchField = $( '<a data-direction="back" href="#"><span class="dashicons dashicons-arrow-left-alt2"></span></a> ' +
					'<input type="text" class="wpbdp-icon-picker-placeholder" />' +
					'<a data-direction="forward" href="#"><span class="dashicons dashicons-arrow-right-alt2"></span></a>' );
			if ( useFA ) {
				$control.html(
					'<p><label><input type="radio" value="dashicons" name="wpbdp-font"/>Dashicons</label> ' +
					'<label><input type="radio" value="fa" name="wpbdp-font"/>Font Awesome</label></p>'
				);
			} else {
				$control.html( '<input type="hidden" name="wpbdp-font" value="dashicons"/>' );
			}
			$control.append( $searchField );
			$control.find( 'input.wpbdp-icon-picker-placeholder' ).attr( 'placeholder', WPBDPIcon.search );
			$( 'input[name="wpbdp-font"]', $control ).on( 'change', function( e ) {
				e.preventDefault();
				if ( this.checked ) {
					options[0] = this.value;
					fontSet();
					buildList( $popup, $button, 1 );
				}
			});

			$( 'a', $control ).click( function( e ) {
				e.preventDefault();
				if ( $( this ).data( 'direction' ) === 'back' ) {
					// move last 25 elements to front
					$( 'li:gt(' + ( icons.length - 26 ) + ')', $list ).each(
						function() {
							$( this ).prependTo( $list );
						}
					);
				} else {
					// move first 25 elements to the end
					$( 'li:lt(25)', $list ).each(
						function() {
							$( this ).appendTo( $list );
						}
					);
				}
			});

			$popup.appendTo( 'body' ).show();

			$( 'input[name="wpbdp-font"][value="' + options[0] + '"]' ).attr( 'checked', 'checked' ).change();
			$( 'input', $control ).on( 'keyup', function() {
				var search = $( this ).val();
				if ( search === '' ) {
					// show all again
					$( 'li:lt(25)', $list ).show();
				} else {
					$( 'li', $list ).each(
						function() {
							if ( $( this ).data( 'icon' ).toString().toLowerCase().indexOf( search.toLowerCase() ) !== -1 ) {
								$( this ).show();
							} else {
								$( this ).hide();
							}
						}
					);
				}
			});
		}
	};

	$( function() {
		$( '.wpbdp-icon-picker' ).wpbdpIconPicker();
	});

	$( document ).on( 'change', '#wpbdp-formfield-form select.wpbd-field-label-select', function() {
		var $fieldVisibility = $( this ).find( 'option:selected' ).val(),
		$iconArea             = $( '.if-field-icon' );
		if ( $fieldVisibility === 'icon' || $fieldVisibility === 'fieldlabelicon' ||  $fieldVisibility === 'valueicon' ) {
			$iconArea.removeClass( 'wpbdp-hidden' );
		} else {
			$iconArea.addClass( 'wpbdp-hidden' );
		}
	});

}( jQuery ) );
