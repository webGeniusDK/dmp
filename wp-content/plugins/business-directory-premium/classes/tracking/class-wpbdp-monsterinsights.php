<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Listing MonsterInsights tracking
 *
 * @since 5.2
 */
class WPBDP_MonsterInsights {

	public function __construct() {

		// Load the plugin.
		$this->init_monsterinsights();
	}

	/**
	 * Set up MonsterInsights tracking
	 */
	public function init_monsterinsights() {
		add_action( 'wpbdp_register_settings', array( $this, 'register_settings' ), 20 );

		if ( ! defined( 'MONSTERINSIGHTS_PRO_VERSION' ) ) {
			return;
		}

		$use_mi = wpbdp_get_option( 'listings-monsterinsights-enabled' );
		if ( ! $use_mi ) {
			return;
		}

		if ( version_compare( MONSTERINSIGHTS_VERSION, '7.0', '<' ) ) {
			add_action( 'admin_notices', array( $this, 'monsterinsights_version' ) );
			return;
		}

		add_filter( 'monsterinsights_get_option_hide_admin_bar_reports', array( $this, 'admin_bar_reports' ) );
		add_filter( 'user_has_cap', array( $this, 'listing_cap' ), 10, 4 );
	}

	/**
	 * Register admin settings
	 */
	public function register_settings( $settings ) {
		$is_pro_installed = defined( 'MONSTERINSIGHTS_PRO_VERSION' );

		if ( $is_pro_installed ) {
			wpbdp_register_setting(
				array(
					'id'      => 'listings-monsterinsights-enabled',
					'type'    => 'checkbox',
					'name'    => sprintf(
						/* translators: %s: name */
						__( 'Show %s Stats', 'wpbdp-pro' ),
						'MonsterInsights'
					),
					'desc'    => __( 'Show listing owners the Google Analytics page reports for their own listings', 'wpbdp-pro' ),
					'default' => false,
					'group'   => 'listings/stats',
				)
			);
			return;
		}

		wpbdp_register_setting(
			array(
				'id'    => 'listings-monsterinsights-enabled',
				'desc'  => wp_kses_post(
					sprintf(
						/* translators: %s: addon name */
						'<span>' . __( 'Install %1$s for advanced Google Analytics integration.', 'wpbdp-pro' ) . '</span>',
						self::cta()
					)
				),
				'type'  => 'education',
				'group' => 'listings/stats',
			)
		);
	}

	/**
	 * Version notification
	 */
	public function monsterinsights_version() {
		?>
		<div class="error is-dismissible">
			<p>
				<?php
				echo sprintf(
					/* translators: %s: addon name */
					esc_html__( 'Please install or update %1$s with version %2$s or newer to use the %3$s integration.', 'wpbdp-pro' ),
					wp_kses_post( self::cta() ),
					'7.4',
					'Business Directory MonsterInsights'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Show admin bar reports to the listing owner
	 *
	 * @param mixed  $value
	 * @param string $key
	 * @param mixed  $default
	 */
	public function admin_bar_reports( $value ) {
		if ( $value && WPBDP_Premium_Helper::current_user_is_listing_owner() ) {
			return false;
		}
		return $value;
	}

	public function listing_cap( $allcaps, $caps, $args, $user ) {
		global $post;
		$is_listing = $post && $post->post_type === WPBDP_POST_TYPE;
		if ( isset( $allcaps['monsterinsights_view_dashboard'] ) || ! $is_listing ) {
			return $allcaps;
		}

		$owner_listing_id = WPBDP_Premium_Helper::current_user_is_listing_owner( $user );
		if ( $owner_listing_id && ! isset( $allcaps['monsterinsights_view_dashboard'] ) ) {
			$allcaps['monsterinsights_view_dashboard'] = true;
		}
		return $allcaps;
	}

	/**
	 * MonsterInsights CTA link
	 *
	 * @return string
	 */
	private function cta() {
		return '<a href="https://businessdirectoryplugin.com/go/monsterinsights/" target="_blank" rel="noopener nofollow">MonsterInsights Pro</a> ';
	}
}
