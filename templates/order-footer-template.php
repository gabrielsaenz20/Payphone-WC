<?php
use WC\Payphone\PayphoneConfig;

?>
<div style="display:flex;align-items:center;flex-direction:column;margin-top:30px;">
  <div style="font-weight: 400;font-size:11px;color:#9C948D;max-width:310px;text-align:center;margin-bottom:16px;">
    <?php echo __('This payment is being processed by Payphone, a provider of Grupo Promerica.', PayphoneConfig::PAYPHONE_TRANSLATIONS) ?>
  </div>
  <img alt="partners Payphone" src="<?php echo WC_PAYPHONE_PLUGIN_URL . '/assets/images/partners.png' ?>">
</div>