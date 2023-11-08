<?php
/**
 * ABC Filtering Links
 */

/**
 * This class provides all the functionality for the ABC filtering bar.
 *
 * @since 5.0
 */
class WPBDP_ABC_Filtering {

	public function __construct() {
		add_action( 'wpbdp_register_settings', array( &$this, 'register_settings' ), 20 );
		add_action( 'wpbdp_modules_init', array( &$this, 'load_hooks' ) );

		$this->skip_existing_features();
	}

	public function load_hooks() {
		if ( ! wpbdp_get_option( 'abc-filtering' ) ) {
			return;
		}

		add_filter( 'wpbdp_listing_sort_options_html', array( $this, 'add_filter_to_listings' ) );
		add_filter( 'wpbdp_query_clauses', array( &$this, 'query_letter_filter' ) );
	}

	public function register_settings( $settings ) {
		// Remove if the settings exists so we can include it in a better spot.
		if ( is_callable( array( $settings, 'deregister_setting' ) ) ) {
			$settings->deregister_setting( 'abc-filtering' );
		}

		wpbdp_register_setting(
			array(
				'id'      => 'abc-filtering',
				'name'    => __( 'ABC filtering', 'wpbdp-pro' ),
				'type'    => 'checkbox',
				'default' => false,
				'desc'    => __( 'Display links above listings for alphabetic filtering', 'wpbdp-pro' ),
				'group'   => 'listings/sorting',
			)
		);
	}

	public function add_filter_to_listings( $html ) {
		$current_query = wpbdp_current_query();

		if ( ! $current_query ) {
			return $html;
		}

		return $this->abc_filter_html() . $html;
	}

	public function query_letter_filter( $clauses ) {
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

	private function get_current_letter() {
		$letter = array_key_exists( 'l', $_GET ) ? trim( strtolower( sanitize_text_field( wp_unslash( $_GET['l'] ) ) ) ) : false;

		if ( false === $letter ) {
			return false;
		}

		$letters = $this->get_letters();

		if ( ! isset( $letters[ $letter ] ) ) {
			return false;
		}

		return $letter;
	}

	private function abc_filter_html() {
		$letters        = $this->get_letters();
		$current_letter = $this->get_current_letter();

		$html  = '';
		$html .= '<div class="wpbdp-abc-filtering">';

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
				__( '(Reset)', 'wpbdp-pro' )
			);
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Get the letters to display in the ABC filtering bar.
	 *
	 * @since 5.5
	 */
	public function get_letters() {
		$letters = array();

		$letters['_'] = array( '#', 1 );
		$letters['0'] = array( '0-9', 1 );

		/* translators: ABC filtering letters. The string should contain only characters. */
		$alphabet = __( 'abcdefghijklmnopqrstuvwxyz', 'wpbdp-pro' );

		// Create an array of alphabet letters.
		$valid_letters = preg_split( '//u', $alphabet, 0, PREG_SPLIT_NO_EMPTY );

		/**
		 * ABC filtering. The string should contain only characters.
		 *
		 * @param string $valid_letters The alphabet.
		 */
		$valid_letters = apply_filters( 'wpbdp_abc_filter_letters', $valid_letters );

		foreach ( $valid_letters as $letter ) {
			$letters[ $letter ] = array( strtoupper( $letter ), 1 );

			if ( function_exists( 'mb_strtoupper' ) ) {
				$letters[ $letter ] = array( mb_strtoupper( $letter ), 1 );
			}
		}

		return $letters;
	}

	/**
	 * Load ABC filtering from this plugin.
	 */
	private function skip_existing_features() {
		if ( class_exists( 'WPBDP_CategoriesModule' ) ) {
			$instance = WPBDP_CategoriesModule::instance();
			remove_action( 'wpbdp_modules_init', array( $instance, '_init_abc' ) );
		}
	}

	/**
	 * Auto-load in-accessible properties on demand.
	 *
	 * @since 5.5
	 *
	 * @param mixed $key Key name.
	 * @return mixed
	 */
	public function __get( $key ) {
		switch ( $key ) {
			case 'letters':
				_doing_it_wrong( 'WPBDP_ABC_Filtering->letters', 'This property is no longer available. Now we use an array of letters and you can get the array by WPBDP_ABC_Filtering->get_letters()', '5.5' );
				return $this->get_legacy_letters();
			case 'valid_chars':
				_doing_it_wrong( 'WPBDP_ABC_Filtering->valid_chars', 'This property is no longer available. Now we use an array of letters and you can get the array by WPBDP_ABC_Filtering->get_letters()', '5.5' );
				return $this->get_legacy_valid_chars();
		}
	}

	/**
	 * Get the aphabetic characters.
	 * This is used to keep compatibility with the old ABC filtering.
	 *
	 * @since 5.5
	 */
	private function get_legacy_letters() {
		return 'abcdefghijklmnopqrstuvwxyz';
	}

	/**
	 * Get the valid characters.
	 * This is used to keep compatibility with the old ABC filtering.
	 *
	 * @since 5.5
	 */
	private function get_legacy_valid_chars() {
		return '_0abcdefghijklmnopqrstuvwxyz';
	}
}
