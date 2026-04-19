<?php

namespace WC\Payphone;

defined('ABSPATH') || exit;

class PayphoneConfig
{
  public const PAYPHONE_LOGO = WC_PAYPHONE_PLUGIN_URL . '/assets/images/payphone-payment.png';
  public const PAYPHONE_TRANSLATIONS = 'payphonebox';
  public const PAYPHONE_GATEWAY_ID = 'payphonebox';
  public const PAGE_RESULT_SLUG = 'payphonebox-orders';
  public const PAYPHONE_PAYMENT_BUTTON_BOX_JS = 'https://cdn.payphonetodoesposible.com/box/v1.1/payphone-payment-box.js';
  public const PAYPHONE_PAYMENT_BUTTON_BOX_CSS = 'https://cdn.payphonetodoesposible.com/box/v1.1/payphone-payment-box.css';
  public const PAYPHONE_CONFIRM_URL = 'https://pay.payphonetodoesposible.com/api/button/V2/Confirm';

  public static function get_name_plugin()
  {
    return __('Payphone Payment Box Gateway', PayphoneConfig::PAYPHONE_TRANSLATIONS);
  }
  public static function get_label_payment()
  {
    return __('Pay with Payphone', PayphoneConfig::PAYPHONE_TRANSLATIONS);
  }
}