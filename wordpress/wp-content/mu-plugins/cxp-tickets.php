<?php
/**
 * Plugin Name: Incidencias (JSON → ticket)
 * Version: 1.1.0
 * Description: Importa tickets multi-cliente y opera su pila, reproducción, comparación y entregables.
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
			$latest = class_exists( 'Cxp_Incident_Runner' ) ? Cxp_Incident_Runner::latest_run( $id ) : array();
			$out[] = array(
				'id'      => $id,
				'file'    => basename( $file ),
				'sitio'   => (string) ( $raw['origen']['sitio_url'] ?? '' ),
				'resumen' => (string) ( $raw['sintoma']['resumen'] ?? '' ),
				'impacto' => (string) ( $raw['sintoma']['impacto'] ?? '' ),
				'plan'    => (string) ( $raw['plan_repo'] ?? '' ),
				'run'     => (string) ( $latest['run_id'] ?? '' ),
				'state'   => (string) ( $latest['state'] ?? '' ),
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
		$runner_nonce = class_exists( 'Cxp_Incident_Runner' ) ? Cxp_Incident_Runner::nonce() : '';
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
			#cxp-dbg-tickets .cxp-ticket-actions{display:flex;flex-wrap:wrap;gap:5px}
			#cxp-dbg-tickets .cxp-ticket-actions button{font-size:11px;padding:4px 7px}
			#cxp-dbg-tickets .cxp-ticket-primary{background:#854d0e!important;border-color:#facc15!important;color:#fef08a!important;font-weight:700}
			#cxp-dbg-tickets .cxp-ticket-danger{border-color:#ef4444!important;color:#fecaca!important}
			#cxp-ticket-progress{display:none;margin:8px 0;padding:8px;border:1px solid #2563eb;border-radius:6px;background:#172554;color:#dbeafe}
			#cxp-ticket-progress.is-on{display:block}
		</style>
		<div id="cxp-dbg-tickets">
			<p><strong>Incidencias multi-cliente</strong> — importa el JSON, compara la pila, crea un snapshot completo, instala versiones exactas y ejecuta un flujo seguro. Crear un ticket <em>no modifica</em> WordPress.</p>
			<p>Carpeta: <code><?php echo esc_html( $root ); ?></code></p>
			<ol>
				<li><strong>Copiar JSON para el cliente</strong> y pegárselo.</li>
				<li>El cliente lo rellena (versiones, error, URL, pasos).</li>
				<li><strong>Pegar JSON → Crear ticket</strong>. Queda en <code>incidents/tickets/</code>.</li>
				<li><strong>Vista previa → Aplicar pila → Ejecutar flujo</strong>. Resultado y PDFs quedan en <code>incidents/runs/</code>.</li>
			</ol>
			<div class="cxp-lab-row">
				<button type="button" class="cxp-dbg-copy-one" id="cxp-ticket-copy-tpl">Copiar JSON para el cliente</button>
			</div>
			<textarea id="cxp-ticket-paste" spellcheck="false" placeholder="Pegar aquí el JSON que devolvió el cliente"></textarea>
			<div class="cxp-lab-row">
				<button type="button" class="cxp-dbg-copy-one" id="cxp-ticket-create">Crear ticket con este JSON</button>
			</div>
			<pre id="cxp-ticket-out">Tickets en disco: <?php echo esc_html( (string) count( $tickets ) ); ?></pre>
			<div id="cxp-ticket-progress" role="status">Preparando…</div>
			<table>
				<thead>
					<tr>
						<th>Ticket</th>
						<th>Sitio</th>
						<th>Resumen</th>
						<th>Laboratorio</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $tickets ) : ?>
						<tr><td colspan="4">Ningún ticket todavía. Crea uno pegando el JSON.</td></tr>
					<?php endif; ?>
					<?php foreach ( $tickets as $row ) : ?>
						<tr data-ticket-id="<?php echo esc_attr( $row['id'] ); ?>" data-latest-run="<?php echo esc_attr( $row['run'] ); ?>">
							<td><?php echo esc_html( $row['id'] ); ?><?php echo $row['state'] ? '<br><small>' . esc_html( $row['state'] ) . '</small>' : ''; ?></td>
							<td><?php echo esc_html( $row['sitio'] ); ?></td>
							<td><?php echo esc_html( wp_trim_words( $row['resumen'], 16 ) ); ?></td>
							<td class="cxp-ticket-actions">
								<button type="button" class="cxp-dbg-copy-one cxp-ticket-open" data-id="<?php echo esc_attr( $row['id'] ); ?>">JSON</button>
								<button type="button" class="cxp-dbg-copy-one cxp-ticket-preview" data-id="<?php echo esc_attr( $row['id'] ); ?>">Vista previa</button>
								<button type="button" class="cxp-dbg-copy-one cxp-ticket-build-flow" data-id="<?php echo esc_attr( $row['id'] ); ?>">Crear flujo</button>
								<button type="button" class="cxp-dbg-copy-one cxp-ticket-primary cxp-ticket-apply" data-id="<?php echo esc_attr( $row['id'] ); ?>">Aplicar pila</button>
								<button type="button" class="cxp-dbg-copy-one cxp-ticket-run" data-id="<?php echo esc_attr( $row['id'] ); ?>">Ejecutar flujo</button>
								<button type="button" class="cxp-dbg-copy-one cxp-ticket-result" data-id="<?php echo esc_attr( $row['id'] ); ?>">Resultado</button>
								<button type="button" class="cxp-dbg-copy-one cxp-ticket-pdf-client" data-id="<?php echo esc_attr( $row['id'] ); ?>">PDF cliente</button>
								<button type="button" class="cxp-dbg-copy-one cxp-ticket-pdf-tech" data-id="<?php echo esc_attr( $row['id'] ); ?>">PDF técnico</button>
								<button type="button" class="cxp-dbg-copy-one cxp-ticket-danger cxp-ticket-restore" data-id="<?php echo esc_attr( $row['id'] ); ?>">Restaurar snapshot</button>
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
			var runnerNonce = <?php echo wp_json_encode( $runner_nonce ); ?>;
			var tpl = <?php echo wp_json_encode( $template ); ?>;
			var out = document.getElementById('cxp-ticket-out');
			var progress = document.getElementById('cxp-ticket-progress');
			function runKey(id) { return 'cxp-incident-run-' + id; }
			function getRun(id) {
				var saved = window.localStorage ? localStorage.getItem(runKey(id)) || '' : '';
				if (saved) return saved;
				var rows = document.querySelectorAll('tr[data-ticket-id]');
				for (var i = 0; i < rows.length; i++) {
					if (rows[i].getAttribute('data-ticket-id') === id) return rows[i].getAttribute('data-latest-run') || '';
				}
				return '';
			}
			function setRun(id, run) { if (window.localStorage && run) localStorage.setItem(runKey(id), run); }
			function busy(text) {
				if (progress) { progress.textContent = text || 'Procesando…'; progress.classList.add('is-on'); }
			}
			function idle() { if (progress) progress.classList.remove('is-on'); }
			function payload(data) { return data && data.data !== undefined ? data.data : data; }
			function show(data) {
				var value = payload(data);
				if (out) out.textContent = (value && value.text) ? value.text : JSON.stringify(value, null, 2);
			}
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
			function runnerPost(action, extra) {
				extra = extra || {};
				extra.nonce = runnerNonce;
				var body = new URLSearchParams();
				body.set('action', action);
				Object.keys(extra).forEach(function (k) { body.set(k, extra[k]); });
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
			document.querySelectorAll('.cxp-ticket-preview').forEach(function (btn) {
				btn.addEventListener('click', function (e) {
					e.stopPropagation(); busy('Comparando pila actual con la solicitada…');
					runnerPost('cxp_incident_preview', { id: btn.getAttribute('data-id') }).then(show).catch(show).finally(idle);
				});
			});
			document.querySelectorAll('.cxp-ticket-build-flow').forEach(function (btn) {
				btn.addEventListener('click', function (e) {
					e.stopPropagation();
					var id = btn.getAttribute('data-id');
					if (!window.confirm('Se reemplazará el flujo del ticket por pasos seguros generados desde la URL y el error reportado. ¿Continuar?')) return;
					busy('Creando flujo declarativo seguro…');
					runnerPost('cxp_incident_build_flow', { id: id }).then(show).catch(show).finally(idle);
				});
			});
			document.querySelectorAll('.cxp-ticket-apply').forEach(function (btn) {
				btn.addEventListener('click', function (e) {
					e.stopPropagation();
					var id = btn.getAttribute('data-id');
					if (!window.confirm('Se creará un snapshot completo y se reemplazarán core, plugins y tema según ' + id + '. ¿Continuar?')) return;
					busy('Snapshot y aplicación de la pila exacta en curso. No cierres esta página…');
					runnerPost('cxp_incident_apply', { id: id, run_id: getRun(id) }).then(function (data) {
						var value = payload(data);
						if (data && data.success && value && value.run_id) setRun(id, value.run_id);
						show(data);
						if (value && value.state === 'requiere_reinicio_php') {
							if (window.cxpNotify) window.cxpNotify({ type: 'warning', title: 'Reinicio requerido', message: value.text });
						}
					}).catch(show).finally(idle);
				});
			});
			document.querySelectorAll('.cxp-ticket-run').forEach(function (btn) {
				btn.addEventListener('click', function (e) {
					e.stopPropagation();
					var id = btn.getAttribute('data-id'), run = getRun(id);
					if (!run) { if (out) out.textContent = 'Primero aplica la pila para crear un run.'; return; }
					var chromeWindow = window.open('about:blank', 'cxp-incident-' + id);
					busy('Ejecutando flujo y capturando HTTP/PHP/logs…');
					runnerPost('cxp_incident_preview', { id: id }).then(function (previewData) {
						var value = payload(previewData), target = '';
						(value && value.flow || []).some(function (step) {
							var op = step.op || step.action;
							if ((op === 'open_url' || op === 'request') && step.url) { target = step.url; return true; }
							return false;
						});
						if (chromeWindow) {
							if (target && target.charAt(0) === '/') target = window.location.origin + target;
							try { if (target && new URL(target).origin !== window.location.origin) target = ''; } catch (ignore) { target = ''; }
							chromeWindow.location = target || window.location.origin + '/';
						}
						return runnerPost('cxp_incident_execute', { id: id, run_id: run });
					}).then(show).catch(show).finally(idle);
				});
			});
			document.querySelectorAll('.cxp-ticket-result').forEach(function (btn) {
				btn.addEventListener('click', function (e) {
					e.stopPropagation();
					var id = btn.getAttribute('data-id'), run = getRun(id);
					if (!run) { if (out) out.textContent = 'No hay run seleccionado para ' + id; return; }
					busy('Leyendo evidencia y comparación…');
					runnerPost('cxp_incident_result', { id: id, run_id: run }).then(show).catch(show).finally(idle);
				});
			});
			function openPdf(btn, type) {
				var id = btn.getAttribute('data-id'), run = getRun(id);
				if (!run) { if (out) out.textContent = 'Ejecuta primero el flujo de ' + id; return; }
				var url = ajaxUrl + '?action=cxp_incident_pdf&id=' + encodeURIComponent(id) + '&run_id=' + encodeURIComponent(run) + '&type=' + type + '&nonce=' + encodeURIComponent(runnerNonce);
				window.open(url, '_blank', 'noopener');
			}
			document.querySelectorAll('.cxp-ticket-pdf-client').forEach(function (btn) {
				btn.addEventListener('click', function (e) { e.stopPropagation(); openPdf(btn, 'client'); });
			});
			document.querySelectorAll('.cxp-ticket-pdf-tech').forEach(function (btn) {
				btn.addEventListener('click', function (e) { e.stopPropagation(); openPdf(btn, 'technical'); });
			});
			document.querySelectorAll('.cxp-ticket-restore').forEach(function (btn) {
				btn.addEventListener('click', function (e) {
					e.stopPropagation();
					var id = btn.getAttribute('data-id'), run = getRun(id);
					if (!run) { if (out) out.textContent = 'No hay snapshot seleccionado.'; return; }
					if (!window.confirm('Esto restaurará core, plugins, temas, configuración y base de datos del snapshot ' + run + '. ¿Continuar?')) return;
					busy('Restaurando snapshot completo…');
					runnerPost('cxp_incident_restore', { id: id, run_id: run }).then(show).catch(show).finally(idle);
				});
			});
		})();
		</script>
		<?php
	}

	private static function guard() {
		if ( ! current_user_can( 'manage_options' ) && ! ( function_exists( 'cxp_auto_login_enabled' ) && cxp_auto_login_enabled() ) ) {
			wp_send_json_error( 'No autorizado', 403 );
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), self::NONCE ) ) {
			wp_send_json_error( 'Nonce inválido' );
		}
	}

	public static function ajax_get() {
		self::guard();
		$id   = sanitize_file_name( wp_unslash( $_POST['id'] ?? '' ) );
		$path = class_exists( 'Cxp_Incident_Runner' ) ? Cxp_Incident_Runner::ticket_path( $id ) : ( self::tickets_dir() . '/' . $id . '.json' );
		if ( ! $path || ! is_readable( $path ) ) {
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
		if ( ! in_array( (string) ( $data['schema_version'] ?? '' ), array( '1.0', '1.1' ), true ) ) {
			wp_send_json_error( 'schema_version debe ser 1.0 o 1.1.' );
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
		$plan_dir = self::root() . '/planes/' . $id;
		if ( ! is_dir( $plan_dir ) ) {
			wp_mkdir_p( $plan_dir );
		}
		$plan_readme = $plan_dir . '/README.md';
		if ( ! is_file( $plan_readme ) ) {
			file_put_contents(
				$plan_readme,
				"# Plan {$id}\n\nEstado: pendiente de reproducción.\n\n- Ticket: `../../tickets/{$id}.json`\n- Runs: `../../runs/{$id}/`\n- No completar diagnóstico hasta ejecutar y comparar evidencia.\n"
			);
		}
		wp_send_json_success(
			array(
				'text' => 'Ticket ' . $id . ' creado en incidents/tickets/' . $id . ".json\nSitio: " . $sitio . "\n" . $res . "\nSi es un caso nuevo, documenta el plan en incidents/planes/" . $id . '/',
			)
		);
	}
}

Cxp_Tickets::boot();
