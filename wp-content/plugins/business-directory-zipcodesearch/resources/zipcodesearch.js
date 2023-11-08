if ( typeof jQuery !== 'undefined' ) {
    jQuery( function( $ ) {

        $( '.wpbdp-zipcodesearch-search-unit .mode-radio' ).change( function () {
            var $unit = $( this ).parents( '.wpbdp-zipcodesearch-search-unit' );
            var $distance_fields = $unit.find('.wpbdp-zipcodesearch-distance-fields');
            var val = $(this).val();

            if ('distance' == val)
                $distance_fields.removeClass('hidden');
            else
                $distance_fields.addClass('hidden');
        });

        $(window).on('wpbdp_submit_refresh', function (event, submit, section_id) {
            zip_autocomplete( '.wpbdp-form-field.wpbdp-zipcodesearch-autocomplete' );
        });

        zip_autocomplete = function ( $element ) {
            $( $element ).each(function () {
                var $field = $(this);
                var $input = $field.find('input[type="text"]');

                $input.autocomplete({
                    source: $field.attr('data-ajaxurl'),
                    minLength: 2,
                    response: function (event, ui) {
                        if (!ui.content || ui.content.length < 2) {
                            $(this).autocomplete('close');
                            $(this).siblings('.country-hint').val('');
                        }
                    },
                    select: function (event, ui) {
                        $(this).siblings('.country-hint').val(ui.item.country);
                    },
                });
            });
        };

        $(window).on( 'load', function () {
            if( $( '.zip-field.wpbdp-zipcodesearch-autocomplete, .wpbdp-form-field.wpbdp-zipcodesearch-autocomplete' ).length ) {
                zip_autocomplete( '.zip-field.wpbdp-zipcodesearch-autocomplete, .wpbdp-form-field.wpbdp-zipcodesearch-autocomplete' );
            }
        } );

    } );
}
