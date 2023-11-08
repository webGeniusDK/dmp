<?php
class WPBDP_Ratings_Field extends WPBDP_Form_Field_Type {

    public function __construct() {
        parent::__construct( 'Ratings Field' );
    }

    public function get_id() {
        return 'ratings';
    }

    public function get_supported_associations() {
        return array( 'custom' );
    }

    public function get_behavior_flags( &$field ) {
        return array( 'no-submit', 'no-delete', 'no-validation' );
    }

    public function display_field( &$field, $post_id, $display_context ) {
        global $wpbdp_ratings;

        $html = $field->html_value( $post_id );

        if ( 'listing' == $display_context ) {
            if ( $wpbdp_ratings->can_post_review( $post_id ) ) {
                $html .= '<br/>' . sprintf( '<a href="#rate-listing-form" class="rate-listing-link">%s</a>', esc_html__( 'Leave a review', 'wpbdp-ratings' ) );
            }
        }

        return parent::standard_display_wrapper( $field, $html, 'wpbdp-rating-info' );
    }

    public function get_field_value( &$field, $post_id ) {
        global $wpbdp_ratings;
        return $wpbdp_ratings->get_rating_info( $post_id );
    }

    public function get_field_html_value( &$field, $post_id, $context = '' ) {
        global $wpbdp_ratings;

		$rating = $field->value( $post_id );
		if ( ! $rating ) {
			return '';
		}

        $threshold = intval( wpbdp_get_option( 'ratings-min-ratings' ) );

        if ( $rating->count >= $threshold ) {
	        ob_start();
			$wpbdp_ratings->get_stars(
				array(
					'review'   => $rating->count > 0 ? $rating->average : 0,
					'readonly' => true,
				)
			);

	        $html = ob_get_contents();
	        ob_end_clean();
			$label = apply_filters( 'wpbdp_rating_count_label', $rating->count );
			$html .= sprintf( '<span class="count">(<span class="val">%s</span>)</span>', esc_html( $label ) );
        } else {
            $html = '<span>' . esc_html__( '(More feedback needed)', 'wpbdp-ratings' ) . '</span>';
        }

        return $html;
    }

    public function get_field_plain_value( &$field, $post_id ) {
        $value = $field->value( $post_id );

		if ( ! $value ) {
            return '';
		}

        return $value->average;
    }

	public function render_field_inner( &$field, $value, $context, &$extra = null, $field_settings = array() ) {
		global $wpbdp_ratings;

		if ( 'search' !== $context ) {
			return '';
		}

		add_action( 'wp_footer', array( &$wpbdp_ratings, '_enqueue_scripts' ), 1 );

		$vars['selected'] = (float) $value;
		$vars['readonly'] = false;
		$vars['field_id'] = $field->get_id();
		return wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/stars.php', $vars );
	}

	public function get_schema_org( $field, $post_id ) {
		$rating = $field->value( $post_id );

		if ( ! $rating->count ) {
			return;
		}

		$schema = array(
			'aggregateRating' => array(
				'@type'       => 'AggregateRating',
				'worstRating' => '1',
				'bestRating'  => '5',
				'ratingValue' => $rating->count > 0 ? $rating->average : 0.0,
				'reviewCount' => $rating->count,
				'ratingCount' => $rating->count,
			),
			'review'          => array(),
		);

		foreach ( wpbdp_ratings()->get_reviews( $post_id ) as $review ) {
			$author = ( $review->user_id == 0 ) ? trim( $review->user_name ) : trim( get_the_author_meta( 'display_name', $review->user_id ) );

			$schema['review'][] = array(
				'@type'         => 'Review',
				'author'        => array(
					'@type' => 'Person',
					'name'  => $author ? $author : __( 'Anonymous', 'wpbdp-ratings' ),
				),
				'datePublished' => $review->created_on,
				'reviewBody'    => $review->comment,
				'reviewRating'  => array(
					'@type'       => 'Rating',
					'ratingValue' => $review->rating,
					'bestRating'  => 5,
					'worstRating' => 0,
				),
			);
		}

		return $schema;
	}

}

