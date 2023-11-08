<?php
/**
 * Exporter for ratings personal data.
 *
 * @package Includes/Personal Data Exporter
 */

/**
 * Class WPBDP_RatingsPersonalDataExporter Exporter for ratings personal data.
 *
 * @since 5.1
 */
class WPBDP_RatingsPersonalDataProvider implements WPBDP_PersonalDataProviderInterface {

    /**
     * @var $data_formatter
     */
    private $data_formatter;

    /**
     * WPBDP_RatingsPersonalDataProvider constructor.
     */
    public function __construct( $data_formatter ) {
        $this->data_formatter = $data_formatter;
    }

    /**
     * @return int
     */
    public function get_page_size() {
        return 10;
    }

    /**
     * @param $user
     * @param $email_address
     * @param $page
     * @return array
     */
    public function get_objects( $user, $email_address, $page ) {
        global $wpdb;

        $items_per_page = $this->get_page_size();

        return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpbdp_ratings WHERE user_id = %d OR user_email = %s ORDER BY created_on ASC LIMIT %d OFFSET %d",
				$user->ID,
				$email_address,
				$items_per_page,
				( $page - 1 ) * $items_per_page
			)
		);
    }

    /**
     * @param $ratings
     * @return array
     */
    public function export_objects( $ratings ) {
        $items = array(
            'ID'             => __( 'Rating ID', 'wpbdp-ratings' ),
            'rating_autor'   => __( 'Rating Author', 'wpbdp-ratings' ),
            'rating_email'   => __( 'Rating Author Email', 'wpbdp-ratings' ),
            'rating_ip'      => __( 'Rating Author IP', 'wpbdp-ratings' ),
            'rating_date'    => __( 'Rating Date', 'wpbdp-ratings' ),
            'rating_value'   => __( 'Rating Value', 'wpbdp-ratings' ),
            'rating_content' => __( 'Rating Content', 'wpbdp-ratings' ),
            'rating_url'     => __( 'Rated Listing', 'wpbdp-ratings' ),
        );

        $export_items = array();

        foreach ( $ratings as $rating ) {
            $data = $this->data_formatter->format_data( $items, $this->get_rating_properties( $rating ) );

            $export_items[] = array(
                'group_id'    => 'wpbdp-ratings',
                'group_label' => __( 'Business Directory Ratings', 'wpbdp-ratings' ),
                'item_id'     => "wpbdp-rating-{$rating->id}",
                'data'        => $data,
            );

        }

        return $export_items;
    }

    /**
     * @param $rating
     * @return mixed
     */
    private function get_rating_properties( $rating ) {
        $user = $rating->user_id ? get_userdata( $rating->user_id ) : null;

        $properties = array(
            'ID'             => $rating->id,
            'rating_autor'   => $user ? ( $user->user_nicename ? $user->user_nicename : $user->user_login ) : $rating->user_name,
            'rating_email'   => $user ? ( $user->user_nicename ? $user->user_nicename : $user->user_login ) : $rating->user_name,
            'rating_ip'      => $rating->ip_address,
            'rating_date'    => $rating->created_on,
            'rating_value'   => $rating->rating,
            'rating_content' => $rating->comment,
            'rating_url'     => get_permalink( $rating->listing_id ),
        );

        return $properties;
    }

    /**
     * @param $email_address
     * @param int $page
     * @return array
     */
    public function erase_personal_data( $email_address, $page = 1 ) {
        $user    = get_user_by( 'email', $email_address );
        $objects = $this->get_objects( $user, $email_address, $page );
        $result  = $this->erase_objects( $objects );
        return array(
            'items_removed'  => $result['items_removed'],
            'items_retained' => $result['items_retained'],
            'messages'       => $result['messages'],
            'done'           => count( $objects ) < $this->data_eraser->get_page_size(),
        );
    }

    /**
     * @param $ratings
     * @return array|mixed
     */
    public function erase_objects( $ratings ) {
        global $wpdb;
        $items_removed  = false;
        $items_retained = false;
        $messages       = array();
        foreach ( $ratings as $rating ) {
            if ( $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpbdp_ratings WHERE id = %d", $rating->id ) ) !== false ) {
                $items_removed = true;
                continue;
            }
            $items_retained = true;
            $message = __( 'An unknown error occurred while trying to delete information for review {review_id}.', 'wpbdp-ratings' );
            $message = str_replace( '{listing_id}', $rating->id, $message );
            $messages[] = $message;
        }
        return compact( 'items_removed', 'items_retained', 'messages' );
    }
}
