<?php
do_action( 'wpbdp_before_region_page', $region );
echo wpbdp_x_render( 'listings', array( 'query' => $query ) );
do_action( 'wpbdp_after_region_page', $region );
