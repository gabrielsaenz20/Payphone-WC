<?php
/**
 * Settings for Payphone Payment Gateway.
 *
 */

defined('ABSPATH') || exit;

use WC\Payphone\PayphoneConfig;

return array(
  'enabled' => array(
    'title' => __('Enable/Disable', PayphoneConfig::PAYPHONE_TRANSLATIONS),
    'type' => 'checkbox',
    'label' => __('Enable PayPhone Payment Module.', PayphoneConfig::PAYPHONE_TRANSLATIONS),
    'default' => 'no',
    'description' => __('Show payphone in the Payment List as a payment option', PayphoneConfig::PAYPHONE_TRANSLATIONS)
  ),
  'token' => array(
    'title' => __('Authorization Token:', PayphoneConfig::PAYPHONE_TRANSLATIONS),
    'type' => 'textarea',
    'description' => __('Token obtained from the Payphone developer console.', PayphoneConfig::PAYPHONE_TRANSLATIONS),
    'desc_tip' => true
  ),
  'storeId' => array(
    'title' => __('Store Id:', PayphoneConfig::PAYPHONE_TRANSLATIONS),
    'type' => 'text',
    'description' => __("Optional. Configure this identifier to collect from the merchant's branch. You can get this field from the Payphone Developer console", PayphoneConfig::PAYPHONE_TRANSLATIONS),
    'desc_tip' => true
  )
);