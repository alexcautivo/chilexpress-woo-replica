<?php
/**
 * Plugin Name: Consola de réplica (versiones + logs)
 * Version: 1.2.0
 * Description: Barra inferior desplegable con versiones exactas, plugins y errores, lista para copiar y pegar.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cxp_Debug_Console {
	private static $runtime_errors = array();
	private static $started_at     = 0.0;

	public static function boot() {
		self::$started_at = microtime( true );

		set_error_handler( array( __CLASS__, 'capture_error' ), E_ALL );
		register_shutdown_function( array( __CLASS__, 'capture_shutdown' ) );

		add_action( 'wp_footer', array( __CLASS__, 'render' ), 99999 );
		add_action( 'admin_footer', array( __CLASS__, 'render' ), 99999 );
		add_action( 'login_footer', array( __CLASS__, 'render' ), 99999 );

		add_action( 'wp_ajax_cxp_dbg_delete_orders', array( __CLASS__, 'ajax_delete_orders' ) );
		add_action( 'wp_ajax_nopriv_cxp_dbg_delete_orders', array( __CLASS__, 'ajax_delete_orders' ) );
		add_action( 'admin_post_cxp_dbg_delete_one', array( __CLASS__, 'admin_delete_one' ) );
		add_action( 'admin_post_cxp_dbg_delete_all', array( __CLASS__, 'admin_delete_all' ) );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( __CLASS__, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( __CLASS__, 'handle_bulk_actions' ), 10, 3 );
		add_filter( 'woocommerce_bulk_action_ids', array( __CLASS__, 'filter_bulk_order_ids' ), 10, 3 );
		add_filter( 'woocommerce_admin_order_actions', array( __CLASS__, 'order_row_actions' ), 20, 2 );
		add_action( 'woocommerce_order_list_table_extra_tablenav', array( __CLASS__, 'orders_table_button' ), 20, 2 );
		add_action( 'admin_head', array( __CLASS__, 'admin_styles' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_ui' ), 40 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_ui' ), 40 );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue_ui' ), 40 );
	}

	public static function enqueue_ui() {
		$base = content_url( 'mu-plugins/cxp-debug-console' );
		wp_enqueue_style( 'cxp-console-ui', $base . '/console-ui.css', array(), '1.8.2' );
		wp_enqueue_script( 'cxp-lucide', $base . '/lucide.min.js', array(), '0.469.0', true );
		wp_enqueue_script( 'cxp-console-ui', $base . '/console-ui.js', array( 'cxp-lucide' ), '1.8.2', true );
	}

	public static function capture_error( $errno, $errstr, $errfile = '', $errline = 0 ) {
		self::$runtime_errors[] = array(
			'type'    => self::error_label( $errno ),
			'message' => $errstr,
			'file'    => $errfile,
			'line'    => $errline,
		);
		return false;
	}

	public static function capture_shutdown() {
		$last = error_get_last();
		if ( ! $last ) {
			return;
		}
		$fatal = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR );
		if ( ! in_array( (int) $last['type'], $fatal, true ) ) {
			return;
		}
		self::$runtime_errors[] = array(
			'type'    => self::error_label( $last['type'] ),
			'message' => $last['message'],
			'file'    => $last['file'],
			'line'    => $last['line'],
		);
	}

	public static function render() {
		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$report        = self::build_report();
		$payload       = $report['copy'];
		$errors_n      = count( self::$runtime_errors );
		$log_n         = substr_count( $report['log_tail'], "\n" );
		$inventory     = self::collect_inventory();
		$orders        = self::list_shop_orders();
		$orders_local  = admin_url( 'admin.php?page=wc-orders' );
		$remote_base   = function_exists( 'cxp_remote_shop_url' ) ? cxp_remote_shop_url() : '';
		$orders_remote = $remote_base ? ( $remote_base . '/wp-admin/admin.php?page=wc-orders' ) : '';
		$checkout_url  = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
		$shop_url      = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
		$is_admin_ui   = is_admin();
		$can_cart      = function_exists( 'cxp_storefront_can_open_cart' ) && cxp_storefront_can_open_cart();
		$cart_url      = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
		?>
		<style id="cxp-debug-console-css">
			#cxp-dbg{position:fixed;left:0;right:0;bottom:0;z-index:2147483000;font:12px/1.45 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:#e8edf5 !important;-webkit-text-fill-color:#e8edf5;text-align:left;color-scheme:dark}
			#cxp-dbg *{box-sizing:border-box;color:inherit}
			#cxp-dbg a,#cxp-dbg button,#cxp-dbg p,#cxp-dbg td,#cxp-dbg th,#cxp-dbg span,#cxp-dbg label,#cxp-dbg strong,#cxp-dbg pre,#cxp-dbg h3,#cxp-dbg code,#cxp-dbg em{color:#e8edf5 !important;-webkit-text-fill-color:#e8edf5}
			#cxp-dbg select,#cxp-dbg input,#cxp-dbg textarea,#cxp-dbg option{
				appearance:auto;
				color:#e8edf5 !important;
				-webkit-text-fill-color:#e8edf5 !important;
				background:#1e293b !important;
				background-color:#1e293b !important;
				border:1px solid #64748b !important;
				border-radius:6px;
				padding:4px 8px;
				color-scheme:dark;
				caret-color:#f8fafc
			}
			#cxp-dbg select option,#cxp-dbg optgroup{
				background:#0f172a !important;
				background-color:#0f172a !important;
				color:#e8edf5 !important;
				-webkit-text-fill-color:#e8edf5 !important
			}
			#cxp-dbg input::placeholder,#cxp-dbg textarea::placeholder{
				color:#94a3b8 !important;
				-webkit-text-fill-color:#94a3b8 !important;
				opacity:1
			}
			#cxp-dbg input[type=checkbox],#cxp-dbg input[type=radio]{accent-color:#38bdf8;background:#1e293b !important;width:auto;height:auto}
			#cxp-dbg-bar{display:flex;gap:10px;align-items:center;min-height:36px;padding:6px 12px;border-top:1px solid #3d4d66;background:#111827;cursor:pointer;user-select:none}
			#cxp-dbg-bar strong{color:#fff200 !important;-webkit-text-fill-color:#fff200;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
			#cxp-dbg-credit{color:#93c5fd !important;-webkit-text-fill-color:#93c5fd;font-size:11px;white-space:nowrap}
			#cxp-dbg-meta{flex:1;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;color:#9fb0c7 !important;-webkit-text-fill-color:#9fb0c7}
			#cxp-dbg-count{padding:2px 8px;border-radius:999px;background:#7f1d1d;color:#fecaca !important;-webkit-text-fill-color:#fecaca;font-weight:700}
			#cxp-dbg-count.is-ok{background:#14532d;color:#bbf7d0 !important;-webkit-text-fill-color:#bbf7d0}
			#cxp-dbg-panel{display:none;max-height:min(70vh,760px);overflow:auto;padding:12px;border-top:1px solid #243044;background:#0b1220}
			#cxp-dbg.is-open #cxp-dbg-panel{display:block}
			#cxp-dbg.is-open #cxp-dbg-bar{cursor:default}
			#cxp-dbg-actions{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;align-items:center}
			#cxp-dbg-actions button,#cxp-dbg-actions a.cxp-dbg-btn,#cxp-dbg a.cxp-dbg-btn,#cxp-dbg .cxp-dbg-copy-one,#cxp-dbg .cxp-dbg-del-one,#cxp-dbg button.cxp-dbg-copy-one{appearance:none;display:inline-flex;align-items:center;border:1px solid #3d4d66;border-radius:6px;background:#1e293b;color:#e8edf5 !important;-webkit-text-fill-color:#e8edf5;padding:6px 10px;cursor:pointer;font:inherit;text-decoration:none}
			#cxp-dbg-actions button:hover,#cxp-dbg-actions a.cxp-dbg-btn:hover,#cxp-dbg a.cxp-dbg-btn:hover,#cxp-dbg .cxp-dbg-copy-one:hover,#cxp-dbg .cxp-dbg-del-one:hover{background:#334155}
			#cxp-dbg-actions a.cxp-dbg-btn-ot,#cxp-dbg-ot a.cxp-dbg-btn-ot,#cxp-dbg a.cxp-dbg-btn-ot{border-color:#ca8a04;background:#854d0e;color:#fef08a !important;-webkit-text-fill-color:#fef08a;font-weight:700}
			#cxp-dbg-actions a.cxp-dbg-btn-ot:hover,#cxp-dbg-ot a.cxp-dbg-btn-ot:hover,#cxp-dbg a.cxp-dbg-btn-ot:hover{background:#a16207;color:#fff !important;-webkit-text-fill-color:#fff}
			#cxp-dbg-ot a.cxp-dbg-btn{appearance:none;display:inline-flex;align-items:center;border:1px solid #3d4d66;border-radius:6px;background:#1e293b;color:#e8edf5;padding:6px 10px;cursor:pointer;font:inherit;text-decoration:none}
			#cxp-dbg-shortcuts,#cxp-dbg-plugins,#cxp-dbg-orders,#cxp-dbg-ot{margin:0 0 12px;padding:10px 12px;border:1px solid #3d4d66;border-radius:8px;background:#111827}
			#cxp-dbg-shortcuts p,#cxp-dbg-plugins p,#cxp-dbg-orders p,#cxp-dbg-ot p{margin:0 0 8px;color:#9fb0c7 !important;-webkit-text-fill-color:#9fb0c7}
			#cxp-dbg-shortcuts p strong,#cxp-dbg-plugins p strong,#cxp-dbg-orders p strong,#cxp-dbg-ot p strong{color:#fff200 !important;-webkit-text-fill-color:#fff200;font-weight:700}
			#cxp-dbg-ot .cxp-dbg-ot-actions,#cxp-dbg-ot .cxp-ot-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
			#cxp-dbg-plugins table,#cxp-dbg-orders table{width:100%;border-collapse:collapse}
			#cxp-dbg-plugins th,#cxp-dbg-plugins td,#cxp-dbg-orders th,#cxp-dbg-orders td{padding:6px 8px;border-bottom:1px solid #243044;text-align:left;vertical-align:middle}
			#cxp-dbg-orders a.cxp-dbg-btn{appearance:none;display:inline-flex;align-items:center;border:1px solid #3d4d66;border-radius:6px;background:#1e293b;color:#e8edf5;padding:3px 8px;margin:0 4px 0 0;cursor:pointer;font:inherit;text-decoration:none;font-size:11px}
			#cxp-dbg-plugins th,#cxp-dbg-orders th{color:#fff200;font-size:11px;letter-spacing:.06em;text-transform:uppercase}
			#cxp-dbg-actions a.cxp-dbg-btn-del,#cxp-dbg .cxp-dbg-del-one,#cxp-dbg #cxp-dbg-del-all{border-color:#7f1d1d;background:#7f1d1d;color:#fecaca}
			#cxp-dbg-actions a.cxp-dbg-btn-del:hover,#cxp-dbg .cxp-dbg-del-one:hover,#cxp-dbg #cxp-dbg-del-all:hover{background:#991b1b;color:#fff}
			#cxp-dbg-plugins td.cxp-ver{color:#86efac;font-weight:700;white-space:nowrap}
			#cxp-dbg-plugins td.cxp-file{color:#93c5fd}
			#cxp-dbg-plugins .is-off{color:#fca5a5}
			#cxp-dbg .cxp-dbg-copy-one{padding:3px 8px;font-size:11px}
			#cxp-dbg pre,#cxp-dbg #cxp-dbg-copy-all,#cxp-dbg #cxp-docs-out{margin:0;white-space:pre-wrap;word-break:break-word;color:#f8fafc !important;-webkit-text-fill-color:#f8fafc !important;background:#020617 !important;border:1px solid #334155;padding:10px;border-radius:6px;max-height:min(40vh,420px);overflow:auto}
			#cxp-dbg h3{margin:14px 0 6px;color:#fff200 !important;-webkit-text-fill-color:#fff200;font-size:11px;letter-spacing:.08em;text-transform:uppercase}
			#cxp-dbg .cxp-err{color:#fca5a5 !important;-webkit-text-fill-color:#fca5a5}
			#cxp-dbg-copied{color:#86efac !important;-webkit-text-fill-color:#86efac;font-weight:700}
			#cxp-dbg a.cxp-dbg-btn-export{border-color:#1d4ed8;background:#1e3a8a;color:#dbeafe !important;-webkit-text-fill-color:#dbeafe;font-weight:700}
			body.cxp-dbg-pad{padding-bottom:40px}
		</style>
		<div id="cxp-dbg" class="<?php echo $is_admin_ui ? 'cxp-dbg--admin' : 'cxp-dbg--store'; ?>">
			<div id="cxp-dbg-bar" role="button" tabindex="0" aria-expanded="false">
				<strong>Consola réplica</strong>
				<span class="cxp-dbg-place"><?php echo $is_admin_ui ? 'WP Admin' : 'Tienda'; ?></span>
				<span id="cxp-dbg-credit">Aeolabs · Alexander Cautivo</span>
				<span id="cxp-dbg-meta"><?php echo esc_html( $report['summary'] ); ?></span>
				<span id="cxp-dbg-count" class="<?php echo $errors_n ? '' : 'is-ok'; ?>">
					<?php echo $errors_n ? esc_html( $errors_n . ' error(es) esta petición' ) : 'sin errores esta petición'; ?>
				</span>
				<span><?php echo $log_n ? esc_html( $log_n . ' líneas debug.log' ) : 'debug.log vacío'; ?> · clic para abrir</span>
			</div>
			<div id="cxp-dbg-panel">
				<nav id="cxp-dbg-tabs" aria-label="Secciones de la consola"></nav>
				<p id="cxp-dbg-tabhint">Elige una pestaña. Cada botón dice para qué sirve.</p>
				<div id="cxp-dbg-shortcuts">
					<?php if ( $is_admin_ui ) : ?>
						<p><strong>Atajos de escritorio</strong> — Pedidos HPOS y OT. No uses Acciones masivas sin marcar el checkbox. Entra al pedido (Editar) y pulsa <em>Generar OT</em>.</p>
					<?php else : ?>
						<p><strong>Atajos de la tienda</strong> — Primero checkout (dirección real + cotizar). El carrito se habilita solo después de pasar por el checkout.</p>
					<?php endif; ?>
					<div id="cxp-dbg-actions">
						<a class="cxp-dbg-btn" href="<?php echo esc_url( $shop_url ); ?>" title="Abre el catálogo con peso, medidas y cantidad 1–10.">Tienda</a>
						<button type="button" id="cxp-dbg-search" class="cxp-dbg-btn" title="Enfoca el buscador del encabezado. Escribe un producto y pulsa Buscar o Enter.">Buscar</button>
						<a class="cxp-dbg-btn" href="<?php echo esc_url( $checkout_url ); ?>" title="Checkout clásico: elige un destino real y cotiza Chilexpress.">Checkout</a>
						<?php if ( $can_cart ) : ?>
							<a class="cxp-dbg-btn" href="<?php echo esc_url( $cart_url ); ?>" title="Ya pasaste por el checkout: aquí puedes revisar cantidades.">Carrito</a>
						<?php else : ?>
							<span class="cxp-dbg-help">Carrito bloqueado hasta visitar el checkout.</span>
						<?php endif; ?>
						<?php if ( $is_admin_ui ) : ?>
							<a class="cxp-dbg-btn cxp-dbg-btn-ot" href="<?php echo esc_url( $orders_local ); ?>" title="Lista de pedidos locales para generar OT.">Pedidos locales (Generar OTs)</a>
							<?php if ( $orders_remote ) : ?>
								<a class="cxp-dbg-btn cxp-dbg-btn-ot" href="<?php echo esc_url( $orders_remote ); ?>" target="_blank" rel="noopener noreferrer" title="Misma pantalla en la tienda remota, si está configurada.">Pedidos tienda remota</a>
							<?php endif; ?>
							<button type="button" id="cxp-dbg-del-all">Borrar todos los pedidos</button>
						<?php endif; ?>
						<button type="button" id="cxp-dbg-copy">Copiar todo</button>
						<button type="button" id="cxp-dbg-copy-ver">Copiar solo versiones</button>
						<button type="button" id="cxp-dbg-copy-plugins">Copiar todos los plugins</button>
						<button type="button" id="cxp-dbg-close">Cerrar</button>
						<span id="cxp-dbg-copied" hidden>Copiado</span>
					</div>
				</div>
				<?php do_action( 'cxp_debug_console_panels' ); ?>
				<div id="cxp-dbg-orders">
					<p><strong>Pedidos</strong> — <?php echo esc_html( (string) count( $orders ) ); ?> actuales. Bórralos todos o uno por uno (definitivo, sin papelera).</p>
					<table>
						<thead>
							<tr>
								<th>#</th>
								<th>Fecha</th>
								<th>Estado</th>
								<th>Total</th>
								<th>Cliente</th>
								<th></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! $orders ) : ?>
								<tr><td colspan="7">No hay pedidos.</td></tr>
							<?php else : ?>
								<?php foreach ( $orders as $row ) : ?>
									<tr>
										<td>#<?php echo esc_html( (string) $row['number'] ); ?></td>
										<td><?php echo esc_html( $row['date'] ); ?></td>
										<td><?php echo esc_html( $row['status'] ); ?></td>
										<td><?php echo esc_html( $row['total'] ); ?></td>
										<td><?php echo esc_html( $row['name'] ); ?></td>
										<td>
											<a class="cxp-dbg-btn cxp-dbg-btn-ot" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $row['id'] ) ); ?>">Detalle</a>
											<a class="cxp-dbg-btn cxp-dbg-btn-ot" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=chilexpress_woo_oficial_generar_ot&action=generar_ot&order_id=' . $row['id'] . '&pedidos_cxp=1' ), 'generar-ot' ) ); ?>">Generar OT</a>
										</td>
										<td>
											<button type="button" class="cxp-dbg-del-one" data-id="<?php echo esc_attr( (string) $row['id'] ); ?>">Borrar</button>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
				<div id="cxp-dbg-plugins">
					<p><strong>Plugins instalados</strong> — <?php echo esc_html( (string) count( $inventory['rows'] ) ); ?> ítems. Copia la lista completa o cada uno con su versión.</p>
					<table>
						<thead>
							<tr>
								<th>Plugin</th>
								<th>Versión</th>
								<th>Estado</th>
								<th>Archivo</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $inventory['rows'] as $row ) : ?>
								<tr>
									<td><?php echo esc_html( $row['name'] ); ?></td>
									<td class="cxp-ver"><?php echo esc_html( $row['version'] ); ?></td>
									<td class="<?php echo 'inactivo' === $row['status'] ? 'is-off' : ''; ?>"><?php echo esc_html( $row['status'] ); ?></td>
									<td class="cxp-file"><?php echo esc_html( $row['file'] ); ?></td>
									<td>
										<button type="button" class="cxp-dbg-copy-one" data-copy="<?php echo esc_attr( rawurlencode( $row['line'] ) ); ?>">Copiar</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<pre id="cxp-dbg-plugins-text" hidden><?php echo esc_html( $inventory['copy'] ); ?></pre>
				</div>
				<h3>Pegar en el ticket / chat</h3>
				<pre id="cxp-dbg-copy-all"><?php echo esc_html( $payload ); ?></pre>
			</div>
		</div>
		<script>
		(function () {
			var root = document.getElementById('cxp-dbg');
			var bar = document.getElementById('cxp-dbg-bar');
			var copyAll = document.getElementById('cxp-dbg-copy-all');
			var pluginsText = document.getElementById('cxp-dbg-plugins-text');
			var copied = document.getElementById('cxp-dbg-copied');
			if (!root || !bar) return;
			document.body.classList.add('cxp-dbg-pad');
			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var orderNonce = <?php echo wp_json_encode( wp_create_nonce( 'cxp_dbg_orders' ) ); ?>;
			function open() {
				root.classList.add('is-open');
				bar.setAttribute('aria-expanded', 'true');
			}
			function close() {
				root.classList.remove('is-open');
				bar.setAttribute('aria-expanded', 'false');
			}
			bar.addEventListener('click', function () {
				if (root.classList.contains('is-open')) close(); else open();
			});
			document.getElementById('cxp-dbg-close').addEventListener('click', function (e) {
				e.stopPropagation();
				close();
			});
			var searchBtn = document.getElementById('cxp-dbg-search');
			if (searchBtn) {
				searchBtn.addEventListener('click', function (e) {
					e.stopPropagation();
					var input = document.getElementById('cxp-header-search') || document.querySelector('.wd-header-search-form input.s, form.searchform input.s');
					if (!input) {
						window.location.href = <?php echo wp_json_encode( $shop_url ); ?>;
						return;
					}
					close();
					input.scrollIntoView({ block: 'center', behavior: 'smooth' });
					window.setTimeout(function () {
						input.focus();
						if (input.select) input.select();
					}, 200);
				});
			}
			function copy(text) {
				function ok() {
					copied.hidden = false;
					setTimeout(function () { copied.hidden = true; }, 1200);
				}
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(ok);
					return;
				}
				var ta = document.createElement('textarea');
				ta.value = text;
				document.body.appendChild(ta);
				ta.select();
				document.execCommand('copy');
				ta.remove();
				ok();
			}
			document.getElementById('cxp-dbg-copy').addEventListener('click', function (e) {
				e.stopPropagation();
				copy(copyAll.textContent);
			});
			document.getElementById('cxp-dbg-copy-ver').addEventListener('click', function (e) {
				e.stopPropagation();
				var block = copyAll.textContent.split('--- LOGS')[0].trim();
				copy(block);
			});
			document.getElementById('cxp-dbg-copy-plugins').addEventListener('click', function (e) {
				e.stopPropagation();
				copy(pluginsText ? pluginsText.textContent : '');
			});
			root.addEventListener('click', function (e) {
				var del = e.target.closest('.cxp-dbg-del-one');
				if (del) {
					e.stopPropagation();
					if (!confirm('¿Borrar el pedido #' + del.getAttribute('data-id') + ' de forma definitiva?')) return;
					deleteOrders('one', del.getAttribute('data-id'));
					return;
				}
				var btn = e.target.closest('.cxp-dbg-copy-one');
				if (!btn || !btn.getAttribute('data-copy')) return;
				e.stopPropagation();
				try {
					copy(decodeURIComponent(btn.getAttribute('data-copy') || ''));
				} catch (err) {
					copy(btn.getAttribute('data-copy') || '');
				}
			});
			function deleteOrders(mode, id) {
				var body = new URLSearchParams();
				body.set('action', 'cxp_dbg_delete_orders');
				body.set('nonce', orderNonce);
				body.set('mode', mode);
				if (id) body.set('id', id);
				fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body.toString()
				}).then(function (r) { return r.json(); }).then(function (data) {
					if (data && data.success) {
						window.location.reload();
						return;
					}
					alert((data && data.data) ? data.data : 'No se pudo borrar');
				}).catch(function () {
					alert('No se pudo borrar');
				});
			}
			var delAll = document.getElementById('cxp-dbg-del-all');
			if (delAll) {
				delAll.addEventListener('click', function (e) {
					e.stopPropagation();
					if (!confirm('¿Borrar TODOS los pedidos de forma definitiva?')) return;
					deleteOrders('all');
				});
			}
		})();
		</script>
		<?php
	}

	public static function ajax_delete_orders() {
		self::ensure_admin_user();
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'cxp_dbg_orders' ) ) {
			wp_send_json_error( 'Nonce inválido' );
		}
		$mode = sanitize_key( wp_unslash( $_POST['mode'] ?? 'one' ) );
		if ( 'all' === $mode ) {
			wp_send_json_success( array( 'deleted' => self::delete_shop_orders() ) );
		}
		$id = absint( $_POST['id'] ?? 0 );
		if ( ! $id || ! self::delete_shop_order( $id ) ) {
			wp_send_json_error( 'No se encontró el pedido' );
		}
		wp_send_json_success( array( 'deleted' => 1 ) );
	}

	public static function admin_delete_one() {
		self::ensure_admin_user();
		check_admin_referer( 'cxp_dbg_orders' );
		$id = absint( $_GET['order_id'] ?? $_POST['order_id'] ?? 0 );
		if ( $id ) {
			self::delete_shop_order( $id );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=wc-orders' ) );
		exit;
	}

	public static function admin_delete_all() {
		self::ensure_admin_user();
		check_admin_referer( 'cxp_dbg_orders' );
		self::delete_shop_orders();
		wp_safe_redirect( admin_url( 'admin.php?page=wc-orders' ) );
		exit;
	}

	public static function bulk_actions( $actions ) {
		$actions['cxp_force_delete'] = 'Borrar definitivo';
		return $actions;
	}

	public static function filter_bulk_order_ids( $ids, $action, $type ) {
		if ( 'order' !== $type ) {
			return $ids;
		}
		$keep = array();
		foreach ( (array) $ids as $id ) {
			$id = absint( $id );
			if ( $id && wc_get_order( $id ) instanceof WC_Order ) {
				$keep[] = $id;
			}
		}
		return $keep;
	}

	public static function handle_bulk_actions( $redirect, $action, $ids ) {
		if ( 'cxp_force_delete' !== $action ) {
			return $redirect;
		}
		$n = 0;
		foreach ( (array) $ids as $id ) {
			if ( self::delete_shop_order( (int) $id ) ) {
				++$n;
			}
		}
		return add_query_arg( 'cxp_deleted', $n, $redirect );
	}

	public static function order_row_actions( $actions, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return $actions;
		}
		$actions['cxp_delete'] = array(
			'url'    => wp_nonce_url(
				admin_url( 'admin-post.php?action=cxp_dbg_delete_one&order_id=' . $order->get_id() ),
				'cxp_dbg_orders'
			),
			'name'   => 'Borrar',
			'action' => 'cxp-delete',
		);
		return $actions;
	}

	public static function orders_table_button( $order_type, $which ) {
		if ( 'top' !== $which || 'shop_order' !== $order_type ) {
			return;
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=cxp_dbg_delete_all' ), 'cxp_dbg_orders' );
		echo '<a class="button" id="cxp-dbg-admin-del-all" href="' . esc_url( $url ) . '" onclick="return confirm(\'¿Borrar TODOS los pedidos de forma definitiva?\');">Borrar todos los pedidos</a>';
	}

	public static function admin_styles() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'woocommerce_page_wc-orders' !== $screen->id ) {
			return;
		}
		echo '<style>
			.wc-action-button-cxp-delete::after{font-family:dashicons;content:"\\f182";}
			#cxp-dbg-admin-del-all{margin-left:8px;color:#b91c1c;border-color:#fecaca;}
		</style>';
	}

	private static function ensure_admin_user() {
		if ( is_user_logged_in() && current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( function_exists( 'cxp_auto_login_user' ) ) {
			cxp_auto_login_user();
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Sin permiso' );
		}
	}

	private static function order_query_statuses() {
		$statuses = function_exists( 'wc_get_order_statuses' ) ? array_keys( wc_get_order_statuses() ) : array();
		return array_merge( $statuses, array( 'trash', 'checkout-draft', 'draft', 'auto-draft' ) );
	}

	private static function list_shop_orders() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		$orders = wc_get_orders(
			array(
				'limit'   => 100,
				'orderby' => 'date',
				'order'   => 'DESC',
				'status'  => self::order_query_statuses(),
				'type'    => 'shop_order',
			)
		);
		$rows = array();
		foreach ( $orders as $order ) {
			$rows[] = array(
				'id'     => $order->get_id(),
				'number' => $order->get_order_number(),
				'status' => wc_get_order_status_name( $order->get_status() ),
				'total'  => wp_strip_all_tags( $order->get_formatted_order_total() ),
				'name'   => trim( $order->get_formatted_billing_full_name() ),
				'date'   => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i' ) : '',
			);
		}
		return $rows;
	}

	private static function delete_shop_order( $id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return false;
		}
		$order = wc_get_order( $id );
		if ( ! $order ) {
			return false;
		}
		return (bool) $order->delete( true );
	}

	private static function delete_shop_orders() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}
		$orders = wc_get_orders(
			array(
				'limit'  => -1,
				'status' => self::order_query_statuses(),
				'type'   => 'shop_order',
				'return' => 'ids',
			)
		);
		$n = 0;
		foreach ( $orders as $id ) {
			if ( self::delete_shop_order( (int) $id ) ) {
				++$n;
			}
		}
		return $n;
	}

	private static function collect_inventory() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$rows   = array();
		$active = (array) get_option( 'active_plugins', array() );

		foreach ( get_plugins() as $file => $data ) {
			$on      = in_array( $file, $active, true ) || ( is_multisite() && is_plugin_active_for_network( $file ) );
			$name    = $data['Name'] ?: $file;
			$version = $data['Version'] !== '' ? $data['Version'] : '(sin versión)';
			$rows[]  = self::inventory_row( 'plugin', $name, $version, $on ? 'activo' : 'inactivo', $file );
		}

		foreach ( wp_get_mu_plugins() as $path ) {
			$data    = get_plugin_data( $path, false, false );
			$file    = 'mu-plugins/' . basename( $path );
			$name    = $data['Name'] ?: basename( $path );
			$version = $data['Version'] !== '' ? $data['Version'] : '(mu-plugin)';
			$rows[]  = self::inventory_row( 'mu-plugin', $name, $version, 'mu-plugin', $file );
		}

		if ( function_exists( '_get_dropins' ) ) {
			foreach ( _get_dropins() as $file => $info ) {
				$path = WP_CONTENT_DIR . '/' . $file;
				if ( ! is_readable( $path ) ) {
					continue;
				}
				$data    = get_plugin_data( $path, false, false );
				$name    = $data['Name'] ?: $file;
				$version = $data['Version'] !== '' ? $data['Version'] : '(drop-in)';
				$rows[]  = self::inventory_row( 'drop-in', $name, $version, 'drop-in', $file );
			}
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				$rank = array(
					'activo'    => 0,
					'mu-plugin' => 1,
					'drop-in'   => 2,
					'inactivo'  => 3,
				);
				$ra   = $rank[ $a['status'] ] ?? 9;
				$rb   = $rank[ $b['status'] ] ?? 9;
				if ( $ra !== $rb ) {
					return $ra <=> $rb;
				}
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		$copy_lines = array( '=== PLUGINS INSTALADOS ===' );
		foreach ( $rows as $row ) {
			$copy_lines[] = $row['line'];
		}

		return array(
			'rows' => $rows,
			'copy' => implode( "\n", $copy_lines ),
		);
	}

	private static function inventory_row( $type, $name, $version, $status, $file ) {
		return array(
			'type'    => $type,
			'name'    => $name,
			'version' => $version,
			'status'  => $status,
			'file'    => $file,
			'line'    => sprintf( '%s  %s  [%s]  %s', $name, $version, $status, $file ),
		);
	}

	private static function build_report() {
		$theme      = wp_get_theme();
		$parent     = $theme->parent() ? $theme->parent() : null;
		$inventory  = self::collect_inventory();
		$tax_status = class_exists( 'Automattic\\WooCommerce\\Enums\\ProductTaxStatus' ) ? 'sí (clase encontrada)' : 'NO (clase NO encontrada — esto dispara el fatal de Chilexpress)';
		$elapsed    = round( ( microtime( true ) - self::$started_at ) * 1000 );

		$runtime = array();
		foreach ( self::$runtime_errors as $err ) {
			$runtime[] = sprintf(
				'[%s] %s in %s:%s',
				$err['type'],
				wp_strip_all_tags( $err['message'] ),
				$err['file'],
				$err['line']
			);
		}

		$log_path = WP_CONTENT_DIR . '/debug.log';
		$log_tail = self::tail_log( $log_path, 80 );

		$summary = sprintf(
			'PHP %s · WP %s · WC %s · Chilexpress %s · %s %s',
			PHP_VERSION,
			get_bloginfo( 'version' ),
			defined( 'WC_VERSION' ) ? WC_VERSION : 'n/d',
			defined( 'CHILEXPRESS_WOO_OFICIAL_VERSION' ) ? CHILEXPRESS_WOO_OFICIAL_VERSION : 'n/d',
			$theme->get( 'Name' ),
			$theme->get( 'Version' )
		);

		$copy = implode(
			"\n",
			array(
				'=== RÉPLICA LOCAL SR-108688 ===',
				'Fecha:              ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC',
				'URL:                ' . home_url( '/' ),
				'Request:            ' . ( $_SERVER['REQUEST_METHOD'] ?? '' ) . ' ' . ( $_SERVER['REQUEST_URI'] ?? '' ),
				'Tiempo petición:    ' . $elapsed . ' ms',
				'Título del sitio:   ' . get_option( 'blogname' ),
				'',
				'--- COMO EN EL CORREO DE WORDPRESS ---',
				'Tema activo: ' . $theme->get( 'Name' ) . ' (versión ' . $theme->get( 'Version' ) . ')',
				'Plugin actual: WooCommerce (versión ' . ( defined( 'WC_VERSION' ) ? WC_VERSION : 'n/d' ) . ')',
				'PHP versión ' . PHP_VERSION,
				'WordPress ' . get_bloginfo( 'version' ),
				'Chilexpress Oficial ' . ( defined( 'CHILEXPRESS_WOO_OFICIAL_VERSION' ) ? CHILEXPRESS_WOO_OFICIAL_VERSION : 'n/d' ),
				'',
				'--- VERSIONES ---',
				'PHP:                ' . PHP_VERSION . ' (' . PHP_SAPI . ', ' . PHP_OS_FAMILY . ' ' . php_uname( 'r' ) . ')',
				'WordPress:          ' . get_bloginfo( 'version' ),
				'WooCommerce:        ' . ( defined( 'WC_VERSION' ) ? WC_VERSION : 'no cargado' ),
				'Chilexpress Oficial:' . ( defined( 'CHILEXPRESS_WOO_OFICIAL_VERSION' ) ? CHILEXPRESS_WOO_OFICIAL_VERSION : 'no cargado' ),
				'Tema:               ' . $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) . ' (' . $theme->get_stylesheet() . ')',
				'Tema padre:         ' . ( $parent ? $parent->get( 'Name' ) . ' ' . $parent->get( 'Version' ) : '(ninguno)' ),
				'ProductTaxStatus:   ' . $tax_status,
				'',
				'--- ENTORNO ---',
				'OS:                 ' . php_uname(),
				'DB engine:          ' . ( defined( 'DB_ENGINE' ) ? DB_ENGINE : 'mysql' ),
				'Locale:             ' . determine_locale(),
				'Moneda WC:          ' . get_option( 'woocommerce_currency', '' ),
				'Peso / medidas:     ' . get_option( 'woocommerce_weight_unit', '' ) . ' / ' . get_option( 'woocommerce_dimension_unit', '' ),
				'Pedidos locales:    ' . admin_url( 'admin.php?page=wc-orders' ),
				'Pedidos remotos:    ' . ( function_exists( 'cxp_remote_shop_url' ) && cxp_remote_shop_url() ? cxp_remote_shop_url() . '/wp-admin/admin.php?page=wc-orders' : '(CXP_REMOTE_SHOP_URL no definido)' ),
				'memory_limit:       ' . ini_get( 'memory_limit' ),
				'max_execution_time: ' . ini_get( 'max_execution_time' ),
				'WP_DEBUG:           ' . ( WP_DEBUG ? 'true' : 'false' ),
				'WP_DEBUG_LOG:       ' . ( WP_DEBUG_LOG ? 'true' : 'false' ),
				'WP_DEBUG_DISPLAY:   ' . ( WP_DEBUG_DISPLAY ? 'true' : 'false' ),
				'Extensiones:        gd=' . ( extension_loaded( 'gd' ) ? 'sí' : 'no' ) . ' curl=' . ( extension_loaded( 'curl' ) ? 'sí' : 'no' ) . ' pdo_sqlite=' . ( extension_loaded( 'pdo_sqlite' ) ? 'sí' : 'no' ) . ' mbstring=' . ( extension_loaded( 'mbstring' ) ? 'sí' : 'no' ),
				'',
				$inventory['copy'],
				'',
				'--- ERRORES DE ESTA PETICIÓN ---',
				$runtime ? implode( "\n", $runtime ) : '(ninguno)',
				'',
				'--- LOGS (cola de wp-content/debug.log) ---',
				$log_tail !== '' ? $log_tail : '(debug.log vacío o no existe)',
			)
		);

		return array(
			'summary'  => $summary,
			'copy'     => $copy,
			'log_tail' => $log_tail,
		);
	}

	private static function tail_log( $path, $lines = 80 ) {
		if ( ! is_readable( $path ) ) {
			return '';
		}
		$size = filesize( $path );
		$fh   = fopen( $path, 'rb' );
		if ( ! $fh ) {
			return '';
		}
		$read = min( $size, 120000 );
		if ( $read > 0 ) {
			fseek( $fh, -$read, SEEK_END );
		}
		$chunk = stream_get_contents( $fh );
		fclose( $fh );
		$chunk = wp_strip_all_tags( $chunk );
		$chunk = preg_replace( "/\n{3,}/", "\n\n", $chunk );
		$all   = preg_split( "/\r\n|\n|\r/", trim( (string) $chunk ) );
		$tail  = array_slice( $all, -$lines );
		return implode( "\n", $tail );
	}

	private static function error_label( $errno ) {
		$map = array(
			E_ERROR             => 'E_ERROR',
			E_WARNING           => 'E_WARNING',
			E_PARSE             => 'E_PARSE',
			E_CORE_ERROR        => 'E_CORE_ERROR',
			E_CORE_WARNING      => 'E_CORE_WARNING',
			E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
			E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
			E_USER_ERROR        => 'E_USER_ERROR',
			E_USER_WARNING      => 'E_USER_WARNING',
			E_NOTICE            => 'E_NOTICE',
			E_USER_NOTICE       => 'E_USER_NOTICE',
			E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
			E_DEPRECATED        => 'E_DEPRECATED',
			E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
		);
		return $map[ $errno ] ?? ( 'E_' . $errno );
	}
}

Cxp_Debug_Console::boot();
