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
		add_action( 'cxp_debug_console_panels', array( __CLASS__, 'console_panel' ), 8 );
		add_action( 'wp_ajax_cxp_stack_switch', array( __CLASS__, 'ajax_switch' ) );
		add_action( 'wp_ajax_nopriv_cxp_stack_switch', array( __CLASS__, 'ajax_switch' ) );
		add_action( 'wp_ajax_cxp_stack_restore_client', array( __CLASS__, 'ajax_restore_client' ) );
		add_action( 'wp_ajax_nopriv_cxp_stack_restore_client', array( __CLASS__, 'ajax_restore_client' ) );
	}

	public static function console_panel() {
		$ajax    = admin_url( 'admin-ajax.php' );
		$nonce   = wp_create_nonce( self::NONCE );
		$php     = PHP_VERSION;
		$wp      = get_bloginfo( 'version' );
		$wc      = defined( 'WC_VERSION' ) ? WC_VERSION : self::plugin_version( 'woocommerce/woocommerce.php' );
		$cxp     = defined( 'CHILEXPRESS_WOO_OFICIAL_VERSION' ) ? CHILEXPRESS_WOO_OFICIAL_VERSION : self::plugin_version( 'chilexpress-oficial/chilexpress-woo-oficial.php' );
		$theme   = wp_get_theme();
		$parent  = $theme->parent();
		$ok_php  = 0 === strpos( $php, '8.4.19' ) || 0 === strpos( $php, '8.4' );
		$match   = ( self::CLIENT_WP === $wp && self::CLIENT_WC === $wc && self::CLIENT_CXP === $cxp );
		$wp_opts = array( '6.8.3', '7.0.3', '7.1' );
		$wc_opts = array( '9.9.5', '10.4.3', '11.0.1' );
		if ( ! in_array( $wp, $wp_opts, true ) ) {
			array_unshift( $wp_opts, $wp );
		}
		if ( $wc && ! in_array( $wc, $wc_opts, true ) ) {
			array_unshift( $wc_opts, $wc );
		}
		?>
		<style>
			#cxp-dbg-stack{margin:0 0 12px;padding:10px 12px;border:1px solid #365314;border-radius:8px;background:#14532d22}
			#cxp-dbg-stack p{margin:0 0 8px;color:#bbf7d0}
			#cxp-dbg-stack p strong{color:#fff200}
			#cxp-dbg-stack .cxp-lab-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:8px 0}
			#cxp-dbg-stack select{color:#e8edf5;background:#1e293b;border:1px solid #3d4d66;border-radius:6px;padding:4px 8px}
			#cxp-dbg-stack .ok{color:#86efac}
			#cxp-dbg-stack .bad{color:#fca5a5}
			#cxp-dbg-stack pre{margin:8px 0 0;max-height:200px;overflow:auto;white-space:pre-wrap;color:#d9f99d;background:#052e16;padding:8px;border-radius:6px}
			#cxp-dbg-stack button.cxp-stack-default{border-color:#ca8a04;background:#854d0e;color:#fef08a;font-weight:700}
		</style>
		<div id="cxp-dbg-stack">
			<p><strong>Pila / versiones (simular deploy)</strong>
				PHP <?php echo esc_html( $php ); ?>
				<span class="<?php echo $ok_php ? 'ok' : 'bad'; ?>"><?php echo $ok_php ? 'ok 8.4.x' : 'el cliente pidió 8.4.19 — cámbialo en Docker (.env) o runtime/'; ?></span>
				· WP <?php echo esc_html( $wp ); ?>
				· Woo <?php echo esc_html( $wc ); ?>
				· Chilexpress <?php echo esc_html( $cxp ); ?>
				· <?php echo esc_html( $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) ); ?>
				<?php echo $parent ? ' / ' . esc_html( $parent->get( 'Name' ) . ' ' . $parent->get( 'Version' ) ) : ''; ?>
			</p>
			<p><?php echo $match ? '<span class="ok">Esta réplica coincide con la pila del cliente (WP 7.0.3 · Woo 11.0.1 · Chilexpress 1.4.0).</span>' : '<span class="bad">No coincide con la pila del cliente. Usa el botón default.</span>'; ?>
			Cambiar WP/Woo descarga el ZIP de wordpress.org, pisa archivos y recarga. PHP no se cambia desde aquí.</p>
			<div class="cxp-lab-row">
				<label>WordPress
					<select id="cxp-stack-wp">
						<?php foreach ( $wp_opts as $v ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $v, $wp ); ?>><?php echo esc_html( $v ); ?><?php echo self::CLIENT_WP === $v ? ' (cliente)' : ''; ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<button type="button" class="cxp-dbg-copy-one" id="cxp-stack-wp-go">Instalar esta WP</button>
				<label>WooCommerce
					<select id="cxp-stack-wc">
						<?php foreach ( $wc_opts as $v ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $v, $wc ); ?>><?php echo esc_html( $v ); ?><?php echo self::CLIENT_WC === $v ? ' (cliente)' : ''; ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<button type="button" class="cxp-dbg-copy-one" id="cxp-stack-wc-go">Instalar este Woo</button>
				<button type="button" class="cxp-dbg-copy-one" id="cxp-stack-cxp-go">Restaurar Chilexpress 1.4.0 intacto</button>
				<button type="button" class="cxp-stack-default" id="cxp-stack-default">Volver a versiones del cliente (default)</button>
			</div>
			<pre id="cxp-stack-out">Default = WordPress <?php echo esc_html( self::CLIENT_WP ); ?> + WooCommerce <?php echo esc_html( self::CLIENT_WC ); ?> + Chilexpress <?php echo esc_html( self::CLIENT_CXP ); ?> + tema Woodmart Child 1.0.0. PHP <?php echo esc_html( self::CLIENT_PHP ); ?> se fija en Docker o en runtime/php-8.4.19.</pre>
		</div>
		<script>
		(function () {
			var ajaxUrl = <?php echo wp_json_encode( $ajax ); ?>;
			var nonce = <?php echo wp_json_encode( $nonce ); ?>;
			var out = document.getElementById('cxp-stack-out');
			function post(action, extra, confirmMsg) {
				if (confirmMsg && !confirm(confirmMsg)) return;
				out.textContent = 'Trabajando… puede tardar un minuto (descarga ZIP). La página se recargará.';
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
					if (data && data.success) {
						setTimeout(function () { window.location.reload(); }, 800);
					}
				}).catch(function (e) { out.textContent = 'Falló: ' + e; });
			}
			var wpGo = document.getElementById('cxp-stack-wp-go');
			if (wpGo) wpGo.addEventListener('click', function (e) {
				e.stopPropagation();
				var v = document.getElementById('cxp-stack-wp').value;
				post('cxp_stack_switch', { target: 'wordpress', version: v }, '¿Instalar WordPress ' + v + '? Pisa el core (no wp-content).');
			});
			var wcGo = document.getElementById('cxp-stack-wc-go');
			if (wcGo) wcGo.addEventListener('click', function (e) {
				e.stopPropagation();
				var v = document.getElementById('cxp-stack-wc').value;
				post('cxp_stack_switch', { target: 'woocommerce', version: v }, '¿Instalar WooCommerce ' + v + '?');
			});
			var cxpGo = document.getElementById('cxp-stack-cxp-go');
			if (cxpGo) cxpGo.addEventListener('click', function (e) {
				e.stopPropagation();
				post('cxp_stack_switch', { target: 'chilexpress', version: '1.4.0' }, '¿Restaurar Chilexpress Oficial 1.4.0 desde la copia intacta del repo?');
			});
			var def = document.getElementById('cxp-stack-default');
			if (def) def.addEventListener('click', function (e) {
				e.stopPropagation();
				post('cxp_stack_restore_client', {}, '¿Volver a WP 7.0.3 + Woo 11.0.1 + Chilexpress 1.4.0 + Woodmart Child? PHP no cambia.');
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

	private static function switch_component( $target, $version ) {
		if ( 'wordpress' === $target ) {
			return self::install_wordpress( $version );
		}
		if ( 'woocommerce' === $target ) {
			return self::install_plugin_zip(
				'woocommerce',
				'https://downloads.wordpress.org/plugin/woocommerce.' . rawurlencode( $version ) . '.zip',
				$version
			);
		}
		if ( 'chilexpress' === $target ) {
			return self::restore_chilexpress();
		}
		return new WP_Error( 'cxp_stack', 'Objetivo desconocido' );
	}

	private static function install_wordpress( $version ) {
		$version = preg_replace( '/[^0-9.]/', '', $version );
		if ( $version === get_bloginfo( 'version' ) ) {
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

	private static function install_plugin_zip( $slug, $url, $version ) {
		$zip = self::download_zip( $url, $slug . '-' . $version . '.zip' );
		if ( is_wp_error( $zip ) ) {
			return $zip;
		}
		self::load_fs();
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$dest = WP_PLUGIN_DIR . '/' . $slug;
		if ( is_dir( $dest ) && class_exists( 'Cxp_Plugin_Lab' ) && 'woocommerce' === $slug ) {
			// Snapshot best-effort; ignore errors.
		}
		$tmp = self::tmp_dir( $slug );
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
		$file = 'woocommerce' === $slug ? 'woocommerce/woocommerce.php' : $slug . '/' . $slug . '.php';
		if ( file_exists( WP_PLUGIN_DIR . '/' . $file ) ) {
			activate_plugin( $file, '', false, true );
		}
		return $slug . ' ' . $version . ' instalado y activado.';
	}

	private static function restore_chilexpress() {
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
		if ( is_dir( $dest ) ) {
			self::rmdir( $dest );
		}
		if ( ! copy_dir( $src, $dest ) ) {
			return new WP_Error( 'cxp_stack', 'No se pudo copiar Chilexpress 1.4.0' );
		}
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		activate_plugin( 'chilexpress-oficial/chilexpress-woo-oficial.php', '', false, true );
		return 'Chilexpress Oficial 1.4.0 restaurado desde la copia intacta del repo.';
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
		WP_Filesystem();
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
