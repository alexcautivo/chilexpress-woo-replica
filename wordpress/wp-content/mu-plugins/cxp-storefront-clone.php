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
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$classes[] = 'cxp-storefront-checkout';
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
			'1.0.0'
		);

		wp_add_inline_script(
			'chilexpress-woo-oficial-storefront',
			<<<'JS'
(function () {
  function brandWoodmart() {
    if (document.querySelector('.cxp-storefront-logo')) {
      document.body.classList.add('cxp-storefront-branded');
      return;
    }
    var slot = document.querySelector('.whb-general-header .site-logo') || document.querySelector('.site-logo');
    if (!slot || typeof chilexpressStorefront === 'undefined' || !chilexpressStorefront.logoUrl) {
      return;
    }
    var labels = chilexpressStorefront.labels || {};
    var anchor = document.createElement('a');
    anchor.className = 'cxp-storefront-logo';
    anchor.href = chilexpressStorefront.homeUrl || '/';
    anchor.setAttribute('aria-label', labels.logoAria || 'Chilexpress');
    var img = document.createElement('img');
    img.src = chilexpressStorefront.logoUrl;
    img.alt = labels.logoAria || 'Chilexpress';
    img.width = 180;
    img.height = 29;
    img.decoding = 'async';
    anchor.appendChild(img);
    slot.parentNode.insertBefore(anchor, slot);
    document.body.classList.add('cxp-storefront-branded');
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', brandWoodmart);
  } else {
    brandWoodmart();
  }
})();
JS
		);
	},
	20
);

add_action(
	'wp_body_open',
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
		$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
		?>
		<section class="cxp-catalog-hero" data-cxp-fallback="true">
			<div class="cxp-catalog-hero__inner">
				<h1>Todo lo que necesitas, entregado con Chilexpress</h1>
				<p class="cxp-catalog-hero__text">Explora el catálogo de esta tienda de prueba y encuentra tus productos.</p>
				<a class="cxp-catalog-hero__cta" href="<?php echo esc_url( $shop ); ?>">Ver productos</a>
			</div>
		</section>
		<section class="cxp-catalog-benefits" data-cxp-fallback="true">
			<div class="cxp-catalog-benefits__inner">
				<div class="cxp-catalog-benefit">
					<span class="cxp-catalog-benefit__mark" aria-hidden="true">01</span>
					<strong>Cobertura nacional</strong>
				</div>
				<div class="cxp-catalog-benefit">
					<span class="cxp-catalog-benefit__mark" aria-hidden="true">02</span>
					<strong>Seguimiento de envíos</strong>
				</div>
				<div class="cxp-catalog-benefit">
					<span class="cxp-catalog-benefit__mark" aria-hidden="true">03</span>
					<strong>Entregas rápidas</strong>
				</div>
			</div>
		</section>
		<?php
	}
);
