<?php
/**
 * Genera docs/SR-108688-informe-cliente.pdf
 * Uso: php tools/generar-informe-cliente.php
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

$out = dirname( __DIR__ ) . '/docs/SR-108688-informe-cliente.pdf';
$pdf = new Cxp_Simple_Pdf( 'SR-108688  |  Informe tecnico para el cliente  |  celularesenventa.cl' );

$pdf->heading( 'Por que el sitio cayo al actualizar WooCommerce (y por que una replica con las mismas versiones no cae)' );
$pdf->note( 'Ticket SR-108688  ·  Fecha del correo WordPress: 11 de agosto de 2026  ·  Informe: 20 de agosto de 2026' );
$pdf->note( 'Autor del laboratorio: Alexander Alejandro Cautivo Ramos  ·  Aeolabs  ·  alexander.cautivo@aeolabs.io' );
$pdf->spacer( 6 );

$pdf->heading( '1. Resumen ejecutivo', 2 );
$pdf->para( 'El error critico no significa que Chilexpress Oficial 1.4.0 sea incompatible con WooCommerce 11.0.1, ni que PHP 8.4.19 o Woodmart esten rotos. Significa que, durante unos segundos, WooCommerce se estaba actualizando archivo por archivo y Chilexpress Oficial cargo una clase interna de Woo demasiado pronto, cuando todavia faltaba un archivo (el enum ProductTaxStatus).' );
$pdf->para( 'Una replica con WordPress 7.0.3, WooCommerce 11.0.1 COMPLETO, PHP 8.4.19 y el mismo Chilexpress 1.4.0 NO reproduce el fatal: ahi el archivo del enum ya esta en disco. El escenario del cliente es la ventana del update, no el estado estable posterior.' );

$pdf->heading( '2. Error exacto (correo de WordPress)', 2 );
$pdf->para( 'Se ha producido un error del tipo E_ERROR en la linea 84 del archivo woocommerce/includes/abstracts/abstract-wc-shipping-method.php.' );
$pdf->para( 'Uncaught Error: Class "Automattic\\WooCommerce\\Enums\\ProductTaxStatus" not found' );
$pdf->kv( 'Peticion que fallo', '/wp-admin/admin-ajax.php' );
$pdf->kv( 'Sitio', 'celularesenventa.cl' );
$pdf->kv( 'Titulo del sitio', 'Celulares, Tablets Rugged y Notebooks Resistentes en Chile' );
$pdf->spacer( 4 );

$pdf->heading( '3. Pila informada (y usada en la replica)', 2 );
$pdf->kv( 'PHP', '8.4.19' );
$pdf->kv( 'WordPress', '7.0.3' );
$pdf->kv( 'WooCommerce', '11.0.1' );
$pdf->kv( 'Chilexpress Oficial', '1.4.0 (ZIP intacto; no se parchea en el laboratorio)' );
$pdf->kv( 'Tema', 'Woodmart Child 1.0.0 / padre Woodmart 8.5.7' );
$pdf->spacer( 4 );

$pdf->heading( '4. Causa tecnica', 2 );
$pdf->para( 'Chilexpress Oficial 1.4.0 arranca en el hook plugins_loaded. En admin/class-chilexpress-woo-oficial-admin.php, lineas 30-38, hace require_once hardcoded de abstract-wc-settings-api.php y abstract-wc-shipping-method.php, sin esperar a que WooCommerce termine de inicializarse.' );
$pdf->para( 'El propio plugin SI espera el hook correcto (woocommerce_shipping_init) para registrar el metodo de envio. El problema es este require prematuro en la clase de administracion.' );
$pdf->para( 'En WooCommerce 11 esa clase abstracta declara: public $tax_status = ProductTaxStatus::TAXABLE; y importa Automattic\\WooCommerce\\Enums\\ProductTaxStatus. El enum vive en woocommerce/src/Enums/ProductTaxStatus.php y lo carga el autoloader de Woo cuando Woo YA arranco.' );
$pdf->para( 'Al forzar el require_once en plugins_loaded, PHP evalua ProductTaxStatus::TAXABLE sin que el autoloader haya registrado la clase. Si ademas el archivo del enum no esta en disco (update a medias), el resultado es exactamente el E_ERROR del correo.' );

$pdf->heading( '5. Por que al cliente le daba error y a la replica no', 2 );
$pdf->para( 'El actualizador de WordPress no reemplaza un plugin de forma atomica: borra y copia cientos de archivos mientras el sitio sigue recibiendo visitas (admin-ajax.php del escritorio, del tema, etc.).' );
$pdf->bullet( 'En produccion, durante el update: Chilexpress sigue activo. Ya se copio abstract-wc-shipping-method.php NUEVO (con el enum). Todavia NO esta ProductTaxStatus.php. Llega un hit a admin-ajax.php. PHP muere.' );
$pdf->bullet( 'En la replica de laboratorio: WooCommerce 11.0.1 se instalo completo de una vez. ProductTaxStatus.php existe y contiene class ProductTaxStatus. El mismo require_once de Chilexpress encuentra la clase. El sitio no cae.' );
$pdf->para( 'Por eso mismas versiones (WP 7.0.3 + Woo 11.0.1 + Chilexpress 1.4.0 + PHP 8.4.19) pueden verse "bien" en un entorno ya actualizado y "caidas" en el instante del update. No hay contradiccion.' );
$pdf->para( 'Esto tambien explica por que a veces "despues vuelve": cuando el actualizador termina de copiar el enum, el fatal desaparece solo. El riesgo es cualquier peticion que ocurra en esa ventana, y un sitio que quede con archivos a medias si el update se interrumpe.' );

$pdf->heading( '6. Que NO es este error', 2 );
$pdf->bullet( 'No es el tema Woodmart. Chilexpress carga en plugins_loaded, antes del tema.' );
$pdf->bullet( 'No son las API keys de Chilexpress ni el cotizador/OT.' );
$pdf->bullet( 'No es que PHP 8.4 "no sirva": es la version de produccion.' );
$pdf->bullet( 'No es que WooCommerce 11 no tenga ProductTaxStatus: el archivo existe en 11.0.1 intacto. Falta DURANTE el recambio de archivos.' );

$pdf->heading( '7. Como se confirmo en laboratorio (sin parchear Chilexpress)', 2 );
$pdf->para( 'No se modifico el PHP de chilexpress-oficial. Con Woo intacto el sitio no fataliza. El boton "Replicar caida exacta" oculta o vacia solo ProductTaxStatus.php (simula la ventana del actualizador), llama admin-ajax.php y captura el mismo E_ERROR. Luego restaura el archivo. Emergencia del laboratorio: /__sr108688/restore.' );

$pdf->heading( '8. Que hacer ahora en produccion (sin esperar un parche)', 2 );
$pdf->para( 'Antes de actualizar WooCommerce:' );
$pdf->bullet( '1. Desactivar el plugin Chilexpress Oficial.' );
$pdf->bullet( '2. Actualizar WooCommerce hasta el final.' );
$pdf->bullet( '3. Comprobar que existe wp-content/plugins/woocommerce/src/Enums/ProductTaxStatus.php y que contiene "class ProductTaxStatus".' );
$pdf->bullet( '4. Volver a activar Chilexpress Oficial.' );
$pdf->bullet( '5. Probar checkout (region + comuna Chilexpress) y, si aplica, generar una OT en staging.' );
$pdf->para( 'Si el sitio ya muestra "Ha habido un error critico":' );
$pdf->bullet( 'Por FTP o el administrador de archivos, renombrar la carpeta wp-content/plugins/chilexpress-oficial (queda desactivado).' );
$pdf->bullet( 'Completar o reinstalar WooCommerce 11.0.1 hasta que el enum exista.' );
$pdf->bullet( 'Devolver el nombre a la carpeta chilexpress-oficial y activar el plugin.' );

$pdf->heading( '9. Arreglo permanente (para Chilexpress, no aplicado aqui)', 2 );
$pdf->para( 'Cambiar el arranque de plugins_loaded a woocommerce_loaded, o no hacer require_once de las clases abstractas de Woo hasta woocommerce_shipping_init. Es un cambio de una linea en el plugin oficial. Hasta que Chilexpress lo publique, no se parchea el ZIP 1.4.0 en este laboratorio: el diagnostico usa el mismo codigo que produccion.' );

$pdf->heading( '10. Cierre', 2 );
$pdf->para( 'Conclusion: Chilexpress 1.4.0 y WooCommerce 11.0.1 pueden convivir cuando Woo esta completo. El fatal del 11 de agosto es el cruce de un require_once prematuro con un update in-place incompleto. Mitigacion inmediata: desactivar Chilexpress, actualizar Woo, reactivar. Correccion de fondo: que Chilexpress no cargue WC_Shipping_Method antes de que Woo haya inicializado su autoloader.' );
$pdf->spacer( 10 );
$pdf->note( 'Informe elaborado por Alexander Alejandro Cautivo Ramos (Aeolabs) a partir de replica controlada SR-108688. Chilexpress Oficial 1.4.0 se mantuvo intacto.' );
$pdf->note( 'Contacto: alexander.cautivo@aeolabs.io' );

$pdf->save( $out );
fwrite( STDOUT, "PDF escrito: {$out}\n" );
if ( ! is_readable( $out ) || filesize( $out ) < 800 ) {
	fwrite( STDERR, "El PDF quedo vacio o ilegible\n" );
	exit( 1 );
}
echo 'Tamano: ' . filesize( $out ) . " bytes\n";
