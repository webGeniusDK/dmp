<?php
// Do not allow direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wpbdp_zipcodesearch_radius_units_name() {
	$units        = wpbdp_get_option( 'zipcode-units' );
	$unit_options = wpbdp_zipcodesearch_unit_options();
	if ( ! isset( $unit_options[ $units ] ) ) {
		$units = 'kilometers';
	}
	return $unit_options[ $units ];
}

/**
 * @since 5.3
 */
function wpbdp_zipcodesearch_unit_options() {
	return array(
		'miles'      => __( 'Miles', 'wpbdp-zipcodesearch' ),
		'kilometers' => __( 'Kilometers', 'wpbdp-zipcodesearch' ),
	);
}
