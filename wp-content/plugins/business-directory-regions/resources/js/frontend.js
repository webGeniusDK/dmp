/*global ajaxurl:true */
if (jQuery !== undefined) {

var WPBDP = jQuery.WPBDP = jQuery.extend({}, jQuery.WPBDP, WPBDP);

(function($, undefined) {

    $.WPBDP.Collapsible = function(element) {
        this.element = $(element);
        this.handler = this.element.find('.js-handler').eq(0);
        this.subject = this.element.find('[data-collapsible]').eq(0);
        this.setup();
    };

    $.extend($.WPBDP.Collapsible.prototype, {
        setup: function() {
            var self = this;

            self.subject.hide();
            self.toggleClass();

            self.handler.click(function(event) {
                self.toggle.apply(self, [event, this]);
            });

            if ( self.subject.attr('data-collapsible-default-mode') == 'open' ) {
                self.handler.click();
            }            
        },

        toggleClass: function() {
            if (this.subject.is(':visible')) {
				this.handler.removeClass('bdopen').addClass('bdclose');
                this.handler.find('span').removeClass('open').addClass('close');
            } else {
				this.handler.removeClass('bdclose').addClass('bdopen');
                this.handler.find('span').removeClass('close').addClass('open');
            }
        },

        toggle: function(event) {
            event.preventDefault();
            var self = this;
            self.subject.slideToggle(function() { self.toggleClass(); });
        }
    });

    $.fn.collapsible = function() {
        return this.each(function() {
            var obj = new $.WPBDP.Collapsible(this); $.noop(obj);
        });
    };

}(jQuery));

(function($, undefined) {

    $.RegionField = function(container) {
        this.container = container = $(container);
        this.select = container.find('select');
        this.form = container.closest('form');

        this.field = parseInt(this.select.attr('id').replace('wpbdp-field-', ''), 10);
        this.level = parseInt(container.attr('data-region-level'), 10);
        this.display_context = this.container.attr( 'data-display-context' );

        this.select.change($.proxy(this, 'change'));
        this.form.bind('wpbdp-region-selected', $.proxy(this, 'update'));

        var loading = '...';
        if ( ( 'undefined' !== typeof $.RegionsFrontend ) && ( 'undefined' !== typeof $.RegionsFrontend.UILoadingText ) )
            loading = $.RegionsFrontend.UILoadingText;

        this.select.after($('<span>').addClass('spinner-text').css('display', 'none').text(loading));
    };

    $.extend($.RegionField.prototype, {
        change: function() {
            var self = this, region = self.select.val();
            self.form.trigger('wpbdp-region-selected', [self.field, self.level, region]);
        },

        update: function(event, field, level, region) {
            var self = this, a, b, hidden = self.container.is(':hidden');

            if (region === 0 || self.field === field || self.level < level) {
                return;
            }

            // the visible hierarchy is an ascending ordered list of the
            // levels of the fields that are available in the current view
            levels = self.form.find('.wpbdp-region-field').map(function() {
                return parseInt($(this).attr('data-region-level'), 10);
            }).get().sort();

            updated_field_position = levels.indexOf(level);
            current_field_position = levels.indexOf(self.level);

            // this should never ever happen!... but we all know it will happen
            // just to give somebody a reason to laugh at me. If it happens,
            // I would like to think we have bigger problems than updating this
            // field's options so I'm just gonna return.
            if (updated_field_position === -1 || current_field_position === -1) {
                return;
            }

            // do not update this field if is hidden and is not the
            // next field in the visible hierarchy
            if (updated_field_position + 1 < current_field_position && hidden) {
                return;
            }
            // Get list of Regions and create a dropdown. If no regions
            // are returned replace the select dropdown with the textfield
            //this.spinner.show();
            var $spinner = self.form.find('#wpbdp-field-' + field).siblings('.spinner-text');
            $spinner.fadeIn('fast');

            $.getJSON(typeof ajaxurl === 'undefined' ? $.RegionsFrontend.ajaxurl : ajaxurl, {
                action: 'wpbdp-regions-get-regions',
                field: self.field,
                parent: region,
                level: self.level,
                display_context: self.display_context,
                category_id: self.form.find( 'input[name="category_id"]' ).val()
            }, function(response) {
                if (response.status === 'ok') {
                    $spinner.fadeOut('fast');

                    var options = $(response.html).find('option');
                    self.select.find('option').remove();
                    self.select.append( options ).val( '' );

                    // Fix for #1592
                    if ( 'undefined' !== typeof( self.select.data( 'selectpicker' ) ) ) {
                        self.select.selectpicker( 'refresh' );
                    }

                    if (options.length === 0 || (options.length === 1 && a + 1 < b)) {
                        self.container.slideUp(function() {
                            self.container.addClass('wpbdp-regions-hidden');
                        });
                    } else if (hidden) {
                        self.container.slideDown(function() {
                            self.container.removeClass('wpbdp-regions-hidden');
                        });
                    }
                } else {
                    // TODO: tell the user an error ocurred
                }

                self.form.trigger( 'wpbdp-region-selected-after' );
            });
        }
    });

}(jQuery));

(function($, undefined) {

    var regions_init = function() {
        $('.wpbdp-region-field').each(function() {
            var field = new $.RegionField(this); $.noop(field);
        });

        $('.wpbdp-region-selector').collapsible();

        var sidelist = $('.wpbdp-region-sidelist');
        sidelist.find('.js-handler').closest('li').collapsible();
        sidelist.find('a[data-url]').each(function() {
            var element = $(this);
            element.attr('href', element.attr('data-url'));
        });
    };

    $(function() {
        regions_init();
    });
    $(window).on( 'wpbdp_submit_refresh', function( event, submit, section_id ) {
        if ( 'listing_fields' == section_id ) {
            regions_init();
        }
    } );

}(jQuery));

(function($, undefined) {
    $(document).ready(function() {
        $( '.wpbdp-region-sidelist-wrapper .sidelist-menu-toggle' ).click(function() {
            $('.wpbdp-region-sidelist-wrapper').toggleClass('open');
        });

        // XXX: hack our way through Regions fields for widgets.
        $( '.wpbdp-regions-search-widget' ).each(function( i, v ) {
            var $widget = $(v);
            var all_fields = $widget.find( '.wpbdp-region-field' );
            var hidden_fields = all_fields.not( '.wpbdp-regions-hidden' ).toArray();

            hidden_fields.sort(function( a, b ) {
                return parseInt( $(a).attr( 'data-region-level' ) ) - parseInt( $(b).attr( 'data-region-level' ) );
            });

            if ( all_fields.length == 0 ) {
                return;
            }

            var first_field = all_fields.get(0);

            $( first_field ).removeClass( 'wpbdp-regions-hidden' );
            $widget.find( '.wpbdp-region-field' ).not( first_field ).addClass( 'wpbdp-regions-hidden' );
        });

        // FIXME: Yet another hack. See https://github.com/drodenbaugh/BusinessDirectoryPlugin/issues/2773.
        if ( $( '.wpbdp-region-field.wpbdp-search-filter' ).length > 0 ) {
            var search_containers = $( '.wpbdp-region-field.wpbdp-search-filter' ).parent();
            search_containers.each(function() {
                $( this ).find( '.wpbdp-region-field.wpbdp-search-filter:first' ).removeClass( 'wpbdp-regions-hidden' );
                $( this ).find( '.wpbdp-region-field.wpbdp-search-filter' ).not( ':first' ).addClass( 'wpbdp-regions-hidden' );
            });

            $( '.wpbdp-region-field.wpbdp-search-filter' ).closest( 'form' ).bind( 'wpbdp-region-selected-after', function() {

                var all_fields = $(this).find( '.wpbdp-region-field.wpbdp-search-filter' );
                var level_with_region = 0;

                all_fields.sort(function( a, b ) {
                    return parseInt( $(a).attr( 'data-region-level' ) ) - parseInt( $(b).attr( 'data-region-level' ) );
                });
                all_fields.each(function() {
                    if ( ! level_with_region && $( this ).find( 'select' ).val() ) {
                        level_with_region = parseInt( $( this ).attr( 'data-region-level' ) );
                    }
                });

                if ( ! level_with_region ) {
                    all_fields.not( ':first' ).addClass( 'wpbdp-regions-hidden' ).hide();
                }

            } );

        }

        $( '.wpbdp-region-selector').on( 'wpbdp-region-selected', function ( event, field, level, region ) {
            var region_vals = [];
            $( this ).find( '.wpbdp-region-field select' ).each( function () {
                region_vals.push( $( this ).val() );
            } );

            if ( ! region_vals[0] ) {
                $( this ).find( 'input[name="set-location"]' ).prop( 'disabled', 'disabled' ).fadeOut( 'fast' );
                return;
            }

            $( this ).find( 'input[name="set-location"]' ).prop( 'disabled', false ).fadeIn( 'slow' );

        } );
    });
}(jQuery));

}
