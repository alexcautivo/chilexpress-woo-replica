# Laboratorio de incidencias WordPress + WooCommerce

Réplica local y desplegable para **reproducir fallos de tiendas reales**, compararlos con lo que reportó el cliente y entregar dos PDF (cliente y técnico).

El caso de referencia es el ticket **SR-108688** de [celularesenventa.cl](https://celularesenventa.cl):

```
Class "Automattic\WooCommerce\Enums\ProductTaxStatus" not found
```

Este repo **no parchea** Chilexpress Oficial 1.4.0. Sirve para armar la pila exacta, ejecutar un flujo seguro, ver si el fatal coincide y documentar la causa.

**Autor:** Alexander Alejandro Cautivo Ramos · [Aeolabs.io](https://aeolabs.io) · [alexander.cautivo@aeolabs.io](mailto:alexander.cautivo@aeolabs.io)

Créditos en la tienda, wp-admin, login y consola. Detalle: [AUTHORS.md](AUTHORS.md).  
GitHub: https://github.com/alexcautivo/chilexpress-woo-replica

---

## Cómo funciona ahora

El laboratorio es **una sola WordPress** (`http://127.0.0.1:8080`) con una **consola negra fija** abajo. Desde esa consola se importan incidencias de distintos clientes, se instalan las versiones pedidas y se reproduce el error sin ejecutar PHP ni comandos que mande el cliente.

```
JSON del cliente
    → Crear ticket (solo archivo, no toca el sitio)
    → Vista previa (pila actual vs solicitada)
    → Aplicar pila (snapshot + WordPress/plugins/tema exactos)
    → Ejecutar flujo (Chrome + HTTP + debug.log)
    → Comparar reportado vs real
    → PDF cliente  /  PDF técnico
    → Restaurar snapshot
```

Crear el ticket **nunca instala ni borra nada**. El sitio cambia solo cuando confirmas **Aplicar pila**.

---

## Pila por defecto (SR-108688)

| Componente | Versión |
|---|---|
| PHP | **8.4.19** |
| WordPress | **7.0.3** |
| WooCommerce | **11.0.1** |
| Chilexpress Oficial | **1.4.0** (ZIP intacto del repo) |
| Tema | **Woodmart Child 1.0.0** (padre Woodmart 8.5.7) |
| Idioma / moneda | es_CL / CLP |
| Unidades | kg / cm |

Si la consola muestra otra pila: **Laboratorio → Volver a pila del cliente**.

---

## Acceso

| | |
|---|---|
| Sitio | http://127.0.0.1:8080 |
| Admin | http://127.0.0.1:8080/wp-admin/ *(barra final)* |
| Usuario / clave | `admin` / `admin` — [CREDENTIALS.md](CREDENTIALS.md) |
| Auto-login | solo localhost, o `CXP_AUTO_LOGIN=1` |

Checkout clásico (no Woo Blocks). Destinos reales de la RM: **LA REINA**, **NUNOA**, **SANTIAGO CENTRO**, **MAIPU**. Calle de prueba: Av. Larrain **5862**. Tarjeta `4242…4242` / 12/34 / 123.

El carrito no se abre hasta pasar por el checkout. **Usar dirección** llena el formulario; **Cotizar envío** llama al cotizador staging.

Las keys de Chilexpress **no van en git**. Pégalas en la consola o usa `CXP_API_KEY_GEO`, `CXP_API_KEY_RATE`, `CXP_API_KEY_OT`.

---

## Consola réplica

Clic en la barra inferior. En tienda y wp-admin las pestañas cambian. Cada botón tiene una **i** con la explicación. Manual: [docs/consola-replica.md](docs/consola-replica.md).

| Pestaña | Qué hace |
|---|---|
| **Tienda** | Catálogo, buscar, checkout, carrito |
| **Ticket / SR-108688** | Réplica específica del fatal ProductTaxStatus |
| **Incidencias** | Importar JSON, aplicar pila, flujo, comparación, dos PDF |
| **Pedidos y OT** | Lista HPOS, generar OT, borrar |
| **Documentos** | Markdown y pack para el cliente |
| **APIs** | Keys Cobertura / Cotizador / Envíos |
| **Laboratorio** | PHP, WordPress y **cualquier plugin** a la versión que elijas; ZIP local; snapshot; **Recargar WordPress completo**; utilidad **latest** (no usar para reproducir un ticket) |
| **Sistema** | Inventario de plugins y créditos |

### Incidencias (cualquier cliente)

1. **Copiar JSON para el cliente** — plantilla schema **1.1** (`incidents/templates/para-el-cliente.json`).
2. El cliente rellena PHP, WordPress, tema, plugins (`slug` + `version` + `activo` + `fuente`), error exacto, URL y pasos.
3. **Crear ticket** — queda en `incidents/tickets/{id}.json`.
4. **Vista previa** — diferencia entre lo instalado y lo pedido.
5. **Crear flujo** — pasos declarativos seguros (GET/AJAX, activar plugin, assertions). Nunca corre código del cliente.
6. **Aplicar pila** — snapshot de core, plugins, temas, `wp-config` y SQLite; instala versiones exactas desde wordpress.org o ZIP en `drop-plugins/`. Chilexpress sale del árbol `chilexpress-oficial/` del repo.
7. Si PHP no coincide: la consola prepara el runtime y pide **reiniciar** `start.sh` o rebuild Docker. Luego **Aplicar pila** de nuevo (mismo run).
8. **Ejecutar flujo** — abre Chrome, captura HTTP, JS y `debug.log`, compara con el error reportado (`coincide` / `parcial` / `no_coincide` / `no_reproducible`).
9. **PDF cliente** (lenguaje simple) y **PDF técnico** (pila, pasos, logs, reglas).
10. **Restaurar snapshot**. Artefactos en `incidents/runs/{ticket}/{run}/`.

Fuentes de plugin permitidas: `wordpress.org`, `zip_local`, `repo`. Tope: 40 plugins. URLs del flujo solo del mismo sitio del laboratorio.

Contrato: [incidents/schema/incident.schema.json](incidents/schema/incident.schema.json). Guía: [incidents/README.md](incidents/README.md). Ejemplo ficticio: [incidents/tickets/_EJEMPLO-1.1.json](incidents/tickets/_EJEMPLO-1.1.json).

### Por qué fallaba SR-108688

Chilexpress 1.4.0, en `plugins_loaded`, hace `require_once` de `abstract-wc-shipping-method.php`. Woo 11 usa ahí `ProductTaxStatus::TAXABLE`. En un **update in-place** el abstracto nuevo puede existir y el enum todavía no: PHP muere en `admin-ajax.php`.

Con Woo 11.0.1 **completo** el mismo plugin **no cae**. Mitigación operativa: desactivar Chilexpress, actualizar Woo, reactivar. Fix de una línea (para el fabricante, **no aplicado**): arrancar en `woocommerce_loaded`.

Emergencia: `/__sr108688/restore`

Plan: [incidents/planes/SR-108688/](incidents/planes/SR-108688/).

---

## Arranque local (sin Docker)

```bash
git clone https://github.com/alexcautivo/chilexpress-woo-replica.git
cd chilexpress-woo-replica
cp wordpress/wp-config.sample.php wordpress/wp-config.php
bash start.sh
```

`start.sh` usa PHP de `runtime/php-VERSION/` (Windows). Versión: `PHP_VERSION`, o `runtime/.php-version` (consola → **Preparar esta PHP**), o **8.4.19**.

```bash
PHP_VERSION=8.3.33 bash start.sh
# o
bash tools/fetch-php.sh 8.3.33
bash start.sh
```

Admin: `/wp-admin/` **con barra final**.

Comprobaciones del laboratorio de incidencias (sitio en 8080):

```bash
node tools/test-incident-lab.mjs
```

---

## Docker local

```bash
cp .env.example .env
docker compose -f docker-compose.local.yml down --remove-orphans
docker compose -f docker-compose.local.yml up --build --force-recreate -d
```

| Variable | Default | Qué hace |
|---|---|---|
| `PHP_VERSION` | `8.4.19` | Imagen `php:{versión}-apache` |
| `WORDPRESS_PORT` | `8080` | Puerto host |
| `WP_HOME` | `http://127.0.0.1:8080` | URL pública |
| `DB_ENGINE` | `sqlite` | `sqlite` o `mysql` |
| `CXP_AUTO_LOGIN` | `1` | Auto-login (apagar en Internet) |

MySQL: `DB_ENGINE=mysql` y `docker compose -f docker-compose.local.yml --profile mysql up --build`.

---

## Dokploy

Compose: `docker-compose.yml`. Guía: [docs/dokploy.md](docs/dokploy.md).  
Incidencias: variable `CXP_INCIDENTS_DIR=/var/www/incidents`.

---

## Documentación

| Documento | Contenido |
|---|---|
| [Guía de uso](docs/guia-de-uso.md) | Recorrido de una sesión |
| [Consola réplica](docs/consola-replica.md) | Cada botón |
| [Incidencias](incidents/README.md) | JSON 1.1, apply, runs, PDF |
| [Dokploy](docs/dokploy.md) | Deploy |
| [Identificación / diagnóstico / respuesta](docs/cliente-identificacion.md) | Textos SR-108688 |
| [FAQ](docs/faq-replica.md) | Montaje de la réplica |
| [Autores](AUTHORS.md) | Créditos |
| [Credenciales](CREDENTIALS.md) | `admin` / `admin` |

Los mismos Markdown salen del panel **Documentos**.

---

## Qué no va a git

- `wordpress/wp-config.php` (copia el sample)
- `.env`, API keys, contraseñas de tiendas reales
- Snapshots de runs (`incidents/runs/*/*/snapshot/`)

La SQLite de semilla **sí** va en el repo (`wordpress/wp-content/database/.ht.sqlite`): catálogo y Chilexpress, **sin pedidos**. En Dokploy usa un volumen para no pisar datos reales.

---

## Reglas

- No modificar PHP de `chilexpress-oficial` hasta que se pida.
- Checkout clásico: no volver a Woo Blocks.
- En un dominio público: `CXP_AUTO_LOGIN=0` y cambia la clave `admin`.
- El JSON del cliente no ejecuta PHP, shell ni URLs externas.
- **Actualizar a latest** es utilidad de laboratorio, no el camino para reproducir un ticket.

## Licencia

MIT para el laboratorio (MU-plugins, Docker, docs). WordPress, Woo, Woodmart y Chilexpress conservan las suyas. [LICENSE](LICENSE) · [AUTHORS.md](AUTHORS.md).
