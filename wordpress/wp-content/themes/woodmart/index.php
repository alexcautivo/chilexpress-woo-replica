<?php
get_header();
echo '<div class="wd-page-content site-content"><div class="container wd-content-area">';
if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
	woocommerce_content();
} elseif ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		the_title( '<h1 class="page-title">', '</h1>' );
		the_content();
	}
} else {
	echo '<p>No hay contenido.</p>';
}
echo '</div></div>';
get_footer();
