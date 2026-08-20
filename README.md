# Réplica local — Chilexpress Oficial + WooCommerce — ticket **SR-108688**

Tienda de producción: **celularesenventa.cl**  
Error: `Class "Automattic\WooCommerce\Enums\ProductTaxStatus" not found`  
Este repo **no parchea** el PHP de Chilexpress Oficial 1.4.0.

Documentos listos para el cliente (también se generan desde la consola inferior):

- [Identificación](docs/cliente-identificacion.md)
- [Diagnóstico técnico](docs/cliente-diagnostico.md)
- [Respuesta al cliente](docs/cliente-respuesta.md)
- [Instrucciones detalladas](docs/cliente-instrucciones.md)
- [FAQ de la réplica](docs/faq-replica.md)
- [Credenciales](CREDENTIALS.md)

GitHub: https://github.com/alexcautivo/chilexpress-woo-replica

---

## Pila que pidió el cliente (esta réplica la usa)

| Componente | Versión |
|---|---|
| PHP | **8.4.19** |
| WordPress | **7.0.3** |
| WooCommerce | **11.0.1** |
| Chilexpress Oficial | **1.4.0** (ZIP intacto) |
| Tema | **Woodmart Child 1.0.0** (padre Woodmart 8.5.7) |
| Idioma / moneda | es_CL / CLP |
| Unidades tienda | kg / cm |

Si la consola muestra otra WP o Woo, pulsa **Volver a versiones del cliente (default)**.

---

## Accesos

### Local

| | |
|---|---|
| Sitio | http://127.0.0.1:8080 |
| Admin | http://127.0.0.1:8080/wp-admin/ |
| Usuario | `admin` |
| Contraseña | `admin` |
| Email | `alexander.cautivo+testwordpress@aeolabs.io` |
| Auto-login wp-admin | sí |

### WordPress publicado (clon de prueba, no es producción)

| | |
|---|---|
| Sitio | https://chilexpress-woo-test.5-78-137-25.sslip.io/ |
| Admin | https://chilexpress-woo-test.5-78-137-25.sslip.io/wp-admin/ |
| Usuario | `admin` |
| Contraseña | `OxFpjdVhI35Aq9d1eHK` |

### APIs Chilexpress (staging, las que usa el local)

Portal: https://developers.wschilexpress.com/new-products  
Header: `Ocp-Apim-Subscription-Key` · base `https://qaservices.wschilexpress.com/`

| Producto | API KEY |
|---|---|
| Cobertura | `a6979b4160c6465f85776f43b6c40ffb` |
| Cotizador | `6a144300d4a54800ad354078c1a536d4` |
| Envíos / OT | `5a77a19b76a24297ba01c158286641b7` |

TCC `18578680` · RUT `96756430` · origen Providencia (`PROV`).

Checkout de prueba: Juan Espoz · RM · **LA REINA** · Avenida Larrain **5862** · tarjeta `4242 4242 4242 4242` / 12/34 / 123. Botón **Llenar datos válidos** en `/checkout/`.

---

## Por qué ocurría el error

Chilexpress Oficial 1.4.0, en `admin/class-chilexpress-woo-oficial-admin.php` líneas 30–38, hace `require_once` de `abstract-wc-shipping-method.php` al arrancar en **`plugins_loaded`**.

WooCommerce **11** en esa clase (línea 84) usa `ProductTaxStatus::TAXABLE`. El enum está en `woocommerce/src/Enums/ProductTaxStatus.php` y lo carga el autoloader de Woo **cuando Woo ya inicializó**.

Durante un **update in-place** de WooCommerce los archivos se copian a medias. Chilexpress sigue activo, abre la clase abstracta nueva, el enum aún no está, y PHP muere:

```
E_ERROR …/abstract-wc-shipping-method.php:84
Uncaught Error: Class "Automattic\WooCommerce\Enums\ProductTaxStatus" not found
```

La petición del correo fue `/wp-admin/admin-ajax.php`. Con Woo 11.0.1 **completo**, el mismo Chilexpress **no cae**.

Fix de una línea (para Chilexpress, **no aplicado** aquí): arrancar en `woocommerce_loaded` en vez de `plugins_loaded`. Mitigación operativa: **desactivar Chilexpress, actualizar Woo, reactivar**.

Cómo reproducirlo en la réplica: consola inferior → **SR-108688** → Replicar caída exacta. Emergencia: http://127.0.0.1:8080/__sr108688/restore

---

