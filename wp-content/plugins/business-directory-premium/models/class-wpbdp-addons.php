<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class WPBDP_Addons {

	private static function get_api_addons() {
		$api    = new WPBDP_Plugin_Api( 'auto' );
		$addons = $api->get_api_info();

		if ( empty( $addons ) ) {
			return $addons;
		}

		foreach ( $addons as $k => $addon ) {
			if ( empty( $addon['excerpt'] ) && $k !== 'error' ) {
				unset( $addons[ $k ] );
			}
		}

		return $addons;
	}

	private static function get_pro_from_addons( $addons ) {
		$id = 782898;
		return isset( $addons[ $id ] ) ? $addons[ $id ] : array();
	}

	public static function is_license_valid() {
		$addons = self::get_api_addons();
		$pro    = self::get_pro_from_addons( $addons );

		return empty( $pro['error'] ) && ! empty( $pro['url'] );
	}

	/**
	 * @since 4.08
	 *
	 * @return boolean|int false or the number of days until expiration.
	 */
	public static function is_license_expiring() {
		$version_info = self::get_primary_license_info();
		if ( ! isset( $version_info['active_sub'] ) || $version_info['active_sub'] !== 'no' ) {
			// Check for a subscription first.
			return false;
		}

		if ( ! isset( $version_info['error'] ) || empty( $version_info['expires'] ) ) {
			// It's either invalid or already expired.
			return false;
		}

		$expiration = $version_info['expires'];
		$days_left  = ( $expiration - time() ) / DAY_IN_SECONDS;
		if ( $days_left > 30 || $days_left < 0 ) {
			return false;
		}

		return $days_left;
	}

	private static function get_primary_license_info() {
		return self::fill_update_addon_info();
	}

	public static function check_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		global $wpbdp;
		$installed_addons = $wpbdp->licensing->get_items();

		$version_info = self::fill_update_addon_info();

		$transient->last_checked = time();

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$wp_plugins = get_plugins();

		foreach ( $version_info as $id => $plugin ) {
			$plugin = (object) $plugin;

			if ( ! isset( $plugin->new_version ) || ! isset( $plugin->package ) ) {
				continue;
			}

			$folder = $plugin->plugin;
			if ( empty( $folder ) ) {
				continue;
			}

			if ( ! self::is_installed( $folder ) ) {
				// don't show an update if the plugin isn't installed
				continue;
			}

			$wp_plugin  = isset( $wp_plugins[ $folder ] ) ? $wp_plugins[ $folder ] : array();
			$wp_version = isset( $wp_plugin['Version'] ) ? $wp_plugin['Version'] : '1.0';

			$slug         = explode( '/', $folder );
			$plugin->slug = $slug[0];

			if ( version_compare( $wp_version, $plugin->new_version, '<' ) ) {
				$transient->response[ $folder ] = $plugin;
			} else {
				$plugin = array(
					'version'   => $plugin->version,
					'link'      => $plugin->link,
				);
				$transient->no_update[ $folder ] = (object) $plugin;
			}

			$transient->checked[ $folder ] = $wp_version;
		}

		return $transient;
	}

	/**
	 * @since 5.0
	 *
	 * @param array $installed_addons
	 *
	 * @return array
	 */
	private static function fill_update_addon_info() {
		$api = new WPBDP_Plugin_Api( 'auto' );
		return $api->get_api_info();
	}

	/**
	 * Prevent a ping to the site if all licenses are already covered.
	 */
	public static function override_updates( $value ) {
		global $wpbdp;
		$installed_addons = $wpbdp->licensing->get_items();

		$version_info = self::fill_update_addon_info();
		foreach ( $installed_addons as $addon ) {
			$item_key = $addon['item_type'] . '-' . $addon['id'];
			if ( ! isset( $value[ $item_key ] ) || ! isset( $value[ $item_key ]->download_link ) || empty( $value[ $item_key ]->download_link ) ) {
				// Add to the array if we already have the update details.
				$file = $addon['slug'] ? $addon['slug'] : $addon['id'];
				$add_info = self::find_plugin_by_slug( $file, $version_info );
				if ( $add_info ) {
					$value[ $item_key ] = self::plugin_array_to_obj( $add_info );
				}
			}
		}

		return $value;
	}

	private static function find_plugin_by_slug( $slug, $addons ) {
		if ( empty( $slug ) ) {
			return false;
		}

		$theme_slug = $slug . '/theme.php';
		foreach ( $addons as $addon ) {
			if ( isset( $addon['plugin'] ) && ( $addon['plugin'] === $slug || $addon['plugin'] === $theme_slug ) ) {
				return $addon;
			}
		}
		return false;
	}

	/**
	 * Convert the server value to the value the plugin expects.
	 */
	private static function plugin_array_to_obj( $add_info ) {
		$array = $add_info;
		$array['download_id']  = $add_info['id'];
		$array['last_updated'] = $add_info['date'];
		if ( isset( $add_info['package'] ) ) {
			$array['download_link'] = $add_info['package'];
		}

		return (object) $array;
	}

	public static function add_bulk_licenses( &$license_data ) {
		$addons = self::get_api_addons();
		foreach ( $addons as $addon ) {
			if ( empty( $addon['url'] ) ) {
				continue;
			}

			$slug     = $addon['slug'];
			$is_theme = strpos( $slug, 'theme' );
			$key      = $is_theme ? 'theme-' : 'module-';
			$name     = explode( '/', $addon['plugin'] );

			if ( ! is_array( $name ) || 2 !== count( $name ) || empty( $license_data['module-business-directory-premium']['license_key'] ) ) {
				continue;
			}

			$license_data[ $key . $name[0] ] = array(
				'status'       => $addon['code'],
				'license_key'  => $license_data['module-business-directory-premium']['license_key'],
				'expires'      => gmdate( 'Y-m-d H:i:d', $addon['expires'] ),
				'last_checked' => time(),
			);
		}
	}

	/**
	 * Check if a plugin is installed before showing an update for it
	 *
	 * @since 3.05
	 *
	 * @param string $plugin - the folder/filename.php for a plugin
	 *
	 * @return bool - True if installed
	 */
	private static function is_installed( $plugin ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();

		return isset( $all_plugins[ $plugin ] );
	}

	/**
	 * Render a conditional action button for an add on
	 *
	 * @since 5.1
	 *
	 * @param array $atts includes the following:
	 *                    array $addon
	 *                    string|false $license_type
	 *                    string $plan_required
	 *                    string $upgrade_link
	 */
	public static function show_conditional_action_button( $atts ) {
		$addon         = $atts['addon'];
		$license_type  = $atts['license_type'];
		$plan_required = $atts['plan_required'];
		$upgrade_link  = $atts['upgrade_link'];
		if ( ! $addon ) {
			WPBDP_Show_Modules::addon_upgrade_link( $addon, $upgrade_link );

		} elseif ( $addon['status']['type'] === 'installed' ) {
			self::show_activate_link( $addon );
		} elseif ( ! empty( $addon['url'] ) ) {
			?>
			<a class="wpbdp-install-addon button button-primary" rel="<?php echo esc_attr( $addon['url'] ); ?>" aria-label="<?php esc_attr_e( 'Install', 'wpbdp-pro' ); ?>">
				<?php esc_html_e( 'Install', 'wpbdp-pro' ); ?>
			</a>
			<?php
		} elseif ( $license_type && $license_type === strtolower( $plan_required ) ) {
			?>
			<a class="install-now button button-secondary" href="<?php echo esc_url( wpbdp_admin_upgrade_link( 'addons', 'account/downloads/' ) . '&utm_content=' . $addon['slug'] ); ?>" target="_blank" aria-label="<?php esc_attr_e( 'Upgrade Now', 'wpbdp-pro' ); ?>">
				<?php esc_html_e( 'Renew Now', 'wpbdp-pro' ); ?>
			</a>
			<?php
		} else {
			WPBDP_Show_Modules::addon_upgrade_link( $addon, $upgrade_link );
		}
	}

	/**
	 * @since 5.2
	 */
	private static function show_activate_link( $addon ) {
		if ( is_callable( 'WPBDP_Show_Modules::addon_install_link' ) ) {
			WPBDP_Show_Modules::addon_install_link( $addon );
			return;
		}

		?>
		<a rel="<?php echo esc_attr( $addon['plugin'] ); ?>" class="button button-primary wpbdp-activate-addon <?php echo esc_attr( empty( $addon['activate_url'] ) ? 'wpbdp_hidden' : '' ); ?>">
			<?php esc_html_e( 'Activate', 'wpbdp-pro' ); ?>
		</a>
		<?php
	}
}
