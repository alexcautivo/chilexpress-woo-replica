<?php
/**
 * Isolated CLI probe: same entry as production (wp-admin/admin-ajax.php)
 * with Chilexpress 1.4.0 booting on plugins_loaded.
 */
if ( php_sapi_name() !== 'cli' ) {
	fwrite( STDERR, "CLI only\n" );
	exit( 2 );
}

$home = getenv( 'WP_HOME' );
$host = '127.0.0.1';
$port = '80';
$https = 'off';
if ( is_string( $home ) && $home !== '' ) {
	$p     = wp_parse_url_probe( $home );
	$host  = $p['host'] ?? $host;
	$port  = (string) ( $p['port'] ?? ( ( $p['scheme'] ?? '' ) === 'https' ? 443 : 80 ) );
	$https = ( ( $p['scheme'] ?? '' ) === 'https' ) ? 'on' : 'off';
}
$_SERVER['HTTP_HOST']       = $host . ( ( $port !== '80' && $port !== '443' ) ? ( ':' . $port ) : '' );
$_SERVER['SERVER_NAME']     = $host;
$_SERVER['SERVER_PORT']     = $port;
$_SERVER['REQUEST_URI']     = '/wp-admin/admin-ajax.php';
$_SERVER['SCRIPT_NAME']     = '/wp-admin/admin-ajax.php';
$_SERVER['PHP_SELF']        = '/wp-admin/admin-ajax.php';
$_SERVER['SCRIPT_FILENAME'] = dirname( __DIR__, 3 ) . '/wp-admin/admin-ajax.php';
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['HTTPS']           = $https;

function wp_parse_url_probe( $url ) {
	$p = parse_url( $url );
	return is_array( $p ) ? $p : array();
}

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
ini_set( 'log_errors', '0' );

if ( ! defined( 'WP_DISABLE_FATAL_ERROR_HANDLER' ) ) {
	define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
}

register_shutdown_function(
	static function () {
		$e = error_get_last();
		if ( ! is_array( $e ) || empty( $e['message'] ) ) {
			return;
		}
		$line = $e['message'];
		if ( ! empty( $e['file'] ) ) {
			$line .= ' in ' . $e['file'] . ':' . ( $e['line'] ?? 0 );
		}
		fwrite( STDERR, $line . "\n" );
	}
);

require dirname( __DIR__, 3 ) . '/wp-admin/admin-ajax.php';
