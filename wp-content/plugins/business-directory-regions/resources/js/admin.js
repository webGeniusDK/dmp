/*global ajaxurl:true */
if (jQuery !== undefined) {

var WPBDP = jQuery.WPBDP = jQuery.extend({}, jQuery.WPBDP, WPBDP);

(function($, undefined) {

    $.WPBDP.Regions = function() { };

    $.extend($.WPBDP.Regions.prototype, {
        onSuccess: function(callback) {
            return function(response) {
                if (response && response.success) {
                    if ($.isFunction(callback)) {
                        callback(response);
                    }
                }
                // TODO: show errors
            };
        }
    });

    // enable custom admin styles
    $('#wpcontent').addClass('wpbdp-regions');
    // remove description and slug fields
    $('#tag-description, #tag-slug, #_parent').closest('.form-field').hide();


    $(function() {
        var regions = new $.WPBDP.Regions();

        // handle Enabled
        var selector = '.enabled .row-actions a';
        $('#the-list').delegate(selector, 'click', function(event) {
            event.preventDefault();

            var link = $(this),
                table = link.closest('table'),
                action = link.closest('span').attr('class'),
                id = parseInt(link.closest('tr').attr('id').replace('tag-', ''), 10),
                selectors, html;

            selectors = {
                'enabled': 'span.enable, span.disable'
            };

            $.getJSON(ajaxurl, {
                action: 'wpbdp-regions-' + action,
                region: id
            }, regions.onSuccess(function(response) {
                if (response.updated && response.updated.length) {
                    $.each(response.updated, function(k, id) {
                        var row = table.find('#tag-' + id), column, content;

                        html = response.html;

                        for (column in html) {
                            if (html.hasOwnProperty(column)) {
                                content = html[column].replace(/tag_ID=\d+/, 'tag_ID=' + id);
                                row.find(selectors[column]).closest('td').html(content);
                            }
                        }
                    });
                } else {
                    link.closest('td').html(response.html);
                }
            }));
        })

        .delegate('.name .row-actions .add-child', 'click', function(event) {
            event.preventDefault();

			var row = $(this).closest('tr'),
				id = row.attr('id').replace('tag-', ''),
				tabs = $('#wpbdp-regions-tabs'),
				parentInput = tabs.find('[name="parent_name"]'),
				name;

			tabs.find('[name="parent"]').val(id);
			parentInput.val( row.find('#inline_' + id + ' .name').text() );
            name = tabs.find('[name="tag-name"]').filter(':visible').focus();

			parentInput.add(name).stop().animate({backgroundColor: '#FFFBCC'})
                                   .animate({backgroundColor: 'transparent'}, 3000);
        })

        // Show Sub-Regions when someones clicks the name of a Region.
        .delegate('.row-title', 'click', function(event) {
            event.preventDefault();
            var link = $(this).closest('td').find('.row-actions .children a');
            document.location = link.attr('href');
        })
        .delegate( '.display-link', 'click', function(e) {
            e.preventDefault();
            var url = $(this).attr('href');
            prompt( 'URL:', url );
        });
    });

    $(function() {
        /* insert settings form */
        var settings = $($.RegionsData.templates['settings-form']),
            postbox = settings.find('.postbox');
        $('#wpbody-content .wrap').prepend(settings);
        postbox.addClass('closed').find('.handlediv').click(function() {
            postbox.toggleClass('closed');
        });

        /* insert bulk actions */
        var actions = $($.RegionsData.templates['bulk-actions']);
        actions.appendTo('select[name="action"]');
        actions.clone().appendTo('select[name="action2"]');

        /* insert localize form */
        var form = $($.RegionsData.templates.views);
        form.insertAfter('.wpbdp-content-area-body .search-form');

        /* insert form to add multiple regions */
        var tabs = $($.RegionsData.templates['add-regions-form']),
            multiple = tabs.find('form'),
            single = $('#addtag'),
            container = single.closest('.form-wrap');

        // Setup autocomplete for region parent field.
        var ac_fields = [ {
            'field': multiple.find( 'input[name="parent"]' ),
            'display': multiple.find( 'input[name="parent_name"]' )
        } ];

        $.each( ac_fields, function( i, ac_field ) {
            ac_field.display.autocomplete({
                source: function( request, response ) {
                    $.getJSON( ajaxurl, { action: 'wpbdp-regions-admin-autocomplete', term: request.term }, function( res ) {
                        if ( res.success )
                            response( res.data.items );
                    });
                },

                select: function( event, ui ) {
                    if ( ! ui.item )
                        return;

                    ac_field.field.val( ui.item.term_id );
                },

                focus: function( event, ui ) {
                    $( '.ui-autocomplete > li' ).attr( 'title', 'Region ID: ' + ui.item.term_id + ', Region Slug: ' + ui.item.slug + ( ui.item.parent ? ', Parent: ' + ui.item.parent_name : '' ) );
                }
            });

            ac_field.display.focusout(function(e) {
                var txt = $.trim( $( this ).val() );

                if ( "" == txt )
                    ac_field.field.val( 0 );
            });
        } );

        // create tabs
        container.append(tabs);
        single.remove();
    });

}(jQuery));

}
