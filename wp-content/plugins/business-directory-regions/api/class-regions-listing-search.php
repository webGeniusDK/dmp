<?php

function wpbdp_regions_listing_search() {
    return new WPBDP__Regions__Listing_Search(
        WPBDP_RegionsPlugin::TAXONOMY,
        wpbdp_regions_fields_api(),
        $GLOBALS['wpdb']
    );
}

class WPBDP__Regions__Listing_Search {

    private $taxonomy;
    private $region_fields;
    private $db;

    public function __construct( $taxonomy, $region_fields, $db ) {
        $this->taxonomy      = $taxonomy;
        $this->region_fields = $region_fields;
        $this->db            = $db;
    }

    /**
     * During search, there is no easy way to target the region level
     * (Country, State, ..) associated with one of the region fields.
     * As a result, the only difference between the conditions for
     * searching for regions in the United States and regions in Florida,
     * is the name of the region being searched.
     *
     * When multiple region fields are in the search tree, all using the
     * same search term, the same condition is added multiple times to
     * the final query, potentially having a performance impact and
     * without any improvement for the search results.
     *
     * This function removes all but one of the region fields from the
     * search tree.
     *
     * @since 4.0.7
     */
    public function parse_search_request( $search_tree, $search_request ) {
        if ( empty( $search_request['kw'] ) ) {
            return $search_tree;
        }

        $search_tree = $this->remove_numeric_terms_for_region_fields( $search_tree, $search_request );

        if ( wpbdp_get_option( 'regions-main-box-integration' ) ) {
            return $search_tree;
        }

        return $this->parse_quick_search_request_with_integration_disabled( $search_tree, $search_request );
    }

    private function remove_numeric_terms_for_region_fields( $search_tree, $search_request ) {
        $fields = $this->get_region_fields_in_quick_search();

        foreach ( $search_request['kw'] as $keyword ) {
            if ( ! preg_match( '/^\d*$/', $keyword ) ) {
                continue;
            }

            foreach ( $fields as $field ) {
                $search_tree = WPBDP__Listing_Search::tree_remove_field( $search_tree, $field, $keyword );
            }
        }

        return $search_tree;
    }

    private function get_region_fields_in_quick_search() {
        $quick_search_fields = wpbdp_get_option( 'quick-search-fields' );
        $region_fields       = $this->region_fields->get_fields();

        return array_intersect( $quick_search_fields, $region_fields );
    }

    private function parse_quick_search_request_with_integration_disabled( $search_tree, $search_request ) {
        $region_fields_in_quick_search = $this->get_region_fields_in_quick_search();

        foreach ( array_slice( $region_fields_in_quick_search, 0, -1 ) as $field_id ) {
            $search_tree = WPBDP__Listing_Search::tree_remove_field( $search_tree, $field_id );
        }

        return $search_tree;
    }

    public function cofigure_region_field_search( $query_pieces, $field, $search_terms, $search ) {
        if ( 'region' != $field->get_association() ) {
            return $query_pieces;
        }

        $search_terms = array_filter(
            (array) $search_terms,
            function( $x ) {
                return ! is_numeric( $x );
            }
        );

        if ( empty( $search_terms ) ) {
            return $query_pieces;
        }

        $conditions = array();

        foreach ( $search_terms as $search_term ) {
            $conditions[] = $this->db->prepare( "regions_t.name LIKE '%%%s%%'", $search_term );
        }

        return array( 'where' => '( ' . join( ' OR ', $conditions ) . ' )' );
    }

    public function filter_search_query_pieces( $query_pieces, $search ) {
        if ( strpos( $query_pieces['where'], 'regions_t.name LIKE' ) == false ) {
            return $query_pieces;
        }

        $query_pieces['join'] .= " LEFT JOIN {$this->db->term_relationships} AS regions_tr ON ( {$this->db->posts}.ID = regions_tr.object_id ) ";
        $query_pieces['join'] .= " LEFT JOIN {$this->db->term_taxonomy} AS regions_tt ON ( regions_tr.term_taxonomy_id = regions_tt.term_taxonomy_id AND regions_tt.taxonomy = '{$this->taxonomy}' ) ";
        $query_pieces['join'] .= " LEFT JOIN {$this->db->terms} AS regions_t ON ( regions_tt.term_id = regions_t.term_id ) ";

        return $query_pieces;
    }
}
