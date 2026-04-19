<?php
use WC\Payphone\PayphoneConfig;

$client = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
$phone = $order->get_billing_phone();
$email = $order->get_billing_email();
$status = $dataTransaction->transactionStatus;
switch ($dataTransaction->statusCode) {
  case 2:
    $status = __('Canceled', PayphoneConfig::PAYPHONE_TRANSLATIONS);
    break;
  case 3:
    $status = __('Approved', PayphoneConfig::PAYPHONE_TRANSLATIONS);
    break;
  default:
    break;
}

?>
<div class="header">
  <a href="https://www.payphone.app/" target="_blank" style="display: inline-block;">
    <img style="width:181px" alt="logo Payphone" src="<?php echo WC_PAYPHONE_PLUGIN_URL . '/assets/images/logo.png' ?>">
  </a>
  <div class="title">
    <?php echo $dataTransaction->storeName ?>
  </div>
</div>
<div class="order-info <?php echo $dataTransaction->statusCode === 2 ? 'canceled' : '' ?>">
  <div>
    <span style='text-transform: uppercase;'>
      <?php echo __('Pay', PayphoneConfig::PAYPHONE_TRANSLATIONS) ?>
    </span>:
    <?php echo $status ?>

  </div>
  <div>
    <span style='text-transform: uppercase;'>
      <?php echo __('Order', PayphoneConfig::PAYPHONE_TRANSLATIONS) ?>
    </span>#
    <?php echo $order->get_id() ?>
  </div>
  <?php if ($dataTransaction->message) { ?>
    <span style="width:100%"><?php echo $dataTransaction->message ? $dataTransaction->message : '' ?></span>
  <?php } ?>
</div>

<div class="order-client">
  <div>
    <div>
      <?php echo __('Date', PayphoneConfig::PAYPHONE_TRANSLATIONS) ?>:
      <?php echo date("d-m-Y H:i", strtotime($dataTransaction->date)) ?>
    </div>
    <div style="text-transform: capitalize;">
      <?php echo __('Client', PayphoneConfig::PAYPHONE_TRANSLATIONS) ?>:
      <?php echo $client ?>
    </div>
  </div>
  <div>
    <div>
      <?php echo $dataTransaction->authorizationCode ? __('Authorization', PayphoneConfig::PAYPHONE_TRANSLATIONS) . ':' : '' ?>
      <?php echo $dataTransaction->authorizationCode ?>
    </div>
    <div>
      <?php echo $phone ? __('Phone', PayphoneConfig::PAYPHONE_TRANSLATIONS) : __('Email', PayphoneConfig::PAYPHONE_TRANSLATIONS) ?>:
      <?php echo $phone ? $phone : $email ?>
    </div>
  </div>
  <div style="justify-content:end;">
    <div>
      <?php echo $phone ? __('Email', PayphoneConfig::PAYPHONE_TRANSLATIONS) . ':' : '' ?>
      <?php echo $phone ? $email : '' ?>
    </div>
  </div>
</div>