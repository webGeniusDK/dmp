if ( typeof( WPBDP ) == 'undefined' ) {
    WPBDP = {};
}

if ( typeof( WPBDP_ratings ) === 'undefined' ) {
    WPBDP.ratings = {};
} else {
    WPBDP.ratings = WPBDP_ratings;
}

WPBDP.ratings.handleDelete = function(e) {
    e.preventDefault();

    var $rating = jQuery(this).parents('.rating');
    var rating_id = $rating.attr('data-id');

    jQuery.post(WPBDP.ratings._config.ajaxurl, {action: "wpbdp-ratings", a: "delete", id: rating_id}, function(res){
        if (res.success) {
            WPBDP.ratings.updateRating($rating.attr('data-listing-id'));
            $rating.fadeOut('fast', function(){
				$rating.remove();
			});
        } else {
            alert(res.msg);
        }
    }, 'json');
};

WPBDP.ratings.handleEdit = function(e) {
    e.preventDefault();

    var $rating = jQuery(this).parents('.rating'),
		$editform = jQuery('.rating-comment-edit', $rating),
		cancelLink = this.nextElementSibling;

	this.style.display = 'none';
	if ( cancelLink !== null ) {
		this.nextElementSibling.style.display = 'inline'; // show cancel
	}

    jQuery('.rating-comment', $rating).toggle();
    $editform.toggle();
};

WPBDP.ratings.cancelEdit = function(e) {
    e.preventDefault();

    var $rating = jQuery(this).parents('.rating'),
		cancelLink = this.previousElementSibling;

	this.style.display = 'none';
	if ( cancelLink !== null ) {
		this.previousElementSibling.style.display = 'inline'; // show edit
	}

    jQuery('.rating-comment', $rating).show();
    jQuery('.rating-comment-edit', $rating).hide();
};

WPBDP.ratings.saveEdit = function(e) {
    e.preventDefault();

    var $rating = jQuery(this).parents('.rating');
    var comment = jQuery('.rating-comment-edit textarea', $rating).val();

    jQuery.post(
		WPBDP.ratings._config.ajaxurl,
		{action: "wpbdp-ratings", a: "edit", id: $rating.attr('data-id'), comment: comment},
		function(res){
        if (res.success) {
            jQuery('.rating-comment-edit textarea', $rating).val(res.comment);
            jQuery('.rating-comment', $rating).html(res.html).show();
            jQuery('.rating-comment-edit, .cancel-button', $rating).hide();
			jQuery('.edit', $rating).show();
        } else {
            alert(res.msg);
        }
    }, 'json');
};

WPBDP.ratings.updateRating = function(post_id) {
    jQuery.post(
		WPBDP.ratings._config.ajaxurl,
		{action: "wpbdp-ratings", a: "info", listing_id: post_id}, function(res) {
        if (res.success) {
            jQuery('.wpbdp-rating-info span.count .val').text(res.info.count);

            if (res.info.count == 0)
                jQuery('.wpbdp-ratings-reviews .no-reviews-message').show();
        } else {
            alert(res.msg);
        }
    }, 'json');
};

