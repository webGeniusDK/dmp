<?php

class WPBDP_Migration_Export_Run {

    private static $STATE_PROPS = array( 'part', 'state', 'date', 'bd_version', 'parts', 'contents' );

    private $id = '';
    private $base_dir = '';
    private $working_dir = '';
    private $part = '';
    private $state = array();

    private $date = '';
    private $bd_version = 0;
    private $parts = array();
    private $contents = array();

    private $status = '';


    public function __construct( $basedir = '', $id = '', $parts = array() ) {
        if ( ! is_dir( $basedir ) )
            throw new Exception( sprintf( __( 'Invalid base dir: %s.', 'wpbdp-migrations' ), $basedir ) );

        $this->base_dir = untrailingslashit( $basedir );

        $id = trim( str_replace( array( '.', '/', '\\' ), '', sanitize_file_name( $id ) ) );

        if ( $id ) {
            $this->id = $id;
            return $this->state_restore();
        }

        $this->date = current_time( 'timestamp' );
        $this->bd_version = wpbdp_get_version();
        $this->parts = $this->configure_parts( $parts );
        $this->id = $this->date . md5( serialize( $parts ) );

        $this->working_dir = wp_normalize_path( $basedir . DIRECTORY_SEPARATOR . $this->id );

        if ( ! @mkdir( $this->working_dir, 0777 ) )
            throw new Exception( sprintf( __( 'Could not create export directory. Please check permissions for "%s".', 'wpbdp-migrations' ),
                                          $basedir ) );

        mkdir( $this->working_dir . '/files', 0777 );

        $this->part = $this->parts[0];
        $this->state_persist();
    }

    public function get_id() {
        return $this->id;
    }

    public function finished() {
        return ( $this->part == 'done' );
    }

    public function completed( $part = '' ) {
        $parts = $this->parts;
        $index = array_search( $part, $this->parts, true );
        $current = array_search( $this->part, $this->parts, true );

        if ( false === $index )
            return false;

        return ( $index < $current );
    }

    public function do_work() {
        if ( $this->finished() )
            return;

        @set_time_limit( 0 );

        $callback = array( &$this, 'part_' . $this->part );

        if ( ! is_callable( $callback ) )
            throw new Exception( 'Invalid part: "' . $this->part . '".' );

        $done = call_user_func( $callback );

        if ( $done ) {
            $index = array_search( $this->part, $this->parts );
            $this->part = $this->parts[ $index + 1 ];

            // Clear state for this part.
            $this->state = array();
        }

        $this->state_persist();
    }

    public function get_current_part() {
        return $this->part;
    }

    public function get_parts_done() {
        $done = array();

        foreach ( $this->parts as $p ) {
            if ( $this->completed( $p ) )
                $done[] = $p;
        }

        return $done;
    }

    public function get_parts() {
        $parts = array();

        foreach ( $this->parts as $p ) {
            if ( 'done' == $p )
                continue;

            $parts[] = $p;
        }

        return $parts;
    }

    public function get_status_message() {
        return $this->status;
    }

    private function configure_parts( $parts_ ) {
        $info = self::get_parts_info();
        $parts = array();


        // Add dependencies.
        foreach ( $parts_ as $p ) {
            $parts = array_merge( $parts, $this->resolve_deps_deep( $p ) );
        }
        $parts = array_unique( $parts );

        // Sort parts in correct order.
        usort( $parts, array( &$this, 'sort_parts' ) );

        $parts[] = 'packaging';
        $parts[] = 'done';

        return $parts;
    }

    private function resolve_deps_deep( $part ) {
        $info = self::get_parts_info();

        if ( ! isset( $info[ $part ] ) )
            return array();

        $deps = $info[ $part ]['deps'];
        $res = array();

        foreach ( $deps as $d ) {
            $res = array_merge( $res, $this->resolve_deps_deep( $d ) );
        }

        $res = array_merge( $res, array( $part ) );

        return $res;
    }

    private function sort_parts( $x, $y ) {
        $i1 = array_search( $x, array_keys( self::get_parts_info() ) );
        $i2 = array_search( $y, array_keys( self::get_parts_info() ) );

        return $i1 - $i2;
    }

    private function state_restore() {
        $this->working_dir = $this->base_dir . DIRECTORY_SEPARATOR . $this->id;
        if ( ! is_dir( $this->working_dir ) )
            throw new Exception('Invalid export ID');

        $path = $this->working_dir . DIRECTORY_SEPARATOR . 'state.obj';

        if ( ! is_readable( $path ) )
            throw new Exception('Could not read state file for export.');

        $data = unserialize( file_get_contents( $path ) );

        foreach ( self::$STATE_PROPS as $p ) {
            $this->{$p} = $data[ $p ];
        }
    }

