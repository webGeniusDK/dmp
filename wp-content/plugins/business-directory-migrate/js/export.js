jQuery(function( $ ) {
    $( '.part-checkbox' ).change(function(e) {
        var checked = $(this).is( ':checked' );
        var deps_ = $(this).attr( 'data-deps' ).split( ',' );
        var deps = [];

        // Solve deps completely.
        for ( var i = 0; i < deps_.length; i++ ) {
            var other_deps = $('.part-checkbox[value="' + deps_[i] + '"]').data('deps');

            if ( other_deps ) {
                other_deps = other_deps.split(',');

                for ( var j = 0; j < other_deps.length; j++ ) {
                    if ( -1 == $.inArray( other_deps[j], deps ) ) {
                        deps.push( other_deps[j] );
                    }
                }
            }

            deps.push(deps_[i]);
        }

        for ( var i = 0; i < deps.length; i++ ) {
            deps[i] = '.part-checkbox[value="' + deps[i] + '"]';
        }

        deps = deps.join( ',' );
        console.log(deps);

        if ( checked ) {
            $(deps).prop( 'checked', true );
            $(deps).prop( 'disabled', true );
        } else {
            $(deps).prop( 'checked', false );
            $(deps).prop( 'disabled', false );
        }

    });

    $( '#parts-check-everything' ).change(function(e) {
        $( '.part-checkbox' ).prop( 'checked', $( this ).prop( 'checked' ) );
    });
});

jQuery(function( $ ) {
    var migrate = {};

    migrate.Export_Handler = function() {
        this.step = '';
        this.in_progress = false;
        this.last_msg = '';

        this.$progress = $( '.wpbdp-migrate-progress' );
        this.$parts = $( '.export-parts li', this.$progress );
        this.$done = $( '.wpbdp-migrate-done' );

        this.id = this.$progress.attr('data-id');
    };

    $.extend( migrate.Export_Handler.prototype, {
        start: function() {
            this.$parts.first().addClass('current');

            this.in_progress = true;
            this._advance();
        },

        _cleanup: function() {
            var t = this;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: { 'action': 'wpbdp-migrate-export-cleanup', 'export_id': t.id },
                success: function() {
                    location.href = '';
                }
            });
        },

        _fatal_error: function( msg ) {
            this.in_progress = false;

            var $error = $( '.wpbdp-migrate-error' );

            if ( msg )
                $error.find( 'p' ).text( msg );

            $( '.wpbdp-migrate-progress, .wpbdp-note' ).hide();
            $error.show();
            $( '.canceled-migration' ).show();

            $('html, body').animate({ scrollTop: 0 }, 'medium');
        },

        _advance: function() {
            var t = this;

            if ( ! t.in_progress )
                return;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: { 'action': 'wpbdp-migrate-export-do_work', 'export_id': t.id },
                success: function( res ) {
                    if ( ! res || ! res.success )
                        return t._fatal_error( res.error );

                    this.step = res.data.part;
                    this.last_msg = res.data.status;

//                    t.$parts.not( '.part-' + this.step).removeClass( 'current' );
//                    $.find( '.part-' + this.step, t.$parts ).addClass( 'current' );

                    t.$parts.filter( '.current').find( '.status-msg' ).html( this.last_msg );
                    t.$parts.removeClass('current');
                    t.$parts.filter( '.part-' + this.step ).addClass('current');

                    $.each( res.data.parts_done, function( i, v ) {
                        t.$parts.filter( '.part-' + v ).addClass( 'done' );
                    } );

                    if ( res.data.done ) {
                        t.in_progress = false;

                        var $download_link = t.$done.find('.download-link a');
                        $download_link.attr( 'href', res.data.zip.url );
                        $( '.filename', $download_link ).text(  res.data.zip.filename );
                        $( '.filesize', $download_link ).text( res.data.zip.filesize );

                        t.$done.find( '.cleanup-link a' ).click(function(e) {
                            e.preventDefault();
                            t._cleanup();
                        });

                        $download_link.click(function(e) {
                            t.$done.find( '.cleanup-link' ).fadeIn( 'fast' );
                        });

                        t.$progress.fadeOut( 'fast', function() {
                            t.$done.fadeIn( 'fast' );
                        } );

                        return;
                    }

                    t._advance();
                }
            });
        }
    } );

    if ( 0 == $( '.wpbdp-migrate-progress' ).length )
        return;

    var e = new migrate.Export_Handler();
    e.start();
});
