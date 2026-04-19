<?php
/**
 * Plugin Name:       Payphone WC Modal
 * Plugin URI:        https://github.com/gabrielsaenz20/Payphone-WC
 * Description:       Integra Payphone como método de pago en WooCommerce usando un modal en la página de checkout sin redirigir al cliente.
 * Version:           1.0.0
 * Author:            Gabriel Saenz
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       payphone-wc-modal
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 * WC tested up to:   8.9
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PAYPHONE_WC_VERSION', '1.0.0' );
define( 'PAYPHONE_WC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PAYPHONE_WC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Show an admin notice when WooCommerce is not active.
 */
function payphone_wc_missing_woocommerce_notice() {
	echo '<div class="notice notice-error"><p>'
		. esc_html__( 'Payphone WC Modal requiere que WooCommerce esté instalado y activo.', 'payphone-wc-modal' )
		. '</p></div>';
}

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
 */
function payphone_wc_declare_hpos_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
}
add_action( 'before_woocommerce_init', 'payphone_wc_declare_hpos_compatibility' );

/**
 * Bootstrap: load gateway and register hooks after all plugins are loaded.
 */
add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'payphone_wc_missing_woocommerce_notice' );
		return;
	}

	require_once PAYPHONE_WC_PLUGIN_DIR . 'includes/class-payphone-gateway.php';

	// Register the payment gateway with WooCommerce.
	add_filter( 'woocommerce_payment_gateways', function ( $gateways ) {
		$gateways[] = 'Payphone_WC_Gateway';
		return $gateways;
	} );

	// AJAX: retrieve stored payment data so the modal can initialise the box.
	add_action( 'wp_ajax_payphone_get_payment_data', 'payphone_wc_get_payment_data' );
	add_action( 'wp_ajax_nopriv_payphone_get_payment_data', 'payphone_wc_get_payment_data' );

	// AJAX: server-side confirmation with Payphone API.
	add_action( 'wp_ajax_payphone_confirm_payment', 'payphone_wc_confirm_payment' );
	add_action( 'wp_ajax_nopriv_payphone_confirm_payment', 'payphone_wc_confirm_payment' );

	// AJAX: cancel / abandon a pending order.
	add_action( 'wp_ajax_payphone_cancel_payment', 'payphone_wc_cancel_payment' );
	add_action( 'wp_ajax_nopriv_payphone_cancel_payment', 'payphone_wc_cancel_payment' );

	// Register Payphone as a WooCommerce Blocks payment method so it appears
	// in the block-based Checkout (WooCommerce 7.6+ / Gutenberg).
	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function ( $payment_method_registry ) {
			require_once PAYPHONE_WC_PLUGIN_DIR . 'includes/class-payphone-blocks.php';
			$payment_method_registry->register( new Payphone_WC_Blocks() );
		}
	);
} );

// ---------------------------------------------------------------------------
// AJAX Handlers
// ---------------------------------------------------------------------------

/**
 * Return the payment data stored in the WC session so the JS modal can
 * render the Payphone box with the correct amounts and credentials.
 */
function payphone_wc_get_payment_data() {
	check_ajax_referer( 'payphone_nonce', 'nonce' );

	$order_id     = WC()->session->get( 'payphone_order_id' );
	$payment_data = WC()->session->get( 'payphone_payment_data' );

	if ( ! $order_id || ! $payment_data ) {
		wp_send_json_error(
			array( 'message' => __( 'No se encontraron datos de pago. Por favor recarga la página e intenta de nuevo.', 'payphone-wc-modal' ) )
		);
		return;
	}

	wp_send_json_success( $payment_data );
}

/**
 * Verify the transaction with Payphone's server-side Confirm API, then mark
 * the WooCommerce order as paid and return the thank-you URL.
 */
function payphone_wc_confirm_payment() {
	check_ajax_referer( 'payphone_nonce', 'nonce' );

	$transaction_id        = isset( $_POST['transactionId'] ) ? sanitize_text_field( wp_unslash( $_POST['transactionId'] ) ) : '';
	$client_transaction_id = isset( $_POST['clientTransactionId'] ) ? sanitize_text_field( wp_unslash( $_POST['clientTransactionId'] ) ) : '';
	$order_id              = WC()->session->get( 'payphone_order_id' );

	if ( ! is_numeric( $transaction_id ) || ! $client_transaction_id || ! $order_id ) {
		wp_send_json_error(
			array( 'message' => __( 'Datos de transacción inválidos.', 'payphone-wc-modal' ) )
		);
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		wp_send_json_error(
			array( 'message' => __( 'Orden no encontrada.', 'payphone-wc-modal' ) )
		);
		return;
	}

	// Verify that the clientTransactionId matches what was stored in the session
	// to prevent session-data tampering attacks.
	$stored_payment_data   = WC()->session->get( 'payphone_payment_data' );
	$stored_client_txn_id  = isset( $stored_payment_data['clientTransactionId'] ) ? $stored_payment_data['clientTransactionId'] : '';

	if ( ! hash_equals( $stored_client_txn_id, $client_transaction_id ) ) {
		wp_send_json_error(
			array( 'message' => __( 'ID de transacción no coincide con la sesión activa.', 'payphone-wc-modal' ) )
		);
		return;
	}

	// Retrieve the token stored in gateway settings.
	$gateway = new Payphone_WC_Gateway();
	$token   = $gateway->get_option( 'token' );

	// Call Payphone Confirm API.
	$response = wp_remote_post(
		'https://pay.payphone.app/api/button/V2/confirm',
		array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'id'                  => (int) $transaction_id,
					'clientTransactionId' => $client_transaction_id,
				)
			),
			'timeout' => 30,
		)
	);

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		return;
	}

	$body   = json_decode( wp_remote_retrieve_body( $response ), true );
	$status = isset( $body['transactionStatus'] ) ? $body['transactionStatus'] : '';

	if ( 'Approved' === $status ) {
		$order->payment_complete( $transaction_id );
		$order->add_order_note(
			sprintf(
				/* translators: %s: Payphone transaction ID */
				__( 'Pago aprobado por Payphone. ID de transacción: %s', 'payphone-wc-modal' ),
				esc_html( $transaction_id )
			)
		);

		// Clean up session.
		WC()->session->set( 'payphone_order_id', null );
		WC()->session->set( 'payphone_payment_data', null );

		wp_send_json_success(
			array( 'redirect' => $order->get_checkout_order_received_url() )
		);
	} else {
		$order->update_status(
			'failed',
			__( 'Pago rechazado o no aprobado por Payphone.', 'payphone-wc-modal' )
		);
		wp_send_json_error(
			array(
				'message' => isset( $body['message'] )
					? sanitize_text_field( $body['message'] )
					: __( 'El pago no fue aprobado. Por favor intenta de nuevo.', 'payphone-wc-modal' ),
			)
		);
	}
}

/**
 * Mark a pending order as cancelled when the customer closes the Payphone
 * modal without completing payment.
 */
function payphone_wc_cancel_payment() {
	check_ajax_referer( 'payphone_nonce', 'nonce' );

	$order_id = WC()->session->get( 'payphone_order_id' );

	if ( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && $order->has_status( 'pending' ) ) {
			$order->update_status(
				'cancelled',
				__( 'Pago cancelado por el cliente desde el modal de Payphone.', 'payphone-wc-modal' )
			);
		}
		WC()->session->set( 'payphone_order_id', null );
		WC()->session->set( 'payphone_payment_data', null );
	}

	wp_send_json_success( array( 'message' => __( 'Pago cancelado.', 'payphone-wc-modal' ) ) );
}
