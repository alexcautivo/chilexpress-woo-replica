# Mejoras propuestas a Chilexpress Oficial (1.4.0 → siguiente versión)

**Para qué sirve este archivo:** backlog concreto para el equipo de Chilexpress (o quien mantenga el plugin). No se aplica en este laboratorio: el ZIP **1.4.0 permanece intacto**.

**Cómo solucionaría el problema:** si el plugin **no incluye** las abstractas de WooCommerce hasta que Woo ya cargó (y el autoloader tiene `ProductTaxStatus`), la ventana del actualizador **deja de ser un fatal**. El cliente puede actualizar Woo con Chilexpress activo, o al menos el heartbeat/`admin-ajax.php` no tumba el sitio.

Causa en 1.4.0: [`01-por-que-fallo.md`](01-por-que-fallo.md). Mitigación operativa mientras tanto: [`02-plan-accion.md`](02-plan-accion.md).

---

## 1. Cambio mínimo (el que cierra SR-108688)

Archivo: `admin/class-chilexpress-woo-oficial-admin.php` (arranque del plugin, hoy en **`plugins_loaded`**).

Hoy (1.4.0, líneas 30–38): `require_once` de `abstract-wc-settings-api.php` y `abstract-wc-shipping-method.php` en cuanto PHP abre el admin class.

**Hacer:**

1. Registrar el bootstrap en **`woocommerce_loaded`** (no en `plugins_loaded`), **o**
2. No hacer esos `require_once` hasta `woocommerce_shipping_init` (el plugin ya usa ese hook para el método de envío).
3. Opcional, cinturón y tirantes:

```php
if ( ! class_exists( \Automattic\WooCommerce\Enums\ProductTaxStatus::class ) ) {
    return;
}
```

antes de instanciar cualquier clase que tire de `WC_Shipping_Method` en Woo 11+.

**Por qué funciona:** Woo ya copió (o al menos ya autoloadó) el enum. El `require_once` de la abstracta deja de ejecutarse a mitad del unzip.

**Qué no hacer:** editar `abstract-wc-shipping-method.php` de Woo. Eso no es un fix del plugin de envíos.

---

## 2. Otras mejoras realistas (misma tienda, otros tickets)

Estas no son el fatal SR-108688, pero salen en réplicas reales. Cada una reduce tickets de “no cotiza / no veo OT / update me tumba”.

| Mejora | Problema que evita | Enfoque |
|---|---|---|
| No hardcodear `ABSPATH . 'wp-content/plugins/woocommerce/...'` | Woo en otra ruta (`wp-content/plugins` custom, composer) | Usar `WC_PLUGIN_FILE` / `WC()->plugin_path()` |
| Checkout: documentar que el JS de comunas usa `#billing_state` / `#billing_city` | Checkout de **bloques**: el cotizador “no llama a la API” | Leer también bloques o declarar incompatibilidad clara |
| Acciones OT en HPOS (editar pedido), no solo listado clásico | “No encuentro Generar OT” | `woocommerce_order_actions` / metabox HPOS |
| Comunas: guardar el **código** Chilexpress (`LARE`) además del nombre | `la-reina` vs `LA REINA` → 0 tarifas | Select de cobertura oficial |
| Número de calle: validar dígitos antes de OT | API OT rechaza | Mensaje en checkout, no fatal |
| Guardas si Woo no está activo | Activar Chilexpress sin Woo | `is_plugin_active` / `class_exists( 'WooCommerce' )` y admin notice |
| Declarar `Requires Plugins: woocommerce` y versión mínima testeada (11.x) | Updates a ciegas | `readme.txt` del plugin |
| Test de humo: `plugins_loaded` con Woo a medias no debe fatalizar | Regresión de este ticket | PHPUnit o smoke en CI contra Woo 11 |

Ninguna de esas sustituye el punto 1. El punto 1 es el que cierra **este** correo de error crítico.

---

## 3. Qué pedirle a Chilexpress (texto corto)

> Chilexpress Oficial 1.4.0 carga `WC_Shipping_Method` en `plugins_loaded` con `require_once` de la abstracta de Woo. En WooCommerce 11 esa abstracta usa `ProductTaxStatus`, que el autoloader de Woo aún no tiene durante un update in-place. Resultado: fatal en `admin-ajax.php`.  
> Pedimos una versión que inicialice el admin/shipping **en `woocommerce_loaded` o `woocommerce_shipping_init`**, sin `require_once` prematuro, y que no fatalice si el enum no existe. No debemos parchear el ZIP en cada tienda: el siguiente update oficial borra el parche.

---

## 4. Qué no hace Aeolabs en este repo

No se modifica `wordpress/wp-content/plugins/chilexpress-oficial/`. La réplica demuestra el fallo y el plan. El código del punto 1 lo publica Chilexpress.

Alexander Alejandro Cautivo Ramos · desarrollador full stack · Aeolabs.io
