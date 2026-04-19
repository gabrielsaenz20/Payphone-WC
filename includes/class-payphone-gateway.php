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
		$this->has_fields         = true;
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

		// Add type="module" to our checkout script so it can use a static
		// import of PPaymentButtonBox directly from the Payphone CDN.
		add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_script' ), 10, 2 );

		// WC API endpoint: Payphone redirects the browser here after payment.
		// URL: {home}/wc-api/payphone_cajita/  – configure this in Payphone dashboard.
		add_action( 'woocommerce_api_payphone_cajita', array( $this, 'handle_response_url' ) );
	}

	// -----------------------------------------------------------------------
	// Classic checkout payment fields
	// -----------------------------------------------------------------------

	/**
	 * Output the payment fields HTML for the classic WooCommerce checkout.
	 *
	 * Calculates cart totals on the server and embeds all Payphone payment
	 * parameters directly as a JSON data attribute on #pp-button. This lets
	 * the JS render PPaymentButtonBox immediately on selection without any AJAX
	 * round-trip, mirroring exactly how the working test.html works.
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo '<p class="payphone-description">' . esc_html( $this->description ) . '</p>';
		}

		// Build payment data from the current cart.
		$cart = WC()->cart;
		$cart->calculate_totals();

		$total_cents  = (int) round( $cart->get_total( 'edit' ) * 100 );
		$tax_cents    = (int) round( $cart->get_total_tax() * 100 );
		$pretax_cents = max( 0, $total_cents - $tax_cents );

		$client_transaction_id = 'WC-INLINE-' . time() . '-' . wp_rand( 1000, 9999 );

		$payment_data = array(
			'token'               => $this->get_option( 'token' ),
			'amount'              => $total_cents,
			'amountWithoutTax'    => $pretax_cents,
			'amountWithTax'       => 0,
			'tax'                 => $tax_cents,
			'service'             => 0,
			'tip'                 => 0,
			'storeId'             => $this->get_option( 'store_id' ),
			'reference'           => $this->get_option( 'reference', __( 'Compra en tienda', 'payphone-wc-modal' ) ),
			'currency'            => get_woocommerce_currency(),
			'clientTransactionId' => $client_transaction_id,
			'backgroundColor'     => $this->get_option( 'bg_color', '#6610f2' ),
			'responseUrl'         => WC()->api_request_url( 'payphone_cajita' ),
		);

		// Store in session so process_payment() can verify clientTransactionId.
		WC()->session->set( 'payphone_cart_payment_data', $payment_data );

		// Hidden inputs carry the Payphone transaction IDs to process_payment().
		echo '<input type="hidden" name="payphone_transaction_id" id="payphone_transaction_id" value="">';
		echo '<input type="hidden" name="payphone_client_transaction_id" id="payphone_client_transaction_id" value="">';

		// Embed payment data directly – JS reads this and renders the box
		// immediately on selection, with no AJAX call needed.
		echo '<div id="pp-button" data-payphone="' . esc_attr( wp_json_encode( $payment_data ) ) . '"></div>';
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
			'enabled'           => array(
				'title'   => __( 'Habilitar / Deshabilitar', 'payphone-wc-modal' ),
				'type'    => 'checkbox',
				'label'   => __( 'Habilitar Payphone en el checkout', 'payphone-wc-modal' ),
				'default' => 'yes',
			),
			'title'             => array(
				'title'       => __( 'Título', 'payphone-wc-modal' ),
				'type'        => 'text',
				'description' => __( 'Nombre del método de pago que verá el cliente en el checkout.', 'payphone-wc-modal' ),
				'default'     => __( 'Payphone', 'payphone-wc-modal' ),
				'desc_tip'    => true,
			),
			'description'       => array(
				'title'       => __( 'Descripción', 'payphone-wc-modal' ),
				'type'        => 'textarea',
				'description' => __( 'Descripción visible para el cliente bajo el método de pago.', 'payphone-wc-modal' ),
				'default'     => __( 'Paga de forma segura con Payphone. Se abrirá una ventana emergente para completar tu pago sin salir de esta página.', 'payphone-wc-modal' ),
			),
			'token'             => array(
				'title'       => __( 'Token de API', 'payphone-wc-modal' ),
				'type'        => 'password',
				'description' => __( 'Token Bearer obtenido desde la consola de desarrollador de Payphone (pay.payphone.app).', 'payphone-wc-modal' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'store_id'          => array(
				'title'       => __( 'Store ID', 'payphone-wc-modal' ),
				'type'        => 'text',
				'description' => __( 'ID de tu tienda en Payphone (storeId).', 'payphone-wc-modal' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'reference'         => array(
				'title'       => __( 'Referencia del pago', 'payphone-wc-modal' ),
				'type'        => 'text',
				'description' => __( 'Motivo / descripción del pago que aparece en Payphone (se añade el número de orden automáticamente).', 'payphone-wc-modal' ),
				'default'     => __( 'Compra en tienda', 'payphone-wc-modal' ),
				'desc_tip'    => true,
			),
			'bg_color'          => array(
				'title'       => __( 'Color del botón Payphone', 'payphone-wc-modal' ),
				'type'        => 'color',
				'description' => __( 'Color de fondo del botón de pago dentro de la caja Payphone.', 'payphone-wc-modal' ),
				'default'     => '#6610f2',
				'desc_tip'    => true,
			),
			'response_url_info' => array(
				'title'       => __( 'URL de Respuesta (Payphone)', 'payphone-wc-modal' ),
				'type'        => 'title',
				/* translators: %s: WC API response URL */
				'description' => sprintf(
					__( 'Configura la siguiente URL como <strong>URL de Respuesta</strong> en la consola de Payphone (pay.payphone.app):<br><code>%s</code>', 'payphone-wc-modal' ),
					esc_url( home_url( '/wc-api/payphone_cajita/' ) )
				),
			),
		);
	}

	// -----------------------------------------------------------------------
	// Frontend scripts
	// -----------------------------------------------------------------------

	/**
	 * Add type="module" to the payphone checkout script tag so it can use
	 * a static ES-module import of PPaymentButtonBox directly from the CDN,
	 * matching exactly the pattern used in the working test.html.
	 *
	 * @param string $tag    Full <script> tag HTML.
	 * @param string $handle Script handle registered with wp_enqueue_script.
	 * @return string
	 */
	public function add_module_type_to_script( $tag, $handle ) {
		if ( 'payphone-checkout-js' === $handle ) {
			return str_replace( ' src=', ' type="module" src=', $tag );
		}
		return $tag;
	}

	/**
	 * Enqueue the Payphone CDN stylesheet plus our own modal CSS/JS on the
	 * checkout page. The CDN JS itself is loaded via a <script type="module">
	 * in output_payphone_module_script() so that its named export
	 * (PPaymentButtonBox) can be assigned to window and accessed by our classic
	 * payphone-checkout.js through the global scope.
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
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'payphone_nonce' ),
				'gatewayId'      => $this->id,
				'errorText'      => __( 'Ocurrió un error al procesar el pago. Por favor intenta de nuevo.', 'payphone-wc-modal' ),
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
	 * Inline flow (primary):
	 *  The customer already approved the payment in the PPaymentButtonBox
	 *  before the form was submitted. The JS fills two hidden fields
	 *  (payphone_transaction_id and payphone_client_transaction_id) and then
	 *  triggers the Place Order button. We verify the clientTransactionId
	 *  against the WC session, call Payphone Confirm API, and mark the order
	 *  as paid immediately.
	 *
	 * Fallback flow:
	 *  If the hidden fields are absent (e.g. block checkout, JS failure) we
	 *  store the order amounts in the WC session and return a hash redirect
	 *  so our JS can open the Payphone box after order creation.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array WC redirect result.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// ---- Inline flow: payment was approved before form submission -------
		$pp_txn_id    = isset( $_POST['payphone_transaction_id'] )
			? sanitize_text_field( wp_unslash( $_POST['payphone_transaction_id'] ) )
			: '';
		$pp_client_id = isset( $_POST['payphone_client_transaction_id'] )
			? sanitize_text_field( wp_unslash( $_POST['payphone_client_transaction_id'] ) )
			: '';

		if ( $pp_txn_id && is_numeric( $pp_txn_id ) && $pp_client_id ) {
			// Verify clientTransactionId against the session to prevent replays.
			$cart_data = WC()->session->get( 'payphone_cart_payment_data' );

			if ( ! $cart_data || ! isset( $cart_data['clientTransactionId'] ) ||
				! hash_equals( $cart_data['clientTransactionId'], $pp_client_id ) ) {
				wc_add_notice(
					__( 'Datos de transacción inválidos. Por favor intenta de nuevo.', 'payphone-wc-modal' ),
					'error'
				);
				return array( 'result' => 'failure' );
			}

			// Confirm with Payphone API.
			$token    = $this->get_option( 'token' );
			$response = wp_remote_post(
				'https://pay.payphone.app/api/button/V2/confirm',
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'id'                  => (int) $pp_txn_id,
							'clientTransactionId' => $pp_client_id,
						)
					),
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				$order->update_status( 'failed', $response->get_error_message() );
				wc_add_notice(
					__( 'Error al confirmar el pago con Payphone. Por favor intenta de nuevo.', 'payphone-wc-modal' ),
					'error'
				);
				return array( 'result' => 'failure' );
			}

			$body   = json_decode( wp_remote_retrieve_body( $response ), true );
			$status = isset( $body['transactionStatus'] ) ? $body['transactionStatus'] : '';

			if ( 'Approved' === $status ) {
				$order->payment_complete( $pp_txn_id );
				$order->add_order_note(
					sprintf(
						/* translators: %s: Payphone transaction ID */
						__( 'Pago aprobado por Payphone (cajita inline). ID de transacción: %s', 'payphone-wc-modal' ),
						esc_html( $pp_txn_id )
					)
				);

				WC()->session->set( 'payphone_cart_payment_data', null );
				WC()->cart->empty_cart();

				return array(
					'result'   => 'success',
					'redirect' => $order->get_checkout_order_received_url(),
				);
			}

			$order->update_status( 'failed', __( 'Pago no aprobado por Payphone.', 'payphone-wc-modal' ) );
			wc_add_notice(
				isset( $body['message'] )
					? sanitize_text_field( $body['message'] )
					: __( 'El pago no fue aprobado. Por favor intenta de nuevo.', 'payphone-wc-modal' ),
				'error'
			);
			return array( 'result' => 'failure' );
		}

		// ---- Fallback flow: store data in session, JS opens box after submit -

		// ---- Amount calculation (Payphone expects integers in cents) --------
		$total_cents  = (int) round( $order->get_total() * 100 );
		$tax_cents    = (int) round( $order->get_total_tax() * 100 );
		$pretax_cents = max( 0, $total_cents - $tax_cents );

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
			'responseUrl'         => WC()->api_request_url( 'payphone_cajita' ),
		);

		// Persist in the WC session so the AJAX handler can read it back.
		WC()->session->set( 'payphone_order_id', $order_id );
		WC()->session->set( 'payphone_payment_data', $payment_data );

		$order->update_status( 'pending', __( 'Esperando confirmación de pago de Payphone.', 'payphone-wc-modal' ) );

		return array(
			'result'   => 'success',
			'redirect' => '#payphone-modal-open',
		);
	}

	// -----------------------------------------------------------------------
	// WC API response URL handler
	// -----------------------------------------------------------------------

	/**
	 * Handle Payphone's browser redirect to the WC API response URL.
	 *
	 * Payphone appends `id` (transaction ID) and `clientTransactionId` as GET
	 * parameters when redirecting to:
	 *   {home}/wc-api/payphone_cajita/?id=XXX&clientTransactionId=WC-{order}-{time}
	 *
	 * This method confirms the transaction with Payphone's API, marks the
	 * WooCommerce order as paid, and redirects the customer to the
	 * order-received page. It is idempotent: if the JS `functionResult`
	 * callback already confirmed the payment, it skips the API call.
	 */
	public function handle_response_url() {
		// Accept both 'id' and 'transactionId' parameter names.
		$transaction_id = isset( $_REQUEST['id'] )
			? sanitize_text_field( wp_unslash( $_REQUEST['id'] ) )
			: '';
		if ( ! $transaction_id && isset( $_REQUEST['transactionId'] ) ) {
			$transaction_id = sanitize_text_field( wp_unslash( $_REQUEST['transactionId'] ) );
		}

		$client_transaction_id = isset( $_REQUEST['clientTransactionId'] )
			? sanitize_text_field( wp_unslash( $_REQUEST['clientTransactionId'] ) )
			: '';

		if ( ! is_numeric( $transaction_id ) || ! $client_transaction_id ) {
			wp_die( esc_html__( 'Parámetros de transacción inválidos.', 'payphone-wc-modal' ) );
		}

		// Extract order ID from our clientTransactionId format: WC-{id}-{timestamp}.
		$order_id = 0;
		if ( preg_match( '/^WC-(\d+)-\d+$/', $client_transaction_id, $matches ) ) {
			$order_id = (int) $matches[1];
		}

		if ( ! $order_id ) {
			wp_die( esc_html__( 'No se pudo determinar la orden de compra.', 'payphone-wc-modal' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Orden no encontrada.', 'payphone-wc-modal' ) );
		}

		// If already paid (confirmed earlier by the JS callback), just redirect.
		if ( $order->is_paid() ) {
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		// Call Payphone Confirm API.
		$token    = $this->get_option( 'token' );
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
			$order->update_status( 'failed', $response->get_error_message() );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
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

			if ( WC()->session ) {
				WC()->session->set( 'payphone_order_id', null );
				WC()->session->set( 'payphone_payment_data', null );
			}

			wp_safe_redirect( $order->get_checkout_order_received_url() );
		} else {
			$order->update_status(
				'failed',
				__( 'Pago rechazado o no aprobado por Payphone.', 'payphone-wc-modal' )
			);
			wp_safe_redirect( wc_get_checkout_url() );
		}
		exit;
	}
}
