<?php
/**
 * Plugin Name: APIs Chilexpress (keys oficiales del plugin)
 * Version: 1.0.0
 * Description: Carga las 3 subscription keys publicadas en Chilexpress Oficial 1.4.0 (staging) y permite pegar las del WordPress oficial.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function cxp_chilexpress_official_api_keys() {
	return array(
		'api_key_georeferencia_value' => '134b01b545bc4fb29a994cddedca9379',
		'api_key_generacion_ot_value' => '5a77a19b76a24297ba01c158286641b7',
		'api_key_cotizador_value'     => 'fd46aa18a9fe44c6b49626692605a2e8',
		'api_key_cotizacion_value'    => 'fd46aa18a9fe44c6b49626692605a2e8',
	);
}

/** Keys desde Dokploy/Docker (.env). Vacío si no están definidas. */
function cxp_chilexpress_env_api_keys() {
	$geo  = function_exists( 'cxp_env' ) ? cxp_env( 'CXP_API_KEY_GEO', '' ) : (string) getenv( 'CXP_API_KEY_GEO' );
	$rate = function_exists( 'cxp_env' ) ? cxp_env( 'CXP_API_KEY_RATE', '' ) : (string) getenv( 'CXP_API_KEY_RATE' );
	$ot   = function_exists( 'cxp_env' ) ? cxp_env( 'CXP_API_KEY_OT', '' ) : (string) getenv( 'CXP_API_KEY_OT' );
	$out  = array();
	if ( $geo !== '' ) {
		$out['api_key_georeferencia_value'] = $geo;
	}
	if ( $rate !== '' ) {
		$out['api_key_cotizador_value']  = $rate;
		$out['api_key_cotizacion_value'] = $rate;
	}
	if ( $ot !== '' ) {
		$out['api_key_generacion_ot_value'] = $ot;
	}
	return $out;
}

add_action( 'http_api_debug', 'cxp_chilexpress_log_http', 10, 5 );

function cxp_chilexpress_log_http( $response, $context, $class, $parsed_args, $url ) {
	if ( ! is_string( $url ) || false === stripos( $url, 'wschilexpress.com' ) ) {
		return;
	}
	$code = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response );
	$body = '';
	if ( isset( $parsed_args['body'] ) && is_string( $parsed_args['body'] ) ) {
		$body = substr( $parsed_args['body'], 0, 400 );
	} elseif ( isset( $parsed_args['body'] ) && is_array( $parsed_args['body'] ) ) {
		$body = substr( wp_json_encode( $parsed_args['body'] ), 0, 400 );
	}
	error_log( '[CXP HTTP] ' . ( $parsed_args['method'] ?? 'GET' ) . ' ' . $url . ' → ' . $code . ' body=' . $body );
}
add_action( 'cxp_debug_console_panels', 'cxp_chilexpress_apis_console' );
add_action( 'admin_post_cxp_cxp_save_keys', 'cxp_chilexpress_apis_save' );

function cxp_chilexpress_apis_ensure() {
	$opt = get_option( 'chilexpress_woo_oficial', array() );
	if ( ! is_array( $opt ) ) {
		$opt = array();
	}
	$changed = false;
	$from_env = cxp_chilexpress_env_api_keys();
	if ( $from_env && get_option( 'cxp_env_keys_applied' ) !== '1' ) {
		$opt     = array_merge( $opt, $from_env );
		$changed = true;
		update_option( 'cxp_env_keys_applied', '1', false );
	}
	$official = cxp_chilexpress_official_api_keys();
	foreach ( $official as $key => $value ) {
		if ( empty( $opt[ $key ] ) ) {
			$opt[ $key ] = $value;
			$changed     = true;
		}
	}
	foreach ( array(
		'api_key_georeferencia_enabled',
		'api_key_generacion_ot_enabled',
		'api_key_cotizador_enabled',
		'api_key_cotizacion_enabled',
	) as $flag ) {
		if ( empty( $opt[ $flag ] ) ) {
			$opt[ $flag ] = '1';
			$changed      = true;
		}
	}
	if ( empty( $opt['ambiente'] ) ) {
		$opt['ambiente'] = 'staging';
		$changed         = true;
	}
	if ( $changed ) {
		update_option( 'chilexpress_woo_oficial', $opt, false );
	}
}

