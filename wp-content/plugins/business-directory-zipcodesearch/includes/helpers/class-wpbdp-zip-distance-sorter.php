<?php
// Do not allow direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class _WPBDP_DistanceSorter
 */
// phpcs:ignore PEAR.NamingConventions.ValidClassName.StartWithCapital
class _WPBDP_DistanceSorter {
	public $center      = null;
	public $distance_cb = null;

	public function sort( $a, $b ) {
		$dist = call_user_func( $this->distance_cb, $this->center, $a ) - call_user_func( $this->distance_cb, $this->center, $b );

		if ( $dist > 0.0 ) {
			return 1;
		} elseif ( $dist < 0.0 ) {
			return -1;
		} else {
			return 0;
		}
	}

}
