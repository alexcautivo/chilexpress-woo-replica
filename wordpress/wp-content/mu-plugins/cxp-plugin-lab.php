<?php
/**
 * Plugin Name: Laboratorio de plugins (ZIP + snapshots)
 * Version: 1.0.0
 * Description: Instala ZIPs, anota versión, snapshot y rollback de Chilexpress/WooCommerce. No modifica chilexpress-oficial.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cxp_Plugin_Lab {
	const NONCE    = 'cxp_plugin_lab';
	const BACKUP   = 'cxp_lab_active_plugins_backup';
	const SAFE_OPT = 'cxp_lab_safe_mode';
	const SLUGS    = array( 'chilexpress-oficial', 'woocommerce' );

	public static function boot() {
		add_action( 'cxp_debug_console_panels', array( __CLASS__, 'console_panel' ) );
		add_action( 'admin_post_cxp_lab_upload', array( __CLASS__, 'handle_upload' ) );
		add_action( 'wp_ajax_cxp_lab_snapshot', array( __CLASS__, 'ajax_snapshot' ) );
		add_action( 'wp_ajax_cxp_lab_restore', array( __CLASS__, 'ajax_restore' ) );
		add_action( 'wp_ajax_cxp_lab_install_drop', array( __CLASS__, 'ajax_install_drop' ) );
		add_action( 'wp_ajax_cxp_lab_toggle', array( __CLASS__, 'ajax_toggle' ) );
		add_action( 'wp_ajax_cxp_lab_safe_mode', array( __CLASS__, 'ajax_safe_mode' ) );
		add_action( 'admin_notices', array( __CLASS__, 'safe_mode_notice' ) );
	}

	public static function console_panel() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$tracked   = self::tracked_plugins();
		$drops     = self::drop_zips();
		$snaps     = self::list_snapshots();
		$safe      = (bool) get_option( self::SAFE_OPT, false );
		$ajax      = admin_url( 'admin-ajax.php' );
		$upload    = admin_url( 'admin-post.php' );
		$nonce     = wp_create_nonce( self::NONCE );
		$max_upload = size_format( wp_max_upload_size() );
		?>
		<style>
			#cxp-dbg-lab{margin:0 0 12px;padding:10px 12px;border:1px solid #3d4d66;border-radius:8px;background:#111827}
			#cxp-dbg-lab p{margin:0 0 8px;color:#9fb0c7}
			#cxp-dbg-lab p strong{color:#fff200}
			#cxp-dbg-lab table{width:100%;border-collapse:collapse;margin:0 0 10px}
			#cxp-dbg-lab th,#cxp-dbg-lab td{padding:6px 8px;border-bottom:1px solid #243044;text-align:left}
			#cxp-dbg-lab th{color:#fff200;font-size:11px;letter-spacing:.06em;text-transform:uppercase}
			#cxp-dbg-lab .cxp-ver{color:#86efac;font-weight:700}
			#cxp-dbg-lab .is-off{color:#fca5a5}
			#cxp-dbg-lab .cxp-lab-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 10px}
		#cxp-dbg-lab input,#cxp-dbg-lab select,#cxp-dbg-lab option{color:#e8edf5 !important;-webkit-text-fill-color:#e8edf5 !important;background:#1e293b !important;background-color:#1e293b !important;border:1px solid #64748b !important;border-radius:6px;padding:4px 8px;color-scheme:dark}
			#cxp-dbg-lab .is-safe{color:#fde68a}
		</style>
		<div id="cxp-dbg-lab">
			<p><strong>Laboratorio de plugins</strong> — ZIP con versión, snapshot antes de cambiar, rollback. Chilexpress Oficial no se parchea desde aquí.</p>
			<?php if ( $safe ) : ?>
				<p class="is-safe">Modo seguro ON: solo WooCommerce + Chilexpress + SQLite.</p>
			<?php endif; ?>
			<table>
				<thead>
					<tr>
						<th>Plugin</th>
						<th>Versión</th>
						<th>Estado</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $tracked as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['name'] ); ?></td>
							<td class="cxp-ver"><?php echo esc_html( $row['version'] ); ?></td>
							<td class="<?php echo $row['active'] ? '' : 'is-off'; ?>"><?php echo $row['active'] ? 'activo' : 'inactivo'; ?></td>
							<td>
								<button type="button" class="cxp-dbg-copy-one cxp-lab-snap" data-slug="<?php echo esc_attr( $row['slug'] ); ?>">Snapshot</button>
								<button type="button" class="cxp-dbg-copy-one cxp-lab-toggle" data-file="<?php echo esc_attr( $row['file'] ); ?>" data-on="<?php echo $row['active'] ? '1' : '0'; ?>">
									<?php echo $row['active'] ? 'Desactivar' : 'Activar'; ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<form class="cxp-lab-row" method="post" action="<?php echo esc_url( $upload ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="cxp_lab_upload">
				<?php wp_nonce_field( self::NONCE ); ?>
				<input type="file" name="plugin_zip" accept=".zip" required>
				<label><input type="checkbox" name="activate" value="1" checked> Activar</label>
				<label><input type="checkbox" name="snapshot_first" value="1" checked> Snapshot si pisa Chilexpress/WC</label>
				<button type="submit" class="cxp-dbg-copy-one">Subir e instalar ZIP</button>
				<span>máx. <?php echo esc_html( $max_upload ); ?></span>
			</form>
			<div class="cxp-lab-row">
				<select id="cxp-lab-drop">
					<option value="">ZIP en drop-plugins/</option>
					<?php foreach ( $drops as $zip ) : ?>
						<option value="<?php echo esc_attr( $zip ); ?>"><?php echo esc_html( $zip ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" id="cxp-lab-install-drop" class="cxp-dbg-copy-one">Instalar ZIP de drop-plugins</button>
				<button type="button" id="cxp-lab-safe" class="cxp-dbg-copy-one" data-on="<?php echo $safe ? '1' : '0'; ?>">
					<?php echo $safe ? 'Salir modo seguro' : 'Modo seguro (solo WC + Chilexpress)'; ?>
				</button>
			</div>
			<p><strong>Snapshots</strong> — <?php echo esc_html( (string) count( $snaps ) ); ?> copias en wp-content/cxp-snapshots/</p>
			<table>
				<thead>
					<tr>
						<th>Cuándo</th>
						<th>Slug</th>
						<th>Versión</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $snaps ) : ?>
						<tr><td colspan="4">Ninguno. Haz snapshot antes de instalar un ZIP.</td></tr>
					<?php else : ?>
						<?php foreach ( $snaps as $snap ) : ?>
							<tr>
								<td><?php echo esc_html( $snap['created'] ); ?></td>
								<td><?php echo esc_html( $snap['slug'] ); ?></td>
								<td class="cxp-ver"><?php echo esc_html( $snap['version'] ); ?></td>
								<td>
									<button type="button" class="cxp-dbg-copy-one cxp-lab-restore" data-id="<?php echo esc_attr( $snap['id'] ); ?>">Restaurar</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<script>
		(function () {
			var ajaxUrl = <?php echo wp_json_encode( $ajax ); ?>;
			var nonce = <?php echo wp_json_encode( $nonce ); ?>;
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
			function go(action, extra, confirmMsg) {
				if (confirmMsg && !confirm(confirmMsg)) return;
				post(action, extra).then(function (data) {
					if (data && data.success) {
						window.location.reload();
						return;
					}
					alert((data && data.data) ? data.data : 'Falló la operación');
				}).catch(function () { alert('Falló la operación'); });
			}
			document.querySelectorAll('.cxp-lab-snap').forEach(function (btn) {
				btn.addEventListener('click', function (e) {
					e.stopPropagation();
					go('cxp_lab_snapshot', { slug: btn.getAttribute('data-slug') });
				});
			});
			document.querySelectorAll('.cxp-lab-restore').forEach(function (btn) {
				btn.addEventListener('click', function (e) {
					e.stopPropagation();
					go('cxp_lab_restore', { id: btn.getAttribute('data-id') }, '¿Restaurar este snapshot? Pisa la carpeta actual del plugin.');
				});
			});
			document.querySelectorAll('.cxp-lab-toggle').forEach(function (btn) {
				btn.addEventListener('click', function (e) {
					e.stopPropagation();
					go('cxp_lab_toggle', { file: btn.getAttribute('data-file'), on: btn.getAttribute('data-on') });
				});
			});
			var dropBtn = document.getElementById('cxp-lab-install-drop');
			if (dropBtn) {
				dropBtn.addEventListener('click', function (e) {
					e.stopPropagation();
					var sel = document.getElementById('cxp-lab-drop');
					if (!sel || !sel.value) { alert('Elige un ZIP'); return; }
					go('cxp_lab_install_drop', { zip: sel.value, snapshot_first: '1', activate: '1' }, '¿Instalar ' + sel.value + '?');
				});
			}
			var safeBtn = document.getElementById('cxp-lab-safe');
			if (safeBtn) {
				safeBtn.addEventListener('click', function (e) {
					e.stopPropagation();
					var on = safeBtn.getAttribute('data-on') === '1' ? '0' : '1';
					go('cxp_lab_safe_mode', { on: on }, on === '1' ? '¿Activar modo seguro?' : '¿Restaurar plugins anteriores?');
				});
			}
		})();
		</script>
		<?php
	}

	public static function handle_upload() {
		self::ensure_admin();
		check_admin_referer( self::NONCE );
		if ( empty( $_FILES['plugin_zip']['tmp_name'] ) ) {
			wp_die( 'No se subió el ZIP' );
		}
		$file = $_FILES['plugin_zip'];
		$check = wp_check_filetype( $file['name'], array( 'zip' => 'application/zip' ) );
		if ( empty( $check['ext'] ) ) {
			wp_die( 'Solo ZIP' );
		}
		self::ensure_dirs();
		$dest = self::drop_dir() . '/' . sanitize_file_name( $file['name'] );
		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
			wp_die( 'No se pudo guardar el ZIP en drop-plugins' );
		}
		$result = self::install_zip(
			$dest,
			! empty( $_POST['activate'] ),
			! empty( $_POST['snapshot_first'] )
		);
		if ( is_wp_error( $result ) ) {
			wp_die( $result->get_error_message() );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	public static function ajax_snapshot() {
		self::ajax_guard();
		$slug = self::sanitize_slug( wp_unslash( $_POST['slug'] ?? '' ) );
		$result = self::make_snapshot( $slug );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		wp_send_json_success( $result );
	}

	public static function ajax_restore() {
		self::ajax_guard();
		$id = sanitize_file_name( wp_unslash( $_POST['id'] ?? '' ) );
		$result = self::restore_snapshot( $id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		wp_send_json_success( true );
	}

	public static function ajax_install_drop() {
		self::ajax_guard();
		$zip = sanitize_file_name( wp_unslash( $_POST['zip'] ?? '' ) );
		$path = self::drop_dir() . '/' . $zip;
		if ( ! is_readable( $path ) ) {
			wp_send_json_error( 'ZIP no encontrado' );
		}
		$result = self::install_zip(
			$path,
			! empty( $_POST['activate'] ),
			! empty( $_POST['snapshot_first'] )
		);
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		wp_send_json_success( $result );
	}

	public static function ajax_toggle() {
		self::ajax_guard();
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$file = plugin_basename( wp_unslash( $_POST['file'] ?? '' ) );
		$on   = '1' === (string) ( $_POST['on'] ?? '0' );
		if ( $on ) {
			deactivate_plugins( $file, true );
		} else {
			$err = activate_plugin( $file );
			if ( is_wp_error( $err ) ) {
				wp_send_json_error( $err->get_error_message() );
			}
		}
		wp_send_json_success( true );
	}

	public static function ajax_safe_mode() {
		self::ajax_guard();
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$on = '1' === (string) ( $_POST['on'] ?? '0' );
		if ( $on ) {
			if ( ! get_option( self::BACKUP ) ) {
				update_option( self::BACKUP, (array) get_option( 'active_plugins', array() ), false );
			}
			$keep = array();
			foreach ( array(
				'woocommerce/woocommerce.php',
				'chilexpress-oficial/chilexpress-woo-oficial.php',
				'sqlite-database-integration/load.php',
			) as $file ) {
				if ( file_exists( WP_PLUGIN_DIR . '/' . $file ) ) {
					$keep[] = $file;
				}
			}
			update_option( 'active_plugins', $keep, false );
			update_option( self::SAFE_OPT, 1, false );
		} else {
			$backup = get_option( self::BACKUP );
			if ( is_array( $backup ) ) {
				update_option( 'active_plugins', $backup, false );
			}
			delete_option( self::BACKUP );
			delete_option( self::SAFE_OPT );
		}
		wp_send_json_success( true );
	}

	public static function safe_mode_notice() {
		if ( ! get_option( self::SAFE_OPT ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>Laboratorio:</strong> modo seguro activo (WooCommerce + Chilexpress + SQLite). Salir desde la consola de réplica.</p></div>';
	}

	public static function install_zip( $zip_path, $activate = true, $snapshot_first = false ) {
		self::load_fs();
		$tmp = self::snapshots_dir() . '/tmp-' . wp_generate_password( 8, false );
		wp_mkdir_p( $tmp );
		$result = unzip_file( $zip_path, $tmp );
		if ( is_wp_error( $result ) ) {
			self::rmdir( $tmp );
			return $result;
		}
		$roots = self::plugin_roots_in( $tmp );
		if ( ! $roots ) {
			self::rmdir( $tmp );
			return new WP_Error( 'cxp_lab_zip', 'El ZIP no contiene un plugin PHP con encabezado.' );
		}
		foreach ( $roots as $root ) {
			$slug = basename( $root );
			$dest = WP_PLUGIN_DIR . '/' . $slug;
			if ( $snapshot_first && is_dir( $dest ) && in_array( $slug, self::SLUGS, true ) ) {
				$snap = self::make_snapshot( $slug );
				if ( is_wp_error( $snap ) ) {
					self::rmdir( $tmp );
					return $snap;
				}
			}
			if ( is_dir( $dest ) ) {
				self::rmdir( $dest );
			}
			if ( ! copy_dir( $root, $dest ) ) {
				self::rmdir( $tmp );
				return new WP_Error( 'cxp_lab_copy', 'No se pudo copiar ' . $slug );
			}
			if ( $activate ) {
				$file = self::plugin_file_for_slug( $slug );
				if ( $file ) {
					$err = activate_plugin( $file );
					if ( is_wp_error( $err ) ) {
						self::rmdir( $tmp );
						return $err;
					}
				}
			}
		}
		self::rmdir( $tmp );
		return true;
	}

	private static function make_snapshot( $slug ) {
		$slug = self::sanitize_slug( $slug );
		if ( ! in_array( $slug, self::SLUGS, true ) ) {
			return new WP_Error( 'cxp_lab_slug', 'Solo se snapshot-ean chilexpress-oficial y woocommerce' );
		}
		$src = WP_PLUGIN_DIR . '/' . $slug;
		if ( ! is_dir( $src ) ) {
			return new WP_Error( 'cxp_lab_missing', 'No está instalado ' . $slug );
		}
		self::load_fs();
		self::ensure_dirs();
		$id   = $slug . '-' . gmdate( 'Ymd-His' );
		$dest = self::snapshots_dir() . '/' . $id;
		if ( ! copy_dir( $src, $dest ) ) {
			return new WP_Error( 'cxp_lab_copy', 'No se pudo copiar el snapshot' );
		}
		$version = self::version_for_slug( $slug );
		$manifest = array(
			'id'      => $id,
			'slug'    => $slug,
			'version' => $version,
			'created' => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
		);
		file_put_contents( $dest . '/cxp-snapshot.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		return $manifest;
	}

	private static function restore_snapshot( $id ) {
		$id = sanitize_file_name( $id );
		$dir = self::snapshots_dir() . '/' . $id;
		if ( ! is_dir( $dir ) ) {
			return new WP_Error( 'cxp_lab_snap', 'Snapshot no encontrado' );
		}
		$manifest = self::read_manifest( $dir );
		$slug     = $manifest['slug'] ?? '';
		if ( ! in_array( $slug, self::SLUGS, true ) ) {
			return new WP_Error( 'cxp_lab_slug', 'Snapshot inválido' );
		}
		self::load_fs();
		$dest = WP_PLUGIN_DIR . '/' . $slug;
		if ( is_dir( $dest ) ) {
			self::rmdir( $dest );
		}
		if ( ! copy_dir( $dir, $dest ) ) {
			return new WP_Error( 'cxp_lab_copy', 'No se pudo restaurar' );
		}
		$manifest_file = $dest . '/cxp-snapshot.json';
		if ( file_exists( $manifest_file ) ) {
			wp_delete_file( $manifest_file );
		}
		return true;
	}

	private static function tracked_plugins() {
		$rows = array();
		foreach ( self::SLUGS as $slug ) {
			$file = self::plugin_file_for_slug( $slug );
			$path = $file ? WP_PLUGIN_DIR . '/' . $file : WP_PLUGIN_DIR . '/' . $slug;
			$data = is_readable( $path ) && is_file( $path ) ? get_plugin_data( $path, false, false ) : array();
			$rows[] = array(
				'slug'    => $slug,
				'file'    => $file ?: $slug,
				'name'    => $data['Name'] ?? $slug,
				'version' => $data['Version'] ?? self::version_for_slug( $slug ),
				'active'  => $file ? is_plugin_active( $file ) : false,
			);
		}
		return $rows;
	}

	private static function list_snapshots() {
		$dir = self::snapshots_dir();
		if ( ! is_dir( $dir ) ) {
			return array();
		}
		$rows = array();
		foreach ( scandir( $dir ) as $name ) {
			if ( '.' === $name || '..' === $name || 0 === strpos( $name, 'tmp-' ) ) {
				continue;
			}
			$path = $dir . '/' . $name;
			if ( ! is_dir( $path ) ) {
				continue;
			}
			$manifest = self::read_manifest( $path );
			$rows[]   = array(
				'id'      => $manifest['id'] ?? $name,
				'slug'    => $manifest['slug'] ?? $name,
				'version' => $manifest['version'] ?? 'n/d',
				'created' => $manifest['created'] ?? $name,
			);
		}
		usort(
			$rows,
			static function ( $a, $b ) {
				return strcmp( $b['created'], $a['created'] );
			}
		);
		return $rows;
	}

	private static function drop_zips() {
		$dir = self::drop_dir();
		if ( ! is_dir( $dir ) ) {
			return array();
		}
		$out = array();
		foreach ( scandir( $dir ) as $name ) {
			if ( preg_match( '/\.zip$/i', $name ) ) {
				$out[] = $name;
			}
		}
		return $out;
	}

	private static function plugin_roots_in( $tmp ) {
		$roots = array();
		foreach ( glob( $tmp . '/*.php' ) ?: array() as $file ) {
			$data = get_plugin_data( $file, false, false );
			if ( ! empty( $data['Name'] ) ) {
				$roots[] = $tmp;
				return $roots;
			}
		}
		foreach ( glob( $tmp . '/*', GLOB_ONLYDIR ) ?: array() as $dir ) {
			foreach ( glob( $dir . '/*.php' ) ?: array() as $file ) {
				$data = get_plugin_data( $file, false, false );
				if ( ! empty( $data['Name'] ) ) {
					$roots[] = $dir;
					break;
				}
			}
		}
		return $roots;
	}

	private static function plugin_file_for_slug( $slug ) {
		$map = array(
			'chilexpress-oficial' => 'chilexpress-oficial/chilexpress-woo-oficial.php',
			'woocommerce'         => 'woocommerce/woocommerce.php',
		);
		if ( isset( $map[ $slug ] ) && file_exists( WP_PLUGIN_DIR . '/' . $map[ $slug ] ) ) {
			return $map[ $slug ];
		}
		foreach ( glob( WP_PLUGIN_DIR . '/' . $slug . '/*.php' ) ?: array() as $file ) {
			$data = get_plugin_data( $file, false, false );
			if ( ! empty( $data['Name'] ) ) {
				return $slug . '/' . basename( $file );
			}
		}
		return '';
	}

	private static function version_for_slug( $slug ) {
		$file = self::plugin_file_for_slug( $slug );
		if ( ! $file ) {
			return 'n/d';
		}
		$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );
		return $data['Version'] !== '' ? $data['Version'] : 'n/d';
	}

	private static function read_manifest( $dir ) {
		$path = $dir . '/cxp-snapshot.json';
		if ( ! is_readable( $path ) ) {
			return array();
		}
		$data = json_decode( (string) file_get_contents( $path ), true );
		return is_array( $data ) ? $data : array();
	}

	private static function load_fs() {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! defined( 'FS_METHOD' ) ) {
			define( 'FS_METHOD', 'direct' );
		}
		WP_Filesystem();
		// unzip_file() usa estas constantes; si WP_Filesystem() no las define,
		// la instalacion muere con un fatal.
		if ( ! defined( 'FS_CHMOD_DIR' ) ) {
			define( 'FS_CHMOD_DIR', ( fileperms( ABSPATH ) & 0777 ) | 0755 );
		}
		if ( ! defined( 'FS_CHMOD_FILE' ) ) {
			define( 'FS_CHMOD_FILE', ( fileperms( ABSPATH . 'index.php' ) & 0777 ) | 0644 );
		}
	}

	private static function rmdir( $dir ) {
		global $wp_filesystem;
		if ( $wp_filesystem && $wp_filesystem->is_dir( $dir ) ) {
			$wp_filesystem->rmdir( $dir, true );
			return;
		}
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		foreach ( $items as $item ) {
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
		rmdir( $dir );
	}

	private static function ensure_dirs() {
		wp_mkdir_p( self::snapshots_dir() );
		wp_mkdir_p( self::drop_dir() );
	}

	private static function snapshots_dir() {
		return WP_CONTENT_DIR . '/cxp-snapshots';
	}

	private static function drop_dir() {
		return dirname( ABSPATH ) . '/drop-plugins';
	}

	private static function sanitize_slug( $slug ) {
		$slug = sanitize_key( $slug );
		return str_replace( '_', '-', $slug );
	}

	private static function ajax_guard() {
		self::ensure_admin();
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), self::NONCE ) ) {
			wp_send_json_error( 'Nonce inválido' );
		}
	}

	private static function ensure_admin() {
		if ( is_user_logged_in() && current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( function_exists( 'cxp_auto_login_user' ) ) {
			cxp_auto_login_user();
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( 'Sin permiso' );
		}
	}
}

Cxp_Plugin_Lab::boot();
