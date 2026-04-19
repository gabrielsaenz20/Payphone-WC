<?php

namespace WC\Payphone;

defined('ABSPATH') || exit;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use WC\Payphone\PayphoneDependencies;
use WC\Payphone\PayphonePaymentResult;
use WC\Payphone\PayphoneConfig;

class PayphonePlugin
{
  const WC_PAYPHONE_MIN_REQUIRED_PHP_VERSION = '7.4';
  const WC_PAYPHONE_PLUGIN_VERSION = '1.0.4';
  public function __construct()
  {
    $this->define_constants();
    $this->includes();
    $this->check_dependencies();
    $this->init_hooks();
    $this->create_payment_result();

  }

  private function define_constants()
  {

    if (!defined('WC_PAYPHONE_PLUGIN_PATH')) {
      define('WC_PAYPHONE_PLUGIN_PATH', plugin_dir_path(WC_PAYPHONE_PLUGIN_FILE));
    }
    if (!defined('WC_PAYPHONE_PLUGIN_URL')) {
      define('WC_PAYPHONE_PLUGIN_URL', WP_PLUGIN_URL . "/" . plugin_basename(dirname(WC_PAYPHONE_PLUGIN_FILE)));
    }

    if (!defined('WC_PAYPHONE_PLUGIN_VERSION')) {
      define('WC_PAYPHONE_PLUGIN_VERSION', self::WC_PAYPHONE_PLUGIN_VERSION);
    }

    if (!defined('WC_PAYPHONE_MIN_REQUIRED_PHP_VERSION')) {
      define('WC_PAYPHONE_MIN_REQUIRED_PHP_VERSION', self::WC_PAYPHONE_MIN_REQUIRED_PHP_VERSION);
    }
  }

  private function includes()
  {
    include_once WC_PAYPHONE_PLUGIN_PATH . '/includes/payphone-dependencies.php';
    include_once WC_PAYPHONE_PLUGIN_PATH . '/includes/payphone-payment-result.php';
    include_once WC_PAYPHONE_PLUGIN_PATH . '/includes/payphone-config.php';

  }

  private function check_dependencies()
  {
    return new PayphoneDependencies();
  }

  /**
   * Hook into actions and filters.
   */
  private function init_hooks()
  {
    add_action('plugins_loaded', [$this, 'load_payphone_plugins']);
    // add_filter('locale', array($this, 'payphone_change_locale')); //Change locale es_* to es_EC
    add_action('wp_head', array($this, 'payphone_add_header_code'));

    add_filter('load_textdomain_mofile', array($this, 'payphone_load_my_own_textdomain'), 10, 2); //Change locale es_* to es_EC

  }
  /**
   * Change locale es_* to es_EC
   */
  function payphone_load_my_own_textdomain($mofile, $domain)
  {
    if (PayphoneConfig::PAYPHONE_GATEWAY_ID === $domain && strpos($mofile, 'es_') !== false) {
      $mofile = WC_PAYPHONE_PLUGIN_PATH . 'languages/' . $domain . '-es_EC.mo';
    }
    return $mofile;
  }

  private function create_payment_result()
  {
    return new PayphonePaymentResult();
  }

  public function payphone_add_header_code()
  {
    ?>
<script type="module" src="<?php echo PayphoneConfig::PAYPHONE_PAYMENT_BUTTON_BOX_JS ?>"></script>
<link rel="stylesheet" href="<?php echo PayphoneConfig::PAYPHONE_PAYMENT_BUTTON_BOX_CSS ?>">
</link>
<?php
  }

  public function load_payphone_plugins(): bool
  {
    //Load files translation
    load_plugin_textdomain(PayphoneConfig::PAYPHONE_TRANSLATIONS, false, plugin_basename(dirname(WC_PAYPHONE_PLUGIN_FILE)) . '/languages');

    if (!class_exists('WC_Payment_Gateway'))
      return false;
    add_filter('woocommerce_payment_gateways', array($this, 'payphone_payment_gateways'));
    //Add block woocommerce
    add_action('woocommerce_blocks_loaded', array($this, 'payphone_payment_block'));

    add_filter('plugin_action_links_' . plugin_basename(WC_PAYPHONE_PLUGIN_FILE), array($this, 'plugin_action_links'));

    return true;
  }

  public function payphone_payment_gateways(array $methods): array
  {
    include_once WC_PAYPHONE_PLUGIN_PATH . '/includes/payphone-payment-gateway.php';
    $methods[] = 'PayphonePaymentGateway';

    return $methods;
  }

  /**
   * Registers WooCommerce Blocks integration.
   *
   */
  public static function payphone_payment_block()
  {
    // Check if the required class exists
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
      // Include the custom Blocks Checkout class
      require_once(WC_PAYPHONE_PLUGIN_PATH . '/block-payment/index.php');
      // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
      add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function (PaymentMethodRegistry $payment_method_registry) {
          $payment_method_registry->register(new PayphoneGatewayBlock());
        }
      );
    }
  }

  /**
   * Add relevant links to plugins page.
   *
   * @since 1.2.0
   *
   * @param array $links Plugin action links
   *
   * @return array Plugin action links
   */
  public function plugin_action_links($links)
  {
    $plugin_links = array();

    if (function_exists('WC')) {
      $setting_url = $this->get_admin_setting_link();
      $plugin_links[] = '<a href="' . esc_url($setting_url) . '">' . __('Settings', PayphoneConfig::PAYPHONE_TRANSLATIONS) . '</a>';
    }

    $plugin_links[] = '<a href="https://docs.payphone.app/" target="_blank">' . __('Docs', PayphoneConfig::PAYPHONE_TRANSLATIONS) . '</a>';

    return array_merge($plugin_links, $links);
  }

  /**
   * Link to settings screen.
   */
  public function get_admin_setting_link()
  {
    if (version_compare(WC()->version, '2.6', '>=')) {
      $section_slug = PayphoneConfig::PAYPHONE_GATEWAY_ID;
    } else {
      $section_slug = strtolower('WC_Gateway_PayPhone');
    }
    return admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $section_slug);
  }

}