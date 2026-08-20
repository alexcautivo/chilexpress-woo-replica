# Laboratorio WordPress + WooCommerce + Chilexpress Oficial

Réplica de prueba del ticket **SR-108688** (tienda de producción [celularesenventa.cl](https://celularesenventa.cl)).

Fatal:

```
Class "Automattic\WooCommerce\Enums\ProductTaxStatus" not found
```

Este repositorio **no parchea** el PHP de Chilexpress Oficial 1.4.0. Es un laboratorio para reproducir el update a medias, cotizar en staging, generar OT y redactar la respuesta al cliente.

---

<p align="center">
  <strong>Autor y desarrollador</strong><br>
  Alexander Alejandro Cautivo Ramos<br>
  Aeolabs · <a href="mailto:alexander.cautivo@aeolabs.io">alexander.cautivo@aeolabs.io</a>
</p>

Repo: https://github.com/alexcautivo/chilexpress-woo-replica

---

## Documentación

| Documento | Contenido |
|---|---|
| [Guía de uso](docs/guia-de-uso.md) | Cómo se usa esta app de pruebas, de punta a punta |
| [Consola réplica](docs/consola-replica.md) | Cada botón de la barra inferior |
| [Dokploy](docs/dokploy.md) | Deploy manual del contenedor |
| [Identificación](docs/cliente-identificacion.md) | Texto para el ticket |
| [Diagnóstico](docs/cliente-diagnostico.md) | Causa técnica |
| [Respuesta al cliente](docs/cliente-respuesta.md) | Carta |
| [Instrucciones](docs/cliente-instrucciones.md) | Qué hacer en producción |
| [FAQ](docs/faq-replica.md) | Situaciones que salieron al montar la réplica |
| [Autores](AUTHORS.md) | Créditos |
| [Credenciales de laboratorio](CREDENTIALS.md) | `admin` / `admin` (sin secretos de APIs) |

Los mismos Markdown se generan desde la consola (panel **Documentos**).

---

## Pila del cliente (default)

| Componente | Versión |
|---|---|
| PHP | **8.4.19** |
| WordPress | **7.0.3** |
| WooCommerce | **11.0.1** |
| Chilexpress Oficial | **1.4.0** (ZIP intacto) |
| Tema | **Woodmart Child 1.0.0** (padre Woodmart 8.5.7) |
| Idioma / moneda | es_CL / CLP |
| Unidades | kg / cm |

Si la consola muestra otra WP o Woo: **Volver a versiones del cliente**.

---

## Acceso local

| | |
|---|---|
| Sitio | http://127.0.0.1:8080 |
| Admin | http://127.0.0.1:8080/wp-admin/ |
| Usuario | `admin` |
| Contraseña | `admin` |
| Auto-login | solo en localhost, o si `CXP_AUTO_LOGIN=1` |

Checkout de prueba: Juan Espoz · RM · **LA REINA** · Avenida Larrain **5862** · tarjeta `4242…4242` / 12/34 / 123. Botón **Llenar datos válidos**.

Las subscription keys de Chilexpress **no van en el repo**. Pégalas en la consola o en variables `CXP_API_KEY_GEO`, `CXP_API_KEY_RATE`, `CXP_API_KEY_OT`.

---

## Por qué ocurría el error

Chilexpress Oficial 1.4.0, en `admin/class-chilexpress-woo-oficial-admin.php` líneas 30–38, hace `require_once` de `abstract-wc-shipping-method.php` al arrancar en **`plugins_loaded`**.

WooCommerce **11** en esa clase (línea 84) usa `ProductTaxStatus::TAXABLE`. El enum vive en `woocommerce/src/Enums/ProductTaxStatus.php` y lo carga el autoloader **cuando Woo ya inicializó**.

Durante un **update in-place** los archivos se copian a medias. Chilexpress sigue activo, abre la clase abstracta nueva, el enum aún no está, y PHP muere en `/wp-admin/admin-ajax.php`.

Con Woo 11.0.1 **completo**, el mismo Chilexpress **no cae**.

Fix de una línea (para Chilexpress, **no aplicado** aquí): arrancar en `woocommerce_loaded`. Mitigación: **desactivar Chilexpress, actualizar Woo, reactivar**.

En la réplica: consola → **SR-108688** → Replicar caída exacta. Emergencia: `/__sr108688/restore`

---

## Cómo correr en local (sin Docker)

```bash
git clone https://github.com/alexcautivo/chilexpress-woo-replica.git
cd chilexpress-woo-replica
cp wordpress/wp-config.sample.php wordpress/wp-config.php
bash start.sh
```

`start.sh` espera PHP 8.4.19 en `runtime/php-8.4.19/` (Windows). En Linux/macOS:

```bash
cp wordpress/wp-config.sample.php wordpress/wp-config.php
php install-wp.php
cd wordpress && php -S 127.0.0.1:8080 router.php
```

Admin: `/wp-admin/` **con barra final**.

---

## Docker local

```bash
cp .env.example .env
docker compose up --build
```

| Variable | Default | Qué hace |
|---|---|---|
| `PHP_VERSION` | `8.4.19` | Tag `php:{versión}-apache` |
| `WORDPRESS_PORT` | `8080` | Puerto host |
| `WP_HOME` | `http://127.0.0.1:8080` | URL pública |
| `DB_ENGINE` | `sqlite` | `sqlite` o `mysql` |
| `CXP_AUTO_LOGIN` | `1` | Auto-login admin (apaga en Internet) |

MySQL: `DB_ENGINE=mysql` y `docker compose --profile mysql up --build`.

---

## Dokploy (manual)

Usa **`docker-compose.dokploy.yml`**. Puerto interno **80**. `WP_HOME=https://tu-dominio`. `CXP_AUTO_LOGIN=0`.

Guía completa: [docs/dokploy.md](docs/dokploy.md).

---

## Consola réplica (barra inferior)

Clic en la barra negra de abajo. Manual: [docs/consola-replica.md](docs/consola-replica.md).

| Panel | Para qué |
|---|---|
| **Documentos** | Identificación, diagnóstico, respuesta, instrucciones, FAQ, guía, consola, Dokploy |
| **Pila / versiones** | Instalar otra WP/Woo, restaurar Chilexpress 1.4.0, pila del cliente |
| **SR-108688** | Replicar el fatal y restaurar el enum |
| **Laboratorio de plugins** | ZIP, snapshot, rollback |
| **APIs Chilexpress** | Keys por entorno o pegadas (no se imprimen en el HTML) |
| **Pedidos** | Borrar, Generar OT |
| **Créditos** | Autor del laboratorio |

---

## Qué no se versiona

- `runtime/` (PHP portable Windows)
- `wordpress/wp-content/database/` (SQLite generado)
- `wordpress/wp-config.php` (copia el sample)
- `.env`, `debug.log`, cache, upgrade, snapshots, `*.zip`

Las API keys y contraseñas de tiendas reales **no pertenecen** a este repo público.

## Reglas

- No modificar PHP de `chilexpress-oficial` hasta que se pida.
- Checkout clásico: no volver a Woo Blocks.
- En un dominio público: `CXP_AUTO_LOGIN=0` y cambia la clave `admin`.

## Licencia

MIT para el laboratorio (MU-plugins, Docker, docs). WordPress, Woo, Woodmart y Chilexpress conservan las suyas. Ver [LICENSE](LICENSE) y [AUTHORS.md](AUTHORS.md).
