<?php
namespace WC\Payphone;

defined('ABSPATH') || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class PayphoneGatewayBlock extends AbstractPaymentMethodType
{

  private $gateway;

  /**
   * Payment method name/id/slug.
   *
   * @var string
   */
  protected $name = PayphoneConfig::PAYPHONE_GATEWAY_ID;

  public function initialize()
  {
    //woocommerce_$name_settings
    $this->settings = get_option('woocommerce_' . $this->name . '_settings', []);
    $gateways = WC()->payment_gateways->payment_gateways();
    $this->gateway = $gateways[$this->name];
  }

  public function is_active()
  {
    return $this->gateway->is_available();
  }

  public function get_payment_method_script_handles()
  {
    $script_asset_path = WC_PAYPHONE_PLUGIN_PATH . 'block-payment/build/payphone-gateway.asset.php';
    $script_asset = file_exists($script_asset_path)
      ? require $script_asset_path
      : array(
        'dependencies' => array(),
        'version' => $this->get_file_version($script_asset_path),
      );


    wp_register_script(
      'payphone_gateway_blocks_integration',
      plugin_dir_url(__FILE__) . '/build/payphone-gateway.js',
      $script_asset['dependencies'],
      $script_asset['version'],
      true
    );
    if (function_exists('wp_set_script_translations')) {
      wp_set_script_translations('payphone_gateway_blocks_integration');
    }
    return ['payphone_gateway_blocks_integration'];
  }

  public function get_payment_method_data()
  {
    return [
      'title' => PayphoneConfig::get_label_payment(),
      'icon' => PayphoneConfig::PAYPHONE_LOGO,
      'token' => $this->get_setting('token'),
      'storeId' => $this->get_setting('storeId'),
      'supports' => array_filter($this->gateway->supports, [$this->gateway, 'supports']),
      'domainPlugin' => PayphoneConfig::PAYPHONE_TRANSLATIONS,
      'lang' => get_bloginfo('language'),
      'transactionSummaryUrl' => get_home_url()
    ];
  }

}