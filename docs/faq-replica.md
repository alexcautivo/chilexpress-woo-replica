# Preguntas y situaciones de la réplica (y cómo se resolvió)

Estas dudas salieron al montar la réplica de **celularesenventa.cl** / SR-108688. Ninguna sustituye el fatal principal; son el resto del entorno de prueba.

## ¿Por qué el correo decía WordPress 7.0.3 y el shop de prueba público a veces muestra 7.1?

El **ticket de producción** es WP **7.0.3**. Un clon de prueba aparte puede estar en 7.1. Esta réplica se fija en **7.0.3** como pidió el cliente. En la consola, **Volver a versiones del cliente** reinstala 7.0.3 + Woo 11.0.1 + Chilexpress 1.4.0.

## ¿El tema Woodmart causa el fatal?

No. Chilexpress carga en `plugins_loaded`, antes del tema. Woodmart Child 1.0.0 se replica porque es el tema de producción y el correo lo nombra.

## ¿Hay que parchear Chilexpress 1.4.0?

No en esta réplica. El ZIP se mantiene intacto. El arreglo de una línea (`woocommerce_loaded`) es la recomendación **para Chilexpress**, no un cambio que hayamos aplicado.

## Vi PREX $11.745 y CHEX $8.747 y pensé que las APIs no respondían

Esas cifras las ponía un método de envío **falso** de debug (`cxp_debug_cxp`). Se quitó. El cotizador staging real (teclado 0,9 kg, 45×15×4 cm, Providencia → La Reina) devolvió PREX **$8.800** / CHEX **$5.950**. En `debug.log` aparece `[CXP HTTP] POST https://qaservices.wschilexpress.com/rating/... → 200`.

## El Network del navegador no muestra llamadas a wschilexpress.com

Es normal. El browser llama a `admin-ajax.php` (comunas). PHP en el servidor llama a Chilexpress.

## Checkout en bloques vs clásico

El JS oficial solo rellena selects clásicos. Woo Blocks ignora eso. La réplica fuerza `[woocommerce_checkout]`.

## Región Arica y comuna La Reina / calle Benito Juárez

Sesión vieja + el plugin, si el estado va vacío, cae a **R1**. La réplica rellena RM / LA REINA / Avenida Larrain 5862 y hay botón **Llenar datos válidos**.

## No encuentro Generar OT

En HPOS el listado oficial no siempre muestra el botón. Entrar a **Editar** el pedido o usar la consola réplica → Generar OT. URL: `/wp-admin/admin.php?page=wc-orders&action=edit&id=ID`

## El correo de Woo no llega en local

`php -S` no envía mail. No es el ticket.

## Pedido en espera / stock reservado en SQLite

Woo usa SQL que SQLite no corre. Hold = 0 minutos en local.

## Diseño “Chilexpress antiguo” vs Woodmart de producción

Producción usa Woodmart (barra amarilla de celularesenventa). El HTML de la tienda de prueba Chilexpress es Twenty Twenty-Five + storefront oficial. La réplica aplica topbar negra + header amarillo + logo Chilexpress **encima** de Woodmart, sin volver el checkout a bloques.

## Unidades kg/cm

Chilexpress lee `get_weight()` / `get_length()` en crudo. La tienda wiki está en kg y cm.

## `/wp-admin` sin barra final abría la tienda

El `router.php` del `php -S` trataba el directorio como permalink. Se corrigió: `/wp-admin` redirige a `/wp-admin/`.

## ¿Puedo cambiar versiones para ver cómo se comporta un deploy?

Sí. Consola réplica → **Pila / versiones**: instalar otra WP o Woo desde wordpress.org, restaurar Chilexpress 1.4.0 intacto, o **Volver a versiones del cliente**. Recarga la página al terminar.
