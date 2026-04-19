<?php

defined('ABSPATH') || exit;

use WC\Payphone\PayphoneConfig;

class PayphonePaymentGateway extends WC_Payment_Gateway
{
  /**
   * Payphone StoreId
   *
   * @var string
   */
  public $storeId;
  /**
   * Payphone Token
   *
   * @var string
   */
  public $token;
  public function __construct()
  {
    $this->id = PayphoneConfig::PAYPHONE_GATEWAY_ID;
    $this->has_fields = false;
    $this->title = __("Payphone Payment Box", PayphoneConfig::PAYPHONE_TRANSLATIONS);
    $this->method_description = __('Receive payments via credit or debit card Visa or Mastercard | Payphone', PayphoneConfig::PAYPHONE_TRANSLATIONS);
    $this->supports = array(
      'products',
      'refunds',
    );

    // Load the settings.
    $this->init_form_fields();
    $this->init_settings();

    // Define user set variables.
    $this->enabled = $this->get_option('enabled');
    $this->storeId = $this->get_option('storeId');
    $this->token = $this->get_option('token');

    // Actions.
    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

    // Agrega Fee para pruebas
    //add_action('woocommerce_cart_calculate_fees', array($this, 'woocommerce_custom_feetest'));

  }

  function woocommerce_custom_feetest()
  {
    global $woocommerce;
    if (is_admin() && !defined('DOING_AJAX'))
      return;
    $percentage = 0.01;
    $surcharge = ($woocommerce->cart->cart_contents_total + $woocommerce->cart->shipping_total) * $percentage;
    $woocommerce->cart->add_fee('Surcharge', $surcharge, true, '');
  }

  /**
   * Check if Gateway can be display
   *
   * @return bool
   */
  function is_available()
  {
    global $woocommerce;

    if ($this->enabled == "yes"):
      if (!$this->is_valid_currency()) {
        return false;
      }
      if (version_compare($woocommerce->version, '8.3.0', '<')) {
        return false;
      }
      return true;
    endif;

    return false;
  }

  /**
   * Check if current currency is valid for payphone
   *
   * @return bool
   */
  function is_valid_currency()
  {
    if (!in_array(get_woocommerce_currency(), apply_filters('woocommerce_payphonebox_supported_currencies', array('ARS', 'BRL', 'COP', 'MXN', 'PEN', 'USD'))))
      return false;

    return true;
  }


  /**
   * Initialise Gateway Settings Form Fields.
   */
  public function init_form_fields()
  {
    $this->form_fields = include __DIR__ . '/payphone-settings.php';
  }

  /**
   * Output the gateway settings screen.
   */
  public function admin_options()
  {
    echo '<h3>PayPhone</h3>';
    echo '<p>' . __('Pay with Payphone', PayphoneConfig::PAYPHONE_TRANSLATIONS) . '</p>';
    echo '<table class="form-table">';
    // Generate the HTML For the settings form.
    echo '<h3>' . __('Initial Key Setting', PayphoneConfig::PAYPHONE_TRANSLATIONS) . '</h3>';
    echo __('Response URL:', PayphoneConfig::PAYPHONE_TRANSLATIONS) . ' ' . get_site_url() . '/wc-api/WC_Gateway_PayPhone';
    echo '<br>';

    $this->generate_settings_html();
    echo '</table>';
  }

  function process_payment($order_id)
  {
    global $woocommerce;
    $order = wc_get_order($order_id);

    $clientTransactionId = $_POST['clienttransactionid'];
    $transactionId = $_POST['transactionid'];

    if ($clientTransactionId && $transactionId) {
      try {
        include_once(dirname(__FILE__) . '/payphone-payment-confirm.php');
        $result = new PayphonePaymentConfirm($order_id, $clientTransactionId, $transactionId, $this->token);

        $order->update_meta_data('payphone_tx_id', $transactionId);

        //Llamar al servicio para confirmar la transaccion
        $response = $result->confirm();

        //Agregar datos de la respuesta de payphone a la orden de woocommerce
        $order->add_meta_data("DataPayphone", json_encode($response));
        $order->save();

        $pageResultId = get_option(PayphoneConfig::PAGE_RESULT_SLUG);
        return array(
          'result' => 'success',
          'redirect' => add_query_arg('orderId', $order_id, get_permalink($pageResultId))
        );

      } catch (Exception $ex) {
        $order->add_meta_data("mesaggeErrorPayphone", $ex->getMessage());
        $order->save();
        throw new Exception(__('Payment error: ', PayphoneConfig::PAYPHONE_TRANSLATIONS) . $ex->getMessage());
      }
    } else {
      $message = __('An error occurred in the payment. Contact the administrator.', PayphoneConfig::PAYPHONE_TRANSLATIONS);
      throw new Exception($message);
    }

  }


}