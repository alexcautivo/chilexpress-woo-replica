<?php
/**
 * Plugin Name: Captura de navegador para incidencias
 * Version: 1.0.0
 * Description: Registra errores JavaScript, recursos y respuestas HTTP del Chrome usado durante un run.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cxp_Incident_Browser_Probe {
	const NONCE = 'cxp_incident_browser_probe';

	public static function boot() {
		add_action( 'wp_footer', array( __CLASS__, 'render' ), 9999 );
		add_action( 'admin_footer', array( __CLASS__, 'render' ), 9999 );
		add_action( 'login_footer', array( __CLASS__, 'render' ), 9999 );
		add_action( 'wp_ajax_cxp_incident_browser_event', array( __CLASS__, 'ajax_event' ) );
		add_action( 'wp_ajax_nopriv_cxp_incident_browser_event', array( __CLASS__, 'ajax_event' ) );
	}

	private static function current() {
		$current = get_option( 'cxp_incident_current_run', array() );
		if ( ! is_array( $current ) || empty( $current['ticket_id'] ) || empty( $current['run_id'] ) ) {
			return array();
		}
		return array(
			'ticket_id' => sanitize_file_name( $current['ticket_id'] ),
			'run_id' => sanitize_file_name( $current['run_id'] ),
		);
	}

	public static function render() {
		$current = self::current();
		if ( ! $current ) {
			return;
		}
		$config = array(
			'ajax' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( self::NONCE ),
			'ticket' => $current['ticket_id'],
			'run' => $current['run_id'],
		);
		?>
		<script>
		(function (cfg) {
			if (window.__cxpIncidentProbe) return;
			window.__cxpIncidentProbe = true;
			var sent = 0;
			function report(kind, detail) {
				if (sent++ > 100) return;
				var body = new URLSearchParams();
				body.set('action', 'cxp_incident_browser_event');
				body.set('nonce', cfg.nonce);
				body.set('ticket_id', cfg.ticket);
				body.set('run_id', cfg.run);
				body.set('kind', kind);
				body.set('url', String(location.href).slice(0, 1000));
				body.set('detail', String(detail || '').slice(0, 5000));
				if (navigator.sendBeacon) {
					navigator.sendBeacon(cfg.ajax, body);
				} else {
					fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body, keepalive: true }).catch(function () {});
				}
			}
			window.addEventListener('error', function (event) {
				var target = event.target;
				if (target && target !== window && (target.src || target.href)) {
					report('resource_error', target.src || target.href);
					return;
				}
				report('javascript_error', (event.message || 'Error') + ' @ ' + (event.filename || '') + ':' + (event.lineno || 0));
			}, true);
			window.addEventListener('unhandledrejection', function (event) {
				report('unhandled_rejection', event.reason && (event.reason.stack || event.reason.message) || event.reason);
			});
			var originalFetch = window.fetch;
			if (originalFetch) {
				window.fetch = function () {
					return originalFetch.apply(this, arguments).then(function (response) {
						if (response.status >= 400 && String(response.url).indexOf('cxp_incident_browser_event') === -1) {
							report('fetch_http', response.status + ' ' + response.url);
						}
						return response;
					}).catch(function (error) {
						report('fetch_failed', error && (error.stack || error.message) || error);
						throw error;
					});
				};
			}
			var open = XMLHttpRequest.prototype.open;
			var send = XMLHttpRequest.prototype.send;
			XMLHttpRequest.prototype.open = function (method, url) {
				this.__cxpUrl = url;
				return open.apply(this, arguments);
			};
			XMLHttpRequest.prototype.send = function () {
				this.addEventListener('loadend', function () {
					if (this.status >= 400 && String(this.__cxpUrl || '').indexOf('cxp_incident_browser_event') === -1) {
						report('xhr_http', this.status + ' ' + (this.__cxpUrl || ''));
					}
				});
				return send.apply(this, arguments);
			};
			report('page_loaded', document.title + ' | ' + location.href);
		})(<?php echo wp_json_encode( $config ); ?>);
		</script>
		<?php
	}

	public static function ajax_event() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_send_json_error( 'Nonce inválido', 403 );
		}
		$current = self::current();
		$ticket = sanitize_file_name( wp_unslash( $_POST['ticket_id'] ?? '' ) );
		$run = sanitize_file_name( wp_unslash( $_POST['run_id'] ?? '' ) );
		if ( ! $current || $ticket !== $current['ticket_id'] || $run !== $current['run_id'] ) {
			wp_send_json_error( 'Run inactivo', 409 );
		}
		$root = function_exists( 'cxp_incidents_dir' ) ? cxp_incidents_dir() : dirname( ABSPATH ) . '/incidents';
		$dir = $root . '/runs/' . $ticket . '/' . $run;
		if ( ! is_dir( $dir ) ) {
			wp_send_json_error( 'Run no encontrado', 404 );
		}
		$file = $dir . '/browser-events.json';
		$events = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array();
		if ( ! is_array( $events ) ) {
			$events = array();
		}
		if ( count( $events ) < 500 ) {
			$events[] = array(
				'at' => gmdate( 'c' ),
				'kind' => sanitize_key( wp_unslash( $_POST['kind'] ?? 'event' ) ),
				'url' => esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ),
				'detail' => sanitize_textarea_field( wp_unslash( $_POST['detail'] ?? '' ) ),
			);
			file_put_contents( $file, wp_json_encode( $events, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n", LOCK_EX );
		}
		wp_send_json_success();
	}
}

Cxp_Incident_Browser_Probe::boot();
