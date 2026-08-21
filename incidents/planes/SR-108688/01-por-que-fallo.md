# SR-108688 — por qué falló (explicación explícita)

Ticket: **SR-108688** · Sitio: [celularesenventa.cl](https://celularesenventa.cl)  
Plan: esta carpeta. Ticket JSON: [`../../tickets/SR-108688.json`](../../tickets/SR-108688.json)

**Para qué sirve este archivo:** que cualquier persona (dueño de tienda, soporte, Chilexpress) entienda *el orden de los hechos*, no solo el nombre de una clase PHP.

---

## 1. Lo que vio el cliente

WordPress mandó un correo de **error crítico**. El sitio podía quedar en blanco o con “Ha habido un error crítico en este sitio web”. La petición que murió fue:

`/wp-admin/admin-ajax.php`

El texto exacto:

```
Class "Automattic\WooCommerce\Enums\ProductTaxStatus" not found
```

Archivo WooCommerce: `includes/abstracts/abstract-wc-shipping-method.php` línea **84**.

Eso **no** significa “WooCommerce 11 está roto” ni “Chilexpress 1.4.0 no sirve con Woo 11”. Significa: **en el instante del fallo, PHP ejecutó código de Woo 11 que necesita un archivo que todavía no estaba disponible**.

---

## 2. Línea de tiempo (por eso “solo al actualizar”)

| Momento | Qué hay en disco | Qué hace Chilexpress 1.4.0 | Resultado |
|---|---|---|---|
| A | WooCommerce 10.x completo | Arranca en `plugins_loaded`, hace `require_once` de las abstractas de envío | Sitio OK (esa abstracta aún no usa el enum) |
| B | El actualizador de WordPress **copia archivos uno a uno** | Chilexpress **sigue activo** | Ventana peligrosa |
| C | Ya está la abstracta **nueva** (línea 84: `ProductTaxStatus::TAXABLE`) | `admin/class-chilexpress-woo-oficial-admin.php` líneas 30–38 hace `require_once` de esa abstracta en **`plugins_loaded`** | PHP **carga** la clase nueva |
| D | **Todavía no** está `woocommerce/src/Enums/ProductTaxStatus.php`, o Woo **aún no** registró su autoloader | El enum no existe para PHP | **Fatal.** Cae `admin-ajax.php` y cualquier página que cargue plugins |
| E | Woo 11.0.1 **completo** + autoloader listo | El mismo `require_once` | Sitio **OK**. Por eso “después a veces vuelve” |

El laboratorio confirma E: con Woo 11.0.1 intacto, Chilexpress 1.4.0 **no tira** el sitio. El corte es la ventana B–D.

---

## 3. Por qué Chilexpress dispara el fatal (causa raíz)

En `chilexpress-oficial/admin/class-chilexpress-woo-oficial-admin.php` (1.4.0), al **incluir** el archivo del plugin (hook **`plugins_loaded`**):

```php
require_once .../woocommerce/includes/abstracts/abstract-wc-shipping-method.php
```

Eso no espera a Woo. WooCommerce 11, al parsear esa abstracta, **usa** `ProductTaxStatus`. Esa clase la carga el autoloader de Woo **cuando Woo ya inicializó**.

El propio Chilexpress **sí** registra el método de envío más tarde (`woocommerce_shipping_init`) en otro archivo. El daño lo hace este `require_once` **prematuro**.

No es Woodmart. No son las API keys. No es PHP 8.4 “incompatible”. No hay que bajar Woo a una versión vieja.

---

## 4. Cómo lo sabemos (réplica, sin parchear el ZIP)

Pila igual a producción: PHP **8.4.19**, WordPress **7.0.3**, Woo **11.0.1**, Chilexpress **1.4.0**, Woodmart Child **1.0.0**.

1. Woo completo → no fatal.
2. Consola → Ticket → simula el update (el enum no está, la ruta sí) → llama `admin-ajax.php` → **mismo** E_ERROR.
3. Restaura el enum. Chilexpress 1.4.0 **no se edita**.

---

## 5. Qué archivo sigue (acción)

- Hoy, sin esperar a Chilexpress: [`02-plan-accion.md`](02-plan-accion.md)
- Arreglo de fondo en el plugin oficial: [`03-mejoras-plugin-chilexpress.md`](03-mejoras-plugin-chilexpress.md)
