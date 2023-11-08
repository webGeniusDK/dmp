<?php
/**
 * Ratings Privacy Policy
 *
 * @package Includes/Privacy Policy
 * @since 5.1
 */

if ( defined( 'WPBDP_INC' ) ) {
    require_once WPBDP_INC . 'admin/interface-personal-data-provider.php';
	$formatter = WPBDP_INC . 'admin/helpers/class-data-formatter.php';
	if ( ! file_exists( $formatter ) ) {
		$formatter = WPBDP_INC . 'admin/class-data-formatter.php';
	}
	require_once $formatter;
    require_once WPBDP_RATINGS_PLUGIN_DIR . 'includes/class-ratings-personal-data-provider.php';
}
/**
 * Class WPBDP_Ratings_Privacy_Policy
 *
 * @since 5.1
 */
class WPBDP_Ratings_Privacy_Policy {

    /**
     * @var int
     */
    public $items_per_page = 10;

    /**
     * WPBDP_Privacy_Policy constructor.
     */
    public function __construct() {
        add_action( 'wpbdp_privacy_policy_content', array( $this, 'get_privacy_policy_content' ) );
        add_filter( 'wpbdp_modules_personal_data_exporters', array( $this, 'register_personal_data_exporters' ) );
        add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_personal_data_erasers' ) );
    }

    /**
     * Echoes privacy policy content suggestion for ratings module.
     */
    public function get_privacy_policy_content() {
        echo '<h4>' . esc_html__( 'Ratings', 'wpbdp-ratings' ) . '</h4>';
        echo '<p>' . esc_html__( 'When visitors leave reviews for directory listings we collect the data shown in the review form, and also the visitor’s IP address to help spam detection.', 'wpbdp-ratings' ) . '</p>';
    }

    /**
     * @param $exporters
     * @return mixed
     */
    public function register_personal_data_exporters( $exporters ) {
        $data_formatter = new WPBDP_DataFormatter();

        $exporters['business-directory-plugin-ratings'] = array(
            'exporter_friendly_name' => 'Business Directory Plugin',
            'callback'               => array(
                new WPBDP_PersonalDataExporter(
                    new WPBDP_RatingsPersonalDataProvider(
                        $data_formatter
                    )
                ),
                'export_personal_data',
            ),
        );

        return $exporters;

    }

    /**
     * @param $erasers
     * @return mixed
     */
    public function register_personal_data_erasers( $erasers ) {
        $data_formatter = new WPBDP_DataFormatter();

        $erasers['business-directory-plugin-ratings'] = array(
            'eraser_friendly_name' => 'Business Directory Plugin',
            'callback'             => array(
                new WPBDP_PersonalDataEraser(
                    new WPBDP_RatingsPersonalDataProvider(
                        $data_formatter
                    )
                ),
                'erase_personal_data',
            ),
        );
        return $erasers;
    }
}
