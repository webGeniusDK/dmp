<?php

class WPBDP__Views__Show_Region extends WPBDP__View {

    public function dispatch() {
        global $wp_query;

        wpbdp_push_query( $wp_query );

        $term = get_queried_object();

        if ( is_object( $term ) ) {
            $term->is_tag = false;

            $params = array(
                'title'        => $term->name,
                'region'       => $term,
                'query'        => $wp_query,
                'in_shortcode' => false,
				'is_tag'       => false,
            );

            $html = $this->_render( 'region', $params, 'page' );
        } else {
            $html = '';
        }

        wpbdp_pop_query();

        return $html;
    }
}