    private function state_persist() {
        $data = array();

        foreach ( self::$STATE_PROPS as $p ) {
            $data[ $p ] = $this->{$p};
        }

        $path = $this->working_dir . '/' . 'state.obj';
        file_put_contents( $path, serialize( $data ) );
    }

    private function add_content( $part, $data, $piece = 0 ) {
        $filename = $part . '_' . $piece . '.obj';
        $path = $this->working_dir . '/' . $filename;
        file_put_contents( $path, serialize( $data ) );

        if ( ! isset( $this->contents[ $part ] ) )
            $this->contents[ $part ] = array();

        $this->contents[ $part ][ $piece ] = md5_file( $path );
        return true;
    }

    private function part_settings() {
        global $wpbdp;
        $keys = array_keys( $wpbdp->settings->get_registered_settings() );

        $data = array();
        foreach ( $keys as $k ) {
            $data[ $k ] = wpbdp_get_option( $k );
        }

        $this->status = sprintf( 'Exported %d settings.', count( $data ) );

        return $this->add_content( 'settings', $data );
    }

    private function part_categories() {
        $sub_step = isset( $this->state['terms_step'] ) ? $this->state['terms_step'] : 'terms';

        if ( 'terms' == $sub_step ) {
            $done = $this->taxonomy_part( 'categories', WPBDP_CATEGORY_TAX, 100 );
        } else {
            $done = $this->part_terms_category_images();
        }

        if ( ! $done )
            return false;

        $sub_step = ( 'terms' == $sub_step ) ? 'images' : '';

        if ( $sub_step ) {
            $this->state['terms_step'] = $sub_step;
        } else {
            unset( $this->state['terms_step'] );
            return true;
        }

        return false;
    }

    private function taxonomy_part( $part_name, $tax, $limit, $callback = null ) {
        global $wpdb;

        $total = wp_count_terms( $tax );
        $exported = isset( $this->state['exported'] ) ? intval( $this->state['exported'] ) : 0;
        $terms = get_terms( $tax, array( 'number' => $limit, 'offset' => $exported, 'hide_empty' => 0 ) );
        $data = array();

        if ( ! $terms ) {
            unset( $this->state['exported'] );
            return true;
        }

        $category_icons = ( 'categories' == $part_name ) ? get_option( 'wpbdp[category_images]' ) : array();

        foreach ( $terms as &$t ) {
            $item = array( 'name' => $t->name,
                           'slug' => $t->slug,
                           'parent' => $t->parent );

            // Category images.
            if ( isset( $category_icons['images'][ $t->term_id ] ) ) {
                if ( ! isset( $this->state['category_images'] ) )
                    $this->state['category_images'] = array();

                $item['image'] = basename( $category_icons['images'][ $t->term_id ]['file'] );
                $this->state['category_images'][ $t->term_id ] = $category_icons['images'][ $t->term_id ]['file'];
            }

            // Regions.
            if ( 'regions' == $part_name ) {
                $item['enabled'] = absint( $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}wpbdp_regionmeta WHERE meta_key = %s AND region_id = %d", 'enabled', $t->term_id ) ) );
                $item['sidelist'] = absint( $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}wpbdp_regionmeta WHERE meta_key = %s AND region_id = %d", 'sidelist', $t->term_id ) ) );
                $item['listings'] = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT(tr.object_id) FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.term_id = %d AND tt.taxonomy = %s", $t->term_id, 'wpbdm-region' ) );
            }

            $data[ $t->term_id ] = $item;

        }
        $t = null;

        $exported += count( $data );
        $this->state['exported'] = $exported;

        $piece = ceil( $exported / $limit ) - 1;
        $this->add_content( $part_name, $data, $piece );
        $this->status = sprintf( 'Exported taxonomy %s, items %d - %d', $tax, $piece * $limit, ( $piece + 1 ) * $limit );

        return false;
    }

