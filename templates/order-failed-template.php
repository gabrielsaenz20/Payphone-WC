<?php
use WC\Payphone\PayphoneConfig;

?>
<div class="ppbo-hero ppbo-hero--failed">
  <div class="ppbo-hero__icon">&#10005;</div>
  <h2 class="ppbo-hero__title"><?php echo __('Payment could not be processed', PayphoneConfig::PAYPHONE_TRANSLATIONS) ?></h2>
  <p class="ppbo-hero__subtitle">
    <?php echo sprintf(
      __('Unfortunately your order #%s could not be processed. Please try again or contact us if the problem persists.', PayphoneConfig::PAYPHONE_TRANSLATIONS),
      absint($order->get_id())
    ) ?>
  </p>
  <div class="ppbo-hero__actions">
    <a href="<?php echo esc_url(wc_get_checkout_url()) ?>" class="ppbo-btn ppbo-btn--primary">
      <?php echo __('Try again', PayphoneConfig::PAYPHONE_TRANSLATIONS) ?>
    </a>
    <a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))) ?>" class="ppbo-btn ppbo-btn--outline">
      <?php echo __('Back to shop', PayphoneConfig::PAYPHONE_TRANSLATIONS) ?>
    </a>
  </div>
</div>