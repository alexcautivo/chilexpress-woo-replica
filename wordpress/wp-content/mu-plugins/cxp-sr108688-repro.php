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
		add_action( 'cxp_debug_console_panels', array( __CLASS__, 'console_panel' ) );
		add_action( 'wp_ajax_cxp_sr108688_repro', array( __CLASS__, 'ajax_repro' ) );
		add_action( 'wp_ajax_nopriv_cxp_sr108688_repro', array( __CLASS__, 'ajax_repro' ) );
		add_action( 'wp_ajax_cxp_sr108688_break', array( __CLASS__, 'ajax_break' ) );
		add_action( 'wp_ajax_nopriv_cxp_sr108688_break', array( __CLASS__, 'ajax_break' ) );
		add_action( 'wp_ajax_cxp_sr108688_restore', array( __CLASS__, 'ajax_restore' ) );
		add_action( 'wp_ajax_nopriv_cxp_sr108688_restore', array( __CLASS__, 'ajax_restore' ) );
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
		$php     = PHP_VERSION;
		$wp      = get_bloginfo( 'version' );
		$wc      = defined( 'WC_VERSION' ) ? WC_VERSION : '—';
		$cxp     = self::chilexpress_version();
		$broken  = self::is_broken();
		$theme    = wp_get_theme();
		$ok_php   = 0 === strpos( $php, '8.4' );
		$ok_wp    = '7.0.3' === $wp;
		$ok_wc    = '11.0.1' === $wc;
		$ok_cxp   = '1.4.0' === $cxp;
		$ok_theme = ( 'Woodmart Child' === $theme->get( 'Name' ) && '1.0.0' === $theme->get( 'Version' ) );
		$parent   = $theme->parent();
		$ok_parent = ( $parent && '8.5.7' === $parent->get( 'Version' ) );
		?>
		<style>
			#cxp-dbg-sr{margin:0 0 12px;padding:10px 12px;border:1px solid #7f1d1d;border-radius:8px;background:#1c1917}
			#cxp-dbg-sr p{margin:0 0 8px;color:#fecaca}
			#cxp-dbg-sr p strong{color:#facc15}
			#cxp-dbg-sr .cxp-lab-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:8px 0}
			#cxp-dbg-sr pre{margin:8px 0 0;max-height:280px;overflow:auto;white-space:pre-wrap;color:#fef3c7;background:#0c0a09;padding:8px;border-radius:6px}
			#cxp-dbg-sr .ok{color:#86efac}
			#cxp-dbg-sr .bad{color:#fca5a5}
			#cxp-dbg-sr a{color:#93c5fd}
			#cxp-dbg-sr button.cxp-sr-danger{border-color:#b91c1c;background:#7f1d1d;color:#fecaca}
		</style>
		<div id="cxp-dbg-sr">
			<p><strong>SR-108688 — caída celularesenventa.cl</strong>
				PHP <?php echo esc_html( $php ); ?>
				<span class="<?php echo $ok_php ? 'ok' : 'bad'; ?>"><?php echo $ok_php ? 'ok' : 'distinto a 8.4.19'; ?></span>
				· WP <?php echo esc_html( $wp ); ?>
				<span class="<?php echo $ok_wp ? 'ok' : 'bad'; ?>"><?php echo $ok_wp ? 'ok' : 'tiene que ser 7.0.3'; ?></span>
				· Woo <?php echo esc_html( $wc ); ?>
				<span class="<?php echo $ok_wc ? 'ok' : 'bad'; ?>"><?php echo $ok_wc ? 'ok' : 'tiene que ser 11.0.1'; ?></span>
				· Chilexpress <?php echo esc_html( $cxp ); ?>
				<span class="<?php echo $ok_cxp ? 'ok' : 'bad'; ?>"><?php echo $ok_cxp ? 'ok' : 'tiene que ser 1.4.0'; ?></span>
				· Tema <?php echo esc_html( $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) ); ?>
				<span class="<?php echo $ok_theme ? 'ok' : 'bad'; ?>"><?php echo $ok_theme ? 'ok' : 'tiene que ser Woodmart Child 1.0.0'; ?></span>
				· Padre <?php echo esc_html( $parent ? $parent->get( 'Name' ) . ' ' . $parent->get( 'Version' ) : 'n/d' ); ?>
				<span class="<?php echo $ok_parent ? 'ok' : 'bad'; ?>"><?php echo $ok_parent ? 'ok' : 'tiene que ser Woodmart 8.5.7'; ?></span>
			</p>
			<p>Producción: <code>admin-ajax.php</code> → Chilexpress
				<code>admin/class-chilexpress-woo-oficial-admin.php:116</code>
				hace <code>new Chilexpress_Woo_Oficial_Shipping_Method()</code>
				después de un <code>require_once</code> hardcodeado de
				<code>abstract-wc-shipping-method.php</code> (líneas 30–38) en
				<code>plugins_loaded</code>. Woo 11 usa
				<code>ProductTaxStatus::TAXABLE</code> en la línea 84. Si ese enum
				aún no está en disco (ventana de update in-place), fatal
				<code>Class "Automattic\WooCommerce\Enums\ProductTaxStatus" not found</code>.
				Con Woo completo el sitio <em>no</em> cae. Este botón oculta solo ese archivo,
				dispara el mismo <code>admin-ajax.php</code> y compara el stack. No parchea Chilexpress.
			</p>
			<?php if ( $broken ) : ?>
				<p class="bad">El enum está oculto ahora: el próximo reload cae como producción.
					Emergencia (sin WordPress): <a href="<?php echo esc_url( $restore ); ?>"><?php echo esc_html( $restore ); ?></a>
				</p>
			<?php endif; ?>
			<div class="cxp-lab-row">
				<button type="button" id="cxp-sr-repro" class="cxp-dbg-copy-one">Replicar caída exacta (capturar y restaurar)</button>
				<button type="button" id="cxp-sr-break" class="cxp-dbg-copy-one cxp-sr-danger">Dejar el sitio caído como producción</button>
				<button type="button" id="cxp-sr-restore" class="cxp-dbg-copy-one">Restaurar ProductTaxStatus</button>
				<a class="cxp-dbg-btn" href="<?php echo esc_url( $probe ); ?>" target="_blank" rel="noopener noreferrer">Abrir admin-ajax.php</a>
				<a class="cxp-dbg-btn" href="<?php echo esc_url( $status ); ?>" target="_blank" rel="noopener noreferrer">Estado enum</a>
			</div>
			<pre id="cxp-sr-out">Esperado (correo WordPress del cliente):
