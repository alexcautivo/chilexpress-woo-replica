<?php
/**
 * Plugin Name: Incidencias (JSON → ticket)
 * Version: 1.0.0
 * Description: Plantilla JSON para el cliente y alta de tickets. Carpeta incidents/ del repo. No parchea chilexpress-oficial.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cxp_Tickets {
	const NONCE = 'cxp_tickets';

	public static function boot() {
		add_action( 'cxp_debug_console_panels', array( __CLASS__, 'console_panel' ), 3 );
		add_action( 'wp_ajax_cxp_ticket_create', array( __CLASS__, 'ajax_create' ) );
		add_action( 'wp_ajax_nopriv_cxp_ticket_create', array( __CLASS__, 'ajax_create' ) );
		add_action( 'wp_ajax_cxp_ticket_get', array( __CLASS__, 'ajax_get' ) );
		add_action( 'wp_ajax_nopriv_cxp_ticket_get', array( __CLASS__, 'ajax_get' ) );
	}

	public static function root() {
		return function_exists( 'cxp_incidents_dir' ) ? cxp_incidents_dir() : ( dirname( ABSPATH ) . '/incidents' );
	}

	private static function template_path() {
		return self::root() . '/templates/para-el-cliente.json';
	}

	private static function tickets_dir() {
		return self::root() . '/tickets';
	}

	public static function template_json() {
		$path = self::template_path();
		if ( ! is_readable( $path ) ) {
			return '{}';
		}
		$raw = file_get_contents( $path );
		return is_string( $raw ) ? $raw : '{}';
	}

	public static function list_tickets() {
		$dir   = self::tickets_dir();
		$out   = array();
		$files = is_dir( $dir ) ? glob( $dir . '/*.json' ) : array();
		foreach ( (array) $files as $file ) {
			$raw = json_decode( (string) file_get_contents( $file ), true );
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$id = (string) ( $raw['ticket_id'] ?? basename( $file, '.json' ) );
			$out[] = array(
				'id'      => $id,
				'file'    => basename( $file ),
				'sitio'   => (string) ( $raw['origen']['sitio_url'] ?? '' ),
				'resumen' => (string) ( $raw['sintoma']['resumen'] ?? '' ),
				'impacto' => (string) ( $raw['sintoma']['impacto'] ?? '' ),
				'plan'    => (string) ( $raw['plan_repo'] ?? '' ),
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return strcasecmp( $b['id'], $a['id'] );
			}
		);
		return $out;
	}

	public static function console_panel() {
		$ajax     = admin_url( 'admin-ajax.php' );
		$nonce    = wp_create_nonce( self::NONCE );
		$template = self::template_json();
		$tickets  = self::list_tickets();
		$root     = self::root();
		?>
		<style>
			#cxp-dbg-tickets{margin:0 0 12px;padding:12px;border:1px solid #3d4d66;border-radius:8px;background:#111827}
			#cxp-dbg-tickets p,#cxp-dbg-tickets li{color:#cbd5e1}
			#cxp-dbg-tickets strong{color:#fff200}
			#cxp-dbg-tickets textarea{width:100%;min-height:140px;font:11px/1.4 ui-monospace,monospace}
			#cxp-dbg-tickets table{width:100%;border-collapse:collapse;margin:8px 0}
			#cxp-dbg-tickets td,#cxp-dbg-tickets th{padding:6px 8px;border-bottom:1px solid #243044;text-align:left;font-size:12px}
			#cxp-dbg-tickets .cxp-lab-row{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0}
		</style>
		<div id="cxp-dbg-tickets">
			<p><strong>Incidencias</strong> — este laboratorio no es solo SR-108688. Se copia un JSON al cliente; al devolverlo se <em>crea un ticket</em>. Planes en <code>incidents/</code> del repo. Chilexpress Oficial no se parchea.</p>
			<p>Carpeta: <code><?php echo esc_html( $root ); ?></code></p>
			<ol>
				<li><strong>Copiar JSON para el cliente</strong> y pegárselo.</li>
				<li>El cliente lo rellena (versiones, error, URL, pasos).</li>
				<li><strong>Pegar JSON → Crear ticket</strong>. Queda en <code>incidents/tickets/</code>.</li>
			</ol>
			<div class="cxp-lab-row">
				<button type="button" class="cxp-dbg-copy-one" id="cxp-ticket-copy-tpl">Copiar JSON para el cliente</button>
			</div>
			<textarea id="cxp-ticket-paste" spellcheck="false" placeholder="Pegar aquí el JSON que devolvió el cliente"></textarea>
			<div class="cxp-lab-row">
				<button type="button" class="cxp-dbg-copy-one" id="cxp-ticket-create">Crear ticket con este JSON</button>
			</div>
			<pre id="cxp-ticket-out">Tickets en disco: <?php echo esc_html( (string) count( $tickets ) ); ?></pre>
			<table>
				<thead>
					<tr>
						<th>Ticket</th>
						<th>Sitio</th>
						<th>Resumen</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $tickets ) : ?>
						<tr><td colspan="4">Ningún ticket todavía. Crea uno pegando el JSON.</td></tr>
					<?php endif; ?>
					<?php foreach ( $tickets as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['id'] ); ?></td>
							<td><?php echo esc_html( $row['sitio'] ); ?></td>
							<td><?php echo esc_html( wp_trim_words( $row['resumen'], 16 ) ); ?></td>
							<td>
								<button type="button" class="cxp-dbg-copy-one cxp-ticket-open" data-id="<?php echo esc_attr( $row['id'] ); ?>">Ver JSON</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>SR-108688 (referencia): por qué falló, plan de hoy y mejoras al plugin Chilexpress están en <code>incidents/planes/SR-108688/</code>.</p>
		</div>
		<script>
		(function () {
			var ajaxUrl = <?php echo wp_json_encode( $ajax ); ?>;
			var nonce = <?php echo wp_json_encode( $nonce ); ?>;
			var tpl = <?php echo wp_json_encode( $template ); ?>;
			var out = document.getElementById('cxp-ticket-out');
			function copy(text) {
				function ok() {
					if (window.cxpNotify) window.cxpNotify({ type: 'success', title: 'Copiado', message: 'JSON listo para pegar al cliente.' });
					else if (out) out.textContent = 'Copiado.';
				}
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(ok);
					return;
				}
				ok();
			}
			function post(action, extra) {
				var body = new URLSearchParams();
				body.set('action', action);
				body.set('nonce', nonce);
				Object.keys(extra || {}).forEach(function (k) { body.set(k, extra[k]); });
				return fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body.toString()
				}).then(function (r) { return r.json(); });
			}
			var copyBtn = document.getElementById('cxp-ticket-copy-tpl');
			if (copyBtn) copyBtn.addEventListener('click', function (e) {
				e.stopPropagation();
				copy(tpl);
			});
			var createBtn = document.getElementById('cxp-ticket-create');
			if (createBtn) createBtn.addEventListener('click', function (e) {
				e.stopPropagation();
				var raw = (document.getElementById('cxp-ticket-paste') || {}).value || '';
				if (!raw.trim()) {
					if (out) out.textContent = 'Pega el JSON del cliente.';
					return;
				}
				if (out) out.textContent = 'Creando ticket…';
				post('cxp_ticket_create', { json: raw }).then(function (data) {
					var payload = data && data.data !== undefined ? data.data : data;
					var text = (payload && payload.text) ? payload.text : (typeof payload === 'string' ? payload : JSON.stringify(payload, null, 2));
					if (out) out.textContent = text || 'Listo';
					if (data && data.success) setTimeout(function () { window.location.reload(); }, 800);
				}).catch(function (err) {
					if (out) out.textContent = 'Falló: ' + err;
				});
			});
			document.querySelectorAll('.cxp-ticket-open').forEach(function (btn) {
				btn.addEventListener('click', function (e) {
					e.stopPropagation();
					post('cxp_ticket_get', { id: btn.getAttribute('data-id') }).then(function (data) {
						var payload = data && data.data !== undefined ? data.data : data;
						var text = (payload && payload.text) ? payload.text : JSON.stringify(payload, null, 2);
						if (out) out.textContent = text;
					});
				});
			});
		})();
		</script>
		<?php
	}

	private static function guard() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), self::NONCE ) ) {
			wp_send_json_error( 'Nonce inválido' );
		}
	}

	public static function ajax_get() {
		self::guard();
		$id   = sanitize_file_name( wp_unslash( $_POST['id'] ?? '' ) );
		$path = self::tickets_dir() . '/' . $id . '.json';
		if ( ! is_readable( $path ) ) {
			wp_send_json_error( 'No está el ticket ' . $id );
		}
		wp_send_json_success( array( 'text' => (string) file_get_contents( $path ) ) );
	}

	public static function ajax_create() {
		self::guard();
		$raw = wp_unslash( $_POST['json'] ?? '' );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			wp_send_json_error( 'JSON inválido. El cliente debe devolver el mismo archivo, con las claves intactas.' );
		}
		if ( ( $data['schema_version'] ?? '' ) !== '1.0' ) {
			wp_send_json_error( 'schema_version debe ser 1.0 (plantilla actual).' );
		}
		$sitio = trim( (string) ( $data['origen']['sitio_url'] ?? '' ) );
		$res   = trim( (string) ( $data['sintoma']['resumen'] ?? '' ) );
		if ( $sitio === '' || $sitio === 'https://' ) {
			wp_send_json_error( 'Falta origen.sitio_url.' );
		}
		if ( $res === '' || 0 === strpos( $res, 'En una frase' ) ) {
			wp_send_json_error( 'Falta sintoma.resumen real (no dejen el texto de ejemplo).' );
		}
		$id = sanitize_file_name( (string) ( $data['ticket_id'] ?? '' ) );
		if ( $id === '' ) {
			$id = 'CXP-' . gmdate( 'Ymd-His' );
			$data['ticket_id'] = $id;
		}
		$dir = self::tickets_dir();
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			wp_send_json_error( 'No se pudo crear incidents/tickets. Revisa CXP_INCIDENTS_DIR.' );
		}
		$path = $dir . '/' . $id . '.json';
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === file_put_contents( $path, $json . "\n" ) ) {
			wp_send_json_error( 'No se pudo escribir ' . $path );
		}
		wp_send_json_success(
			array(
				'text' => 'Ticket ' . $id . ' creado en incidents/tickets/' . $id . ".json\nSitio: " . $sitio . "\n" . $res . "\nSi es un caso nuevo, documenta el plan en incidents/planes/" . $id . '/',
			)
		);
	}
}

Cxp_Tickets::boot();
