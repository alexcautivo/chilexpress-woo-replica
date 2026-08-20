<?php
/**
 * Instala WordPress y activa WooCommerce 11.0.1.
 * Uso: php install-wp.php
 */

$_SERVER['HTTP_HOST']       = '127.0.0.1:8080';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['SERVER_NAME']     = '127.0.0.1';
$_SERVER['SERVER_PORT']     = '8080';
$_SERVER['HTTPS']           = 'off';

define( 'WP_INSTALLING', true );

$wp_root = __DIR__ . '/wordpress';
require_once $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

if ( is_blog_installed() ) {
	echo "WordPress ya esta instalado.\n";
} else {
	echo "Instalando WordPress 7.0.3...\n";
	$result = wp_install(
		'Replica Chilexpress SR-108688',
		'admin',
		'admin@local.test',
		true,
		'',
		'admin',
		'en_US'
	);
	if ( is_wp_error( $result ) ) {
		fwrite( STDERR, 'Error de instalacion: ' . $result->get_error_message() . "\n" );
		exit( 1 );
	}
	echo "WordPress instalado. Usuario: admin / Contrasena: admin\n";
}

$woocommerce = 'woocommerce/woocommerce.php';
if ( is_plugin_inactive( $woocommerce ) ) {
	echo "Activando WooCommerce 11.0.1...\n";
	$activated = activate_plugin( $woocommerce, '', false, true );
	if ( is_wp_error( $activated ) ) {
		fwrite( STDERR, 'No se pudo activar WooCommerce: ' . $activated->get_error_message() . "\n" );
		exit( 1 );
	}
	echo "WooCommerce activado.\n";
} else {
	echo "WooCommerce ya estaba activo.\n";
}

$sqlite = 'sqlite-database-integration/load.php';
if ( file_exists( WP_PLUGIN_DIR . '/' . $sqlite ) && is_plugin_inactive( $sqlite ) ) {
	activate_plugin( $sqlite, '', false, true );
}

echo "\n=== Entorno de replica ===\n";
echo 'PHP:         ' . PHP_VERSION . "\n";
echo 'WordPress:   ' . get_bloginfo( 'version' ) . "\n";
if ( defined( 'WC_VERSION' ) ) {
	echo 'WooCommerce: ' . WC_VERSION . "\n";
} else {
	$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $woocommerce, false, false );
	echo 'WooCommerce: ' . ( $data['Version'] ?? 'desconocida' ) . "\n";
}

$chilexpress_dirs = glob( WP_PLUGIN_DIR . '/chilexpress*' );
if ( empty( $chilexpress_dirs ) ) {
	echo "\nFALTA el plugin Chilexpress. Copia el ZIP a drop-plugins/ y ejecuta install-plugin.sh\n";
} else {
	foreach ( $chilexpress_dirs as $dir ) {
		echo 'Chilexpress encontrado: ' . basename( $dir ) . "\n";
	}
}

echo "\nURL:      http://127.0.0.1:8080\n";
echo "Admin:    http://127.0.0.1:8080/wp-admin\n";
echo "Usuario:  admin\n";
echo "Password: admin\n";
