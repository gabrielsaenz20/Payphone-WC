/**
 * Payphone WC – Checkout integration script (ES module)
 *
 * Inline flow:
 *  1. Customer selects Payphone from the WC payment method list.
 *  2. JS reads payment data that PHP embedded in #pp-button's data-payphone
 *     attribute — no AJAX call needed, exactly like test.html.
 *  3. PPaymentButtonBox is rendered inside #pp-button.
 *  4. Customer pays using the Cajita de Pagos.
 *  5. functionResult fires with transactionStatus = 'Approved'.
 *  6. JS fills the two hidden inputs and triggers the WC "Place Order" button.
 *  7. WC validates the form, creates the order, and calls process_payment().
 *  8. process_payment() finds the pre-approved transaction in POST + session,
 *     confirms with Payphone API, marks the order as paid, and returns the
 *     order-received URL.
 *
 * This file is loaded with type="module" (via script_loader_tag filter) so the
 * static CDN import resolves exactly as in the working test.html.
 */

/* global jQuery, payphoneParams */

import * as _PayphoneSDK from 'https://cdn.payphonetodoesposible.com/box/v1.1/payphone-payment-box.js';

// Resolve the constructor regardless of whether the module uses a named export
// (export class PPaymentButtonBox) or a default export (export default class).
var PPaymentButtonBox = _PayphoneSDK.PPaymentButtonBox || _PayphoneSDK['default'];

(function ($) {
'use strict';

/* ------------------------------------------------------------------
 * State
 * ------------------------------------------------------------------ */
var ppRendered = false;

/* ------------------------------------------------------------------
 * Helpers
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
 * Render PPaymentButtonBox from embedded page data
 * ------------------------------------------------------------------ */

/**
 * Read the payment data embedded by PHP in #pp-button's data-payphone
 * attribute and render the Payphone box immediately — no AJAX needed.
 */
function maybeRenderBox() {
if ( ! isPayphoneSelected() || ppRendered ) {
return;
}

if ( typeof PPaymentButtonBox !== 'function' ) {
showBoxError( payphoneParams.errorText );
return;
}

var $btn = $( '#pp-button' );
if ( ! $btn.length ) {
return;
}

// PHP embeds all payment parameters as JSON in the data-payphone attribute.
var data = $btn.data( 'payphone' );
if ( ! data || ! data.token ) {
showBoxError( payphoneParams.errorText );
return;
}

renderBox( data );
}

/**
 * Instantiate PPaymentButtonBox and render it into #pp-button.
 *
 * @param {Object} data Payment parameters (from data-payphone attribute).
 */
function renderBox( data ) {
if ( ppRendered ) {
return;
}

var container = document.getElementById( 'pp-button' );
if ( ! container ) {
return;
}

ppRendered = true;
$( '#pp-button' ).empty();

/* eslint-disable no-new */
try {
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
handleResult( result, data.clientTransactionId );
},
}).render( 'pp-button' );
} catch ( e ) {
ppRendered = false;
showBoxError( payphoneParams.errorText );
}
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

ppRendered = false;
showBoxError( msg );
return;
}

$( '#payphone_transaction_id' ).val( result.transactionId );
$( '#payphone_client_transaction_id' ).val(
result.clientTransactionId || fallbackClientTxnId
);

showLoading();

$( 'form.checkout, form.woocommerce-checkout' ).first().unblock();

// Trigger Place Order — WC creates the order, then calls process_payment().
$( '#place_order' ).trigger( 'click' );
}

/* ------------------------------------------------------------------
 * Payment method selection listeners
 * ------------------------------------------------------------------ */

$( document.body ).on( 'payment_method_selected', function () {
if ( isPayphoneSelected() ) {
maybeRenderBox();
} else {
clearBox();
}
} );

$( document ).on( 'change', 'input[name="payment_method"]', function () {
if ( isPayphoneSelected() ) {
maybeRenderBox();
} else {
clearBox();
}
} );

/**
 * After each checkout update (coupon, shipping, etc.) WC refreshes the
 * payment_fields HTML — which gives us a fresh data-payphone attribute
 * with updated totals. Re-render the box.
 */
$( document.body ).on( 'updated_checkout', function () {
ppRendered = false;
if ( isPayphoneSelected() ) {
maybeRenderBox();
}
} );

/* ------------------------------------------------------------------
 * Fallback: hash-change flow (block checkout / JS errors)
 * ------------------------------------------------------------------ */

$( document.body ).on( 'checkout_place_order_success', function (event, response) {
if (
response &&
response.redirect &&
response.redirect.indexOf( '#payphone-modal-open' ) !== -1
) {
$( 'form.checkout' ).unblock();
$( 'form.woocommerce-checkout' ).unblock();

ppRendered = false;
if ( isPayphoneSelected() ) {
maybeRenderBox();
}
}
} );

window.addEventListener( 'hashchange', function () {
if ( window.location.hash === '#payphone-modal-open' && ! ppRendered ) {
ppRendered = false;
if ( isPayphoneSelected() ) {
maybeRenderBox();
}
}
} );

/* ------------------------------------------------------------------
 * Initialisation
 * ------------------------------------------------------------------ */

$( function () {
if ( isPayphoneSelected() ) {
maybeRenderBox();
}
} );

}( jQuery ));
