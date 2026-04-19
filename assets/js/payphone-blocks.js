/**
 * Payphone WC Modal – WooCommerce Blocks checkout registration.
 *
 * Registers the Payphone payment method so it appears in the
 * block-based WooCommerce Checkout (WC 7.6+).
 *
 * Data is passed from PHP via AbstractPaymentMethodType::get_payment_method_data()
 * and accessed here through getSetting().
 */
( function () {
	'use strict';

	var registerPaymentMethod = window.wc.wcBlocksRegistry.registerPaymentMethod;
	var getSetting            = window.wc.wcSettings.getSetting;
	var createElement         = window.wp.element.createElement;
	var decodeEntities        = window.wp.htmlEntities.decodeEntities;

	var settings    = getSetting( 'payphone_modal_data', {} );
	var title       = decodeEntities( settings.title       || 'Payphone' );
	var description = decodeEntities( settings.description || '' );

	/**
	 * Label shown next to the payment method radio button.
	 */
	function PayphoneLabel() {
		return createElement( 'span', null, title );
	}

	/**
	 * Content shown below the radio button when the method is selected.
	 */
	function PayphoneContent() {
		return description
			? createElement( 'p', null, description )
			: null;
	}

	registerPaymentMethod( {
		name:           'payphone_modal',
		label:          createElement( PayphoneLabel, null ),
		content:        createElement( PayphoneContent, null ),
		edit:           createElement( PayphoneContent, null ),
		canMakePayment: function () { return true; },
		ariaLabel:      title,
		placeOrderButtonLabel: decodeEntities( 'Pagar con Payphone' ),
		supports: {
			features: settings.supports || [ 'products' ],
		},
	} );
}() );
