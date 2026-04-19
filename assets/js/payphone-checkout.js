/**
 * Payphone WC – Checkout integration script
 *
 * Flow:
 *  1. Customer fills the WC checkout form and selects Payphone.
 *  2. Clicks "Pagar con Payphone" → triggers the standard WC "Place Order"
 *     AJAX submission.
 *  3. Server creates the order and returns
 *     { result: 'success', redirect: '#payphone-modal-open' }.
 *  4. We intercept via `checkout_place_order_success` (classic) or a
 *     hashchange event (block checkout).
 *  5. The checkout form is hidden and the inline Payphone payment section
 *     is revealed on the same page.
 *  6. We fetch payment data from the WC session (AJAX) and render
 *     PPaymentButtonBox into #pp-button.
 *  7. PPaymentButtonBox fires `functionResult` when the transaction finishes.
 *  8. On Approved → confirm server-side → redirect to order-received page.
 *     On failure/cancel → restore the checkout form so the customer can retry.
 */

/* global jQuery, payphoneParams, PPaymentButtonBox */

(function ($) {
	'use strict';

	/* ------------------------------------------------------------------
	 * State
	 * ------------------------------------------------------------------ */
	var isBoxOpen    = false;
	var ppBoxRendered = false;

	/* ------------------------------------------------------------------
	 * Inline payment section helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Inject the inline payment section HTML once, right after the checkout
	 * form. It is hidden by default; showPaymentBox() makes it visible.
	 */
	function createPaymentSection() {
		if ( $('#payphone-payment-section').length ) {
			return;
		}

		var html =
			'<div id="payphone-payment-section" style="display:none;">' +
				'<h2 class="payphone-section-title">' + payphoneParams.sectionTitle + '</h2>' +
				'<div id="pp-button"></div>' +
				'<button id="payphone-cancel-btn" type="button" class="payphone-cancel-btn">' +
					payphoneParams.cancelText +
				'</button>' +
			'</div>';

		// Insert after the checkout form (or append to body as fallback).
		var $form = $('form.checkout, form.woocommerce-checkout').first();
		if ( $form.length ) {
			$form.after(html);
		} else {
			$('body').append(html);
		}
	}

	/**
	 * Hide the checkout form and show the inline Payphone payment section.
	 */
	function showPaymentBox() {
		isBoxOpen    = true;
		ppBoxRendered = false;

		$('form.checkout, form.woocommerce-checkout').first().hide();
		$('#payphone-payment-section').show();

		// Show a loading indicator until the box script renders.
		$('#pp-button').html(
			'<div class="payphone-loading">' +
				payphoneParams.processingText +
			'</div>'
		);

		// Scroll to the payment section.
		$('html, body').animate(
			{ scrollTop: $('#payphone-payment-section').offset().top - 40 },
			300
		);
	}

	/**
	 * Hide the inline payment section and restore the checkout form so the
	 * customer can change payment method or retry.
	 */
	function hidePaymentBox() {
		isBoxOpen    = false;
		ppBoxRendered = false;

		$('#payphone-payment-section').hide();
		$('#pp-button').empty();

		var $form = $('form.checkout, form.woocommerce-checkout').first();
		$form.show();
		$form.unblock();
	}

	/** Replace the #pp-button area with an inline error message. */
	function showBoxError( message ) {
		$('#pp-button').html(
			'<p class="payphone-box-error">' + $('<span>').text(message).html() + '</p>'
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
	 * The Payphone box JS is loaded as type="module" which may initialise
	 * slightly after our script. Poll until PPaymentButtonBox is defined.
	 *
	 * @param {Object} data Payment parameters from WC session.
	 */
	function waitForBoxConstructor( data ) {
		var attempts    = 0;
		var maxAttempts = 50; // 5 s maximum wait.

		var interval = setInterval(function () {
			attempts++;

			if ( typeof PPaymentButtonBox !== 'undefined' ) {
				clearInterval(interval);
				renderBox(data);
			} else if ( attempts >= maxAttempts ) {
				clearInterval(interval);
				showBoxError(payphoneParams.errorText);
			}
		}, 100);
	}

	/**
	 * Instantiate PPaymentButtonBox with the order's payment data and render
	 * it directly into the inline #pp-button container.
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
			hidePaymentBox();

			// Surface an error notice on the restored checkout page.
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

		// Show processing state while we confirm with the server.
		$('#pp-button').html(
			'<div class="payphone-loading">' +
				payphoneParams.processingText +
			'</div>'
		);

		// Confirm server-side via AJAX.
		$.ajax({
			url:  payphoneParams.ajaxUrl,
			type: 'POST',
			data: {
				action:              'payphone_confirm_payment',
				nonce:               payphoneParams.nonce,
				transactionId:       result.transactionId,
				clientTransactionId: result.clientTransactionId || fallbackClientTxnId,
			},
			success: function (response) {
				if ( response.success && response.data && response.data.redirect ) {
					window.location.href = response.data.redirect;
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
	 * "Pagar con Payphone" button (classic checkout)
	 * ------------------------------------------------------------------ */

	/**
	 * When the customer clicks the "Pagar con Payphone" button rendered inside
	 * the payment fields area, programmatically trigger the standard WC
	 * "Place Order" button. This kicks off the AJAX order creation, which then
	 * returns our magic hash and triggers the inline box.
	 */
	$(document).on('click', '#payphone-pay-button', function (e) {
		e.preventDefault();
		$('#place_order').trigger('click');
	});

	/**
	 * Hide the generic "Place Order" button when Payphone is selected so the
	 * customer uses our branded "Pagar con Payphone" button instead.
	 * Restore it when another gateway is picked.
	 */
	function syncPlaceOrderButton() {
		var selected = $('input[name="payment_method"]:checked').val();
		if ( selected === payphoneParams.gatewayId ) {
			$('#place_order').hide();
		} else {
			$('#place_order').show();
		}
	}

	// Run once on load and again whenever the payment method changes.
	$(document.body).on( 'payment_method_selected updated_checkout', syncPlaceOrderButton );
	$(function () {
		syncPlaceOrderButton();
	});

	/* ------------------------------------------------------------------
	 * WooCommerce checkout integration
	 * ------------------------------------------------------------------ */

	/**
	 * WooCommerce fires `checkout_place_order_success` on the document body
	 * after a successful AJAX order creation (classic checkout).  We intercept
	 * the response: if the redirect is our magic hash we show the inline
	 * Payphone box instead of letting WC navigate away.
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

			showPaymentBox();
			fetchDataAndRenderBox();
		}
	});

	/**
	 * Block-based checkout (WC 7.6+) redirects the browser to the URL
	 * returned by process_payment(). Since that URL is a hash-only value
	 * (`#payphone-modal-open`), no full navigation occurs – only a hashchange
	 * event fires. We listen for it here so the inline box appears in both
	 * classic and block checkout.
	 */
	window.addEventListener('hashchange', function () {
		if ( window.location.hash === '#payphone-modal-open' && ! isBoxOpen ) {
			showPaymentBox();
			fetchDataAndRenderBox();
		}
	});

	/* ------------------------------------------------------------------
	 * Cancel button handler
	 * ------------------------------------------------------------------ */

	$(document).on('click', '#payphone-cancel-btn', function () {
		cancelPayment();
		hidePaymentBox();
	});

	/* ------------------------------------------------------------------
	 * Initialisation
	 * ------------------------------------------------------------------ */

	$(function () {
		createPaymentSection();
	});

}(jQuery));
