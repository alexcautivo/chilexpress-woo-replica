<?php
/**
 * Plugin Name: Checkout réplica (contacto + Chilexpress + tarjeta debug)
 * Version: 1.0.0
 * Description: Prefills Juan Espoz, tarifas Chilexpress de prueba y tarjeta falsa que aprueba.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'woocommerce_payment_gateways',
	static function ( $gateways ) {
		$gateways[] = 'CXP_Test_Card_Gateway';
		return $gateways;
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		class CXP_Test_Card_Gateway extends WC_Payment_Gateway {
			public function __construct() {
				$this->id                 = 'cxp_test_card';
				$this->method_title       = 'Tarjeta de prueba (debug)';
				$this->method_description = 'Pasarela falsa local. Aprueba 4242 4242 4242 4242.';
				$this->has_fields         = true;
				$this->supports           = array( 'products' );
				$this->init_form_fields();
				$this->init_settings();
				$this->title       = $this->get_option( 'title', 'Tarjeta de crédito (prueba)' );
				$this->description = $this->get_option(
					'description',
					'Pago de prueba. Usa 4242 4242 4242 4242 · vence 12/34 · CVC 123. No se cobra nada.'
				);
				$this->enabled     = $this->get_option( 'enabled', 'yes' );
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			}

			public function init_form_fields() {
				$this->form_fields = array(
					'enabled'     => array(
						'title'   => 'Habilitar',
						'type'    => 'checkbox',
						'label'   => 'Habilitar tarjeta de prueba',
						'default' => 'yes',
					),
					'title'       => array(
						'title'   => 'Título',
						'type'    => 'text',
						'default' => 'Tarjeta de crédito (prueba)',
					),
					'description' => array(
						'title'   => 'Descripción',
						'type'    => 'textarea',
						'default' => 'Tarjeta falsa de debug. Número: 4242 4242 4242 4242 · Vence 12/34 · CVC 123. Siempre aprueba.',
					),
				);
			}

			public function payment_fields() {
				echo '<p>' . esc_html( $this->description ) . '</p>';
				woocommerce_form_field(
					'cxp_card_number',
					array(
						'type'     => 'text',
						'label'    => 'Número de tarjeta',
						'required' => true,
						'default'  => '4242424242424242',
					)
				);
				woocommerce_form_field(
					'cxp_card_exp',
					array(
						'type'     => 'text',
						'label'    => 'Vencimiento (MM/AA)',
						'required' => true,
						'default'  => '12/34',
					)
				);
				woocommerce_form_field(
					'cxp_card_cvc',
					array(
						'type'     => 'text',
						'label'    => 'CVC',
						'required' => true,
						'default'  => '123',
					)
				);
			}

			public function validate_fields() {
				$number = $this->get_card_number();
				if ( ! in_array( $number, array( '4242424242424242', '4111111111111111' ), true ) ) {
					wc_add_notice( 'Tarjeta rechazada. Usa 4242 4242 4242 4242 (debug).', 'error' );
					return false;
				}
				return true;
			}

			public function process_payment( $order_id ) {
				$order  = wc_get_order( $order_id );
				$number = $this->get_card_number();
				if ( ! in_array( $number, array( '4242424242424242', '4111111111111111' ), true ) ) {
					wc_add_notice( 'Tarjeta rechazada. Usa 4242 4242 4242 4242 (debug).', 'error' );
					return array( 'result' => 'failure' );
				}

				$order->payment_complete( 'cxp-test-' . $order_id );
				$order->add_order_note( 'Pago debug aprobado. Tarjeta de prueba ****' . substr( $number, -4 ) . '.' );
				if ( WC()->cart ) {
					WC()->cart->empty_cart();
				}
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			}

			private function get_card_number() {
				$raw = isset( $_POST['cxp_card_number'] ) ? wp_unslash( $_POST['cxp_card_number'] ) : '4242424242424242';
				$raw = preg_replace( '/\D+/', '', (string) $raw );
				return $raw !== '' ? $raw : '4242424242424242';
			}
		}
	},
	20
);

add_action(
	'woocommerce_blocks_loaded',
	static function () {
		if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}

		class CXP_Test_Card_Blocks extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
			protected $name = 'cxp_test_card';

			public function initialize() {
				$this->settings = get_option( 'woocommerce_cxp_test_card_settings', array() );
			}

			public function is_active() {
				return 'yes' === ( $this->settings['enabled'] ?? 'yes' );
			}

			public function get_payment_method_script_handles() {
				wp_register_script(
					'cxp-test-card-blocks',
					content_url( 'mu-plugins/cxp-checkout-debug/blocks.js' ),
					array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
					'1.0.2',
					true
				);
				return array( 'cxp-test-card-blocks' );
			}

			public function get_payment_method_data() {
				return array(
					'title'       => $this->settings['title'] ?? 'Tarjeta de crédito (prueba)',
					'description' => $this->settings['description'] ?? 'Tarjeta falsa de debug. Número: 4242 4242 4242 4242 · Vence 12/34 · CVC 123. Siempre aprueba.',
					'supports'    => array( 'products' ),
					'testCard'    => '4242424242424242',
					'testExp'     => '12/34',
					'testCvc'     => '123',
				);
			}
		}

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			static function ( $registry ) {
				$registry->register( new CXP_Test_Card_Blocks() );
			}
		);
	}
);

add_action( 'init', 'cxp_checkout_debug_configure', 30 );
add_action( 'init', 'cxp_checkout_force_oficial_shipping', 35 );
add_action( 'woocommerce_init', 'cxp_checkout_debug_prefill_customer', 30 );
add_action( 'woocommerce_cart_loaded_from_session', 'cxp_checkout_debug_prefill_customer', 20 );
add_action( 'template_redirect', 'cxp_checkout_debug_ensure_cart_item', 5 );

// SQLite cannot run WooCommerce reserved-stock SQL (FOR UPDATE / INTERVAL). Skip holds locally.
add_filter( 'woocommerce_order_hold_stock_minutes', '__return_zero' );

add_filter(
	'woocommerce_checkout_fields',
	static function ( $fields ) {
		$defaults = cxp_checkout_debug_customer_data();
		foreach ( array( 'billing', 'shipping' ) as $group ) {
			$state_key = $group . '_state';
			$city_key  = $group . '_city';
			if ( isset( $fields[ $group ][ $state_key ] ) ) {
				$fields[ $group ][ $state_key ]['default'] = $defaults['state'];
			}
			if ( isset( $fields[ $group ][ $city_key ] ) && class_exists( 'Chilexpress_Woo_Oficial_Coverage' ) ) {
				$state   = WC()->checkout ? WC()->checkout->get_value( $state_key ) : '';
				$state   = $state ? $state : $defaults['state'];
				$coverage = new Chilexpress_Woo_Oficial_Coverage();
				$comunas  = $coverage->obtener_comunas( $state );
				$options  = array();
				foreach ( (array) $comunas as $name => $code ) {
					$options[ $name ] = $name;
				}
				if ( $options ) {
					$fields[ $group ][ $city_key ]['options'] = $options;
					$fields[ $group ][ $city_key ]['default'] = isset( $options[ $defaults['city'] ] ) ? $defaults['city'] : array_key_first( $options );
				}
			}
		}
		return $fields;
	},
	40
);

add_filter(
	'woocommerce_checkout_get_value',
	static function ( $value, $input ) {
		$data = cxp_checkout_debug_customer_data();
		$map  = array(
			'billing_first_name'  => $data['first_name'],
			'billing_last_name'   => $data['last_name'],
			'billing_email'       => $data['email'],
			'billing_country'     => $data['country'],
			'billing_state'       => $data['state'],
			'billing_city'        => $data['city'],
			'billing_address_1'   => $data['address_1'],
			'billing_address_2'   => $data['address_2'],
			'billing_postcode'    => $data['postcode'],
			'billing_phone'       => $data['phone'],
			'billing_address_3'   => $data['complement'] ?? 'Casa',
			'shipping_first_name' => $data['first_name'],
			'shipping_last_name'  => $data['last_name'],
			'shipping_country'    => $data['country'],
			'shipping_state'      => $data['state'],
			'shipping_city'       => $data['city'],
			'shipping_address_1'  => $data['address_1'],
			'shipping_address_2'  => $data['address_2'],
			'shipping_postcode'   => $data['postcode'],
			'shipping_phone'      => $data['phone'],
			'shipping_address_3'  => $data['complement'] ?? 'Casa',
		);
		if ( isset( $map[ $input ] ) && ( $value === null || $value === '' ) ) {
			return $map[ $input ];
		}
		return $value;
	},
	10,
	2
);

add_filter(
	'woocommerce_order_button_text',
	static function () {
		return 'Realizar el pedido';
	}
);

add_action(
	'woocommerce_before_checkout_form',
	static function () {
		$addresses = cxp_checkout_debug_quote_addresses();
		?>
		<nav class="cxp-checkout-steps" aria-label="Pasos del checkout">
			<button type="button" class="cxp-checkout-steps__btn is-active" data-cxp-step="1"><span>1</span> Datos de envío</button>
			<button type="button" class="cxp-checkout-steps__btn" data-cxp-step="2"><span>2</span> Pago y resumen</button>
		</nav>
		<div class="cxp-fill-valid" role="region" aria-label="Destinos de envío">
			<div class="cxp-fill-valid__intro">
				<h2><?php echo function_exists( 'cxp_icon' ) ? cxp_icon( 'map-pin' ) : ''; ?> Elige un destino real</h2>
				<p>Calles y comunas que existen en la RM. El nombre de comuna es el de Chilexpress (ej. <code>NUNOA</code>, <code>SANTIAGO CENTRO</code>). <strong>Usar dirección</strong> llena el formulario. <strong>Cotizar envío</strong> llama al cotizador (PREX, CHEX y precio).</p>
			</div>
			<ul class="cxp-addr-list">
				<?php foreach ( $addresses as $addr ) : ?>
				<li class="cxp-addr" data-addr="<?php echo esc_attr( $addr['id'] ); ?>">
					<div class="cxp-addr__meta">
						<?php echo function_exists( 'cxp_icon' ) ? cxp_icon( 'house' ) : ''; ?>
						<strong><?php echo esc_html( $addr['first_name'] . ' ' . $addr['last_name'] ); ?></strong>
						<span class="cxp-addr__city"><?php echo esc_html( $addr['city_label'] ?? $addr['city'] ); ?></span>
						<span class="cxp-addr__street"><?php echo esc_html( $addr['address_1'] . ' ' . $addr['address_2'] ); ?></span>
						<em class="cxp-addr__hint"><?php echo esc_html( $addr['hint'] ?? ( $addr['city'] . ' · ' . $addr['county_code'] ) ); ?></em>
					</div>
					<div class="cxp-addr__actions">
						<button type="button" class="cxp-fill-addr" data-addr="<?php echo esc_attr( $addr['id'] ); ?>"><?php echo function_exists( 'cxp_icon' ) ? cxp_icon( 'file-text' ) : ''; ?> Usar dirección</button>
						<button type="button" class="cxp-probe-addr" data-addr="<?php echo esc_attr( $addr['id'] ); ?>"><?php echo function_exists( 'cxp_icon' ) ? cxp_icon( 'truck' ) : ''; ?> Cotizar envío</button>
					</div>
					<pre class="cxp-probe-out" hidden></pre>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	},
	5
);

add_action(
	'woocommerce_after_checkout_billing_form',
	static function () {
		?>
		<div class="cxp-step-actions">
			<button type="button" class="cxp-go-payment" data-cxp-step="2">Continuar al pago →</button>
		</div>
		<?php
	}
);

add_action(
	'woocommerce_review_order_before_payment',
	static function () {
		?>
		<aside class="cxp-pay-summary" aria-live="polite">
			<h3>Resumen de tus datos</h3>
			<dl>
				<div><dt>Cliente</dt><dd class="js-sum-name">—</dd></div>
				<div><dt>Correo</dt><dd class="js-sum-email">—</dd></div>
				<div><dt>Dirección</dt><dd class="js-sum-addr">—</dd></div>
				<div><dt>Envío elegido</dt><dd class="js-sum-ship">Elige un radio de Chilexpress</dd></div>
			</dl>
			<?php
			if ( function_exists( 'cxp_storefront_cart_dims_table' ) ) {
				echo cxp_storefront_cart_dims_table(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
			<button type="button" class="cxp-go-data" data-cxp-step="1">← Volver a datos</button>
		</aside>
		<?php
	},
	5
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		wp_enqueue_script(
			'cxp-fill-valid',
			content_url( 'mu-plugins/cxp-checkout-debug/fill.js' ),
			array( 'jquery' ),
			'1.8.0',
			true
		);
		wp_localize_script(
			'cxp-fill-valid',
			'cxpCheckoutLab',
			array(
				'ajax'      => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'cxp_probe_rate' ),
				'addresses' => cxp_checkout_debug_quote_addresses(),
			)
		);
	},
	30
);

add_action( 'wp_ajax_cxp_probe_rate', 'cxp_checkout_debug_ajax_probe_rate' );
add_action( 'wp_ajax_nopriv_cxp_probe_rate', 'cxp_checkout_debug_ajax_probe_rate' );

function cxp_checkout_debug_quote_addresses() {
	$email = function_exists( 'cxp_lab_email' ) ? cxp_lab_email() : 'alexander.cautivo+testwordpress@aeolabs.io';
	$base  = array(
		'email'      => $email,
		'phone'      => '912345678',
		'country'    => 'CL',
		'state'      => 'RM',
		'postcode'   => '',
		'complement' => 'Casa',
	);
	// city + county_code = coverageName / countyCode de chilexpress-oficial/includes/data/comunas/RM.json
	// Calles y números reales de la RM para que el formulario y el cotizador coincidan.
	$list = array(
		array(
			'id'          => 'lareina',
			'first_name'  => 'Juan',
			'last_name'   => 'Espoz',
			'city'        => 'LA REINA',
			'city_label'  => 'La Reina',
			'county_code' => 'LARE',
			'address_1'   => 'Avenida Larrain',
			'address_2'   => '5862',
			'hint'        => 'Plaza La Reina. Comuna oficial LA REINA · LARE.',
		),
		array(
			'id'          => 'providencia',
			'first_name'  => 'Ana',
			'last_name'   => 'Soto',
			'city'        => 'PROVIDENCIA',
			'city_label'  => 'Providencia',
			'county_code' => 'PROV',
			'address_1'   => 'Avenida Providencia',
			'address_2'   => '2124',
			'hint'        => 'Metro Manuel Montt. Comuna oficial PROVIDENCIA · PROV.',
		),
		array(
			'id'          => 'lascondes',
			'first_name'  => 'Pedro',
			'last_name'   => 'Rivas',
			'city'        => 'LAS CONDES',
			'city_label'  => 'Las Condes',
			'county_code' => 'LCON',
			'address_1'   => 'Avenida Apoquindo',
			'address_2'   => '4501',
			'hint'        => 'Escuela Militar. Comuna oficial LAS CONDES · LCON.',
		),
		array(
			'id'          => 'nunoa',
			'first_name'  => 'Camila',
			'last_name'   => 'Diaz',
			'city'        => 'NUNOA',
			'city_label'  => 'Nunoa',
			'county_code' => 'NUNO',
			'address_1'   => 'Avenida Irarrazaval',
			'address_2'   => '3250',
			'hint'        => 'Plaza Nunoa. Comuna oficial NUNOA · NUNO (sin eñe, asi lo pide Chilexpress).',
		),
		array(
			'id'          => 'puentealto',
			'first_name'  => 'Diego',
			'last_name'   => 'Munoz',
			'city'        => 'PUENTE ALTO',
			'city_label'  => 'Puente Alto',
			'county_code' => 'PALT',
			'address_1'   => 'Avenida Concha y Toro',
			'address_2'   => '2986',
			'hint'        => 'Plaza de Puente Alto. Comuna oficial PUENTE ALTO · PALT.',
		),
		array(
			'id'          => 'santiago',
			'first_name'  => 'Laura',
			'last_name'   => 'Reyes',
			'city'        => 'SANTIAGO CENTRO',
			'city_label'  => 'Santiago Centro',
			'county_code' => 'STGO',
			'address_1'   => 'Avenida Libertador Bernardo O Higgins',
			'address_2'   => '1140',
			'hint'        => 'Alameda frente a La Moneda. Comuna oficial SANTIAGO CENTRO · STGO.',
		),
		array(
			'id'          => 'maipu',
			'first_name'  => 'Andres',
			'last_name'   => 'Perez',
			'city'        => 'MAIPU',
			'city_label'  => 'Maipu',
			'county_code' => 'MIPU',
			'address_1'   => 'Avenida Pajaritos',
			'address_2'   => '3209',
			'hint'        => 'Mall Arauco Maipu. Comuna oficial MAIPU · MIPU (no MAIP).',
		),
		array(
			'id'          => 'vitacura',
			'first_name'  => 'Sofia',
			'last_name'   => 'Hahn',
			'city'        => 'VITACURA',
			'city_label'  => 'Vitacura',
			'county_code' => 'VITA',
			'address_1'   => 'Avenida Vitacura',
			'address_2'   => '2670',
			'hint'        => 'Parque Bicentenario. Comuna oficial VITACURA · VITA.',
		),
	);
	$out = array();
	foreach ( $list as $row ) {
		$out[ $row['id'] ] = array_merge( $base, $row );
	}
	return $out;
}

function cxp_checkout_debug_customer_data() {
	$addresses = cxp_checkout_debug_quote_addresses();
	return $addresses['lareina'];
}

function cxp_checkout_debug_probe_package() {
	$general = get_option( 'chilexpress_woo_oficial_general', array() );
	$pkg     = array(
		'weight'   => (float) ( $general['peso_producto_defecto'] ?? 0.8 ),
		'height'   => (float) ( $general['alto_producto_defecto'] ?? 15 ),
		'width'    => (float) ( $general['ancho_producto_defecto'] ?? 20 ),
		'length'   => (float) ( $general['largo_producto_defecto'] ?? 10 ),
		'declared' => 19990,
	);
	if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
		return $pkg;
	}
	$biggest = 0;
	$weight  = 0.0;
	$price   = 0.0;
	foreach ( WC()->cart->get_cart() as $item ) {
		$product = $item['data'] ?? null;
		if ( ! $product instanceof WC_Product ) {
			continue;
		}
		$qty    = (float) ( $item['quantity'] ?? 1 );
		$height = (float) ( $product->get_height() !== '' ? $product->get_height() : $pkg['height'] );
		$width  = (float) ( $product->get_width() !== '' ? $product->get_width() : $pkg['width'] );
		$length = (float) ( $product->get_length() !== '' ? $product->get_length() : $pkg['length'] );
		$size   = $height * $width * $length;
		if ( $size > $biggest ) {
			$biggest       = $size;
			$pkg['height'] = $height;
			$pkg['width']  = $width;
			$pkg['length'] = $length;
		}
		$item_weight = $product->get_weight() !== '' ? (float) $product->get_weight() : $pkg['weight'];
		$weight     += $item_weight * $qty;
		$price      += (float) $product->get_price() * $qty;
	}
	if ( $weight > 0 ) {
		$pkg['weight'] = $weight;
	}
	if ( $price > 0 ) {
		$pkg['declared'] = $price;
	}
	return $pkg;
}

function cxp_checkout_debug_ajax_probe_rate() {
	check_ajax_referer( 'cxp_probe_rate', 'nonce' );

	$id        = sanitize_key( wp_unslash( $_POST['addr'] ?? '' ) );
	$addresses = cxp_checkout_debug_quote_addresses();
	if ( ! isset( $addresses[ $id ] ) ) {
		wp_send_json_error( array( 'message' => 'Dirección no reconocida.' ), 400 );
	}

	$addr = $addresses[ $id ];
	if ( ! class_exists( 'Chilexpress_Woo_Oficial_API' ) ) {
		$api_file = WP_PLUGIN_DIR . '/chilexpress-oficial/includes/class-chilexpress-woo-oficial-api.php';
		if ( is_readable( $api_file ) ) {
			require_once $api_file;
		}
	}
	if ( ! class_exists( 'Chilexpress_Woo_Oficial_API' ) ) {
		wp_send_json_error(
			array(
				'quote_ready' => false,
				'message'     => 'No está cargada la API de Chilexpress Oficial.',
			)
		);
	}

	$general  = get_option( 'chilexpress_woo_oficial_general', array() );
	$origin   = (string) ( $general['comuna_origen'] ?? 'PROV' );
	$priority = 0;
	$tcc      = $general['numero_tcc_origen'] ?? '18578680';
	$pkg      = cxp_checkout_debug_probe_package();
	$api      = new Chilexpress_Woo_Oficial_API();
	$response = $api->obtener_cotizacion(
		$origin,
		$addr['county_code'],
		$priority,
		$tcc,
		$pkg['weight'],
		$pkg['height'],
		$pkg['width'],
		$pkg['length'],
		$pkg['declared']
	);

	if ( is_wp_error( $response ) ) {
		wp_send_json_error(
			array(
				'quote_ready' => false,
				'origin'      => $origin,
				'destination' => $addr['county_code'],
				'city'        => $addr['city'],
				'package'     => $pkg,
				'message'     => $response->get_error_message(),
			)
		);
	}

	$services = array();
	foreach ( (array) $response as $opt ) {
		if ( ! is_object( $opt ) ) {
			continue;
		}
		$services[] = array(
			'code'           => $opt->serviceTypeCode ?? '',
			'name'           => $opt->serviceDescription ?? '',
			'price'          => $opt->serviceValue ?? '',
			'price_discount' => $opt->serviceValueDiscount ?? '',
		);
	}

	$ready = false;
	foreach ( $services as $svc ) {
		if ( '' !== (string) $svc['name'] && '' !== (string) $svc['price_discount'] && '' !== (string) $svc['code'] ) {
			$ready = true;
			break;
		}
	}

	$chosen = sanitize_text_field( wp_unslash( $_POST['chosen'] ?? '' ) );
	if ( $ready ) {
		cxp_checkout_debug_apply_customer( $addr );
		cxp_checkout_debug_store_quote( $addr, $services, $chosen );
	}

	wp_send_json_success(
		array(
			'quote_ready' => $ready,
			'addr'        => $id,
			'origin'      => $origin,
			'destination' => $addr['county_code'],
			'city'        => $addr['city'],
			'street'      => $addr['address_1'] . ' ' . $addr['address_2'],
			'package'     => $pkg,
			'services'    => $services,
			'message'     => $ready
				? sprintf( 'Elige un radio: la API trajo %d servicio(s) con precio.', count( $services ) )
				: 'La API respondió, pero faltan serviceTypeCode / serviceDescription / serviceValueDiscount.',
		)
	);
}

function cxp_checkout_debug_apply_customer( $addr ) {
	if ( ! function_exists( 'WC' ) || ! WC()->customer ) {
		return;
	}
	$customer = WC()->customer;
	foreach ( array( 'billing', 'shipping' ) as $group ) {
		$customer->{"set_{$group}_first_name"}( $addr['first_name'] );
		$customer->{"set_{$group}_last_name"}( $addr['last_name'] );
		$customer->{"set_{$group}_country"}( $addr['country'] );
		$customer->{"set_{$group}_state"}( $addr['state'] );
		$customer->{"set_{$group}_city"}( $addr['city'] );
		$customer->{"set_{$group}_address_1"}( $addr['address_1'] );
		$customer->{"set_{$group}_address_2"}( $addr['address_2'] );
		$customer->{"set_{$group}_phone"}( $addr['phone'] );
	}
	$customer->set_billing_email( $addr['email'] );
	$customer->set_calculated_shipping( false );
	$customer->save();
	if ( WC()->session ) {
		foreach ( array_keys( (array) WC()->session->get_session_data() ) as $key ) {
			if ( 0 === strpos( (string) $key, 'shipping_for_package' ) ) {
				WC()->session->set( $key, null );
			}
		}
	}
	if ( WC()->shipping() ) {
		WC()->shipping()->reset_shipping();
	}
}

function cxp_checkout_debug_store_quote( $addr, $services, $chosen = '' ) {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	if ( '' === $chosen && ! empty( $services[0]['code'] ) ) {
		$chosen = (string) $services[0]['code'];
	}
	WC()->session->set(
		'cxp_api_quote',
		array(
			'city'        => $addr['city'],
			'county_code' => $addr['county_code'],
			'services'    => $services,
			'chosen'      => $chosen,
		)
	);
	if ( $chosen ) {
		WC()->session->set( 'chosen_shipping_methods', array( 'chilexpress_woo_oficial:' . $chosen ) );
	}
}

add_filter(
	'woocommerce_package_rates',
	static function ( $rates, $package ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return $rates;
		}
		$quote = WC()->session->get( 'cxp_api_quote' );
		if ( empty( $quote['services'] ) || ! is_array( $quote['services'] ) ) {
			return $rates;
		}
		$city = strtoupper( (string) ( $package['destination']['city'] ?? '' ) );
		$want = strtoupper( (string) ( $quote['city'] ?? '' ) );
		if ( $want && $city && $city !== $want && 'ALHUE' !== $city && 'ALHU' !== $city ) {
			return $rates;
		}
		$out = array();
		foreach ( $quote['services'] as $svc ) {
			$code  = (string) ( $svc['code'] ?? '' );
			$label = (string) ( $svc['name'] ?? '' );
			$cost  = (float) ( $svc['price_discount'] !== '' ? $svc['price_discount'] : ( $svc['price'] ?? 0 ) );
			if ( '' === $code || '' === $label ) {
				continue;
			}
			$id   = 'chilexpress_woo_oficial:' . $code;
			$rate = new WC_Shipping_Rate( $id, 'Chilexpress - ' . $label, $cost, array(), 'chilexpress_woo_oficial' );
			$rate->add_meta_data( 'serviceTypeCode', $code );
			$out[ $id ] = $rate;
		}
		return $out ? $out : $rates;
	},
	30,
	2
);

add_action(
	'woocommerce_checkout_update_order_review',
	static function ( $posted ) {
		parse_str( (string) $posted, $data );
		$chosen = $data['shipping_method'][0] ?? '';
		if ( ! $chosen || ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$quote = WC()->session->get( 'cxp_api_quote' );
		if ( ! is_array( $quote ) ) {
			return;
		}
		if ( preg_match( '/:(\d+)$/', (string) $chosen, $m ) ) {
			$quote['chosen'] = $m[1];
			WC()->session->set( 'cxp_api_quote', $quote );
		}
	}
);

function cxp_checkout_debug_prefill_customer() {
	if ( ! function_exists( 'WC' ) || ! WC()->customer ) {
		return;
	}
	$customer = WC()->customer;
	$city     = strtoupper( (string) $customer->get_shipping_city() );
	$city     = str_replace( array( 'Á', 'É', 'Í', 'Ó', 'Ú' ), array( 'A', 'E', 'I', 'O', 'U' ), $city );
	$state    = (string) $customer->get_shipping_state();
	$street   = (string) $customer->get_billing_address_1();
	$broken   = '' === $city
		|| in_array( $city, array( 'ALHUE', 'ALHU', 'SANTIAGO' ), true )
		|| false !== stripos( $street, 'benito' )
		|| false !== stripos( $city, 'ARICA' )
		|| ( 'LA REINA' !== $city && 'RM' === $state && false !== stripos( $city, 'PEDRO' ) );
	if ( ! $broken ) {
		return;
	}

	$data = cxp_checkout_debug_customer_data();
	foreach ( array( 'billing', 'shipping' ) as $group ) {
		$customer->{"set_{$group}_first_name"}( $data['first_name'] );
		$customer->{"set_{$group}_last_name"}( $data['last_name'] );
		$customer->{"set_{$group}_country"}( $data['country'] );
		$customer->{"set_{$group}_state"}( $data['state'] );
		$customer->{"set_{$group}_city"}( $data['city'] );
		$customer->{"set_{$group}_address_1"}( $data['address_1'] );
		$customer->{"set_{$group}_address_2"}( $data['address_2'] );
		$customer->{"set_{$group}_postcode"}( $data['postcode'] );
		$customer->{"set_{$group}_phone"}( $data['phone'] );
	}
	$customer->set_billing_email( $data['email'] );
	$customer->set_calculated_shipping( false );
	$customer->save();

	if ( WC()->session ) {
		WC()->session->set( 'cxp_addr_prefilled_v6', '1' );
		WC()->session->set( 'chosen_shipping_methods', array() );
		if ( method_exists( WC()->session, 'get_session_data' ) ) {
			foreach ( array_keys( (array) WC()->session->get_session_data() ) as $key ) {
				if ( 0 === strpos( (string) $key, 'shipping_for_package' ) ) {
					WC()->session->set( $key, null );
				}
			}
		}
	}
	if ( WC()->shipping() ) {
		WC()->shipping()->reset_shipping();
	}
}

add_filter( 'default_checkout_billing_city', 'cxp_checkout_debug_default_city' );
add_filter( 'default_checkout_shipping_city', 'cxp_checkout_debug_default_city' );
add_filter( 'default_checkout_billing_state', 'cxp_checkout_debug_default_state' );
add_filter( 'default_checkout_shipping_state', 'cxp_checkout_debug_default_state' );

function cxp_checkout_debug_default_city( $value ) {
	$city = strtoupper( (string) $value );
	$city = str_replace( array( 'Á', 'É', 'Í', 'Ó', 'Ú' ), array( 'A', 'E', 'I', 'O', 'U' ), $city );
	if ( '' === $city || in_array( $city, array( 'ALHUE', 'ALHU' ), true ) ) {
		return 'LA REINA';
	}
	return $value;
}

function cxp_checkout_debug_default_state( $value ) {
	return $value ? $value : 'RM';
}

function cxp_checkout_debug_ensure_cart_item() {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
		return;
	}
	if ( ! WC()->cart || ! WC()->cart->is_empty() ) {
		return;
	}
	$product = get_page_by_path( 'teclado-mecanico', OBJECT, 'product' );
	if ( $product ) {
		WC()->cart->add_to_cart( $product->ID, 1 );
	}
}

function cxp_checkout_debug_page( $title, $content ) {
	$pages = get_posts(
		array(
			'post_type'              => 'page',
			'title'                  => $title,
			'post_status'            => 'publish',
			'numberposts'            => 1,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
		)
	);
	if ( $pages ) {
		return (int) $pages[0]->ID;
	}
	return (int) wp_insert_post(
		array(
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
		)
	);
}

function cxp_checkout_use_classic_pages() {
	if ( ! function_exists( 'wc_get_page_id' ) ) {
		return;
	}
	$map = array(
		'cart'     => '[woocommerce_cart]',
		'checkout' => '[woocommerce_checkout]',
	);
	foreach ( $map as $which => $shortcode ) {
		$id = wc_get_page_id( $which );
		if ( $id <= 0 ) {
			continue;
		}
		$page = get_post( $id );
		if ( ! $page ) {
			continue;
		}
		if ( false !== strpos( (string) $page->post_content, $shortcode ) && false === strpos( (string) $page->post_content, 'wp:woocommerce/checkout' ) && false === strpos( (string) $page->post_content, 'wp:woocommerce/cart' ) ) {
			continue;
		}
		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => $shortcode,
			)
		);
	}
}

function cxp_checkout_force_oficial_shipping() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	cxp_checkout_use_classic_pages();

	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id = 'cxp_debug_cxp'" );

	if ( class_exists( 'WC_Shipping_Zones' ) ) {
		cxp_checkout_debug_ensure_zone();
	}
}

function cxp_checkout_debug_ensure_zone() {
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
		if ( 'chilexpress_woo_oficial' === $method->id || 'Chilexpress_Woo_Oficial_Shipping_Method' === get_class( $method ) ) {
			$has_oficial = true;
			break;
		}
	}
	if ( ! $has_oficial ) {
		$zone->add_shipping_method( 'chilexpress_woo_oficial' );
	}
}

add_filter(
	'woocommerce_shipping_methods',
	static function ( $methods ) {
		if ( class_exists( 'Chilexpress_Woo_Oficial_Shipping_Method' ) ) {
			$methods['chilexpress_woo_oficial'] = 'Chilexpress_Woo_Oficial_Shipping_Method';
		}
		unset( $methods['cxp_debug_cxp'] );
		return $methods;
	},
	30
);

function cxp_checkout_debug_configure() {
	if ( get_option( 'cxp_checkout_debug_configured' ) === '5' ) {
		return;
	}
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$email = function_exists( 'cxp_lab_email' ) ? cxp_lab_email() : 'alexander.cautivo+testwordpress@aeolabs.io';
	update_option( 'admin_email', $email );
	$user = get_user_by( 'login', 'admin' );
	if ( $user ) {
		wp_update_user(
			array(
				'ID'         => $user->ID,
				'user_email' => $email,
			)
		);
	}
	foreach ( array( 'woocommerce_new_order_settings', 'woocommerce_cancelled_order_settings', 'woocommerce_failed_order_settings' ) as $opt ) {
		$settings = get_option( $opt, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings['enabled']   = $settings['enabled'] ?? 'yes';
		$settings['recipient'] = $email;
		update_option( $opt, $settings );
	}

	$terms_id   = cxp_checkout_debug_page(
		'Términos y condiciones',
		'<p>Términos de esta tienda de prueba Chilexpress. Solo ambiente local de debug.</p>'
	);
	$privacy_id = cxp_checkout_debug_page(
		'Política de privacidad',
		'<p>Política de privacidad de la tienda de prueba. No se procesan pagos reales.</p>'
	);
	update_option( 'woocommerce_terms_page_id', $terms_id );
	update_option( 'wp_page_for_privacy_policy', $privacy_id );
	update_option( 'woocommerce_checkout_privacy_policy_text', 'Al proceder con tu compra aceptas nuestros [terms] y [privacy_policy]' );
	update_option( 'woocommerce_enable_order_comments', 'yes' );
	update_option( 'woocommerce_enable_guest_checkout', 'yes' );
	update_option( 'woocommerce_enable_checkout_login_reminder', 'no' );
	update_option( 'woocommerce_ship_to_destination', 'billing' );
	update_option( 'woocommerce_calc_shipping', 'yes' );
	update_option( 'woocommerce_enable_shipping_calc', 'yes' );
	update_option( 'woocommerce_shipping_cost_requires_address', 'yes' );

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
	update_option(
		'woocommerce_cxp_test_card_settings',
		array(
			'enabled'     => 'yes',
			'title'       => 'Tarjeta de crédito (prueba)',
			'description' => 'Tarjeta falsa de debug. Número: 4242 4242 4242 4242 · Vence 12/34 · CVC 123. Siempre aprueba. No es un pago real.',
		)
	);

	cxp_checkout_use_classic_pages();
	cxp_checkout_debug_ensure_zone();
	update_option( 'cxp_checkout_debug_configured', '5' );
}
