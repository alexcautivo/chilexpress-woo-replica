<?php
/**
 * Router for PHP built-in server so WordPress permalinks AND wp-admin work.
 *
 * Without this, /wp-admin is a directory and was incorrectly sent to the
 * storefront index.php (the shop), not wp-admin/index.php.
 */
$root = $_SERVER['DOCUMENT_ROOT'];
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
if ( ! is_string( $path ) || $path === '' ) {
	$path = '/';
}
$file = $root . $path;

if ( is_string( $path ) && strpos( $path, '/__sr108688' ) === 0 ) {
	require $root . '/sr108688-emergency.php';
	exit;
}

if ( $path !== '/' && file_exists( $file ) ) {
	if ( is_dir( $file ) ) {
		if ( substr( $path, -1 ) !== '/' ) {
			$query = isset( $_SERVER['QUERY_STRING'] ) && $_SERVER['QUERY_STRING'] !== ''
				? '?' . $_SERVER['QUERY_STRING']
				: '';
			header( 'Location: ' . $path . '/' . $query, true, 302 );
			exit;
		}
	}
	return false;
}

require_once $root . '/index.php';
