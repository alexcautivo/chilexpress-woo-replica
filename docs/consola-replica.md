# Consola réplica — cómo funciona cada pieza

La **Consola réplica** es la barra negra fija en el borde inferior (front, wp-admin y login). Un clic la abre. Es el tablero de esta app de pruebas.

Autor: **Alexander Alejandro Cautivo Ramos** · [alexander.cautivo@aeolabs.io](mailto:alexander.cautivo@aeolabs.io)

Código: `wordpress/wp-content/mu-plugins/cxp-debug-console.php` más paneles enganchados al hook `cxp_debug_console_panels`.

---

## Barra compacta (siempre visible)

| Elemento | Significado |
|---|---|
| **Consola réplica** | Título. Clic abre/cierra el panel |
| Aeolabs · Alexander Cautivo | Crédito del laboratorio |
| `PHP · WP · WC · Chilexpress · tema` | Pila de *esta* petición |
| Badge rojo / verde | Errores PHP capturados en esta página |
| Líneas de `debug.log` | Cola del log de WordPress |

Si el badge está rojo, abre la consola y mira **ERRORES DE ESTA PETICIÓN** y la cola de `debug.log`.

---

## Atajos de la fila superior

| Botón | Qué hace |
|---|---|
| **Pedidos locales (Generar OTs)** | Lista HPOS `/wp-admin/admin.php?page=wc-orders` |
| **Pedidos tienda remota** | Solo si definiste `CXP_REMOTE_SHOP_URL` |
| **Tienda** | Catálogo (peso, medidas, cantidad 1–10) |
| **Buscar** | Enfoca el buscador del encabezado. En la tienda, el botón **Buscar** tiene una **i** al lado que explica el flujo |
| **Checkout** | Checkout clásico + destinos reales RM + cotizador |
| **Carrito** | Solo después de visitar el checkout |
| **Copiar todo** | Informe listo para pegar en el ticket (versiones, plugins, logs) |
| **Copiar solo versiones** | El bloque de pila, sin logs |
| **Copiar todos los plugins** | Inventario nombre + versión + activo/mu |
| **Borrar todos los pedidos** | Definitivo (sin papelera). Pide confirmación |
| **Cerrar** | Pliega la consola |

## Pestaña Incidencias

Cualquier cliente, no solo SR-108688. Carpeta del repo: `incidents/`.

| Botón | Qué hace |
|---|---|
| **Copiar JSON para el cliente** | Plantilla `incidents/templates/para-el-cliente.json` |
| **Crear ticket con este JSON** | Valida y guarda `incidents/tickets/{id}.json` |
| **Ver JSON** | Abre un ticket ya creado |

El plan explícito de SR-108688 (por qué falló, qué hacer hoy, mejoras al plugin Chilexpress) está en `incidents/planes/SR-108688/`.

Abajo, la tabla **Pedidos** lista hasta 100 órdenes con **Detalle**, **Generar OT** y **Borrar**.

Para OT: entra a **Editar** el pedido o marca el checkbox y usa el flujo oficial «Generar Multiples OT». No uses Acciones masivas de Woo sin marcar filas.

---

## Panel — Documentos para el cliente

MU-plugin: `cxp-client-docs.php`. Lee Markdown de `/docs` (o `CXP_DOCS_DIR`) y sustituye `{{WP_VERSION}}`, `{{WC_VERSION}}`, etc.

| Botón | Archivo |
|---|---|
| Identificación del problema | `docs/cliente-identificacion.md` |
| Diagnóstico técnico | `docs/cliente-diagnostico.md` |
| Respuesta al cliente | `docs/cliente-respuesta.md` |
| Instrucciones detalladas | `docs/cliente-instrucciones.md` |
| FAQ | `docs/faq-replica.md` |
| Guía de uso del laboratorio | `docs/guia-de-uso.md` |
| Manual de la consola | `docs/consola-replica.md` |
| Despliegue en Dokploy | `docs/dokploy.md` |
| Copiar / descargar pack | Todos concatenados |

Cada «Descargar» usa `admin-post.php?action=cxp_docs_download` (hace falta sesión de administrador si el auto-login está apagado).

---

## Panel — Pila / versiones (simular deploy)

MU-plugin: `cxp-stack-versions.php`. Caja verde/amarilla arriba de la consola (prioridad 2).

