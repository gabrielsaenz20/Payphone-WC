<?php
/**
 * Template Name: Pagina Virtual del resultado de la transaccion de Payphone
 */

// Cargar menu
if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
  // Tema basado en bloques (FSE)
  echo do_blocks( '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->' );
  
  // Cargar estilos
  wp_enqueue_style( 'global-styles' );
  wp_head();
} else {
  // Tema clásico
  get_header();
}

$redirectHome = "<script>location.href = '" . get_site_url() . "'</script>";

// Obtener el ID de la order
// Obtener los datos completos de la orden
$queries = array();
parse_str($_SERVER['QUERY_STRING'], $queries);
if (array_key_exists("orderId", $queries)) {
  $order_id = $queries['orderId'];
}
if ($order_id) {
  try {
    $order = wc_get_order($order_id);
    $showTransactionPayphone = $order->get_meta('showTransactionPayphone', true);

    if ($showTransactionPayphone || !$order) {
      //echo $redirectHome;
    }
  } catch (\Throwable $th) {
    echo $redirectHome;
  }

} else {
  echo $redirectHome;
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
  <title>Detalle de Pago</title>
  <meta charset="<?php bloginfo('charset'); ?>" />
  <?php wp_head(); ?>
  <link rel="stylesheet" href="<?php echo WC_PAYPHONE_PLUGIN_URL . '/assets/css/payphone-order.css' ?>">
  </link>
</head>

<body <?php body_class(); ?>>
  <div class="wp-site-blocks ppbo-order">
    <main>
      <div class="content">
        <?php
        //Validar si la order fallo
        if ($order->has_status('failed')) {
          wc_get_template('templates/order-failed-template.php', array('order' => $order), '', WC_PAYPHONE_PLUGIN_PATH);
        } else {
          //Obtener los datos adicionales de la orden
          $dataTransaction = $order->get_meta('DataPayphone', true);
          if ($dataTransaction != "") {
            $dataTransaction = json_decode($dataTransaction);

            //Template header de la orden
            wc_get_template(
              'templates/order-header-template.php',
              array('dataTransaction' => $dataTransaction, 'order' => $order),
              '',
              WC_PAYPHONE_PLUGIN_PATH
            );
            //Template de los productos
            wc_get_template(
              'templates/order-products-template.php',
              array(
                'dataTransaction' => $dataTransaction,
                'order' => $order,

              ),
              '',
              WC_PAYPHONE_PLUGIN_PATH
            );

            //Template del detalle del pago
            wc_get_template(
              'templates/order-payment-detail-template.php',
              array('dataTransaction' => $dataTransaction),
              '',
              WC_PAYPHONE_PLUGIN_PATH
            );

            //Template footer de la orden
            wc_get_template('templates/order-footer-template.php', [], '', WC_PAYPHONE_PLUGIN_PATH);

            $order->update_meta_data('showTransactionPayphone', 'ready');
            $order->save();
          } else {
            //echo $redirectHome;
          }
        }
        ?>
      </div>
    </main>
  </div>
  <?php
  // Incluir el pie de pagina
  //get_footer();
  wp_footer();
  ?>
</body>

</html>