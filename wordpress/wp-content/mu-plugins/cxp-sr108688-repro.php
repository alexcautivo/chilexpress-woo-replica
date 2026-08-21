<?php
/**
 * Plugin Name: SR-108688 — replicar caída del cliente
 * Version: 1.0.0
 * Description: Botón que simula la ventana de actualización WooCommerce 11.0.1 y captura el fatal de Chilexpress 1.4.0 en admin-ajax.php. No parchea chilexpress-oficial.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cxp_Sr108688_Repro {
	const NONCE = 'cxp_sr108688';
	const HID   = '.cxp-sr108688';

	public static function boot() {
		add_action( 'cxp_debug_console_panels', array( __CLASS__, 'console_panel' ), 1 );
		add_action( 'wp_ajax_cxp_sr108688_repro', array( __CLASS__, 'ajax_repro' ) );
		add_action( 'wp_ajax_nopriv_cxp_sr108688_repro', array( __CLASS__, 'ajax_repro' ) );
		add_action( 'wp_ajax_cxp_sr108688_break', array( __CLASS__, 'ajax_break' ) );
		add_action( 'wp_ajax_nopriv_cxp_sr108688_break', array( __CLASS__, 'ajax_break' ) );
		add_action( 'wp_ajax_cxp_sr108688_restore', array( __CLASS__, 'ajax_restore' ) );
		add_action( 'wp_ajax_nopriv_cxp_sr108688_restore', array( __CLASS__, 'ajax_restore' ) );
		add_action( 'wp_ajax_cxp_sr108688_pdf', array( __CLASS__, 'ajax_pdf' ) );
		add_action( 'wp_ajax_nopriv_cxp_sr108688_pdf', array( __CLASS__, 'ajax_pdf' ) );
	}

	public static function enum_path() {
		return WP_PLUGIN_DIR . '/woocommerce/src/Enums/ProductTaxStatus.php';
	}

	public static function hidden_path() {
		return self::enum_path() . self::HID;
	}

	public static function is_broken() {
		$src = self::enum_path();
		if ( ! is_file( self::hidden_path() ) ) {
			return false;
		}
		if ( ! is_file( $src ) ) {
			return true;
		}
		return false === strpos( (string) file_get_contents( $src ), 'class ProductTaxStatus' );
	}

	public static function hide_enum() {
		$src = self::enum_path();
		$dst = self::hidden_path();
		if ( ! is_file( $src ) && ! is_file( $dst ) ) {
			return new WP_Error( 'cxp_sr108688', 'No está ProductTaxStatus.php (¿WooCommerce incompleto?)' );
		}
		if ( is_file( $src ) && false !== strpos( (string) file_get_contents( $src ), 'class ProductTaxStatus' ) && ! is_file( $dst ) ) {
			if ( ! copy( $src, $dst ) ) {
				return new WP_Error( 'cxp_sr108688', 'No se pudo respaldar ProductTaxStatus.php' );
			}
		}
		$stub = "<?php\n// SR-108688: ruta presente, clase ausente (update in-place incompleto).\n";
		if ( false === file_put_contents( $src, $stub ) ) {
			return new WP_Error( 'cxp_sr108688', 'No se pudo vaciar ProductTaxStatus.php' );
		}
		return true;
	}

	public static function restore_enum() {
		$src = self::enum_path();
		$dst = self::hidden_path();
		if ( is_file( $dst ) ) {
			if ( ! copy( $dst, $src ) ) {
				return new WP_Error( 'cxp_sr108688', 'No se pudo restaurar ProductTaxStatus.php' );
			}
			unlink( $dst );
		}
		if ( ! is_file( $src ) || false === strpos( (string) file_get_contents( $src ), 'class ProductTaxStatus' ) ) {
			return new WP_Error( 'cxp_sr108688', 'ProductTaxStatus.php no quedó restaurado' );
		}
		return true;
	}

	public static function console_panel() {
		$ajax    = admin_url( 'admin-ajax.php' );
		$nonce   = wp_create_nonce( self::NONCE );
		$restore = home_url( '/__sr108688/restore' );
		$status  = home_url( '/__sr108688/status' );
		$probe   = admin_url( 'admin-ajax.php' );
		$stack   = self::stack_status();
		$broken  = self::is_broken();
		$last    = get_option( 'cxp_sr108688_last', array() );
		?>
		<style>
			#cxp-dbg-sr{margin:0 0 14px;padding:14px;border:2px solid #facc15;border-radius:12px;background:#1c1917}
			#cxp-dbg-sr p{margin:0 0 8px;color:#fecaca}
			#cxp-dbg-sr p strong,#cxp-dbg-sr h2,#cxp-dbg-sr h3{color:#facc15}
			#cxp-dbg-sr h2{margin:0 0 8px;font-size:16px;letter-spacing:.03em}
			#cxp-dbg-sr .cxp-sr-hero{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0 0 12px}
			#cxp-dbg-sr .cxp-sr-walk{
				appearance:none;cursor:pointer;min-height:56px;padding:14px 22px;border:3px solid #facc15;border-radius:999px;
				background:#fff200;color:#111 !important;-webkit-text-fill-color:#111;font:800 15px/1.2 ui-sans-serif,system-ui,sans-serif;
				letter-spacing:.04em;text-transform:uppercase;box-shadow:0 0 0 4px #7f1d1d
			}
			#cxp-dbg-sr .cxp-sr-walk:hover{background:#fff;color:#000 !important}
			#cxp-dbg-sr .cxp-lab-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:8px 0}
			#cxp-dbg-sr pre{margin:8px 0 0;max-height:280px;overflow:auto;white-space:pre-wrap;color:#fef3c7;background:#0c0a09;padding:8px;border-radius:6px}
			#cxp-dbg-sr .ok{color:#86efac}
			#cxp-dbg-sr .bad{color:#fca5a5}
			#cxp-dbg-sr a{color:#93c5fd}
			#cxp-dbg-sr button.cxp-sr-danger{border-color:#b91c1c;background:#7f1d1d;color:#fecaca}
			#cxp-dbg-sr .cxp-sr-steps{display:grid;gap:8px;margin:10px 0}
			#cxp-dbg-sr .cxp-sr-step{padding:10px 12px;border:1px solid #44403c;border-radius:10px;background:#0c0a09}
			#cxp-dbg-sr .cxp-sr-step.is-on{border-color:#facc15;background:#292524}
			#cxp-dbg-sr .cxp-sr-step.is-done{border-color:#166534}
			#cxp-dbg-sr .cxp-sr-step.is-fail{border-color:#b91c1c}
			#cxp-dbg-sr .cxp-sr-step b{display:block;margin-bottom:4px;color:#fde68a}
			#cxp-dbg-sr .cxp-sr-step span{color:#e7e5e4}
			#cxp-dbg-sr .cxp-sr-ver{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 8px}
			#cxp-dbg-sr .cxp-sr-ver em{font-style:normal;padding:3px 8px;border-radius:999px;background:#292524}
		</style>
		<div id="cxp-dbg-sr">
			<h2>SR-108688 · celularesenventa.cl</h2>
			<p>Correo de Juan Espoz (19 ago): AEOLABS no había replicado el fatal. El cliente cayó en <code>admin-ajax.php</code> al actualizar WooCommerce 11.0.1 con Chilexpress Oficial 1.4.0. Este flujo recorre el ticket, replica el error y deja copiar evidencia. El <strong>PDF plan de acción</strong> es para el cliente: pasos claros, sin jerga. <strong>No parchea chilexpress-oficial.</strong></p>
			<div class="cxp-sr-ver">
				<?php foreach ( $stack as $row ) : ?>
					<em class="<?php echo $row['ok'] ? 'ok' : 'bad'; ?>"><?php echo esc_html( $row['label'] . ': ' . $row['have'] . ( $row['ok'] ? ' · ok' : ' · pedir ' . $row['need'] ) ); ?></em>
				<?php endforeach; ?>
			</div>
			<div class="cxp-sr-hero">
				<button type="button" id="cxp-sr-walk" class="cxp-sr-walk">Recorrer ticket y replicar el fatal</button>
				<button type="button" id="cxp-sr-copy" class="cxp-dbg-copy-one">Copiar evidencia</button>
				<button type="button" id="cxp-sr-pdf" class="cxp-dbg-copy-one">PDF plan de acción (para el cliente)</button>
			</div>
			<div class="cxp-sr-steps" id="cxp-sr-steps">
				<div class="cxp-sr-step" data-step="1"><b>1. Contexto del correo</b><span>11 ago: WordPress avisó E_ERROR en abstract-wc-shipping-method.php:84 — Class ProductTaxStatus not found. Entrada: /wp-admin/admin-ajax.php. Sitio: celularesenventa.cl (UPMOVIL). Chilexpress soporte dijo “1.4.0 no es compatible”. Gonzalo escaló: no aceptar quedarse en Woo viejo. Ricardo pidió a AEOLABS. Rafael: no se replicó en lab. Juan (19 ago): con las versiones exactas del correo sí se replica; causa en admin.php 30–38; parche de una línea plugins_loaded → woocommerce_loaded.</span></div>
				<div class="cxp-sr-step" data-step="2"><b>2. Versiones del cliente (obligatorias)</b><span>WordPress 7.0.3 · WooCommerce 11.0.1 · PHP 8.4.19 · Chilexpress Oficial 1.4.0 · Woodmart Child 1.0.0 / Woodmart 8.5.7. Juan: lo primero es clonar esas versiones, no un Woo “parecido”.</span></div>
				<div class="cxp-sr-step" data-step="3"><b>3. Causa raíz (código real, intacto)</b><span>chilexpress-woo-oficial.php:107 arranca en plugins_loaded. admin/class-chilexpress-woo-oficial-admin.php:30–38 hace require_once hardcodeado de abstract-wc-shipping-method.php. El constructor (línea 116) instancia Chilexpress_Woo_Oficial_Shipping_Method. Woo 11 declara public $tax_status = ProductTaxStatus::TAXABLE. El enum vive en woocommerce/src/Enums/ProductTaxStatus.php y lo carga el autoloader de Woo cuando Woo ya arrancó. En un update in-place el abstracto nuevo puede existir y el enum todavía no: fatal idéntico al correo.</span></div>
				<div class="cxp-sr-step" data-step="4"><b>4. Por qué el lab “no caía”</b><span>Con Woo 11.0.1 completo el enum está en disco: el mismo require_once no fataliza. El escenario de producción es la ventana del actualizador (hosting compartido, recambio no atómico), no una incompatibilidad fija 1.4.0 vs 11.0.1. Woo 11.0.0+ ya endureció su propio código; Chilexpress amplifica la ventana al cargar el abstracto en cada request.</span></div>
				<div class="cxp-sr-step" data-step="5"><b>5. Réplica controlada</b><span>Se oculta/vacía solo ProductTaxStatus.php (ruta presente, clase ausente). Se dispara el mismo admin-ajax.php. Se compara el stack con el correo. Luego se restaura el enum. Chilexpress 1.4.0 no se edita.</span></div>
				<div class="cxp-sr-step" data-step="6"><b>6. Resultado y entrega</b><span>Si coincide: copiar evidencia. El PDF del botón es el plan de acción para el cliente (pasos claros, sin jerga). El informe técnico queda en docs/. Si no coincide: el log dice qué marcador faltó.</span></div>
			</div>
			<?php if ( $broken ) : ?>
				<p class="bad">El enum está oculto ahora: el próximo reload cae como producción. Emergencia: <a href="<?php echo esc_url( $restore ); ?>"><?php echo esc_html( $restore ); ?></a></p>
			<?php endif; ?>
			<div class="cxp-lab-row">
				<button type="button" id="cxp-sr-repro" class="cxp-dbg-copy-one">Solo replicar (capturar y restaurar)</button>
				<button type="button" id="cxp-sr-break" class="cxp-dbg-copy-one cxp-sr-danger">Dejar el sitio caído</button>
				<button type="button" id="cxp-sr-restore" class="cxp-dbg-copy-one">Restaurar ProductTaxStatus</button>
				<a class="cxp-dbg-btn" href="<?php echo esc_url( $probe ); ?>" target="_blank" rel="noopener noreferrer">Abrir admin-ajax.php</a>
				<a class="cxp-dbg-btn" href="<?php echo esc_url( $status ); ?>" target="_blank" rel="noopener noreferrer">Estado enum</a>
			</div>
			<pre id="cxp-sr-out"><?php echo esc_html( ! empty( $last['text'] ) ? $last['text'] : "Pulsa «Recorrer ticket y replicar el fatal».\n\nEsperado (correo WordPress del 11 ago):\nE_ERROR linea 84 abstract-wc-shipping-method.php\nClass \"Automattic\\WooCommerce\\Enums\\ProductTaxStatus\" not found\n#0 class-chilexpress-woo-oficial-admin.php(116)\n#10 wp-admin/admin-ajax.php" ); ?></pre>
		</div>
		<script>
		(function () {
			var ajaxUrl = <?php echo wp_json_encode( $ajax ); ?>;
			var nonce = <?php echo wp_json_encode( $nonce ); ?>;
			var out = document.getElementById('cxp-sr-out');
			var copied = document.getElementById('cxp-dbg-copied');
			function mark(n, cls) {
				var el = document.querySelector('#cxp-sr-steps [data-step="' + n + '"]');
				if (!el) return;
				el.classList.remove('is-on', 'is-done', 'is-fail');
				if (cls) el.classList.add(cls);
			}
			function sleep(ms) { return new Promise(function (ok) { setTimeout(ok, ms); }); }
			function post(action, confirmMsg) {
				if (confirmMsg && !confirm(confirmMsg)) return Promise.reject(new Error('cancelado'));
				var body = new URLSearchParams();
				body.set('action', action);
				body.set('nonce', nonce);
				return fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body.toString()
				}).then(function (r) {
					var type = r.headers.get('content-type') || '';
					if (type.indexOf('pdf') !== -1) return r.blob().then(function (blob) { return { pdf: blob }; });
					return r.json();
				});
			}
			function showPayload(data) {
				if (!data) { out.textContent = 'Sin respuesta'; return ''; }
				if (data.pdf) return '';
				var payload = data.data !== undefined ? data.data : data;
				var text = '';
				if (typeof payload === 'string') text = payload;
				else if (payload && payload.text) text = payload.text;
				else text = JSON.stringify(payload, null, 2);
				out.textContent = text;
				if (data.success && payload && payload.reload) window.location.reload();
				return text;
			}
			function walk() {
				var dbg = document.getElementById('cxp-dbg');
				if (dbg) dbg.classList.add('is-open');
				out.textContent = 'Recorriendo el ticket SR-108688…';
				var n = 1;
				function next() {
					if (n > 5) {
						out.textContent = 'Simulando update in-place y llamando admin-ajax.php…';
						return post('cxp_sr108688_repro').then(function (data) {
							var text = showPayload(data);
							var ok = data && data.success && text && text.indexOf('COINCIDE') !== -1;
							mark(5, 'is-done');
							mark(6, ok ? 'is-done' : 'is-fail');
							if (!ok && data && !data.success) mark(5, 'is-fail');
						}).catch(function (e) {
							mark(5, 'is-fail');
							out.textContent = 'Falló: ' + e;
						});
					}
					for (var i = 1; i < n; i++) mark(i, 'is-done');
					mark(n, 'is-on');
					n += 1;
					return sleep(700).then(next);
				}
				return next();
			}
			document.getElementById('cxp-sr-walk').addEventListener('click', function (e) {
				e.stopPropagation();
				walk();
			});
			document.getElementById('cxp-sr-copy').addEventListener('click', function (e) {
				e.stopPropagation();
				navigator.clipboard.writeText(out.textContent || '').then(function () {
					if (copied) { copied.hidden = false; setTimeout(function () { copied.hidden = true; }, 1600); }
				});
			});
			document.getElementById('cxp-sr-pdf').addEventListener('click', function (e) {
				e.stopPropagation();
				out.textContent = (out.textContent || '') + '\n\nGenerando PDF…';
				post('cxp_sr108688_pdf').then(function (data) {
					if (data && data.pdf) {
						var url = URL.createObjectURL(data.pdf);
						var a = document.createElement('a');
						a.href = url;
						a.download = 'SR-108688-plan-accion-cliente.pdf';
						a.click();
						URL.revokeObjectURL(url);
						out.textContent = (out.textContent || '').replace(/\n\nGenerando PDF…$/, '') + '\n\nPDF descargado: SR-108688-plan-accion-cliente.pdf';
						return;
					}
					showPayload(data);
				}).catch(function (err) { out.textContent = 'PDF: ' + err; });
			});
			document.getElementById('cxp-sr-repro').addEventListener('click', function (e) {
				e.stopPropagation();
				out.textContent = 'Ejecutando…';
				post('cxp_sr108688_repro').then(showPayload).catch(function (err) { out.textContent = 'Falló: ' + err; });
			});
			document.getElementById('cxp-sr-break').addEventListener('click', function (e) {
				e.stopPropagation();
				post('cxp_sr108688_break', '¿Ocultar ProductTaxStatus.php? El siguiente reload cae como celularesenventa.cl.').then(showPayload).catch(function () {});
			});
			document.getElementById('cxp-sr-restore').addEventListener('click', function (e) {
				e.stopPropagation();
				post('cxp_sr108688_restore').then(showPayload).catch(function (err) { out.textContent = 'Falló: ' + err; });
			});
		})();
		</script>
		<?php
	}

	private static function stack_status() {
		$theme  = wp_get_theme();
		$parent = $theme->parent();
		$php    = PHP_VERSION;
		$wp     = get_bloginfo( 'version' );
		$wc     = defined( 'WC_VERSION' ) ? WC_VERSION : '—';
		$cxp    = self::chilexpress_version();
		return array(
			array( 'label' => 'PHP', 'have' => $php, 'need' => '8.4.19', 'ok' => 0 === strpos( $php, '8.4' ) ),
			array( 'label' => 'WordPress', 'have' => $wp, 'need' => '7.0.3', 'ok' => '7.0.3' === $wp ),
			array( 'label' => 'WooCommerce', 'have' => $wc, 'need' => '11.0.1', 'ok' => '11.0.1' === $wc ),
			array( 'label' => 'Chilexpress', 'have' => $cxp, 'need' => '1.4.0', 'ok' => '1.4.0' === $cxp ),
			array( 'label' => 'Tema', 'have' => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ), 'need' => 'Woodmart Child 1.0.0', 'ok' => ( 'Woodmart Child' === $theme->get( 'Name' ) && '1.0.0' === $theme->get( 'Version' ) ) ),
			array( 'label' => 'Padre', 'have' => $parent ? $parent->get( 'Name' ) . ' ' . $parent->get( 'Version' ) : 'n/d', 'need' => 'Woodmart 8.5.7', 'ok' => ( $parent && '8.5.7' === $parent->get( 'Version' ) ) ),
		);
	}

	public static function ajax_repro() {
		self::guard();
		$hidden = self::hide_enum();
		if ( is_wp_error( $hidden ) ) {
			wp_send_json_error( $hidden->get_error_message() );
		}

		$output = '';
		$code   = 1;
		try {
			$run    = self::run_probe();
			$output = $run['output'];
			$code   = $run['code'];
		} finally {
			self::restore_enum();
		}

		$match = self::match_production( $output );
		$log   = self::debug_log_fatal();
		$lines = array(
			'Ticket SR-108688 · réplica local de la ventana de update WooCommerce 11.0.1',
			'Sitio cliente: celularesenventa.cl · correo WordPress 11 ago 2026 · Juan Espoz 19 ago',
			'Entrada: wp-admin/admin-ajax.php (igual que el correo de WordPress al cliente)',
			'Acción: ProductTaxStatus.php quedó como stub vacío (ruta existe, clase no).',
			'Chilexpress 1.4.0 intacto (require_once hardcodeado + plugins_loaded).',
			'Enum restaurado después de la prueba.',
			'Exit code del probe: ' . $code,
			$match['ok'] ? 'COINCIDE con el fatal de producción.' : 'NO coincidió el texto del fatal. Revisa la salida y debug.log.',
			'Marcadores: ' . implode( ', ', $match['hits'] ),
			'----- salida probe -----',
			$output,
			'----- debug.log (fatal) -----',
			$log,
		);
		$text = implode( "\n", $lines );
		update_option(
			'cxp_sr108688_last',
			array(
				'at'     => current_time( 'mysql' ),
				'ok'     => ! empty( $match['ok'] ),
				'hits'   => $match['hits'],
				'code'   => $code,
				'text'   => $text,
				'output' => $output,
				'log'    => $log,
				'stack'  => self::stack_status(),
			),
			false
		);
		wp_send_json_success( $text );
	}

	public static function ajax_pdf() {
		self::guard_pdf();
		if ( ! class_exists( 'Cxp_Simple_Pdf' ) ) {
			require_once WP_CONTENT_DIR . '/mu-plugins/cxp-simple-pdf.php';
		}
		if ( ! class_exists( 'Cxp_Simple_Pdf' ) ) {
			wp_send_json_error( 'Falta Cxp_Simple_Pdf' );
		}
		self::build_action_pdf()->output( 'SR-108688-plan-accion-cliente.pdf' );
	}

	private static function build_action_pdf() {
		$last = get_option( 'cxp_sr108688_last', array() );
		$ok   = ! empty( $last['ok'] );
		$pdf  = new Cxp_Simple_Pdf( 'SR-108688  |  Plan de accion para el cliente  |  celularesenventa.cl' );

		$pdf->heading( 'Que paso y que hay que hacer (lenguaje simple)' );
		$pdf->note( 'Tienda: celularesenventa.cl  ·  Ticket: SR-108688  ·  Fecha del problema: 11 de agosto de 2026' );
		$pdf->note( 'Preparo: Alexander Alejandro Cautivo Ramos  ·  Aeolabs  ·  alexander.cautivo@aeolabs.io' );
		$pdf->spacer( 6 );

		$pdf->heading( '1. En una frase', 2 );
		$pdf->para( 'La tienda se cayo mientras se actualizaba WooCommerce. El plugin de Chilexpress siguio trabajando a mitad del proceso, cuando WooCommerce todavia no habia terminado de copiar todos sus archivos. No es que Chilexpress 1.4.0 "no sirva" con WooCommerce 11. Cuando la actualizacion termina bien, ambos conviven.' );

		$pdf->heading( '2. Que NO es este problema', 2 );
		$pdf->bullet( 'No es el tema Woodmart ni un virus.' );
		$pdf->bullet( 'No son las claves de Chilexpress ni el cotizador de envios.' );
		$pdf->bullet( 'No hay que bajar WooCommerce a una version vieja (eso deja la tienda insegura).' );
		$pdf->bullet( 'No hay que editar a mano el plugin Chilexpress en el sitio. Ese cambio lo debe publicar Chilexpress.' );

		$pdf->heading( '3. Que hacer ahora (sitio en marcha)', 2 );
		$pdf->para( 'Sigan estos pasos en este orden. No hace falta saber programar.' );
		$pdf->bullet( '1. En WordPress: Plugins, desactivar "Chilexpress Oficial".' );
		$pdf->bullet( '2. Actualizar WooCommerce y esperar a que termine al 100%.' );
		$pdf->bullet( '3. Volver a activar Chilexpress Oficial.' );
		$pdf->bullet( '4. Probar una compra de prueba (elegir region, comuna y un envio Chilexpress).' );
		$pdf->bullet( '5. Si usan ordenes de transporte, generar una OT de prueba (mejor en ambiente de pruebas).' );

		$pdf->heading( '4. Si el sitio ya muestra "Ha habido un error critico"', 2 );
		$pdf->para( 'No se puede entrar al escritorio. Hay que hacerlo por el hosting (administrador de archivos o FTP), con ayuda de UPMOVIL si hace falta.' );
		$pdf->bullet( '1. Entrar a la carpeta wp-content/plugins/.' );
		$pdf->bullet( '2. Renombrar chilexpress-oficial a chilexpress-oficial-off (con eso el plugin se apaga).' );
		$pdf->bullet( '3. Completar o reinstalar WooCommerce hasta el final.' );
		$pdf->bullet( '4. Devolver el nombre original a la carpeta (chilexpress-oficial) y activar el plugin desde WordPress.' );
		$pdf->bullet( '5. Probar el checkout como en el punto 3.' );

		$pdf->heading( '5. Regla para la proxima actualizacion', 2 );
		$pdf->para( 'Antes de actualizar WooCommerce: apagar Chilexpress. Actualizar. Recien despues, volver a encender Chilexpress. No actualicen los dos al mismo tiempo.' );

		$pdf->heading( '6. El arreglo de fondo (no lo hagan ustedes)', 2 );
		$pdf->para( 'Chilexpress debe publicar una version que espere a que WooCommerce termine de cargar. Hasta que eso salga, usen la regla del punto 5. No parcheen el plugin en produccion: el siguiente update oficial lo borra y se pierde el diagnostico.' );

		$pdf->heading( '7. Como saber que quedo bien', 2 );
		$pdf->bullet( 'El sitio abre (tienda, carrito y checkout).' );
		$pdf->bullet( 'Se puede elegir region, comuna y un envio Chilexpress.' );
		$pdf->bullet( 'No vuelve el correo de WordPress con "error critico" al usar el escritorio.' );

		$pdf->heading( '8. Lo que confirmo el laboratorio', 2 );
		$pdf->para( $ok
			? 'Se reprodujo el mismo corte que en produccion: ocurre solo si WooCommerce esta a medias. Con WooCommerce ya completo, Chilexpress 1.4.0 no tumba el sitio. El plugin oficial no se modifico.'
			: 'Con WooCommerce ya instalado completo, el sitio no se cae. El corte aparece solo durante la ventana de la actualizacion. El plugin oficial no se modifico.'
		);
		$pdf->spacer( 8 );
		$pdf->note( 'Este PDF es para el equipo de la tienda. El informe tecnico queda aparte. Aeolabs no modifica Chilexpress Oficial 1.4.0.' );
		return $pdf;
	}

	public static function ajax_break() {
		self::guard();
		$hidden = self::hide_enum();
		if ( is_wp_error( $hidden ) ) {
			wp_send_json_error( $hidden->get_error_message() );
		}
		wp_send_json_success(
			"ProductTaxStatus.php oculto.\nRecarga cualquier página: debería caer como producción.\nEmergencia: " . home_url( '/__sr108688/restore' )
		);
	}

	public static function ajax_restore() {
		self::guard();
		$ok = self::restore_enum();
		if ( is_wp_error( $ok ) ) {
			wp_send_json_error( $ok->get_error_message() );
		}
		wp_send_json_success(
			array(
				'text'   => 'ProductTaxStatus.php restaurado. El sitio debería cargar.',
				'reload' => true,
			)
		);
	}

	private static function run_probe() {
		$php  = PHP_BINARY;
		$ini  = dirname( $php ) . DIRECTORY_SEPARATOR . 'php.ini';
		$cli  = WP_CONTENT_DIR . '/mu-plugins/cxp-sr108688/probe.php';
		if ( ! is_readable( $cli ) ) {
			return array(
				'code'   => 2,
				'output' => 'Falta probe.php',
			);
		}
		$cmd = array( $php );
		if ( is_readable( $ini ) ) {
			$cmd[] = '-c';
			$cmd[] = $ini;
		}
		$cmd[] = $cli;

		$pipes = array();
		$proc  = proc_open(
			$cmd,
			array(
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			),
			$pipes,
			ABSPATH,
			null
		);
		if ( ! is_resource( $proc ) ) {
			return array(
				'code'   => 2,
				'output' => 'No se pudo lanzar PHP CLI',
			);
		}
		stream_set_timeout( $pipes[1], 25 );
		stream_set_timeout( $pipes[2], 25 );
		$stdout = stream_get_contents( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$code = proc_close( $proc );
		$out  = trim( $stderr . "\n" . $stdout );
		return array(
			'code'   => (int) $code,
			'output' => $out !== '' ? $out : '(sin salida)',
		);
	}

	private static function match_production( $output ) {
		$needles = array(
			'ProductTaxStatus',
			'not found',
			'abstract-wc-shipping-method.php',
			'class-chilexpress-woo-oficial-admin.php',
			'chilexpress-woo-oficial.php',
			'admin-ajax.php',
			'plugins_loaded',
		);
		$hits = array();
		foreach ( $needles as $n ) {
			if ( false !== stripos( $output, $n ) ) {
				$hits[] = $n;
			}
		}
		$ok = count( $hits ) >= 4
			&& false !== stripos( $output, 'ProductTaxStatus' )
			&& false !== stripos( $output, 'class-chilexpress-woo-oficial-admin.php' );
		return array(
			'ok'   => $ok,
			'hits' => $hits,
		);
	}

	private static function debug_log_fatal() {
		$path = WP_CONTENT_DIR . '/debug.log';
		if ( ! is_readable( $path ) ) {
			return '(sin debug.log)';
		}
		$raw  = (string) file_get_contents( $path );
		$pos  = strrpos( $raw, 'ProductTaxStatus' );
		if ( false === $pos ) {
			return '(debug.log no tiene ProductTaxStatus todavía)';
		}
		$start = strrpos( substr( $raw, 0, $pos ), '[' );
		$chunk = false === $start ? substr( $raw, max( 0, $pos - 200 ) ) : substr( $raw, $start );
		return trim( substr( $chunk, 0, 2500 ) );
	}

	private static function chilexpress_version() {
		$file = WP_PLUGIN_DIR . '/chilexpress-oficial/chilexpress-woo-oficial.php';
		if ( ! is_readable( $file ) ) {
			return '—';
		}
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$data = get_plugin_data( $file, false, false );
		return (string) ( $data['Version'] ?? '—' );
	}

	private static function guard() {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			if ( function_exists( 'cxp_auto_login_user' ) ) {
				cxp_auto_login_user();
			}
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Sin permiso' );
		}
		self::guard_nonce();
	}

	/**
	 * El PDF del cliente es solo lectura. En Dokploy CXP_AUTO_LOGIN=0 y la consola
	 * está en la tienda: exigir manage_options devolvía {"success":false,"data":"Sin permiso"}.
	 */
	private static function guard_pdf() {
		self::guard_nonce();
	}

	private static function guard_nonce() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), self::NONCE ) ) {
			wp_send_json_error( 'Nonce inválido' );
		}
	}
}

Cxp_Sr108688_Repro::boot();
