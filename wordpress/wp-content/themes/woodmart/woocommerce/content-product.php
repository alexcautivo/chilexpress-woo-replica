<?php
/**
 * Product card — estructura Woodmart (wd-product / wd-hover-tiled).
 *
 * @var WC_Product $product
 */
defined( 'ABSPATH' ) || exit;

global $product;
if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}
?>
<li <?php wc_product_class( '', $product ); ?>>
	<div class="wd-product-wrapper product-wrapper">
		<div class="wd-product-thumb product-element-top">
			<a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="wd-product-img-link product-image-link">
				<?php echo $product->get_image( 'woocommerce_thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</a>
			<?php if ( $product->is_on_sale() ) : ?>
				<div class="product-labels labels-rectangular"><span class="onsale product-label">Oferta</span></div>
			<?php endif; ?>
		</div>
		<div class="wd-product-content product-element-bottom">
			<h3 class="wd-entities-title">
				<a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
			</h3>
			<?php woocommerce_template_loop_price(); ?>
			<div class="wd-add-btn wd-add-btn-replace">
				<?php woocommerce_template_loop_add_to_cart(); ?>
			</div>
		</div>
	</div>
</li>
