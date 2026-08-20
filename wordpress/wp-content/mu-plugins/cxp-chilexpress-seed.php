<?php
/**
 * Plugin Name: Semilla Chilexpress (config + comunas reales)
 * Version: 1.0.0
 * Description: Completa TCC, RUT, origen, remitente y comunas RM. No modifica chilexpress-oficial.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'cxp_chilexpress_seed_apply', 40 );
add_action( 'admin_init', 'cxp_chilexpress_seed_fix_orders_now', 20 );

function cxp_chilexpress_seed_apply() {
	if ( get_option( 'cxp_chilexpress_seeded' ) === '5' ) {
		return;
	}
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$shop = cxp_chilexpress_seed_shop();
	$dest = cxp_chilexpress_seed_destination();

	$general = get_option( 'chilexpress_woo_oficial_general', array() );
	if ( ! is_array( $general ) ) {
		$general = array();
	}

	$general = array_merge(
		$general,
		array(
			'articulos_tienda'            => $general['articulos_tienda'] ?? 5,
			'dias_procesamiento'          => $general['dias_procesamiento'] ?? 1,
			'tipo_prioridad'              => $general['tipo_prioridad'] ?? 2,
			'ancho_producto_defecto'      => 20,
			'alto_producto_defecto'       => 15,
			'largo_producto_defecto'      => 10,
			'peso_producto_defecto'       => 0.8,
			'porcentaje_descuento'        => $general['porcentaje_descuento'] ?? 0,
			'region_origen'               => $shop['region'],
			'comuna_origen'               => $shop['comuna_code'],
			'numero_tcc_origen'           => '18578680',
			'nombre_remitente'            => $shop['name'],
			'telefono_remitente'          => $shop['phone'],
			'email_remitente'             => $shop['email'],
			'rut_seller_remitente'        => '96756430',
			'rut_marketplace_remitente'   => '96756430',
			'region_devolucion'           => $shop['region'],
			'comuna_devolucion'           => $shop['comuna_code'],
			'calle_devolucion'            => $shop['street'],
			'numero_calle_devolucion'     => $shop['number'],
			'complemento_devolucion'      => $shop['complement'],
			'corte_horario'               => $general['corte_horario'] ?? 12,
			'dias_semana'                 => $general['dias_semana'] ?? array( 1, 2, 3, 4, 5 ),
		)
	);
	update_option( 'chilexpress_woo_oficial_general', $general, false );

	$modules = get_option( 'chilexpress_woo_oficial', array() );
	if ( ! is_array( $modules ) ) {
		$modules = array();
	}
	$modules['ambiente']                       = 'staging';
	$modules['api_key_georeferencia_enabled']  = '1';
	$modules['api_key_generacion_ot_enabled']  = '1';
	$modules['api_key_cotizador_enabled']      = '1';
	$modules['api_key_cotizacion_enabled']     = '1';
	if ( function_exists( 'cxp_chilexpress_published_shop_api_keys' ) ) {
		$modules = array_merge( $modules, cxp_chilexpress_published_shop_api_keys() );
	} elseif ( function_exists( 'cxp_chilexpress_official_api_keys' ) ) {
		$modules = array_merge( $modules, cxp_chilexpress_official_api_keys() );
	}
	update_option( 'chilexpress_woo_oficial', $modules, false );

	$coverage = cxp_chilexpress_seed_all_coverage();
	update_option(
		'chilexpress_woo_oficial_region_comuna',
		array(
			'regiones_habilitadas' => $coverage['regiones'],
			'comunas_habilitadas'  => $coverage['comunas'],
		),
		false
	);

	cxp_chilexpress_seed_fix_orders( $dest );
	update_option( 'cxp_chilexpress_seeded', '5', false );
}

function cxp_chilexpress_seed_fix_orders_now() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	cxp_chilexpress_seed_fix_orders( cxp_chilexpress_seed_destination() );
}

function cxp_chilexpress_seed_shop() {
	return array(
		'name'       => 'Tienda Replica Providencia',
		'phone'      => '222234567',
		'email'      => 'alexander.cautivo+testwordpress@aeolabs.io',
		'region'     => 'RM',
		'comuna'     => 'PROVIDENCIA',
		'comuna_code'=> 'PROV',
		'street'     => 'Avenida Providencia',
		'number'     => '1208',
		'complement' => 'Oficina 302',
	);
}

function cxp_chilexpress_seed_destination() {
	return array(
		'first_name' => 'Juan',
		'last_name'  => 'Espoz',
		'email'      => 'alexander.cautivo+testwordpress@aeolabs.io',
		'phone'      => '912345678',
		'country'    => 'CL',
		'state'      => 'RM',
		'city'       => 'LA REINA',
		'address_1'  => 'Avenida Larrain',
		'address_2'  => '5862',
		'postcode'   => '',
		'complement' => 'Casa',
	);
}

function cxp_chilexpress_seed_all_coverage() {
	$regiones = array();
	$comunas  = array();
	$reg_path = WP_PLUGIN_DIR . '/chilexpress-oficial/includes/data/regiones/regiones.json';
	if ( is_readable( $reg_path ) ) {
		$data = json_decode( (string) file_get_contents( $reg_path ), true );
		foreach ( (array) ( $data['regions'] ?? array() ) as $row ) {
			$id = (string) ( $row['regionId'] ?? '' );
			if ( $id ) {
				$regiones[] = $id;
			}
		}
	}
	$dir = WP_PLUGIN_DIR . '/chilexpress-oficial/includes/data/comunas';
	if ( is_dir( $dir ) ) {
		foreach ( glob( $dir . '/*.json' ) ?: array() as $file ) {
			$data = json_decode( (string) file_get_contents( $file ), true );
			foreach ( (array) ( $data['coverageAreas'] ?? array() ) as $row ) {
				$name = (string) ( $row['coverageName'] ?? '' );
				$code = (string) ( $row['countyCode'] ?? '' );
				if ( $name && $code ) {
					$comunas[ $name ] = $code;
				}
			}
		}
	}
	if ( ! $regiones ) {
		$regiones = array( 'RM' );
	}
	if ( ! $comunas ) {
		$comunas = cxp_chilexpress_seed_rm_comunas();
	}
	return array(
		'regiones' => $regiones,
		'comunas'  => $comunas,
	);
}

function cxp_chilexpress_seed_rm_comunas() {
	$path = WP_PLUGIN_DIR . '/chilexpress-oficial/includes/data/comunas/RM.json';
	if ( ! is_readable( $path ) ) {
		return array(
			'PROVIDENCIA' => 'PROV',
			'LA REINA'    => 'LARE',
			'SANTIAGO CENTRO' => 'STGO',
		);
	}
	$data = json_decode( (string) file_get_contents( $path ), true );
	$out  = array();
	foreach ( (array) ( $data['coverageAreas'] ?? array() ) as $row ) {
		$name = (string) ( $row['coverageName'] ?? '' );
		$code = (string) ( $row['countyCode'] ?? '' );
		if ( $name && $code ) {
			$out[ $name ] = $code;
		}
	}
	return $out;
}

function cxp_chilexpress_seed_fix_orders( $dest ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return;
	}
	$orders = wc_get_orders(
		array(
			'limit'  => -1,
			'type'   => 'shop_order',
			'status' => array_keys( wc_get_order_statuses() ),
			'return' => 'objects',
		)
	);
	foreach ( $orders as $order ) {
		if ( ! $order instanceof WC_Order ) {
			continue;
		}
		if ( 'created' === (string) $order->get_meta( 'ot_status' ) ) {
			continue;
		}
		$city    = strtoupper( trim( (string) $order->get_shipping_city() ) );
		$street  = (string) $order->get_shipping_address_1();
		$needs   = ! $order->get_billing_phone()
			|| false !== stripos( $street, 'benito' )
			|| in_array( $city, array( '', 'ALHUE', 'ALHU' ), true )
			|| $city !== strtoupper( $dest['city'] );
		if ( ! $needs ) {
			continue;
		}
		$order->set_billing_phone( $dest['phone'] );
		$order->set_shipping_phone( $dest['phone'] );
		$order->set_shipping_city( $dest['city'] );
		$order->set_billing_city( $dest['city'] );
		$order->set_shipping_state( $dest['state'] );
		$order->set_billing_state( $dest['state'] );
		$order->set_shipping_country( $dest['country'] );
		$order->set_billing_country( $dest['country'] );
		$order->set_shipping_address_1( $dest['address_1'] );
		$order->set_shipping_address_2( $dest['address_2'] );
		$order->set_billing_address_1( $dest['address_1'] );
		$order->set_billing_address_2( $dest['address_2'] );
		$order->update_meta_data( '_shipping_address_3', $dest['complement'] );
		$order->update_meta_data( '_billing_address_3', $dest['complement'] );
		$order->save();
		update_post_meta( $order->get_id(), '_shipping_address_3', $dest['complement'] );
		update_post_meta( $order->get_id(), '_billing_address_3', $dest['complement'] );
	}
}
