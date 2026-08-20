<?php
/**
 * Clona diseño, ajustes WooCommerce y productos de
 * https://chilexpress-woo-test.5-78-137-25.sslip.io/
 */

$_SERVER['HTTP_HOST']       = '127.0.0.1:8080';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['SERVER_NAME']     = '127.0.0.1';
$_SERVER['SERVER_PORT']     = '8080';
$_SERVER['HTTPS']           = 'off';

require_once __DIR__ . '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

function seed_log( $message ) {
	echo $message . "\n";
}

$woocommerce = 'woocommerce/woocommerce.php';
if ( is_plugin_inactive( $woocommerce ) ) {
	$activated = activate_plugin( $woocommerce, '', false, true );
	if ( is_wp_error( $activated ) ) {
		fwrite( STDERR, 'WooCommerce: ' . $activated->get_error_message() . "\n" );
		exit( 1 );
	}
}

$chilexpress = 'chilexpress-oficial/chilexpress-woo-oficial.php';
if ( file_exists( WP_PLUGIN_DIR . '/' . $chilexpress ) && is_plugin_inactive( $chilexpress ) ) {
	$activated = activate_plugin( $chilexpress, '', false, true );
	if ( is_wp_error( $activated ) ) {
		seed_log( 'Chilexpress no se pudo activar (puede ser el fatal del informe): ' . $activated->get_error_message() );
	} else {
		seed_log( 'Chilexpress Oficial 1.4.0 activado.' );
	}
}

switch_theme( 'woodmart-child' );
update_option( 'blogname', 'Celulares, Tablets Rugged y Notebooks Resistentes en Chile' );
update_option( 'blogdescription', '' );
update_option( 'timezone_string', 'America/Santiago' );
update_option( 'WPLANG', 'es_CL' );
update_option( 'locale', 'es_CL' );
update_option( 'permalink_structure', '/%postname%/' );
update_option( 'show_on_front', 'posts' );

if ( class_exists( 'WC_Install' ) ) {
	WC_Install::create_pages();
}

update_option( 'woocommerce_store_address', 'Av. Providencia 1234' );
update_option( 'woocommerce_store_city', 'Santiago' );
update_option( 'woocommerce_default_country', 'CL:RM' );
update_option( 'woocommerce_currency', 'CLP' );
update_option( 'woocommerce_price_thousand_sep', ',' );
update_option( 'woocommerce_price_decimal_sep', '.' );
update_option( 'woocommerce_price_num_decimals', '2' );
update_option( 'woocommerce_weight_unit', 'kg' );
update_option( 'woocommerce_dimension_unit', 'cm' );
update_option( 'woocommerce_price_thousand_sep', '.' );
update_option( 'woocommerce_price_decimal_sep', ',' );
update_option( 'woocommerce_price_num_decimals', '0' );
update_option( 'woocommerce_calc_taxes', 'no' );
update_option( 'woocommerce_enable_coupons', 'yes' );
update_option( 'woocommerce_ship_to_countries', 'specific' );
update_option( 'woocommerce_specific_ship_to_countries', array( 'CL' ) );
update_option( 'woocommerce_allowed_countries', 'specific' );
update_option( 'woocommerce_specific_allowed_countries', array( 'CL' ) );
update_option( 'woocommerce_default_customer_address', 'base' );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_store_pages_only', 'no' );
update_option(
	'woocommerce_cod_settings',
	array(
		'enabled'            => 'yes',
		'title'              => 'Contra reembolso',
		'description'        => 'Paga en efectivo en el momento de la entrega.',
		'instructions'       => '',
		'enable_for_methods' => array(),
		'enable_for_virtual' => 'yes',
	)
);

flush_rewrite_rules( false );
seed_log( 'Tema Woodmart Child 1.0.0, locale es_CL, moneda CLP, unidades kg/cm (wiki WooCommerce).' );

