<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BusinessDirectory_RatingsReviewTable extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'review pending', 'wpbdp-ratings' ),
				'plural'   => __( 'reviews pending', 'wpbdp-ratings' ),
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'user_ip' => __( 'User/IP', 'wpbdp-ratings' ),
			'rating'  => __( 'Rating', 'wpbdp-ratings' ),
			'comment' => __( 'Comment', 'wpbdp-ratings' ),
			'listing' => __( 'Listing', 'wpbdp-ratings' ),
		);
	}

	/**
	 * @since 5.3
	 */
	protected function get_views() {
		$links = array();
		if ( ! wpbdp_get_option( 'ratings-require-approval' ) ) {
			return $links;
		}

		$statuses = array(
			''        => __( 'All', 'wpbdp-ratings' ),
			'pending' => __( 'Pending', 'wpbdp-ratings' ),
		);

		$current = wpbdp_get_var( array( 'param' => 'status' ) );

		foreach ( $statuses as $status => $name ) {
			$class = $status === $current ? ' class="current"' : '';

			$links[ $status ] = '<a href="' . esc_url( add_query_arg( compact( 'status' ) ) ) . '" ' . $class . '>' . esc_html( $name ) . '</a>';

			unset( $status, $name );
		}

		return $links;
	}

	public function prepare_items() {
		global $wpdb;

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$s_query = $this->filter_query();

		$this->items = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT * FROM {$wpdb->prefix}wpbdp_ratings {$s_query} ORDER BY id DESC"
		);
	}

	/**
	 * Get URL parameters and use them to filter the results.
	 *
	 * @since 5.3
	 */
	private function filter_query() {
		global $wpdb;

		$s_query = ' WHERE 1=1';

		$s = wpbdp_get_var( array( 'param' => 's' ) );
		if ( $s != '' ) {
			preg_match_all( '/".*?("|$)|((?<=[\\s",+])|^)[^\\s",+]+/', $s, $matches );
			$search_terms = array_map( 'trim', $matches[0] );
			foreach ( (array) $search_terms as $term ) {
				$s_query .= $wpdb->prepare(
					' AND user_name LIKE %s OR user_email LIKE %s OR comment LIKE %s OR id = %s',
					$term,
					$term,
					$term,
					$term,
				);
				unset( $term );
			}
		}

		$status = wpbdp_get_var( array( 'param' => 'status' ) );
		if ( $status === 'pending' ) {
			$s_query .= $wpdb->prepare(
				' AND approved = %s',
				0
			);
		}

		return $s_query;
	}

	/* Rows */
	public function column_user_ip( $row ) {
		$html = '';

		if ( $row->user_id == 0 ) {
			$html .= '<b>' . esc_attr( $row->user_name ) . '</b>';
			$html .= '<br />' . esc_attr( $row->user_email );
		} else {
			$html .= '<b>' . get_the_author_meta( 'display_name', $row->user_id ) . '</b>';
			$html .= '<br />' . get_the_author_meta( 'user_email', $row->user_id );
		}
		$html .= '<br />' . esc_html( $row->ip_address );

		return $html;
	}

	public function column_rating( $row ) {
		global $wpbdp_ratings;
		ob_start();
		$wpbdp_ratings->get_stars(
			array(
				'review'   => $row,
				'readonly' => true,
			)
		);
		return ob_get_clean();
	}

	public function column_comment( $row ) {
		$html  = '';

		$html .= '<div class="submitted-on">';

		/* translators: %s is the date */
		$html .= sprintf( __( 'Submitted on %s', 'wpbdp-ratings' ), date_i18n( get_option( 'date_format' ), strtotime( $row->created_on ) ) );
		$html .= '</div>';

		$html .= '<p id="review-' . esc_attr( $row->id ) . '">' . esc_attr( $row->comment ) . '</p>';

		$actions = array();
		if ( ! $row->approved ) {
			$actions['approve_rating'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					add_query_arg(
						array(
							'action' => 'approve',
							'id'     => $row->id,
						)
					)
				),
				__( 'Approve', 'wpbdp-ratings' )
			);
		}

		$actions['delete'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url(
				add_query_arg(
					array(
						'action' => 'delete',
						'id' => $row->id,
					)
				)
			),
			__( 'Delete', 'wpbdp-ratings' )
		);
		$html .= $this->row_actions( $actions );

		return $html;
	}

	public function column_listing( $row ) {
		return sprintf( '<a href="%s">%s</a>', get_permalink( $row->listing_id ), get_the_title( $row->listing_id ) );
	}

}
