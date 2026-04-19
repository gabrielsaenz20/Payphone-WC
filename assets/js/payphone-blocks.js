/**
 * Payphone WC Modal – WooCommerce Blocks checkout integration.
 *
 * Registers the Payphone payment method in the block-based checkout.
 *
 * Flow:
 *  1. When Payphone is selected the content component mounts and fetches
 *     fresh payment data (token, amounts, clientTransactionId) from the
 *     server via the existing `payphone_get_cart_payment_data` AJAX action.
 *     The action also stores the clientTransactionId in the WC session so
 *     process_payment() can verify it later.
 *  2. The Payphone CDN SDK is loaded via waitForSDK(), which waits for the
 *     <script type="module"> bridge in wp_head to expose window.PPaymentButtonBox.
 *  3. The customer completes payment inside the Payphone box.
 *     functionResult fires and we capture the transactionId and
 *     clientTransactionId in a ref.
 *  4. When the customer clicks "Place Order", onPaymentProcessing fires.
 *     – If payment not yet approved: block checkout and show an error.
 *     – If approved: return paymentMethodData with the transaction IDs.
 *  5. WC Blocks sends those IDs to the server, where process_payment()
 *     finds them in $_POST, verifies against the session and calls the
 *     Payphone Confirm API to mark the order as paid.
 *
 * If the cart totals change (shipping, coupon, etc.) the effect re-runs,
 * re-fetches fresh amounts from the server, and re-renders the box.
 */

