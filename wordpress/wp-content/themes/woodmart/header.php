<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="whb-header whb-header_627927 whb-sticky-shadow">
	<div class="whb-main-header">
		<div class="whb-row whb-top-bar whb-sticky-row whb-with-bg whb-without-border whb-color-light">
			<div class="container">
				<div class="whb-flex-row whb-top-bar-inner">
					<div class="whb-column whb-col-left whb-visible-lg-only">
						<div class="wd-header-text"><a href="mailto:contacto@celularesenventa.cl">¡Envianos un Correo!</a></div>
					</div>
					<div class="whb-column whb-col-center">
						<div class="wd-header-text">Contactanos...
							<a href="https://wa.me/56993692578">+569 9369 2578</a>
							<strong>|</strong>
							<a href="tel://224370424">+562 2437 0424</a>
							<strong>|</strong>
							<a href="mailto:contacto@celularesenventa.cl">contacto@celularesenventa.cl</a>
						</div>
					</div>
					<div class="whb-column whb-col-right whb-visible-lg-only">
						<div class="wd-header-text">¡Envío gratis, rapido y seguro a todo Chile!</div>
					</div>
				</div>
			</div>
		</div>
		<div class="whb-row whb-general-header whb-sticky-row whb-without-bg whb-color-dark">
			<div class="container">
				<div class="whb-flex-row whb-general-header-inner">
					<div class="whb-column whb-col-left">
						<div class="site-logo">
							<?php
							$cxp_logo = plugins_url( 'chilexpress-oficial/public/imgs/logo-chilexpress.svg' );
							?>
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="cxp-storefront-logo wd-logo wd-main-logo" rel="home" aria-label="Chilexpress">
								<img width="320" height="52" src="<?php echo esc_url( $cxp_logo ); ?>" alt="Chilexpress" decoding="async">
							</a>
						</div>
					</div>
					<div class="whb-column whb-col-center">
						<div class="wd-search-form wd-header-search-form wd-display-form cxp-search-wrap">
							<form role="search" method="get" class="searchform wd-style-default" action="<?php echo esc_url( home_url( '/' ) ); ?>">
								<label class="screen-reader-text" for="cxp-header-search">Buscar productos</label>
								<input id="cxp-header-search" type="text" class="s" placeholder="Busca tus productos aquí" value="<?php echo esc_attr( get_search_query() ); ?>" name="s" required title="Escribe el nombre de un producto del catálogo (audífonos, notebook, silla…).">
								<input type="hidden" name="post_type" value="product">
								<button type="submit" class="searchsubmit" aria-label="Buscar productos del catálogo" title="Busca en el catálogo de la réplica. Luego agrega 1 a 10 unidades y cotiza en el checkout.">
									<?php echo function_exists( 'cxp_icon' ) ? cxp_icon( 'search' ) : ''; ?>
									<span>Buscar</span>
								</button>
							</form>
							<button type="button" class="cxp-search-info" aria-label="Qué hace Buscar" data-tip="Busca productos del catálogo por nombre. Pulsa Buscar o Enter. Después agrégalos (máximo 10 de cada uno) y ve al checkout para cotizar Chilexpress con una dirección real de la RM.">
								<?php echo function_exists( 'cxp_icon' ) ? cxp_icon( 'info' ) : 'i'; ?>
								<span class="cxp-search-tip">Busca productos del catálogo por nombre. Pulsa Buscar o Enter. Después agrégalos (máximo 10 de cada uno) y ve al checkout para cotizar Chilexpress con una dirección real de la RM.</span>
							</button>
						</div>
					</div>
					<div class="whb-column whb-col-right">
						<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
							<div class="wd-header-cart wd-tools-element">
								<a class="cxp-header-cart-link" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
									<span>Carrito</span>
									<span class="wd-tools-count"><?php echo esc_html( WC()->cart ? WC()->cart->get_cart_contents_count() : 0 ); ?></span>
								</a>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<div class="whb-row whb-header-bottom whb-sticky-row whb-with-bg whb-color-light">
			<div class="container">
				<nav class="wd-header-nav">
					<ul class="wd-nav wd-nav-main">
						<?php
						$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
						?>
						<li><a href="<?php echo esc_url( $shop ); ?>"><span class="nav-link-text">Tienda</span></a></li>
						<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
							<li><a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><span class="nav-link-text">Carrito</span></a></li>
							<li><a href="<?php echo esc_url( wc_get_checkout_url() ); ?>"><span class="nav-link-text">Checkout</span></a></li>
						<?php endif; ?>
					</ul>
				</nav>
			</div>
		</div>
	</div>
</header>
<?php do_action( 'cxp_after_header' ); ?>
