<?php

/**
 * Register the Stripe module.
 */
class WPBDP__Stripe {

	/**
	 * The short key used to represent this plugin.
	 *
	 * @var string
	 */
	public $id = 'stripe';

	/**
	 * The main plugin file for this plugin.
	 *
	 * @var string
	 */
	public $file;

	/**
	 * Name of this plugin.
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Main plugin version required.
	 *
	 * @var string
	 */
	public $required_bd_version = '5.5.4';

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	public $version = '5.5';

	/**
	 * @param WPBDP__Modules $modules Modules API.
	 */
	public static function load( $modules ) {
		$modules->load( new self() );
	}

	public function __construct() {
		$this->file  = dirname( __DIR__ ) . '/business-directory-stripe.php';
		$this->title = 'Stripe Payment Module';
	}

	public function init() {
		add_action( 'wp', array( $this, 'register_scripts' ) );

		add_action( 'wpbdp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		add_filter( 'wpbdp_payment_gateways', array( $this, 'register_gateway' ) );
	}

	public function register_scripts() {
		// See {@link https://stripe.com/docs/checkout#integration-custom}.
		wp_register_script(
			'wpbdp-stripe-checkout',
			plugins_url( 'assets/stripe-checkout-custom-integration.js', $this->file ),
			array(),
			$this->version,
			true
		);
	}

	public function enqueue_styles() {
		wp_enqueue_style(
			'wpbdm-stripe',
			plugins_url( 'assets/styles.css', $this->file ),
			array(),
			$this->version
		);
	}

	/**
	 * @param array $gateways Existing gateways.
	 */
	public function register_gateway( $gateways ) {
		require_once plugin_dir_path( $this->file ) . 'includes/class-stripe-gateway.php';
		$gateways[] = new WPBDP__Stripe__Gateway( $this->version );
		return $gateways;
	}
}