WPBDP.ratings.init = function() {

	function loadStars() {
		/*jshint validthis:true */
		updateStars( this );
	}

	function hoverStars() {
		/*jshint validthis:true */
		var input = this.previousElementSibling;
		updateStars( input );
	}

	function updateStars( hovered ) {
		var starGroup = hovered.parentElement,
			stars = starGroup.children,
			current = parseInt( hovered.value ),
			starClass = 'bd-star-rating',
			selectLabel = false;

		starGroup.className += ' wpbdp-star-hovered';
		for ( var i = 0; i < stars.length; i++ ) {
			if ( typeof stars[ i ].className !== 'undefined' && stars[ i ].className.indexOf( starClass ) > -1 ) {
				if ( selectLabel ) {
					stars[ i ].className += ' bd-star-rating-hover';
				} else {
					stars[ i ].classList.remove( 'bd-star-rating-hover', 'bd-star-rating-on' );
				}
			} else {
				selectLabel = ( parseInt( stars[ i ].value ) <= current );
			}
		}
	}

	function unhoverStars() {
		/*jshint validthis:true */
		var input = this.previousElementSibling,
			starGroup = input.parentElement;
		starGroup.classList.remove( 'wpbdp-star-hovered' );
		var stars = starGroup.childNodes;
		var selected = jQuery( starGroup ).find( 'input:checked' ).attr( 'id' );
		var isSelected = '';

		for ( var i = stars.length - 1; i > 0; i-- ) {
			if ( typeof stars[ i ].className !== 'undefined' && stars[ i ].className.indexOf( 'bd-star-rating' ) > -1 ) {
				stars[ i ].classList.remove( 'bd-star-rating-hover' );
				if ( isSelected === '' && typeof selected !== 'undefined' && stars[ i ].getAttribute( 'for' ) == selected ) {
					isSelected = ' bd-star-rating-on';
				}
				if ( isSelected !== '' ) {
					stars[ i ].className += isSelected;
				}
			}
		}
	}

	/* Add review form (admin) */
	function newAdminRating(e) {
	    e.preventDefault();
	    jQuery(this).hide();
	    jQuery('#wpbdp-ratings-admin-post-review .form').fadeIn();
	}

    function saveAdminRating(e) {
        e.preventDefault();

        var request = {
            "action": "wpbdp-ratings-add",
            "rating[listing_id]": jQuery('input[name="wpbdp_ratings_rating[listing_id]"]').val(),
            "rating[user_name]": jQuery('input[name="wpbdp_ratings_rating[user_name]"]').val(),
            "rating[rating]": jQuery('input[name="score"]:checked').val(),
            "rating[comment]": jQuery('textarea[name="wpbdp_ratings_rating[comment]"]').val()
        };

        jQuery.post(
			WPBDP.ratings._config.ajaxurl,
			request,
			function(response){
            if (response.ok) {
                var $new = jQuery(response.html);

                jQuery('#wpbdp-ratings tr.no-items').hide();
                jQuery('#wpbdp-ratings table').prepend($new);

				jQuery('#wpbdp-ratings-admin-post-review .form').hide();
                jQuery('#wpbdp-ratings-admin-post-review input[type=text], #wpbdp-ratings-admin-post-review textarea').val('');
				jQuery('#wpbdp-ratings-admin-post-review input[type=radio]').prop( 'checked', false );
				jQuery('#wpbdp-ratings-admin-post-review .bd-star-rating-on').removeClass('bd-star-rating-on');

                jQuery('#wpbdp-ratings-admin-post-review .form a.button-secondary').click();
            } else {
                alert(response.errormsg ? response.errormsg : 'Unknown Error');
            }
        }, 'json');

        jQuery('#wpbdp-ratings-admin-post-review .add-review-link').show();

    }

	jQuery( document ).on( 'mouseenter click', '.wpbdp-star-group input', loadStars );
	jQuery( document ).on( 'mouseenter', '.wpbdp-star-group .bd-star-rating:not(.bd-star-rating-readonly)', hoverStars );
	jQuery( document ).on( 'mouseleave', '.wpbdp-star-group .bd-star-rating:not(.bd-star-rating-readonly)', unhoverStars );

	jQuery( document ).on( 'click', '#wpbdp-search-form .reset', function() {
		jQuery( '.wpbdp-star-group .bd-star-rating' ).removeClass( 'bd-star-rating-on' )
	} );

	jQuery( document ).on( 'click', '#wpbdp-ratings-admin-post-review .add-review-link', newAdminRating );
	jQuery( document ).on( 'click', '#wpbdp-ratings-admin-post-review .form a.wpbdp-ratings-add-btn', saveAdminRating );

    // Edit actions
    jQuery( document ).on( 'click', '.listing-ratings .edit-actions .edit', WPBDP.ratings.handleEdit );
    jQuery( document ).on( 'click', '.listing-ratings .edit-actions .delete' , WPBDP.ratings.handleDelete );

	jQuery( document ).on( 'click', '.listing-ratings .cancel-button' , WPBDP.ratings.cancelEdit );
	jQuery( document ).on( 'click', '.listing-ratings .rating-comment-edit input.save-button' , WPBDP.ratings.saveEdit );

    jQuery( document ).on( 'click','.show-review-form-button' , function() {
        jQuery( '.review-form' ).toggleClass( 'wpbdp-hide-on-mobile' );
    } );

	/**
	 * Leave a review event handler.
	 *
	 * Displays the review form.
	 */
	 jQuery( 'a.rate-listing-link' ).on( 'click', function ( e ) {
		var $reviewForm = jQuery( '.review-form' );

		// If there is no container tab element for the review form, return.
		if ( $reviewForm.is( ':visible' ) ) {
			return;
		}

		var $reviewFormContainerTab = $reviewForm.closest( '.ui-tabs-panel' );

		if ( $reviewFormContainerTab.length > 0 ) {
			// Also trigger the tab the form is in.
			var tabId = $reviewFormContainerTab.attr( 'aria-labelledby' );
			if ( typeof tabId !== 'undefined' ) {
				$reviewFormContainerTab = jQuery( '#' + tabId );
				if ( $reviewFormContainerTab.length > 0 ) {
					$reviewFormContainerTab.trigger( 'click' );
				}
			}
		}
	} );
};

jQuery(function($){
    WPBDP.ratings.init();
});
