# Respuesta para el cliente — SR-108688 (celularesenventa.cl)

Estimado/a,

Revisamos el correo de WordPress del 11 de agosto de 2026 y replicamos el entorno con las **mismas versiones** que indicaron:

- WordPress **7.0.3**
- WooCommerce **11.0.1**
- PHP **8.4.19**
- Chilexpress Oficial **1.4.0**
- Tema activo **Woodmart Child 1.0.0** (padre Woodmart 8.5.7)
- La petición que falló: `/wp-admin/admin-ajax.php`

## Qué significa el error

WordPress reportó:

`Class "Automattic\WooCommerce\Enums\ProductTaxStatus" not found`  
en `woocommerce/includes/abstracts/abstract-wc-shipping-method.php` línea 84.

En WooCommerce 11 esa clase **existe** y es normal. El sitio se cae cuando Chilexpress Oficial 1.4.0 **carga demasiado pronto** una clase interna de WooCommerce, en el momento en que WooCommerce se está actualizando y **aún no terminó de copiar todos los archivos** (en concreto el enum `ProductTaxStatus.php`).

No es un virus, no es el tema Woodmart y no es que PHP 8.4 “no sirva”. Es un orden de carga del plugin Chilexpress 1.4.0 junto al actualizador de WooCommerce.

Con WooCommerce 11.0.1 **ya instalado completo**, Chilexpress 1.4.0 levanta. El hueco peligroso es **durante** la actualización.

## Qué les recomendamos hacer ahora (sin esperar un parche)

**Antes de actualizar WooCommerce:**

1. En Plugins, desactivar **Chilexpress Oficial**.
2. Actualizar WooCommerce.
3. Comprobar que existe el archivo  
   `wp-content/plugins/woocommerce/src/Enums/ProductTaxStatus.php`.
4. Volver a activar Chilexpress Oficial.
5. Hacer un pedido de prueba (región + comuna Chilexpress) y, si aplica, generar una OT en staging.

**Si el sitio ya está en error crítico:**

1. Entrar por FTP o el administrador de archivos del hosting.
2. Renombrar  
   `wp-content/plugins/chilexpress-oficial`  
   a  
   `wp-content/plugins/chilexpress-oficial.off`
3. El admin de WordPress debería volver.
4. Reinstalar/completar WooCommerce 11.0.1.
5. Renombrar la carpeta otra vez a `chilexpress-oficial` y activar el plugin.

## Qué tiene que cambiar Chilexpress (desarrollo)

El plugin 1.4.0 hace `require_once` de las clases abstractas de envío de Woo en `plugins_loaded`. En Woo 11 eso ejecuta código que necesita una clase que Woo todavía no registró.

El cambio correcto es arrancar **después** de Woo (`woocommerce_loaded`) o registrar el método de envío solo en `woocommerce_shipping_init` (el propio plugin ya usa ese hook en otra parte). **No hemos modificado el ZIP oficial 1.4.0**; es un cambio que debe publicar Chilexpress.

## Cómo lo comprobamos

Levantamos una réplica con esas versiones exactas. El sitio estable no cae. Al simular la ventana de update (el archivo del enum aún no está), `admin-ajax.php` reproduce el **mismo** fatal del correo.

Quedamos atentos si necesitan acompañar el próximo update de WooCommerce paso a paso.

Saludos,
