<?php
/**
 * Payphone WC Modal – WooCommerce Payment Gateway
 *
 * @package Payphone_WC_Modal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Payphone_WC_Gateway class.
 *
 * Registers "Payphone" as a WooCommerce payment method that renders a
 * Payphone Cajita de Pagos inside a modal overlay on the checkout page,
 * so the customer never has to leave the site.
 */
class Payphone_WC_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor – set up the gateway ID, labels, and settings.
	 */
	public function __construct() {
		$this->id                 = 'payphone_modal';
		$this->icon               = '';
		$this->has_fields         = false;
		$this->method_title       = __( 'Payphone', 'payphone-wc-modal' );
		$this->method_description = __( 'Acepta pagos con Payphone mediante una ventana emergente segura en la página de checkout, sin redirigir al cliente.', 'payphone-wc-modal' );
		$this->supports           = array( 'products' );

		// Load settings.
		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		// Default to 'yes' so the gateway shows immediately after installation
		// before the admin has saved the settings page for the first time.
		$this->enabled     = $this->get_option( 'enabled', 'yes' );

		// Hook: save admin settings.
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'process_admin_options' )
		);

		// Hook: enqueue frontend scripts only on checkout.
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

		// Add type="module" to the Payphone CDN script tag (ES module).
		add_filter( 'script_loader_tag', array( $this, 'add_module_type_attribute' ), 10, 2 );
	}

	/**
	 * Inject type="module" on the Payphone CDN script tag so the browser
	 * treats it as an ES module (required for PPaymentButtonBox to be exported).
	 *
	 * @param string $tag    Full <script> tag HTML.
	 * @param string $handle Script handle.
	 * @return string Modified tag.
	 */
	public function add_module_type_attribute( $tag, $handle ) {
		if ( 'payphone-box-js' === $handle ) {
			// Only replace the first occurrence to avoid duplication.
			return preg_replace( '/<script\s/', '<script type="module" ', $tag, 1 );
		}
		return $tag;
	}

	// -----------------------------------------------------------------------
	// Admin settings
	// -----------------------------------------------------------------------

	/**
	 * Define the gateway settings fields shown in WooCommerce → Settings →
	 * Payments → Payphone.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Habilitar / Deshabilitar', 'payphone-wc-modal' ),
				'type'    => 'checkbox',
				'label'   => __( 'Habilitar Payphone en el checkout', 'payphone-wc-modal' ),
				'default' => 'yes',
			),
			'title'       => array(
				'title'       => __( 'Título', 'payphone-wc-modal' ),
				'type'        => 'text',
				'description' => __( 'Nombre del método de pago que verá el cliente en el checkout.', 'payphone-wc-modal' ),
				'default'     => __( 'Payphone', 'payphone-wc-modal' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Descripción', 'payphone-wc-modal' ),
				'type'        => 'textarea',
				'description' => __( 'Descripción visible para el cliente bajo el método de pago.', 'payphone-wc-modal' ),
				'default'     => __( 'Paga de forma segura con Payphone. Se abrirá una ventana emergente para completar tu pago sin salir de esta página.', 'payphone-wc-modal' ),
			),
			'token'       => array(
				'title'       => __( 'Token de API', 'payphone-wc-modal' ),
				'type'        => 'password',
				'description' => __( 'Token Bearer obtenido desde la consola de desarrollador de Payphone (pay.payphone.app).', 'payphone-wc-modal' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'store_id'    => array(
				'title'       => __( 'Store ID', 'payphone-wc-modal' ),
				'type'        => 'text',
				'description' => __( 'ID de tu tienda en Payphone (storeId).', 'payphone-wc-modal' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'reference'   => array(
				'title'       => __( 'Referencia del pago', 'payphone-wc-modal' ),
				'type'        => 'text',
				'description' => __( 'Motivo / descripción del pago que aparece en Payphone (se añade el número de orden automáticamente).', 'payphone-wc-modal' ),
				'default'     => __( 'Compra en tienda', 'payphone-wc-modal' ),
				'desc_tip'    => true,
			),
			'bg_color'    => array(
				'title'       => __( 'Color del botón Payphone', 'payphone-wc-modal' ),
				'type'        => 'color',
				'description' => __( 'Color de fondo del botón de pago dentro de la caja Payphone.', 'payphone-wc-modal' ),
				'default'     => '#6610f2',
				'desc_tip'    => true,
			),
		);
	}

	// -----------------------------------------------------------------------
	// Frontend scripts
	// -----------------------------------------------------------------------

	/**
	 * Enqueue the Payphone CDN files plus our own modal CSS/JS on the
	 * checkout page.
	 */
	public function payment_scripts() {
		if ( ! is_checkout() || 'no' === $this->enabled ) {
			return;
		}

		// Payphone CDN – payment box stylesheet.
		wp_enqueue_style(
			'payphone-box-css',
			'https://cdn.payphonetodoesposible.com/box/v1.1/payphone-payment-box.css',
			array(),
			null
		);

		/*
		 * Payphone CDN – ES-module script.
		 * The type="module" attribute is injected by add_module_type_attribute().
		 */
		wp_enqueue_script(
			'payphone-box-js',
			'https://cdn.payphonetodoesposible.com/box/v1.1/payphone-payment-box.js',
			array(),
			null,
			true
		);

		// Our modal stylesheet.
		wp_enqueue_style(
			'payphone-modal-css',
			PAYPHONE_WC_PLUGIN_URL . 'assets/css/payphone-modal.css',
			array(),
			PAYPHONE_WC_VERSION
		);

		// Our checkout JS (depends on jQuery, loads in footer).
		wp_enqueue_script(
			'payphone-checkout-js',
			PAYPHONE_WC_PLUGIN_URL . 'assets/js/payphone-checkout.js',
			array( 'jquery' ),
			PAYPHONE_WC_VERSION,
			true
		);

		// Pass PHP data to JS.
		wp_localize_script(
			'payphone-checkout-js',
			'payphoneParams',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'payphone_nonce' ),
				'gatewayId'   => $this->id,
				'cancelText'  => __( 'Cancelar Pago', 'payphone-wc-modal' ),
				'errorText'   => __( 'Ocurrió un error al procesar el pago. Por favor intenta de nuevo.', 'payphone-wc-modal' ),
				'processingText' => __( 'Procesando pago…', 'payphone-wc-modal' ),
			)
		);
	}

	// -----------------------------------------------------------------------
	// Payment processing
	// -----------------------------------------------------------------------

	/**
	 * Called by WooCommerce when the customer submits the checkout form with
	 * Payphone selected.
	 *
	 * Instead of charging the card here, we:
	 *  1. Store the order amounts + Payphone credentials in the WC session.
	 *  2. Return a "redirect" to a URL hash (#payphone-modal-open) so the
	 *     browser stays on the checkout page.
	 *  3. Our JS detects the hash and opens the Payphone modal.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array WC redirect result.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// ---- Amount calculation (Payphone expects integers in cents) --------
		$total_cents  = (int) round( $order->get_total() * 100 );
		$tax_cents    = (int) round( $order->get_total_tax() * 100 );
		$pretax_cents = max( 0, $total_cents - $tax_cents ); // Base before tax, clamped to ≥ 0.

		/*
		 * Payphone fields:
		 *  amount            = total (cents)
		 *  amountWithoutTax  = untaxed base (cents)
		 *  amountWithTax     = taxable base (cents)  — 0 when items are all taxed
		 *  tax               = IVA amount (cents)
		 *
		 * For maximum compatibility across different store tax configurations
		 * we treat the entire pre-tax amount as "untaxed base" and pass the IVA
		 * separately. The totals always balance: pretax + 0 + tax = total.
		 */
		$amount_without_tax = $pretax_cents;
		$amount_with_tax    = 0;

		// Unique client transaction ID tied to this order attempt.
		$client_transaction_id = 'WC-' . $order_id . '-' . time();

		$payment_data = array(
			'token'               => $this->get_option( 'token' ),
			'amount'              => $total_cents,
			'amountWithoutTax'    => $amount_without_tax,
			'amountWithTax'       => $amount_with_tax,
			'tax'                 => $tax_cents,
			'service'             => 0,
			'tip'                 => 0,
			'storeId'             => $this->get_option( 'store_id' ),
			'reference'           => $this->get_option( 'reference' ) . ' #' . $order_id,
			'currency'            => get_woocommerce_currency(),
			'clientTransactionId' => $client_transaction_id,
			'backgroundColor'     => $this->get_option( 'bg_color', '#6610f2' ),
			// responseUrl: where Payphone redirects the browser after payment.
			// Our handler confirms the transaction and redirects to order-received.
			'responseUrl'         => add_query_arg(
				array(
					'action'              => 'payphone_response_redirect',
					'clientTransactionId' => $client_transaction_id,
				),
				admin_url( 'admin-ajax.php' )
			),
		);

		// Persist in the WC session so the AJAX handler can read it back.
		WC()->session->set( 'payphone_order_id', $order_id );
		WC()->session->set( 'payphone_payment_data', $payment_data );

		// Mark order pending. Stock reduction and cart clearing happen here;
		// WooCommerce will automatically restore stock if the order is cancelled,
		// and payment_complete() will trigger the final stock update on success.
		$order->update_status( 'pending', __( 'Esperando confirmación de pago de Payphone.', 'payphone-wc-modal' ) );
		wc_reduce_stock_levels( $order_id );
		WC()->cart->empty_cart();

		/*
		 * Returning '#payphone-modal-open' as the redirect URL causes the WC JS
		 * to do `window.location = '#payphone-modal-open'`, which is a hash
		 * change — the checkout page stays loaded and our JS opens the modal.
		 */
		return array(
			'result'   => 'success',
			'redirect' => '#payphone-modal-open',
		);
	}
}
