<?php
/**
 * Plugin Name: Créditos del laboratorio
 * Version: 1.0.0
 * Description: Autor y contacto de esta app de pruebas WordPress / Chilexpress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'cxp_debug_console_panels', 'cxp_about_console_panel', 100 );

function cxp_about_console_panel() {
	?>
	<style>
		#cxp-dbg-about{margin:0 0 12px;padding:10px 12px;border:1px solid #1e3a5f;border-radius:8px;background:#0b1a33}
		#cxp-dbg-about p{margin:0 0 6px;color:#9fb0c7}
		#cxp-dbg-about p strong{color:#fff200}
		#cxp-dbg-about a{color:#93c5fd}
	</style>
	<div id="cxp-dbg-about">
		<p><strong>Laboratorio de prueba WordPress + WooCommerce + Chilexpress Oficial</strong></p>
		<p>Autor y desarrollador: <strong>Alexander Alejandro Cautivo Ramos</strong><br>
			Aeolabs · <a href="mailto:alexander.cautivo@aeolabs.io">alexander.cautivo@aeolabs.io</a></p>
		<p>Ticket de referencia: SR-108688 (celularesenventa.cl). Chilexpress Oficial 1.4.0 se mantiene intacto. Guía: consola → Documentos, o carpeta <code>docs/</code> del repo.</p>
	</div>
	<?php
}
