<?php
class WPBDP_Migration_Pack {

    private $path = '';
    private $working_dir = '';
    private $filename = '';

    private $manifest = array();

    private $part = '';
    private $data = array();

    private $status = '';


    public function __construct( $file, $from_scratch = false ) {
        $this->path = $file;
        $this->filename = basename( $file );
        $this->working_dir = untrailingslashit( dirname( $file ) );

        if ( ! is_file( $this->path ) || ! is_readable( $this->path ) )
            throw new Exception( sprintf( __( 'Can not read file path: %s.', 'wpbdp-migrate' ), $this->path ) );

        $this->pack_dir = $this->working_dir . DIRECTORY_SEPARATOR . md5_file( $this->path );
        if ( $from_scratch && is_dir( $this->pack_dir ) )
            WPBDP_FS::rmdir( $this->pack_dir );

        try {
            $this->unpack();
            $this->load_manifest();
            $this->validate();
            $this->load_state();
            $this->save_state();
        } catch ( Exception $e ) {
            $this->cleanup();
            throw $e;
        }
    }

    public function get_id() {
        return base64_encode( $this->filename );
    }

    public function get_filename() {
        return $this->filename;
    }

    public function get_file_size( $formatted = true ) {
        $size = filesize( $this->path );
        return $formatted ? size_format( $size ) : $size;
    }

    public function get_date() {
        return $this->manifest['date'];
    }

    public function get_bd_version() {
        return $this->manifest['bd_version'];
    }

    public function get_parts() {
        return array_keys( $this->manifest['parts'] );
    }

    public function get_current_part() {
        return $this->part;
    }

    public function get_parts_done() {
        $done = array();

        if ( $this->finished() )
            return $this->get_parts();

        foreach ( $this->get_parts() as $p ) {
            if ( $this->completed( $p ) )
                $done[] = $p;
        }

        return $done;
    }

    public function get_status_message() {
        return $this->status;
    }

    public function completed( $part ) {
        $index = array_search( $part, $this->get_parts() );
        $current = array_search( $this->part, $this->get_parts() );

        return ( $index < $current );
    }

    public function finished() {
        return ( $this->part == 'done' );
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
            $keys = array_keys( $this->manifest['parts'] );
            $index = array_search( $this->part, $keys );

            if ( count( $keys ) > ( $index + 1 ) )
                $this->part = $keys[ $index + 1 ];
            else
                $this->part = 'done';
        }

