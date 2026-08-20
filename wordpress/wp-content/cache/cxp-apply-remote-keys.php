<?php
$_SERVER['HTTP_HOST']       = '127.0.0.1:8080';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['SERVER_NAME']     = '127.0.0.1';
$_SERVER['SERVER_PORT']     = '8080';
$_SERVER['HTTPS']           = 'off';

require dirname( __DIR__, 2 ) . '/wp-load.php';

$json = json_decode( (string) file_get_contents( __DIR__ . '/cxp-remote-keys.json' ), true );
$mod  = $json['modules'] ?? array();

$opt = get_option( 'chilexpress_woo_oficial', array() );
if ( ! is_array( $opt ) ) {
	$opt = array();
}

$geo  = (string) ( $mod['api_key_georeferencia_value'] ?? '' );
$ot   = (string) ( $mod['api_key_generacion_ot_value'] ?? '' );
$rate = (string) ( $mod['api_key_cotizador_value'] ?? '' );

$opt['api_key_georeferencia_value']   = $geo;
$opt['api_key_generacion_ot_value']   = $ot;
$opt['api_key_cotizador_value']       = $rate;
$opt['api_key_cotizacion_value']      = $rate;
$opt['api_key_georeferencia_enabled'] = '1';
$opt['api_key_generacion_ot_enabled'] = '1';
$opt['api_key_cotizador_enabled']     = '1';
$opt['api_key_cotizacion_enabled']    = '1';
$opt['ambiente']                      = ( $mod['ambiente'] ?? 'staging' ) === 'production' ? 'production' : 'staging';

update_option( 'chilexpress_woo_oficial', $opt, false );
update_option( 'cxp_remote_wp_copied', '1', false );

echo "ambiente=" . $opt['ambiente'] . "\n";
echo "geo=" . $geo . "\n";
echo "ot=" . $ot . "\n";
echo "rate=" . $rate . "\n";
echo "saved\n";
