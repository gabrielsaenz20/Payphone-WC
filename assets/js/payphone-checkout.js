/**
 * Payphone WC Modal – Checkout integration script
 *
 * Flow:
 *  1. Customer fills the WC checkout form and selects Payphone.
 *  2. WC submits the form via AJAX → server creates the order and returns
 *     { result: 'success', redirect: '#payphone-modal-open' }.
 *  3. WC JS sets window.location = '#payphone-modal-open' (hash change only –
 *     the page stays loaded).
 *  4. We detect the hash change via the `checkout_place_order_success` event
 *     and fetch the payment data from the WC session (AJAX).
 *  5. We open the modal and render the Payphone Cajita de Pagos.
 *  6. PPaymentButtonBox fires `functionResult` when payment completes.
 *  7. We confirm server-side (Payphone Confirm API) via AJAX.
 *  8. On success → redirect to the WC order-received page.
 *     On failure → show error, close modal so the customer can retry.
 */

/* global jQuery, payphoneParams, PPaymentButtonBox */

(function ($) {
	'use strict';

	/* ------------------------------------------------------------------
	 * State
	 * ------------------------------------------------------------------ */
	var isModalOpen    = false;
	var ppBoxRendered  = false;

	/* ------------------------------------------------------------------
	 * Modal DOM helpers
	 * ------------------------------------------------------------------ */

	/** Inject the modal HTML once into the page body. */
	function createModalMarkup() {
		if ( $('#payphone-modal').length ) {
			return;
		}

		var html =
			'<div id="payphone-modal-overlay"></div>' +
			'<div id="payphone-modal" role="dialog" aria-modal="true" aria-label="Payphone">' +
				'<button id="payphone-modal-close" type="button">' +
					payphoneParams.cancelText +
				'</button>' +
				'<div id="pp-button"></div>' +
			'</div>';

		$('body').append(html);
	}

	/** Show the modal and overlay. */
	function openModal() {
		isModalOpen   = true;
		ppBoxRendered = false;
		$('#payphone-modal-overlay').fadeIn(200);
		$('#payphone-modal').fadeIn(200);
		// Show a loading indicator until the box script renders.
		$('#pp-button').html(
			'<div class="payphone-loading">' +
				payphoneParams.processingText +
			'</div>'
		);
	}

	/** Hide the modal and overlay, restore the WC form state. */
	function closeModal() {
		isModalOpen   = false;
		ppBoxRendered = false;
		$('#payphone-modal-overlay').fadeOut(150);
		$('#payphone-modal').fadeOut(150, function () {
			$('#pp-button').empty();
		});

		// Restore the checkout form so the customer can change payment method
		// or retry without refreshing.
		$('form.checkout').unblock();
		$('form.woocommerce-checkout').unblock();
	}

	/** Replace the box area with an error message. */
	function showModalError( message ) {
		$('#pp-button').html(
			'<p class="payphone-modal-error">' + $('<span>').text(message).html() + '</p>'
		);
	}

	/* ------------------------------------------------------------------
	 * Payphone box rendering
	 * ------------------------------------------------------------------ */

	/**
	 * Fetch payment data from the WC session and, once the PPaymentButtonBox
	 * constructor is available, render the box.
	 */
	function fetchDataAndRenderBox() {
		$.ajax({
			url:  payphoneParams.ajaxUrl,
			type: 'POST',
			data: {
				action: 'payphone_get_payment_data',
				nonce:  payphoneParams.nonce,
			},
			success: function (response) {
				if ( response.success && response.data ) {
					waitForBoxConstructor(response.data);
				} else {
					showModalError(
						( response.data && response.data.message )
							? response.data.message
							: payphoneParams.errorText
					);
				}
			},
			error: function () {
				showModalError(payphoneParams.errorText);
			},
		});
	}

	/**
	 * The Payphone box JS is loaded as type="module" which may initialise
	 * slightly after our script. Poll until PPaymentButtonBox is defined.
	 *
	 * @param {Object} data Payment parameters from WC session.
	 */
	function waitForBoxConstructor( data ) {
		var attempts = 0;
		var maxAttempts = 50; // 5 s maximum wait.

		var interval = setInterval(function () {
			attempts++;

			if ( typeof PPaymentButtonBox !== 'undefined' ) {
				clearInterval(interval);
				renderBox(data);
			} else if ( attempts >= maxAttempts ) {
				clearInterval(interval);
				showModalError(payphoneParams.errorText);
			}
		}, 100);
	}

	/**
	 * Instantiate PPaymentButtonBox with the order's payment data.
	 * `functionResult` is Payphone's callback when the transaction completes.
	 *
	 * @param {Object} data Payment parameters.
	 */
	function renderBox( data ) {
		if ( ppBoxRendered ) {
			return;
		}
		ppBoxRendered = true;

		$('#pp-button').empty(); // Remove loading indicator.

		/* eslint-disable no-new */
		new PPaymentButtonBox({
			token:               data.token,
			amount:              data.amount,
			amountWithoutTax:    data.amountWithoutTax,
			amountWithTax:       data.amountWithTax,
			tax:                 data.tax,
			service:             data.service,
			tip:                 data.tip,
			storeId:             data.storeId,
			reference:           data.reference,
			currency:            data.currency,
			clientTransactionId: data.clientTransactionId,
			backgroundColor:     data.backgroundColor,
			responseUrl:         data.responseUrl,

			/**
			 * Called by the Payphone box when the transaction finishes
			 * (approved, cancelled, or error).
			 *
			 * @param {Object} result Payphone result object.
			 * @param {string} result.transactionStatus  e.g. 'Approved'.
			 * @param {number} result.transactionId      Payphone transaction ID.
			 * @param {string} result.clientTransactionId Our ID.
			 */
			functionResult: function (result) {
				handlePayphoneResult(result, data.clientTransactionId);
			},
		}).render('pp-button');
		/* eslint-enable no-new */
	}

	/* ------------------------------------------------------------------
	 * Payment result handling
	 * ------------------------------------------------------------------ */

	/**
	 * Process the result returned by PPaymentButtonBox.
	 *
	 * @param {Object} result              Payphone result object.
	 * @param {string} fallbackClientTxnId Client transaction ID as fallback.
	 */
	function handlePayphoneResult( result, fallbackClientTxnId ) {
		if ( ! result || result.transactionStatus !== 'Approved' ) {
			// Payment was declined or the customer cancelled inside the box.
			cancelPayment();
			closeModal();

			// Surface an error notice on the checkout page.
			var msg = ( result && result.transactionStatus )
				? payphoneParams.errorText + ' (' + result.transactionStatus + ')'
				: payphoneParams.errorText;

			$('.woocommerce-notices-wrapper').first().html(
				'<ul class="woocommerce-error" role="alert"><li>' +
					$('<span>').text(msg).html() +
				'</li></ul>'
			);
			return;
		}

		// Show processing state.
		$('#pp-button').html(
			'<div class="payphone-loading">' +
				payphoneParams.processingText +
			'</div>'
		);

		// Confirm server-side.
		$.ajax({
			url:  payphoneParams.ajaxUrl,
			type: 'POST',
			data: {
				action:            'payphone_confirm_payment',
				nonce:             payphoneParams.nonce,
				transactionId:     result.transactionId,
				clientTransactionId: result.clientTransactionId || fallbackClientTxnId,
			},
			success: function (response) {
				if ( response.success && response.data && response.data.redirect ) {
					window.location.href = response.data.redirect;
				} else {
					showModalError(
						( response.data && response.data.message )
							? response.data.message
							: payphoneParams.errorText
					);
				}
			},
			error: function () {
				showModalError(payphoneParams.errorText);
			},
		});
	}

	/**
	 * Notify the server to cancel / abandon the pending order.
	 */
	function cancelPayment() {
		$.ajax({
			url:  payphoneParams.ajaxUrl,
			type: 'POST',
			data: {
				action: 'payphone_cancel_payment',
				nonce:  payphoneParams.nonce,
			},
		});
	}

	/* ------------------------------------------------------------------
	 * WooCommerce checkout integration
	 * ------------------------------------------------------------------ */

	/**
	 * WooCommerce fires `checkout_place_order_success` on the document body
	 * after a successful AJAX order creation.  We intercept the response here:
	 * if the redirect is our magic hash we open the Payphone modal instead of
	 * letting WC navigate away.
	 */
	$(document.body).on('checkout_place_order_success', function (event, response) {
		if (
			response &&
			response.redirect &&
			response.redirect.indexOf('#payphone-modal-open') !== -1
		) {
			// Unblock the WC form (it gets blocked during AJAX checkout).
			$('form.checkout').unblock();
			$('form.woocommerce-checkout').unblock();

			openModal();
			fetchDataAndRenderBox();
		}
	});

	/* ------------------------------------------------------------------
	 * Modal close handlers
	 * ------------------------------------------------------------------ */

	$(document).on('click', '#payphone-modal-close', function () {
		cancelPayment();
		closeModal();
	});

	$(document).on('click', '#payphone-modal-overlay', function () {
		cancelPayment();
		closeModal();
	});

	// Allow Escape key to close the modal.
	$(document).on('keydown', function (e) {
		if ( isModalOpen && ( e.key === 'Escape' || e.keyCode === 27 ) ) {
			cancelPayment();
			closeModal();
		}
	});

	/* ------------------------------------------------------------------
	 * Initialisation
	 * ------------------------------------------------------------------ */

	$(function () {
		createModalMarkup();
	});

}(jQuery));
