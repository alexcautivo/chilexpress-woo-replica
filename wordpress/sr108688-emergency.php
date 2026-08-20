<?php
/**
 * Emergency restore for SR-108688 without loading WordPress.
 */
$enum = __DIR__ . '/wp-content/plugins/woocommerce/src/Enums/ProductTaxStatus.php';
$hid  = $enum . '.cxp-sr108688';
$path = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
$action = 'status';
if ( is_string( $path ) && preg_match( '#/__sr108688/([a-z]+)#', $path, $m ) ) {
	$action = $m[1];
}

header( 'Content-Type: text/plain; charset=UTF-8' );
header( 'Cache-Control: no-store' );

$has_class = static function ( $file ) {
	return is_file( $file ) && false !== strpos( (string) file_get_contents( $file ), 'class ProductTaxStatus' );
};

if ( 'restore' === $action ) {
	if ( is_file( $hid ) ) {
		copy( $hid, $enum );
		unlink( $hid );
	}
	echo $has_class( $enum ) ? "RESTORED ProductTaxStatus.php\nSitio: http://127.0.0.1:8080/wp-admin/\n" : "FAILED: enum class still missing\n";
	exit;
}

echo 'ProductTaxStatus class: ' . ( $has_class( $enum ) ? 'presente' : 'AUSENTE' ) . "\n";
echo 'Backup: ' . ( is_file( $hid ) ? 'sí' : 'no' ) . "\n";
echo $has_class( $enum ) ? "Sitio debería cargar.\n" : "Sitio caído. Abre /__sr108688/restore\n";
exit;
