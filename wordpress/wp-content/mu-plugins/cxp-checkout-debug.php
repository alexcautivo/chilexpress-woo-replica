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
					'Tarjeta falsa de debug. Número: 4242 4242 4242 4242 · Vence 12/34 · CVC 123. Siempre aprueba.'
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
		$data = cxp_checkout_debug_customer_data();
		?>
		<div class="cxp-fill-valid" role="region" aria-label="Datos de prueba Chilexpress">
			<p>Llena el checkout con datos válidos para cotizar Chilexpress: <?php echo esc_html( $data['first_name'] . ' ' . $data['last_name'] ); ?>, <?php echo esc_html( $data['state'] ); ?> / <?php echo esc_html( $data['city'] ); ?>, <?php echo esc_html( $data['address_1'] . ' ' . $data['address_2'] ); ?>.</p>
			<button type="button" id="cxp-fill-valid">Llenar datos válidos</button>
		</div>
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
			'1.0.0',
			true
		);
		wp_localize_script( 'cxp-fill-valid', 'cxpFillAddress', cxp_checkout_debug_customer_data() );
	},
	30
);

function cxp_checkout_debug_customer_data() {
	if ( function_exists( 'cxp_chilexpress_seed_destination' ) ) {
		return cxp_chilexpress_seed_destination();
	}
	return array(
		'first_name' => 'Juan',
		'last_name'  => 'Espoz',
		'email'      => function_exists( 'cxp_lab_email' ) ? cxp_lab_email() : 'alexander.cautivo+testwordpress@aeolabs.io',
		'country'    => 'CL',
		'state'      => 'RM',
		'city'       => 'LA REINA',
		'address_1'  => 'Avenida Larrain',
		'address_2'  => '5862',
		'postcode'   => '',
		'phone'      => '912345678',
	);
}

function cxp_checkout_debug_prefill_customer() {
	if ( ! function_exists( 'WC' ) || ! WC()->customer ) {
		return;
	}
	$customer = WC()->customer;
	$city     = strtoupper( (string) $customer->get_shipping_city() );
	$state    = (string) $customer->get_shipping_state();
	$street   = (string) $customer->get_billing_address_1();
	$broken   = false !== stripos( $street, 'benito' )
		|| false !== stripos( $city, 'ARICA' )
		|| ( '' !== $city && 'LA REINA' !== $city && 'RM' === $state && false !== stripos( $city, 'PEDRO' ) );
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
		WC()->session->set( 'cxp_addr_prefilled_v5', '1' );
		WC()->session->set( 'chosen_shipping_methods', array() );
	}
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