| Control | Efecto |
|---|---|
| Select **PHP** + **Preparar esta PHP** | Descarga NTS x64 a `runtime/php-VER/` y escribe `runtime/.php-version`. **Hay que reiniciar** `start.sh` (PHP no cambia en caliente). En Docker: `PHP_VERSION` en `.env` + rebuild |
| Select WordPress + **Instalar esta WordPress** | Baja el ZIP de wordpress.org y pisa el core. No toca `wp-content`. Recarga |
| Tabla por plugin (Woo, SQLite, Akismet, …) | Select de versión + campo “otra” + **Instalar esta versión** |
| **Restaurar 1.4.0** (Chilexpress) | Copia intacta desde `chilexpress-oficial/` del repo. No se parchea PHP |
| Otro plugin (slug wordpress.org) | Instala cualquier plugin público por slug + versión |
| **Recargar WordPress completo** | Reinstala el core (versión del selector) **y** todos los plugins de la tabla. Conserva uploads/DB. Recarga el navegador |
| **Volver a pila del cliente** | WP **7.0.3** + Woo **11.0.1** + Chilexpress **1.4.0** + Woodmart Child |
| **Correo de laboratorio** | Cambia el mail de admin WP, usuario `admin`, Woo from/stock, remitente Chilexpress y prefill del checkout |

PHP **no** se cambia aquí. En Docker: `PHP_VERSION` en `.env` y rebuild. En Windows local: carpeta `runtime/php-8.4.19/`.

Tras un cambio, espera el mensaje y recarga. Si el sitio queda a medias, el botón de pila del cliente vuelve al stack de SR-108688.

---

## Panel — SR-108688 (replicar la caída)

MU-plugin: `cxp-sr108688-repro.php`.

| Botón | Qué hace |
|---|---|
| Replicar caída exacta | Oculta/stub `woocommerce/src/Enums/ProductTaxStatus.php` y pega `admin-ajax.php` (como el correo) |
| Dejar el sitio caído | Deja el enum incompleto para inspeccionar |
| Restaurar enum | Copia de vuelta el archivo bueno |

Emergencia si el admin no carga: `https://TU-HOST/__sr108688/restore`

Esto imita la **ventana de update in-place** de Woo. No modifica Chilexpress.

Causa real (resumen): Chilexpress en `plugins_loaded` hace `require_once` de `abstract-wc-shipping-method.php`. Woo 11 ahí usa `ProductTaxStatus::TAXABLE`. Si el enum aún no está en disco, PHP muere. Fix recomendado *para el plugin* (no aplicado): arrancar en `woocommerce_loaded`. Mitigación operativa: desactivar Chilexpress, actualizar Woo, reactivar.

---

## Panel — Laboratorio de plugins

MU-plugin: `cxp-plugin-lab.php`.

| Acción | Uso |
|---|---|
| Snapshot | Copia Woo o Chilexpress a `wp-content/cxp-snapshots/` |
| Rollback | Restaura un snapshot |
| Activar / desactivar | Solo esos dos plugins |
| Subir ZIP | Instala un ZIP (p. ej. otra build de Woo) |
| drop-plugins/ | ZIPs que dejas en esa carpeta del repo |
| Modo seguro | Deja activos Woo + Chilexpress + SQLite |

Chilexpress **no se edita** desde aquí; solo se instala, snapshot o rollback del árbol.

---

## Panel — APIs Chilexpress

MU-plugin: `cxp-chilexpress-apis.php`.

Muestra si Cobertura / Cotizador / OT están habilitados y si hay key (solo últimos 4 caracteres).

| Botón | Uso |
|---|---|
| Guardar keys pegadas | Escribe lo que pegas (campos vacíos no pisan) |
| Cargar keys del entorno | Lee `CXP_API_KEY_GEO`, `CXP_API_KEY_RATE`, `CXP_API_KEY_OT` (Dokploy) |
| Cargar defaults del plugin 1.4.0 | Las keys de ejemplo que trae el ZIP oficial |

Ambiente: **staging** para pruebas. Las llamadas salen desde PHP (`[CXP HTTP]` en `debug.log`), no desde el Network del navegador.

---

## Panel — Créditos

MU-plugin: `cxp-about.php`. Autor del laboratorio, correo Aeolabs y recordatorio de que Chilexpress 1.4.0 permanece intacto.

---

## Tabla de plugins (final de la consola)

Inventario de plugins, MU-plugins y drop-ins. Copia uno o todos. Sirve para contrastar con el correo de WordPress del cliente.

El bloque **Pegar en el ticket / chat** es el informe completo (pila, entorno, plugins, errores, cola de log).

---

## Seguridad de la consola en Internet (Dokploy)

En localhost el laboratorio entra solo a wp-admin (`CXP_AUTO_LOGIN` implícito).

En un dominio público:

1. `CXP_AUTO_LOGIN=0`
2. Entra con `admin` y la clave que hayas puesto
3. Las acciones destructivas (borrar pedidos, cambiar WP, romper el enum) exigen capacidad `manage_options`

No dejes auto-login encendido en un deploy público.
