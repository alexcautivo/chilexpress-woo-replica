<?php
/**
 * Plugin Name: Documentos para el cliente (SR-108688)
 * Version: 1.0.0
 * Description: Botones en la consola réplica para generar diagnóstico, respuesta, instrucciones e identificación del problema.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cxp_Client_Docs {
	const NONCE = 'cxp_client_docs';

	private static $docs = array(
		'identificacion' => array(
			'file'  => 'cliente-identificacion.md',
			'title' => 'Identificación del problema',
			'download' => 'SR-108688-identificacion.md',
		),
		'diagnostico'    => array(
			'file'  => 'cliente-diagnostico.md',
			'title' => 'Diagnóstico técnico',
			'download' => 'SR-108688-diagnostico.md',
		),
		'respuesta'      => array(
			'file'  => 'cliente-respuesta.md',
			'title' => 'Respuesta al cliente',
			'download' => 'SR-108688-respuesta-cliente.md',
		),
		'instrucciones'  => array(
			'file'  => 'cliente-instrucciones.md',
			'title' => 'Instrucciones detalladas',
			'download' => 'SR-108688-instrucciones-cliente.md',
		),
		'faq'            => array(
			'file'  => 'faq-replica.md',
			'title' => 'FAQ / situaciones de la réplica',
			'download' => 'SR-108688-faq-replica.md',
		),
		'guia'           => array(
			'file'  => 'guia-de-uso.md',
			'title' => 'Guía de uso del laboratorio',
			'download' => 'guia-laboratorio.md',
		),
		'consola'        => array(
			'file'  => 'consola-replica.md',
			'title' => 'Manual de la consola réplica',
			'download' => 'manual-consola-replica.md',
		),
		'dokploy'        => array(
			'file'  => 'dokploy.md',
			'title' => 'Despliegue en Dokploy',
			'download' => 'dokploy.md',
		),
		'informe_pdf'    => array(
			'file'  => 'SR-108688-informe-cliente.pdf',
			'title' => 'Informe PDF (por qué cae en producción)',
			'download' => 'SR-108688-informe-cliente.pdf',
		),
	);

	public static function boot() {
		add_action( 'cxp_debug_console_panels', array( __CLASS__, 'console_panel' ), 5 );
		add_action( 'wp_ajax_cxp_docs_get', array( __CLASS__, 'ajax_get' ) );
		add_action( 'wp_ajax_nopriv_cxp_docs_get', array( __CLASS__, 'ajax_get' ) );
		add_action( 'admin_post_cxp_docs_download', array( __CLASS__, 'download' ) );
		add_action( 'admin_post_nopriv_cxp_docs_download', array( __CLASS__, 'download' ) );
	}

	public static function docs_dir() {
		if ( function_exists( 'cxp_docs_dir' ) ) {
			return cxp_docs_dir();
		}
		return dirname( ABSPATH ) . '/docs';
	}

	public static function render_doc( $id ) {
		if ( ! isset( self::$docs[ $id ] ) ) {
			return new WP_Error( 'cxp_docs', 'Documento desconocido' );
		}
		$path = self::docs_dir() . '/' . self::$docs[ $id ]['file'];
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'cxp_docs', 'No está ' . self::$docs[ $id ]['file'] );
		}
		if ( preg_match( '/\.pdf$/i', self::$docs[ $id ]['file'] ) ) {
			return new WP_Error( 'cxp_docs', 'Este es un PDF: usa Descargar, no copiar texto.' );
		}
		$theme  = wp_get_theme();
		$parent = $theme->parent();
		$map    = array(
			'{{DATE}}'           => gmdate( 'Y-m-d H:i' ) . ' UTC',
			'{{SITE_URL}}'       => trailingslashit( home_url( '/' ) ),
			'{{PHP_VERSION}}'    => PHP_VERSION,
			'{{WP_VERSION}}'     => get_bloginfo( 'version' ),
			'{{WC_VERSION}}'     => defined( 'WC_VERSION' ) ? WC_VERSION : 'n/d',
			'{{CXP_VERSION}}'    => defined( 'CHILEXPRESS_WOO_OFICIAL_VERSION' ) ? CHILEXPRESS_WOO_OFICIAL_VERSION : self::cxp_version(),
			'{{THEME}}'          => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
			'{{PARENT_THEME}}'   => $parent ? $parent->get( 'Name' ) . ' ' . $parent->get( 'Version' ) : '(ninguno)',
		);
		$text = (string) file_get_contents( $path );
		return strtr( $text, $map );
	}

	public static function render_bundle() {
		$parts = array();
		foreach ( array_keys( self::$docs ) as $id ) {
			$doc = self::render_doc( $id );
			if ( is_wp_error( $doc ) ) {
				continue;
			}
			$parts[] = $doc;
		}
		return implode( "\n\n" . str_repeat( '=', 72 ) . "\n\n", $parts );
	}

	public static function console_panel() {
		$ajax  = admin_url( 'admin-ajax.php' );
		$nonce = wp_create_nonce( self::NONCE );
		$exports = array(
			'identificacion' => array(
				'title' => '1. Identificar el error',
				'hint'  => 'Qué falló (ProductTaxStatus) y cómo reconocerlo.',
			),
			'diagnostico'    => array(
				'title' => '2. Diagnóstico / por qué',
				'hint'  => 'Causa: require en plugins_loaded durante el update.',
			),
			'respuesta'      => array(
				'title' => '3. Solución (texto al cliente)',
				'hint'  => 'Carta: desactivar Chilexpress, actualizar Woo, reactivar.',
			),
			'instrucciones'  => array(
				'title' => '4. Instrucciones para arreglarlo',
				'hint'  => 'Pasos en producción, FTP y cómo comprobar el enum.',
			),
		);
		$pdf_url = wp_nonce_url( admin_url( 'admin-post.php?action=cxp_docs_download&doc=informe_pdf' ), self::NONCE );
		$pack_url = wp_nonce_url( admin_url( 'admin-post.php?action=cxp_docs_download&doc=all' ), self::NONCE );
		?>
		<style>
			#cxp-dbg-docs{margin:0 0 12px;padding:10px 12px;border:1px solid #ca8a04;border-radius:8px;background:#111827}
			#cxp-dbg-docs p{margin:0 0 8px;color:#9fb0c7 !important;-webkit-text-fill-color:#9fb0c7}
			#cxp-dbg-docs p strong{color:#fff200 !important;-webkit-text-fill-color:#fff200}
			#cxp-dbg-docs .cxp-docs-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;margin:8px 0}
			#cxp-dbg-docs .cxp-docs-card{border:1px solid #3d4d66;border-radius:8px;padding:8px;background:#0b1220}
			#cxp-dbg-docs .cxp-docs-card h4{margin:0 0 4px;color:#fef08a !important;-webkit-text-fill-color:#fef08a;font-size:12px}
			#cxp-dbg-docs .cxp-docs-card span{display:block;margin:0 0 8px;color:#9fb0c7 !important;-webkit-text-fill-color:#9fb0c7;font-size:11px}
			#cxp-dbg-docs .cxp-docs-card .cxp-lab-row{display:flex;flex-wrap:wrap;gap:6px}
			#cxp-dbg-docs .cxp-lab-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:8px 0}
			#cxp-dbg-docs pre,#cxp-dbg-docs #cxp-docs-out{margin:8px 0 0;max-height:240px;overflow:auto;white-space:pre-wrap;color:#f8fafc !important;-webkit-text-fill-color:#f8fafc !important;background:#020617 !important;padding:8px;border-radius:6px;border:1px solid #334155}
			#cxp-dbg-docs a.cxp-dbg-btn-export{border-color:#ca8a04;background:#854d0e;color:#fef08a !important;-webkit-text-fill-color:#fef08a;font-weight:700}
		</style>
		<div id="cxp-dbg-docs">
			<p><strong>Exportar para el cliente (SR-108688)</strong> — copiar al chat o descargar. El texto lleva las versiones de este sitio. El PDF explica por qué en producción cae y en la réplica (Woo completo) no.</p>
			<div class="cxp-docs-grid">
				<?php foreach ( $exports as $id => $card ) : ?>
					<div class="cxp-docs-card">
						<h4><?php echo esc_html( $card['title'] ); ?></h4>
						<span><?php echo esc_html( $card['hint'] ); ?></span>
						<div class="cxp-lab-row">
							<button type="button" class="cxp-dbg-copy-one cxp-docs-btn" data-id="<?php echo esc_attr( $id ); ?>">Copiar</button>
							<a class="cxp-dbg-btn" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cxp_docs_download&doc=' . rawurlencode( $id ) ), self::NONCE ) ); ?>">Descargar .md</a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="cxp-lab-row">
				<a class="cxp-dbg-btn cxp-dbg-btn-export" href="<?php echo esc_url( $pdf_url ); ?>">Descargar informe PDF</a>
				<button type="button" class="cxp-dbg-copy-one" id="cxp-docs-all">Copiar pack (identificación + diagnóstico + solución + instrucciones)</button>
				<a class="cxp-dbg-btn" href="<?php echo esc_url( $pack_url ); ?>">Descargar pack .md</a>
				<button type="button" class="cxp-dbg-copy-one cxp-docs-btn" data-id="faq">FAQ réplica</button>
				<button type="button" class="cxp-dbg-copy-one cxp-docs-btn" data-id="guia">Guía de uso</button>
			</div>
			<pre id="cxp-docs-out">Pulsa Copiar o Descargar. Aquí aparece el texto (fondo oscuro, letras claras) y se copia al portapapeles.</pre>
		</div>
		<script>
		(function () {
			var ajaxUrl = <?php echo wp_json_encode( $ajax ); ?>;
			var nonce = <?php echo wp_json_encode( $nonce ); ?>;
			var out = document.getElementById('cxp-docs-out');
			function copy(text) {
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text);
					return;
				}
				var ta = document.createElement('textarea');
				ta.value = text;
				document.body.appendChild(ta);
				ta.select();
				document.execCommand('copy');
				ta.remove();
			}
			function load(id) {
				out.textContent = 'Generando…';
				var body = new URLSearchParams();
				body.set('action', 'cxp_docs_get');
				body.set('nonce', nonce);
				body.set('doc', id);
				fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body.toString()
				}).then(function (r) { return r.json(); }).then(function (data) {
					var text = (data && data.data && data.data.text) ? data.data.text : ((data && data.data) ? String(data.data) : 'Sin contenido');
					out.textContent = text;
					if (data && data.success) copy(text);
				}).catch(function (e) { out.textContent = 'Falló: ' + e; });
			}
			document.querySelectorAll('.cxp-docs-btn').forEach(function (btn) {
				btn.addEventListener('click', function (e) {
					e.stopPropagation();
					load(btn.getAttribute('data-id'));
				});
			});
			var all = document.getElementById('cxp-docs-all');
			if (all) all.addEventListener('click', function (e) {
				e.stopPropagation();
				load('all');
			});
		})();
		</script>
		<?php
	}

	public static function ajax_get() {
		self::guard_nonce();
		$id  = sanitize_key( wp_unslash( $_POST['doc'] ?? '' ) );
		$doc = 'all' === $id ? self::render_bundle() : self::render_doc( $id );
		if ( is_wp_error( $doc ) ) {
			wp_send_json_error( $doc->get_error_message() );
		}
		wp_send_json_success( array( 'text' => $doc ) );
	}

	public static function download() {
		self::ensure_user();
		check_admin_referer( self::NONCE );
		$id = sanitize_key( wp_unslash( $_GET['doc'] ?? '' ) );
		if ( 'all' === $id ) {
			$body = self::render_bundle();
			$name = 'SR-108688-pack-cliente.md';
			if ( is_wp_error( $body ) ) {
				wp_die( $body->get_error_message() );
			}
			nocache_headers();
			header( 'Content-Type: text/markdown; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $name . '"' );
			echo $body;
			exit;
		}
		if ( isset( self::$docs[ $id ] ) && preg_match( '/\.pdf$/i', self::$docs[ $id ]['file'] ) ) {
			$path = self::docs_dir() . '/' . self::$docs[ $id ]['file'];
			if ( ! is_readable( $path ) ) {
				wp_die( 'No está el PDF' );
			}
			$name = self::$docs[ $id ]['download'];
			nocache_headers();
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: attachment; filename="' . $name . '"' );
			header( 'Content-Length: ' . (string) filesize( $path ) );
			readfile( $path );
			exit;
		}
		$body = self::render_doc( $id );
		$name = self::$docs[ $id ]['download'] ?? ( 'SR-108688-' . $id . '.md' );
		if ( is_wp_error( $body ) ) {
			wp_die( $body->get_error_message() );
		}
		nocache_headers();
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		echo $body;
		exit;
	}

	private static function cxp_version() {
		$file = WP_PLUGIN_DIR . '/chilexpress-oficial/chilexpress-woo-oficial.php';
		if ( ! is_readable( $file ) ) {
			return 'n/d';
		}
		$data = get_plugin_data( $file, false, false );
		return $data['Version'] !== '' ? $data['Version'] : 'n/d';
	}

	private static function guard_nonce() {
		self::ensure_user();
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), self::NONCE ) ) {
			wp_send_json_error( 'Nonce inválido' );
		}
	}

	private static function ensure_user() {
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( function_exists( 'cxp_auto_login_user' ) ) {
			cxp_auto_login_user();
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sin permiso' );
		}
	}
}

Cxp_Client_Docs::boot();
