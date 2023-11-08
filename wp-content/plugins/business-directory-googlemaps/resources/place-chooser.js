(function($) {
    var googlemaps = s = wpbdp.googlemaps || {};

    googlemaps.PlaceChooser = function( container, settings ) {
        var t = this;

        this.$container = $(container);
        this.settings = $.extend({
            initial_value: {},
            context: '',
            debug: false,
            done_after_drag: false,
            show_done_button: true,
            auto_located: false
        }, settings);

        this._listeners = [];

        this.$map = this.$container.find('.map');
        this.$actions = this.$container.find('.actions');
        this.$action_area = this.$container.find('.action-area');

        if ( this.settings.show_done_button ) {
            t.$actions.find( '.done' ).click(function(e) {
                e.preventDefault();
                t._notify_listeners();
            });
        } else {
            t.$actions.find( '.done' ).remove();
        }

        t.$container.on( 'click', '.action-area-wrapper .wpbdp-place-chooser-cancel-button', function( e ) {
            e.preventDefault();
            t._toggle_action_area();
        } );

        this.$container.find('.actions .search-nearby-toggle').click(function(e) {
            e.preventDefault();
            t.search_nearby();
        });
        this.$container.find('.actions .enter-coordinates-toggle').click(function(e) {
            e.preventDefault();
            t.enter_coordinates();
        });

        this.$container.parent().find('input[name="enable_location_override"]').change( function ( e )  {
            if ( $( this ).prop( 'checked' ) ) {
                t.$container.show();
                if ( ! t.settings.auto_located ) {
                    if ( wpbdp.submit_listing.doing_ajax ) {
                        alert( "Wait a moment!" );
                        return;
                    }

                    wpbdp.submit_listing.doing_ajax = true;

                    var $form = $( '#wpbdp-submit-listing' ).find('form');
                    var data = $form.serialize();

                    data += '&action=wpbdp_get_address_from_state';

                    $.post( WPBDP_googlemaps_place_chooser.ajaxurl, data, function( res ) {
                        wpbdp.submit_listing.doing_ajax = false;

                        if ( ! res.address ) {
                            return;
                        }

                        t.search_by_address( res.address );
                        t.settings.auto_located = true;
                        $( 'input[name="done_location_override"]').val( 1 );

                    }, 'json' );

                }
            } else {
                t.$container.hide();
                $( 'input[name="done_location_override"]').val( '' );
                t.settings.auto_located = false;
            }
        } );
    };

    $.extend( googlemaps.PlaceChooser.prototype, {
        init: function() {
            this.init_map();
        },

        get_value: function() {
            var pos = this.marker.getPosition();
            return { lat: pos.lat(), lng: pos.lng() };
        },

        set_value: function(lat, lng) {
            this.debug('set_value()', lat, lng);
            this.marker.setPosition(new google.maps.LatLng( lat, lng ));
            this.google_map.setCenter(this.marker.getPosition());
            this._notify_listeners();
        },

        init_map: function() {
            var t = this;

            // Initialize map with default values.
            var def_value = new google.maps.LatLng( 0.0, 0.0 );
            t.google_map = new google.maps.Map( this.$map.get(0), {
                center: def_value,
                zoom: 5,
                mapTypeId: google.maps.MapTypeId.ROADMAP
            } );
            t.marker = new google.maps.Marker({
                position: def_value,
                map: this.google_map,
                draggable: true
            });

            if ( t.settings.done_after_drag ) {
                google.maps.event.addListener( t.marker, 'dragend', function() {
                    t._notify_listeners();
                } );
            }

            // Try to set initial location based on settings, context and browser geolocation.
            if ( t.settings.initial_value && 'undefined' != typeof( t.settings.initial_value.lat ) && 'undefined' != typeof( t.settings.initial_value.lng ) ) {
                t.debug('Setting initial value based on settings.', t.settings.initial_value);
                t.set_value( t.settings.initial_value.lat, t.settings.initial_value.lng );
            } else if ( t.settings.context ) {
                t.debug('Setting initial value based on context.', t.settings.context);
            } else {
                t.debug('Setting initial value based on geolocation API.');
                t.geolocate();
            }
        },

        geolocate: function() {
            var t = this;

            if ( navigator.geolocation ) {
                navigator.geolocation.getCurrentPosition(function(pos) {
                    t.set_value( pos.coords.latitude, pos.coords.longitude );
                });
            }
        },

        debug: function(v) {
            if ( ! this.settings.debug )
                return;

            for ( var i = 0; i < arguments.length; i++ ) {
                console.log( '[PlaceChooser] ' + arguments[i].toString());
            }
        },

        _notify_listeners: function() {
                var pos = this.marker.getPosition();
                var res = { 'success': true, 'lat': pos.lat(), 'lng': pos.lng() };

                $.each( this._listeners, function(i, f) {
                    f(res);
                } );
        },

        when_done: function(cb) {
            this._listeners.push(cb);
        },

        _toggle_action_area: function() {
            var t = this;

            if ( t.$actions.is( ':visible' ) ) {
                t.$actions.hide();
                t.$container.find('.action-area-wrapper').show();
            } else {
                t.$container.find('.action-area-wrapper').hide();
                t.$actions.show();
                t.$action_area.html('');
            }
        },

        /* {{ Actions. */
        enter_coordinates: function() {
            var t     = this;
            var $form = $( '<form class="enter-coordinates">' +
                           '<label>' + WPBDP_googlemaps_place_chooser.l10n.latitude + ': <input type="text" name="lat" class="coords-lat" /></label>' +
                           '<label>' + WPBDP_googlemaps_place_chooser.l10n.longitude + ': <input type="text" name="lng" class="coords-lng" /></label>' +
                           '<input class="wpbdp-place-chooser-cancel-button" type="button" value="' + WPBDP_googlemaps_place_chooser.l10n.return + '" />' +
                           '<input type="submit" value="' + WPBDP_googlemaps_place_chooser.l10n.set_location + '" class="locate-point" />' +
                           '</form>' );
            var $lat = $form.find('input[name="lat"]');
            var $lng = $form.find('input[name="lng"]');

            $form.submit(function(e) {
                e.preventDefault();

                var lat = parseFloat( $.trim( $lat.val() ) );
                var lng = parseFloat( $.trim( $lng.val() ) );

                if ( isNaN( lat ) || isNaN( lng ) )
                    return;

                t.set_value( lat, lng );
            });

            t.$action_area.html($form);
            t._toggle_action_area();

            $lat.focus();
        },

        search_nearby: function() {
            var t            = this;
            var $search_form = $('<form class="search-nearby"><label>' + WPBDP_googlemaps_place_chooser.l10n.address + ': <input type="text" class="search-term" /></label><input class="wpbdp-place-chooser-cancel-button" type="button" value="' + WPBDP_googlemaps_place_chooser.l10n.return + '" /><input type="submit" value="' + WPBDP_googlemaps_place_chooser.l10n.search + '" class="do-search" /></form>');
            $search_form.find('input.search-term').focus();
            $search_form.submit(function(e) {
                e.preventDefault();

                var address = $.trim( $search_form.find('.search-term').val() );

                t.search_by_address( address );
            });

            t.$action_area.html($search_form);
            t._toggle_action_area();

            $search_form.find('input.search-term').focus();
        },

        search_by_address: function ( address ) {
            if ( ! address )
                return;

            var t = this;

            if ( 'undefined' == typeof( t.google_geocoder ) )
                t.google_geocoder = new google.maps.Geocoder();

            t.debug('Geocoding address: ' + address);
            t.google_geocoder.geocode( { 'address': address }, function( results, status ) {
                if ( google.maps.GeocoderStatus.OK != status ) {
                    return;
                }

                var res = results[0].geometry.location;
                t.set_value( res.lat(), res.lng() );
            } );
        }

        /* }} */
    } );

/*    $(document).ready(function() {
        var chooser = new googlemaps.PlaceChooser( $('.wpbdp-googlemaps-place-chooser-container').get(0) );
        chooser.when_done(function(res) {
            alert('Done. Location = (' + res.lat + ', ' + res.lng + ')' );
        });
    });*/

})(jQuery);