Se ha producido un error del tipo E_ERROR en la línea 84 del archivo
.../woocommerce/includes/abstracts/abstract-wc-shipping-method.php
Uncaught Error: Class "Automattic\WooCommerce\Enums\ProductTaxStatus" not found

#0 .../chilexpress-oficial/admin/class-chilexpress-woo-oficial-admin.php(116)
#3 .../chilexpress-oficial/chilexpress-woo-oficial.php(103)  plugins_loaded
#10 .../wp-admin/admin-ajax.php

Pulsa «Replicar caída exacta».</pre>
		</div>
		<script>
		(function () {
			var ajaxUrl = <?php echo wp_json_encode( $ajax ); ?>;
			var nonce = <?php echo wp_json_encode( $nonce ); ?>;
			var out = document.getElementById('cxp-sr-out');
			function post(action, confirmMsg) {
				if (confirmMsg && !confirm(confirmMsg)) return;
				out.textContent = 'Ejecutando…';
				var body = new URLSearchParams();
				body.set('action', action);
				body.set('nonce', nonce);
				fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body.toString()
				}).then(function (r) { return r.json(); }).then(function (data) {
					if (!data) { out.textContent = 'Sin respuesta'; return; }
					var payload = data.data !== undefined ? data.data : data;
					if (typeof payload === 'string') {
						out.textContent = payload;
					} else if (payload && payload.text) {
						out.textContent = payload.text;
					} else {
						out.textContent = JSON.stringify(payload, null, 2);
					}
					if (data.success && payload && payload.reload) {
						window.location.reload();
					}
				}).catch(function (e) {
					out.textContent = 'Falló: ' + e;
				});
			}
			var repro = document.getElementById('cxp-sr-repro');
			if (repro) repro.addEventListener('click', function (e) {
				e.stopPropagation();
				post('cxp_sr108688_repro');
			});
			var brk = document.getElementById('cxp-sr-break');
			if (brk) brk.addEventListener('click', function (e) {
				e.stopPropagation();
				post('cxp_sr108688_break', '¿Ocultar ProductTaxStatus.php? El siguiente reload cae como celularesenventa.cl. Restaura con el botón o /__sr108688/restore');
			});
			var rst = document.getElementById('cxp-sr-restore');
			if (rst) rst.addEventListener('click', function (e) {
				e.stopPropagation();
				post('cxp_sr108688_restore');
			});
		})();
		</script>
		<?php
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
		wp_send_json_success( implode( "\n", $lines ) );
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
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), self::NONCE ) ) {
			wp_send_json_error( 'Nonce inválido' );
		}
	}
}

Cxp_Sr108688_Repro::boot();
