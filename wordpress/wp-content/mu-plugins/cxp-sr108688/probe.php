<?php
/**
 * Isolated CLI probe: same entry as production (wp-admin/admin-ajax.php)
 * with Chilexpress 1.4.0 booting on plugins_loaded.
 */
if ( php_sapi_name() !== 'cli' ) {
	fwrite( STDERR, "CLI only\n" );
	exit( 2 );
}

$_SERVER['HTTP_HOST']       = '127.0.0.1:8080';
$_SERVER['SERVER_NAME']     = '127.0.0.1';
$_SERVER['SERVER_PORT']     = '8080';
$_SERVER['REQUEST_URI']     = '/wp-admin/admin-ajax.php';
$_SERVER['SCRIPT_NAME']     = '/wp-admin/admin-ajax.php';
$_SERVER['PHP_SELF']        = '/wp-admin/admin-ajax.php';
$_SERVER['SCRIPT_FILENAME'] = dirname( __DIR__, 3 ) . '/wp-admin/admin-ajax.php';
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['HTTPS']           = 'off';

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
ini_set( 'log_errors', '0' );

if ( ! defined( 'WP_DISABLE_FATAL_ERROR_HANDLER' ) ) {
	define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
}

require dirname( __DIR__, 3 ) . '/wp-admin/admin-ajax.php';
