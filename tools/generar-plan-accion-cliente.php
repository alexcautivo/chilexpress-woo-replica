<?php
/**
 * Genera docs/SR-108688-plan-accion-cliente.pdf
 * Uso: php tools/generar-plan-accion-cliente.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', true );
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $text ) ) );
	}
}

require_once dirname( __DIR__ ) . '/wordpress/wp-content/mu-plugins/cxp-simple-pdf.php';

$out = dirname( __DIR__ ) . '/docs/SR-108688-plan-accion-cliente.pdf';
$pdf = new Cxp_Simple_Pdf( 'SR-108688  |  Plan de accion para el cliente  |  celularesenventa.cl' );

$pdf->heading( 'Que paso y que hay que hacer (lenguaje simple)' );
$pdf->note( 'Tienda: celularesenventa.cl  ·  Ticket: SR-108688  ·  Fecha del problema: 11 de agosto de 2026' );
$pdf->note( 'Preparo: Alexander Alejandro Cautivo Ramos  ·  Aeolabs  ·  alexander.cautivo@aeolabs.io' );
$pdf->spacer( 6 );

$pdf->heading( '1. En una frase', 2 );
$pdf->para( 'La tienda se cayo mientras se actualizaba WooCommerce. El plugin de Chilexpress siguio trabajando a mitad del proceso, cuando WooCommerce todavia no habia terminado de copiar todos sus archivos. No es que Chilexpress 1.4.0 "no sirva" con WooCommerce 11. Cuando la actualizacion termina bien, ambos conviven.' );

$pdf->heading( '2. Que NO es este problema', 2 );
$pdf->bullet( 'No es el tema Woodmart ni un virus.' );
$pdf->bullet( 'No son las claves de Chilexpress ni el cotizador de envios.' );
$pdf->bullet( 'No hay que bajar WooCommerce a una version vieja (eso deja la tienda insegura).' );
$pdf->bullet( 'No hay que editar a mano el plugin Chilexpress en el sitio. Ese cambio lo debe publicar Chilexpress.' );

$pdf->heading( '3. Que hacer ahora (sitio en marcha)', 2 );
$pdf->para( 'Sigan estos pasos en este orden. No hace falta saber programar.' );
$pdf->bullet( '1. En WordPress: Plugins, desactivar "Chilexpress Oficial".' );
$pdf->bullet( '2. Actualizar WooCommerce y esperar a que termine al 100%.' );
$pdf->bullet( '3. Volver a activar Chilexpress Oficial.' );
$pdf->bullet( '4. Probar una compra de prueba (elegir region, comuna y un envio Chilexpress).' );
$pdf->bullet( '5. Si usan ordenes de transporte, generar una OT de prueba (mejor en ambiente de pruebas).' );

$pdf->heading( '4. Si el sitio ya muestra "Ha habido un error critico"', 2 );
$pdf->para( 'No se puede entrar al escritorio. Hay que hacerlo por el hosting (administrador de archivos o FTP), con ayuda de UPMOVIL si hace falta.' );
$pdf->bullet( '1. Entrar a la carpeta wp-content/plugins/.' );
$pdf->bullet( '2. Renombrar chilexpress-oficial a chilexpress-oficial-off (con eso el plugin se apaga).' );
$pdf->bullet( '3. Completar o reinstalar WooCommerce hasta el final.' );
$pdf->bullet( '4. Devolver el nombre original a la carpeta (chilexpress-oficial) y activar el plugin desde WordPress.' );
$pdf->bullet( '5. Probar el checkout como en el punto 3.' );

$pdf->heading( '5. Regla para la proxima actualizacion', 2 );
$pdf->para( 'Antes de actualizar WooCommerce: apagar Chilexpress. Actualizar. Recien despues, volver a encender Chilexpress. No actualicen los dos al mismo tiempo.' );

$pdf->heading( '6. El arreglo de fondo (no lo hagan ustedes)', 2 );
$pdf->para( 'Chilexpress debe publicar una version que espere a que WooCommerce termine de cargar. Hasta que eso salga, usen la regla del punto 5. No parcheen el plugin en produccion: el siguiente update oficial lo borra y se pierde el diagnostico.' );

$pdf->heading( '7. Como saber que quedo bien', 2 );
$pdf->bullet( 'El sitio abre (tienda, carrito y checkout).' );
$pdf->bullet( 'Se puede elegir region, comuna y un envio Chilexpress.' );
$pdf->bullet( 'No vuelve el correo de WordPress con "error critico" al usar el escritorio.' );

$pdf->heading( '8. Lo que confirmo el laboratorio', 2 );
$pdf->para( 'Con WooCommerce ya instalado completo, el sitio no se cae. El corte aparece solo durante la ventana de la actualizacion. El plugin oficial no se modifico.' );
$pdf->spacer( 8 );
$pdf->note( 'Este PDF es para el equipo de la tienda. El informe tecnico queda aparte. Aeolabs no modifica Chilexpress Oficial 1.4.0.' );

$pdf->save( $out );
fwrite( STDOUT, "PDF escrito: {$out}\n" );
if ( ! is_readable( $out ) || filesize( $out ) < 800 ) {
	fwrite( STDERR, "El PDF quedo vacio o ilegible\n" );
	exit( 1 );
}
echo 'Tamano: ' . filesize( $out ) . " bytes\n";
