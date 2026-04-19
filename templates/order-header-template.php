<?php
use WC\Payphone\PayphoneConfig;

$client = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
$phone = $order->get_billing_phone();
$email = $order->get_billing_email();
$status = $dataTransaction->transactionStatus;
$is_approved = (int) $dataTransaction->statusCode === 3;
$is_canceled = (int) $dataTransaction->statusCode === 2;
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

<?php if ($is_approved) : ?>
<div class="ppbo-hero ppbo-hero--success">
  <div class="ppbo-hero__icon">&#10003;</div>
  <h2 class="ppbo-hero__title"><?php echo __('Thank you for your purchase!', PayphoneConfig::PAYPHONE_TRANSLATIONS) ?></h2>
  <p class="ppbo-hero__subtitle">
    <?php echo sprintf(
      __('Order #%1$s has been confirmed. A confirmation email was sent to %2$s.', PayphoneConfig::PAYPHONE_TRANSLATIONS),
      absint($order->get_id()),
      esc_html($email)
    ) ?>
  </p>
</div>
<?php elseif ($is_canceled) : ?>
<div class="ppbo-hero ppbo-hero--canceled">
  <div class="ppbo-hero__icon">&#10005;</div>
  <h2 class="ppbo-hero__title"><?php echo __('Payment not completed', PayphoneConfig::PAYPHONE_TRANSLATIONS) ?></h2>
  <p class="ppbo-hero__subtitle">
    <?php echo __('Your payment was canceled. No charge has been made to your account.', PayphoneConfig::PAYPHONE_TRANSLATIONS) ?>
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
<?php endif; ?>

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