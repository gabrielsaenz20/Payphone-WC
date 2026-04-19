<?php
namespace WC\Payphone;

defined('ABSPATH') || exit;

use WC\Payphone\PayphoneConfig;

class PayphonePaymentResult
{
  public $pageResultId = PayphoneConfig::PAGE_RESULT_SLUG;
  public function __construct()
  {
    register_activation_hook(WC_PAYPHONE_PLUGIN_FILE, array($this, 'payphone_create_page_result'));
    register_deactivation_hook(WC_PAYPHONE_PLUGIN_FILE, array($this, 'payphone_delete_page_result'));
    add_action('template_redirect', array($this, 'payphone_order_plugin_template_redirect'));
  }

  function payphone_order_plugin_template_redirect(): void
  {
    $settings = get_option('woocommerce_' . PayphoneConfig::PAYPHONE_GATEWAY_ID . '_settings', array());
    $result_page_id = isset($settings['result_page_id']) ? (int) $settings['result_page_id'] : 0;
    $stored_post_id = (int) get_option(PayphoneConfig::PAGE_RESULT_SLUG);

    $is_result_page = is_single(PayphoneConfig::PAGE_RESULT_SLUG)
      || ($result_page_id > 0 && is_page($result_page_id))
      || ($stored_post_id > 0 && (is_single($stored_post_id) || is_page($stored_post_id)));

    if ($is_result_page) {
      include_once WC_PAYPHONE_PLUGIN_PATH . 'templates/payphone-order-template.php';
      exit;
    }
  }

  function payphone_delete_page_result()
  {
    if (!current_user_can('activate_plugins')) {
      return;
    }
    $pageId = get_option($this->pageResultId);
    if ($pageId) {
      wp_delete_post($pageId, true);
    }
  }

  function payphone_create_page_result()
  {
    if (!current_user_can('activate_plugins'))
      return;

    // Check for existing post of any type with this slug so we don't create duplicates.
    $existing = get_page_by_path(PayphoneConfig::PAGE_RESULT_SLUG, OBJECT, array('page', 'post'));
    if ($existing) {
      update_option($this->pageResultId, $existing->ID);
      return;
    }

    $new_page = array(
      'post_type' => 'post',
      'post_title' => 'Resultado de Pago con Payphone',
      'post_status' => 'publish',
      'post_author' => 1,
      'post_name' => PayphoneConfig::PAGE_RESULT_SLUG,
      'comment_status' => 'closed',
      'ping_status' => 'closed'
    );
    $pageId = wp_insert_post($new_page);
    update_option($this->pageResultId, $pageId);
  }
}