var wpbdp = window.wpbdp || {};

(function($) {
    var googlemaps = wpbdp.googlemaps = wpbdp.googlemaps || {};
    googlemaps._maps = [];
    googlemaps.listeners = { 'map_created': [], 'map_rendered': [] };

    googlemaps.refresh_all = function() {
        $.each( googlemaps._maps, function( i, map ) {
            map.refresh();
        } );
    };

    googlemaps.Map = function( htmlID, settings ) {
		var args = [];

        this.MAP_TYPES = {
            'roadmap': google.maps.MapTypeId.ROADMAP,
            'satellite': google.maps.MapTypeId.SATELLITE,
            'hybrid': google.maps.MapTypeId.HYBRID,
            'terrain': google.maps.MapTypeId.TERRAIN
        };

        this.$map = $( '#' + htmlID );
        this.locations = [];
        this.settings = settings;

        // Sanitize zoom level.
        if ( 'undefined' != typeof( this.settings.zoom_level ) && 'auto' != this.settings.zoom_level ) {
            this.settings.zoom_level = parseInt( this.settings.zoom_level );
		}

        this.settings.removeEmpty = true;
        this.bounds = new google.maps.LatLngBounds();

		args.mapTypeId = this.MAP_TYPES[ this.settings.map_type ];

		if ( typeof this.settings.map_id !== 'undefined' ) {
			args.mapId = this.settings.map_id;
		} else {
			args.styles = this.settings.styles;
		}

		this.GoogleMap = new google.maps.Map( this.$map[0], args );

        this.infoWindow = new google.maps.InfoWindow();
        this.oms = new OverlappingMarkerSpiderfier(
			this.GoogleMap,
			{
				markersWontMove: true,
				markersWontHide: true,
				keepSpiderfied: true
			}
		);

        if ( '1' === WPBDP_googlemaps_marker_cluster.is_marker_cluster_enabled ) {
            this.mc = new MarkerClusterer(
				this.GoogleMap,
				this.locations,
                {
					gridSize: 40,
					imagePath: WPBDP_googlemaps_marker_cluster.markers_path,
					maxZoom: 15
				}
			);
        }
        this.rendered = false;

        for ( var i = 0; i < googlemaps.listeners.map_created.length; i++ )
            googlemaps.listeners.map_created[i]( this );

        googlemaps._maps.push( this );
    };

    $.extend( googlemaps.Map.prototype, {
        _addMarker: function( place ) {
			var markerOpts;

            if ( 'undefined' === typeof( place ) || ! place )
                return;

            if ( 'undefined' === typeof( place.geolocation) || ! place.geolocation ||
                 'undefined' === typeof( place.geolocation.lat ) || ! place.geolocation.lat ||
                 'undefined' === typeof( place.geolocation.lng ) || ! place.geolocation.lng )
                return;

            var position = new google.maps.LatLng( place.geolocation.lat, place.geolocation.lng );
            this.bounds.extend( position );

			markerOpts = place;
			delete markerOpts.url;
			delete markerOpts.title;

			markerOpts.map = this.GoogleMap;
			markerOpts.position = position;
			markerOpts.animation = this.settings.animate_markers ? google.maps.Animation.DROP : null;

			var marker = new google.maps.Marker( markerOpts );
            marker.descriptionHTML = place.content.replace( /(?:\r\n|\r|\n)/g, "<br />" );
            this.oms.addMarker( marker );
			if ( '1' === WPBDP_googlemaps_marker_cluster.is_marker_cluster_enabled ) {
                this.mc.addMarker( marker );
            }
        },

        setLocations: function( locations ) {
            this.locations = locations;
        },

        fitContainer: function(stretch, enlarge) {
            if ( ! this.settings.auto_resize || "auto" === this.settings.map_size )
                return;

            var parent_width = this.$map.parent().innerWidth();
            var current_width = this.$map.outerWidth();

            if ( parent_width < current_width ) {
                this.$map.width( parent_width - 2 );
            } else if ( parent_width >= this.orig_width ) {
                this.$map.width( this.orig_width - 2 );
            }

            this.refresh();
        },

        refresh: function() {
            if ( ! this.$map.is( ':visible' ) )
                return;

            if ( ! this.rendered ) {
                this.render();
            } else {
                this.$map.width( this.$map.parent().innerWidth() - 2 );
                google.maps.event.trigger( this.GoogleMap, 'resize' );
                this.GoogleMap.setCenter( this.bounds.getCenter() );
            }
        },

        render: function() {
            var i, map = this;
            this.orig_width = this.$map.width();

            var refElement = this.$map.parent().siblings( this.settings.position.element );

            if ( refElement.length == 0 ) {
                refElement = this.$map.parent().siblings('div.listings');
                this.settings.position.insertpos = '';
            }

            if ( 'top' === this.settings.position.location ) {
                if ( 'before' === this.settings.position.insertpos ) {
                    this.$map.insertBefore(refElement);
                } else if ( 'after' === this.settings.position.insertpos ) {
                    this.$map.insertAfter(refElement);
                } else {
                    this.$map.prependTo(refElement);
                }
            }

            // Add markers to map.
            if ( this.locations ) {
                for( i = 0; i < this.locations.length; i++ ) {
                    this._addMarker( this.locations[i] );
                }
            }

			this.oms.addListener( 'click', function( marker, event ) {
				map.infoWindow.setContent( marker.descriptionHTML );
				map.infoWindow.open( map.GoogleMap, marker );
			});

			this.oms.addListener( 'spiderfy' , function( markers ) {
				map.infoWindow.close();
			});

            if ( '1' === WPBDP_googlemaps_marker_cluster.is_marker_cluster_enabled ) {
                $( this.mc.getMarkers() ).each( function() {
                    google.maps.event.addListener(this, 'click', function () {
                        map.infoWindow.setContent( this.descriptionHTML );
                        map.infoWindow.open( map.GoogleMap, this );
                    });
                } );
            }

            for ( i = 0; i < googlemaps.listeners.map_rendered.length; i++ )
                googlemaps.listeners.map_rendered[i]( this );

            if ( this.settings.removeEmpty && ! this.locations ) {
                this.$map.remove();
            }

            if ( this.locations.length == 1 ) {
                if ( 'auto' != this.settings.zoom_level ) {
                    this.GoogleMap.setZoom( this.settings.zoom_level );
                } else {
                    this.GoogleMap.setZoom( 15 );
                }
            } else {
                this.GoogleMap.fitBounds( this.bounds );
            }

            this.GoogleMap.setCenter( this.bounds.getCenter() );

            this.rendered = true;

			$(window).on( 'resize', function() {
                map.fitContainer( true, false );
            });

            map.fitContainer( true, false );
        }
    });

    /**
     * @since 3.6
     */
    googlemaps.DirectionsHandler = function( map, $form, $display ) {
        if ( 0 == $form.length )
            return;

        if ( ! map.settings.listingID || map.locations.length != 1 )
            return;

        this._map = map;
        this._$form = $form;
        this._$display = null;

        this.from = null;
        this.to = [map.locations[0].geolocation.lat, map.locations[0].geolocation.lng];
        this.travelMode = null;

        this._working = false;
        this._error = '';

        var t = this;
        t._$form.find( '.find-route-btn' ).click(function(e) {
            e.preventDefault();
            t.startRouting();
        });

        var isNotSecureContext = typeof window.isSecureContext !== 'undefined' && ! window.isSecureContext;

        if ( isNotSecureContext ) {
            t._$form.find( 'input[name="from_mode"]' )
                .val('address')
                .parent('label')
                    .hide();
            t._$form.find( 'input[name="from_address"]' ).show();
        } else {
            t._$form.find( 'input[name="from_mode"]' ).change( function( e, focus ) {
                var $field = t._$form.find( 'input[name="from_address"]' );

                if ( 'address' == $( this ).val() && 'no-focus' === focus ) {
                    $field.show();
                } else if ( 'address' === $( this ).val() ) {
                    $field.show().focus();
                } else {
                    $field.hide();
                }
            }).filter( ':checked' ).trigger( 'change', 'no-focus' );
        }
    };

    $.extend( googlemaps.DirectionsHandler.prototype, {
        HTML_TEMPLATE : '<div id="wpbdp-map-directions-wrapper" style="display: none;">' +
                        '<div id="wpbdp-map-directions" class="cf">' +
                        '<div class="wpbdp-google-map route-map"></div>' + 
                        '<div class="directions-panel"></div>' +
                        '</div>' +
                        '</div>',

        TRAVEL_MODES : {
            'driving': google.maps.TravelMode.DRIVING,
            'cycling': google.maps.TravelMode.BICYCLING,
            'transit': google.maps.TravelMode.TRANSIT,
            'walking': google.maps.TravelMode.WALKING
        },

/*an API key. Each TransitMode specifies a preferred mode of transit. The following values are permitted:
google.maps.TransitMode.BUS indicates that the calculated route should prefer travel by bus.
google.maps.TransitMode.RAIL indicates that the calculated route should prefer travel by train, tram, light rail, and subway.
google.maps.TransitMode.SUBWAY indicates that the calculated route should prefer travel by subway.
google.maps.TransitMode.TRAIN indicates that the calculated route should prefer travel by train.
google.maps.TransitMode.TRAM indicates that the calculated route should prefer travel by tram and light rail.*/

        error: function( msg ) {
            var t = this;

            if ( 'undefined' === typeof msg || ! msg )
                msg = '';

            t._error = msg;
            t._working = false;

            if ( msg )
                alert( t._error );

            t._$form.find( '.find-route-btn' ).prop( 'disabled', false )
                                              .val( WPBDP_googlemaps_directions_l10n.submit_normal );
        },

        startRouting: function() {
            var t = this;

            if ( t._working )
                return;

            t._working = true;
            t._$form.find( '.find-route-btn' ).prop( 'disabled', true )
                                              .val( WPBDP_googlemaps_directions_l10n.submit_working );

            // Reset everything.
            $( '#wpbdp-map-directions-wrapper' ).remove();
            t._$display = $( t.HTML_TEMPLATE ).appendTo( 'body' );

            var fromMode = t._$form.find( 'input[name="from_mode"]:checked' ).val();
            var address = $.trim( this._$form.find( 'input[name="from_address"]' ).val() );
            var travelMode = t._$form.find( 'select[name="travel_mode"]' ).val();

            if ( 'current' != fromMode && 'address' != fromMode ) {
                t.error();
                return;
            }

            if ( 'address' == fromMode && ! address ) {
                t.error();
                return;
            }

            if ( 'driving' != travelMode && 'cycling' != travelMode && 'walking' != travelMode && 'transit' != travelMode ) {
                t.error();
                return;
            }

            t.travelMode = travelMode;

            if ( 'current' == fromMode ) {
                t.geolocate();
            } else {
                // TODO: maybe we can do away with passing the string directly? Does running the geocoding service work
                // better?
                t.geocode( address );
            }
        },

        geolocate: function() {
            var t = this;

            if ( ! t._working )
                return;

            if ( ! navigator.geolocation ) {
                t.error( WPBDP_googlemaps_directions_l10n.errors_no_route );
                return;
            }

            navigator.geolocation.getCurrentPosition(
                function(pos) {
                    t.from = [ pos.coords.latitude, pos.coords.longitude ];
                    t.displayRoute();
                },
                function(err) {
                    t.error(err.message);
                    return;
                }
            );
        },

        geocode: function( address ) {
            var t = this;

            if ( ! t._working )
                return;

            if ( 'undefined' == typeof t._geocoderService )
                t._geocoderService = new google.maps.Geocoder();

            t._geocoderService.geocode( { 'address': address }, function( results, status_ ) {
                if ( google.maps.GeocoderStatus.OK !== status_ ) {
                    t.error( WPBDP_googlemaps_directions_l10n.errors_no_route );
                    return;
                }

                var pos = results[0].geometry.location;
                t.from = [ pos.lat(), pos.lng() ];
                t.displayRoute();
            } );
        },

        displayRoute: function() {
            var t = this, directionsService, request;

            if ( ! t._working )
                return;

            directionsService = new google.maps.DirectionsService();

            request = {
                origin: new google.maps.LatLng( t.from[0], t.from[1] ),
                destination: new google.maps.LatLng( t.to[0], t.to[1] ),
                travelMode: t.TRAVEL_MODES[ t.travelMode ]
            };

            directionsService.route( request, function( route, status_ ) {
                if ( google.maps.DirectionsStatus.OK != status_ ) {
                    t.error( WPBDP_googlemaps_directions_l10n.errors_no_route );
                    return;
                }

                t.route = route;
                t.showThickbox();
            } );
        },

        showThickbox: function() {
            var t = this,
                $directions,
                $thickboxTitle,
                width,
                height,
                contentHeight;

            if ( ! t._working )
                return;

            width = $( window ).width() - 40;
            height = $( window ).height() - 40;

            var listingTitle = t._$form.find( 'input[name="listing_title"]' ).val();
            var title = WPBDP_googlemaps_directions_l10n[ 'titles_' + t.travelMode ].replace( /%s/g, listingTitle );

            tb_show( title, '#TB_inline?width=' + width + '&height=' + height + '&inlineId=wpbdp-map-directions-wrapper' );

            $directions = $( '#wpbdp-map-directions' );
            $thickboxTitle = $directions.closest( '#TB_window' ).find( '#TB_title' );
            contentHeight = height - $thickboxTitle.outerHeight();

            // Adjust dimensions of Thickbox dialog and the content
            $directions.css( { height: contentHeight  } );

            $directions.closest( '#TB_window' ).css( {
                marginLeft: - Math.floor( width / 2 ) + 'px',
                marginTop: - Math.floor( height / 2 ) + 'px',
                width: width + 'px'
            } );

            $directions.closest( '#TB_ajaxContent' ).css( {
                height: contentHeight,
                padding: 0,
            } );

            t.showMap( $directions );

            t._working = false;
            t._$form.find( '.find-route-btn' ).prop( 'disabled', false )
                                              .val( WPBDP_googlemaps_directions_l10n.submit_normal );
        },

        showMap: function( $container ) {
            var self = this, mapOptions, map, directionsDisplay;

            mapOptions = {
                zoom: 7,
                center: new google.maps.LatLng( self.from[0], self.from[1] ),
                mapTypeId: google.maps.MapTypeId.ROADMAP
            };

            map = new google.maps.Map( $container.find( '.route-map' ).get( 0 ), mapOptions );

            directionsDisplay = new google.maps.DirectionsRenderer();
            directionsDisplay.setMap( map );
            directionsDisplay.setPanel( $container.find( '.directions-panel' ).get( 0 ) );
            directionsDisplay.setDirections( self.route );
        }
    } );

    $( function() {
        if ( typeof WPBDP_googlemaps_data === 'undefined' ) {
            return;
        }

        $( '[id^="wpbdp-map-"]' ).each( function() {
            var uid = $( this ).attr( 'id' ).replace( 'wpbdp-map-', '' );

            if ( typeof WPBDP_googlemaps_data[ 'map_' + uid ] === 'undefined' ){
                return;
            }

            var settings = WPBDP_googlemaps_data[ 'map_' + uid ].settings;
            var locations = WPBDP_googlemaps_data[ 'map_' + uid ].locations;
            var map = new wpbdp.googlemaps.Map( 'wpbdp-map-' + settings.map_uid , settings );

            map.setLocations( locations );
            map.render();

            if ( settings.with_directions ) {
                var directions = new wpbdp.googlemaps.DirectionsHandler( map, jQuery( '.wpbdp-map-directions-config' ) );
            }
        } );
    } );

})(jQuery);
