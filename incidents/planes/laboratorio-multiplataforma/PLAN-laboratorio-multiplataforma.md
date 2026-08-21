# Plan maestro — incidencias Chilexpress en más plataformas

**Estado:** borrador. **No implementar** Magento, PrestaShop ni Shopify hasta que Alexander diga qué plan de tienda ejecutar y en qué orden.

Autor: Alexander Alejandro Cautivo Ramos · [Aeolabs.io](https://aeolabs.io)

---

## 1. Qué pediste

Replicar el **probador de WordPress** para **otras tiendas**, con:

1. Incidencias por cliente (JSON copiable, versiones, síntoma, flujo seguro).
2. Simulación **local** y **contenedores Docker** desplegables (Dokploy).
3. Mismos **productos, precios, tarjetas y plantillas de relleno** que el laboratorio Woo.
4. Un plan **por plataforma**, ejecutado solo cuando lo indiques y respondiendo preguntas antes de tocar código.

WordPress **no se sustituye**. Sigue en `wordpress/` + `docker-compose.yml`. Las otras tiendas serían **servicios hermanos**, no un reemplazo.

---

## 2. Qué ya existe (no se tira)

| Pieza | Dónde | Qué reutilizar |
|---|---|---|
| Contrato incidencias 1.1 | `incidents/schema/incident.schema.json` | Extender con `plataforma` (`wordpress` / `magento` / `prestashop` / `shopify`) |
| Plantilla cliente | `incidents/templates/para-el-cliente.json` | Una plantilla por plataforma o un JSON con bloque `pila` distinto |
| Consola + PDFs | MU-plugins `cxp-*` | El **modelo** (preview, apply, flow, PDF cliente/técnico). La UI de Woo no corre dentro de Magento |
| Catálogo wiki | `cxp-wiki-catalog.php` | 5 SKU, CLP, kg/cm |
| Checkout de prueba | `cxp-checkout-debug.php` | Direcciones RM + tarjeta `4242` |
| Keys Chilexpress | env `CXP_API_KEY_*` | Mismo staging `qaservices.wschilexpress.com` |
| Contenedores en el VPS | Dokploy Docker | Ya hay `chilexpress-magento-web` y `chilexpress-prestashop-web` (no forman parte de este repo) |

Las wikis Azure DevOps de Magento / PrestaShop / Shopify **requieren login**. Hasta copiar versiones oficiales del módulo, el plan asume el mismo rol que Chilexpress Oficial 1.4.0 en Woo: **no parchear el módulo de Chilexpress** salvo que lo pidas.

---

## 3. Semilla compartida (todas las tiendas)

Copiar estos datos, no inventar catálogo distinto.

### 3.1 Productos (wiki)

| SKU / slug | Nombre | Precio CLP | Peso kg | L×A×H cm | Caso |
|---|---|---|---|---|---|
| `audifonos-bluetooth` | Audífonos Bluetooth | 19990 | 0,2 | 15×10×5 | envío liviano |
| `teclado-mecanico` | Teclado mecánico | 49990 | 0,9 | 45×15×4 | paquete estándar |
| `monitor-24-pulgadas` | Monitor 24 pulgadas | 129990 | 3,5 | 60×40×15 | volumen medio |
| `notebook-15-pulgadas` | Notebook 15 pulgadas | 549990 | 2,2 | 40×30×8 | valor alto |
| `silla-ergonomica-bulto-grande` | Silla ergonómica | 189990 | 12 | 70×65×30 | bulto grande |

Idioma `es_CL`, moneda **CLP**, unidades **kg / cm**, país **CL**.

### 3.2 Cliente y tarjeta de laboratorio

| Campo | Valor |
|---|---|
| Nombre | Juan Espoz |
| Email | `alexander.cautivo+testwordpress@aeolabs.io` (variante `+testmagento`, `+testprestashop`, `+testshopify`) |
| Teléfono | 912345678 |
| Región | RM |
| Comuna | `LA REINA` (código Chilexpress `LARE`) |
| Calle | Avenida Larrain |
| Número | 5862 (solo dígitos) |
| Tarjeta | `4242 4242 4242 4242` · 12/34 · 123 |
| Alternativa | `4111 1111 1111 1111` |

Otras comunas de relleno automático (mismo set Woo): PROVIDENCIA `PROV`, LAS CONDES, NUNOA, SANTIAGO CENTRO, MAIPU.

Origen tienda (semilla Chilexpress): RM · PROVIDENCIA (`PROV`) · Avenida Providencia 1208, Oficina 302 · TCC `18578680` · RUT `96756430` · ambiente **staging**.

### 3.3 Botón «Usar dirección» / «Llenar checkout»

Cada plataforma debe exponer el equivalente del relleno automático de Woo:

- región + comuna con **nombre Chilexpress**, no slug;
- calle y número separados;
- tarjeta de debug que **nunca cobra**.

---

## 4. Contrato de incidencia común

Hoy el schema 1.1 habla de WordPress (`pila.wordpress`, `plugins[]` con slug de wordpress.org).

Propuesta **cuando se ejecute** (no ahora): `schema_version` `1.2` con:

```json
"plataforma": "magento",
"pila": {
  "php": "8.2.x",
  "runtime": "apache|nginx|fpm",
  "cms": { "nombre": "Magento", "edicion": "open_source", "version": "2.4.x" },
  "modulo_chilexpress": { "nombre": "", "version": "", "fuente": "zip_local|repo|marketplace" }
}
```

| Campo Woo actual | Magento | PrestaShop | Shopify |
|---|---|---|---|
| `pila.wordpress` | Magento Open Source / Adobe Commerce | PrestaShop | plan Shopify + API version |
| `plugins[]` | módulos Composer / Marketplace | módulos `/modules` | apps + theme app extensions |
| `pila.tema` | tema Magento | tema PS | theme + checkout extensibility |
| `flujo_reproduccion` | rutas Magento (`/checkout`, `/rest/V1/…`) | front + `admin-ajax` equivalente | Storefront / checkout / Admin GraphQL |
| `fuente` wordpress.org | packagist / zip | Addons / zip | Partner dashboard / zip local |

Reglas que **no** cambian:

- El JSON del cliente **no ejecuta** PHP, shell ni URLs externas.
- Snapshot antes de aplicar pila.
- Dos PDF: cliente (simple) y técnico (stack, logs, versiones) para el equipo Chilexpress.
- Chilexpress **no se parchea** en el laboratorio.

---

## 5. Arquitectura de repo (propuesta, sin crear carpetas de código aún)

```
labs/
  wordpress/          ← lo de hoy (o se deja donde está)
  magento/            ← compose + seed + módulo intacto
  prestashop/
  shopify/            ← simulación (Shopify no corre core on-prem)
incidents/
  schema/
  templates/para-el-cliente-{plataforma}.json
  tickets/
  planes/laboratorio-multiplataforma/   ← este plan
```

Hasta que ejecutes un plan, **no se crean** `labs/magento` ni compose extra. WordPress permanece en la raíz actual para no romper Dokploy.

Un compose por plataforma (puertos distintos en local):

| Plataforma | Puerto local propuesto | Compose |
|---|---|---|
| WordPress | 8080 | `docker-compose.local.yml` (ya existe) |
| Magento | 8081 | futuro `labs/magento/docker-compose.yml` |
| PrestaShop | 8082 | futuro `labs/prestashop/docker-compose.yml` |
| Shopify sim | 8083 | futuro `labs/shopify/docker-compose.yml` |

En Dokploy: **un servicio Compose por plataforma**, mismos volúmenes de datos + `CXP_INCIDENTS_DIR` si el runner vive en un sidecar, o tickets solo en git/`incidents/` hasta tener consola nativa.

---

## 6. Consola y runner

Opciones (pregunta 4):

A. **Sidecar Node** que habla HTTP con cada tienda (recomendado a medio plazo): un solo panel de incidencias.
B. **Consola dentro de cada CMS** (como los MU-plugins Woo): más fiel, más trabajo duplicado.
C. **Solo CLI** (`node tools/run-incident.mjs --platform magento`) al principio.

El flujo a copiar:

```
JSON cliente → ticket → vista previa → aplicar pila
  → ejecutar flujo (Chrome + logs de la plataforma)
  → comparar → PDF cliente / PDF técnico → restaurar snapshot
```

---

## 7. Qué hay hoy en el VPS (contexto, no se toca)

En Dokploy ya corrían (agosto 2026):

- `chilexpress-magento-web` (imagen `chilexpress-magento-test-web`, puerto 80)
- `chilexpress-magento-db` (MySQL 8)
- `chilexpress-magento-search` (OpenSearch)
- `chilexpress-prestashop-web` (`prestashop/prestashop:8-apache`)

Esos contenedores **no están versionados en este repo**. El plan de cada tienda debe decidir: **envolverlos**, **clonar su receta** al repo, o **partir de cero** con semilla wiki.

---

## 8. Orden de ejecución sugerido (tú confirmas)

1. Responder las preguntas de la sección 10.
2. **Magento** (ya hay stack en el VPS; más cercano a un “Woo de Adobe”).
3. **PrestaShop** (imagen oficial Apache; módulo en `/modules`).
4. **Shopify** (el más distinto: no hay PHP core; hay que simular Admin/Storefront o usar tienda de desarrollo).

Cada paso es un PR aparte. Si dices «ejecuta PrestaShop» no se toca Magento.

---

## 9. Fuera de alcance hasta que lo pidas

- Parchear módulos oficiales Chilexpress.
- Unificar las tres tiendas en un solo contenedor.
- Autodeploy de Magento/Presta/Shopify junto al WordPress actual.
- Leer wikis Azure DevOps sin credenciales.

---

## 10. Preguntas (responder antes del primer execute)

1. **¿En qué orden?** Magento → PrestaShop → Shopify, u otro.
2. **¿Los contenedores actuales del VPS se absorben a este repo o se dejan como labs aparte?**
3. **¿Tienes ZIP/versión del módulo Chilexpress** para Magento, PrestaShop y la app Shopify? (equivalente a 1.4.0)
4. **¿Consola?** Sidecar único, consola por CMS, o primero solo CLI.
5. **¿Shopify** es tienda de desarrollo + app custom, o un mock local de checkout/cotizador?
6. **¿Misma SQLite/MySQL por plataforma?** Magento casi siempre MySQL + Elasticsearch/OpenSearch.
7. **¿Dominios Dokploy?** ¿subdominios nuevos (`chilexpress-magento.…sslip.io`) o paths?
8. **¿La plantilla JSON** es un archivo por plataforma o un solo JSON con `plataforma`?
9. **¿La URL Shopify** es exactamente `https://chilexpresscode.visualstudio.com/Shopify`?
10. **¿Puedes pegar** (sin secretos) la tabla de versiones de cada wiki Azure DevOps?

Hasta que contestes y digas **«ejecuta PLAN-magento»** (o el que elijas), esto permanece como documentación.
