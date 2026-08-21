<?php
/**
 * Plugin Name: Pila de versiones (WP / Woo / Chilexpress)
 * Version: 1.0.0
 * Description: Reinstala o cambia versiones para probar deploys. Botón default = pila del cliente (WP 7.0.3, Woo 11.0.1, Chilexpress 1.4.0).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cxp_Stack_Versions {
	const NONCE = 'cxp_stack_versions';
	const CLIENT_WP  = '7.0.3';
	const CLIENT_WC  = '11.0.1';
	const CLIENT_CXP = '1.4.0';
	const CLIENT_PHP = '8.4.19';

	public static function boot() {
		add_action( 'cxp_debug_console_panels', array( __CLASS__, 'console_panel' ), 2 );
		add_action( 'wp_ajax_cxp_stack_switch', array( __CLASS__, 'ajax_switch' ) );
		add_action( 'wp_ajax_nopriv_cxp_stack_switch', array( __CLASS__, 'ajax_switch' ) );
		add_action( 'wp_ajax_cxp_stack_restore_client', array( __CLASS__, 'ajax_restore_client' ) );
		add_action( 'wp_ajax_nopriv_cxp_stack_restore_client', array( __CLASS__, 'ajax_restore_client' ) );
		add_action( 'wp_ajax_cxp_stack_reload_full', array( __CLASS__, 'ajax_reload_full' ) );
		add_action( 'wp_ajax_nopriv_cxp_stack_reload_full', array( __CLASS__, 'ajax_reload_full' ) );
		add_action( 'wp_ajax_cxp_stack_set_email', array( __CLASS__, 'ajax_set_email' ) );
		add_action( 'wp_ajax_nopriv_cxp_stack_set_email', array( __CLASS__, 'ajax_set_email' ) );
		add_action( 'wp_ajax_cxp_stack_switch_php', array( __CLASS__, 'ajax_switch_php' ) );
		add_action( 'wp_ajax_nopriv_cxp_stack_switch_php', array( __CLASS__, 'ajax_switch_php' ) );
		add_action( 'wp_ajax_cxp_stack_update_all_latest', array( __CLASS__, 'ajax_update_all_latest' ) );
		add_action( 'wp_ajax_cxp_stack_apply', array( __CLASS__, 'ajax_apply' ) );
		add_action( 'wp_ajax_nopriv_cxp_stack_apply', array( __CLASS__, 'ajax_apply' ) );
		add_action( 'wp_ajax_cxp_stack_upload_zip', array( __CLASS__, 'ajax_upload_zip' ) );
		add_action( 'wp_ajax_nopriv_cxp_stack_upload_zip', array( __CLASS__, 'ajax_upload_zip' ) );
	}

	public static function console_panel() {
		$ajax    = admin_url( 'admin-ajax.php' );
		$nonce   = wp_create_nonce( self::NONCE );
		$php     = PHP_VERSION;
		$wp      = get_bloginfo( 'version' );
		$theme   = wp_get_theme();
		$parent  = $theme->parent();
		$email    = function_exists( 'cxp_lab_email' ) ? cxp_lab_email() : get_option( 'admin_email' );
		$php_next = self::php_target_version();
		$php_opts = self::php_version_choices( $php );
		$wp_opts  = array( '6.7.2', '6.8.3', '7.0.3', '7.1' );
		if ( ! in_array( $wp, $wp_opts, true ) ) {
			array_unshift( $wp_opts, $wp );
		}
		$plugins = self::switchable_plugins();
		?>
		<style>
			#cxp-dbg-stack{margin:0 0 12px;padding:10px 12px;border:1px solid #ca8a04;border-radius:8px;background:#052e16}
			#cxp-dbg-stack p,#cxp-dbg-stack td,#cxp-dbg-stack th,#cxp-dbg-stack label,#cxp-dbg-stack li{margin:0 0 8px;color:#bbf7d0 !important;-webkit-text-fill-color:#bbf7d0}
			#cxp-dbg-stack p strong,#cxp-dbg-stack h4{color:#fff200 !important;-webkit-text-fill-color:#fff200}
			#cxp-dbg-stack .cxp-lab-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:8px 0}
			#cxp-dbg-stack select,#cxp-dbg-stack input[type=text],#cxp-dbg-stack input[type=email],#cxp-dbg-stack input[type=file],#cxp-dbg-stack option{color:#e8edf5 !important;-webkit-text-fill-color:#e8edf5 !important;background:#1e293b !important;background-color:#1e293b !important;border:1px solid #64748b !important;border-radius:6px;padding:4px 8px;min-width:140px;color-scheme:dark}
			#cxp-dbg-stack .ok{color:#86efac !important;-webkit-text-fill-color:#86efac}
			#cxp-dbg-stack .bad{color:#fca5a5 !important;-webkit-text-fill-color:#fca5a5}
			#cxp-dbg-stack pre{margin:8px 0 0;max-height:220px;overflow:auto;white-space:pre-wrap;color:#f8fafc !important;-webkit-text-fill-color:#f8fafc !important;background:#020617 !important;padding:8px;border-radius:6px;border:1px solid #334155}
			#cxp-dbg-stack button.cxp-stack-default,#cxp-dbg-stack button.cxp-stack-reload{border-color:#ca8a04;background:#854d0e;color:#fef08a !important;-webkit-text-fill-color:#fef08a;font-weight:700}
			#cxp-dbg-stack table{width:100%;border-collapse:collapse;margin:8px 0}
			#cxp-dbg-stack th,#cxp-dbg-stack td{padding:6px 8px;border-bottom:1px solid #14532d;text-align:left;vertical-align:middle}
			#cxp-dbg-stack th{color:#fff200 !important;-webkit-text-fill-color:#fff200;font-size:11px;text-transform:uppercase}
			#cxp-dbg-stack .cxp-step{margin:10px 0;padding:10px;border:1px solid #166534;border-radius:8px;background:#031b0f}
			#cxp-dbg-stack .cxp-step > h4{margin:0 0 4px;font-size:13px}
			#cxp-dbg-stack .cxp-step > .cxp-help{margin:0 0 8px;font-size:12px;opacity:.85}
			#cxp-dbg-stack .cxp-now{display:flex;flex-wrap:wrap;gap:14px;padding:8px 10px;border:1px solid #14532d;border-radius:8px;background:#022c16;font-size:12px}
			#cxp-dbg-stack .cxp-now b{color:#fff200 !important;-webkit-text-fill-color:#fff200}
			#cxp-dbg-stack tr.is-changed{background:#1c2a10}
			#cxp-dbg-stack tr.is-changed td:first-child::before{content:'● ';color:#fbbf24}
			#cxp-stack-apply{background:#166534 !important;border-color:#4ade80 !important;color:#dcfce7 !important;-webkit-text-fill-color:#dcfce7;font-weight:800;padding:7px 14px}
			#cxp-stack-apply[disabled]{opacity:.45;font-weight:600}
			#cxp-stack-pending{font-size:12px}
		</style>
		<div id="cxp-dbg-stack">
			<p><strong>Laboratorio de versiones</strong> — cambia WordPress, los plugins y sus versiones, y aplica todo junto. Cada instalación baja el ZIP oficial de wordpress.org.</p>
			<div class="cxp-now">
				<span><b>PHP</b> <?php echo esc_html( $php ); ?></span>
				<span><b>WordPress</b> <?php echo esc_html( $wp ); ?></span>
				<span><b>Tema</b> <?php echo esc_html( $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) ); ?><?php echo $parent ? esc_html( ' / ' . $parent->get( 'Name' ) . ' ' . $parent->get( 'Version' ) ) : ''; ?></span>
				<span><b>Plugins</b> <?php echo esc_html( (string) count( $plugins ) ); ?></span>
			</div>

			<div class="cxp-step">
				<h4>1. Elige las versiones que quieres</h4>
				<p class="cxp-help">Cambia lo que necesites en la tabla y pulsa <strong>Aplicar cambios</strong> al final. Deja igual lo que no quieras tocar. La columna <em>Activo</em> enciende o apaga el plugin.</p>
				<table>
					<thead>
						<tr>
							<th>Componente</th>
							<th>Instalada</th>
							<th>Nueva versión</th>
							<th>Activo</th>
						</tr>
					</thead>
					<tbody>
						<tr data-row="wordpress">
							<td><strong>WordPress</strong></td>
							<td class="ok" data-current="<?php echo esc_attr( $wp ); ?>"><?php echo esc_html( $wp ); ?></td>
							<td>
								<select id="cxp-stack-wp">
									<?php foreach ( $wp_opts as $v ) : ?>
										<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $v, $wp ); ?>><?php echo esc_html( $v ); ?><?php echo self::CLIENT_WP === $v ? ' (cliente)' : ''; ?></option>
									<?php endforeach; ?>
								</select>
								<input type="text" id="cxp-stack-wp-custom" placeholder="otra, ej. 6.8.1" size="12">
							</td>
							<td>—</td>
						</tr>
						<?php foreach ( $plugins as $slug => $meta ) : ?>
							<tr data-row="plugin" data-slug="<?php echo esc_attr( $slug ); ?>" data-source="<?php echo esc_attr( $meta['source'] ); ?>">
								<td><?php echo esc_html( $meta['name'] ); ?><br><small><?php echo esc_html( $slug ); ?></small></td>
								<td class="ok" data-current="<?php echo esc_attr( $meta['current'] ); ?>"><?php echo esc_html( $meta['current'] ); ?></td>
								<td>
									<?php if ( 'repo' === $meta['source'] ) : ?>
										<span>1.4.0 intacto (copia del repo)</span>
									<?php else : ?>
										<select class="cxp-stack-plugin-ver" data-slug="<?php echo esc_attr( $slug ); ?>">
											<?php foreach ( $meta['versions'] as $v ) : ?>
												<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $v, $meta['current'] ); ?>><?php echo esc_html( $v ); ?></option>
											<?php endforeach; ?>
										</select>
										<input type="text" class="cxp-stack-plugin-custom" data-slug="<?php echo esc_attr( $slug ); ?>" placeholder="otra, ej. 10.0.0" size="11">
									<?php endif; ?>
								</td>
								<td>
									<input type="checkbox" class="cxp-stack-plugin-active" data-slug="<?php echo esc_attr( $slug ); ?>" data-was="<?php echo ! empty( $meta['active'] ) ? '1' : '0'; ?>" <?php checked( ! empty( $meta['active'] ) ); ?>>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<div class="cxp-lab-row">
					<button type="button" id="cxp-stack-apply" disabled>Aplicar cambios</button>
					<button type="button" class="cxp-dbg-copy-one" id="cxp-stack-reset">Descartar cambios</button>
					<span id="cxp-stack-pending">Sin cambios pendientes.</span>
				</div>
			</div>

			<div class="cxp-step">
				<h4>2. Instalar un plugin que no está en la tabla</h4>
				<p class="cxp-help">Para plugins públicos de wordpress.org. El <em>slug</em> es la carpeta del plugin, la misma que aparece en su URL. Deja la versión vacía para instalar la última.</p>
				<div class="cxp-lab-row">
					<input type="text" id="cxp-stack-extra-slug" placeholder="slug, ej. jetpack" size="18">
					<input type="text" id="cxp-stack-extra-ver" placeholder="versión (opcional)" size="14">
					<button type="button" class="cxp-dbg-copy-one" id="cxp-stack-extra-go">Instalar desde wordpress.org</button>
				</div>
			</div>

			<div class="cxp-step">
				<h4>3. Instalar un plugin desde un archivo ZIP</h4>
				<p class="cxp-help">Para plugins premium o propios que no están en wordpress.org. Se acepta un <code>.zip</code> de hasta 64 MB y queda guardado en <code>drop-plugins/</code> para poder repetirlo.</p>
				<div class="cxp-lab-row">
					<input type="file" id="cxp-stack-zip" accept=".zip,application/zip">
					<label><input type="checkbox" id="cxp-stack-zip-activate" checked> Activarlo al instalar</label>
					<button type="button" class="cxp-dbg-copy-one" id="cxp-stack-zip-go">Subir e instalar ZIP</button>
				</div>
			</div>

			<div class="cxp-step">
				<h4>4. Acciones sobre toda la pila</h4>
				<p class="cxp-help">Estas acciones tardan más porque reinstalan varios paquetes. <strong>Actualizar todo a latest</strong> sirve para explorar, no para reproducir una incidencia antigua.</p>
				<div class="cxp-lab-row">
					<button type="button" class="cxp-stack-reload" id="cxp-stack-reload">Reinstalar WordPress y los plugins de la tabla</button>
					<button type="button" class="cxp-stack-reload" id="cxp-stack-latest">Actualizar todo a latest</button>
					<button type="button" class="cxp-stack-default" id="cxp-stack-default">Volver a la pila del cliente (WP <?php echo esc_html( self::CLIENT_WP ); ?> · Woo <?php echo esc_html( self::CLIENT_WC ); ?> · CXP <?php echo esc_html( self::CLIENT_CXP ); ?>)</button>
				</div>
			</div>

			<div class="cxp-step">
				<h4>5. Cambiar PHP (necesita reiniciar el servidor)</h4>
				<p class="cxp-help">PHP no se puede cambiar con el sitio encendido. Aquí se prepara el runtime y luego hay que reiniciar <code>start.sh</code> o reconstruir Docker. Siguiente arranque: <code><?php echo esc_html( $php_next ); ?></code><?php echo $php === $php_next ? '' : ' — distinto al actual, falta reiniciar'; ?>.</p>
				<div class="cxp-lab-row">
					<select id="cxp-stack-php">
						<?php foreach ( $php_opts as $v ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $v, $php_next ); ?>><?php echo esc_html( $v ); ?><?php echo self::CLIENT_PHP === $v ? ' (cliente)' : ''; ?></option>
						<?php endforeach; ?>
					</select>
					<input type="text" id="cxp-stack-php-custom" placeholder="otra, ej. 8.3.33" size="14">
					<button type="button" class="cxp-dbg-copy-one" id="cxp-stack-php-go">Preparar esta PHP</button>
				</div>
			</div>

			<div class="cxp-step">
				<h4>6. Correo del laboratorio</h4>
				<p class="cxp-help">Se aplica al admin de WordPress, al usuario <code>admin</code>, al remitente de WooCommerce y de Chilexpress, y al checkout de prueba.</p>
				<div class="cxp-lab-row">
					<input type="email" id="cxp-stack-email" value="<?php echo esc_attr( $email ); ?>" size="36" placeholder="correo@dominio.cl">
					<button type="button" class="cxp-dbg-copy-one" id="cxp-stack-email-go">Aplicar este correo</button>
				</div>
			</div>

			<pre id="cxp-stack-out">Cambia versiones en la tabla y pulsa «Aplicar cambios». La página se recarga sola cuando termina bien.</pre>
		</div>
		<script>
		(function () {
			var ajaxUrl = <?php echo wp_json_encode( $ajax ); ?>;
			var nonce = <?php echo wp_json_encode( $nonce ); ?>;
			var out = document.getElementById('cxp-stack-out');
			function post(action, extra, confirmMsg) {
				if (confirmMsg && !confirm(confirmMsg)) return;
				out.textContent = 'Trabajando… puede tardar (descarga ZIP). La página se recargará si sale bien.';
				var body = new URLSearchParams();
				body.set('action', action);
				body.set('nonce', nonce);
				Object.keys(extra || {}).forEach(function (k) { body.set(k, extra[k]); });
				fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body.toString()
				}).then(function (r) { return r.json(); }).then(function (data) {
					var payload = data && data.data !== undefined ? data.data : data;
					var text = (payload && payload.text) ? payload.text : (typeof payload === 'string' ? payload : JSON.stringify(payload, null, 2));
					out.textContent = text || 'Listo';
					var shouldReload = data && data.success && !(payload && payload.reload === false);
					if (shouldReload) {
						setTimeout(function () { window.location.reload(); }, 900);
					}
				}).catch(function (e) { out.textContent = 'Falló: ' + e; });
			}
			function wpVersion() {
				var custom = (document.getElementById('cxp-stack-wp-custom').value || '').trim();
				return custom || document.getElementById('cxp-stack-wp').value;
			}
			function pluginVersion(slug) {
				var custom = document.querySelector('.cxp-stack-plugin-custom[data-slug="' + slug + '"]');
				if (custom && custom.value.trim()) return custom.value.trim();
				var sel = document.querySelector('.cxp-stack-plugin-ver[data-slug="' + slug + '"]');
				return sel ? sel.value : '';
			}
			function phpVersion() {
				var custom = (document.getElementById('cxp-stack-php-custom').value || '').trim();
				return custom || document.getElementById('cxp-stack-php').value;
			}
			// --- Cambios pendientes ------------------------------------------------
			function pluginRows() {
				return Array.prototype.slice.call(document.querySelectorAll('#cxp-dbg-stack tr[data-row="plugin"]'));
			}
			function collectChanges() {
				var changes = { plugins: {} }, list = [];
				var wpRow = document.querySelector('#cxp-dbg-stack tr[data-row="wordpress"]');
				var wpNow = wpRow ? wpRow.querySelector('td[data-current]').getAttribute('data-current') : '';
				var wpWanted = wpVersion();
				if (wpWanted && wpWanted !== wpNow) {
					changes.wordpress = wpWanted;
					list.push('WordPress ' + wpNow + ' → ' + wpWanted);
					if (wpRow) wpRow.classList.add('is-changed'); 
				} else if (wpRow) {
					wpRow.classList.remove('is-changed');
				}
				pluginRows().forEach(function (row) {
					var slug = row.getAttribute('data-slug');
					var source = row.getAttribute('data-source');
					var now = row.querySelector('td[data-current]').getAttribute('data-current');
					var box = row.querySelector('.cxp-stack-plugin-active');
					var wasActive = box ? box.getAttribute('data-was') === '1' : false;
					var isActive = box ? box.checked : wasActive;
					var wanted = source === 'repo' ? now : pluginVersion(slug);
					var versionChanged = Boolean(wanted) && wanted !== now;
					var activeChanged = isActive !== wasActive;
					if (versionChanged || activeChanged) {
						changes.plugins[slug] = { version: versionChanged ? wanted : '', active: isActive };
						row.classList.add('is-changed');
						if (versionChanged) list.push(slug + ' ' + now + ' → ' + wanted);
						if (activeChanged) list.push(slug + ': ' + (isActive ? 'activar' : 'desactivar'));
					} else {
                        row.classList.remove('is-changed');
					}
				});
				return { changes: changes, list: list };
			}
			function refreshPending() {
				var pending = collectChanges();
				var label = document.getElementById('cxp-stack-pending');
				var apply = document.getElementById('cxp-stack-apply');
				if (label) {
					label.textContent = pending.list.length
						? pending.list.length + ' cambio(s) pendiente(s): ' + pending.list.join(' · ')
						: 'Sin cambios pendientes.';
				}
				if (apply) apply.disabled = pending.list.length === 0;
				return pending;
			}
			document.querySelectorAll('#cxp-dbg-stack select, #cxp-dbg-stack input[type=text], #cxp-dbg-stack input[type=checkbox]').forEach(function (el) {
				if (el.id === 'cxp-stack-php' || el.id === 'cxp-stack-php-custom') return;
				if (el.id === 'cxp-stack-extra-slug' || el.id === 'cxp-stack-extra-ver') return;
				if (el.id === 'cxp-stack-zip-activate') return;
				el.addEventListener('change', refreshPending);
				el.addEventListener('input', refreshPending);
			});
			refreshPending();

			var apply = document.getElementById('cxp-stack-apply');
			if (apply) apply.addEventListener('click', function (e) {
				e.stopPropagation();
				var pending = refreshPending();
				if (!pending.list.length) return;
				post(
					'cxp_stack_apply',
					{ changes: JSON.stringify(pending.changes) },
					'Se aplicaran ' + pending.list.length + ' cambio(s):\n\n' + pending.list.join('\n') + '\n\n¿Continuar?'
				);
			});
			var reset = document.getElementById('cxp-stack-reset');
			if (reset) reset.addEventListener('click', function (e) {
				e.stopPropagation();
				window.location.reload();
			});

			// --- ZIP ---------------------------------------------------------------
			var zipGo = document.getElementById('cxp-stack-zip-go');
			if (zipGo) zipGo.addEventListener('click', function (e) {
				e.stopPropagation();
				var input = document.getElementById('cxp-stack-zip');
				if (!input || !input.files || !input.files[0]) { out.textContent = 'Elige primero un archivo .zip'; return; }
				var file = input.files[0];
				if (!confirm('¿Instalar el plugin del archivo ' + file.name + '?')) return;
				out.textContent = 'Subiendo e instalando ' + file.name + '…';
				var form = new FormData();
				form.set('action', 'cxp_stack_upload_zip');
				form.set('nonce', nonce);
				form.set('zip', file);
				if (document.getElementById('cxp-stack-zip-activate').checked) form.set('activate', '1');
				fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: form })
					.then(function (r) { return r.json(); })
					.then(function (data) {
						var payload = data && data.data !== undefined ? data.data : data;
						out.textContent = (payload && payload.text) ? payload.text : (typeof payload === 'string' ? payload : JSON.stringify(payload, null, 2));
						if (data && data.success) setTimeout(function () { window.location.reload(); }, 1200);
					})
					.catch(function (err) { out.textContent = 'Falló: ' + err; });
			});

			var phpGo = document.getElementById('cxp-stack-php-go');
			if (phpGo) phpGo.addEventListener('click', function (e) {
				e.stopPropagation();
				var v = phpVersion();
				post('cxp_stack_switch_php', { version: v }, '¿Preparar PHP ' + v + '? No cambia el proceso actual: después hay que reiniciar el servidor.');
			});
			var extraGo = document.getElementById('cxp-stack-extra-go');
			if (extraGo) extraGo.addEventListener('click', function (e) {
				e.stopPropagation();
				var slug = (document.getElementById('cxp-stack-extra-slug').value || '').trim().toLowerCase();
				var v = (document.getElementById('cxp-stack-extra-ver').value || '').trim();
				if (!slug) { out.textContent = 'Falta el slug del plugin (carpeta en wordpress.org).'; return; }
				post('cxp_stack_switch', { target: slug, version: v }, '¿Instalar ' + slug + ' ' + (v || 'latest') + '?');
			});
			var reload = document.getElementById('cxp-stack-reload');
			if (reload) reload.addEventListener('click', function (e) {
				e.stopPropagation();
				var plugins = {};
				pluginRows().forEach(function (row) {
					var slug = row.getAttribute('data-slug');
					var source = row.getAttribute('data-source');
					plugins[slug] = source === 'repo' ? '1.4.0' : pluginVersion(slug);
				});
				post('cxp_stack_reload_full', { version: wpVersion(), plugins: JSON.stringify(plugins) }, '¿Reinstalar WordPress ' + wpVersion() + ' y los plugins de la tabla? wp-content (uploads/DB) se conserva.');
			});
			var def = document.getElementById('cxp-stack-default');
			if (def) def.addEventListener('click', function (e) {
				e.stopPropagation();
				post('cxp_stack_restore_client', {}, '¿Volver a WP 7.0.3 + Woo 11.0.1 + Chilexpress 1.4.0 + Woodmart Child?');
			});
			var latest = document.getElementById('cxp-stack-latest');
			if (latest) latest.addEventListener('click', function (e) {
				e.stopPropagation();
				post('cxp_stack_update_all_latest', {}, 'Esta utilidad NO reproduce una incidencia: instalará latest para WordPress y todos los plugins públicos de la tabla. ¿Continuar?');
			});
			var emailGo = document.getElementById('cxp-stack-email-go');
			if (emailGo) emailGo.addEventListener('click', function (e) {
				e.stopPropagation();
				var email = (document.getElementById('cxp-stack-email').value || '').trim();
				post('cxp_stack_set_email', { email: email }, '¿Aplicar ' + email + ' a admin, Woo, Chilexpress y checkout?');
			});
		})();
		</script>
		<?php
	}

	public static function ajax_switch() {
		self::guard();
		@set_time_limit( 180 );
		$target  = sanitize_key( wp_unslash( $_POST['target'] ?? '' ) );
		$version = sanitize_text_field( wp_unslash( $_POST['version'] ?? '' ) );
		$result  = self::switch_component( $target, $version );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		wp_send_json_success( array( 'text' => $result, 'reload' => true ) );
	}

	/**
	 * Aplica de una sola vez todos los cambios que el operador marcó en la
	 * tabla: WordPress, versión de cada plugin y su estado activo/inactivo.
	 * Un fallo en un plugin no aborta el resto: se informa por fila.
	 */
	public static function ajax_apply() {
		self::guard();
		@set_time_limit( 900 );
		$raw     = wp_unslash( $_POST['changes'] ?? '{}' );
		$changes = json_decode( $raw, true );
		if ( ! is_array( $changes ) ) {
			wp_send_json_error( 'No se pudo leer la lista de cambios.' );
		}

		$log      = array();
		$failures = 0;

		$wp = sanitize_text_field( (string) ( $changes['wordpress'] ?? '' ) );
		if ( $wp !== '' && $wp !== self::disk_wp_version() ) {
			$done = self::install_wordpress( $wp, true );
			if ( is_wp_error( $done ) ) {
				$failures++;
				$log[] = 'WordPress ' . $wp . ': ERROR ' . $done->get_error_message();
			} else {
				$log[] = (string) $done;
			}
		}

		$plugins = is_array( $changes['plugins'] ?? null ) ? $changes['plugins'] : array();
		foreach ( $plugins as $slug => $wanted ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' === $slug || ! is_array( $wanted ) ) {
				continue;
			}
			$version = sanitize_text_field( (string) ( $wanted['version'] ?? '' ) );
			$active  = ! empty( $wanted['active'] );
			$current = self::installed_plugin_version( $slug );

			if ( 'sqlite-database-integration' === $slug && $version !== '' && $version !== $current ) {
				$log[] = $slug . ': no se reinstala en caliente (rompería SQLite). Sin cambios.';
				continue;
			}

			if ( $version !== '' && $version !== $current ) {
				$done = self::switch_component( $slug, $version );
				if ( is_wp_error( $done ) ) {
					$failures++;
					$log[] = $slug . ' ' . $version . ': ERROR ' . $done->get_error_message();
					continue;
				}
				$log[] = (string) $done;
			}

			$isActive = self::plugin_is_active( $slug );
			if ( $active && ! $isActive ) {
				self::activate_slug( $slug );
				$log[] = $slug . ': activado';
			} elseif ( ! $active && $isActive ) {
				$off = self::deactivate_slug( $slug );
				$log[] = is_wp_error( $off ) ? $slug . ': ERROR al desactivar ' . $off->get_error_message() : $slug . ': desactivado';
			}
		}

		if ( ! $log ) {
			wp_send_json_success( array( 'text' => 'No había cambios pendientes.', 'reload' => false ) );
		}
		if ( function_exists( 'opcache_reset' ) ) {
			@opcache_reset();
		}
		$log[] = $failures
			? $failures . ' cambio(s) fallaron. Revisa el detalle de arriba.'
			: 'Todos los cambios se aplicaron.';
		wp_send_json_success( array( 'text' => implode( "\n", $log ), 'reload' => 0 === $failures ) );
	}

	/**
	 * Instala un plugin desde un ZIP subido por el operador. El archivo se
	 * conserva en drop-plugins/ para poder repetir la instalación.
	 */
	public static function ajax_upload_zip() {
		self::guard();
		@set_time_limit( 300 );
		if ( empty( $_FILES['zip']['name'] ) ) {
			wp_send_json_error( 'No llegó ningún archivo.' );
		}
		$file = $_FILES['zip'];
		if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			wp_send_json_error( 'El navegador no pudo subir el archivo (código ' . (int) $file['error'] . ').' );
		}
		if ( (int) $file['size'] > 64 * 1024 * 1024 ) {
			wp_send_json_error( 'El ZIP supera 64 MB.' );
		}
		$name = sanitize_file_name( (string) $file['name'] );
		if ( ! preg_match( '/\.zip$/i', $name ) ) {
			wp_send_json_error( 'Solo se aceptan archivos .zip' );
		}
		$dir = dirname( ABSPATH ) . '/drop-plugins';
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			wp_send_json_error( 'No se pudo crear drop-plugins/.' );
		}
		$target = $dir . '/' . $name;
		if ( ! move_uploaded_file( (string) $file['tmp_name'], $target ) ) {
			wp_send_json_error( 'No se pudo guardar el ZIP en drop-plugins/.' );
		}
		if ( ! class_exists( 'Cxp_Plugin_Lab' ) ) {
			wp_send_json_error( 'Falta el módulo de instalación de plugins.' );
		}
		$activate = ! empty( $_POST['activate'] );
		$done     = Cxp_Plugin_Lab::install_zip( $target, $activate, false );
		if ( is_wp_error( $done ) ) {
			wp_send_json_error( 'ZIP guardado en drop-plugins/' . $name . " pero no se pudo instalar:\n" . $done->get_error_message() );
		}
		wp_send_json_success(
			array(
				'text'   => 'Instalado desde ' . $name . ( $activate ? ' y activado.' : ' (queda inactivo).' ) . "\nEl ZIP quedó en drop-plugins/ por si hay que repetirlo.",
				'reload' => true,
			)
		);
	}

	private static function plugin_is_active( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( (array) get_option( 'active_plugins', array() ) as $file ) {
			if ( dirname( (string) $file ) === $slug ) {
				return true;
			}
		}
		return false;
	}

	private static function disk_wp_version() {
		$file = ABSPATH . WPINC . '/version.php';
		if ( ! is_readable( $file ) ) {
			return get_bloginfo( 'version' );
		}
		$raw = (string) file_get_contents( $file );
		return preg_match( '/\$wp_version\s*=\s*[\'"]([^\'"]+)/', $raw, $m ) ? $m[1] : get_bloginfo( 'version' );
	}

	public static function ajax_restore_client() {
		self::guard();
		@set_time_limit( 240 );
		$log = array();
		foreach ( array(
			array( 'wordpress', self::CLIENT_WP ),
			array( 'woocommerce', self::CLIENT_WC ),
			array( 'chilexpress', self::CLIENT_CXP ),
		) as $step ) {
			$done = self::switch_component( $step[0], $step[1] );
			if ( is_wp_error( $done ) ) {
				wp_send_json_error( implode( "\n", $log ) . "\n" . $done->get_error_message() );
			}
			$log[] = $done;
		}
		switch_theme( 'woodmart-child' );
		if ( class_exists( 'Cxp_Sr108688_Repro' ) ) {
			Cxp_Sr108688_Repro::restore_enum();
		}
		$log[] = 'Tema activo: woodmart-child';
		$log[] = 'Pila del cliente restaurada. Recargando.';
		wp_send_json_success( array( 'text' => implode( "\n", $log ), 'reload' => true ) );
	}

	public static function ajax_update_all_latest() {
		self::guard();
		@set_time_limit( 900 );
		$log = array();
		$response = wp_remote_get( 'https://api.wordpress.org/core/version-check/1.7/', array( 'timeout' => 30 ) );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}
		$payload = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$latest = sanitize_text_field( (string) ( $payload['offers'][0]['current'] ?? '' ) );
		if ( ! $latest ) {
			wp_send_json_error( 'WordPress.org no devolvió la versión actual.' );
		}
		$wp = self::install_wordpress( $latest, true );
		if ( is_wp_error( $wp ) ) {
			wp_send_json_error( $wp->get_error_message() );
		}
		$log[] = $wp;
		foreach ( self::switchable_plugins() as $slug => $meta ) {
			if ( 'repo' === $meta['source'] || 'sqlite-database-integration' === $slug ) {
				$log[] = $slug . ': conservado (' . $meta['source'] . ')';
				continue;
			}
			$done = self::install_plugin_zip( $slug, '' );
			$log[] = is_wp_error( $done ) ? $slug . ': ERROR ' . $done->get_error_message() : $done;
		}
		if ( function_exists( 'opcache_reset' ) ) {
			@opcache_reset();
		}
		wp_send_json_success( array( 'text' => implode( "\n", $log ), 'reload' => true ) );
	}

	public static function ajax_reload_full() {
		self::guard();
		@set_time_limit( 300 );
		$version = sanitize_text_field( wp_unslash( $_POST['version'] ?? self::CLIENT_WP ) );
		$raw     = wp_unslash( $_POST['plugins'] ?? '{}' );
		$map     = json_decode( $raw, true );
		if ( ! is_array( $map ) ) {
			$map = array();
		}
		$log = array();
		$wp  = self::install_wordpress( $version, true );
		if ( is_wp_error( $wp ) ) {
			wp_send_json_error( $wp->get_error_message() );
		}
		$log[] = $wp;
		foreach ( $map as $slug => $ver ) {
			$slug = sanitize_key( $slug );
			$ver  = sanitize_text_field( (string) $ver );
			if ( 'sqlite-database-integration' === $slug ) {
				$log[] = 'sqlite-database-integration: no se reinstala en la recarga completa (rompería SQLite en caliente). Usa el botón de esa fila si hace falta.';
				continue;
			}
			$done = self::switch_component( $slug, $ver );
			if ( is_wp_error( $done ) ) {
				$log[] = $slug . ': ' . $done->get_error_message();
			} else {
				$log[] = $done;
			}
		}
		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}
		if ( function_exists( 'opcache_reset' ) ) {
			@opcache_reset();
		}
		$log[] = 'Stack recargada. Recargando el navegador.';
		wp_send_json_success( array( 'text' => implode( "\n", $log ), 'reload' => true ) );
	}

	public static function ajax_set_email() {
		self::guard();
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			wp_send_json_error( 'Correo inválido' );
		}
		update_option( 'cxp_lab_email', $email, false );
		update_option( 'admin_email', $email );
		update_option( 'new_admin_email', $email );
		$user = get_user_by( 'login', 'admin' );
		if ( $user ) {
			wp_update_user(
				array(
					'ID'         => $user->ID,
					'user_email' => $email,
				)
			);
		}
		update_option( 'woocommerce_email_from_address', $email );
		update_option( 'woocommerce_stock_email_recipient', $email );
		$general = get_option( 'chilexpress_woo_oficial_general', array() );
		if ( ! is_array( $general ) ) {
			$general = array();
		}
		$general['email_remitente'] = $email;
		update_option( 'chilexpress_woo_oficial_general', $general, false );
		wp_send_json_success(
			array(
				'text' => 'Correo de laboratorio: ' . $email . "\nAplicado a admin WP, usuario admin, Woo from/stock, remitente Chilexpress y checkout de prueba (recarga para ver el prefill).",
			)
		);
	}

	public static function ajax_switch_php() {
		self::guard();
		@set_time_limit( 300 );
		$version = preg_replace( '/[^0-9.]/', '', wp_unslash( $_POST['version'] ?? '' ) );
		if ( $version === '' ) {
			wp_send_json_error( 'Falta la versión de PHP' );
		}
		$log   = array();
		$ready = self::set_php_target( $version );
		if ( is_wp_error( $ready ) ) {
			wp_send_json_error( $ready->get_error_message() );
		}
		$log[] = $ready;
		$log[] = 'PHP en ejecución ahora: ' . PHP_VERSION . ' (este proceso no cambia).';
		if ( self::is_docker() ) {
			$log[] = 'Estás en Docker. En .env deja PHP_VERSION=' . $version . ' y rebuild:';
			$log[] = '  docker compose up --build';
			$log[] = 'Dokploy: misma variable y Redeploy.';
		} else {
			$log[] = 'Windows local: Ctrl+C el start.sh actual y luego:';
			$log[] = '  bash start.sh';
			$log[] = 'O una sola vez: PHP_VERSION=' . $version . ' bash start.sh';
		}
		wp_send_json_success( array( 'text' => implode( "\n", $log ), 'reload' => false ) );
	}

	private static function php_target_version() {
		$file = dirname( ABSPATH ) . '/runtime/.php-version';
		if ( is_readable( $file ) ) {
			$v = trim( (string) file_get_contents( $file ) );
			if ( preg_match( '/^\d+\.\d+/', $v ) ) {
				return $v;
			}
		}
		$opt = get_option( 'cxp_php_target', '' );
		if ( is_string( $opt ) && preg_match( '/^\d+\.\d+/', $opt ) ) {
			return $opt;
		}
		$env = function_exists( 'cxp_env' ) ? cxp_env( 'PHP_VERSION', '' ) : '';
		if ( $env !== '' ) {
			return $env;
		}
		return PHP_VERSION;
	}

	private static function php_version_choices( $current ) {
		$opts = array( '8.1.34', '8.2.33', '8.3.33', '8.4.19', '8.4.24', '8.5.9' );
		foreach ( self::installed_php_runtimes() as $v ) {
			$opts[] = $v;
		}
		$opts[] = $current;
		$opts[] = self::php_target_version();
		$opts   = array_values( array_unique( array_filter( $opts ) ) );
		usort(
			$opts,
			static function ( $a, $b ) {
				return version_compare( $a, $b );
			}
		);
		return $opts;
	}

	private static function installed_php_runtimes() {
		$dir   = dirname( ABSPATH ) . '/runtime';
		$found = array();
		if ( ! is_dir( $dir ) ) {
			return $found;
		}
		foreach ( glob( $dir . '/php-*', GLOB_ONLYDIR ) ?: array() as $path ) {
			$ver = substr( basename( $path ), 4 );
			if ( is_readable( $path . '/php.exe' ) || is_readable( $path . '/php' ) ) {
				$found[] = $ver;
			}
		}
		return $found;
	}

	private static function is_docker() {
		if ( file_exists( '/.dockerenv' ) ) {
			return true;
		}
		return false;
	}

	public static function set_php_target( $version ) {
		update_option( 'cxp_php_target', $version, false );
		$runtime = dirname( ABSPATH ) . '/runtime';
		if ( ! wp_mkdir_p( $runtime ) ) {
			return new WP_Error( 'cxp_php', 'No se pudo crear runtime/' );
		}
		if ( false === file_put_contents( $runtime . '/.php-version', $version . "\n" ) ) {
			return new WP_Error( 'cxp_php', 'No se pudo escribir runtime/.php-version' );
		}
		self::patch_dotenv_php_version( $version );
		if ( self::is_docker() ) {
			return 'Preferencia PHP ' . $version . ' guardada (Docker: rebuild con PHP_VERSION).';
		}
		$dest = $runtime . '/php-' . $version;
		if ( is_readable( $dest . '/php.exe' ) || is_readable( $dest . '/php' ) ) {
			return 'PHP ' . $version . ' ya está en runtime/php-' . $version . '.';
		}
		if ( 'Windows' !== PHP_OS_FAMILY ) {
			return 'Preferencia guardada. En Linux/macOS instala PHP ' . $version . ' en el sistema o coloca el binario en runtime/php-' . $version . '/php';
		}
		$got = self::prepare_windows_php( $version );
		if ( is_wp_error( $got ) ) {
			return $got;
		}
		return $got;
	}

	private static function patch_dotenv_php_version( $version ) {
		$file = dirname( ABSPATH ) . '/.env';
		if ( ! is_readable( $file ) || ! is_writable( $file ) ) {
			return;
		}
		$raw = (string) file_get_contents( $file );
		if ( preg_match( '/^PHP_VERSION=/m', $raw ) ) {
			$raw = preg_replace( '/^PHP_VERSION=.*$/m', 'PHP_VERSION=' . $version, $raw, 1 );
		} else {
			$raw = 'PHP_VERSION=' . $version . "\n" . $raw;
		}
		file_put_contents( $file, $raw );
	}

	private static function prepare_windows_php( $version ) {
		$dest = dirname( ABSPATH ) . '/runtime/php-' . $version;
		$url  = self::windows_php_zip_url( $version );
		if ( is_wp_error( $url ) ) {
			return $url;
		}
		$zip = self::download_zip( $url, 'php-' . $version . '-nts.zip' );
		if ( is_wp_error( $zip ) ) {
			return $zip;
		}
		self::load_fs();
		wp_mkdir_p( $dest );
		$unzip = unzip_file( $zip, $dest );
		if ( is_wp_error( $unzip ) ) {
			return $unzip;
		}
		wp_delete_file( $zip );
		if ( ! is_readable( $dest . '/php.exe' ) ) {
			return new WP_Error( 'cxp_php', 'El ZIP no dejó php.exe en runtime/php-' . $version );
		}
		self::enable_php_ini_extensions( $dest );
		return 'PHP ' . $version . ' descargado a runtime/php-' . $version . ' (NTS x64).';
	}

	private static function windows_php_zip_url( $version ) {
		$urls = array();
		$res  = wp_remote_get(
			'https://windows.php.net/downloads/releases/releases.json',
			array(
				'timeout' => 30,
			)
		);
		if ( ! is_wp_error( $res ) ) {
			$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
			if ( is_array( $data ) ) {
				foreach ( $data as $minor => $pack ) {
					if ( ! is_array( $pack ) ) {
						continue;
					}
					$got = isset( $pack['version'] ) ? (string) $pack['version'] : '';
					if ( $got !== $version && $minor !== $version ) {
						continue;
					}
					foreach ( $pack as $key => $meta ) {
						if ( ! is_string( $key ) || 0 !== strpos( $key, 'nts-' ) || false === strpos( $key, 'x64' ) ) {
							continue;
						}
						if ( empty( $meta['zip']['path'] ) ) {
							continue;
						}
						$path   = $meta['zip']['path'];
						$urls[] = 'https://windows.php.net/downloads/releases/' . $path;
						$urls[] = 'https://windows.php.net/downloads/releases/archives/' . $path;
					}
				}
			}
		}
		foreach ( array( 'vs17', 'vs16', 'vc15' ) as $vs ) {
			$name   = 'php-' . $version . '-nts-Win32-' . $vs . '-x64.zip';
			$urls[] = 'https://windows.php.net/downloads/releases/' . $name;
			$urls[] = 'https://windows.php.net/downloads/releases/archives/' . $name;
		}
		$urls = array_values( array_unique( $urls ) );
		foreach ( $urls as $url ) {
			$head = wp_remote_head( $url, array( 'timeout' => 20 ) );
			if ( ! is_wp_error( $head ) && (int) wp_remote_retrieve_response_code( $head ) === 200 ) {
				return $url;
			}
			$probe = wp_remote_get(
				$url,
				array(
					'timeout'     => 15,
					'redirection' => 2,
					'headers'     => array( 'Range' => 'bytes=0-64' ),
				)
			);
			$code = is_wp_error( $probe ) ? 0 : (int) wp_remote_retrieve_response_code( $probe );
			if ( in_array( $code, array( 200, 206 ), true ) ) {
				return $url;
			}
		}
		return new WP_Error(
			'cxp_php',
			'No hay ZIP NTS x64 de PHP ' . $version . ' en windows.php.net. Prueba 8.2.33, 8.3.33, 8.4.19, 8.4.24 o 8.5.9.'
		);
	}

	private static function enable_php_ini_extensions( $dir ) {
		$ini = $dir . '/php.ini';
		if ( ! is_file( $ini ) ) {
			$src = is_file( $dir . '/php.ini-development' ) ? $dir . '/php.ini-development' : $dir . '/php.ini-production';
			if ( is_file( $src ) ) {
				copy( $src, $ini );
			}
		}
		if ( ! is_readable( $ini ) ) {
			return;
		}
		$raw = (string) file_get_contents( $ini );
		if ( preg_match( '/^;?extension_dir\s*=/m', $raw ) ) {
			$raw = preg_replace( '/^;?extension_dir\s*=.*$/m', 'extension_dir = "ext"', $raw, 1 );
		} else {
			$raw .= "\nextension_dir = \"ext\"\n";
		}
		foreach ( array( 'curl', 'fileinfo', 'gd', 'intl', 'mbstring', 'exif', 'openssl', 'pdo_sqlite', 'sodium', 'sqlite3', 'zip' ) as $ext ) {
			$raw = preg_replace( '/^;extension=' . preg_quote( $ext, '/' ) . '\s*$/m', 'extension=' . $ext, $raw );
		}
		file_put_contents( $ini, $raw );
	}

	private static function switchable_plugins() {
		$catalog = array(
			'woocommerce'                   => array(
				'name'     => 'WooCommerce',
				'source'   => 'wporg',
				'file'     => 'woocommerce/woocommerce.php',
				'versions' => array( '9.9.5', '10.4.3', '11.0.1' ),
			),
			'sqlite-database-integration'   => array(
				'name'     => 'SQLite Database Integration',
				'source'   => 'wporg',
				'file'     => 'sqlite-database-integration/load.php',
				'versions' => array( '2.2.6', '2.2.13', '3.0.0' ),
			),
			'akismet'                       => array(
				'name'     => 'Akismet',
				'source'   => 'wporg',
				'file'     => 'akismet/akismet.php',
				'versions' => array( '5.3.7', '5.4', '5.7' ),
			),
			'chilexpress-oficial'           => array(
				'name'     => 'Chilexpress Oficial',
				'source'   => 'repo',
				'file'     => 'chilexpress-oficial/chilexpress-woo-oficial.php',
				'versions' => array( '1.4.0' ),
			),
		);
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( get_plugins() as $file => $data ) {
			if ( 'hello.php' === $file ) {
				continue;
			}
			$slug = dirname( $file );
			if ( '.' === $slug ) {
				$slug = basename( $file, '.php' );
			}
			if ( isset( $catalog[ $slug ] ) ) {
				$catalog[ $slug ]['file'] = $file;
				$catalog[ $slug ]['name'] = $data['Name'];
				continue;
			}
			$catalog[ $slug ] = array(
				'name'     => $data['Name'],
				'source'   => 'wporg',
				'file'     => $file,
				'versions' => array( $data['Version'] ),
			);
		}
		$active = (array) get_option( 'active_plugins', array() );
		foreach ( $catalog as $slug => &$meta ) {
			$cur = self::plugin_version( $meta['file'] );
			$meta['current'] = $cur;
			$meta['active']  = in_array( $meta['file'], $active, true );
			if ( $cur && $cur !== 'n/d' && ! in_array( $cur, $meta['versions'], true ) ) {
				array_unshift( $meta['versions'], $cur );
			}
		}
		unset( $meta );
		return $catalog;
	}

	public static function switch_component( $target, $version ) {
		if ( 'wordpress' === $target ) {
			return self::install_wordpress( $version );
		}
		if ( 'chilexpress' === $target || 'chilexpress-oficial' === $target ) {
			return self::restore_chilexpress();
		}
		$plugins = self::switchable_plugins();
		if ( isset( $plugins[ $target ] ) && 'repo' === $plugins[ $target ]['source'] ) {
			return self::restore_chilexpress();
		}
		if ( preg_match( '/^[a-z0-9-]+$/', $target ) ) {
			return self::install_plugin_zip( $target, $version );
		}
		return new WP_Error( 'cxp_stack', 'Objetivo desconocido: ' . $target );
	}

	public static function install_wordpress( $version, $force = false ) {
		$version = preg_replace( '/[^0-9.]/', '', $version );
		if ( $version === '' ) {
			return new WP_Error( 'cxp_stack', 'Falta la versión de WordPress' );
		}
		if ( ! $force && $version === get_bloginfo( 'version' ) ) {
			return 'WordPress ya está en ' . $version;
		}
		$url = 'https://wordpress.org/wordpress-' . $version . '.zip';
		$zip = self::download_zip( $url, 'wordpress-' . $version . '.zip' );
		if ( is_wp_error( $zip ) ) {
			return $zip;
		}
		self::load_fs();
		$tmp = self::tmp_dir( 'wpcore' );
		$unzip = unzip_file( $zip, $tmp );
		if ( is_wp_error( $unzip ) ) {
			self::rmdir( $tmp );
			return $unzip;
		}
		$src = $tmp . '/wordpress';
		if ( ! is_dir( $src ) ) {
			self::rmdir( $tmp );
			return new WP_Error( 'cxp_stack', 'El ZIP de WordPress no tiene carpeta wordpress/' );
		}
		$skip = array( 'wp-content', 'wp-config.php', 'wp-config-sample.php' );
		foreach ( scandir( $src ) as $name ) {
			if ( '.' === $name || '..' === $name || in_array( $name, $skip, true ) ) {
				continue;
			}
			$from = $src . '/' . $name;
			$to   = ABSPATH . $name;
			if ( is_dir( $from ) ) {
				if ( is_dir( $to ) ) {
					self::rmdir( $to );
				}
				if ( ! copy_dir( $from, $to ) ) {
					self::rmdir( $tmp );
					return new WP_Error( 'cxp_stack', 'No se pudo copiar ' . $name );
				}
			} else {
				copy( $from, $to );
			}
		}
		self::rmdir( $tmp );
		wp_delete_file( $zip );
		return 'WordPress ' . $version . ' instalado (core). wp-content no se tocó.';
	}

	public static function install_plugin_zip( $slug, $version ) {
		$version = preg_replace( '/[^0-9.]/', '', $version );
		if ( $version === '' ) {
			$url = 'https://downloads.wordpress.org/plugin/' . rawurlencode( $slug ) . '.zip';
		} else {
			$url = 'https://downloads.wordpress.org/plugin/' . rawurlencode( $slug ) . '.' . rawurlencode( $version ) . '.zip';
		}
		$zip = self::download_zip( $url, $slug . '-' . ( $version ?: 'latest' ) . '.zip' );
		if ( is_wp_error( $zip ) ) {
			return $zip;
		}
		self::load_fs();
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$dest = WP_PLUGIN_DIR . '/' . $slug;
		$tmp  = self::tmp_dir( $slug );
		$unzip = unzip_file( $zip, $tmp );
		if ( is_wp_error( $unzip ) ) {
			self::rmdir( $tmp );
			return $unzip;
		}
		$root = is_dir( $tmp . '/' . $slug ) ? $tmp . '/' . $slug : $tmp;
		if ( is_dir( $dest ) ) {
			self::rmdir( $dest );
		}
		if ( ! copy_dir( $root, $dest ) ) {
			self::rmdir( $tmp );
			return new WP_Error( 'cxp_stack', 'No se pudo copiar ' . $slug );
		}
		self::rmdir( $tmp );
		wp_delete_file( $zip );
		self::activate_slug( $slug );
		return $slug . ' ' . ( $version ?: 'latest' ) . ' instalado y activado.';
	}

	public static function activate_slug( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( get_plugins() as $file => $data ) {
			if ( 0 === strpos( $file, $slug . '/' ) || $file === $slug . '.php' ) {
				activate_plugin( $file, '', false, true );
				return;
			}
		}
	}

	public static function deactivate_slug( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( get_plugins() as $file => $data ) {
			if ( 0 === strpos( $file, $slug . '/' ) || $file === $slug . '.php' ) {
				deactivate_plugins( $file, true, false );
				return true;
			}
		}
		return new WP_Error( 'cxp_stack', 'No está instalado ' . $slug );
	}

	public static function installed_plugin_version( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( get_plugins() as $file => $data ) {
			if ( 0 === strpos( $file, $slug . '/' ) || $file === $slug . '.php' ) {
				return (string) ( $data['Version'] ?? '' );
			}
		}
		return '';
	}

	public static function install_theme_zip( $slug, $version = '' ) {
		$slug = sanitize_key( $slug );
		$version = preg_replace( '/[^0-9.]/', '', (string) $version );
		if ( ! $slug ) {
			return new WP_Error( 'cxp_stack', 'Falta el slug del tema' );
		}
		$url = $version
			? 'https://downloads.wordpress.org/theme/' . rawurlencode( $slug ) . '.' . rawurlencode( $version ) . '.zip'
			: 'https://downloads.wordpress.org/theme/' . rawurlencode( $slug ) . '.zip';
		$zip = self::download_zip( $url, $slug . '-' . ( $version ?: 'latest' ) . '-theme.zip' );
		if ( is_wp_error( $zip ) ) {
			return $zip;
		}
		self::load_fs();
		$tmp = self::tmp_dir( 'theme-' . $slug );
		$unzip = unzip_file( $zip, $tmp );
		if ( is_wp_error( $unzip ) ) {
			self::rmdir( $tmp );
			return $unzip;
		}
		$root = is_dir( $tmp . '/' . $slug ) ? $tmp . '/' . $slug : $tmp;
		if ( ! is_readable( $root . '/style.css' ) ) {
			self::rmdir( $tmp );
			return new WP_Error( 'cxp_stack', 'El ZIP no contiene un tema válido' );
		}
		$dest = get_theme_root() . '/' . $slug;
		if ( is_dir( $dest ) ) {
			self::rmdir( $dest );
		}
		if ( ! copy_dir( $root, $dest ) ) {
			self::rmdir( $tmp );
			return new WP_Error( 'cxp_stack', 'No se pudo copiar el tema ' . $slug );
		}
		self::rmdir( $tmp );
		wp_delete_file( $zip );
		switch_theme( $slug );
		return 'Tema ' . $slug . ' ' . ( $version ?: 'latest' ) . ' instalado y activado.';
	}

	public static function install_theme_local_zip( $slug, $zip ) {
		$slug = sanitize_key( $slug );
		if ( ! $slug || ! is_readable( $zip ) || 'zip' !== strtolower( pathinfo( $zip, PATHINFO_EXTENSION ) ) ) {
			return new WP_Error( 'cxp_stack', 'ZIP local de tema inválido' );
		}
		self::load_fs();
		$tmp = self::tmp_dir( 'theme-local-' . $slug );
		$unzip = unzip_file( $zip, $tmp );
		if ( is_wp_error( $unzip ) ) {
			self::rmdir( $tmp );
			return $unzip;
		}
		$root = is_dir( $tmp . '/' . $slug ) ? $tmp . '/' . $slug : $tmp;
		if ( ! is_readable( $root . '/style.css' ) ) {
			self::rmdir( $tmp );
			return new WP_Error( 'cxp_stack', 'El ZIP local no contiene style.css para ' . $slug );
		}
		$dest = get_theme_root() . '/' . $slug;
		if ( is_dir( $dest ) ) {
			self::rmdir( $dest );
		}
		if ( ! copy_dir( $root, $dest ) ) {
			self::rmdir( $tmp );
			return new WP_Error( 'cxp_stack', 'No se pudo copiar el tema local ' . $slug );
		}
		self::rmdir( $tmp );
		switch_theme( $slug );
		return 'Tema ' . $slug . ' instalado desde ZIP local y activado.';
	}

	public static function restore_chilexpress() {
		$src = dirname( ABSPATH ) . '/chilexpress-oficial';
		if ( ! is_dir( $src ) || ! is_readable( $src . '/chilexpress-woo-oficial.php' ) ) {
			$src = WP_PLUGIN_DIR . '/chilexpress-oficial';
			if ( ! is_dir( $src ) ) {
				return new WP_Error( 'cxp_stack', 'No hay copia intacta de chilexpress-oficial en el repo' );
			}
			return 'Chilexpress Oficial ya está en wp-content/plugins (no hay baseline aparte).';
		}
		self::load_fs();
		$dest = WP_PLUGIN_DIR . '/chilexpress-oficial';
		if ( is_dir( $dest ) && realpath( $dest ) && realpath( $src ) && realpath( $dest ) === realpath( $src ) ) {
			if ( ! function_exists( 'activate_plugin' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			activate_plugin( 'chilexpress-oficial/chilexpress-woo-oficial.php', '', false, true );
			return 'Chilexpress Oficial 1.4.0 ya era la copia del repo (activada).';
		}
		// includes/data guarda el cache de regiones y comunas que el plugin baja
		// de la API. El arbol del repo solo trae READMEs: si no se conserva, el
		// selector de Region queda vacio despues de cada restauracion.
		$cache = '';
		if ( is_dir( $dest . '/includes/data' ) ) {
			$cache = WP_CONTENT_DIR . '/upgrade/cxp-cxpdata-' . wp_generate_password( 6, false );
			wp_mkdir_p( $cache );
			copy_dir( $dest . '/includes/data', $cache );
		}
		if ( is_dir( $dest ) ) {
			self::rmdir( $dest );
		}
		if ( ! copy_dir( $src, $dest ) ) {
			return new WP_Error( 'cxp_stack', 'No se pudo copiar Chilexpress 1.4.0' );
		}
		$restored_cache = '';
		if ( $cache && is_dir( $cache ) ) {
			wp_mkdir_p( $dest . '/includes/data' );
			copy_dir( $cache, $dest . '/includes/data' );
			self::rmdir( $cache );
			$restored_cache = ' Cache de regiones y comunas conservado.';
		}
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		activate_plugin( 'chilexpress-oficial/chilexpress-woo-oficial.php', '', false, true );
		return 'Chilexpress Oficial 1.4.0 restaurado desde la copia intacta del repo.' . $restored_cache;
	}

	private static function download_zip( $url, $filename ) {
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$tmp = download_url( $url, 120 );
		if ( is_wp_error( $tmp ) ) {
			return new WP_Error( 'cxp_stack', 'No se pudo descargar ' . $url . ' — ' . $tmp->get_error_message() );
		}
		$dir = WP_CONTENT_DIR . '/upgrade';
		wp_mkdir_p( $dir );
		$dest = $dir . '/' . sanitize_file_name( $filename );
		if ( file_exists( $dest ) ) {
			wp_delete_file( $dest );
		}
		if ( ! rename( $tmp, $dest ) && ! copy( $tmp, $dest ) ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'cxp_stack', 'No se pudo guardar el ZIP' );
		}
		if ( is_file( $tmp ) ) {
			wp_delete_file( $tmp );
		}
		return $dest;
	}

	private static function plugin_version( $file ) {
		$path = WP_PLUGIN_DIR . '/' . $file;
		if ( ! is_readable( $path ) ) {
			return 'n/d';
		}
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$data = get_plugin_data( $path, false, false );
		return $data['Version'] !== '' ? $data['Version'] : 'n/d';
	}

	private static function load_fs() {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! defined( 'FS_METHOD' ) ) {
			define( 'FS_METHOD', 'direct' );
		}
		WP_Filesystem();
		// Si WP_Filesystem() sale antes de tiempo, unzip_file() muere con
		// "Undefined constant FS_CHMOD_DIR". Se garantizan aquí.
		if ( ! defined( 'FS_CHMOD_DIR' ) ) {
			define( 'FS_CHMOD_DIR', ( fileperms( ABSPATH ) & 0777 ) | 0755 );
		}
		if ( ! defined( 'FS_CHMOD_FILE' ) ) {
			define( 'FS_CHMOD_FILE', ( fileperms( ABSPATH . 'index.php' ) & 0777 ) | 0644 );
		}
	}

	private static function tmp_dir( $prefix ) {
		$dir = WP_CONTENT_DIR . '/upgrade/cxp-' . $prefix . '-' . wp_generate_password( 6, false );
		wp_mkdir_p( $dir );
		return $dir;
	}

	private static function rmdir( $dir ) {
		global $wp_filesystem;
		if ( $wp_filesystem && method_exists( $wp_filesystem, 'rmdir' ) && $wp_filesystem->is_dir( $dir ) ) {
			$wp_filesystem->rmdir( $dir, true );
			return;
		}
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				self::rmdir( $path );
			} else {
				wp_delete_file( $path );
			}
		}
		@rmdir( $dir );
	}

	private static function guard() {
		if ( is_user_logged_in() && current_user_can( 'update_core' ) ) {
			// ok
		} elseif ( function_exists( 'cxp_auto_login_user' ) ) {
			cxp_auto_login_user();
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), self::NONCE ) ) {
			wp_send_json_error( 'Nonce inválido' );
		}
	}
}

Cxp_Stack_Versions::boot();
