<?php
/**
 * Plugin Name: Motor de incidencias reproducibles
 * Version: 1.1.0
 * Description: Aplica pilas exactas, ejecuta flujos declarativos y compara evidencia por incidencia.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cxp_Incident_Runner {
	const NONCE = 'cxp_incident_runner';
	const RULES_VERSION = '1.0.0';
	const LOCK_OPTION = 'cxp_incident_runner_lock';
	const CURRENT_RUN_OPTION = 'cxp_incident_current_run';
	const MAX_PLUGINS = 40;
	const MAX_STEPS = 50;

	private static $allowed_actions = array(
		'request',
		'open_url',
		'post_ajax',
		'activate_plugin',
		'deactivate_plugin',
		'clear_cache',
		'wait',
		'assert_http',
		'assert_text',
		'assert_log',
		'run_internal_scenario',
		'internal_scenario',
	);

	public static function boot() {
		add_action( 'wp_ajax_cxp_incident_preview', array( __CLASS__, 'ajax_preview' ) );
		add_action( 'wp_ajax_cxp_incident_build_flow', array( __CLASS__, 'ajax_build_flow' ) );
		add_action( 'wp_ajax_cxp_incident_apply', array( __CLASS__, 'ajax_apply' ) );
		add_action( 'wp_ajax_cxp_incident_execute', array( __CLASS__, 'ajax_execute' ) );
		add_action( 'wp_ajax_cxp_incident_restore', array( __CLASS__, 'ajax_restore' ) );
		add_action( 'wp_ajax_cxp_incident_result', array( __CLASS__, 'ajax_result' ) );
		add_action( 'wp_ajax_cxp_incident_pdf', array( __CLASS__, 'ajax_pdf' ) );
	}

	public static function root() {
		return function_exists( 'cxp_incidents_dir' ) ? cxp_incidents_dir() : dirname( ABSPATH ) . '/incidents';
	}

	public static function runs_dir() {
		return self::root() . '/runs';
	}

	public static function latest_run( $ticket_id ) {
		$base = self::runs_dir() . '/' . sanitize_file_name( $ticket_id );
		$dirs = is_dir( $base ) ? glob( $base . '/*', GLOB_ONLYDIR ) : array();
		if ( ! $dirs ) {
			return array();
		}
		rsort( $dirs, SORT_NATURAL );
		$run = self::read_json( $dirs[0] . '/run.json' );
		return $run ?: array( 'run_id' => basename( $dirs[0] ), 'state' => 'desconocida' );
	}

	public static function nonce() {
		return wp_create_nonce( self::NONCE );
	}

	public static function ticket_path( $id ) {
		$id = sanitize_file_name( (string) $id );
		$dir = self::root() . '/tickets';
		if ( ! $id ) {
			return '';
		}
		$direct = $dir . '/' . $id . '.json';
		if ( is_readable( $direct ) ) {
			return $direct;
		}
		foreach ( glob( $dir . '/*.json' ) ?: array() as $file ) {
			$raw = json_decode( (string) file_get_contents( $file ), true );
			if ( is_array( $raw ) && sanitize_file_name( (string) ( $raw['ticket_id'] ?? '' ) ) === $id ) {
				return $file;
			}
		}
		return '';
	}

	public static function load_ticket( $id ) {
		$path = self::ticket_path( $id );
		if ( ! $path ) {
			return new WP_Error( 'cxp_incident_ticket', 'No existe el ticket ' . sanitize_file_name( (string) $id ) );
		}
		$data = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'cxp_incident_ticket', 'El ticket no contiene JSON válido' );
		}
		return $data;
	}

	public static function validate_ticket( $ticket ) {
		$errors = array();
		$version = (string) ( $ticket['schema_version'] ?? '' );
		if ( ! in_array( $version, array( '1.0', '1.1' ), true ) ) {
			$errors[] = 'schema_version debe ser 1.0 o 1.1';
		}
		if ( empty( $ticket['origen']['sitio_url'] ) || 'https://' === $ticket['origen']['sitio_url'] ) {
			$errors[] = 'Falta origen.sitio_url';
		}
		if ( empty( $ticket['sintoma']['resumen'] ) ) {
			$errors[] = 'Falta sintoma.resumen';
		}
		$plugins = self::ticket_plugins( $ticket );
		if ( count( $plugins ) > self::MAX_PLUGINS ) {
			$errors[] = 'La incidencia supera el límite de ' . self::MAX_PLUGINS . ' plugins';
		}
		foreach ( $plugins as $i => $plugin ) {
			$slug = sanitize_key( (string) ( $plugin['slug'] ?? '' ) );
			if ( ! $slug ) {
				$errors[] = 'plugins[' . $i . '] no tiene slug';
			}
			$source = (string) ( $plugin['fuente'] ?? 'wordpress.org' );
			if ( ! in_array( $source, array( 'wordpress.org', 'zip_local', 'repo' ), true ) ) {
				$errors[] = 'plugins[' . $i . '] tiene una fuente no permitida';
			}
			if ( empty( $plugin['version'] ) ) {
				$errors[] = 'plugins[' . $i . '] no tiene versión';
			} elseif ( 'latest' === $plugin['version'] && empty( $ticket['flujo_reproduccion']['politicas']['permitir_latest'] ) ) {
				$errors[] = 'plugins[' . $i . '] pide latest pero la política exige versiones exactas';
			}
		}
		$steps = (array) ( $ticket['flujo_reproduccion']['steps'] ?? array() );
		if ( count( $steps ) > self::MAX_STEPS ) {
			$errors[] = 'El flujo supera el límite de ' . self::MAX_STEPS . ' pasos';
		}
		foreach ( $steps as $i => $step ) {
			$action = self::step_action( $step );
			if ( empty( $step['id'] ) ) {
				$errors[] = 'flujo_reproduccion.steps[' . $i . '] no tiene id';
			}
			if ( ! in_array( $action, self::$allowed_actions, true ) ) {
				$errors[] = 'flujo_reproduccion.steps[' . $i . '] usa una acción no permitida: ' . $action;
			}
			if ( in_array( $action, array( 'request', 'open_url' ), true ) ) {
				$url = (string) ( $step['url'] ?? '' );
				if ( ! $url || 0 !== strpos( $url, '/' ) || 0 === strpos( $url, '//' ) ) {
					$errors[] = 'flujo_reproduccion.steps[' . $i . '] debe usar una ruta relativa segura';
				}
			}
			if ( in_array( $action, array( 'run_internal_scenario', 'internal_scenario' ), true ) ) {
				$scenario = (string) ( $step['escenario'] ?? $step['scenario'] ?? '' );
				if ( ! in_array( $scenario, array( 'sr108688_incomplete_woo_update', 'sr108688_incomplete_woocommerce_update', 'sr108688_restore_enum' ), true ) ) {
					$errors[] = 'flujo_reproduccion.steps[' . $i . '] usa un escenario interno no registrado';
				}
			}
		}
		return $errors;
	}

	public static function current_stack() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active = (array) get_option( 'active_plugins', array() );
		$plugins = array();
		foreach ( get_plugins() as $file => $data ) {
			$slug = dirname( $file );
			if ( '.' === $slug ) {
				$slug = basename( $file, '.php' );
			}
			$plugins[ $slug ] = array(
				'name' => (string) ( $data['Name'] ?? $slug ),
				'version' => (string) ( $data['Version'] ?? '' ),
				'active' => in_array( $file, $active, true ),
				'file' => $file,
			);
		}
		$theme = wp_get_theme();
		return array(
			'php' => PHP_VERSION,
			'wordpress' => self::disk_wp_version(),
			'theme' => array(
				'slug' => get_stylesheet(),
				'name' => $theme->get( 'Name' ),
				'version' => $theme->get( 'Version' ),
			),
			'plugins' => $plugins,
		);
	}

	private static function disk_wp_version() {
		$file = ABSPATH . WPINC . '/version.php';
		if ( ! is_readable( $file ) ) {
			return get_bloginfo( 'version' );
		}
		$raw = (string) file_get_contents( $file );
		return preg_match( '/\\$wp_version\\s*=\\s*[\'"]([^\'"]+)/', $raw, $m ) ? $m[1] : get_bloginfo( 'version' );
	}

	private static function ticket_plugins( $ticket ) {
		$plugins = is_array( $ticket['plugins'] ?? null ) ? $ticket['plugins'] : array();
		$wc = (string) ( $ticket['pila']['woocommerce'] ?? '' );
		$cxp = (string) ( $ticket['pila']['chilexpress_oficial'] ?? '' );
		$has_wc = false;
		$has_cxp = false;
		foreach ( $plugins as $plugin ) {
			if ( 'woocommerce' === ( $plugin['slug'] ?? '' ) ) {
				$has_wc = true;
			}
			if ( 'chilexpress-oficial' === ( $plugin['slug'] ?? '' ) ) {
				$has_cxp = true;
			}
		}
		if ( $wc && ! $has_wc ) {
			$plugins[] = array(
				'nombre' => 'WooCommerce',
				'slug' => 'woocommerce',
				'version' => $wc,
				'activo' => true,
				'fuente' => 'wordpress.org',
			);
		}
		if ( $cxp && ! $has_cxp ) {
			$plugins[] = array(
				'nombre' => 'Chilexpress Oficial',
				'slug' => 'chilexpress-oficial',
				'version' => $cxp,
				'activo' => true,
				'fuente' => 'repo',
			);
		}
		return $plugins;
	}

	public static function preview( $ticket ) {
		$current = self::current_stack();
		$requested = array(
			'php' => (string) ( $ticket['pila']['php'] ?? '' ),
			'wordpress' => (string) ( $ticket['pila']['wordpress'] ?? '' ),
			'plugins' => self::ticket_plugins( $ticket ),
			'theme' => $ticket['pila']['tema'] ?? array(),
		);
		$changes = array();
		if ( $requested['php'] && $requested['php'] !== $current['php'] ) {
			$changes[] = 'PHP ' . $current['php'] . ' → ' . $requested['php'] . ' (requiere reinicio)';
		}
		if ( $requested['wordpress'] && $requested['wordpress'] !== $current['wordpress'] ) {
			$changes[] = 'WordPress ' . $current['wordpress'] . ' → ' . $requested['wordpress'];
		}
		foreach ( $requested['plugins'] as $plugin ) {
			$slug = (string) ( $plugin['slug'] ?? '' );
			$actual = (string) ( $current['plugins'][ $slug ]['version'] ?? 'no instalado' );
			$changes[] = $slug . ' ' . $actual . ' → ' . (string) ( $plugin['version'] ?? 'latest' ) .
				( ! empty( $plugin['activo'] ) ? ' activo' : ' inactivo' );
		}
		return array(
			'valid' => ! self::validate_ticket( $ticket ),
			'errors' => self::validate_ticket( $ticket ),
			'current' => $current,
			'requested' => $requested,
			'changes' => $changes,
			'flow' => (array) ( $ticket['flujo_reproduccion']['steps'] ?? array() ),
		);
	}

	public static function ajax_preview() {
		self::guard();
		$ticket = self::load_ticket( wp_unslash( $_POST['id'] ?? '' ) );
		if ( is_wp_error( $ticket ) ) {
			wp_send_json_error( $ticket->get_error_message() );
		}
		wp_send_json_success( self::preview( $ticket ) );
	}

	public static function ajax_build_flow() {
		self::guard();
		$id = sanitize_file_name( wp_unslash( $_POST['id'] ?? '' ) );
		$ticket = self::load_ticket( $id );
		if ( is_wp_error( $ticket ) ) {
			wp_send_json_error( $ticket->get_error_message() );
		}
		$message = (string) ( $ticket['sintoma']['mensaje_error'] ?? '' );
		$url = (string) ( $ticket['sintoma']['url_donde_falla'] ?? '/' );
		if ( preg_match( '#^https?://#i', $url ) ) {
			$url = (string) ( wp_parse_url( $url, PHP_URL_PATH ) ?: '/' );
		}
		if ( 0 !== strpos( $url, '/' ) ) {
			$url = '/' . ltrim( $url, '/' );
		}
		$steps = array(
			array(
				'id' => 'abrir-url-reportada',
				'op' => 'open_url',
				'descripcion' => 'Abrir la URL donde el cliente informó el fallo',
				'url' => $url,
			),
		);
		if ( false !== stripos( $message, 'ProductTaxStatus' ) && 'SR-108688' === $id ) {
			$steps[] = array(
				'id' => 'simular-update-incompleto',
				'op' => 'run_internal_scenario',
				'descripcion' => 'Simular ventana de actualización incompleta de WooCommerce',
				'escenario' => 'sr108688_incomplete_woo_update',
			);
		}
		$marker = '';
		if ( preg_match( '/Class\\s+[\'"]?([^\'"\\s]+)[\'"]?\\s+not found/i', $message, $match ) ) {
			$marker = $match[1];
		} elseif ( $message ) {
			$marker = trim( substr( preg_replace( '/\\s+/', ' ', $message ), 0, 120 ) );
		}
		if ( $marker ) {
			$steps[] = array(
				'id' => 'buscar-marcador-reportado',
				'op' => 'assert_log',
				'descripcion' => 'Buscar el marcador principal reportado',
				'texto' => $marker,
			);
		}
		$ticket['flujo_reproduccion'] = array(
			'steps' => $steps,
		);
		$path = self::ticket_path( $id );
		if ( ! $path || ! self::write_json( $path, $ticket ) ) {
			wp_send_json_error( 'No se pudo actualizar el ticket.' );
		}
		wp_send_json_success( array(
			'text' => 'Flujo declarativo seguro creado para ' . $id . ".\n" . wp_json_encode( $steps, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			'steps' => $steps,
		) );
	}

	public static function ajax_apply() {
		self::guard();
		@set_time_limit( 900 );
		$id = sanitize_file_name( wp_unslash( $_POST['id'] ?? '' ) );
		$resume_run = sanitize_file_name( wp_unslash( $_POST['run_id'] ?? '' ) );
		$result = self::apply_ticket( $id, $resume_run );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		wp_send_json_success( $result );
	}

	public static function apply_ticket( $id, $resume_run = '' ) {
		$ticket = self::load_ticket( $id );
		if ( is_wp_error( $ticket ) ) {
			return $ticket;
		}
		$errors = self::validate_ticket( $ticket );
		if ( $errors ) {
			return new WP_Error( 'cxp_incident_validation', implode( "\n", $errors ) );
		}
		$locked = self::acquire_lock( $id );
		if ( is_wp_error( $locked ) ) {
			return $locked;
		}
		try {
			$run_id = $resume_run ?: self::new_run_id();
			$run_dir = self::run_dir( $id, $run_id );
			if ( ! is_dir( $run_dir ) && ! wp_mkdir_p( $run_dir ) ) {
				return new WP_Error( 'cxp_incident_run', 'No se pudo crear ' . $run_dir );
			}
			$run = self::read_json( $run_dir . '/run.json' );
			if ( ! $run ) {
				$run = array(
					'ticket_id' => $id,
					'run_id' => $run_id,
					'state' => 'validada',
					'created_at' => gmdate( 'c' ),
					'rules_version' => self::RULES_VERSION,
					'events' => array(),
				);
				self::write_run( $run_dir, $run, 'Incidencia validada' );
				$snapshot = self::create_snapshot( $run_dir );
				if ( is_wp_error( $snapshot ) ) {
					self::write_run( $run_dir, $run, 'Falló snapshot: ' . $snapshot->get_error_message(), 'fallida' );
					return $snapshot;
				}
				self::write_run( $run_dir, $run, 'Snapshot completo creado', 'snapshot' );
			}

			$requested_php = (string) ( $ticket['pila']['php'] ?? '' );
			if ( $requested_php && $requested_php !== PHP_VERSION ) {
				if ( class_exists( 'Cxp_Stack_Versions' ) ) {
					$prepared = Cxp_Stack_Versions::set_php_target( $requested_php );
					if ( is_wp_error( $prepared ) ) {
						return $prepared;
					}
					self::write_run( $run_dir, $run, (string) $prepared, 'requiere_reinicio_php' );
				}
				update_option( self::CURRENT_RUN_OPTION, array( 'ticket_id' => $id, 'run_id' => $run_id ), false );
				return array(
					'ticket_id' => $id,
					'run_id' => $run_id,
					'state' => 'requiere_reinicio_php',
					'text' => 'PHP solicitada ' . $requested_php . '. Reinicia start.sh o reconstruye Docker y pulsa Continuar.',
				);
			}

			self::write_run( $run_dir, $run, 'Aplicando pila exacta', 'aplicando' );
			$log = array();
			$wp_version = (string) ( $ticket['pila']['wordpress'] ?? '' );
			if ( $wp_version && class_exists( 'Cxp_Stack_Versions' ) ) {
				$done = Cxp_Stack_Versions::install_wordpress( $wp_version );
				if ( is_wp_error( $done ) ) {
					return self::apply_failure( $run_dir, $run, $done );
				}
				$log[] = $done;
			}
			foreach ( self::ticket_plugins( $ticket ) as $plugin ) {
				$done = self::apply_plugin( $plugin );
				if ( is_wp_error( $done ) ) {
					return self::apply_failure( $run_dir, $run, $done );
				}
				$log[] = $done;
			}
			$theme_done = self::apply_theme( $ticket['pila']['tema'] ?? null );
			if ( is_wp_error( $theme_done ) ) {
				return self::apply_failure( $run_dir, $run, $theme_done );
			}
			if ( $theme_done ) {
				$log[] = $theme_done;
			}
			self::clear_runtime_caches();
			$verified = self::verify_applied_stack( $ticket );
			self::write_json( $run_dir . '/stack-after.json', self::current_stack() );
			self::write_json( $run_dir . '/apply-log.json', array( 'log' => $log, 'verification' => $verified ) );
			if ( ! $verified['ok'] ) {
				return self::apply_failure( $run_dir, $run, new WP_Error( 'cxp_incident_verify', implode( '; ', $verified['errors'] ) ) );
			}
			$health = self::health_checks();
			self::write_json( $run_dir . '/health.json', $health );
			self::write_run( $run_dir, $run, 'Pila instalada y verificada', 'lista' );
			update_option( self::CURRENT_RUN_OPTION, array( 'ticket_id' => $id, 'run_id' => $run_id ), false );
			return array(
				'ticket_id' => $id,
				'run_id' => $run_id,
				'state' => 'lista',
				'text' => implode( "\n", array_map( 'strval', $log ) ),
				'health' => $health,
			);
		} finally {
			self::release_lock();
		}
	}

	private static function apply_failure( $run_dir, &$run, $error ) {
		self::write_run( $run_dir, $run, 'Aplicación detenida: ' . $error->get_error_message(), 'fallida' );
		return new WP_Error( 'cxp_incident_apply', $error->get_error_message() . "\nLa pila puede estar parcial. Usa Restaurar snapshot." );
	}

	private static function apply_plugin( $plugin ) {
		$slug = sanitize_key( (string) ( $plugin['slug'] ?? '' ) );
		$version = sanitize_text_field( (string) ( $plugin['version'] ?? '' ) );
		$source = (string) ( $plugin['fuente'] ?? 'wordpress.org' );
		$active = ! empty( $plugin['activo'] );
		if ( 'repo' === $source && 'chilexpress-oficial' === $slug ) {
			$done = Cxp_Stack_Versions::restore_chilexpress();
		} elseif ( 'zip_local' === $source ) {
			$name = sanitize_file_name( (string) ( $plugin['archivo_zip'] ?? $slug . '-' . $version . '.zip' ) );
			$zip = dirname( self::root() ) . '/drop-plugins/' . $name;
			if ( ! is_readable( $zip ) ) {
				$zip = dirname( ABSPATH ) . '/drop-plugins/' . $name;
			}
			if ( ! is_readable( $zip ) || ! class_exists( 'Cxp_Plugin_Lab' ) ) {
				return new WP_Error( 'cxp_incident_zip', 'Falta ZIP local autorizado: ' . $name );
			}
			$checksum = self::verify_zip_checksum( $zip, (string) ( $plugin['checksum'] ?? '' ) );
			if ( is_wp_error( $checksum ) ) {
				return $checksum;
			}
			$done = Cxp_Plugin_Lab::install_zip( $zip, $active, false );
			if ( true === $done ) {
				$done = $slug . ' instalado desde ZIP local';
			}
		} else {
			$done = Cxp_Stack_Versions::install_plugin_zip( $slug, 'latest' === $version ? '' : $version );
		}
		if ( is_wp_error( $done ) ) {
			return $done;
		}
		if ( $active ) {
			Cxp_Stack_Versions::activate_slug( $slug );
		} else {
			$off = Cxp_Stack_Versions::deactivate_slug( $slug );
			if ( is_wp_error( $off ) ) {
				return $off;
			}
		}
		return (string) $done . ( $active ? ' [activo]' : ' [inactivo]' );
	}

	private static function apply_theme( $theme ) {
		if ( ! is_array( $theme ) || empty( $theme['slug'] ) ) {
			return '';
		}
		$slug = sanitize_key( $theme['slug'] );
		$version = sanitize_text_field( (string) ( $theme['version'] ?? '' ) );
		$source = (string) ( $theme['fuente'] ?? 'wordpress.org' );
		if ( 'zip_local' === $source ) {
			$installed = wp_get_theme( $slug );
			if ( $installed->exists() && ( ! $version || $installed->get( 'Version' ) === $version ) ) {
				switch_theme( $slug );
				return 'Tema local existente ' . $slug . ' ' . $installed->get( 'Version' ) . ' activado.';
			}
			$name = sanitize_file_name( (string) ( $theme['archivo_zip'] ?? $slug . '-' . $version . '.zip' ) );
			$zip = dirname( self::root() ) . '/drop-plugins/' . $name;
			if ( ! is_readable( $zip ) ) {
				$zip = dirname( ABSPATH ) . '/drop-plugins/' . $name;
			}
			if ( ! is_readable( $zip ) ) {
				return new WP_Error( 'cxp_incident_theme', 'Falta ZIP local del tema: ' . $name );
			}
			$checksum = self::verify_zip_checksum( $zip, (string) ( $theme['checksum'] ?? '' ) );
			if ( is_wp_error( $checksum ) ) {
				return $checksum;
			}
			return Cxp_Stack_Versions::install_theme_local_zip( $slug, $zip );
		}
		if ( 'wordpress.org' !== $source ) {
			return new WP_Error( 'cxp_incident_theme', 'Fuente de tema no instalable: ' . $source );
		}
		return Cxp_Stack_Versions::install_theme_zip(
			$slug,
			$version
		);
	}

	private static function verify_applied_stack( $ticket ) {
		$errors = array();
		$wp = (string) ( $ticket['pila']['wordpress'] ?? '' );
		if ( $wp && self::disk_wp_version() !== $wp ) {
			$errors[] = 'WordPress real ' . self::disk_wp_version() . ', solicitado ' . $wp;
		}
		foreach ( self::ticket_plugins( $ticket ) as $plugin ) {
			$version = (string) ( $plugin['version'] ?? '' );
			$actual = Cxp_Stack_Versions::installed_plugin_version( (string) $plugin['slug'] );
			if ( 'latest' !== $version && $actual !== $version ) {
				$errors[] = $plugin['slug'] . ' real ' . ( $actual ?: 'no instalado' ) . ', solicitado ' . $version;
			}
		}
		return array( 'ok' => ! $errors, 'errors' => $errors );
	}

	private static function verify_zip_checksum( $file, $expected ) {
		$expected = strtolower( trim( $expected ) );
		if ( '' === $expected ) {
			return true;
		}
		if ( 0 !== strpos( $expected, 'sha256:' ) || 71 !== strlen( $expected ) ) {
			return new WP_Error( 'cxp_incident_checksum', 'Checksum debe usar sha256: seguido de 64 caracteres hex.' );
		}
		$actual = 'sha256:' . hash_file( 'sha256', $file );
		if ( ! hash_equals( $expected, $actual ) ) {
			return new WP_Error( 'cxp_incident_checksum', 'El checksum del ZIP local no coincide.' );
		}
		return true;
	}

	public static function ajax_execute() {
		self::guard();
		@set_time_limit( 300 );
		$id = sanitize_file_name( wp_unslash( $_POST['id'] ?? '' ) );
		$run_id = sanitize_file_name( wp_unslash( $_POST['run_id'] ?? '' ) );
		$result = self::execute_flow( $id, $run_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		wp_send_json_success( $result );
	}

	public static function execute_flow( $id, $run_id ) {
		$ticket = self::load_ticket( $id );
		if ( is_wp_error( $ticket ) ) {
			return $ticket;
		}
		$run_dir = self::run_dir( $id, $run_id );
		$run = self::read_json( $run_dir . '/run.json' );
		if ( ! $run || ! in_array( $run['state'] ?? '', array( 'lista', 'comparada', 'comparada_con_fallos' ), true ) ) {
			return new WP_Error( 'cxp_incident_run', 'La pila de este run no está lista' );
		}
		$locked = self::acquire_lock( $id );
		if ( is_wp_error( $locked ) ) {
			return $locked;
		}
		try {
			self::write_run( $run_dir, $run, 'Ejecutando flujo declarativo', 'ejecutando' );
			$debug = WP_CONTENT_DIR . '/debug.log';
			$offset = is_file( $debug ) ? filesize( $debug ) : 0;
			$context = array( 'last_status' => null, 'last_body' => '', 'last_url' => '' );
			$results = array();
			$steps = (array) ( $ticket['flujo_reproduccion']['steps'] ?? array() );
			foreach ( $steps as $index => $step ) {
				$started = microtime( true );
				$out = self::execute_step( $step, $context, $debug, $offset );
				$out['index'] = $index + 1;
				$out['duration_ms'] = (int) round( ( microtime( true ) - $started ) * 1000 );
				$results[] = $out;
				if ( empty( $out['ok'] ) && ! empty( $step['stop_on_failure'] ) ) {
					break;
				}
			}
			$log_delta = self::log_delta( $debug, $offset );
			$assertions = self::evaluate_assertions(
				(array) ( $ticket['flujo_reproduccion']['assertions'] ?? array() ),
				$context,
				$log_delta
			);
			$results = array_merge( $results, $assertions );
			$browser_events = self::read_json( $run_dir . '/browser-events.json' );
			$actual = self::actual_evidence( $context, $log_delta, $results, $browser_events );
			$comparison = self::compare_evidence( $ticket, $actual );
			$reported_evidence = (array) ( $ticket['evidencia'] ?? array() );
			self::write_json( $run_dir . '/reported-evidence.json', $reported_evidence );
			$capture = ltrim( (string) ( $reported_evidence['captura_archivo'] ?? '' ), '/' );
			if ( 0 === strpos( $capture, 'evidence/' ) && is_readable( self::root() . '/' . $capture ) ) {
				$extension = strtolower( pathinfo( $capture, PATHINFO_EXTENSION ) );
				copy( self::root() . '/' . $capture, $run_dir . '/reported-screenshot.' . $extension );
			}
			self::write_json( $run_dir . '/steps.json', $results );
			file_put_contents( $run_dir . '/debug.log', $log_delta );
			self::write_json( $run_dir . '/result.json', $actual );
			self::write_json( $run_dir . '/comparison.json', $comparison );
			$failed = count( array_filter( $results, static function ( $step ) { return isset( $step['ok'] ) && ! $step['ok']; } ) );
			self::write_run(
				$run_dir,
				$run,
				'Flujo finalizado: ' . $comparison['verdict'] . ( $failed ? ' (' . $failed . ' paso(s) fallidos)' : ' (todos los pasos OK)' ),
				$failed ? 'comparada_con_fallos' : 'comparada'
			);
			return array(
				'ticket_id' => $id,
				'run_id' => $run_id,
				'state' => 'comparada',
				'steps' => $results,
				'actual' => $actual,
				'comparison' => $comparison,
			);
		} finally {
			self::release_lock();
		}
	}

	private static function execute_step( $step, &$context, $debug, $offset ) {
		$action = self::step_action( $step );
		$label = (string) ( $step['label'] ?? $step['descripcion'] ?? $action );
		$result = array( 'action' => $action, 'label' => $label, 'ok' => true, 'message' => '' );
		switch ( $action ) {
			case 'request':
			case 'open_url':
				$url = self::safe_url( (string) ( $step['url'] ?? '/' ) );
				if ( is_wp_error( $url ) ) {
					return array_merge( $result, array( 'ok' => false, 'message' => $url->get_error_message() ) );
				}
				$response = wp_remote_request( $url, array(
					'method' => strtoupper( (string) ( $step['method'] ?? 'GET' ) ),
					'timeout' => min( 30, max( 1, (int) ( $step['timeout'] ?? 20 ) ) ),
					'body' => is_array( $step['body'] ?? null ) ? $step['body'] : ( is_array( $step['campos'] ?? null ) ? $step['campos'] : null ),
					'redirection' => 3,
				) );
				if ( is_wp_error( $response ) ) {
					return array_merge( $result, array( 'ok' => false, 'message' => $response->get_error_message() ) );
				}
				$context['last_status'] = wp_remote_retrieve_response_code( $response );
				$context['last_body'] = substr( (string) wp_remote_retrieve_body( $response ), 0, 20000 );
				$context['last_url'] = $url;
				$result['status'] = $context['last_status'];
				$result['message'] = 'HTTP ' . $context['last_status'] . ' ' . $url;
				break;
			case 'post_ajax':
				$ajax_action = sanitize_key( (string) ( $step['ajax_action'] ?? $step['action'] ?? '' ) );
				$response = wp_remote_post( admin_url( 'admin-ajax.php' ), array(
					'timeout' => 30,
					'body' => array_merge( (array) ( $step['body'] ?? $step['campos'] ?? array() ), array( 'action' => $ajax_action ) ),
				) );
				if ( is_wp_error( $response ) ) {
					return array_merge( $result, array( 'ok' => false, 'message' => $response->get_error_message() ) );
				}
				$context['last_status'] = wp_remote_retrieve_response_code( $response );
				$context['last_body'] = substr( (string) wp_remote_retrieve_body( $response ), 0, 20000 );
				$context['last_url'] = admin_url( 'admin-ajax.php?action=' . $ajax_action );
				$result['status'] = $context['last_status'];
				$result['message'] = 'AJAX ' . $ajax_action . ' HTTP ' . $context['last_status'];
				break;
			case 'activate_plugin':
				Cxp_Stack_Versions::activate_slug( sanitize_key( $step['slug'] ?? '' ) );
				$result['message'] = 'Plugin activado';
				break;
			case 'deactivate_plugin':
				$done = Cxp_Stack_Versions::deactivate_slug( sanitize_key( $step['slug'] ?? '' ) );
				$result['ok'] = ! is_wp_error( $done );
				$result['message'] = is_wp_error( $done ) ? $done->get_error_message() : 'Plugin desactivado';
				break;
			case 'clear_cache':
				self::clear_runtime_caches();
				$result['message'] = 'Caches y transients limpiados';
				break;
			case 'wait':
				$seconds = min( 5, max( 0, (int) ( $step['seconds'] ?? $step['segundos'] ?? 1 ) ) );
				sleep( $seconds );
				$result['message'] = 'Espera ' . $seconds . ' s';
				break;
			case 'assert_http':
				$expected = (int) ( $step['status'] ?? 200 );
				$result['ok'] = $context['last_status'] === $expected;
				$result['message'] = 'Esperado HTTP ' . $expected . ', real ' . (string) $context['last_status'];
				break;
			case 'assert_text':
				$text = (string) ( $step['contains'] ?? $step['texto'] ?? '' );
				$result['ok'] = '' !== $text && false !== stripos( $context['last_body'], $text );
				$result['message'] = $result['ok'] ? 'Texto encontrado' : 'No se encontró: ' . $text;
				break;
			case 'assert_log':
				$text = (string) ( $step['contains'] ?? $step['texto'] ?? '' );
				$delta = self::log_delta( $debug, $offset );
				$result['ok'] = '' !== $text && false !== stripos( $delta, $text );
				$result['message'] = $result['ok'] ? 'Marcador encontrado en log' : 'No se encontró en log: ' . $text;
				break;
			case 'run_internal_scenario':
			case 'internal_scenario':
				$result = array_merge( $result, self::execute_internal_scenario( (string) ( $step['scenario'] ?? $step['escenario'] ?? '' ), $context ) );
				break;
			default:
				$result['ok'] = false;
				$result['message'] = 'Acción no permitida';
		}
		return $result;
	}

	private static function execute_internal_scenario( $scenario, &$context ) {
		if ( 'sr108688_restore_enum' === $scenario && class_exists( 'Cxp_Sr108688_Repro' ) ) {
			$restored = Cxp_Sr108688_Repro::restore_enum();
			return array(
				'ok' => ! is_wp_error( $restored ),
				'message' => is_wp_error( $restored ) ? $restored->get_error_message() : 'ProductTaxStatus restaurado',
			);
		}
		if ( ! in_array( $scenario, array( 'sr108688_incomplete_woo_update', 'sr108688_incomplete_woocommerce_update' ), true ) || ! class_exists( 'Cxp_Sr108688_Repro' ) ) {
			return array( 'ok' => false, 'message' => 'Escenario interno no registrado: ' . $scenario );
		}
		$hidden = Cxp_Sr108688_Repro::hide_enum();
		if ( is_wp_error( $hidden ) ) {
			return array( 'ok' => false, 'message' => $hidden->get_error_message() );
		}
		try {
			$response = wp_remote_get( admin_url( 'admin-ajax.php' ), array( 'timeout' => 30 ) );
			if ( is_wp_error( $response ) ) {
				$context['last_status'] = 0;
				$context['last_body'] = $response->get_error_message();
			} else {
				$context['last_status'] = wp_remote_retrieve_response_code( $response );
				$context['last_body'] = substr( (string) wp_remote_retrieve_body( $response ), 0, 20000 );
			}
			$context['last_url'] = admin_url( 'admin-ajax.php' );
		} finally {
			Cxp_Sr108688_Repro::restore_enum();
		}
		return array( 'ok' => true, 'status' => $context['last_status'], 'message' => 'Ventana de update incompleto simulada y restaurada' );
	}

	private static function step_action( $step ) {
		if ( ! is_array( $step ) ) {
			return '';
		}
		return (string) ( $step['op'] ?? $step['action_type'] ?? $step['accion'] ?? $step['action'] ?? '' );
	}

	private static function actual_evidence( $context, $log, $steps, $browser_events = array() ) {
		$browser_text = '';
		foreach ( (array) $browser_events as $event ) {
			$browser_text .= (string) ( $event['kind'] ?? '' ) . ': ' . (string) ( $event['detail'] ?? '' ) . "\n";
		}
		$combined = $context['last_body'] . "\n" . $log . "\n" . $browser_text;
		$signature = self::normalize_error( $combined );
		$failed_steps = array_values( array_filter( $steps, static function ( $step ) { return isset( $step['ok'] ) && ! $step['ok']; } ) );
		return array(
			'captured_at' => gmdate( 'c' ),
			'url' => $context['last_url'],
			'http_status' => $context['last_status'],
			'signature' => $signature,
			'log_excerpt' => substr( $log, -30000 ),
			'body_excerpt' => substr( $context['last_body'], 0, 5000 ),
			'browser_events' => array_slice( (array) $browser_events, -100 ),
			'steps_ok' => count( array_filter( $steps, static function ( $step ) { return ! empty( $step['ok'] ); } ) ),
			'steps_total' => count( $steps ),
			'steps_failed' => count( $failed_steps ),
			'execution_ok' => count( $steps ) > 0 && ! $failed_steps,
			'stack' => self::current_stack(),
		);
	}

	private static function evaluate_assertions( $assertions, $context, $log ) {
		$out = array();
		$signature = self::normalize_error( (string) $context['last_body'] . "\n" . $log );
		foreach ( $assertions as $assertion ) {
			$type = (string) ( $assertion['tipo'] ?? '' );
			$expected = $assertion['esperado'] ?? '';
			$actual = '';
			$ok = false;
			switch ( $type ) {
				case 'http_status':
					$actual = (int) $context['last_status'];
					$ok = $actual === (int) $expected;
					break;
				case 'body_contains':
					$actual = substr( (string) $context['last_body'], 0, 500 );
					$ok = false !== stripos( (string) $context['last_body'], (string) $expected );
					break;
				case 'body_not_contains':
					$actual = substr( (string) $context['last_body'], 0, 500 );
					$ok = false === stripos( (string) $context['last_body'], (string) $expected );
					break;
				case 'log_contains':
					$actual = substr( $log, -1000 );
					$ok = false !== stripos( $log, (string) $expected );
					break;
				case 'php_error_class':
					$actual = (string) ( $signature['class'] ?? '' );
					$ok = $actual === (string) $expected;
					break;
				case 'php_error_file':
					$actual = basename( (string) ( $signature['file'] ?? '' ) );
					$ok = $actual === basename( (string) $expected );
					break;
				case 'php_error_line':
					$actual = (int) ( $signature['line'] ?? 0 );
					$ok = $actual === (int) $expected;
					break;
				case 'php_error_type':
					$actual = (string) ( $signature['type'] ?? '' );
					$normalized = 'E_ERROR' === $expected ? 'fatal error' : strtolower( (string) $expected );
					$ok = false !== stripos( strtolower( $actual ), $normalized );
					break;
			}
			$out[] = array(
				'action' => 'assertion:' . $type,
				'label' => (string) ( $assertion['id'] ?? $type ),
				'ok' => $ok,
				'message' => 'Esperado: ' . (string) $expected . ' | Real: ' . ( is_scalar( $actual ) ? (string) $actual : wp_json_encode( $actual ) ),
				'duration_ms' => 0,
			);
		}
		return $out;
	}

	public static function normalize_error( $text ) {
		$text = wp_strip_all_tags( html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$out = array( 'type' => '', 'class' => '', 'message' => '', 'file' => '', 'line' => 0, 'markers' => array() );
		if ( preg_match( '/(PHP\\s+)?(Fatal error|Parse error|Warning|Notice|Deprecated|Uncaught\\s+[A-Za-z0-9_\\\\]+)\\s*:?\\s*(.*?)(?:\\s+in\\s+([^\\r\\n]+?)\\s+on line\\s+(\\d+)|\\r?\\n|$)/i', $text, $m ) ) {
			$out['type'] = trim( $m[2] );
			$out['message'] = trim( $m[3] );
			$out['file'] = isset( $m[4] ) ? wp_normalize_path( trim( $m[4] ) ) : '';
			$out['line'] = isset( $m[5] ) ? (int) $m[5] : 0;
		}
		if ( preg_match( '/Class\\s+[\'"]?([^\'"\\s]+)[\'"]?\\s+not found/i', $text, $m ) ) {
			$out['class'] = $m[1];
			$out['markers'][] = 'class_not_found';
		}
		foreach ( array( 'ProductTaxStatus', 'admin-ajax.php', 'abstract-wc-shipping-method.php', 'plugins_loaded', 'woocommerce_loaded' ) as $marker ) {
			if ( false !== stripos( $text, $marker ) ) {
				$out['markers'][] = $marker;
			}
		}
		$out['markers'] = array_values( array_unique( $out['markers'] ) );
		if ( ! $out['message'] ) {
			$out['message'] = trim( substr( preg_replace( '/\\s+/', ' ', $text ), 0, 500 ) );
		}
		return $out;
	}

	public static function compare_evidence( $ticket, $actual ) {
		$reported_text = (string) ( $ticket['sintoma']['mensaje_error'] ?? '' ) . "\n" .
			(string) ( $ticket['sintoma']['resultado_obtenido'] ?? '' ) . "\n" .
			(string) ( $ticket['evidencia']['correo_wordpress'] ?? '' ) . "\n" .
			(string) ( $ticket['evidencia']['ticket_texto'] ?? '' ) . "\n" .
			(string) ( $ticket['evidencia']['capturas_notas'] ?? '' ) . "\n" .
			(string) ( $ticket['evidencia']['debug_log_extracto'] ?? '' );
		$reported = self::normalize_error( $reported_text );
		$reported['class'] = (string) ( $ticket['evidencia']['clase_error'] ?? $reported['class'] );
		$reported['file'] = (string) ( $ticket['evidencia']['archivo'] ?? $reported['file'] );
		$reported['line'] = (int) ( $ticket['evidencia']['linea'] ?? $reported['line'] );
		$reported['http_status'] = $ticket['evidencia']['http_status'] ?? null;
		$real = (array) ( $actual['signature'] ?? array() );
		$checks = array();
		$checks['class'] = ! empty( $reported['class'] ) && $reported['class'] === ( $real['class'] ?? '' );
		$checks['message'] = ! empty( $reported['message'] ) && self::similar_text_score( $reported['message'], (string) ( $real['message'] ?? '' ) ) >= 0.55;
		$reported_file = basename( (string) ( $reported['file'] ?? '' ) );
		$real_file = basename( (string) ( $real['file'] ?? '' ) );
		$checks['file'] = $reported_file && $reported_file === $real_file;
		$reported_markers = (array) ( $reported['markers'] ?? array() );
		$real_markers = (array) ( $real['markers'] ?? array() );
		$shared = array_values( array_intersect( $reported_markers, $real_markers ) );
		$checks['markers'] = count( $shared ) >= max( 1, min( 2, count( $reported_markers ) ) );
		$positive = count( array_filter( $checks ) );
		if ( $positive >= 3 || ( $checks['class'] && $checks['file'] ) ) {
			$verdict = 'coincide';
		} elseif ( $positive >= 1 || $shared ) {
			$verdict = 'coincide_parcialmente';
		} elseif ( empty( $real['message'] ) ) {
			$verdict = 'no_reproducible';
		} else {
			$verdict = 'no_coincide';
		}
		$causes = self::probable_causes( $ticket, $reported, $real, $actual );
		$rules = array_map(
			static function ( $cause ) {
				return array( 'id' => $cause['rule'], 'title' => $cause['summary'], 'reason' => $cause['summary'] );
			},
			$causes
		);
		$primary = $causes[0];
		$differences = array();
		foreach ( $checks as $field => $ok ) {
			if ( ! $ok ) {
				$differences[] = array( 'field' => $field, 'expected' => 'coincidir', 'actual' => 'diferente o ausente' );
			}
		}
		$recommendations = self::recommendations_for_causes( $causes );
		$issue_reproduced = in_array( $verdict, array( 'coincide', 'coincide_parcialmente' ), true );
		$outcome = 'no_reproducida';
		if ( 'coincide' === $verdict ) {
			$outcome = 'fallo_reproducido';
		} elseif ( 'coincide_parcialmente' === $verdict ) {
			$outcome = 'fallo_parcial';
		} elseif ( 'no_coincide' === $verdict ) {
			$outcome = 'fallo_diferente';
		}
		return array(
			'verdict' => $verdict,
			'outcome' => $outcome,
			'issue_reproduced' => $issue_reproduced,
			'execution_ok' => ! empty( $actual['execution_ok'] ),
			'score' => round( $positive / count( $checks ), 2 ),
			'checks' => $checks,
			'shared_markers' => $shared,
			'markers_present' => $shared,
			'missing_markers' => array_values( array_diff( $reported_markers, $real_markers ) ),
			'markers_missing' => array_values( array_diff( $reported_markers, $real_markers ) ),
			'reported' => $reported,
			'reproduced' => $real,
			'probable_causes' => $causes,
			'probable_cause' => array(
				'id' => $primary['rule'],
				'title' => $primary['summary'],
				'explanation' => $primary['summary'],
				'explanation_simple' => $primary['summary'],
				'confidence' => $verdict === 'coincide' ? 'alta' : 'media',
			),
			'rules_matched' => $rules,
			'differences' => $differences,
			'recommendations' => $recommendations,
			'verdict_explanation' => 'Se compararon clase, mensaje, archivo y marcadores técnicos del reporte con la ejecución real.',
			'summary_client' => $issue_reproduced
				? 'La prueba del laboratorio terminó y reprodujo total o parcialmente el problema informado. Esto es un resultado negativo para el sitio, pero confirma el reporte del cliente.'
				: 'La prueba del laboratorio terminó sin reproducir el mismo problema informado. Esto no descarta un fallo intermitente; indica que no apareció con la pila y los pasos probados.',
			'stack_actual' => $actual['stack'] ?? array(),
			'compared_at' => gmdate( 'c' ),
			'rules_version' => self::RULES_VERSION,
		);
	}

	private static function probable_causes( $ticket, $reported, $real, $actual ) {
		$all = strtolower( wp_json_encode( array( $reported, $real, $actual ) ) );
		$causes = array();
		if ( false !== strpos( $all, 'class_not_found' ) || false !== strpos( $all, 'not found' ) ) {
			$causes[] = array( 'rule' => 'missing_dependency', 'summary' => 'Una clase o dependencia requerida no estaba disponible al cargar el plugin.' );
		}
		if ( ! empty( $ticket['entorno']['actualizacion_en_curso'] ) ) {
			$causes[] = array( 'rule' => 'partial_update', 'summary' => 'La evidencia coincide con una actualización incompleta o con archivos de versiones mezcladas.' );
		}
		if ( false !== strpos( $all, 'plugins_loaded' ) ) {
			$causes[] = array( 'rule' => 'early_load', 'summary' => 'Un plugin se inicia antes de que WooCommerce termine de registrar sus clases.' );
		}
		if ( false !== strpos( $all, 'checkout') && ( false !== strpos( $all, 'block') || 'bloques' === ( $ticket['entorno']['checkout'] ?? '' ) ) ) {
			$causes[] = array( 'rule' => 'checkout_blocks', 'summary' => 'El checkout usa bloques y el JavaScript del plugin espera los campos del checkout clásico.' );
		}
		if ( false !== strpos( $all, '401' ) || false !== strpos( $all, '403' ) || false !== strpos( $all, 'unauthorized' ) || false !== strpos( $all, 'subscription key' ) ) {
			$causes[] = array( 'rule' => 'api_credentials', 'summary' => 'Chilexpress rechazó la solicitud por credenciales, ambiente o permisos de API.' );
		}
		if ( false !== strpos( $all, 'timeout' ) || false !== strpos( $all, 'timed out' ) || false !== strpos( $all, 'could not resolve host' ) || false !== strpos( $all, 'curl error 6' ) ) {
			$causes[] = array( 'rule' => 'network_failure', 'summary' => 'La tienda no pudo completar la conexión con el servicio externo de Chilexpress.' );
		}
		if ( false !== strpos( $all, 'comuna' ) || false !== strpos( $all, 'coverage') || false !== strpos( $all, 'countycode' ) ) {
			$causes[] = array( 'rule' => 'coverage_data', 'summary' => 'La región/comuna o su código no coincide con el catálogo de cobertura esperado por Chilexpress.' );
		}
		if ( false !== strpos( $all, 'cannot redeclare' ) || false !== strpos( $all, 'plugin conflict' ) ) {
			$causes[] = array( 'rule' => 'plugin_conflict', 'summary' => 'Dos plugins o versiones incompatibles están cargando código que entra en conflicto.' );
		}
		$requested_php = (string) ( $ticket['pila']['php'] ?? '' );
		if ( $requested_php && $requested_php !== (string) ( $actual['stack']['php'] ?? '' ) ) {
			$causes[] = array( 'rule' => 'php_mismatch', 'summary' => 'La versión de PHP probada no coincide con la informada por el cliente.' );
		}
		if ( (int) ( $actual['http_status'] ?? 0 ) >= 500 ) {
			$causes[] = array( 'rule' => 'server_error', 'summary' => 'El servidor produjo una respuesta 5xx durante el paso que falla.' );
		}
		if ( ! $causes ) {
			$causes[] = array( 'rule' => 'insufficient_evidence', 'summary' => 'La evidencia disponible no permite atribuir una causa única; revisar diferencias de pila y el log técnico.' );
		}
		return $causes;
	}

	private static function recommendations_for_causes( $causes ) {
		$map = array(
			'missing_dependency' => 'Reinstalar la versión exacta del plugin afectado y verificar que el paquete esté completo.',
			'partial_update' => 'Desactivar temporalmente el plugin dependiente, completar la actualización y reactivarlo después de validar el sitio.',
			'early_load' => 'El fabricante debe mover la inicialización al hook de la dependencia cargada y comprobar que las clases existan.',
			'checkout_blocks' => 'Probar con checkout clásico o adaptar el módulo Chilexpress a WooCommerce Blocks / Checkout Extensibility.',
			'api_credentials' => 'Verificar que las tres API keys correspondan al mismo ambiente, estén activas y tengan acceso al producto solicitado.',
			'network_failure' => 'Revisar DNS, firewall, TLS y conectividad saliente del hosting hacia los endpoints Chilexpress; repetir con timeout controlado.',
			'coverage_data' => 'Enviar nombre y código oficiales de región/comuna (por ejemplo LA REINA / LARE), refrescar cobertura y repetir la cotización.',
			'plugin_conflict' => 'Repetir con solo WooCommerce y Chilexpress activos; reactivar los demás plugins uno por uno hasta identificar el conflicto.',
			'php_mismatch' => 'Repetir la prueba con la misma versión de PHP informada por el cliente.',
			'server_error' => 'Revisar el stack trace y las últimas líneas de debug.log antes de repetir la operación.',
			'insufficient_evidence' => 'Solicitar el correo de error crítico, debug.log y pasos exactos antes de concluir la causa.',
		);
		$out = array();
		foreach ( $causes as $cause ) {
			$rule = (string) ( $cause['rule'] ?? '' );
			if ( isset( $map[ $rule ] ) ) {
				$out[] = $map[ $rule ];
			}
		}
		return array_values( array_unique( $out ) );
	}

	private static function similar_text_score( $a, $b ) {
		$a = strtolower( preg_replace( '/\\s+/', ' ', trim( $a ) ) );
		$b = strtolower( preg_replace( '/\\s+/', ' ', trim( $b ) ) );
		if ( '' === $a || '' === $b ) {
			return 0;
		}
		similar_text( substr( $a, 0, 1000 ), substr( $b, 0, 1000 ), $percent );
		return $percent / 100;
	}

	public static function ajax_restore() {
		self::guard();
		@set_time_limit( 900 );
		$id = sanitize_file_name( wp_unslash( $_POST['id'] ?? '' ) );
		$run_id = sanitize_file_name( wp_unslash( $_POST['run_id'] ?? '' ) );
		$restored = self::restore_snapshot( self::run_dir( $id, $run_id ) );
		if ( is_wp_error( $restored ) ) {
			wp_send_json_error( $restored->get_error_message() );
		}
		wp_send_json_success( array( 'text' => 'Snapshot restaurado. Recarga WordPress.', 'reload' => true ) );
	}

	public static function create_snapshot( $run_dir ) {
		$snapshot = $run_dir . '/snapshot';
		if ( is_dir( $snapshot ) ) {
			return self::read_json( $snapshot . '/manifest.json' );
		}
		if ( ! wp_mkdir_p( $snapshot ) ) {
			return new WP_Error( 'cxp_snapshot', 'No se pudo crear el snapshot' );
		}
		$manifest = array(
			'created_at' => gmdate( 'c' ),
			'stack' => self::current_stack(),
			'active_plugins' => (array) get_option( 'active_plugins', array() ),
			'stylesheet' => get_stylesheet(),
			'template' => get_template(),
			'files' => array(),
		);
		$targets = array(
			'core' => array( ABSPATH, $snapshot . '/core', array( 'wp-content', 'wp-config.php' ) ),
			'plugins' => array( WP_PLUGIN_DIR, $snapshot . '/plugins', array() ),
			'themes' => array( get_theme_root(), $snapshot . '/themes', array() ),
		);
		foreach ( $targets as $name => $args ) {
			$copied = self::copy_tree( $args[0], $args[1], $args[2] );
			if ( is_wp_error( $copied ) ) {
				return $copied;
			}
			$manifest['files'][] = $name;
		}
		if ( is_readable( ABSPATH . 'wp-config.php' ) ) {
			copy( ABSPATH . 'wp-config.php', $snapshot . '/wp-config.php' );
			$manifest['files'][] = 'wp-config.php';
		}
		$db_dir = defined( 'DB_DIR' ) ? DB_DIR : WP_CONTENT_DIR . '/database/';
		$db_file = defined( 'DB_FILE' ) ? DB_FILE : '.ht.sqlite';
		global $wpdb;
		if ( isset( $wpdb ) && defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) {
			$wpdb->query( 'PRAGMA wal_checkpoint(FULL)' );
		}
		foreach ( array( $db_file, $db_file . '-wal', $db_file . '-shm' ) as $file ) {
			if ( is_readable( trailingslashit( $db_dir ) . $file ) ) {
				wp_mkdir_p( $snapshot . '/database' );
				copy( trailingslashit( $db_dir ) . $file, $snapshot . '/database/' . $file );
			}
		}
		self::write_json( $snapshot . '/manifest.json', $manifest );
		self::write_json( $run_dir . '/stack-before.json', $manifest['stack'] );
		return $manifest;
	}

	public static function restore_snapshot( $run_dir ) {
		$snapshot = $run_dir . '/snapshot';
		$manifest = self::read_json( $snapshot . '/manifest.json' );
		if ( ! $manifest ) {
			return new WP_Error( 'cxp_snapshot', 'No existe el snapshot del run' );
		}
		$pairs = array(
			array( $snapshot . '/core', ABSPATH, array() ),
			array( $snapshot . '/plugins', WP_PLUGIN_DIR, array() ),
			array( $snapshot . '/themes', get_theme_root(), array() ),
		);
		foreach ( $pairs as $index => $pair ) {
			if ( 0 === $index ) {
				self::remove_core_files();
			} elseif ( is_dir( $pair[1] ) ) {
				self::remove_tree( $pair[1] );
			}
			$copy = self::copy_tree( $pair[0], $pair[1], $pair[2] );
			if ( is_wp_error( $copy ) ) {
				return $copy;
			}
		}
		if ( is_readable( $snapshot . '/wp-config.php' ) ) {
			copy( $snapshot . '/wp-config.php', ABSPATH . 'wp-config.php' );
		}
		$db_dir = defined( 'DB_DIR' ) ? DB_DIR : WP_CONTENT_DIR . '/database/';
		foreach ( glob( $snapshot . '/database/*' ) ?: array() as $file ) {
			copy( $file, trailingslashit( $db_dir ) . basename( $file ) );
		}
		$run = self::read_json( $run_dir . '/run.json' );
		if ( $run ) {
			self::write_run( $run_dir, $run, 'Snapshot restaurado', 'restaurada' );
		}
		self::clear_runtime_caches();
		return true;
	}

	private static function copy_tree( $src, $dest, $skip = array() ) {
		if ( ! is_dir( $src ) ) {
			return new WP_Error( 'cxp_snapshot', 'No existe origen de snapshot: ' . $src );
		}
		if ( ! is_dir( $dest ) && ! wp_mkdir_p( $dest ) ) {
			return new WP_Error( 'cxp_snapshot', 'No se pudo crear: ' . $dest );
		}
		$items = scandir( $src );
		if ( false === $items ) {
			return new WP_Error( 'cxp_snapshot', 'No se pudo leer: ' . $src );
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item || in_array( $item, $skip, true ) ) {
				continue;
			}
			$from = rtrim( $src, '/\\' ) . DIRECTORY_SEPARATOR . $item;
			$to = rtrim( $dest, '/\\' ) . DIRECTORY_SEPARATOR . $item;
			if ( is_link( $from ) ) {
				continue;
			}
			if ( is_dir( $from ) ) {
				$done = self::copy_tree( $from, $to );
				if ( is_wp_error( $done ) ) {
					return $done;
				}
			} elseif ( ! @copy( $from, $to ) ) {
				return new WP_Error( 'cxp_snapshot', 'No se pudo copiar ' . $from );
			}
		}
		return true;
	}

	private static function remove_tree( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) ?: array() as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			is_dir( $path ) && ! is_link( $path ) ? self::remove_tree( $path ) : @unlink( $path );
		}
		@rmdir( $dir );
	}

	private static function remove_core_files() {
		foreach ( scandir( ABSPATH ) ?: array() as $item ) {
			if ( in_array( $item, array( '.', '..', 'wp-content', 'wp-config.php' ), true ) ) {
				continue;
			}
			$path = ABSPATH . $item;
			is_dir( $path ) && ! is_link( $path ) ? self::remove_tree( $path ) : @unlink( $path );
		}
	}

	private static function health_checks() {
		$out = array();
		foreach ( array( 'front' => home_url( '/' ), 'admin' => admin_url( '/' ), 'ajax' => admin_url( 'admin-ajax.php' ) ) as $name => $url ) {
			$res = wp_remote_get( $url, array( 'timeout' => 20, 'redirection' => 2 ) );
			$out[ $name ] = is_wp_error( $res )
				? array( 'ok' => false, 'error' => $res->get_error_message() )
				: array( 'ok' => wp_remote_retrieve_response_code( $res ) < 500, 'status' => wp_remote_retrieve_response_code( $res ) );
		}
		return $out;
	}

	private static function clear_runtime_caches() {
		global $wpdb;
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients();
		}
		if ( isset( $wpdb ) ) {
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'" );
		}
		if ( function_exists( 'opcache_reset' ) ) {
			@opcache_reset();
		}
	}

	private static function safe_url( $url ) {
		$url = trim( $url );
		if ( 0 === strpos( $url, '/' ) ) {
			$url = home_url( $url );
		}
		$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host || strtolower( $host ) !== strtolower( $home_host ) ) {
			return new WP_Error( 'cxp_incident_url', 'El flujo solo puede llamar al mismo sitio del laboratorio' );
		}
		return esc_url_raw( $url );
	}

	private static function log_delta( $file, $offset ) {
		if ( ! is_readable( $file ) ) {
			return '';
		}
		$size = filesize( $file );
		if ( false === $size || $size <= $offset ) {
			return '';
		}
		$fh = fopen( $file, 'rb' );
		if ( ! $fh ) {
			return '';
		}
		fseek( $fh, $offset );
		$data = stream_get_contents( $fh, 200000 );
		fclose( $fh );
		return (string) $data;
	}

	public static function ajax_result() {
		self::guard();
		$id = sanitize_file_name( wp_unslash( $_POST['id'] ?? '' ) );
		$run_id = sanitize_file_name( wp_unslash( $_POST['run_id'] ?? '' ) );
		$dir = self::run_dir( $id, $run_id );
		wp_send_json_success( array(
			'run' => self::read_json( $dir . '/run.json' ),
			'steps' => self::read_json( $dir . '/steps.json' ),
			'result' => self::read_json( $dir . '/result.json' ),
			'comparison' => self::read_json( $dir . '/comparison.json' ),
		) );
	}

	public static function ajax_pdf() {
		self::guard();
		$id = sanitize_file_name( wp_unslash( $_REQUEST['id'] ?? '' ) );
		$run_id = sanitize_file_name( wp_unslash( $_REQUEST['run_id'] ?? '' ) );
		$type = 'technical' === ( $_REQUEST['type'] ?? '' ) ? 'technical' : 'client';
		$ticket = self::load_ticket( $id );
		$dir = self::run_dir( $id, $run_id );
		if ( is_wp_error( $ticket ) || ! class_exists( 'Cxp_Incident_Pdf' ) ) {
			wp_die( 'No se puede generar el PDF.' );
		}
		$run = self::read_json( $dir . '/run.json' );
		$reported_evidence = self::read_json( $dir . '/reported-evidence.json' );
		if ( $reported_evidence ) {
			$ticket['evidencia'] = $reported_evidence;
		}
		$result = self::read_json( $dir . '/result.json' );
		$comparison = self::read_json( $dir . '/comparison.json' );
		$steps = self::read_json( $dir . '/steps.json' );
		$actual = array_merge(
			(array) ( $result['signature'] ?? array() ),
			array(
				'url' => (string) ( $result['url'] ?? '' ),
				'http_status' => $result['http_status'] ?? '',
				'message' => (string) ( $result['signature']['message'] ?? '' ),
			)
		);
		$report_run = array_merge(
			$run,
			array(
				'steps' => $steps,
				'result' => $actual,
				'actual' => $actual,
				'steps_ok' => $result['steps_ok'] ?? null,
				'steps_total' => $result['steps_total'] ?? null,
				'steps_failed' => $result['steps_failed'] ?? null,
				'execution_ok' => $result['execution_ok'] ?? null,
				'health' => self::read_json( $dir . '/health.json' ),
				'apply_log' => self::read_json( $dir . '/apply-log.json' ),
				'stack_actual' => (array) ( $result['stack'] ?? self::read_json( $dir . '/stack-after.json' ) ),
				'snapshot_id' => $run_id,
				'restored' => 'restaurada' === ( $run['state'] ?? '' ),
				'evidence' => array(
					'http' => array(
						'status' => $result['http_status'] ?? '',
						'url' => $result['url'] ?? '',
						'body_excerpt' => $result['body_excerpt'] ?? '',
					),
					'php' => $result['log_excerpt'] ?? '',
					'js' => $result['browser_events'] ?? array(),
					'debug_log' => $result['log_excerpt'] ?? '',
				),
			)
		);
		if ( 'technical' === $type ) {
			Cxp_Incident_Pdf::download_technical( $ticket, $report_run, $comparison );
		} else {
			Cxp_Incident_Pdf::download_client( $ticket, $report_run, $comparison );
		}
		exit;
	}

	private static function run_dir( $id, $run_id ) {
		return self::runs_dir() . '/' . sanitize_file_name( $id ) . '/' . sanitize_file_name( $run_id );
	}

	private static function new_run_id() {
		return gmdate( 'Ymd-His' ) . '-' . strtolower( wp_generate_password( 5, false, false ) );
	}

	private static function write_run( $dir, &$run, $message, $state = '' ) {
		if ( $state ) {
			$run['state'] = $state;
		}
		$run['updated_at'] = gmdate( 'c' );
		$run['events'][] = array( 'at' => gmdate( 'c' ), 'state' => $run['state'], 'message' => $message );
		self::write_json( $dir . '/run.json', $run );
	}

	private static function read_json( $file ) {
		if ( ! is_readable( $file ) ) {
			return array();
		}
		$data = json_decode( (string) file_get_contents( $file ), true );
		return is_array( $data ) ? $data : array();
	}

	private static function write_json( $file, $data ) {
		$dir = dirname( $file );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return false !== file_put_contents( $file, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
	}

	private static function acquire_lock( $ticket_id ) {
		$lock = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $lock ) && ! empty( $lock['at'] ) && time() - (int) $lock['at'] < 900 ) {
			return new WP_Error( 'cxp_incident_lock', 'Ya hay una ejecución activa para ' . ( $lock['ticket_id'] ?? 'otra incidencia' ) );
		}
		update_option( self::LOCK_OPTION, array( 'ticket_id' => $ticket_id, 'at' => time() ), false );
		return true;
	}

	private static function release_lock() {
		delete_option( self::LOCK_OPTION );
	}

	private static function guard() {
		if ( ! current_user_can( 'manage_options' ) && ! ( function_exists( 'cxp_auto_login_enabled' ) && cxp_auto_login_enabled() ) ) {
			wp_send_json_error( 'No autorizado', 403 );
		}
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_send_json_error( 'Nonce inválido', 403 );
		}
	}
}

Cxp_Incident_Runner::boot();
