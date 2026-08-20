<?php
/**
 * Plugin Name: Catálogo wiki WooCommerce (kg/cm)
 * Version: 1.0.0
 * Description: 5 productos de la wiki: precios, pesos y dimensiones en kg/cm para cotizar Chilexpress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'cxp_wiki_catalog_apply', 45 );

function cxp_wiki_catalog_products() {
	return array(
		array(
			'slug'        => 'audifonos-bluetooth',
			'name'        => 'Audífonos Bluetooth',
			'price'       => '19990',
			'weight'      => '0.2',
			'length'      => '15',
			'width'       => '10',
			'height'      => '5',
			'stock'       => 98,
			'short'       => 'Envío liviano/mínimo. Wiki: 0,2 kg · 15×10×5 cm.',
		),
		array(
			'slug'        => 'teclado-mecanico',
			'name'        => 'Teclado mecánico',
			'price'       => '49990',
			'weight'      => '0.9',
			'length'      => '45',
			'width'       => '15',
			'height'      => '4',
			'stock'       => 100,
			'short'       => 'Paquete estándar. Wiki: 0,9 kg · 45×15×4 cm.',
		),
		array(
			'slug'        => 'monitor-24-pulgadas',
			'name'        => 'Monitor 24 pulgadas',
			'price'       => '129990',
			'weight'      => '3.5',
			'length'      => '60',
			'width'       => '40',
			'height'      => '15',
			'stock'       => 49,
			'short'       => 'Volumen medio. Wiki: 3,5 kg · 60×40×15 cm.',
		),
		array(
			'slug'        => 'notebook-15-pulgadas',
			'name'        => 'Notebook 15 pulgadas',
			'price'       => '549990',
			'weight'      => '2.2',
			'length'      => '40',
			'width'       => '30',
			'height'      => '8',
			'stock'       => 30,
			'short'       => 'Valor declarado alto. Wiki: 2,2 kg · 40×30×8 cm.',
		),
		array(
			'slug'        => 'silla-ergonomica-bulto-grande',
			'name'        => 'Silla ergonómica (bulto grande)',
			'price'       => '189990',
			'weight'      => '12',
			'length'      => '70',
			'width'       => '65',
			'height'      => '30',
			'stock'       => 20,
			'short'       => 'Bulto grande/pesado. Wiki: 12 kg · 70×65×30 cm.',
		),
	);
}

function cxp_wiki_catalog_apply() {
	if ( get_option( 'cxp_wiki_catalog' ) === '1' ) {
		return;
	}
	if ( ! class_exists( 'WC_Product_Simple' ) ) {
		return;
	}

	update_option( 'woocommerce_weight_unit', 'kg' );
	update_option( 'woocommerce_dimension_unit', 'cm' );
	update_option( 'woocommerce_currency', 'CLP' );
	update_option( 'woocommerce_price_thousand_sep', '.' );
	update_option( 'woocommerce_price_decimal_sep', ',' );
	update_option( 'woocommerce_price_num_decimals', '0' );
	update_option( 'woocommerce_default_country', 'CL:RM' );

	foreach ( cxp_wiki_catalog_products() as $data ) {
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
		$product->set_short_description( $data['short'] );
		$product->set_manage_stock( true );
		if ( (int) $product->get_stock_quantity() <= 0 ) {
			$product->set_stock_quantity( $data['stock'] );
		}
		$product->set_stock_status( 'instock' );
		$product->set_virtual( false );
		$product->set_downloadable( false );
		$product->save();
	}

	cxp_wiki_catalog_enable_chilexpress_shipping();
	update_option( 'cxp_wiki_catalog', '1', false );
}

function cxp_wiki_catalog_enable_chilexpress_shipping() {
	if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
		return;
	}
	$zone = null;
	foreach ( WC_Shipping_Zones::get_zones() as $zone_data ) {
		if ( 'Chile' === ( $zone_data['zone_name'] ?? '' ) ) {
			$zone = WC_Shipping_Zones::get_zone( $zone_data['id'] );
			break;
		}
	}
	if ( ! $zone ) {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'Chile' );
		$zone->save();
		$zone->add_location( 'CL', 'country' );
	}
	$has_oficial = false;
	foreach ( $zone->get_shipping_methods( true ) as $method ) {
		if ( 'chilexpress_woo_oficial' === $method->id ) {
			$has_oficial = true;
			break;
		}
	}
	if ( ! $has_oficial ) {
		$zone->add_shipping_method( 'chilexpress_woo_oficial' );
	}
}
