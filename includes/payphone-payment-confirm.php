<?php

defined('ABSPATH') || exit;

use WC\Payphone\PayphoneConfig;

class PayphonePaymentConfirm
{
  public $clientTransactionId;
  public $transactionId;
  public $orderId;
  public $token;
  public $contador;

  public function __construct($orderId, $clientTransactionId, $transactionId, $token)
  {
    $this->orderId = $orderId;
    $this->clientTransactionId = $clientTransactionId;
    $this->transactionId = $transactionId;
    $this->token = $token;
    $this->contador = 0;
  }

  public function confirm()
  {
    global $woocommerce;
    $order = new WC_Order($this->orderId);

    $result = $this->confirm_call($this->contador);

    if ($result == null) {
      $order->update_status('cancelled', __('No valid response was obtained', PayphoneConfig::PAYPHONE_TRANSLATIONS));
      throw new Exception(__("Url not found, payment with PayPhone will be automatically reversed, contact the administrator", PayphoneConfig::PAYPHONE_TRANSLATIONS));

    } else {

      if ($order->has_status(array('processing', 'completed'))) {
        return $result;
      }

      if ($result->statusCode == 2) {
        $order->update_status('cancelled', $result->message);
      }

      if ($result->statusCode == 3) {
        $order->payment_complete();
        $order->update_status('completed');
        $woocommerce->cart->empty_cart();
      }
    }
    return $result;
  }

  private function confirm_call($cont)
  {
    $payphone_args = $this->get_confirm_args();
    $json = json_encode($payphone_args);
    $headers = array(
      'Authorization' => 'Bearer ' . $this->token,
      'Content-Type' => 'application/json',
      'Content-Length' => strlen($json)
    );

    $args = array(
      'body' => $json,
      'timeout' => '5',
      'redirection' => '5',
      'httpversion' => '1.0',
      'blocking' => true,
      'headers' => $headers
    );
    $response = wp_remote_post(PayphoneConfig::PAYPHONE_CONFIRM_URL, $args);
    $info = wp_remote_retrieve_response_code($response);
    if (is_array($response)) {
      reset($response);
      $tipo = get_class(current($response));
    } else {
      $tipo = get_class($response);
    }
    if (strcmp($tipo, 'WP_Error') !== 0) {
      $obj_response = json_decode($response['body']);
      if ($info == 200 && $obj_response != null) {
        return json_decode($response['body']);
      }

      $cont = $cont + 1;
      if ($cont <= 1) {
        return $this->confirm_call($cont);
      }

      if ($obj_response == null) {
        throw new Exception(__("Url not found to confirm the transaction", PayphoneConfig::PAYPHONE_TRANSLATIONS));
      }

      if ($obj_response->message) {
        throw new Exception($obj_response->message);
      }
    } else {
      throw new Exception(__('The request could not be completed', PayphoneConfig::PAYPHONE_TRANSLATIONS));
    }
  }

  private function get_confirm_args()
  {
    $args = new stdClass();
    $args->id = $this->transactionId;
    $args->clientTxId = $this->clientTransactionId;
    return $args;
  }
}