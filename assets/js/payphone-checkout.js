/**
 * Payphone WC – Checkout integration script
 *
 * New inline flow:
 *  1. Customer selects Payphone from the WC payment method list.
 *  2. JS immediately fetches cart-based payment data (AJAX – no WC order yet).
 *  3. PPaymentButtonBox is rendered inside #pp-button (within payment_fields).
 *  4. Customer pays using the Cajita de Pagos.
 *  5. functionResult fires with transactionStatus = 'Approved'.
 *  6. JS fills the two hidden inputs and triggers the WC "Place Order" button.
 *  7. WC validates the form, creates the order, and calls process_payment().
 *  8. process_payment() finds the pre-approved transaction in POST + session,
 *     confirms with Payphone API, marks the order as paid, and returns the
 *     order-received URL.
 *
 * Fallback (hash-change) flow is preserved for block checkout / JS failures.
 */

/* global jQuery, payphoneParams, PPaymentButtonBox */

(function ($) {
	'use strict';

	/* ------------------------------------------------------------------
	 * State
	 * ------------------------------------------------------------------ */
	var ppRendered = false;

	/* ------------------------------------------------------------------
	 * Inline box helpers
	 * ------------------------------------------------------------------ */

	function isPayphoneSelected() {
		return $('input[name="payment_method"]:checked').val() === payphoneParams.gatewayId;
	}

	function showLoading() {
		$('#pp-button').html(
			'<div class="payphone-loading">' + payphoneParams.processingText + '</div>'
		);
	}

	function showBoxError( message ) {
		ppRendered = false;
		$('#pp-button').html(
			'<p class="payphone-box-error">' + $('<span>').text(message).html() + '</p>'
		);
	}

	function clearBox() {
		ppRendered = false;
		$('#pp-button').empty();
		$('#payphone_transaction_id').val('');
		$('#payphone_client_transaction_id').val('');
	}

	/* ------------------------------------------------------------------
	 * Render PPaymentButtonBox from cart data
	 * ------------------------------------------------------------------ */

	/**
	 * Fetch cart payment data and render the Payphone box.
	 * Called whenever Payphone is selected and the box is not yet rendered.
	 */
	function maybeRenderBox() {
		if ( ! isPayphoneSelected() || ppRendered ) {
			return;
		}

		showLoading();

		$.ajax({
			url:  payphoneParams.ajaxUrl,
			type: 'POST',
			data: {
				action: 'payphone_get_cart_payment_data',
				nonce:  payphoneParams.nonce,
			},
			success: function (response) {
				if ( response.success && response.data ) {
					waitForConstructor(response.data);
				} else {
					showBoxError(
						( response.data && response.data.message )
							? response.data.message
							: payphoneParams.errorText
					);
				}
			},
			error: function () {
				showBoxError(payphoneParams.errorText);
			},
		});
	}

	/**
	 * PPaymentButtonBox is an ES module that may finish loading slightly after
	 * our script. Poll until the constructor is available (max 5 s).
	 *
	 * @param {Object} data Payment parameters returned by the AJAX handler.
	 */
	function waitForConstructor( data ) {
		var attempts = 0;

		var interval = setInterval(function () {
			attempts++;

			if ( typeof PPaymentButtonBox !== 'undefined' ) {
				clearInterval(interval);
				renderBox(data);
			} else if ( attempts >= 50 ) {
				clearInterval(interval);
				showBoxError(payphoneParams.errorText);
			}
		}, 100);
	}

	/**
	 * Instantiate PPaymentButtonBox and render it into #pp-button.
	 *
	 * @param {Object} data Payment parameters.
	 */
	function renderBox( data ) {
		if ( ppRendered ) {
			return;
		}
		ppRendered = true;

		$('#pp-button').empty();

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
			 * Called by the Payphone box when the transaction finishes.
			 *
			 * @param {Object} result
			 * @param {string} result.transactionStatus  e.g. 'Approved'
			 * @param {number} result.transactionId      Payphone transaction ID
			 * @param {string} result.clientTransactionId Our client ID
			 */
			functionResult: function (result) {
				handleResult(result, data.clientTransactionId);
			},
		}).render('pp-button');
		/* eslint-enable no-new */
	}

	/* ------------------------------------------------------------------
	 * Payment result handling
	 * ------------------------------------------------------------------ */

	/**
	 * Handle the result returned by PPaymentButtonBox.
	 *
	 * On Approved: store transaction IDs in hidden inputs, submit the WC
	 * checkout form. process_payment() will confirm the transaction and
	 * mark the order as paid.
	 *
	 * On failure/cancel: show an error and allow the customer to retry.
	 *
	 * @param {Object} result              Payphone result.
	 * @param {string} fallbackClientTxnId Fallback clientTransactionId.
	 */
	function handleResult( result, fallbackClientTxnId ) {
		if ( ! result || result.transactionStatus !== 'Approved' ) {
			var msg = ( result && result.transactionStatus )
				? payphoneParams.errorText + ' (' + result.transactionStatus + ')'
				: payphoneParams.errorText;

			// Allow the customer to retry by re-rendering the box.
			ppRendered = false;
			showBoxError(msg);
			return;
		}

		// Payment approved — pass transaction IDs to process_payment() via
		// the hidden form fields and submit the WC checkout form.
		$('#payphone_transaction_id').val(result.transactionId);
		$('#payphone_client_transaction_id').val(
			result.clientTransactionId || fallbackClientTxnId
		);

		// Show processing message while WC creates the order.
		showLoading();

		// Unblock the form (it may be blocked if WC already tried to submit).
		$('form.checkout, form.woocommerce-checkout').first().unblock();

		// Trigger Place Order — WC validates the form, creates the order, then
		// calls process_payment() with our hidden-field transaction data.
		$('#place_order').trigger('click');
	}

	/* ------------------------------------------------------------------
	 * Payment method selection listener
	 * ------------------------------------------------------------------ */

	/**
	 * Render the box immediately when Payphone is selected.
	 * Clear the box when any other method is selected.
	 */
	$(document.body).on('payment_method_selected', function () {
		if ( isPayphoneSelected() ) {
			maybeRenderBox();
		} else {
			clearBox();
		}
	});

	/**
	 * After each checkout update (coupon, shipping, etc.) the payment_fields
	 * HTML is re-rendered by WC, so we must re-render the box.
	 */
	$(document.body).on('updated_checkout', function () {
		ppRendered = false; // DOM was refreshed; allow re-render.
		if ( isPayphoneSelected() ) {
			maybeRenderBox();
		}
	});

	/* ------------------------------------------------------------------
	 * Fallback: hash-change flow (block checkout / JS errors)
	 * ------------------------------------------------------------------ */

	/**
	 * Classic checkout: intercept the hash-redirect returned by process_payment()
	 * (fallback path only – the inline flow bypasses this entirely).
	 */
	$(document.body).on('checkout_place_order_success', function (event, response) {
		if (
			response &&
			response.redirect &&
			response.redirect.indexOf('#payphone-modal-open') !== -1
		) {
			$('form.checkout').unblock();
			$('form.woocommerce-checkout').unblock();

			// Fetch order-based payment data and re-render the box inline.
			ppRendered = false;
			showLoading();

			$.ajax({
				url:  payphoneParams.ajaxUrl,
				type: 'POST',
				data: { action: 'payphone_get_payment_data', nonce: payphoneParams.nonce },
				success: function (r) {
					if ( r.success && r.data ) {
						waitForConstructor(r.data);
					} else {
						showBoxError(
							( r.data && r.data.message ) ? r.data.message : payphoneParams.errorText
						);
					}
				},
				error: function () { showBoxError(payphoneParams.errorText); },
			});
		}
	});

	window.addEventListener('hashchange', function () {
		if ( window.location.hash === '#payphone-modal-open' && ! ppRendered ) {
			ppRendered = false;
			showLoading();

			$.ajax({
				url:  payphoneParams.ajaxUrl,
				type: 'POST',
				data: { action: 'payphone_get_payment_data', nonce: payphoneParams.nonce },
				success: function (r) {
					if ( r.success && r.data ) { waitForConstructor(r.data); }
					else { showBoxError(payphoneParams.errorText); }
				},
				error: function () { showBoxError(payphoneParams.errorText); },
			});
		}
	});

	/* ------------------------------------------------------------------
	 * Initialisation
	 * ------------------------------------------------------------------ */

	$(function () {
		// Render the box on page load if Payphone is already pre-selected.
		if ( isPayphoneSelected() ) {
			maybeRenderBox();
		}
	});

}(jQuery));
