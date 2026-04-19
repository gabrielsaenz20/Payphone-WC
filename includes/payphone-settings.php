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
  ),
  'result_page_id' => array(
    'title' => __('Result Page:', PayphoneConfig::PAYPHONE_TRANSLATIONS),
    'type' => 'select',
    'description' => __('Select the page where customers will be redirected after completing a payment. The page must exist before selecting it here.', PayphoneConfig::PAYPHONE_TRANSLATIONS),
    'desc_tip' => true,
    'default' => '',
    'options' => array_merge(
      array('' => __('— Default (auto-created post) —', PayphoneConfig::PAYPHONE_TRANSLATIONS)),
      wp_list_pluck(get_pages(array('post_status' => 'publish', 'sort_column' => 'post_title')), 'post_title', 'ID')
    )
  ),
  'custom_css' => array(
    'title' => __('Custom CSS:', PayphoneConfig::PAYPHONE_TRANSLATIONS),
    'type' => 'textarea',
    'description' => __('Optional. Add CSS rules to customise the appearance of the Payphone payment form. Example: <code>.ppb-body { background-color: transparent; }</code>', PayphoneConfig::PAYPHONE_TRANSLATIONS),
    'default' => '',
    'desc_tip' => false
  )
);