function cxp_chilexpress_apis_status() {
	$opt = get_option( 'chilexpress_woo_oficial', array() );
	$rows = array(
		array(
			'producto' => 'Cobertura / Georeferencia',
			'suscribe' => 'https://developers.wschilexpress.com/products/georeference/subscribe',
			'option'   => 'api_key_georeferencia_value',
			'enabled'  => ! empty( $opt['api_key_georeferencia_enabled'] ),
		),
		array(
			'producto' => 'Cotizador',
			'suscribe' => 'https://developers.wschilexpress.com/products/rating/subscribe',
			'option'   => 'api_key_cotizador_value',
			'enabled'  => ! empty( $opt['api_key_cotizador_enabled'] ) || ! empty( $opt['api_key_cotizacion_enabled'] ),
		),
		array(
			'producto' => 'Envíos / Órdenes de transporte',
			'suscribe' => 'https://developers.wschilexpress.com/products/transportorders/subscribe',
			'option'   => 'api_key_generacion_ot_value',
			'enabled'  => ! empty( $opt['api_key_generacion_ot_enabled'] ),
		),
	);
	foreach ( $rows as &$row ) {
		$val           = trim( (string) ( $opt[ $row['option'] ] ?? '' ) );
		$row['filled'] = $val !== '';
		$row['tail']   = $val !== '' ? substr( $val, -4 ) : '';
		$row['value']  = $val;
	}
	unset( $row );
	return array(
		'ambiente' => $opt['ambiente'] ?? 'staging',
		'rows'     => $rows,
	);
}

add_action( 'init', 'cxp_chilexpress_apis_ensure', 5 );

function cxp_chilexpress_apis_console() {
	$status = cxp_chilexpress_apis_status();
	$save   = admin_url( 'admin-post.php' );
	$local  = admin_url( 'admin.php?page=chilexpress_woo_oficial_menu' );
	$portal = 'https://developers.wschilexpress.com/new-products';
	$profile = 'https://developers.wschilexpress.com/developer';
	$remote = function_exists( 'cxp_remote_shop_url' ) ? cxp_remote_shop_url() : '';
	$has_env = (bool) cxp_chilexpress_env_api_keys();
	?>
	<style>
		#cxp-dbg-apis{margin:0 0 12px;padding:10px 12px;border:1px solid #3d4d66;border-radius:8px;background:#111827}
		#cxp-dbg-apis p{margin:0 0 8px;color:#9fb0c7}
		#cxp-dbg-apis p strong{color:#fff200}
		#cxp-dbg-apis table{width:100%;border-collapse:collapse;margin:0 0 10px}
		#cxp-dbg-apis th,#cxp-dbg-apis td{padding:6px 8px;border-bottom:1px solid #243044;text-align:left}
		#cxp-dbg-apis th{color:#fff200;font-size:11px;letter-spacing:.06em;text-transform:uppercase}
		#cxp-dbg-apis .ok{color:#86efac}
		#cxp-dbg-apis .off{color:#fca5a5}
		#cxp-dbg-apis input[type=text]{width:100%;max-width:420px;background:#1e293b;border:1px solid #3d4d66;color:#e8edf5;border-radius:6px;padding:4px 8px}
		#cxp-dbg-apis .cxp-lab-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:8px 0}
		#cxp-dbg-apis a{color:#93c5fd}
	</style>
	<div id="cxp-dbg-apis">
		<p><strong>APIs Chilexpress</strong> — las 3 del portal
			<a href="<?php echo esc_url( $portal ); ?>" target="_blank" rel="noopener noreferrer">developers.wschilexpress.com/new-products</a>
			(Cobertura, Cotizador, Envíos). Ambiente: <span class="ok"><?php echo esc_html( (string) $status['ambiente'] ); ?></span>
		</p>
		<table>
			<thead>
				<tr>
					<th>Producto</th>
					<th>Módulo</th>
					<th>Key</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $status['rows'] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['producto'] ); ?></td>
						<td class="<?php echo $row['enabled'] ? 'ok' : 'off'; ?>"><?php echo $row['enabled'] ? 'habilitado' : 'apagado'; ?></td>
						<td class="<?php echo $row['filled'] ? 'ok' : 'off'; ?>">
							<?php echo $row['filled'] ? 'guardada …' . esc_html( $row['tail'] ) : 'vacía'; ?>
						</td>
						<td><a href="<?php echo esc_url( $row['suscribe'] ); ?>" target="_blank" rel="noopener noreferrer">Suscribir</a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p>Pantalla de keys:
			<a href="<?php echo esc_url( $local ); ?>">Habilitación de Módulos</a>
			<?php if ( $remote ) : ?>
				· <a href="<?php echo esc_url( $remote . '/wp-admin/admin.php?page=chilexpress_woo_oficial_menu' ); ?>" target="_blank" rel="noopener noreferrer">tienda remota (CXP_REMOTE_SHOP_URL)</a>
			<?php endif; ?>
			·
			<a href="<?php echo esc_url( $profile ); ?>" target="_blank" rel="noopener noreferrer">perfil developers</a>
		</p>
		<form method="post" action="<?php echo esc_url( $save ); ?>">
			<input type="hidden" name="action" value="cxp_cxp_save_keys">
			<?php wp_nonce_field( 'cxp_cxp_keys' ); ?>
			<p>Pega las subscription keys de staging (no se publican en el repo). Vacío = defaults del ZIP 1.4.0 o variables <code>CXP_API_KEY_*</code>.
				<?php echo $has_env ? ' Este contenedor tiene keys por entorno.' : ''; ?></p>
			<table>
				<tr>
					<td>Georeferencia / Cobertura</td>
					<td><input type="password" name="geo" value="" placeholder="dejar vacío para no cambiar" autocomplete="off"></td>
				</tr>
				<tr>
					<td>Cotizador</td>
					<td><input type="password" name="rate" value="" placeholder="dejar vacío para no cambiar" autocomplete="off"></td>
				</tr>
				<tr>
					<td>Envíos / OT</td>
					<td><input type="password" name="ot" value="" placeholder="dejar vacío para no cambiar" autocomplete="off"></td>
				</tr>
			</table>
			<div class="cxp-lab-row">
				<label>Ambiente
					<select name="ambiente">
						<option value="staging" <?php selected( $status['ambiente'], 'staging' ); ?>>Staging (pruebas)</option>
						<option value="production" <?php selected( $status['ambiente'], 'production' ); ?>>Production</option>
					</select>
				</label>
				<button type="submit" class="cxp-dbg-copy-one" name="mode" value="save">Guardar keys pegadas</button>
				<button type="submit" class="cxp-dbg-copy-one" name="mode" value="env">Cargar keys del entorno (Dokploy)</button>
				<button type="submit" class="cxp-dbg-copy-one" name="mode" value="official">Cargar defaults del plugin 1.4.0</button>
			</div>
		</form>
		<p>TCC de prueba del plugin: <code>18578680</code> · RUT de prueba: <code>96756430</code> · origen Providencia (<code>PROV</code>).</p>
	</div>
	<?php
}

