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

| | Local | Dokploy (en línea) |
|---|---|---|
| Sitio | http://127.0.0.1:8080 | http://chilexpress-woo.5-78-137-25.sslip.io |
| Admin | http://127.0.0.1:8080/wp-admin/ | http://chilexpress-woo.5-78-137-25.sslip.io/wp-admin/ |
| Usuario / clave | `admin` / `admin` — [CREDENTIALS.md](CREDENTIALS.md) | igual; **cámbiala** en cuanto el sitio sea público |
| Auto-login | solo localhost, o `CXP_AUTO_LOGIN=1` | **apagado** (`CXP_AUTO_LOGIN=0`) |

Checkout clásico (no Woo Blocks). Destinos reales de la RM: **LA REINA**, **NUNOA**, **SANTIAGO CENTRO**, **MAIPU**. Calle de prueba: Av. Larrain **5862**. Tarjeta `4242…4242` / 12/34 / 123.

El formulario de checkout (y los botones **Usar dirección** / **Cotizar envío**) solo aparece **con un producto en el carrito**. Añade algo desde `/shop/` antes de ir a `/checkout/`.

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
| **Laboratorio** | Seis pasos: elegir versiones de WordPress y plugins y **Aplicar cambios** en bloque; instalar por slug; **subir un ZIP**; acciones de pila; PHP; correo |
| **Sistema** | Inventario de plugins y créditos |

### Incidencias (cualquier cliente)

1. **Copiar formulario JSON genérico** — plantilla schema **1.1** (`incidents/templates/para-el-cliente.json`).
2. El cliente rellena PHP, WordPress, tema, **todos los plugins** (`slug` + `version` + `activo` + `fuente`), URL, pasos, resultado esperado y resultado obtenido.
3. **Validar y añadir nuevo ticket** — comprueba que existan las versiones y datos mínimos; queda en `incidents/tickets/{id}.json`.
4. Opcional: **📎 Texto / pantallazo** añade el correo/ticket copiado y una captura PNG/JPG/WEBP. Transcribe o describe el texto visible para incluirlo en el diagnóstico automático.
5. **▶ Probar ticket completo** — en un solo flujo hace vista previa, snapshot, instala la pila exacta, verifica salud, ejecuta los pasos seguros y compara evidencia.
6. Si PHP no coincide, el Play se pausa: reconstruye Docker/reinicia PHP y vuelve a pulsarlo para continuar el mismo run.
7. La salida separa dos conceptos: **prueba ejecutada correctamente** e **incidencia reproducida/no reproducida**, y explica causa probable + cómo corregirla.
8. Descarga **PDF cliente** y **PDF técnico** tanto si apareció el fallo como si no. Ambos incluyen pila solicitada/real, plugins y versiones; el técnico agrega pasos, HTTP, PHP, JS, logs y reglas.
9. **Restaurar snapshot** devuelve core, plugins, temas, `wp-config` y SQLite al estado anterior. Artefactos en `incidents/runs/{ticket}/{run}/`.

Fuentes de plugin permitidas: `wordpress.org`, `zip_local`, `repo`. Tope: 40 plugins. URLs del flujo solo del mismo sitio del laboratorio.

Contrato: [incidents/schema/incident.schema.json](incidents/schema/incident.schema.json). Guía: [incidents/README.md](incidents/README.md). Ejemplo ficticio: [incidents/tickets/_EJEMPLO-1.1.json](incidents/tickets/_EJEMPLO-1.1.json).

### Qué mandarle al cliente

1. Consola → **Incidencias** → **Copiar JSON para el cliente**.
2. En el correo pide: URL y error exacto; PHP y WordPress; tema; **todos los plugins con versión exacta y activo/inactivo**; pasos; resultado esperado/obtenido; correo crítico o `debug.log`.
3. Indica dónde verlo: **WooCommerce → Estado** y **Herramientas → Salud del sitio → Información**.
4. Pide que devuelva el JSON completo y que no incluya contraseñas, API keys ni datos de producción.

Correo listo para copiar, ejemplos de plugins públicos/privados y operación paso a paso: [Guía de incidencias para clientes](docs/guia-incidencias-clientes.md).

### Formas de armar la prueba

- **Fiel al cliente (recomendado):** importa el JSON y pulsa **▶ Probar ticket completo**.
- **Paso a paso:** usa Vista previa → Crear flujo → Aplicar pila → Ejecutar flujo → Resultado.
- **Manual:** en **Laboratorio** cambia WordPress y la versión de cada plugin en la tabla, marca cuáles quedan activos y pulsa **Aplicar cambios**. Para plugins premium, súbelos con **Subir e instalar ZIP**.
- **Actualización general:** **Actualizar a latest**. Sirve para explorar, no para reproducir fielmente un ticket antiguo.

Después, **Ejecutar flujo** compara el fallo y genera:

- **PDF cliente:** explicación simple, impacto y próximos pasos.
- **PDF técnico:** versiones, pasos, stack, logs, reglas y diff para desarrolladores de Chilexpress.

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

