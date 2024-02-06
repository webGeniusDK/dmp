jQuery(function($) {
    var migrate = {};

    migrate.Import_Handler = function() {
        this.step = '';
        this.in_progress = false;
        this.status_msg = '';

        this.$progress = $( '.wpbdp-migrate-progress' );
        this.$parts = $( '.export-parts li', this.$progress );
        this.$done = $( '.wpbdp-migrate-done' );

        this.id = this.$progress.attr('data-id');
    };

    $.extend( migrate.Import_Handler.prototype, {
        start: function() {
            this.$parts.first().addClass('current');

            this.in_progress = true;
            this._advance();
        },

        _fatal_error: function( msg ) {
            this.in_progress = false;

            var $error = $( '.wpbdp-migrate-error' );

            if ( msg )
                $error.find( 'p' ).text( msg );

            $( '.wpbdp-migrate-progress, .wpbdp-note, .wpbdp-migrate-pack-info' ).hide();
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
                data: { 'action': 'wpbdp-migrate-import-do_work', 'import_id': t.id },
                success: function( res ) {
                    if ( ! res || ! res.success )
                        return t._fatal_error( res.error );

                    this.step = res.data.part;
                    this.status_msg = res.message;

                    t.$progress.find( '.status-msg p' ).html( this.status_msg );

                    t.$parts.removeClass('current');
                    t.$parts.filter( '.part-' + this.step ).addClass('current');

                    $.each( res.data.parts_done, function( i, v ) {
                        t.$parts.filter( '.part-' + v ).addClass( 'done' );
                    } );

                    if ( res.data.done ) {
                        t.in_progress = false;

                        $( '.wpbdp-note' ).hide();
                        return;
                    }

                    t._advance();
                }
            });
        }
    } );

    if ( 0 == $( '.wpbdp-migrate-progress' ).length )
        return;

    var i = new migrate.Import_Handler();
    i.start();
});