        $this->save_state();
    }

    public function cleanup( $delete_file = false ) {
        wpbdp_rrmdir( $this->pack_dir );

        if ( $delete_file )
            @unlink( $this->path );
    }

    private function unpack() {
        if ( ! is_writeable( $this->working_dir ) )
            throw new Exception( sprintf( __( 'Directory "%s" should be writeable.', 'wpbdp-migrate' ), $this->working_dir ) );

        if ( is_dir( $this->pack_dir ) )
            return;

        require_once( ABSPATH . 'wp-admin/includes/class-pclzip.php' );
        $zip = new PclZip( $this->path );
        $files = $zip->extract( PCLZIP_OPT_PATH, $this->pack_dir, PCLZIP_OPT_REMOVE_ALL_PATH );

        if ( ! $files )
            throw new Exception( sprintf( __( 'Could not unpack ZIP file: %s.', 'wpbdp-migrate' ), $this->filename ) );
    }

    private function load_manifest() {
        $manifest_file = $this->pack_dir . DIRECTORY_SEPARATOR . 'state.obj';

        if ( ! is_readable( $manifest_file ) )
            throw new Exception( __( 'Can not read manifest file for export pack.', 'wpbdp-migrate' ) );

        $manifest = unserialize( file_get_contents( $manifest_file ) );

        $this->manifest['date'] = $manifest['date'];
        $this->manifest['bd_version'] = $manifest['bd_version'];
        $this->manifest['parts'] = array();

        foreach ( $manifest['contents'] as $c => $files ) {
            if ( 'images' == $c )
                continue;

            $this->manifest['parts'][ $c ] = array();

            foreach ( $files as $i => $md5 ) {
                $this->manifest['parts'][ $c ][] = array( 'file' => $c . '_' . $i . '.obj',
                                                          'md5' => $md5 );
            }
        }
    }

    private function validate() {
        if ( version_compare( $this->manifest['bd_version'], WPBDP_VERSION, '>' ) )
            throw new Exception( sprintf( __( 'This pack can only be imported in Business Directory Plugin version %s or greater.', 'wpbdp-migrate' ), $this->manifest['bd_version'] ) );

        foreach ( $this->manifest['parts'] as $p => $files ) {
            foreach ( $files as $f ) {
                $path = $this->pack_dir . DIRECTORY_SEPARATOR . $f['file'];

                if ( ! is_readable( $path ) || $f['md5'] != md5_file( $path ) )
                    throw new Exception( sprintf( __( 'Checksum does not match expected value for pack file "%s".', 'wpbdp-migrate' ), $f['file'] ) );
            }
        }
    }

    private function load_state() {
        $path = $this->pack_dir . DIRECTORY_SEPARATOR . 'import.obj';

        if ( file_exists( $path ) )
            $state = unserialize( file_get_contents( $path ) );
        else
            $state = array();

        if ( $state ) {
            $this->part = $state['part'];
            $this->data = $state['data'];
        } else {
            $parts = array_keys( $this->manifest['parts'] );
            $this->part = $parts[0];
            $this->data = array();
        }
    }

    private function save_state() {
        $path = $this->pack_dir . DIRECTORY_SEPARATOR . 'import.obj';

        $obj = array( 'part' => $this->part, 'data' => $this->data );
        file_put_contents( $path, serialize( $obj ) );
    }

    private function read_part( $part, $index = 0 ) {
        $info = $this->manifest['parts'][ $part ];

        $file = false;
        if ( isset( $info[ $index ] ) )
            $file = $info[ $index ]['file'];

        if ( ! $file )
            return false;

        return unserialize( file_get_contents( $this->pack_dir . DIRECTORY_SEPARATOR . $file ) );
    }

    private function part_settings() {
        $data = $this->read_part( 'settings', 0 );

        foreach ( $data as $option => $value ) {
            wpbdp_set_option( $option, $value );
        }

        return true;
    }

    private function part_categories() {
        $done = $this->taxonomy_part( 'categories' );

        if ( ! $done )
            return false;

        // Add images.
        $new_to_old = array_combine( array_values( $this->data['taxs']['categories']['id_translation'] ),
                                     array_keys( $this->data['taxs']['categories']['id_translation'] ) );

        $images = array();
        $images['images'] = array();

        foreach ( $this->data['taxs']['categories']['images'] as $term_id => $img_name ) {
            $file_path = $this->pack_dir . '/c' . $new_to_old[ $term_id ] . '_' . $img_name;

            if ( ! file_exists( $file_path ) )
                continue;

            $upload = wpbdp_media_upload( $file_path, false, false );
            if ( ! $upload )
                continue;

            $images['images'][ $term_id ] = array( 'file' => _wp_relative_upload_path( $upload['file'] ) );
            // $category_images = get_option( 'wpbdp[category_images]' );
        }

        update_option( 'wpbdp[category_images]', $images );
        return true;
    }

    private function part_tags() {
        return $this->taxonomy_part( 'tags' );
    }

    private function part_regions() {
        return $this->taxonomy_part( 'regions' );
    }

    private function part_regions_settings() {
        global $wpdb;
        $data           = $this->read_part( 'regions_settings' );
        $regions_levels = array(); 
        if ( isset( $data['wpbdp-regions-form-fields'] ) ) {
            foreach ( $data['wpbdp-regions-form-fields'] as $level => $shortname ) {
                $field_id = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}wpbdp_form_fields WHERE shortname = %s", $shortname ) );
                if ( ! empty( $field_id ) ) {
                    $regions_levels[$level] = is_array( $field_id ) ? array_shift( $field_id ) : $field_id;
                }
            }
        }

        if ( ! empty( $regions_levels ) ) {
            update_option( 'wpbdp-regions-form-fields', $regions_levels );
        }

        return true;
    }

    private function taxonomy_part( $tax_id ) {
        global $wpdb;

        static $tax_id_wp_tax = array( 'categories' => WPBDP_CATEGORY_TAX,
                                       'tags' => WPBDP_TAGS_TAX,
                                       'regions' => 'wpbdm-region' );
        $taxonomy = $tax_id_wp_tax[ $tax_id ];
        $index = isset( $this->data['tax_part'] ) ? intval( $this->data['tax_part'] ) : 0;
        $terms = $this->read_part( $tax_id, $index );

        if ( ! isset( $this->data['taxs'] ) )
            $this->data['taxs'] = array();

        if ( ! isset( $this->data['taxs'][ $tax_id ] ) )
            $this->data['taxs'][ $tax_id ] = array( 'id_translation' => array(), 'parents' => array() );

        if ( 'categories' == $tax_id && ! isset( $this->data['taxs'][ $tax_id ]['images'] ) )
            $this->data['taxs'][ $tax_id ]['images'] = array();

        if ( 'regions' == $tax_id && ! isset( $this->data['taxs'][ $tax_id ]['listings'] ) )
            $this->data['taxs'][ $tax_id ]['listings'] = array();

        if ( 0 == $index && $terms ) {
            // Cleanup.
            if ( 'regions' == $tax_id && wpbdp_table_exists( $wpdb->prefix . 'wpbdp_regionmeta' ) ) {
                $wpdb->query( "DELETE FROM {$wpdb->prefix}wpbdp_regionmeta" );
            }

            $wpdb->query( $wpdb->prepare( "DELETE tr FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy = %s", $taxonomy ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $taxonomy ) );
        }

        // If we're done...
        if ( ! $terms ) {
            // Re-assign parents.
            if ( 'tags' != $tax_id ) {
                foreach ( $this->data['taxs'][ $tax_id ]['parents'] as $term_id => $parent_id ) {
                    $new_parent_id = $this->data['taxs'][ $tax_id ]['id_translation'][ $parent_id ];
                    wp_update_term( $term_id, $taxonomy, array( 'parent' => $new_parent_id ) );
                }
            }

            unset( $this->data['taxs'][ $tax_id ]['parents'] );
            unset( $this->data['tax_part'] );
            return true;
        }

        foreach ( $terms as $term_id => $term_data ) {
            $res = wp_insert_term( $term_data['name'], $taxonomy, array( 'slug' => $term_data['slug'], 'parent' => 0 ) );

            if ( is_wp_error( $res ) ) {
                continue;
            }

            $this->data['taxs'][ $tax_id ]['id_translation'][ $term_id ] = $res['term_id'];

            if ( 'tags' == $tax_id )
                continue;

            if ( $term_data['parent'] )
                $this->data['taxs'][ $tax_id ]['parents'][ $res['term_id'] ] = $term_data['parent'];

            if ( ! empty( $term_data['image'] ) )
                $this->data['taxs'][ $tax_id ]['images'][ $res['term_id'] ] = $term_data['image'];

            if ( 'regions' == $tax_id ) {
                foreach ( $term_data['listings'] as $listing_id ) {
                    if ( ! isset( $this->data['taxs'][ $tax_id ]['listings'][ $listing_id ] ) )
                        $this->data['taxs'][ $tax_id ]['listings'][ $listing_id ] = array();

                    $this->data['taxs'][ $tax_id ]['listings'][ $listing_id ][] = $res['term_id'];
                }

                if ( $term_data['enabled'] )
                    $wpdb->insert( $wpdb->prefix . 'wpbdp_regionmeta', array( 'region_id' => $res['term_id'], 'meta_key' => 'enabled', 'meta_value' => 1 ) );

                if ( $term_data['sidelist'] )
                    $wpdb->insert( $wpdb->prefix . 'wpbdp_regionmeta', array( 'region_id' => $res['term_id'], 'meta_key' => 'sidelist', 'meta_value' => 1 ) );
            }
        }

        $index++;
        $this->data['tax_part'] = $index;
    }

    private function part_fields() {
        global $wpdb;

        // Clear previous fields.
        $wpdb->query( "DELETE FROM {$wpdb->prefix}wpbdp_form_fields" );

        $this->data['fields'] = array();

        // Insert new fields.
        $fields = $this->read_part( 'fields', 0 );

        foreach ( $fields as &$f ) {
            $orig_id = $f['id'];
            unset( $f['id'] );
            $f['field_data'] = serialize( $f['field_data'] );

            $wpdb->insert( $wpdb->prefix . 'wpbdp_form_fields',
                           $f );

            $this->data['fields'][ $orig_id ] = $wpdb->insert_id;
        }

        // Update Qucik Search Fields settings
        $quick_search_fields = wpbdp_get_option( 'quick-search-fields', array() );
        $new_quick_search_fields = array();

        foreach ( $quick_search_fields as $field_id ) {
            if ( ! isset( $this->data['fields'][ $field_id ] ) ) {
                continue;
            }

            $new_quick_search_fields[] = $this->data['fields'][ $field_id ];
        }

        if ( $new_quick_search_fields ) {
            wpbdp_set_option( 'quick-search-fields', $new_quick_search_fields );
        }

        return true;
    }

    private function part_fees() {
        global $wpdb;

        // Clear previous fields.
        $wpdb->query( "DELETE FROM {$wpdb->prefix}wpbdp_plans" );

        $this->data['fees'] = array();
        $fees = $this->read_part( 'fees', 0 );

        foreach ( $fees as $f ) {
            $orig_id = $f['id'];
            unset( $f['id'] );

            if ( is_array( $f['supported_categories'] ) ) {
                $new_categories = array();

                foreach ( $f['supported_categories'] as $cat_id ) {
                    // TODO: 'terms' seems to have been replaced with 'taxs' and
                    //       the IDs seems to be stored in id_translation sub-index.
                    if ( ! empty( $this->data['terms']['categories'][ $cat_id ] ) ) {
                        $new_categories[] = $this->data['terms']['categories'][ $cat_id ];
                    }
                }

                if ( $new_categories ) {
                    $f['supported_categories'] = implode( ',', $new_categories );
                } else {
                    $f['supported_categories'] = 'all';
                }
            } else {
                $f['supported_categories'] = 'all';
            }

            $f['pricing_details'] = serialize( $f['pricing_details'] );
            $f['extra_data'] = serialize( $f['extra_data'] );

            $wpdb->insert( $wpdb->prefix . 'wpbdp_plans', $f );
            $this->data['fees'][ $orig_id ] = $wpdb->insert_id;
        }

        return true;
    }

    private function part_discount_codes() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wpbdp_coupons';

        if ( ! wpbdp_table_exists( $table_name ) ) {
            return true;
        }

        $wpdb->query( 'DELETE FROM ' . $table_name );
        $codes = $this->read_part( 'discount_codes' );

        foreach ( $codes as $c ) {
            // TODO: handle allowed_users better.
            $wpdb->insert( $table_name, (array) $c );
        }

        return true;
    }

    private function part_listings_delete() {
        global $wpdb;

        $ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", WPBDP_POST_TYPE ) );

        foreach ( $ids as $i )
            wp_delete_post( $i, true );
    }

    private function part_attachments() {
        $index = isset( $this->data['attachments_index'] ) ? intval( $this->data['attachments_index'] ) : 0;
        $attachments = $this->read_part( 'attachments', $index );

        if ( ! $attachments )
            return true;

        foreach ( $attachments as $listing_id => $listing_attachments ) {
            $new_id = $this->data['listings_id_translation'][ $listing_id ];

            if ( ! $new_id )
                continue;

            $meta = array();

            foreach ( $listing_attachments as $at ) {
                $path = $this->pack_dir . '/' . $at;

                if ( ! file_exists( $path ) )
                    continue;

                $upload = wpbdp_media_upload( $path, false, false );

                if ( ! $upload )
                    continue;

                $upload['path'] = $upload['file'];
                $upload['description'] = '';
                $upload['file'] = array( 'name' => basename( $upload['path'] ) );
                $upload['key'] = md5( $upload['path'] . '/' . time() );
                $upload['file_'] = _wp_relative_upload_path( $upload['path'] );

                $meta[ $upload['key'] ] = $upload;
            }

            update_post_meta( $new_id, '_wpbdp[attachments]', $meta );
        }

        $index++;
        $this->data['attachments_index'] = $index;
    }

    private function part_listings() {
        global $wpdb;

        if ( ! isset( $this->data['lindex'] ) ) {
            $this->data['lindex'] = 0;
            $this->data['listings_id_translation'] = array();
            $this->part_listings_delete();
        }

        $listings = $this->read_part( 'listings', $this->data['lindex'] );
        if ( ! $listings )
            return true;

        foreach ( $listings as $i => $l ) {
            $fields = $l['fields'];
            $plan = (array) $l['plan'];

            $images = $l['images'];
            $thumbnail = $l['thumbnail_id'];

            $payments = $l['payments'];

            $state = (object) array(
                'fields' => array(),
                'images' => array(),
                'categories' => array(),
                'post_status' => $l['status'],
            );

            foreach ( $fields as $field_id => $field_value ) {
                $new_id = $this->data['fields'][ $field_id ];
                $state->fields[ $new_id ] = $field_value;
            }

            foreach ( $l['categories'] as $c ) {
                $state->categories[] = $this->data['taxs']['categories']['id_translation'][ $c ];
            }

            $listing = wpbdp_save_listing( (array) $state );

            $this->data['listings_id_translation'][ $l['id'] ] = $listing->get_id();

            // Regions.
            if ( ! empty( $this->data['taxs']['regions']['listings'][ $l['id'] ] ) ) {
                $regions = array_map( 'intval', $this->data['taxs']['regions']['listings'][ $l['id'] ] );

                wp_set_object_terms( $listing->get_id(), $regions, 'wpbdm-region' );
                unset( $this->data['taxs']['regions']['listings'][ $l['id'] ] );
            }

            // Handle tags.
            foreach ( $l['tags'] as $t_id ) {
                $t_id = intval( $t_id );

                if ( empty( $this->data['taxs']['tags']['id_translation'][ $t_id ] ) )
                    continue;

                wp_set_object_terms( $listing->get_id(), $this->data['taxs']['tags']['id_translation'][ $t_id ], WPBDP_TAGS_TAX, true );
            }

            // Update author ID.
            if ( ! empty( $l['author'] ) ) {
                if ( $uid = username_exists( $l['author'] ) )
                    wp_update_post( array( 'ID' => $listing->get_id(), 'post_author' => $uid ) );
            }

            // Images.
            foreach ( $images as $img_file ) {
                $media_id = $this->upload_image( $img_file );

                if ( ! $media_id )
                    continue;

                $listing->set_images( array( $media_id ), true );

                if ( $thumbnail == $img_file )
                    $listing->set_thumbnail_id( $media_id );
            }

            // Plan.
            if ( isset( $plan['fee_id'] ) && isset( $this->data['fees'][ $plan['fee_id'] ] ) ) {
                $new_plan_id = $this->data['fees'][ $plan['fee_id'] ];
            } else {
                $new_plan_id = 0;
            }

            if ( $new_plan_id ) {
                $plan['listing_id'] = $listing->get_id();
                $plan['fee_id'] = $new_plan_id;

                $wpdb->delete( $wpdb->prefix . 'wpbdp_listings', array( 'listing_id' => $listing->get_id() ) );
                $wpdb->insert( $wpdb->prefix . 'wpbdp_listings', $plan );
            }

            // // Fees.
            // foreach ( $fees as $f ) {
            //     $recurring_data = array();
            //
            //     if ( ! empty( $f['recurring_id'] ) )
            //         $recurring_data['recurring_id'] = $f['recurring_id'];
            //
            //     if ( ! empty( $f['payment_id'] ) )
            //         $recurring_data['payment_id'] = $f['payment_id'];
            //
            //     if ( $recurring_data )
            //         $recurring_data = serialize( $recurring_data );
            //     else
            //         $recurring_data = null;
            //
            //     $wpdb->update( $wpdb->prefix . 'wpbdp_listing_fees',
            //                    array( 'listing_id' => $listing->get_id(),
            //                           'category_id' => $term_id,
            //                           'fee_id' => $fee_id,
            //                           'fee_days' => $f['fee_days'],
            //                           'fee_images' => $f['fee_images'],
            //                           'expires_on' => $f['expires_on'],
            //                           'recurring' => $f['recurring'],
            //                           'recurring_id' => $f['recurring_id'],
            //                           'recurring_data' => $recurring_data ),
            //                    array( 'listing_id' => $listing->get_id(), 'category_id' => $term_id ) );
            //
            //     if ( ! $f['expires_on'] )
            //         $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wpbdp_listing_fees SET expires_on = NULL WHERE listing_id = %d AND category_id = %d",
            //                                       $listing->get_id(),
            //                                       $term_id ) );
            // }

            // Geolocation.
            if ( ! empty( $l['geolocation'] ) )
                update_post_meta( $listing->get_id(), '_wpbdp[googlemaps][geolocation]', $l['geolocation'] );

            // Payments.
            foreach ( $payments as $p ) {
                $payment_row = $p;
                $payment_row['listing_id'] = $listing->get_id();

                unset( $payment_row['created_on'] );
                unset( $payment_row['payerinfo'] );
                unset( $payment_row['extra_data'] );
                unset( $payment_row['notes'] );
                unset( $payment_row['tag'] );

                $payment_items = unserialize( $payment_row['payment_items'] );
                foreach ( $payment_items as $i => $payment_item ) {
                    switch ( $payment_item['type'] ) {
                    case 'plan':
                    case 'recurring_plan':
                        if ( ! empty( $payment_item['fee_id'] ) ) {
                            $payment_items[ $i ]['fee_id'] = $this->data['fees'][ $payment_item['fee_id'] ];
                        }

                        if ( ! empty( $payment_item['rel_id_1'] ) && isset( $this->data['taxs']['categories']['id_translation'][ $payment_item['rel_id_1'] ] ) ) {
                            $payment_items[ $i ]['rel_id_1'] = $this->data['taxs']['categories']['id_translation'][ $payment_item['rel_id_1'] ];
                        }

                        if ( ! empty( $payment_item['rel_id_2'] ) ) {
                            $payment_items[ $i ]['rel_id_2'] = $this->data['fees'][ $payment_item['fee_id'] ];
                        }

                        break;
                    }
                }

                $payment_row['payment_items'] = serialize( $payment_items );

                $wpdb->insert( $wpdb->prefix . 'wpbdp_payments', $payment_row );
            }
        }

        $this->data['lindex'] = $this->data['lindex'] + 1;
    }

    private function part_ratings() {
        global $wpdb;

        if ( ! wpbdp_table_exists( $wpdb->prefix . 'wpbdp_ratings' ) ) {
            return true;
        }

        $wpdb->query( "DELETE FROM {$wpdb->prefix}wpbdp_ratings" );
        $ratings = $this->read_part( 'ratings' );

        foreach ( $ratings as $r ) {
            $r['listing_id'] = $this->data['listings_id_translation'][ $r['listing_id'] ];
            $r['user_id'] = 0;

            if ( $uid = username_exists( $r['user_name'] ) ) {
                $r['user_id'] = $uid;
                $r['user_name'] = '';
            }

            $wpdb->insert( $wpdb->prefix . 'wpbdp_ratings', $r );
        }

        return true;
    }

    private function part_authors() {
        $authors = $this->read_part( 'authors', 0 );

        foreach ( $authors as $author ) {
            if ( $current_uid = username_exists( $author['basic']['user_login'] ) )
                continue;

            $user_data = array(
                'user_login' => $author['basic']['user_login'],
                'user_url' => $author['basic']['user_url'],
                'user_pass' => $author['basic']['user_pass'],
                'user_nicename' => $author['basic']['user_nicename'],
                'user_email' => $author['basic']['user_email'],
                'display_name' => $author['basic']['display_name']
            );
            $new_id = wp_insert_user( $user_data );

            if ( is_wp_error( $new_id ) )
                continue;

            $user_data['ID'] = $new_id;

            // Update password.
            wp_insert_user( $user_data );
        }

        return true;
    }

    private function upload_image( $filename ) {
        $filepath = $this->pack_dir . DIRECTORY_SEPARATOR . $filename;

        if ( ! file_exists( $filepath ) )
            return false;

        // Make a copy of the file because wpbdp_media_upload() moves the original file.
        copy( $filepath, $filepath . '.backup' );
        $media_id = wpbdp_media_upload( $filepath, true, true );
        rename( $filepath . '.backup', $filepath );

        return $media_id;
    }

    public static function obtain_info_for_file( $f ) {
        require_once( ABSPATH . 'wp-admin/includes/class-pclzip.php' );

        $info = array();
        $info['path'] = $f;
        $info['filename'] = basename( $f );


        require_once( ABSPATH . 'wp-admin/includes/class-pclzip.php' );
        $zip = new PclZip( $f );

        $files = $zip->extract( PCLZIP_OPT_EXTRACT_AS_STRING, PCLZIP_OPT_BY_PREG, '((?<!\w)/{0,1}state\.obj)' );

        if ( ! $files )
            return false;

        $state = unserialize( $files[0]['content'] );

        if ( ! $state )
            return false;

        $info['filesize'] = filesize( $f );
        $info['date'] = $state['date'];
        $info['bd_version'] = $state['bd_version'];

        return $info;
    }

}