## Cómo correr en local (sin Docker)

Hace falta PHP **8.4.19** (en Windows este repo usa `runtime/php-8.4.19/`, no versionado: es el binario portable). Git Bash:

```bash
git clone https://github.com/alexcautivo/chilexpress-woo-replica.git
cd chilexpress-woo-replica
cp wordpress/wp-config.sample.php wordpress/wp-config.php
# Si no tienes runtime/php-8.4.19, instala PHP 8.4.19 NTS y úsalo en start.sh
bash start.sh
```

`start.sh` instala WP si hace falta y sirve en http://127.0.0.1:8080 (`php -S` + `wordpress/router.php`).

Linux/macOS con PHP 8.4.19 del sistema:

```bash
cp wordpress/wp-config.sample.php wordpress/wp-config.php
php install-wp.php
cd wordpress && php -S 127.0.0.1:8080 router.php
```

Admin: `/wp-admin/` **con barra final**.

---

## Cómo correr con Docker (configurable)

```bash
cp .env.example .env
# Edita PHP_VERSION, WORDPRESS_PORT, WP_HOME, DB_ENGINE
docker compose up --build
```

`.env` relevante:

| Variable | Default | Qué hace |
|---|---|---|
| `PHP_VERSION` | `8.4.19` | Tag `php:{versión}-apache` |
| `WORDPRESS_PORT` | `8080` | Puerto host |
| `WP_HOME` | `http://127.0.0.1:8080` | URL pública del contenedor |
| `DB_ENGINE` | `sqlite` | `sqlite` o `mysql` |

MySQL/MariaDB:

```bash
# en .env: DB_ENGINE=mysql
docker compose --profile mysql up --build
```

El contenedor monta `./wordpress` (core 7.0.3 + plugins + temas + MU-plugins). La copia intacta de Chilexpress 1.4.0 está en `./chilexpress-oficial`.

Parar: `docker compose down`. Datos SQLite quedan en `wordpress/wp-content/database/`.

---

## Consola réplica (barra inferior)

Se abre desde abajo en front y admin.

| Panel | Para qué |
|---|---|
| **Documentos para el cliente** | Identificación, diagnóstico, respuesta, instrucciones, FAQ. Copia o descarga `.md` con las versiones actuales |
| **Pila / versiones** | Instalar otra WP o Woo (wordpress.org), restaurar Chilexpress 1.4.0, **Volver a versiones del cliente** (WP 7.0.3 + Woo 11.0.1 + CXP 1.4.0 + Woodmart Child). Recarga al terminar. PHP se cambia en Docker/runtime |
| **SR-108688** | Replicar el fatal, dejar el sitio caído, restaurar el enum |
| **Laboratorio de plugins** | ZIP, snapshot, rollback Woo/Chilexpress |
| Pedidos | Borrar, Generar OT, links locales/remotos |

---

## Situaciones de la réplica (resumen)

Detalle en [docs/faq-replica.md](docs/faq-replica.md).

| Qué se vio | Causa | Solución en la réplica |
|---|---|---|
| PREX $11.745 / CHEX $8.747 | Método fake `cxp_debug_cxp` | Solo método oficial; cotizador staging real |
| Checkout bloques sin comunas | JS oficial = campos clásicos | `[woocommerce_checkout]` |
| Arica + La Reina / Benito Juárez | Sesión + default R1 | Prefill + **Llenar datos válidos** |
| Sin Generar OT en HPOS | UI oficial de listado clásico | MU-plugin en editar pedido |
| Mail local | `php -S` sin MTA | Documentado |
| Stock hold SQLite | SQL de Woo incompatible | Hold = 0 |
| Look Chilexpress antiguo | Overlay + Woodmart | Storefront CSS/JS sin volver a bloques |

Productos wiki (kg/cm): audífonos, teclado, monitor, notebook, silla. Shop: http://127.0.0.1:8080/shop/

---

## Qué no se versiona

- `runtime/` (PHP portable Windows)
- `wordpress/wp-content/database/` (SQLite generado)
- `wordpress/wp-config.php` (copia el sample)
- `debug.log`, cache, upgrade, snapshots

El resto (WordPress 7.0.3, Woo 11.0.1, Chilexpress 1.4.0, Woodmart, MU-plugins, docs, Docker) va en el repo.

## Reglas

- No modificar PHP de `chilexpress-oficial` hasta que se pida.
- El README y `CREDENTIALS.md` incluyen claves del WP publicado a pedido; el repo es privado.