( function () {
'use strict';

var registerPaymentMethod = window.wc.wcBlocksRegistry.registerPaymentMethod;
var getSetting            = window.wc.wcSettings.getSetting;
var createElement         = window.wp.element.createElement;
var useEffect             = window.wp.element.useEffect;
var useRef                = window.wp.element.useRef;
var decodeEntities        = window.wp.htmlEntities.decodeEntities;

var settings   = getSetting( 'payphone_modal_data', {} );
var title      = decodeEntities( settings.title || 'Payphone' );
var AJAX_URL   = settings.ajaxUrl  || '';
var NONCE      = settings.nonce    || '';
var ERROR_TEXT = decodeEntities( settings.errorText || 'Completa el pago con Payphone antes de continuar.' );

/* -----------------------------------------------------------------------
 * SDK readiness helper (mirrors payphone-checkout.js)
 *
 * Resolves with window.PPaymentButtonBox once the <script type="module">
 * bridge output by print_sdk_module_bridge() in wp_head has loaded the SDK.
 * Resolves immediately if the bridge already ran. Times out after 15 s.
 * --------------------------------------------------------------------- */

function waitForSDK() {
	if ( typeof window.PPaymentButtonBox !== 'undefined' ) {
		return Promise.resolve( window.PPaymentButtonBox );
	}
	return new Promise( function ( resolve ) {
		var timer = setTimeout( function () { resolve( null ); }, 15000 );
		window.addEventListener( 'payphone:ready', function () {
			clearTimeout( timer );
			resolve( window.PPaymentButtonBox || null );
		}, { once: true } );
	} );
}

/* -----------------------------------------------------------------------
 * Label shown next to the radio button
 * --------------------------------------------------------------------- */

function PayphoneLabel() {
return createElement( 'span', null, title );
}

/* -----------------------------------------------------------------------
 * Content shown when Payphone is selected in the block checkout
 * --------------------------------------------------------------------- */

function PayphoneContent( props ) {
var eventRegistration   = props.eventRegistration   || {};
var emitResponse        = props.emitResponse        || {};
var onPaymentProcessing = eventRegistration.onPaymentProcessing;

// Holds the approved transaction data from PPaymentButtonBox.functionResult.
var txnRef = useRef( null );

// Derive a cart-total key so we can detect when totals change and
// re-initialize the box with up-to-date amounts.
var billing    = props.billing || {};
var cartTotals = billing.cartTotals || {};
var totalKey   = String( cartTotals.total_price || '0' ) + '-' + String( cartTotals.total_tax || '0' );

/* -------------------------------------------------------------------
 * Fetch payment data from the server and render PPaymentButtonBox.
 * Reruns whenever cart totals change.
 * ----------------------------------------------------------------- */
useEffect( function () {
if ( ! AJAX_URL || ! NONCE ) {
return;
}

var containerId = 'pp-button-block';
var container   = document.getElementById( containerId );
if ( ! container ) {
return;
}

// Reset prior state.
txnRef.current    = null;
container.innerHTML = '';

var aborted  = false;
var formData = new FormData();
formData.append( 'action', 'payphone_get_cart_payment_data' );
formData.append( 'nonce',  NONCE );

fetch( AJAX_URL, { method: 'POST', body: formData, credentials: 'same-origin' } )
.then( function ( r ) { return r.json(); } )
.then( function ( response ) {
if ( aborted || ! response.success || ! response.data ) {
return;
}

var data = response.data;

return waitForSDK().then( function ( Box ) {
if ( aborted ) {
return;
}

if ( typeof Box !== 'function' ) {
return;
}

var el = document.getElementById( containerId );
if ( ! el ) {
return;
}

el.innerHTML = '';

try {
new Box( {
token:               data.token,
amount:              data.amount,
amountWithoutTax:    data.amountWithoutTax,
amountWithTax:       data.amountWithTax,
tax:                 data.tax,
service:             data.service  || 0,
tip:                 data.tip      || 0,
storeId:             data.storeId,
reference:           data.reference,
currency:            data.currency,
clientTransactionId: data.clientTransactionId,
backgroundColor:     data.backgroundColor,
responseUrl:         data.responseUrl,

/**
 * Called by the Payphone box when the
 * transaction finishes.
 *
 * @param {Object} result
 */
functionResult: function ( result ) {
if ( result && result.transactionStatus === 'Approved' ) {
txnRef.current = {
transactionId:       String( result.transactionId ),
clientTransactionId: result.clientTransactionId
|| data.clientTransactionId,
};
}
},
} ).render( containerId );
} catch ( e ) {
// SDK render error – leave the container empty so
// the user at least sees the payment option without
// a broken box.
}
} );
} )
.catch( function () {
// Network or parse failure – silent. The user will see the
// error when they try to place the order.
} );

// Mark this render cycle stale on re-run or unmount.
return function () {
aborted = true;
};
}, [ totalKey ] );

/* -------------------------------------------------------------------
 * Register the Place-Order gate.
 * ----------------------------------------------------------------- */
useEffect( function () {
if ( typeof onPaymentProcessing !== 'function' ) {
return;
}

var unsubscribe = onPaymentProcessing( function () {
var txn = txnRef.current;

if ( ! txn || ! txn.transactionId ) {
return {
type:    emitResponse.responseTypes
? emitResponse.responseTypes.ERROR
: 'error',
message: ERROR_TEXT,
};
}

return {
type: emitResponse.responseTypes
? emitResponse.responseTypes.SUCCESS
: 'success',
meta: {
paymentMethodData: {
payphone_transaction_id:        txn.transactionId,
payphone_client_transaction_id: txn.clientTransactionId,
},
},
};
} );

return typeof unsubscribe === 'function' ? unsubscribe : undefined;
}, [ onPaymentProcessing ] );

/* -------------------------------------------------------------------
 * Render
 * ----------------------------------------------------------------- */
var description = settings.description
? createElement(
'p',
{ className: 'payphone-description' },
decodeEntities( settings.description )
)
: null;

return createElement(
'div',
null,
description,
createElement( 'div', { id: 'pp-button-block' } )
);
}

/* -----------------------------------------------------------------------
 * Edit / preview component shown in the WordPress block editor.
 * (No live SDK or AJAX here — just a static label.)
 * --------------------------------------------------------------------- */
function PayphoneEdit() {
return createElement(
'div',
{ className: 'payphone-description' },
decodeEntities( settings.description || title )
);
}

/* -----------------------------------------------------------------------
 * Registration
 * --------------------------------------------------------------------- */
registerPaymentMethod( {
name:           'payphone_modal',
label:          createElement( PayphoneLabel,   null ),
content:        createElement( PayphoneContent, null ),
edit:           createElement( PayphoneEdit,    null ),
canMakePayment: function () { return true; },
ariaLabel:      title,
supports: {
features: settings.supports || [ 'products' ],
},
} );
}() );
