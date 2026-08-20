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
							$logo = content_url( 'uploads/2023/11/logo-1.svg' );
							if ( has_custom_logo() ) {
								the_custom_logo();
							} else {
								echo '<a href="' . esc_url( home_url( '/' ) ) . '" class="wd-logo wd-main-logo" rel="home">';
								echo '<img width="459" height="87" src="' . esc_url( $logo ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" style="max-width:300px">';
								echo '</a>';
							}
							?>
						</div>
					</div>
					<div class="whb-column whb-col-center">
						<div class="wd-search-form wd-header-search-form wd-display-form">
							<form role="search" method="get" class="searchform wd-style-default" action="<?php echo esc_url( home_url( '/' ) ); ?>">
								<input type="text" class="s" placeholder="Busca tus productos aquí!" value="<?php echo esc_attr( get_search_query() ); ?>" name="s" required>
								<input type="hidden" name="post_type" value="product">
								<button type="submit" class="searchsubmit"><span>Search</span></button>
							</form>
						</div>
					</div>
					<div class="whb-column whb-col-right">
						<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
							<div class="wd-header-cart wd-tools-element">
								<a href="<?php echo esc_url( wc_get_cart_url() ); ?>">
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
