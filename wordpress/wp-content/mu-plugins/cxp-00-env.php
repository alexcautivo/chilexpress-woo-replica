<?php
/**
 * Plugin Name: Entorno de la réplica
 * Version: 1.0.0
 * Description: Lee variables de entorno (Dokploy / Docker / local) sin hardcodear secretos.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function cxp_env( $key, $default = '' ) {
	if ( defined( $key ) ) {
		$const = constant( $key );
		if ( is_bool( $const ) ) {
			return $const ? '1' : '0';
		}
		if ( $const !== '' && $const !== null ) {
			return (string) $const;
		}
	}
	$v = getenv( $key );
	if ( false === $v || '' === $v ) {
		return $default;
	}
	return (string) $v;
}

function cxp_env_bool( $key, $default = false ) {
	if ( false === getenv( $key ) && ! defined( $key ) ) {
		return (bool) $default;
	}
	$v = strtolower( cxp_env( $key, $default ? '1' : '0' ) );
	return in_array( $v, array( '1', 'true', 'yes', 'on' ), true );
}

function cxp_is_local_home() {
	$home = cxp_env( 'WP_HOME', '' );
	if ( $home === '' && function_exists( 'home_url' ) ) {
		$home = home_url( '/' );
	}
	return (bool) preg_match( '#(127\.0\.0\.1|localhost)#i', (string) $home );
}

function cxp_auto_login_enabled() {
	if ( false !== getenv( 'CXP_AUTO_LOGIN' ) && '' !== getenv( 'CXP_AUTO_LOGIN' ) ) {
		return cxp_env_bool( 'CXP_AUTO_LOGIN', false );
	}
	if ( defined( 'CXP_AUTO_LOGIN' ) ) {
		return (bool) CXP_AUTO_LOGIN;
	}
	return cxp_is_local_home();
}

function cxp_remote_shop_url() {
	return untrailingslashit( cxp_env( 'CXP_REMOTE_SHOP_URL', '' ) );
}

function cxp_docs_dir() {
	$candidates = array(
		cxp_env( 'CXP_DOCS_DIR', '' ),
		dirname( ABSPATH ) . '/docs',
		rtrim( ABSPATH, '/\\' ) . '/docs',
		dirname( dirname( ABSPATH ) ) . '/docs',
	);
	foreach ( $candidates as $dir ) {
		if ( $dir && is_dir( $dir ) ) {
			return rtrim( $dir, '/\\' );
		}
	}
	return dirname( ABSPATH ) . '/docs';
}
