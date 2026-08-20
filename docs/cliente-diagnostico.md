# Diagnóstico técnico — SR-108688

Réplica: {{SITE_URL}} · {{DATE}}  
PHP {{PHP_VERSION}} · WordPress {{WP_VERSION}} · WooCommerce {{WC_VERSION}} · Chilexpress Oficial {{CXP_VERSION}}

## 1. Qué pasó en producción

Al actualizar WooCommerce **con Chilexpress Oficial 1.4.0 activo**, WordPress envió el correo de error crítico. La petición que falló fue `admin-ajax.php` (misma URL del correo). El admin y el front pueden quedar en “Ha habido un error crítico en este sitio web”.

## 2. Causa raíz (por qué ocurre)

Chilexpress Oficial 1.4.0, archivo `admin/class-chilexpress-woo-oficial-admin.php` líneas 30–38:

```php
if ( ! class_exists( 'WC_Settings_API' ) ) {
    require_once ABSPATH . 'wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-settings-api.php';
}
if ( ! class_exists( 'WC_Shipping_Method' ) ) {
    require_once ABSPATH . 'wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-shipping-method.php';
}
```

Ese archivo se carga cuando el plugin arranca en el hook **`plugins_loaded`**.

En WooCommerce 11, `abstract-wc-shipping-method.php` línea 84 hace:

```php
public $tax_status = ProductTaxStatus::TAXABLE;
```

`ProductTaxStatus` vive en `woocommerce/src/Enums/ProductTaxStatus.php` y lo carga el autoloader de Woo **después** de que Woo terminó de inicializarse.

Durante un **update in-place** de WooCommerce (el propio actualizador de WordPress sustituye archivos uno a uno):

1. Chilexpress sigue activo y se dispara en `plugins_loaded`.
2. El `require_once` abre la clase abstracta nueva (ya copiada).
3. El enum todavía no está en disco, o el autoloader de Woo aún no registró el namespace.
4. PHP lanza el fatal. Toda petición que cargue plugins (incluido `admin-ajax.php`) cae.

El propio plugin Chilexpress **sí** registra el método de envío en `woocommerce_shipping_init` en otro archivo. El problema es este `require_once` prematuro en el admin class.

## 3. Cómo se reprodujo (sin parchear Chilexpress)

En la réplica **no se modifica** el PHP de `chilexpress-oficial`.

1. Pila exacta del cliente: WP **7.0.3**, Woo **11.0.1**, PHP **8.4.19**, Chilexpress **1.4.0**, Woodmart Child **1.0.0**.
2. Con Woo intacto el sitio **no fataliza**. Eso coincide con “después del update, si los archivos están completos, a veces vuelve”.
3. El botón **Replicar caída exacta** de la consola oculta/vacía solo `ProductTaxStatus.php` (simula la ventana del actualizador), llama `admin-ajax.php` y captura el mismo E_ERROR. Luego restaura el archivo.
4. **Dejar el sitio caído como producción** deja el enum oculto para ver la pantalla crítica. Emergencia: `{{SITE_URL}}__sr108688/restore`.

## 4. Fix recomendado a Chilexpress (una línea, aún no aplicado)

Cambiar el arranque de `plugins_loaded` a **`woocommerce_loaded`** (o no hacer `require_once` de las abstractas de Woo hasta `woocommerce_shipping_init`).

Hasta que Chilexpress publique ese cambio, **no parcheamos** el ZIP oficial 1.4.0 en esta réplica.

## 5. Mitigación operativa (sin tocar código del plugin)

Antes de actualizar WooCommerce:

1. Desactivar **Chilexpress Oficial**.
2. Actualizar WooCommerce hasta el final (comprobar que existe `wp-content/plugins/woocommerce/src/Enums/ProductTaxStatus.php`).
3. Volver a activar Chilexpress Oficial.

Si el sitio ya está caído: acceso por FTP/SFTP, renombrar la carpeta `chilexpress-oficial` (queda desactivado), completar/reinstalar Woo 11.0.1, volver a poner la carpeta y activar.

## 6. Hallazgos colaterales de la réplica (no son el fatal SR-108688)

Estos se resolvieron **solo en MU-plugins de la réplica**, no en el plugin oficial:

| Situación | Por qué | Qué se hizo en la réplica |
|---|---|---|
| Tarifas PREX $11.745 / CHEX $8.747 “falsas” | Había un método de envío debug `cxp_debug_cxp` | Se eliminó. Zona Chile solo usa `chilexpress_woo_oficial`. Cotizador staging real: p. ej. teclado PROV→LARE PREX $8.800 / CHEX $5.950 |
| Checkout en bloques no cotizaba | El JS oficial solo habla con `#billing_state` / `#billing_city` vía `admin-ajax.php?action=obtener_comunas_desde_region` | Checkout clásico `[woocommerce_checkout]` |
| Comuna / región mezcladas (Arica + La Reina, Benito Juárez) | Customer session + default R1 del plugin | Prefill RM / LA REINA / Avenida Larrain 5862 y botón **Llenar datos válidos** |
| No se veía “Generar OT” en HPOS | El oficial pone acciones en el listado clásico | MU-plugin añade Generar OT en editar pedido HPOS |
| Correo no sale en `php -S` | El built-in server no tiene mailer | Documentado; no es el ticket |
| Hold de stock SQLite | Woo usa SQL `FOR UPDATE` / `INTERVAL` | Hold minutos = 0 en local |
| Diseño tienda de prueba Chilexpress | Overlay del plugin oficial + Woodmart | CSS/JS storefront Chilexpress sobre Woodmart; checkout clásico se mantiene |

## 7. Conclusión para el ticket

El fatal es una **condición de carrera de carga** entre Chilexpress 1.4.0 (`plugins_loaded` + `require_once` de abstractas Woo 11) y el actualizador de WooCommerce. No hace falta cambiar el tema ni PHP. Hace falta que Chilexpress no instancie `WC_Shipping_Method` antes de `woocommerce_loaded`, y/o desactivar el plugin durante el update.
