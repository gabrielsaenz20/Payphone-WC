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
    if (is_single(PayphoneConfig::PAGE_RESULT_SLUG)) {
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
    $new_page = array(
      'post_type' => 'post',
      'post_title' => 'Resultado de Pago con Payphone',
      'post_status' => 'publish',
      'post_author' => 1,
      'post_name' => PayphoneConfig::PAGE_RESULT_SLUG,
      'comment_status' => 'closed',
      'ping_status' => 'closed'
    );
    if (!get_page_by_path(PayphoneConfig::PAGE_RESULT_SLUG, OBJECT, 'page')) { // Check If Page Not Exits
      $pageId = wp_insert_post($new_page);
      update_option($this->pageResultId, $pageId);
    }
  }
}