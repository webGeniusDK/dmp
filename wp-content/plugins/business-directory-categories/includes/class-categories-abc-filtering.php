<?php
/**
 * ABC Filtering Bar
 *
 * @package Categories/Includes
 */

// phpcs:disable

/**
 * This class provides all the functionality for the ABC filtering bar.
 *
 * @since 3.5.1
 * @SuppressWarnings(PHPMD)
 */
class WPBDP_Categories_ABC_Filtering {

    const VALID_CHARS = '_0abcdefghijklmnopqrstuvwxyz';
    const LETTERS     = 'abcdefghijklmnopqrstuvwxyz';

    /**
     * WPBDP_Categories_ABC_Filtering constructor.
     */
    public function __construct() {
        add_filter( 'wpbdp_listing_sort_options_html', array( $this, 'add_filter_to_listings' ) );
        add_filter( 'wpbdp_query_clauses', array( &$this, 'query_letter_filter' ) );
        add_action( 'wpbdp_enqueue_scripts', array( &$this, 'enqueue_styles' ) );
    }

    function add_filter_to_listings( $html ) {
        $current_query = wpbdp_current_query();

        if ( ! $current_query ) {
            return $html;
        }

        return $this->abc_filter_html() . $html;
    }

    function query_letter_filter( $clauses ) {
        global $wpdb;

        $current_letter = $this->get_current_letter();

        if ( false === $current_letter ) {
            return $clauses;
        }

        switch ( $current_letter ) {
            case '_':
                $clauses['where'] .= " AND ( LOWER(LEFT({$wpdb->posts}.post_title, 1))  ) NOT REGEXP '[a-zA-Z0-9]+'";
                break;

            case '0':
                $clauses['where'] .= " AND ( LOWER(LEFT({$wpdb->posts}.post_title, 1))  ) REGEXP '[0-9]+'";
                break;

            default:
                $clauses['where'] .= $wpdb->prepare(
                    " AND ( LOWER(LEFT({$wpdb->posts}.post_title, 1)) = %s )",
                    $current_letter
                );

                break;
        }

        return $clauses;
    }

    function enqueue_styles() {
        wp_enqueue_style(
            'wpbdp-abc-filtering',
            plugins_url( '/resources/abc.css', dirname( __FILE__ ) ),
            array(),
            WPBDP_CategoriesModule::VERSION
        );
    }

    private function get_current_letter() {
        $letter = array_key_exists( 'l', $_GET ) ? trim( strtolower( $_GET['l'] ) ) : false;

        if ( false === $letter ) {
            return false;
        }

        $valid_letters = apply_filters( 'wpbdp_abc_filter_letters', preg_split( '//u', self::VALID_CHARS, -1, PREG_SPLIT_NO_EMPTY ) );

        if ( ! in_array( $letter, $valid_letters, true ) ) {
            return false;
        }

        return $letter;
    }

    private function abc_filter_html() {
        $current_letter = $this->get_current_letter();

        $letters      = array();
        $letters['_'] = array( '#', 1 );
        $letters['0'] = array( '0-9', 1 );

        $valid_letters = apply_filters( 'wpbdp_abc_filter_letters', preg_split( '//u', self::LETTERS, -1, PREG_SPLIT_NO_EMPTY ) );

        foreach ( $valid_letters as $letter ) {
            $letters[ $letter ] = array( strtoupper( $letter ), 1 );

            if ( function_exists( 'mb_strtoupper' ) ) {
                $letters[ $letter ] = array( mb_strtoupper( $letter ), 1 );
            }
        }

        $html  = '';
        $html .= '<div class="wpbdp-abc-filtering wpbdp-hide-on-mobile">';

        foreach ( $letters as $letter => $info ) {
            $html .= sprintf( '<span class="letter %s">', ( $letter === $current_letter ? 'current' : '' ) );

            if ( $info[1] > 0 ) {
                $url   = add_query_arg( 'l', $letter, get_pagenum_link( 1, false ) );
                $html .= sprintf( '<a href="%s" rel="nofollow">%s</a>', esc_url( $url ), $info[0] );
            } else {
                $html .= $info[0];
            }

            $html .= '</span>';
        }

        if ( $current_letter ) {
            $html .= sprintf(
                '<a href="%s" class="reset">%s</a>',
                remove_query_arg( 'l' ),
                __( '(Reset)', 'wpbdp-categories' )
            );
        }

        $html .= '</div>';

        return $html;
    }

}
