<?php
/**
 * Plugin Name: Incidencias (JSON → ticket)
 * Version: 1.2.0
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
		add_action( 'wp_ajax_cxp_ticket_evidence', array( __CLASS__, 'ajax_evidence' ) );
		add_action( 'wp_ajax_nopriv_cxp_ticket_evidence', array( __CLASS__, 'ajax_evidence' ) );
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
			#cxp-dbg-tickets .cxp-ticket-play{background:#166534!important;border-color:#4ade80!important;color:#dcfce7!important;font-weight:800}
			#cxp-dbg-tickets .cxp-ticket-danger{border-color:#ef4444!important;color:#fecaca!important}
			#cxp-ticket-progress{display:none;margin:8px 0;padding:8px;border:1px solid #2563eb;border-radius:6px;background:#172554;color:#dbeafe}
			#cxp-ticket-progress.is-on{display:block}
			#cxp-dbg-tickets .cxp-ticket-stage{margin:10px 0;padding:10px;border:1px solid #334155;border-radius:8px;background:#0f172a}
			#cxp-dbg-tickets .cxp-ticket-stage h4{margin:0 0 6px;color:#f8fafc}
			#cxp-ticket-result-card{display:none;white-space:pre-wrap;margin:10px 0;padding:12px;border:1px solid #475569;border-radius:8px;background:#020617;color:#e2e8f0}
			#cxp-ticket-result-card.is-on{display:block}
			#cxp-ticket-result-card.is-good{border-color:#22c55e;background:#052e16}
			#cxp-ticket-result-card.is-bad{border-color:#ef4444;background:#450a0a}
			#cxp-ticket-result-card.is-warn{border-color:#f59e0b;background:#451a03}
			#cxp-ticket-result-card h4{margin:0 0 8px;color:#fff}
			#cxp-ticket-result-card ul{margin:6px 0 0 18px}
			#cxp-ticket-evidence{display:none}
			#cxp-ticket-evidence.is-on{display:block}
			#cxp-ticket-evidence textarea{min-height:90px;margin:5px 0}
			#cxp-ticket-evidence input[type=file]{display:block;margin:8px 0;color:#e2e8f0}
		</style>
		<div id="cxp-dbg-tickets">
			<p><strong>Probador genérico WordPress + WooCommerce + Chilexpress</strong> — sirve para cualquier cliente. Copia el formulario, importa su respuesta y pulsa <strong>▶ Probar ticket completo</strong>. El laboratorio instala la pila exacta, ejecuta el flujo seguro y compara el fallo reportado con el resultado real.</p>
			<p>Carpeta: <code><?php echo esc_html( $root ); ?></code></p>
			<div class="cxp-ticket-stage">
				<h4>1. Enviar formulario al cliente</h4>
				<p>El cliente informa PHP, WordPress, tema, todos los plugins y versiones, URL, pasos, resultado esperado y error real. No debe enviar secretos.</p>
				<div class="cxp-lab-row">
					<button type="button" class="cxp-dbg-copy-one cxp-ticket-primary" id="cxp-ticket-copy-tpl">1. Copiar formulario JSON genérico</button>
				</div>
			</div>
			<div class="cxp-ticket-stage">
				<h4>2. Añadir nuevo ticket</h4>
				<textarea id="cxp-ticket-paste" spellcheck="false" placeholder="Pega aquí el JSON completo que devolvió cualquier cliente WordPress"></textarea>
				<div class="cxp-lab-row">
					<button type="button" class="cxp-dbg-copy-one cxp-ticket-primary" id="cxp-ticket-create">2. Validar y añadir nuevo ticket</button>
				</div>
			</div>
			<pre id="cxp-ticket-out">Tickets en disco: <?php echo esc_html( (string) count( $tickets ) ); ?></pre>
			<div id="cxp-ticket-progress" role="status">Preparando…</div>
			<div id="cxp-ticket-result-card" role="status"></div>
			<div id="cxp-ticket-evidence" class="cxp-ticket-stage">
				<h4>Comparación opcional con evidencia del cliente: <span id="cxp-ticket-evidence-id"></span></h4>
				<p>Pega el texto completo del ticket/correo. Si adjuntas un pantallazo, transcribe o describe su texto: la imagen se conserva como evidencia, pero el diagnóstico automático compara texto.</p>
				<textarea id="cxp-ticket-evidence-text" maxlength="30000" placeholder="Texto copiado del ticket, correo o mensaje de error"></textarea>
				<textarea id="cxp-ticket-evidence-notes" maxlength="4000" placeholder="Qué muestra el pantallazo y en qué paso apareció"></textarea>
				<input id="cxp-ticket-evidence-file" type="file" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
				<div class="cxp-lab-row">
					<button type="button" class="cxp-dbg-copy-one cxp-ticket-primary" id="cxp-ticket-evidence-save">Guardar evidencia y usarla al comparar</button>
					<button type="button" class="cxp-dbg-copy-one" id="cxp-ticket-evidence-close">Cerrar</button>
				</div>
			</div>
			<div class="cxp-ticket-stage">
				<h4>3. Elegir un ticket y probarlo</h4>
				<p><strong>Play</strong> hace vista previa → snapshot → instala versiones → verifica la tienda → ejecuta pasos → compara. Si PHP debe cambiar, se detiene y pide reconstruir/reiniciar antes de continuar.</p>
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
						<tr data-ticket-id="<?php echo esc_attr( $row['id'] ); ?>" data-latest-run="<?php echo esc_attr( $row['run'] ); ?>" data-run-state="<?php echo esc_attr( $row['state'] ); ?>">
							<td><?php echo esc_html( $row['id'] ); ?><?php echo $row['state'] ? '<br><small>' . esc_html( $row['state'] ) . '</small>' : ''; ?></td>
							<td><?php echo esc_html( $row['sitio'] ); ?></td>
							<td><?php echo esc_html( wp_trim_words( $row['resumen'], 16 ) ); ?></td>
							<td class="cxp-ticket-actions">
								<button type="button" class="cxp-dbg-copy-one cxp-ticket-play" data-id="<?php echo esc_attr( $row['id'] ); ?>">▶ Probar ticket completo</button>
								<button type="button" class="cxp-dbg-copy-one cxp-ticket-evidence-open" data-id="<?php echo esc_attr( $row['id'] ); ?>">📎 Texto / pantallazo</button>
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
			</div>
			<p><strong>Salida:</strong> el resultado distingue «flujo ejecutado correctamente» de «incidencia reproducida». Ambos PDF se pueden descargar tanto si el fallo aparece como si no aparece. SR-108688 queda solo como caso de referencia.</p>
		</div>
		<script>
		(function () {
			var ajaxUrl = <?php echo wp_json_encode( $ajax ); ?>;
			var nonce = <?php echo wp_json_encode( $nonce ); ?>;
			var runnerNonce = <?php echo wp_json_encode( $runner_nonce ); ?>;
			var tpl = <?php echo wp_json_encode( $template ); ?>;
			var out = document.getElementById('cxp-ticket-out');
			var progress = document.getElementById('cxp-ticket-progress');
			var resultCard = document.getElementById('cxp-ticket-result-card');
			var evidencePanel = document.getElementById('cxp-ticket-evidence');
			var evidenceTicketId = '';
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
			function requireSuccess(data) {
				if (data && data.success) return payload(data);
				var value = payload(data);
				throw new Error(typeof value === 'string' ? value : ((value && value.message) || 'La operación no se pudo completar.'));
			}
			function verdictLabel(code) {
				var labels = {
					coincide: 'INCIDENCIA REPRODUCIDA — el sitio presentó el mismo fallo',
					coincide_parcialmente: 'COINCIDENCIA PARCIAL — aparecieron señales del fallo',
					no_coincide: 'RESULTADO DIFERENTE — apareció otro fallo',
					no_reproducible: 'INCIDENCIA NO REPRODUCIDA — la pila no presentó ese fallo'
				};
				return labels[code] || String(code || 'PENDIENTE').toUpperCase();
			}
			function renderResult(value) {
				value = value || {};
				var comparison = value.comparison || {};
				var steps = value.steps || [];
				var actual = value.actual || value.result || {};
				var verdict = comparison.verdict || '';
				var failed = steps.filter(function (step) { return step && step.ok === false; });
				var executionOk = steps.length > 0 && failed.length === 0;
				var lines = [
					'Prueba: ' + (executionOk ? 'COMPLETADA SIN PASOS FALLIDOS' : (steps.length ? 'COMPLETADA CON ' + failed.length + ' PASO(S) FALLIDO(S)' : 'SIN PASOS EJECUTADOS')),
					'Incidencia: ' + verdictLabel(verdict),
					'Pasos: ' + (actual.steps_ok !== undefined ? actual.steps_ok : (steps.length - failed.length)) + '/' + (actual.steps_total !== undefined ? actual.steps_total : steps.length),
					'HTTP final: ' + (actual.http_status === null || actual.http_status === undefined ? 'sin dato' : actual.http_status),
					'Run: ' + (value.run_id || getRun(value.ticket_id || '') || 'sin run')
				];
				if (comparison.probable_cause && comparison.probable_cause.title) {
					lines.push('Causa probable: ' + comparison.probable_cause.title);
				}
				if (comparison.recommendations && comparison.recommendations.length) {
					lines.push('Cómo corregirlo:');
					comparison.recommendations.forEach(function (item) { lines.push('• ' + item); });
				}
				if (failed.length) {
					lines.push('Fallos: ' + failed.map(function (step) { return step.label || step.action || 'paso'; }).join(' | '));
				}
				if (resultCard) {
					resultCard.className = 'is-on ' + (verdict === 'coincide' || verdict === 'no_coincide' ? 'is-bad' : (verdict === 'coincide_parcialmente' ? 'is-warn' : 'is-good'));
					resultCard.textContent = lines.join('\n');
				}
				if (out) out.textContent = JSON.stringify(value, null, 2);
			}
			function show(data) {
				var value = payload(data);
				if (value && value.comparison) {
					renderResult(value);
					return;
				}
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
			function evidencePost(id) {
				var form = new FormData();
				var fileInput = document.getElementById('cxp-ticket-evidence-file');
				form.set('action', 'cxp_ticket_evidence');
				form.set('nonce', nonce);
				form.set('id', id);
				form.set('ticket_texto', (document.getElementById('cxp-ticket-evidence-text') || {}).value || '');
				form.set('capturas_notas', (document.getElementById('cxp-ticket-evidence-notes') || {}).value || '');
				if (fileInput && fileInput.files && fileInput.files[0]) form.set('captura', fileInput.files[0]);
				return fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: form }).then(function (r) { return r.json(); });
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
			document.querySelectorAll('.cxp-ticket-evidence-open').forEach(function (btn) {
				btn.addEventListener('click', function (e) {
					e.stopPropagation();
					evidenceTicketId = btn.getAttribute('data-id');
					if (evidencePanel) evidencePanel.classList.add('is-on');
					var idLabel = document.getElementById('cxp-ticket-evidence-id');
					if (idLabel) idLabel.textContent = evidenceTicketId;
					post('cxp_ticket_get', { id: evidenceTicketId }).then(function (data) {
						var value = requireSuccess(data), ticket = {};
						try { ticket = JSON.parse(value.text || '{}'); } catch (ignore) {}
						var evidence = ticket.evidencia || {};
						var text = document.getElementById('cxp-ticket-evidence-text');
						var notes = document.getElementById('cxp-ticket-evidence-notes');
						if (text) text.value = evidence.ticket_texto || '';
						if (notes) notes.value = evidence.capturas_notas || '';
					}).catch(show);
				});
			});
			var evidenceSave = document.getElementById('cxp-ticket-evidence-save');
			if (evidenceSave) evidenceSave.addEventListener('click', function (e) {
				e.stopPropagation();
				if (!evidenceTicketId) return;
				busy('Guardando texto y pantallazo del cliente…');
				evidencePost(evidenceTicketId).then(function (data) {
					var value = requireSuccess(data);
					show({ data: value });
					if (window.cxpNotify) window.cxpNotify({ type: 'success', title: 'Evidencia guardada', message: 'Se comparará en el próximo Play.' });
				}).catch(function (err) { show({ data: err.message || String(err) }); }).finally(idle);
			});
			var evidenceClose = document.getElementById('cxp-ticket-evidence-close');
			if (evidenceClose) evidenceClose.addEventListener('click', function (e) {
				e.stopPropagation();
				if (evidencePanel) evidencePanel.classList.remove('is-on');
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
			document.querySelectorAll('.cxp-ticket-play').forEach(function (btn) {
				btn.addEventListener('click', function (e) {
					e.stopPropagation();
					var id = btn.getAttribute('data-id');
					var ticketRow = btn.closest ? btn.closest('tr[data-ticket-id]') : null;
					var resumeRun = ticketRow && ticketRow.getAttribute('data-run-state') === 'requiere_reinicio_php' ? getRun(id) : '';
					if (!window.confirm('Se probará ' + id + ' de principio a fin: snapshot, versiones exactas, verificación, flujo y comparación. El sitio quedará con la pila del cliente hasta pulsar Restaurar snapshot. ¿Continuar?')) return;
					var chromeWindow = window.open('about:blank', 'cxp-incident-' + id);
					var preview = null;
					var stopped = false;
					if (resultCard) resultCard.className = '';
					busy('1/4 Validando ticket y comparando versiones…');
					runnerPost('cxp_incident_preview', { id: id }).then(function (data) {
						preview = requireSuccess(data);
						if (!preview.valid) throw new Error('Ticket inválido:\n' + (preview.errors || []).join('\n'));
						if ((preview.flow || []).length) return preview;
						busy('1/4 Creando un flujo seguro desde el reporte…');
						return runnerPost('cxp_incident_build_flow', { id: id }).then(function (built) {
							requireSuccess(built);
							return runnerPost('cxp_incident_preview', { id: id }).then(requireSuccess);
						});
					}).then(function (readyPreview) {
						preview = readyPreview;
						var target = '';
						(preview.flow || []).some(function (step) {
							var op = step.op || step.action;
							if ((op === 'open_url' || op === 'request') && step.url) { target = step.url; return true; }
							return false;
						});
						if (chromeWindow) {
							if (target && target.charAt(0) === '/') target = window.location.origin + target;
							try { if (target && new URL(target).origin !== window.location.origin) target = ''; } catch (ignore) { target = ''; }
							chromeWindow.location = target || window.location.origin + '/';
						}
						busy('2/4 Creando snapshot e instalando WordPress, tema y plugins exactos…');
						return runnerPost('cxp_incident_apply', { id: id, run_id: resumeRun });
					}).then(function (applyData) {
						var applied = requireSuccess(applyData);
						if (applied.run_id) setRun(id, applied.run_id);
						if (applied.state === 'requiere_reinicio_php') {
							stopped = true;
							if (resultCard) {
								resultCard.className = 'is-on is-warn';
								resultCard.textContent = 'PAUSA: ' + applied.text + '\nRun guardado: ' + applied.run_id + '\nDespués del reinicio vuelve a pulsar Play para continuar.';
							}
							return null;
						}
						busy('3/4 Pila verificada. Ejecutando pasos y capturando HTTP, PHP, JavaScript y logs…');
						return runnerPost('cxp_incident_execute', { id: id, run_id: applied.run_id });
					}).then(function (executeData) {
						if (stopped) return;
						var executed = requireSuccess(executeData);
						busy('4/4 Comparación terminada. Preparando salida y reportes…');
						renderResult(executed);
						if (window.cxpNotify) window.cxpNotify({
							type: executed.comparison && executed.comparison.verdict === 'coincide' ? 'warning' : 'success',
							title: verdictLabel(executed.comparison && executed.comparison.verdict),
							message: 'Descarga PDF cliente o PDF técnico. Usa Restaurar snapshot al terminar.'
						});
					}).catch(function (err) {
						if (chromeWindow && chromeWindow.location.href === 'about:blank') chromeWindow.close();
						if (resultCard) {
							resultCard.className = 'is-on is-bad';
							resultCard.textContent = 'PRUEBA DETENIDA\n' + (err && err.message ? err.message : String(err)) + '\nSi la pila quedó parcial, usa Restaurar snapshot.';
						}
						if (out) out.textContent = err && err.stack ? err.stack : String(err);
					}).finally(idle);
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

	private static function validate_intake( $data ) {
		$errors = class_exists( 'Cxp_Incident_Runner' ) ? Cxp_Incident_Runner::validate_ticket( $data ) : array();
		if ( '1.1' !== (string) ( $data['schema_version'] ?? '' ) ) {
			return $errors;
		}
		$required = array(
			'sintoma.url_donde_falla' => $data['sintoma']['url_donde_falla'] ?? '',
			'sintoma.resultado_esperado' => $data['sintoma']['resultado_esperado'] ?? '',
			'sintoma.resultado_obtenido' => $data['sintoma']['resultado_obtenido'] ?? '',
			'pila.php' => $data['pila']['php'] ?? '',
			'pila.wordpress' => $data['pila']['wordpress'] ?? '',
			'pila.tema.slug' => $data['pila']['tema']['slug'] ?? '',
			'pila.tema.version' => $data['pila']['tema']['version'] ?? '',
		);
		foreach ( $required as $field => $value ) {
			$value = trim( (string) $value );
			if ( '' === $value || 'no_se' === strtolower( $value ) || false !== stripos( $value, 'que debia' ) || false !== stripos( $value, 'que ocurrio' ) ) {
				$errors[] = 'Falta completar ' . $field;
			}
		}
		foreach ( array(
			'pila.wordpress' => $data['pila']['wordpress'] ?? '',
			'pila.tema.version' => $data['pila']['tema']['version'] ?? '',
		) as $field => $version ) {
			$version = strtolower( trim( (string) $version ) );
			if ( '' === $version || 'no_se' === $version || false !== strpos( $version, 'x' ) || 'latest' === $version ) {
				$errors[] = $field . ' debe ser exacta';
			}
		}
		if ( ! preg_match( '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', (string) ( $data['pila']['php'] ?? '' ) ) ) {
			$errors[] = 'pila.php debe ser exacta (ej. 8.2.29)';
		}
		$steps = array_filter( (array) ( $data['sintoma']['pasos_para_reproducir'] ?? array() ), static function ( $step ) {
			$step = trim( (string) $step );
			return '' !== $step && false === stripos( $step, 'Entré a ...' ) && false === stripos( $step, 'Pulsé ...' );
		} );
		if ( ! $steps ) {
			$errors[] = 'Falta al menos un paso real en sintoma.pasos_para_reproducir';
		}
		$plugins = (array) ( $data['plugins'] ?? array() );
		if ( count( $plugins ) < 2 ) {
			$errors[] = 'Incluye WooCommerce, Chilexpress y todos los demás plugins (también los inactivos)';
		}
		foreach ( $plugins as $index => $plugin ) {
			$version = strtolower( trim( (string) ( $plugin['version'] ?? '' ) ) );
			if ( '' === $version || 'no_se' === $version || false !== strpos( $version, 'x' ) ) {
				$errors[] = 'plugins[' . $index . '].version debe ser exacta';
			}
		}
		return array_values( array_unique( $errors ) );
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

	public static function ajax_evidence() {
		self::guard();
		$id = sanitize_file_name( wp_unslash( $_POST['id'] ?? '' ) );
		$path = class_exists( 'Cxp_Incident_Runner' ) ? Cxp_Incident_Runner::ticket_path( $id ) : ( self::tickets_dir() . '/' . $id . '.json' );
		if ( ! $path || ! is_readable( $path ) ) {
			wp_send_json_error( 'No está el ticket ' . $id );
		}
		$ticket = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $ticket ) ) {
			wp_send_json_error( 'El ticket no contiene JSON válido.' );
		}
		$text = substr( sanitize_textarea_field( wp_unslash( $_POST['ticket_texto'] ?? '' ) ), 0, 30000 );
		$notes = substr( sanitize_textarea_field( wp_unslash( $_POST['capturas_notas'] ?? '' ) ), 0, 4000 );
		$ticket['evidencia'] = is_array( $ticket['evidencia'] ?? null ) ? $ticket['evidencia'] : array();
		$ticket['evidencia']['ticket_texto'] = $text;
		$ticket['evidencia']['capturas_notas'] = $notes;
		$saved_file = (string) ( $ticket['evidencia']['captura_archivo'] ?? '' );
		if ( ! empty( $_FILES['captura']['name'] ) ) {
			$file = $_FILES['captura'];
			if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
				wp_send_json_error( 'No se pudo recibir el pantallazo.' );
			}
			if ( (int) ( $file['size'] ?? 0 ) > 5 * 1024 * 1024 ) {
				wp_send_json_error( 'El pantallazo supera 5 MB.' );
			}
			$extension = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
			if ( ! in_array( $extension, array( 'png', 'jpg', 'jpeg', 'webp' ), true ) ) {
				wp_send_json_error( 'Formato no permitido. Usa PNG, JPG o WEBP.' );
			}
			$image = @getimagesize( (string) $file['tmp_name'] );
			if ( ! is_array( $image ) || ! in_array( (string) ( $image['mime'] ?? '' ), array( 'image/png', 'image/jpeg', 'image/webp' ), true ) ) {
				wp_send_json_error( 'El archivo no es una imagen válida.' );
			}
			$dir = self::root() . '/evidence/' . $id;
			if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
				wp_send_json_error( 'No se pudo crear la carpeta de evidencia.' );
			}
			$name = gmdate( 'Ymd-His' ) . '-' . sanitize_file_name( pathinfo( (string) $file['name'], PATHINFO_FILENAME ) ) . '.' . $extension;
			if ( ! move_uploaded_file( (string) $file['tmp_name'], $dir . '/' . $name ) ) {
				wp_send_json_error( 'No se pudo guardar el pantallazo.' );
			}
			$saved_file = 'evidence/' . $id . '/' . $name;
			$ticket['evidencia']['captura_archivo'] = $saved_file;
		}
		$json = wp_json_encode( $ticket, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === file_put_contents( $path, $json . "\n" ) ) {
			wp_send_json_error( 'No se pudo actualizar el ticket.' );
		}
		wp_send_json_success( array(
			'text' => 'Evidencia guardada para ' . $id . ( $saved_file ? "\nPantallazo: incidents/" . $saved_file : '' ) . "\nSe usará en el próximo análisis y en los PDF.",
			'captura_archivo' => $saved_file,
		) );
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
		$errors = self::validate_intake( $data );
		if ( $errors ) {
			wp_send_json_error( "El ticket todavía no tiene toda la información necesaria:\n- " . implode( "\n- ", $errors ) );
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
