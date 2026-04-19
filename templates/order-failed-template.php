<?php
use WC\Payphone\PayphoneConfig;

?>
<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed">
  <?php esc_html_e('Unfortunately, your order cannot be processed because Payphone declined the transaction. Please try your purchase again.', PayphoneConfig::PAYPHONE_TRANSLATIONS); ?>
</p>