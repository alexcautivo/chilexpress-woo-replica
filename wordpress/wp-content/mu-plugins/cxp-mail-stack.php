<?php
/**
 * Plugin Name: Pila del correo SR-108688
 * Version: 1.0.0
 * Description: Activa Woodmart Child 1.0.0 y el título del sitio como en el correo de WordPress al cliente.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'cxp_mail_stack_apply', 5 );

function cxp_mail_stack_apply() {
	if ( get_option( 'cxp_mail_stack' ) === '1' ) {
		return;
	}
	$child = wp_get_theme( 'woodmart-child' );
	if ( $child->exists() ) {
		switch_theme( 'woodmart-child' );
	}
	update_option( 'blogname', 'Celulares, Tablets Rugged y Notebooks Resistentes en Chile' );
	update_option( 'cxp_mail_stack', '1', false );
}
