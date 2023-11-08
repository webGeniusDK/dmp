<?php

global $wpdb;

define( 'WPBDP_REGIONS_MODULE_META_TABLE', $wpdb->prefix . 'wpbdp_regionmeta' );

class WPBDP_RegionsPluginInstaller {

    /**
     * @var object  An instance of Regions Plugin.
     */
    private $plugin;

    public function __construct( $plugin ) {
        $this->plugin = $plugin;
    }

    public function activate() {
        global $wpdb;

		update_option( 'wpbdp-regions-flush-rewrite-rules', true );

        // Form Fields are hidden when the module is deactivated
        // so we have to show them again when the module is
        // activated.
		update_option( 'wpbdp-regions-show-fields', true );
    }

    public function deactivate() {
        wpbdp_regions_fields_api()->hide_fields();
    }

    public function uninstall() {
		foreach ( wpbdp_regions()->options as $option ) {
			delete_option( $option );
        }
    }

    public function upgrade_check() {
        $plugin_version = $this->plugin->get_version();
		$installed_version = get_option( 'wpbdp-regions-db-version', '0' );
		if ( $installed_version === $plugin_version ) {
			return;
		}

		$this->upgrade( $installed_version, $plugin_version );

		update_option( 'wpbdp-regions-create-default-regions', true );
		update_option( 'wpbdp-regions-db-version', $plugin_version );
    }

	private function upgrade( $oldversion, $newversion ) {
		if ( $oldversion == $newversion ) {
			return;
		}

		if ( version_compare( $oldversion, '1.1', '<=' ) && version_compare( $newversion, '1.2dev', '>=' ) ) {
			$fields = wpbdp_get_form_fields( array( 'association' => 'region', 'display_flags' => array( 'search' ) ) );

			foreach ( $fields as &$f ) {
				if ( ! $f->has_display_flag( 'region-selector' ) ) {
					$f->add_display_flag( 'region-selector' );
                    $f->save();
                }
            }
        }

        if ( version_compare( $oldversion, '3.6.2', '<' ) ) {
            $old_show_conts_value = (bool) wpbdp_get_option( 'regions-sidelist-counts' );
            wpbdp_set_option( 'regions-show-counts', $old_show_conts_value );
        }

        if ( version_compare( $oldversion, '5.1.5', '<' ) ) {
            $fields = get_option( 'wpbdp-regions-form-fields' );

			if ( $fields ) {

				foreach ( $fields as $level => $id ) {
                    $field = wpbdp_get_form_field( $id );
                    if ( ! $field ) {
                        continue;
                    }
                    $field->set_data( 'level', $level );

                    $field->save();
                }
            }
        }

		if ( version_compare( $oldversion, '5.3', '<' ) ) {
			$this->migrate_to_4();
		}

		return update_option( 'wpbdp-regions-db-version', $newversion );
    }

	/**
	 * Migrate from custom table to WP term meta in v5.3.
	 */
	private function migrate_to_4() {
		global $wpdb;
		$table = $wpdb->prefix . 'wpbdp_regionmeta';
		$count = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table );

		if ( ! $count ) {
			return;
		}

		wpbdp_set_option( 'regions-legacy-urls', true );

		$limit   = 100;
		$batches = $count / $limit; // Number of while-loop calls.
		for ( $i = 0; $i <= $batches; $i++ ) {
			$offset = $i * $limit;
			$rows   = $wpdb->get_results( 'SELECT * FROM ' . $table . " LIMIT $limit OFFSET $offset" );
			$moved  = array();
			foreach ( $rows as $row ) {
				// Only save disabled regions.
				$skip = $row->meta_key === 'sidelist' || ( $row->meta_key === 'enabled' && $row->meta_value );
				if ( ! $skip ) {
					add_term_meta( $row->region_id, $row->meta_key, $row->meta_value );
				}
				$moved[] = $row->meta_id;
			}

			if ( ! empty( $moved ) ) {
				$moved = implode( ',', array_map( 'absint', $moved ) );
				$wpdb->query( "DELETE FROM $table WHERE meta_id IN ($moved)" );
			}
		}
	}
}