Comprobaciones del laboratorio (sitio local en 8080, o Dokploy con `CXP_BASE`):

**PowerShell** (Windows):

```powershell
cd c:\Users\HP\Desktop\wirdscrepss
# Local (Docker o start.sh debe estar arriba):
$env:CXP_BASE = "http://127.0.0.1:8080"
node tools/test-functional.mjs
node tools/test-incident-lab.mjs

# Deploy público:
$env:CXP_BASE = "http://chilexpress-woo.5-78-137-25.sslip.io"
node tools/test-functional.mjs
node tools/test-incident-lab.mjs
```

Checkout en un navegador real (necesita Chrome y `npm install` en `tools/`):

```powershell
cd tools
node test-checkout-fill.mjs
```

**Bash / Git Bash:**

```bash
CXP_BASE=http://127.0.0.1:8080 node tools/test-functional.mjs
CXP_BASE=http://chilexpress-woo.5-78-137-25.sslip.io node tools/test-incident-lab.mjs
```

Las pruebas **no aplican pila** ni reinstalan plugins. En Dokploy, **Aplicar pila** sí cambia el entorno hasta **Restaurar snapshot**. Verificado contra el deploy público (home, shop, checkout con carrito, admin, vista previa SR-108688, PDF cliente y técnico).

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

Dokploy usa tres volúmenes persistentes: SQLite, uploads e incidencias. Un redeploy actualiza código, documentos y contrato JSON sin borrar tickets/runs creados desde la consola.

URL pública: `http://chilexpress-woo.5-78-137-25.sslip.io`

Redeploy: panel Dokploy → **Projects** → **alexwoocommerce** → **chilexpress-woo** → **Deploy**. La pestaña **Docker** solo lista contenedores; no publica código. Tras el deploy, la consola debe mostrar **Incidencias**, **Copiar JSON**, **Vista previa**, **Aplicar pila** y los dos PDF.

---

## Auditor de artefactos (ZIP → PASS/FAIL)

Herramienta separada en [`auditor/`](auditor/). Responde si **un ZIP oficial concreto** es compatible con **una versión concreta** de WooCommerce, PrestaShop, Magento o Shopify.

```bash
cd auditor
node bin/auditor.mjs inspect artifacts/incoming/woocommerce-plugin-1.4.0-RELEASE.zip
node bin/auditor.mjs audit   artifacts/incoming/woocommerce-plugin-1.4.0-RELEASE.zip --platform-version=11.0.1
node bin/auditor.mjs matrix  artifacts/incoming/woocommerce-plugin-1.4.0-RELEASE.zip --versions=9.8.5,10.6.2,11.0.1
```

Calcula SHA-256, detecta plataforma y versión, levanta un lab Docker desechable con versiones fijas, instala el ZIP sin modificarlo, ejecuta rutas críticas y la regresión SR-108688, y entrega `reports/AUDIT-ID/` con exit code. Un fatal nunca termina en 0. La IA es opcional y no puede convertir `FAIL` en `PASS`.

Detalle: [auditor/README.md](auditor/README.md) · [arquitectura](auditor/docs/ARQUITECTURA.md) · [plan Dokploy](auditor/docs/PLAN-DEPLOY-DOKPLOY.md).

---

## Otras plataformas (solo plan)

Magento, PrestaShop y Shopify **no están implementados** en este repo. Hay un plan por tienda, con la misma semilla (5 productos wiki, CLP, kg/cm, LA REINA, Larrain 5862, tarjeta `4242`) y el mismo flujo de incidencias JSON. No se escribe código de esas tiendas hasta que lo indiques.

Carpeta: [incidents/planes/laboratorio-multiplataforma/](incidents/planes/laboratorio-multiplataforma/).

| Plan | Activar con |
|---|---|
| [Magento](incidents/planes/laboratorio-multiplataforma/PLAN-magento.md) | *ejecuta el plan Magento* |
| [PrestaShop](incidents/planes/laboratorio-multiplataforma/PLAN-prestashop.md) | *ejecuta el plan PrestaShop* |
| [Shopify](incidents/planes/laboratorio-multiplataforma/PLAN-shopify.md) | *ejecuta el plan Shopify* |

---

## Documentación

| Documento | Contenido |
|---|---|
| [Guía de uso](docs/guia-de-uso.md) | Recorrido de una sesión |
| [Consola réplica](docs/consola-replica.md) | Cada botón |
| [Incidencias](incidents/README.md) | JSON 1.1, apply, runs, PDF |
| [Guía de incidencias para clientes](docs/guia-incidencias-clientes.md) | Correo, JSON, versiones, ejecución e informes |
| [Plan Magento / PrestaShop / Shopify](incidents/planes/laboratorio-multiplataforma/) | Solo especificación; no ejecutar aún |
| [Auditor de artefactos](auditor/README.md) | ZIP → compatibilidad, matriz, regresiones, IA opcional |
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
- Pantallazos/texto privado del cliente (`incidents/evidence/` y copias del run)

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
