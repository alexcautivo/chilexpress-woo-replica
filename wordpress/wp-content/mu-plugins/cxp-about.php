<?php
/**
 * Plugin Name: Créditos del laboratorio
 * Version: 1.1.0
 * Description: Autor visible: Alexander Alejandro Cautivo Ramos, full stack, Aeolabs.io.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function cxp_author_name() {
	return 'Alexander Alejandro Cautivo Ramos';
}

function cxp_author_role() {
	return 'Desarrollador full stack';
}

function cxp_author_org() {
	return 'Aeolabs';
}

function cxp_author_site() {
	return 'https://aeolabs.io';
}

function cxp_author_email_public() {
	return 'alexander.cautivo@aeolabs.io';
}

function cxp_author_line( $short = false ) {
	if ( $short ) {
		return 'Alexander Cautivo · Full stack · Aeolabs.io';
	}
	return cxp_author_name() . ' · ' . cxp_author_role() . ' · ' . cxp_author_org() . '.io';
}

function cxp_author_notice_html() {
	return sprintf(
		'Este laboratorio lo desarrolló <strong>%1$s</strong>, %2$s de <a href="%3$s" target="_blank" rel="noopener noreferrer">%4$s.io</a>.',
		esc_html( cxp_author_name() ),
		esc_html( strtolower( cxp_author_role() ) ),
		esc_url( cxp_author_site() ),
		esc_html( cxp_author_org() )
	);
}

add_action( 'admin_bar_menu', 'cxp_author_admin_bar', 80 );
add_action( 'wp_head', 'cxp_author_admin_bar_css', 99 );
add_action( 'admin_head', 'cxp_author_admin_bar_css', 99 );
add_action( 'wp_body_open', 'cxp_author_storefront_strip', 4 );
add_action( 'login_header', 'cxp_author_login_strip' );
add_action( 'login_enqueue_scripts', 'cxp_author_login_css' );
add_filter( 'admin_footer_text', 'cxp_author_admin_footer' );
add_action( 'wp_dashboard_setup', 'cxp_author_dashboard_widget' );
add_action( 'cxp_debug_console_panels', 'cxp_about_console_panel', 100 );

function cxp_author_admin_bar( $bar ) {
	if ( ! $bar instanceof WP_Admin_Bar ) {
		return;
	}
	$bar->add_node(
		array(
			'id'     => 'cxp-author',
			'parent' => 'top-secondary',
			'title'  => cxp_author_line( true ),
			'href'   => cxp_author_site(),
			'meta'   => array(
				'class'  => 'cxp-adminbar-author',
				'target' => '_blank',
				'title'  => 'Todo este desarrollo: ' . cxp_author_name() . ', ' . cxp_author_role() . ' de ' . cxp_author_org() . '.io',
			),
		)
	);
	$bar->add_node(
		array(
			'id'     => 'cxp-author-name',
			'parent' => 'cxp-author',
			'title'  => cxp_author_name(),
			'href'   => 'mailto:' . cxp_author_email_public(),
		)
	);
	$bar->add_node(
		array(
			'id'     => 'cxp-author-role',
			'parent' => 'cxp-author',
			'title'  => cxp_author_role() . ' · ' . cxp_author_org() . '.io',
			'href'   => cxp_author_site(),
			'meta'   => array( 'target' => '_blank' ),
		)
	);
}

function cxp_author_admin_bar_css() {
	echo '<style id="cxp-author-adminbar">
#wpadminbar #wp-admin-bar-cxp-author > .ab-item {
	background: #fff200 !important;
	color: #111 !important;
	font-weight: 800 !important;
	letter-spacing: 0.01em;
}
#wpadminbar #wp-admin-bar-cxp-author:hover > .ab-item,
#wpadminbar #wp-admin-bar-cxp-author.hover > .ab-item {
	background: #f5e000 !important;
	color: #000 !important;
}
#wpadminbar #wp-admin-bar-cxp-author .ab-sub-wrapper .ab-item {
	color: #fff !important;
}
.cxp-author-strip {
	background: #fff200;
	color: #111;
	font: 700 13px/1.35 Inter, ui-sans-serif, system-ui, sans-serif;
	text-align: center;
	padding: 8px 16px;
	letter-spacing: 0.01em;
}
.cxp-author-strip a {
	color: #111;
	font-weight: 800;
	text-decoration: underline;
}
.cxp-author-strip strong { font-weight: 900; }
</style>';
}

function cxp_author_storefront_strip() {
	if ( is_admin() ) {
		return;
	}
	echo '<div class="cxp-author-strip" role="note">Desarrollado por <strong>' . esc_html( cxp_author_name() ) . '</strong> · ' . esc_html( cxp_author_role() ) . ' · <a href="' . esc_url( cxp_author_site() ) . '" target="_blank" rel="noopener noreferrer">Aeolabs.io</a></div>';
}

function cxp_author_login_strip() {
	echo '<p class="cxp-author-strip" style="margin:0;">Laboratorio de <strong>' . esc_html( cxp_author_name() ) . '</strong> · ' . esc_html( cxp_author_role() ) . ' · Aeolabs.io</p>';
}

function cxp_author_login_css() {
	echo '<style>.cxp-author-strip{background:#fff200;color:#111;text-align:center;padding:10px 16px;font-weight:700;}#login{padding-top:48px;}</style>';
}

function cxp_author_admin_footer( $text ) {
	return 'Desarrollado por <strong>' . esc_html( cxp_author_name() ) . '</strong>, ' . esc_html( strtolower( cxp_author_role() ) ) . ' de <a href="' . esc_url( cxp_author_site() ) . '" target="_blank" rel="noopener noreferrer">Aeolabs.io</a>.';
}

function cxp_author_dashboard_widget() {
	wp_add_dashboard_widget(
		'cxp_author_widget',
		'Autor de este laboratorio',
		static function () {
			echo '<p>' . wp_kses_post( cxp_author_notice_html() ) . '</p>';
			echo '<p>Correo: <a href="mailto:' . esc_attr( cxp_author_email_public() ) . '">' . esc_html( cxp_author_email_public() ) . '</a></p>';
		}
	);
}

function cxp_about_console_panel() {
	?>
	<style>
		#cxp-dbg-about{margin:0 0 12px;padding:12px 14px;border:1px solid #fff200;border-radius:8px;background:#111}
		#cxp-dbg-about p{margin:0 0 6px;color:#e8edf5}
		#cxp-dbg-about p strong{color:#fff200}
		#cxp-dbg-about a{color:#fff200}
	</style>
	<div id="cxp-dbg-about">
		<p><strong>Todo este desarrollo</strong></p>
		<p>Autor: <strong><?php echo esc_html( cxp_author_name() ); ?></strong><br>
			<?php echo esc_html( cxp_author_role() ); ?> de <a href="<?php echo esc_url( cxp_author_site() ); ?>" target="_blank" rel="noopener noreferrer">Aeolabs.io</a><br>
			<a href="mailto:<?php echo esc_attr( cxp_author_email_public() ); ?>"><?php echo esc_html( cxp_author_email_public() ); ?></a></p>
		<p>Ticket de referencia: SR-108688 (celularesenventa.cl). Chilexpress Oficial 1.4.0 se mantiene intacto.</p>
	</div>
	<?php
}
