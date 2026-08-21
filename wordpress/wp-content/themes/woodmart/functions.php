<?php
/**
 * Woodmart 8.5.7 — réplica SR-108688 (CSS y metadatos de celularesenventa.cl).
 * El ZIP comercial de ThemeForest no está; las plantillas PHP son de réplica con las mismas clases.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'custom-logo', array( 'height' => 87, 'width' => 459, 'flex-height' => true, 'flex-width' => true ) );
		add_theme_support( 'woocommerce' );
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );
		register_nav_menus(
			array(
				'woodmart_main' => 'Menú principal Woodmart',
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		$ver  = wp_get_theme( 'woodmart' )->get( 'Version' );
		$base = get_template_directory_uri();
		wp_enqueue_style( 'woodmart-style', $base . '/style.css', array(), $ver );
		wp_enqueue_style( 'woodmart-font', $base . '/css/woodmart-font.css', array( 'woodmart-style' ), $ver );
		wp_enqueue_style( 'woodmart-google-fonts', $base . '/css/google-fonts.css', array( 'woodmart-style' ), $ver );
		wp_enqueue_style( 'woodmart-theme-settings', $base . '/css/theme-settings.css', array( 'woodmart-style' ), $ver );
		wp_enqueue_style( 'woodmart-header-vars', $base . '/css/header-vars.css', array( 'woodmart-style' ), $ver );
		wp_enqueue_style( 'woodmart-replica-layout', $base . '/css/replica-layout.css', array( 'woodmart-header-vars' ), $ver );
		if ( function_exists( 'is_woocommerce' ) ) {
			wp_enqueue_script( 'wc-add-to-cart' );
			wp_enqueue_script( 'wc-cart-fragments' );
		}
	}
);

add_filter(
	'body_class',
	static function ( $classes ) {
		$classes[] = 'wrapper-full-width';
		$classes[] = 'theme-woodmart';
		$classes[] = 'wd';
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			$classes[] = 'woodmart-archive-shop';
			$classes[] = 'woocommerce-shop';
		}
		return $classes;
	}
);

add_filter(
	'woocommerce_product_loop_start',
	static function ( $html ) {
		if ( false === strpos( $html, 'wd-products' ) ) {
			$html = str_replace(
				'class="products',
				'class="products wd-products wd-grid-g grid-columns-3 elements-grid wd-loop-builder-off wd-products-with-shadow',
				$html
			);
		}
		return $html;
	}
);

add_filter(
	'woocommerce_post_class',
	static function ( $classes ) {
		$classes[] = 'wd-product';
		$classes[] = 'wd-col';
		$classes[] = 'wd-hover-tiled';
		$classes[] = 'product-grid-item';
		return $classes;
	}
);

