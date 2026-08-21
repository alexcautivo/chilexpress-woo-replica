<?php
/**
 * Ajusta wp-config.php dentro del contenedor (WP_HOME, DB_ENGINE, auto-login).
 */
$f = '/var/www/html/wp-config.php';
if ( ! is_readable( $f ) ) {
	fwrite( STDERR, "wp-config.php no existe\n" );
	exit( 0 );
}
$c    = file_get_contents( $f );
$home = getenv( 'WP_HOME' ) ?: 'http://127.0.0.1:8080';
$home = str_replace( array( "\r", "\n", "'" ), '', $home );

$c = preg_replace( "/define\(\s*'WP_HOME'\s*,\s*'[^']*'\s*\)/", "define( 'WP_HOME', '" . $home . "' )", $c, 1, $n_home );
$c = preg_replace( "/define\(\s*'WP_SITEURL'\s*,\s*'[^']*'\s*\)/", "define( 'WP_SITEURL', '" . $home . "' )", $c, 1, $n_site );
if ( ! $n_home ) {
	$c = str_replace( "<?php", "<?php\ndefine( 'WP_HOME', '" . $home . "' );", $c, 1 );
}
if ( ! $n_site ) {
	$c = preg_replace( "/define\(\s*'WP_HOME'/", "define( 'WP_SITEURL', '" . $home . "' );\ndefine( 'WP_HOME'", $c, 1 );
}

if ( getenv( 'DB_ENGINE' ) === 'mysql' ) {
	$host = getenv( 'WORDPRESS_DB_HOST' ) ?: 'db';
	$name = getenv( 'WORDPRESS_DB_NAME' ) ?: 'wordpress';
	$user = getenv( 'WORDPRESS_DB_USER' ) ?: 'wordpress';
	$pass = getenv( 'WORDPRESS_DB_PASSWORD' ) ?: 'wordpress';
	$c    = preg_replace( "/define\(\s*'DB_ENGINE'\s*,\s*'[^']*'\s*\)/", "define( 'DB_ENGINE', 'mysql' )", $c );
	$c    = preg_replace( "/define\(\s*'DB_HOST'\s*,\s*'[^']*'\s*\)/", "define( 'DB_HOST', '" . $host . "' )", $c );
	$c    = preg_replace( "/define\(\s*'DB_NAME'\s*,\s*'[^']*'\s*\)/", "define( 'DB_NAME', '" . $name . "' )", $c );
	$c    = preg_replace( "/define\(\s*'DB_USER'\s*,\s*'[^']*'\s*\)/", "define( 'DB_USER', '" . $user . "' )", $c );
	$c    = preg_replace( "/define\(\s*'DB_PASSWORD'\s*,\s*'[^']*'\s*\)/", "define( 'DB_PASSWORD', '" . $pass . "' )", $c );
}

// CXP_AUTO_LOGIN lo resuelve wp-config.sample.php via getenv().
// No reescribir esa línea: el replace por primer ')' deja PHP inválido.

$c = preg_replace( "/define\(\s*'WP_DEBUG_DISPLAY'\s*,\s*true\s*\)/", "define( 'WP_DEBUG_DISPLAY', false )", $c );

file_put_contents( $f, $c );
echo "wp-config: WP_HOME={$home}\n";
