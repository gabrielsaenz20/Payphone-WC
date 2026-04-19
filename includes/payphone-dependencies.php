<?php

namespace WC\Payphone;

defined('ABSPATH') || exit;

use WC\Payphone\PayphoneConfig;

class PayphoneDependencies
{
  const WC_PAYPHONE_MIN_REQUIRED_PHP_VERSION = '7.4';
  const WC_PAYPHONE_PLUGIN_VERSION = '1.0.4';
  public function __construct()
  {
    add_action('admin_notices', array($this, 'maybeShowWoocommerceMissingNotice'));
    add_action('admin_notices', array($this, 'maybeShowPayseraMinPhpVersionNotice'));

  }

  public function hasWoocommerce(): bool
  {
    return class_exists('WooCommerce');
  }

  public function isPhpSupported(): bool
  {
    return version_compare(PHP_VERSION, WC_PAYPHONE_MIN_REQUIRED_PHP_VERSION, '>=')
      ? true : false;
  }

  public function maybeShowWoocommerceMissingNotice(): void
  {
    if ($this->hasWoocommerce()) {
      return;
    }

    $this->showWoocommerceMissingNotice();
  }

  public function maybeShowPayseraMinPhpVersionNotice(): void
  {
    if ($this->isPhpSupported()) {
      return;
    }

    $this->showPayseraMinPhpVersionNotice();
  }

  public function showWoocommerceMissingNotice(): void
  {
    ?>
    <div class="error">
      <p><b><?php PayphoneConfig::get_name_plugin(); ?></b></p>
      <p><?php esc_html_e($this->getDepencyErrorMessages()['woocommerce_missing']); ?></p>
    </div>
    <?php
  }

  public function showPayseraMinPhpVersionNotice(): void
  {
    ?>
    <div class="error">
      <p><b><?php PayphoneConfig::get_name_plugin(); ?></b></p>
      <p><?php esc_html_e($this->getDepencyErrorMessages()['php_min_version']); ?></p>
    </div>
    <?php
  }

  public function getDepencyErrorMessages(): array
  {
    return [
      'woocommerce_missing' => __(
        'The Payphone plugin requires WooCommerce to be installed and activated.',
        PayphoneConfig::PAYPHONE_TRANSLATIONS
      ),
      'php_min_version' => sprintf(
        /* translators: 1: Min Required PHP Version */
        __('The Payphone plugin requires at least PHP %s', PayphoneConfig::PAYPHONE_TRANSLATIONS),
        WC_PAYPHONE_MIN_REQUIRED_PHP_VERSION
      ),
    ];
  }
}