function cxp_chilexpress_apis_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		if ( function_exists( 'cxp_auto_login_user' ) ) {
			cxp_auto_login_user();
		}
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permiso' );
	}
	check_admin_referer( 'cxp_cxp_keys' );

	$opt = get_option( 'chilexpress_woo_oficial', array() );
	if ( ! is_array( $opt ) ) {
		$opt = array();
	}
	$official = cxp_chilexpress_official_api_keys();
	$from_env = cxp_chilexpress_env_api_keys();
	$mode     = sanitize_key( wp_unslash( $_POST['mode'] ?? 'save' ) );

	if ( 'official' === $mode ) {
		$geo  = $official['api_key_georeferencia_value'];
		$rate = $official['api_key_cotizador_value'];
		$ot   = $official['api_key_generacion_ot_value'];
	} elseif ( 'env' === $mode ) {
		$geo  = $from_env['api_key_georeferencia_value'] ?? $official['api_key_georeferencia_value'];
		$rate = $from_env['api_key_cotizador_value'] ?? $official['api_key_cotizador_value'];
		$ot   = $from_env['api_key_generacion_ot_value'] ?? $official['api_key_generacion_ot_value'];
	} else {
		$geo  = sanitize_text_field( wp_unslash( $_POST['geo'] ?? '' ) );
		$rate = sanitize_text_field( wp_unslash( $_POST['rate'] ?? '' ) );
		$ot   = sanitize_text_field( wp_unslash( $_POST['ot'] ?? '' ) );
		if ( $geo === '' ) {
			$geo = $opt['api_key_georeferencia_value'] ?? $official['api_key_georeferencia_value'];
		}
		if ( $rate === '' ) {
			$rate = $opt['api_key_cotizador_value'] ?? $official['api_key_cotizador_value'];
		}
		if ( $ot === '' ) {
			$ot = $opt['api_key_generacion_ot_value'] ?? $official['api_key_generacion_ot_value'];
		}
	}

	$opt['api_key_georeferencia_value']  = $geo;
	$opt['api_key_cotizador_value']      = $rate;
	$opt['api_key_cotizacion_value']     = $rate;
	$opt['api_key_generacion_ot_value']  = $ot;
	$opt['api_key_georeferencia_enabled'] = '1';
	$opt['api_key_generacion_ot_enabled'] = '1';
	$opt['api_key_cotizador_enabled']     = '1';
	$opt['api_key_cotizacion_enabled']    = '1';
	$amb = sanitize_key( wp_unslash( $_POST['ambiente'] ?? 'staging' ) );
	$opt['ambiente'] = 'production' === $amb ? 'production' : 'staging';
	update_option( 'chilexpress_woo_oficial', $opt, false );

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
	exit;
}