$image_dir = __DIR__ . '/storefront-assets/products';
$products  = array(
	array(
		'slug'   => 'silla-ergonomica-bulto-grande',
		'name'   => 'Silla ergonómica (bulto grande)',
		'price'  => '189990',
		'weight' => '12',
		'length' => '70',
		'width'  => '65',
		'height' => '30',
		'stock'  => 20,
		'image'  => $image_dir . '/silla.jpg',
	),
	array(
		'slug'   => 'notebook-15-pulgadas',
		'name'   => 'Notebook 15 pulgadas',
		'price'  => '549990',
		'weight' => '2.2',
		'length' => '40',
		'width'  => '30',
		'height' => '8',
		'stock'  => 30,
		'image'  => $image_dir . '/notebook.jpeg',
	),
	array(
		'slug'   => 'monitor-24-pulgadas',
		'name'   => 'Monitor 24 pulgadas',
		'price'  => '129990',
		'weight' => '3.5',
		'length' => '60',
		'width'  => '40',
		'height' => '15',
		'stock'  => 49,
		'image'  => $image_dir . '/monitor.jpeg',
	),
	array(
		'slug'   => 'teclado-mecanico',
		'name'   => 'Teclado mecánico',
		'price'  => '49990',
		'weight' => '0.9',
		'length' => '45',
		'width'  => '15',
		'height' => '4',
		'stock'  => 100,
		'image'  => $image_dir . '/teclado.jpg',
	),
	array(
		'slug'   => 'audifonos-bluetooth',
		'name'   => 'Audífonos Bluetooth',
		'price'  => '19990',
		'weight' => '0.2',
		'length' => '15',
		'width'  => '10',
		'height' => '5',
		'stock'  => 98,
		'image'  => $image_dir . '/audifonos.jpg',
	),
);

function seed_attach_image( $file, $parent_id, $title ) {
	if ( ! is_readable( $file ) ) {
		return 0;
	}
	$filename = basename( $file );
	$upload   = wp_upload_bits( $filename, null, file_get_contents( $file ) );
	if ( ! empty( $upload['error'] ) ) {
		seed_log( '  imagen error: ' . $upload['error'] );
		return 0;
	}
	$filetype   = wp_check_filetype( $filename, null );
	$attachment = array(
		'post_mime_type' => $filetype['type'] ?: 'image/jpeg',
		'post_title'     => $title,
		'post_content'   => '',
		'post_status'    => 'inherit',
	);
	$attach_id  = wp_insert_attachment( $attachment, $upload['file'], $parent_id );
	if ( is_wp_error( $attach_id ) || ! $attach_id ) {
		return 0;
	}
	$meta = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
	wp_update_attachment_metadata( $attach_id, $meta );
	return (int) $attach_id;
}

if ( ! class_exists( 'WC_Product_Simple' ) ) {
	fwrite( STDERR, "WooCommerce no está disponible.\n" );
	exit( 1 );
}

foreach ( $products as $data ) {
	$existing = get_page_by_path( $data['slug'], OBJECT, 'product' );
	$product  = $existing ? wc_get_product( $existing->ID ) : new WC_Product_Simple();
	if ( ! $product ) {
		$product = new WC_Product_Simple();
	}

	$product->set_name( $data['name'] );
	$product->set_slug( $data['slug'] );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'visible' );
	$product->set_regular_price( $data['price'] );
	$product->set_price( $data['price'] );
	$product->set_weight( $data['weight'] );
	$product->set_length( $data['length'] );
	$product->set_width( $data['width'] );
	$product->set_height( $data['height'] );
	$product->set_manage_stock( true );
	$product->set_stock_quantity( $data['stock'] );
	$product->set_stock_status( 'instock' );
	$product->set_sold_individually( false );
	$product->set_virtual( false );
	$product->set_downloadable( false );
	$product_id = $product->save();

	if ( ! $product->get_image_id() ) {
		$attach_id = seed_attach_image( $data['image'], $product_id, $data['name'] );
		if ( $attach_id ) {
			$product->set_image_id( $attach_id );
			$product->save();
		}
	}

	seed_log( sprintf(
		'Producto %s  $%s  %s kg  %s×%s×%s cm  stock %d  id %d',
		$data['name'],
		number_format( (float) $data['price'], 2, '.', ',' ),
		$data['weight'],
		$data['length'],
		$data['width'],
		$data['height'],
		$data['stock'],
		$product_id
	) );
}

seed_log( '' );
seed_log( 'Shop:     http://127.0.0.1:8080/shop/' );
seed_log( 'Cart:     http://127.0.0.1:8080/cart/' );
seed_log( 'Admin:    http://127.0.0.1:8080/wp-admin   (admin / admin)' );
seed_log( 'No se copió la contraseña del sitio remoto.' );
