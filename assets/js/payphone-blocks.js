/**
 * Payphone WC Modal – WooCommerce Blocks checkout integration.
 *
 * Registers the Payphone payment method in the block-based checkout
 * (WooCommerce 8.x+ with the Checkout block as the default).
 *
 * Flow:
 *  1. When Payphone is selected the content component mounts.
 *  2. loadSDK() and the AJAX payment-data fetch run in parallel.
 *     – loadSDK() is self-contained: if window.PPaymentButtonBox is already
 *       set (by the wp_head bridge), it resolves immediately; otherwise it
 *       injects its own <script type="module"> bridge and waits for the
 *       'payphone:ready' event.
 *     – The AJAX call returns the token, amounts, and clientTransactionId
 *       that process_payment() will later verify against the WC session.
 *  3. Once both are ready, PPaymentButtonBox is rendered inline into
 *     #pp-button-block — no modal, no redirect, no new window.
 *  4. functionResult fires with transactionStatus = 'Approved' and the
 *     transaction IDs are stored in a ref.
 *  5. When the customer clicks "Place Order", onPaymentSetup fires.
 *     – If payment not yet approved: return an error response.
 *     – If approved: return paymentMethodData with the transaction IDs.
 *  6. WC Blocks POSTs those IDs to process_payment() which verifies them
 *     against the session and calls the Payphone Confirm API.
 *
 * Compatible with WooCommerce Blocks 8.6+ which renamed onPaymentProcessing
 * to onPaymentSetup. A fallback to onPaymentProcessing is kept for older
 * sites that haven't updated yet.
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
var AJAX_URL   = settings.ajaxUrl || '';
var NONCE      = settings.nonce   || '';
var SDK_URL    = 'https://cdn.payphonetodoesposible.com/box/v1.1/payphone-payment-box.js';
var SDK_CSS    = 'https://cdn.payphonetodoesposible.com/box/v1.1/payphone-payment-box.css';
var ERROR_TEXT = decodeEntities( settings.errorText || 'Completa el pago con Payphone antes de continuar.' );

/* -----------------------------------------------------------------------
 * Self-contained SDK loader
 *
 * Returns a Promise that resolves with the PPaymentButtonBox constructor.
 *
 * Strategy:
 *  a) If window.PPaymentButtonBox is already set (by the wp_head bridge
 *     injected by payment_scripts()), resolve immediately.
 *  b) Otherwise, inject a <script type="module"> bridge ourselves and wait
 *     for the 'payphone:ready' event. A module-type guard flag prevents
 *     double-injection if this function is called multiple times.
 *  c) A 15-second safety timeout ensures we never block indefinitely.
 *
 * The Payphone box CSS is also guaranteed to be present on the page.
 * --------------------------------------------------------------------- */

var sdkLoadPromise = null;

function loadSDK() {
	if ( sdkLoadPromise ) {
		return sdkLoadPromise;
	}

	// Ensure the Payphone box CSS is loaded (the wp_enqueue_scripts hook
	// in payment_scripts() covers classic + block checkout, but this acts
	// as a safety net in case the enqueue didn't run).
	if ( ! document.querySelector( 'link[href*="payphone-payment-box.css"]' ) ) {
		var link  = document.createElement( 'link' );
		link.rel  = 'stylesheet';
		link.href = SDK_CSS;
		document.head.appendChild( link );
	}

	// Fast path: SDK already loaded by the wp_head bridge.
	if ( typeof window.PPaymentButtonBox !== 'undefined' ) {
		sdkLoadPromise = Promise.resolve( window.PPaymentButtonBox );
		return sdkLoadPromise;
	}

	sdkLoadPromise = new Promise( function ( resolve ) {
		var done  = false;
		var timer = setTimeout( function () {
			if ( ! done ) {
				done = true;
				resolve( null );
			}
		}, 15000 );

		window.addEventListener( 'payphone:ready', function () {
			if ( ! done ) {
				done = true;
				clearTimeout( timer );
				resolve( window.PPaymentButtonBox || null );
			}
		}, { once: true } );

		// Inject bridge only once (the wp_head bridge sets the same flag).
		if ( ! window.__payphoneBridgeInjected ) {
			window.__payphoneBridgeInjected = true;
			var script       = document.createElement( 'script' );
			script.type      = 'module';
			script.textContent = [
				'( async () => {',
				'  try {',
				'    const m = await import( "' + SDK_URL + '" );',
				'    window.PPaymentButtonBox = m.PPaymentButtonBox || m.default || m;',
				'  } catch ( e ) {',
				'    window.PPaymentButtonBox = null;',
				'  }',
				'  window.dispatchEvent( new Event( "payphone:ready" ) );',
				'} )();',
			].join( '\n' );
			document.head.appendChild( script );
		}
	} );

	return sdkLoadPromise;
}

