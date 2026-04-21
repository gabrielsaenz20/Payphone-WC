/**
 * Payphone WC – Checkout integration script
 *
 * Inline flow:
 *  1. Customer selects Payphone from the WC payment method list.
 *  2. JS reads payment data that PHP embedded in #pp-button's data-payphone
 *     attribute (no AJAX needed).
 *  3. waitForSDK() waits for the <script type="module"> bridge in wp_head to
 *     finish loading the Payphone CDN SDK and assign window.PPaymentButtonBox.
 *  4. PPaymentButtonBox is rendered inside #pp-button.
 *  5. Customer pays using the Cajita de Pagos.
 *  6. functionResult fires with transactionStatus = 'Approved'.
 *  7. JS fills the two hidden inputs and triggers the WC "Place Order" button.
 *  8. WC creates the order and calls process_payment().
 *  9. process_payment() confirms with Payphone API, marks the order as paid.
 *
 * The SDK is pre-loaded via a <script type="module"> bridge output by
 * Payphone_WC_Gateway::print_sdk_module_bridge() in wp_head. That bridge
 * assigns PPaymentButtonBox to window and fires 'payphone:ready'. Using a
 * module script is the only reliable way to load an ES module SDK from a CDN
 * inside WordPress — dynamic import() in non-module scripts can be silently
 * blocked by many hosting environments and Content Security Policies.
 */

/* global jQuery, payphoneParams */

( function ( $ ) {
'use strict';

/* ------------------------------------------------------------------
 * State
 * ------------------------------------------------------------------ */
var ppRendered = false;

/* ------------------------------------------------------------------
 * SDK readiness helper
 * ------------------------------------------------------------------ */

/**
 * Resolve with window.PPaymentButtonBox once the module bridge in wp_head
 * has loaded the SDK and dispatched the 'payphone:ready' event.
 * Resolves immediately if the bridge already ran. Times out after 15 s.
 *
 * @return {Promise<Function|null>}
 */
function waitForSDK() {
if ( typeof window.PPaymentButtonBox !== 'undefined' ) {
return Promise.resolve( window.PPaymentButtonBox );
}
return new Promise( function ( resolve ) {
var timer = setTimeout( function () {
resolve( null );
}, 15000 );
window.addEventListener( 'payphone:ready', function () {
clearTimeout( timer );
resolve( window.PPaymentButtonBox || null );
}, { once: true } );
} );
}

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
 * Read payment data from #pp-button's data-payphone attribute and wait
 * for the SDK to be ready, then render the box.
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

// Wait for the module bridge in wp_head to expose window.PPaymentButtonBox.
waitForSDK()
.then( function ( Box ) {
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
