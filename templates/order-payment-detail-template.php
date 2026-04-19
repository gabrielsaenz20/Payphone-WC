<?php
use WC\Payphone\PayphoneConfig;

?>
<div class="payment-title">
  <?php echo __('Detail Payment', PayphoneConfig::PAYPHONE_TRANSLATIONS) ?>
</div>
<div class="payment-info">
  <div>
    <div>
      <?php echo __('Payment Method', PayphoneConfig::PAYPHONE_TRANSLATIONS) ?>:
      <?php echo $dataTransaction->cardBrand ?>
    </div>
  </div>
  <div>
    <div>
      <?php echo __('Transaction number', PayphoneConfig::PAYPHONE_TRANSLATIONS) ?>:
      <?php echo $dataTransaction->transactionId ?>
    </div>
  </div>
  <div style="text-transform: capitalize;">
    <div>
      <?php echo $dataTransaction->optionalParameter4 ? __('Names', PayphoneConfig::PAYPHONE_TRANSLATIONS) : __('Client', PayphoneConfig::PAYPHONE_TRANSLATIONS) ?>:
      <?php echo $dataTransaction->optionalParameter4 ? strtolower($dataTransaction->optionalParameter4) : $dataTransaction->phoneNumber ?>
    </div>
  </div>
  <div>
    <div>
      <?php echo __('Reference', PayphoneConfig::PAYPHONE_TRANSLATIONS) ?>:
      <?php echo $dataTransaction->reference ?>
    </div>
  </div>
</div>