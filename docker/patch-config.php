<?php
/**
 * Ajusta wp-config.php dentro del contenedor (WP_HOME, DB_ENGINE).
 */
$f = '/var/www/html/wp-config.php';
if ( ! is_readable( $f ) ) {
	fwrite( STDERR, "wp-config.php no existe\n" );
	exit( 0 );
}
$c    = file_get_contents( $f );
$home = getenv( 'WP_HOME' ) ?: 'http://127.0.0.1:8080';
$c    = preg_replace( "/define\(\s*'WP_HOME'\s*,\s*'[^']*'\s*\)/", "define( 'WP_HOME', '" . $home . "' )", $c );
$c    = preg_replace( "/define\(\s*'WP_SITEURL'\s*,\s*'[^']*'\s*\)/", "define( 'WP_SITEURL', '" . $home . "' )", $c );
if ( getenv( 'DB_ENGINE' ) === 'mysql' ) {
	$host = getenv( 'WORDPRESS_DB_HOST' ) ?: 'db';
	$c    = preg_replace( "/define\(\s*'DB_ENGINE'\s*,\s*'[^']*'\s*\)/", "define( 'DB_ENGINE', 'mysql' )", $c );
	$c    = preg_replace( "/define\(\s*'DB_HOST'\s*,\s*'[^']*'\s*\)/", "define( 'DB_HOST', '" . $host . "' )", $c );
}
file_put_contents( $f, $c );
echo "wp-config: WP_HOME={$home}\n";
