<?php
/**
 * Plugin Name: Auto-login admin (réplica local)
 * Version: 1.0.0
 * Description: Entra a wp-admin como admin sin pedir usuario ni contraseña. No afecta el checkout de invitado.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'login_init', 'cxp_auto_login_on_wp_login', 1 );

function cxp_auto_login_user() {
	if ( function_exists( 'cxp_auto_login_enabled' ) && ! cxp_auto_login_enabled() ) {
		return false;
	}
	if ( is_user_logged_in() ) {
		return false;
	}
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return false;
	}
	$user = get_user_by( 'login', 'admin' );
	if ( ! $user || ! user_can( $user, 'manage_options' ) ) {
		return false;
	}
	wp_clear_auth_cookie();
	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, true, is_ssl() );
	return true;
}

function cxp_auto_login_on_wp_login() {
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';
	if ( in_array( $action, array( 'logout', 'lostpassword', 'retrievepassword', 'rp', 'resetpass', 'postpass' ), true ) ) {
		return;
	}
	if ( ! cxp_auto_login_user() ) {
		return;
	}

	$redirect = admin_url( 'admin.php?page=wc-orders' );
	if ( ! empty( $_REQUEST['redirect_to'] ) ) {
		$candidate = wp_sanitize_redirect( wp_unslash( $_REQUEST['redirect_to'] ) );
		if ( $candidate ) {
			$redirect = $candidate;
		}
	}
	wp_safe_redirect( $redirect );
	exit;
}
