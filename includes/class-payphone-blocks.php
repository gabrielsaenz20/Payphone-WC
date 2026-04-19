<?php
/**
 * Payphone WC Modal – WooCommerce Blocks payment method integration.
 *
 * Registers the gateway so it appears in the block-based checkout
 * (Gutenberg Checkout block, default in WooCommerce 8.x+).
 *
 * @package Payphone_WC_Modal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Payphone_WC_Blocks class.
 *
 * Provides the gateway title, description, and JS needed to display
 * the Payphone payment option inside the WooCommerce Checkout block.
 */
class Payphone_WC_Blocks extends AbstractPaymentMethodType {

	/**
	 * Payment method name/id used by the Blocks registry – must match the
	 * gateway's $this->id value.
	 *
	 * @var string
	 */
	protected $name = 'payphone_modal';

	/**
	 * Read the saved gateway settings from the database.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_payphone_modal_settings', array() );
	}

	/**
	 * Returns true when the gateway is enabled in WooCommerce settings.
	 *
	 * @return bool
	 */
	public function is_active() {
		$enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
		return 'yes' === $enabled;
	}

	/**
	 * Register and return the JS handle(s) needed by the Checkout block.
	 *
	 * @return string[]
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'payphone-blocks-js',
			PAYPHONE_WC_PLUGIN_URL . 'assets/js/payphone-blocks.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			PAYPHONE_WC_VERSION,
			true
		);

		return array( 'payphone-blocks-js' );
	}

	/**
	 * Return data that will be available to the frontend blocks JS via
	 * `getSetting('payphone_modal_data', {})`.
	 *
	 * Includes the AJAX URL and a nonce so the blocks JS can call the
	 * `payphone_get_cart_payment_data` action to fetch fresh payment data
	 * (token, amounts, clientTransactionId) from the server. This mirrors
	 * what `payment_fields()` does for the classic checkout.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => isset( $this->settings['title'] ) && $this->settings['title']
				? $this->settings['title']
				: __( 'Payphone', 'payphone-wc-modal' ),
			'description' => isset( $this->settings['description'] ) ? $this->settings['description'] : '',
			'supports'    => array( 'products' ),
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'payphone_nonce' ),
			'errorText'   => __( 'Completa el pago con Payphone antes de continuar.', 'payphone-wc-modal' ),
		);
	}
}
