# Identificación del problema — SR-108688

Sitio afectado: **celularesenventa.cl**  
Fecha del correo WordPress: **11 de agosto de 2026**  
Réplica local: {{DATE}} · {{SITE_URL}}

## Pila que el cliente reportó (y que usa esta réplica)

| Componente | Versión pedida | Versión en esta réplica |
|---|---|---|
| PHP | 8.4.19 | {{PHP_VERSION}} |
| WordPress | 7.0.3 | {{WP_VERSION}} |
| WooCommerce | 11.0.1 | {{WC_VERSION}} |
| Chilexpress Oficial | 1.4.0 | {{CXP_VERSION}} |
| Tema activo | Woodmart Child 1.0.0 | {{THEME}} |
| Tema padre | Woodmart 8.5.7 | {{PARENT_THEME}} |
| Captura | `/wp-admin/admin-ajax.php` | igual |

Título del sitio en producción: **Celulares, Tablets Rugged y Notebooks Resistentes en Chile**.

## Error exacto

```
Se ha producido un error del tipo E_ERROR en la línea 84 del archivo
…/woocommerce/includes/abstracts/abstract-wc-shipping-method.php.
Uncaught Error: Class "Automattic\WooCommerce\Enums\ProductTaxStatus" not found
```

## Cómo se identifica en 30 segundos

1. El fatal nombra `ProductTaxStatus` y `abstract-wc-shipping-method.php:84`.
2. El stack pasa por `chilexpress-oficial/admin/class-chilexpress-woo-oficial-admin.php` (aprox. línea 116: `new Chilexpress_Woo_Oficial_Shipping_Method()`).
3. Ese archivo hace `require_once` hardcodeado de las clases abstractas de Woo **en `plugins_loaded`**, no en `woocommerce_shipping_init` / `woocommerce_loaded`.
4. WooCommerce 11 declara `public $tax_status = ProductTaxStatus::TAXABLE;` en la clase abstracta. Si el autoload del enum aún no está (ventana de actualización in-place), PHP muere antes de terminar de cargar el plugin.
5. Con WooCommerce 11.0.1 **completo en disco**, el mismo Chilexpress 1.4.0 **no cae**. El sitio estable no es el escenario del fatal; el escenario es el **update a medias**.

## Qué no es

- No es un fallo del tema Woodmart (el error ocurre al cargar el plugin, antes del tema).
- No es un fallo de las API keys de Chilexpress.
- No es un fallo de PHP 8.4 por sí solo: PHP 8.4.19 es el de producción y la réplica lo usa.
- No es que WooCommerce 11 “no tenga” `ProductTaxStatus`: el archivo existe en 11.0.1 intacto (`woocommerce/src/Enums/ProductTaxStatus.php`). Falta **durante** el recambio de archivos.