    private function part_terms_category_images() {
        $upload_dir = wp_upload_dir();

        $copied = isset( $this->state['category_images_exported'] ) ? intval( $this->state['category_images_exported'] ) : 0;
        $images = $this->state['category_images'];
        $total = count( $images );
        $limit = 10;

        $slice = array_slice( $images, $copied, $limit, true );

        if ( ! $slice )
            return true;

        foreach ( $slice as $term_id => $image_file ) {
            $img_path = wp_normalize_path( realpath( $upload_dir['basedir'] ) . '/' . $image_file );
            $img_dest = $this->working_dir . '/files/' . 'c' . $term_id . '_' . basename( $img_path );
            copy( $img_path, $img_dest );
        }
        $copied += $limit;

        $piece = ceil( $copied / $limit ) - 1;
        $this->state['category_images_exported'] = $copied;
        $this->status = sprintf( 'Exported category images %d - %d', $piece * $limit, ( $piece + 1 ) * $limit );

        return false;
    }

    private function part_tags() {
        return $this->taxonomy_part( 'tags', WPBDP_TAGS_TAX, 100 );
    }

    private function part_regions() {
        // $sub_step = isset( $this->state['terms_step'] ) ? $this->state['terms_step'] : 'terms';

        // if ( 'terms' == $sub_step ) {
            $taxonomy = get_taxonomy( 'wpbdm-region' );

            if ( false === $taxonomy ) {
                return true;
            }

            return $this->taxonomy_part( 'regions', $taxonomy->name, 100 );
        // } else {
        //     $done = $this->part_regions_settings();
        // }

        // if ( ! $done )
        //     return false;

        // $sub_step = ( 'terms' == $sub_step ) ? 'settings' : '';

        // if ( $sub_step ) {
        //     $this->state['terms_step'] = $sub_step;
        // } else {
        //     unset( $this->state['terms_step'] );
        //     return true;
        // }

        // return false;
    }

    private function part_regions_settings() {
        $settings = array();

        $fields = get_option( 'wpbdp-regions-form-fields' );

        if( $fields ) {

            foreach( $fields as $level => $id ) {
                $field = wpbdp_get_form_field( $id );
                if ( ! $field ) {
                    continue;
                }
                $settings['wpbdp-regions-form-fields'][$level] = $field->get_short_name();
            }

            $this->add_content( 'regions_settings', $settings, 0 );
        }

        return true;

        $visible_regions = get_option( 'wpbdp-visible-regions-children' );

        if ( $visible_regions ) {
        
            // $settings['wpbdp-visible-regions-children'];
        }

    }

    private function part_fees() {
        global $wpbdp;

        $data = array();
        $fees = wpbdp_get_fee_plans( array( 'enabled' => 'all', 'include_free' => true ) );

        foreach ( $fees as &$f ) {
            $f_array = array();

            foreach ( array( 'id', 'label', 'amount', 'days', 'images', 'sticky', 'recurring', 'pricing_model', 'pricing_details', 'supported_categories', 'weight', 'enabled', 'description', 'extra_data', 'tag' ) as $k ) {
                $f_array[ $k ] = $f->{$k};
            }

            $data[ $f->id ] = $f_array;
        }
        $f = null;

        return $this->add_content( 'fees', $data );
    }

    private function part_fields() {
        global $wpdb;

        $fields = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wpbdp_form_fields ORDER BY id",
                                      ARRAY_A );

        foreach ( $fields as &$f )
            $f['field_data'] = unserialize( $f['field_data'] );

