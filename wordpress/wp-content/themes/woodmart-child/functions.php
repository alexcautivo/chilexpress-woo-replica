<?php
/**
 * Woodmart Child 1.0.0 — mismo encabezado que celularesenventa.cl / correo SR-108688.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style(
			'woodmart-child-style',
			get_stylesheet_uri(),
			array( 'woodmart-replica-layout' ),
			'8.5.7'
		);
	},
	1000
);
