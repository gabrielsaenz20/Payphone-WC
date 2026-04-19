<?php
/*
 * Plugin Name: Payphone Payment Box Gateway
 * Plugin URI: https://www.payphone.app/business/
 * Description: Accept payments in your online store with Visa, Mastercard cards or Payphone balance using our plugin.
 * Version: 1.0.4
 * Author: Payphone
 * Author URI: https://www.payphone.app/
 * Text Domain: payphonebox
 * Domain Path: /languages/
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * WC requires at least: 8.3.0
 * WC tested up to: 8.8.3
 *
 */

defined('ABSPATH') || exit;

if (!defined('WC_PAYPHONE_PLUGIN_FILE')) {
  define('WC_PAYPHONE_PLUGIN_FILE', __FILE__);
}

//Habilitar HPOS
add_action('before_woocommerce_init', function () {
  if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
  }
});

//Debug to console.log browser
if (!function_exists('wc_payphone_console_log')) {
  function wc_payphone_console_log($data)
  {
    $output = $data;
    if (is_array($output)) {
      $output = implode(',', $output);
    }

    echo "<script>console.log('PayphoneDebug: " . $output . "' );</script>";
  }
}

//Debug error log php
if (!function_exists('wc_payphone_write_log')) {
  function wc_payphone_write_log($log)
  {
    if (is_array($log) || is_object($log)) {
      error_log(print_r($log, true));
    } else {
      error_log($log);
    }
  }

}

// Include the main Plugin class.
if (!class_exists('PayphonePlugin', false)) {
  include_once dirname(__FILE__) . '/includes/payphone-plugin.php';
}

/**
 * Load Payphone Plugin when all plugins loaded.
 *
 * @return WC\Payphone\PayphonePlugin
 */
function payphonePlugin()
{
  return new WC\Payphone\PayphonePlugin();
}
payphonePlugin();