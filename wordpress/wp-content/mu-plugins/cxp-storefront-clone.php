<?php
/**
 * Plugin Name: Chilexpress storefront clone
 * Version: 1.0.0
 * Description: Replica el diseño público de la tienda de prueba Chilexpress (topbar, logo, hero y catálogo) sin modificar el plugin oficial 1.4.0.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'body_class',
	static function ( $classes ) {
		if ( is_admin() ) {
			return $classes;
		}
		$classes[] = 'cxp-storefront-enhanced';
		if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() || is_front_page() || is_home() ) ) {
			$classes[] = 'cxp-storefront-catalog';
		}
		if ( function_exists( 'is_product' ) && is_product() ) {
			$classes[] = 'cxp-storefront-product';
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			$classes[] = 'cxp-storefront-cart';
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$classes[] = 'cxp-storefront-checkout';
		}
		if ( ! cxp_storefront_can_open_cart() ) {
			$classes[] = 'cxp-cart-locked';
		}
		return $classes;
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		$base = WP_PLUGIN_DIR . '/chilexpress-oficial/public';
		$url  = plugins_url( 'chilexpress-oficial/public' );
		if ( ! is_readable( $base . '/css/chilexpress-woo-oficial-storefront.css' ) ) {
			return;
		}

		wp_enqueue_style(
			'chilexpress-woo-oficial-storefront',
			$url . '/css/chilexpress-woo-oficial-storefront.css',
			array(),
			'1.4.0'
		);
		wp_enqueue_script(
			'chilexpress-woo-oficial-storefront',
			$url . '/js/chilexpress-woo-oficial-storefront.js',
			array(),
			'1.4.0',
			true
		);
		wp_localize_script(
			'chilexpress-woo-oficial-storefront',
			'chilexpressStorefront',
			array(
				'logoUrl'    => $url . '/imgs/logo-chilexpress.svg',
				'faviconUrl' => $url . '/imgs/favicon.ico',
				'pageTitle'  => 'Chilexpress WooCommerce Test',
				'homeUrl'    => home_url( '/' ),
				'labels'     => array(
					'topbar'       => 'Ambiente de prueba · Integración Chilexpress',
					'topbarAria'   => 'Ambiente de la tienda',
					'logoAria'     => 'Chilexpress',
					'heroTitle'    => 'Todo lo que necesitas, entregado con Chilexpress',
					'heroText'     => 'Explora el catálogo de esta tienda de prueba y encuentra tus productos.',
					'heroCta'      => 'Ver productos',
					'coverage'     => 'Cobertura nacional',
					'tracking'     => 'Seguimiento de envíos',
					'fastDelivery' => 'Entregas rápidas',
				),
			)
		);

		wp_enqueue_style(
			'cxp-checkout-cxp',
			content_url( 'mu-plugins/cxp-checkout-debug/checkout-cxp.css' ),
			array( 'chilexpress-woo-oficial-storefront' ),
			'1.8.3'
		);

		wp_enqueue_script(
			'cxp-storefront-qty',
			content_url( 'mu-plugins/cxp-checkout-debug/storefront.js' ),
			array( 'jquery' ),
			'1.8.0',
			true
		);
		wp_localize_script(
			'cxp-storefront-qty',
			'cxpStorefront',
			array(
				'maxQty'     => 10,
				'canCart'    => cxp_storefront_can_open_cart() ? 1 : 0,
				'checkout'   => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' ),
				'cartLocked' => 'Primero pasa por el checkout. El carrito se habilita después.',
			)
		);

		wp_add_inline_script(
			'chilexpress-woo-oficial-storefront',
			<<<'JS'
(function () {
  function brandWoodmart() {
    document.body.classList.add('cxp-storefront-branded');
    var misplaced = document.querySelectorAll('header.whb-header > a.cxp-storefront-logo');
    Array.prototype.forEach.call(misplaced, function (el) {
      el.parentNode.removeChild(el);
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', brandWoodmart);
  } else {
    brandWoodmart();
  }
})();
JS
		);

		if ( function_exists( 'WC' ) ) {
			wp_enqueue_script( 'wc-add-to-cart' );
			wp_enqueue_script( 'wc-cart-fragments' );
		}
	},
	20
);

add_action(
	'cxp_after_header',
	static function () {
		if ( is_admin() ) {
			return;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return;
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return;
		}
		if ( ! function_exists( 'is_shop' ) || ! ( is_shop() || is_front_page() || is_home() ) ) {
			return;
		}
		$shop     = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
		$checkout = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
		?>
		<section class="cxp-catalog-hero cxp-catalog-hero--lab" data-cxp-fallback="true" id="cxp-product-catalog">
			<div class="cxp-catalog-hero__inner">
				<p class="cxp-catalog-hero__kicker"><?php echo function_exists( 'cxp_icon' ) ? cxp_icon( 'truck' ) : ''; ?> Envíos Chilexpress · toda Chile</p>
				<h1>Tecnología lista para despachar</h1>
				<p class="cxp-catalog-hero__text">Elige uno o varios productos (hasta 10 de cada uno), revisa peso y medidas, y cotiza en el checkout con direcciones reales de la Región Metropolitana.</p>
				<div class="cxp-catalog-hero__actions">
					<a class="cxp-catalog-hero__cta" href="<?php echo esc_url( $shop ); ?>#cxp-shop-grid"><?php echo function_exists( 'cxp_icon' ) ? cxp_icon( 'package' ) : ''; ?> Ver catálogo</a>
					<a class="cxp-catalog-hero__cta cxp-catalog-hero__cta--checkout" href="<?php echo esc_url( $checkout ); ?>"><?php echo function_exists( 'cxp_icon' ) ? cxp_icon( 'credit-card' ) : ''; ?> Ir al checkout</a>
				</div>
			</div>
		</section>
		<section class="cxp-catalog-benefits" data-cxp-fallback="true">
			<div class="cxp-catalog-benefits__inner">
				<div class="cxp-catalog-benefit">
					<span class="cxp-catalog-benefit__mark" aria-hidden="true"><?php echo function_exists( 'cxp_icon' ) ? cxp_icon( 'map-pin' ) : ''; ?></span>
					<strong>Cobertura nacional</strong>
					<span>Direcciones reales de la RM</span>
				</div>
				<div class="cxp-catalog-benefit">
					<span class="cxp-catalog-benefit__mark" aria-hidden="true"><?php echo function_exists( 'cxp_icon' ) ? cxp_icon( 'truck' ) : ''; ?></span>
					<strong>Cotización en vivo</strong>
					<span>PREX, CHEX y más servicios</span>
				</div>
				<div class="cxp-catalog-benefit">
					<span class="cxp-catalog-benefit__mark" aria-hidden="true"><?php echo function_exists( 'cxp_icon' ) ? cxp_icon( 'package' ) : ''; ?></span>
					<strong>Hasta 10 por producto</strong>
					<span>Peso y medidas ya cargados</span>
				</div>
			</div>
		</section>
		<?php
	}
);

function cxp_storefront_format_dims( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return '';
	}
	$weight = $product->get_weight();
	$length = $product->get_length();
	$width  = $product->get_width();
	$height = $product->get_height();
	if ( '' === $weight && '' === $length && '' === $width && '' === $height ) {
		return '';
	}
	$wu = get_option( 'woocommerce_weight_unit', 'kg' );
	$du = get_option( 'woocommerce_dimension_unit', 'cm' );
	return sprintf(
		'%s %s · %s × %s × %s %s',
		'' !== $weight ? wc_format_localized_decimal( $weight ) : '—',
		$wu,
		'' !== $length ? wc_format_localized_decimal( $length ) : '—',
		'' !== $width ? wc_format_localized_decimal( $width ) : '—',
		'' !== $height ? wc_format_localized_decimal( $height ) : '—',
		$du
	);
}

function cxp_storefront_dims_markup( $product, $context = 'card' ) {
	$line = cxp_storefront_format_dims( $product );
	if ( '' === $line ) {
		return '';
	}
	$class = 'card' === $context ? 'cxp-product-dims' : 'cxp-product-dims cxp-product-dims--' . sanitize_html_class( $context );
	$html  = '<div class="' . esc_attr( $class ) . '"><span>Peso y medidas</span><strong>' . esc_html( $line ) . '</strong>';
	$caso  = function_exists( 'cxp_wiki_catalog_caso' ) ? cxp_wiki_catalog_caso( $product ) : '';
	if ( $caso && 'single' === $context ) {
		$html .= '<em>' . esc_html( $caso ) . '</em>';
	}
	$html .= '</div>';
	return $html;
}

function cxp_storefront_cart_dims_table() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
		return '';
	}
	ob_start();
	?>
	<table class="cxp-dims-table">
		<caption>Detalle del bulto</caption>
		<thead>
			<tr>
				<th>Producto</th>
				<th>Precio</th>
				<th>Peso</th>
				<th>Medidas</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( WC()->cart->get_cart() as $item ) : ?>
			<?php
			$product = $item['data'] ?? null;
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			$qty    = (int) ( $item['quantity'] ?? 1 );
			$weight = $product->get_weight();
			$length = $product->get_length();
			$width  = $product->get_width();
			$height = $product->get_height();
			$dims   = implode(
				'×',
				array(
					'' !== $length ? wc_format_localized_decimal( $length ) : '—',
					'' !== $width ? wc_format_localized_decimal( $width ) : '—',
					'' !== $height ? wc_format_localized_decimal( $height ) : '—',
				)
			);
			$peso = '' !== $weight ? wc_format_localized_decimal( $weight ) . ' kg' : '—';
			?>
			<tr>
				<td><?php echo esc_html( $product->get_name() . ( $qty > 1 ? ' × ' . $qty : '' ) ); ?></td>
				<td><?php echo wp_kses_post( WC()->cart->get_product_subtotal( $product, $qty ) ); ?></td>
				<td><?php echo esc_html( $peso ); ?></td>
				<td><?php echo esc_html( $dims ); ?> cm</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
	return ob_get_clean();
}

function cxp_storefront_echo_loop_dims() {
	static $done = array();
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$id = $product->get_id();
	if ( isset( $done[ $id ] ) ) {
		return;
	}
	$done[ $id ] = true;
	echo cxp_storefront_dims_markup( $product, 'card' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

add_action( 'woocommerce_after_shop_loop_item_title', 'cxp_storefront_echo_loop_dims', 8 );
add_action( 'woocommerce_after_shop_loop_item', 'cxp_storefront_echo_loop_dims', 6 );

add_action(
	'woocommerce_single_product_summary',
	static function () {
		global $product;
		echo cxp_storefront_dims_markup( $product, 'single' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	},
	25
);

add_filter(
	'woocommerce_cart_item_name',
	static function ( $name, $cart_item ) {
		$product = $cart_item['data'] ?? null;
		$line    = cxp_storefront_format_dims( $product );
		if ( '' === $line ) {
			return $name;
		}
		$html = $name . '<div class="cxp-product-dims cxp-product-dims--inline"><span>Peso y medidas</span> <strong>' . esc_html( $line ) . '</strong></div>';
		return $html;
	},
	20,
	2
);

add_action(
	'woocommerce_before_cart_table',
	static function () {
		echo cxp_storefront_cart_dims_table(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
);

add_action(
	'woocommerce_review_order_before_cart_contents',
	static function () {
		echo '<tr class="cxp-dims-row"><td colspan="2">' . cxp_storefront_cart_dims_table() . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
);

function cxp_storefront_cart_count_html() {
	$count = function_exists( 'WC' ) && WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
	return '<span class="wd-tools-count">' . esc_html( (string) $count ) . '</span>';
}

function cxp_storefront_checkout_bar_html() {
	$count    = function_exists( 'WC' ) && WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
	$total    = function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_subtotal() : '';
	$checkout = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
	$can_cart = cxp_storefront_can_open_cart();
	$cart     = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
	ob_start();
	?>
	<div class="cxp-checkout-bar" role="region" aria-label="Ir al checkout">
		<div class="cxp-checkout-bar__info">
			<strong><?php echo esc_html( sprintf( _n( '%d producto', '%d productos', $count, 'cxp' ), $count ) ); ?></strong>
			<span><?php echo wp_kses_post( $total ); ?></span>
		</div>
		<div class="cxp-checkout-bar__actions">
			<?php if ( $can_cart ) : ?>
				<a class="cxp-checkout-bar__cart" href="<?php echo esc_url( $cart ); ?>"><?php echo function_exists( 'cxp_icon' ) ? cxp_icon( 'shopping-cart' ) : ''; ?> Ver carrito</a>
			<?php else : ?>
				<span class="cxp-checkout-bar__locked" title="El carrito se habilita después de pasar por el checkout"><?php echo function_exists( 'cxp_icon' ) ? cxp_icon( 'lock' ) : ''; ?> Carrito después del checkout</span>
			<?php endif; ?>
			<a class="cxp-checkout-bar__go" href="<?php echo esc_url( $checkout ); ?>"><?php echo function_exists( 'cxp_icon' ) ? cxp_icon( 'credit-card' ) : ''; ?> Ir al checkout</a>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

add_action(
	'wp_footer',
	static function () {
		if ( is_admin() || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return;
		}
		echo cxp_storefront_checkout_bar_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	},
	40
);

add_filter(
	'woocommerce_add_to_cart_fragments',
	static function ( $fragments ) {
		$fragments['span.wd-tools-count']   = cxp_storefront_cart_count_html();
		$fragments['div.cxp-checkout-bar'] = cxp_storefront_checkout_bar_html();
		return $fragments;
	}
);

add_action(
	'init',
	static function () {
		update_option( 'woocommerce_enable_ajax_add_to_cart', 'yes' );
	},
	20
);

add_action(
	'woocommerce_proceed_to_checkout',
	static function () {
		$checkout = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
		echo '<a class="cxp-cart-checkout-btn checkout-button button alt wc-forward" href="' . esc_url( $checkout ) . '">Ir al checkout</a>';
	},
	25
);

function cxp_storefront_can_open_cart() {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return true;
	}
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return false;
	}
	return (bool) WC()->session->get( 'cxp_passed_checkout' );
}

add_action(
	'template_redirect',
	static function () {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'cxp_passed_checkout', 1 );
		}
	},
	1
);

add_action(
	'template_redirect',
	static function () {
		if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return;
		}
		if ( cxp_storefront_can_open_cart() ) {
			return;
		}
		$checkout = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
		wp_safe_redirect( $checkout );
		exit;
	},
	2
);

add_filter(
	'woocommerce_get_cart_url',
	static function ( $url ) {
		if ( cxp_storefront_can_open_cart() ) {
			return $url;
		}
		return function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : $url;
	}
);

add_filter(
	'woocommerce_add_to_cart_message_html',
	static function ( $message ) {
		if ( cxp_storefront_can_open_cart() ) {
			return $message;
		}
		$checkout = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
		return str_replace( array( 'Ver carrito', 'View cart' ), 'Ir al checkout', $message );
	}
);

function cxp_storefront_max_qty() {
	return 10;
}

add_filter(
	'woocommerce_quantity_input_args',
	static function ( $args ) {
		$args['min_value']   = 1;
		$args['max_value']   = cxp_storefront_max_qty();
		$current             = isset( $args['input_value'] ) ? (int) $args['input_value'] : 1;
		$args['input_value'] = min( cxp_storefront_max_qty(), max( 1, $current ) );
		return $args;
	},
	20
);

add_filter(
	'woocommerce_add_to_cart_validation',
	static function ( $passed, $product_id, $qty ) {
		$max = cxp_storefront_max_qty();
		$qty = (int) $qty;
		if ( $qty < 1 || $qty > $max ) {
			wc_add_notice( 'Puedes agregar entre 1 y 10 unidades de cada producto.', 'error' );
			return false;
		}
		if ( function_exists( 'WC' ) && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( (int) ( $item['product_id'] ?? 0 ) === (int) $product_id ) {
					if ( (int) $item['quantity'] + $qty > $max ) {
						wc_add_notice( 'Máximo 10 unidades de este producto en el carrito.', 'error' );
						return false;
					}
				}
			}
		}
		return $passed;
	},
	10,
	3
);

add_filter(
	'woocommerce_update_cart_validation',
	static function ( $passed, $cart_item_key, $values, $qty ) {
		if ( (int) $qty > cxp_storefront_max_qty() ) {
			wc_add_notice( 'Máximo 10 unidades por producto.', 'error' );
			return false;
		}
		return $passed;
	},
	10,
	4
);

function cxp_storefront_loop_qty_html() {
	ob_start();
	?>
	<div class="cxp-qty" data-max="10">
		<span class="cxp-qty__label">Cantidad</span>
		<button type="button" class="cxp-qty__btn" data-dir="-1" aria-label="Quitar uno"><?php echo function_exists( 'cxp_icon' ) ? cxp_icon( 'minus' ) : '−'; ?></button>
		<input class="cxp-qty__input" type="number" min="1" max="10" value="1" inputmode="numeric" aria-label="Cantidad">
		<button type="button" class="cxp-qty__btn" data-dir="1" aria-label="Agregar uno"><?php echo function_exists( 'cxp_icon' ) ? cxp_icon( 'plus' ) : '+'; ?></button>
	</div>
	<?php
	return ob_get_clean();
}

add_filter( 'loop_shop_columns', static function () {
	return 3;
} );

add_action(
	'woocommerce_after_shop_loop_item',
	static function () {
		echo cxp_storefront_loop_qty_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	},
	9
);
