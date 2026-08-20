<?php
global $order_label, $order_type;
if (!is_user_logged_in() || !current_user_can('edit_shop_orders')) {
    echo "Not Authorized";
    die();
}
if(!isset($order_label)){
    die("Invalid Order Id");
}

if(!isset($order_type)){
    $order_type = 4;
}

$order_type = intval($order_type);
if ($order_type < 1 || $order_type > 4) {
    $order_type = 4;
}

$order_id = intval($order_label);
if (!$order_id && $order_id < 0) {
    die("Invalid Order Id");
}
$order = wc_get_order( $order_id );
if (!$order) {
    die("Order Not found");
}

$API = new Chilexpress_Woo_Oficial_API();

$transportOrderNumbers = $order->get_meta( 'transportOrderNumbers');
$etiqueta = $API->obtener_etiqueta( $transportOrderNumbers[0], $order_type);

$type = 'PDF';
if (property_exists($etiqueta, 'data') && property_exists($etiqueta->data, 'label') && property_exists($etiqueta->data->label, 'labelType')) {
    $type = $etiqueta->data->label->labelType;
}

$fileExtension = '';
$contentType = '';
$decodeData = true;
switch ($type) {
    case 'PDF':
        $fileExtension = '.pdf';
        $contentType = 'application/pdf';
        break;
    case 'ZPL':
        $fileExtension = '.zpl';
        $contentType = 'application/zpl';
        $decodeData = false;
        break;
    case 'Binary':
        $fileExtension = '.png';
        $contentType = 'image/png';
        break;
    default:
        echo "Unknown label type";
        die(0);
}

header('Content-Type: ' . $contentType);
$fileName = 'Orden_de_transporte_' . $transportOrderNumbers[0];
header('Content-Disposition: attachment; filename="'.$fileName.$fileExtension.'"');
if (property_exists($etiqueta, 'data') && property_exists($etiqueta->data, 'label') && property_exists($etiqueta->data->label, 'labelData')) {
    if ($decodeData) {
        echo base64_decode($etiqueta->data->label->labelData);
    } else {
        echo $etiqueta->data->label->labelData;
    }
}
die();
