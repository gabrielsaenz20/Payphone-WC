/**
 * Payphone WC – Checkout integration script
 *
 * Inline flow:
 *  1. Customer selects Payphone from the WC payment method list.
 *  2. JS reads payment data that PHP embedded in #pp-button's data-payphone
 *     attribute (no AJAX needed).
 *  3. The Payphone CDN SDK is loaded on-demand via dynamic import().
 *  4. PPaymentButtonBox is rendered inside #pp-button.
 *  5. Customer pays using the Cajita de Pagos.
 *  6. functionResult fires with transactionStatus = 'Approved'.
 *  7. JS fills the two hidden inputs and triggers the WC "Place Order" button.
 *  8. WC creates the order and calls process_payment().
 *  9. process_payment() confirms with Payphone API, marks the order as paid.
 *
 * Dynamic import() is used instead of a static top-level import so this file
 * can be loaded as a plain <script> (no type="module" required).  Dynamic
 * import() works in any script context in all modern browsers.
 */

/* global jQuery, payphoneParams */

( function ( $ ) {
'use strict';

var SDK_URL = 'https://cdn.payphonetodoesposible.com/box/v1.1/payphone-payment-box.js';

/* ------------------------------------------------------------------
 * State
 * ------------------------------------------------------------------ */
var ppRendered = false;

/* ------------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------------ */

function isPayphoneSelected() {
return $( 'input[name="payment_method"]:checked' ).val() === payphoneParams.gatewayId;
}

function showLoading() {
$( '#pp-button' ).html(
'<div class="payphone-loading">' + payphoneParams.processingText + '</div>'
);
}

function showBoxError( message ) {
ppRendered = false;
$( '#pp-button' ).html(
'<p class="payphone-box-error">' + $( '<span>' ).text( message ).html() + '</p>'
);
}

function clearBox() {
ppRendered = false;
$( '#pp-button' ).empty();
$( '#payphone_transaction_id' ).val( '' );
$( '#payphone_client_transaction_id' ).val( '' );
}

/* ------------------------------------------------------------------
 * Render PPaymentButtonBox
 * ------------------------------------------------------------------ */

/**
 * Read payment data from #pp-button's data-payphone attribute and load
 * the Payphone SDK via dynamic import(), then render the box.
 */
function maybeRenderBox() {
if ( ! isPayphoneSelected() || ppRendered ) {
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

ppRendered = true;
showLoading();

// Dynamic import() works in any script context (module or classic).
import( SDK_URL )
.then( function ( module ) {
var Box = module.PPaymentButtonBox || module['default'];
if ( typeof Box !== 'function' ) {
ppRendered = false;
showBoxError( payphoneParams.errorText );
return;
}
renderBox( Box, data );
} )
.catch( function () {
ppRendered = false;
showBoxError( payphoneParams.errorText );
} );
}

/**
 * Instantiate PPaymentButtonBox and render it into #pp-button.
 *
 * @param {Function} Box  PPaymentButtonBox constructor.
 * @param {Object}   data Payment parameters.
 */
function renderBox( Box, data ) {
var container = document.getElementById( 'pp-button' );
if ( ! container ) {
ppRendered = false;
return;
}

$( '#pp-button' ).empty();

/* eslint-disable no-new */
try {
new Box( {
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
 */
functionResult: function ( result ) {
handleResult( result, data.clientTransactionId );
},
} ).render( 'pp-button' );
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
 * @param {Object} result              Payphone result object.
 * @param {string} fallbackClientTxnId Our clientTransactionId fallback.
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
 * Fallback: hash-change flow
 * ------------------------------------------------------------------ */

$( document.body ).on( 'checkout_place_order_success', function ( event, response ) {
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

}( jQuery ) );