/* -----------------------------------------------------------------------
 * Label shown next to the radio button
 * --------------------------------------------------------------------- */

function PayphoneLabel() {
	return createElement( 'span', null, title );
}

/* -----------------------------------------------------------------------
 * Content rendered inline when Payphone is selected in the block checkout
 * --------------------------------------------------------------------- */

function PayphoneContent( props ) {
	var eventRegistration = props.eventRegistration || {};
	var emitResponse      = props.emitResponse      || {};

	// WC Blocks 8.6+ renamed onPaymentProcessing → onPaymentSetup.
	// Keep the fallback for older WC installs.
	var onPaymentSetup = eventRegistration.onPaymentSetup || eventRegistration.onPaymentProcessing;

	// Holds the approved transaction data returned by functionResult.
	var txnRef = useRef( null );

	// Derive a cart-total key to re-initialize the box when amounts change
	// (coupon applied, shipping method changed, etc.).
	// In WC Blocks 8.x+, cartTotals is a root-level prop; in older versions
	// it lives inside props.billing.
	var cartTotals = props.cartTotals || ( props.billing && props.billing.cartTotals ) || {};
	var totalKey   = String( cartTotals.total_price || '0' ) + '-' + String( cartTotals.total_tax || '0' );

	/* -------------------------------------------------------------------
	 * Load SDK + fetch payment data in parallel, then render the box.
	 * Reruns whenever cart totals change.
	 * ----------------------------------------------------------------- */
	useEffect( function () {
		if ( ! AJAX_URL || ! NONCE ) {
			return;
		}

		var aborted     = false;
		var containerId = 'pp-button-block';

		txnRef.current = null;

		var formData = new FormData();
		formData.append( 'action', 'payphone_get_cart_payment_data' );
		formData.append( 'nonce',  NONCE );

		// Start SDK loading and AJAX fetch concurrently.
		Promise.all( [
			loadSDK(),
			fetch( AJAX_URL, { method: 'POST', body: formData, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } ),
		] )
		.then( function ( results ) {
			if ( aborted ) {
				return;
			}

			var Box      = results[ 0 ];
			var response = results[ 1 ];

			if ( typeof Box !== 'function' ) {
				return;
			}
			if ( ! response.success || ! response.data ) {
				return;
			}

			var data = response.data;
			var el   = document.getElementById( containerId );
			if ( ! el ) {
				return;
			}

			el.innerHTML = '';

			/* eslint-disable no-new */
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
					 * Called by the Payphone box when the transaction finishes.
					 *
					 * @param {Object} result
					 */
					functionResult: function ( result ) {
						if ( result && result.transactionStatus === 'Approved' ) {
							txnRef.current = {
								transactionId:       String( result.transactionId ),
								clientTransactionId: result.clientTransactionId || data.clientTransactionId,
							};
						}
					},
				} ).render( containerId );
			} catch ( e ) {
				// SDK render error — container stays empty, payment option
				// remains visible without a broken box.
			}
			/* eslint-enable no-new */
		} )
		.catch( function () {
			// Network or parse failure — silent. The error will surface
			// when the customer attempts to place the order.
		} );

		return function () {
			aborted = true;
			// Clear the box on unmount / re-render so a fresh instance
			// is created on the next run.
			var el = document.getElementById( 'pp-button-block' );
			if ( el ) {
				el.innerHTML = '';
			}
		};
	}, [ totalKey ] );

	/* -------------------------------------------------------------------
	 * Place-Order gate: called by WC Blocks when the form is submitted.
	 * ----------------------------------------------------------------- */
	useEffect( function () {
		if ( typeof onPaymentSetup !== 'function' ) {
			return;
		}

		var unsubscribe = onPaymentSetup( function () {
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
	}, [ onPaymentSetup ] );

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
