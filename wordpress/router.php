<?php
/**
 * Router for PHP built-in server so WordPress permalinks work.
 */
$root = $_SERVER['DOCUMENT_ROOT'];
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = $root . $path;

if ( $path !== '/' && file_exists( $file ) && ! is_dir( $file ) ) {
	return false;
}

require_once $root . '/index.php';
