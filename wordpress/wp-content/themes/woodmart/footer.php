<footer class="wd-footer footer-container color-scheme-light">
	<div class="container main-footer">
		<aside class="footer-sidebar widget-area wd-grid-g">
			<div class="footer-column">
				<h3>celularesenventa.cl</h3>
				<h6>Descubre más que solo celulares...</h6>
				<ul class="wd-list">
					<li><a href="https://wa.me/56993692578">+569 9369 2578</a></li>
					<li><a href="tel://224370424">+562 2437 0424</a></li>
					<li><a href="mailto:contacto@celularesenventa.cl">contacto@celularesenventa.cl</a></li>
				</ul>
			</div>
			<div class="footer-column">
				<h5 class="widget-title">Tienda</h5>
				<ul>
					<li><a href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) ); ?>">Catálogo</a></li>
					<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
						<li><a href="<?php echo esc_url( wc_get_cart_url() ); ?>">Carrito</a></li>
						<li><a href="<?php echo esc_url( wc_get_checkout_url() ); ?>">Checkout</a></li>
					<?php endif; ?>
				</ul>
			</div>
			<div class="footer-column">
				<h5 class="widget-title">Envíos</h5>
				<p>¡Envío gratis, rapido y seguro a todo Chile!</p>
			</div>
		</aside>
		<div class="copyrights-wrapper">
			<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