        return $this->add_content( 'fields', $fields );
    }

    private function part_authors() {
        global $wpdb;

        $data = array();
        $user_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_author FROM {$wpdb->posts} WHERE post_type = %s",
                                                    WPBDP_POST_TYPE ) );

        foreach ( $user_ids as $uid ) {
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->users} WHERE ID = %d", $uid ), ARRAY_A );
            $meta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->usermeta} WHERE user_id = %d", $uid ), ARRAY_A );

            $data[] = array( 'basic' => $row, 'meta' => $meta );
        }

        return $this->add_content( 'authors', $data );
    }

    private function part_listings() {
        global $wpdb;

        $upload_dir = wp_upload_dir();

        $exported = isset( $this->state['exported'] ) ? $this->state['exported'] : 0;
//        $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", WPBDP_POST_TYPE ) );
        $ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status != %s ORDER BY ID ASC LIMIT %d OFFSET %d",
                WPBDP_POST_TYPE,
                'auto-draft',
                20,
                $exported ) );
        $listings = array();

        if ( ! $ids )
            return true;

        $fields = wpbdp_get_form_fields( array( 'association' => array( '-category' ) ) );

        foreach ( $ids as $id ) {
            $listing = WPBDP_Listing::get( $id );

            $data = array( 'id' => 0,
                           'fields' => array(),
                           'plan' => array(),
                           'tags' => array(),
                           'status' => get_post_status( $id ),
                           'payments' => array(),
                           'images' => array(),
                           'thumbnail_id' => 0 );

            $data['id'] = $id;
            $data['author'] = $listing->get_author_meta( 'user_login' );
            $data['tags'] = wp_get_post_terms( $id, WPBDP_TAGS_TAX, array( 'fields' => 'ids' ) );

            // Field info.
            foreach ( $fields as &$f )
                $data['fields'][ $f->get_id() ] = $f->value( $id );

            // Categories.
            $data['categories'] = wp_get_post_terms( $id, WPBDP_CATEGORY_TAX, array( 'fields' => 'ids' ) );

            // Plan.
            $data['plan'] = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpbdp_listings WHERE listing_id = %d", $id ) );

            // Images.
            $thumbnail_id = $listing->get_thumbnail_id();
            $images = $listing->get_images( 'ids' );

            foreach ( $images as $img_id ) {
                $meta = wp_get_attachment_metadata( $img_id );

                if ( ! isset( $meta['file'] ) )
                    continue;

                $img_path = realpath( $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $meta['file'] );
                if ( ! is_readable( $img_path ) )
                    continue;

                $img_dest = $this->working_dir . '/files/l' . $listing->get_id() . '_' . $img_id . '_' . basename( $img_path );

                if ( copy( $img_path, $img_dest ) )
                    $data['images'][] = basename( $img_dest );

                if ( $img_id == $thumbnail_id )
                    $data['thumbnail_id'] = basename( $img_dest );
            }

            // Google maps geolocation (if available).
            $geolocation = get_post_meta( $listing->get_id(), '_wpbdp[googlemaps][geolocation]', true );
            if ( $geolocation )
                $data['geolocation'] = $geolocation;

            // Payments.
            $payments = array();
            foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpbdp_payments WHERE listing_id = %d", $listing->get_id() ), ARRAY_A ) as $p ) {
                unset( $p['listing_id'] );

                // $p['items'] = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpbdp_payments_items WHERE payment_id = %d", $p['id'] ), ARRAY_A );
                $payments[] = $p;
            }
            $data['payments'] = $payments;

            $listings[ $id ] = $data;
            $exported++;
        }

        $this->state['exported'] = $exported;

        $piece = ceil( $exported / 20 ) - 1;
        $this->add_content( 'listings', $listings, $piece );

        $this->status = sprintf( 'Exported listings %d - %d', $exported, $exported + 20 );

        return false;
    }

    private function part_ratings() {
        global $wpdb;

        $exported = isset( $this->state['ratings_exported'] ) ? $this->state['ratings_exported'] : 0;
        $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpbdp_ratings" );

        $data = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpbdp_ratings ORDER BY id ASC LIMIT %d OFFSET %d",
                            20,
                            $exported ),
            ARRAY_A );

        if ( ! $data )
            return true;

        foreach ( $data as &$rating ) {
            if ( ! $rating['user_id'] )
                continue;

            $u = get_user_by( 'id', absint( $rating['user_id'] ) );
            if ( ! $u )
                continue;

            $rating['user_name'] = $u->user_login;
        }
        // $rating = null;

        $exported += count( $data );

        $this->state['ratings_exported'] = $exported;

        $piece = ceil( $exported / 20 ) - 1;
        $this->add_content( 'ratings', $data, $piece );

        $this->status = sprintf( 'Exported ratings %d - %d', $exported, $exported + 20 );

        return false;
    }

    private function part_discount_codes() {
        global $wpdb;

        $codes = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wpbdp_coupons ORDER BY id ASC", ARRAY_A );

        foreach ( $codes as &$c ) {
            $c['allowed_users'] = unserialize( $c['allowed_users'] );
        }

        $this->add_content( 'discount_codes', $codes );
        $this->status = sprintf( 'Exported discount codes: %d', count( $codes ) );

        return true;
    }

    private function part_attachments() {
        global $wpdb;

        $upload_dir = wp_upload_dir();

        $exported = isset( $this->state['exported'] ) ? intval( $this->state['exported'] ) : 0;
        $limit = 5;
        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY post_id ASC LIMIT %d OFFSET %d",
            '_wpbdp[attachments]',
            $limit,
            $exported ) );

        if ( ! $items )
            return true;

        $data = array();
        foreach ( $items as $item ) {
            $post_id = $item->post_id;
            $attachments_ = unserialize( $item->meta_value );
            $attachments = array();

            foreach ( $attachments_ as $at ) {
                $file_path = wp_normalize_path( realpath( $upload_dir['basedir'] ) . '/' . $at['file_'] );
                $dest_path = $this->working_dir . '/files/' . 'a' . $post_id . '_' . basename( $file_path );

                if ( copy( $file_path, $dest_path ) )
                    $attachments[] = basename( $dest_path );
            }

            if ( $attachments )
                $data[ $post_id ] = $attachments;
        }

        $exported += $limit;
        $this->state['exported'] = $exported;

        $piece = ceil( $exported / $limit ) - 1;
        $this->add_content( 'attachments', $data, $piece );
        $this->status = sprintf( 'Exported %d sets of attachments', $exported );

        return false;
    }

    private function part_packaging() {
        define( 'PCLZIP_TEMPORARY_DIR', $this->working_dir );
        require_once( ABSPATH . 'wp-admin/includes/class-pclzip.php' );

        $files = array();

        $files[] = $this->working_dir . DIRECTORY_SEPARATOR . 'state.obj';

        foreach ( $this->contents as $file => $subfiles ) {
            foreach ( $subfiles as $i => $md5 ) {
                $files[] = $this->working_dir . '/' . $file . '_' . $i . '.obj';
            }
        }

        // Everything under files/ is packaged too.
        $files = array_merge( $files, WPBDP_FS::ls( $this->working_dir . '/files' ) );

        $path = $this->working_dir . DIRECTORY_SEPARATOR . 'export.zip';
        $zip = new PclZip( $path );
        $res = $zip->create( implode( ',', $files ), PCLZIP_OPT_REMOVE_ALL_PATH );

        if ( ! $res )
            throw new Exception( sprintf( __( 'Could not create ZIP file: %s.', 'wpbdp-migrations' ), $path ) );

        // Remove all files now that we have the ZIP.
        foreach ( $files as $f )
            @unlink( $f );

        return true;
    }

    public function get_zip_file() {
        $path = $this->working_dir . DIRECTORY_SEPARATOR . 'export.zip';

        if ( ! file_exists( $path ) )
            return false;

        return $path;
    }

    public function cleanup() {
        wpbdp_rrmdir( $this->working_dir );
    }

    public static function get_parts_info() {
        $parts = array();

        $parts['settings'] = array( 'description' => __( 'Configuration (settings and options)', 'wpbdp-migrate' ),
                                    'deps' => array() );
        $parts['categories'] = array( 'description' => __( 'Directory Categories', 'wpbdp-migrate' ),
                                      'deps' => array() );
        $parts['tags'] = array( 'description' => __( 'Directory Tags', 'wpbdp-migrate' ),
                                'deps' => array() );
        $parts['fields'] = array( 'description' => __( 'Form Fields', 'wpbdp-migrate' ),
                                  'deps' => array() );
        $parts['regions_settings'] = array( 'description' => __( 'Directory Regions Settings', 'wpbdp-migrate' ),
                                  'deps' => array() );
        $parts['fees'] = array( 'description' => __( 'Fees', 'wpbdp-migrate' ),
                                'deps' => array( 'categories' ) );
        $parts['authors'] = array( 'description' => __( 'Listing authors', 'wpbdp-migrate' ),
                                   'deps' => array() );
        $parts['listings'] = array( 'description' => __( 'Listings', 'wpbdp-migrate' ),
                                    'deps' => array( 'fields', 'categories', 'tags', 'fees' ) );
                                    
        if ( class_exists( 'WPBDP_RegionsPlugin' ) ) {
            $parts['regions'] = array(
                'description' => __( 'Directory Regions', 'wpbdp-migrate' ),
                'deps' => array()
            );
        }
        if ( class_exists( 'WPBDP_Coupons_Module' ) ) {
            $parts['discount_codes'] = array(
                'description' => __( 'Discount Codes', 'wpbdp-migrate' ),
                'deps' => array()
            );
        }
        if ( class_exists( 'BusinessDirectory_RatingsModule' ) ) {
            $parts['ratings'] = array(
                'description' => __( 'Listing ratings', 'wpbdp-migrate' ),
                'deps' => array( 'listings' )
            );
        }
        if ( class_exists( 'WPBDP_ListingAttachmentsModule' ) ) {
            $parts['attachments'] = array(
                'description' => __( 'Listing attachments', 'wpbdp-migrate' ),
                'deps' => array( 'listings' )
            );
        }
        $parts['packaging'] = array( 'description' => __( 'Packaging (ZIP file generation)', 'wpbdp-migrate' ),
                                     'deps' => array() );

        return $parts;
    }

}
