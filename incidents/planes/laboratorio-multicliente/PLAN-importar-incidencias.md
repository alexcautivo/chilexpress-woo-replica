# Plan — laboratorio multi-cliente (JSON → incidencia → pila instalable)

**Estado:** borrador listo. **No ejecutar** hasta que Alexander lo pida expresamente (por ejemplo: *«ejecuta el plan laboratorio-multicliente»*).

**No borrar esta carpeta ni este archivo.** Es la especificación de trabajo; se actualiza cuando respondas las preguntas de la última sección.

Autor del laboratorio: Alexander Alejandro Cautivo Ramos · [Aeolabs.io](https://aeolabs.io)

Ticket de referencia (no se sustituye): [SR-108688](../SR-108688/README.md).

---

## 1. Qué pediste

Poder **agregar más incidencias** de clientes distintos, no solo SR-108688.

1. Un **JSON copiable** que se le pega al cliente para que lo complete.
2. **Importar** ese JSON y **crear una incidencia** nueva en el laboratorio.
3. Que **todos los plugins y versiones** de esa incidencia se puedan importar.
4. Que cada plugin se pueda **poner en la versión del ticket** instalándolo **desde WordPress.org** (el mismo mecanismo que ya usa el panel Pila / versiones).
5. Este plan vive aquí hasta que digas **cuándo** ejecutarlo, y mientras tanto **preguntarte** lo que hace falta para no inventar reglas.

---

## 2. Qué ya existe (no se tira)

Hoy el laboratorio **ya tiene** el esqueleto, pero **no aplica la pila** al importar.

| Pieza | Ruta | Qué hace hoy | Hueco |
|---|---|---|---|
| Plantilla cliente | `incidents/templates/para-el-cliente.json` | Se copia desde la consola | `plugins[]` solo tiene `nombre` + `version` + `activo`. **No hay slug** de wordpress.org. |
| Contrato | `incidents/schema/incident.schema.json` | Valida a mano (schema 1.0) | No exige slug, origen del ZIP, tema descargable ni PHP. |
| Alta de ticket | MU-plugin `cxp-tickets.php` | Pega JSON → `incidents/tickets/{id}.json` | No instala nada. No crea carpeta `planes/{id}/`. No resuelve plugins. |
| Instalar versión | MU-plugin `cxp-stack-versions.php` | ZIP `downloads.wordpress.org/plugin/{slug}.{version}.zip` + activar | Hay que pulsar **plugin a plugin**. No lee el ticket. Chilexpress sale del ZIP del **repo**, no de .org. |
| Temas comerciales | Woodmart Child fijo en «pila del cliente» | Restaura woodmart-child | Un cliente con Storefront / Flatsome / hijo propio **no se replica** con un clic. |
| PHP | `runtime/php-VERSION/` + `start.sh` / Docker `PHP_VERSION` | Cambio **con reinicio** | El JSON puede pedir 8.1 y el proceso sigue en 8.4.19. |

Conclusión: el flujo «copiar / pegar / crear ticket» **ya está**. Lo que falta es el **contrato rico** (slug + fuente) y el botón **Aplicar pila de esta incidencia**.

---

## 3. Objetivo cuando se ejecute

Un operador (tú o yo, cuando lo indiques) debe poder:

```
Copiar plantilla  →  cliente completa  →  pegar JSON
     → validar
     → guardar incidents/tickets/{ticket_id}.json
     → (opcional) crear incidents/planes/{ticket_id}/
     → pulsar «Aplicar pila de este ticket»
     → WordPress + cada plugin con versión del JSON
       instalados desde wordpress.org (o ZIP local si no está en .org)
     → informe: OK / no encontrado / premium / error HTTP
```

Reglas que **no** se cambian salvo que lo digas en las preguntas:

- Chilexpress Oficial **no se parchea**. Si el ticket pide 1.4.0, se restaura el árbol `chilexpress-oficial/` del repo (igual que ahora).
- SQLite (`sqlite-database-integration`) **no se reinstala en caliente** (ya está bloqueado en recarga completa).
- Una sola instancia WordPress en `127.0.0.1:8080` (salvo que elijas snapshots; pregunta 2).

---

## 4. Contrato JSON propuesto (schema 1.1)

Se **extiende** 1.0 para no romper SR-108688. Campos nuevos son opcionales en tickets viejos; **obligatorios en la plantilla nueva** que se le manda al cliente.

### 4.1 Raíz

| Campo | Quién lo llena | Notas |
|---|---|---|
| `schema_version` | plantilla | `"1.1"` en plantilla nueva. Importador acepta `1.0` y `1.1`. |
| `ticket_id` | vacío → Aeolabs | Si el cliente pone su ID interno (`SR-…`, `WOO-…`) se respeta (sanitizado). Si vacío: `CXP-YYYYMMDD-HHMMSS`. |
| `instrucciones_para_completar` | solo plantilla | Se puede quitar al guardar el ticket. |
| `laboratorio` | Aeolabs al importar | Estado de réplica: `pendiente` / `pila_aplicada` / `parcial` / `no_aplicable`. |

El resto (`origen`, `sintoma`, `evidencia`, `pedido`) se mantiene como en 1.0.

### 4.2 `pila` (núcleo)

```json
"pila": {
  "php": "8.4.19",
  "wordpress": "6.8.2",
  "woocommerce": "9.8.5",
  "chilexpress_oficial": "1.4.0",
  "tema": {
    "nombre": "Storefront",
    "version": "4.6.1",
    "slug": "storefront",
    "fuente": "wordpress.org",
    "es_hijo": false,
    "padre_slug": "",
    "padre_version": ""
  }
}
```

Compatibilidad 1.0: si `tema` sigue siendo string (`"Woodmart Child 1.0.0"`), no se instala tema; solo se documenta.

`php` **nunca se instala desde wordpress.org**. El informe debe decir: *«PHP pedido X; runtime actual Y; reinicia start.sh / rebuild Docker si hace falta»*.

### 4.3 `plugins[]` — lo que desbloquea el import

Cada ítem, para poder instalarlo:

```json
{
  "nombre": "WooCommerce",
  "slug": "woocommerce",
  "version": "11.0.1",
  "activo": true,
  "fuente": "wordpress.org",
  "notas": ""
}
```

| Campo | Obligatorio para instalar | Valores de `fuente` |
|---|---|---|
| `nombre` | para humanos | — |
| `slug` | **sí** para .org (carpeta del plugin, no el título) | — |
| `version` | sí (o `"latest"`) | — |
| `activo` | sí | si `false`, se instala (o se deja) y se **desactiva** |
| `fuente` | sí | `wordpress.org` · `repo` (Chilexpress del árbol) · `drop-plugins` (ZIP en `drop-plugins/{slug}.zip`) · `premium_no_instalar` · `desconocido` |

**Resolución de slug** (fase de implementación, si lo apruebas):

1. Si viene `slug` → se usa.
2. Si no, mapa interno nombre → slug (`WooCommerce` → `woocommerce`, `Akismet` → `akismet`, …).
3. Si no, consulta `https://api.wordpress.org/plugins/info/1.2/?action=query_plugins&search=` y **no instala** si hay más de un candidato: se lista para que elijas.
4. Nunca adivinar un slug con score bajo.

### 4.4 Política de plugins que **no** están en el JSON

Hay que decidirlo (pregunta 3). Opciones:

- **A — Estricto:** desactivar todo lo que no esté en `plugins[]` (excepto MU-plugins del lab `cxp-*` y SQLite). Réplica más fiel; puede romper Woodmart si el ticket no lo lista.
- **B — Acumulativo:** solo instalar/cambiar los del JSON; el resto queda. Más seguro para el lab; menos fiel al cliente.
- **C — Lista blanca del lab:** siempre activos: SQLite + Woo (si el ticket es Woo) + Chilexpress si viene en el JSON + MU `cxp-*`.

### 4.5 Plantilla que se copia al cliente (borrador)

La plantilla real se escribirá al ejecutar. Contenido previsto:

- Instrucciones cortas: WooCommerce → Estado → copiar tabla de plugins; Salud del sitio → versiones.
- Un bloque `plugins` con **3 filas de ejemplo** (Woo, Chilexpress, y una vacía) y comentario de que pueden pegar N filas.
- Campo `slug`: «carpeta del plugin, se ve en `/wp-content/plugins/NOMBRE/` o en la URL de wordpress.org».
- Advertencia: **no pegar claves API, contraseñas ni dumps**.
- `fuente: "desconocido"` si no saben de dónde salió el plugin.

---

## 5. UX en la consola réplica (cuando se implemente)

Panel **Incidencias** (`cxp-tickets.php`), sin quitar lo actual:

| Botón | Acción |
|---|---|
| Copiar JSON para el cliente | Plantilla 1.1 |
| Crear ticket con este JSON | Igual que ahora + validación 1.1 + carpeta `planes/{id}/` con README stub |
| **Aplicar pila de este ticket** | Nuevo. Confirmación. Log línea a línea. |
| **Informe de pila** | Qué se instaló, qué faltó slug, qué es premium, PHP mismatch |
| **Volver a pila SR-108688** | Reutiliza el botón ya existente de stack |
| Ver JSON | Igual |

No se aplica la pila **en silencio** al crear el ticket: crear ≠ instalar. Evita destrozar el laboratorio de celularesenventa.cl si solo querías archivar el JSON.

---

## 6. Motor de instalación (reutilizar, no duplicar)

Al ejecutar, el importador debe llamar a la lógica ya existente en `Cxp_Stack_Versions`:

| Target | Método actual |
|---|---|
| Core WP | `install_wordpress( $version )` — ZIP wordpress.org, no pisa `wp-content` |
| Plugin .org | `install_plugin_zip( $slug, $version )` → activa |
| Chilexpress | `source === repo` → copiar `chilexpress-oficial/` |
| Tema .org | Hoy **no hay** `install_theme_zip`. Hay que añadirlo (mismo patrón: `downloads.wordpress.org/theme/{slug}.{version}.zip`) **solo si** respondes sí a la pregunta 5. |
| ZIP local | Reutilizar subida / `drop-plugins/` de `cxp-plugin-lab.php` |

Orden de aplicación sugerido:

1. Snapshot rápido de plugins activos (lista en `wp-content/cxp-snapshots/pre-{ticket_id}-…`) — pregunta 6.
2. WordPress core si `pila.wordpress` ≠ actual.
3. WooCommerce y el resto de `plugins[]` con `fuente=wordpress.org`.
4. Chilexpress si aplica.
5. Activar / desactivar según `activo`.
6. Tema si `fuente=wordpress.org`.
7. Escribir `laboratorio` + log en `incidents/tickets/{id}.apply-log.txt` (o clave `laboratorio.log` dentro del JSON).
8. Recargar el front.

Timeouts: ya hay `set_time_limit( 240–300 )` en recarga completa; un ticket con 40 plugins puede necesitar más o instalar en cola.

---

## 7. Fases de implementación (solo cuando digas ejecutar)

### Fase 0 — Congelar decisiones

Tú respondes las preguntas de la §9. Sin eso no se toca schema ni PHP.

### Fase 1 — Contrato y plantilla

- `incident.schema.json` → 1.1 (compatible 1.0).
- Nueva plantilla `templates/para-el-cliente-v1.1.json` (la 1.0 se puede dejar como fallback).
- Ejemplo ficticio `tickets/_EJEMPLO-1.1.json` (sitio inventado, sin datos reales de clientes).
- SR-108688 se deja en 1.0 **o** se le añaden slugs a Woo/Chilexpress sin cambiar el relato.

### Fase 2 — Importador

- Validar JSON, slugs, versiones.
- Crear ticket + stub `planes/{id}/README.md` (01/02/03 vacíos, no inventar el diagnóstico).
- Resolver slugs pendientes y devolver lista «elige 1 de N».

### Fase 3 — Aplicar pila

- AJAX `cxp_ticket_apply_stack`.
- Informe OK / error por plugin.
- No parchear Chilexpress.

### Fase 4 — Temas .org y extras (opcional)

- Instalar tema público.
- Documentar temas premium: *«deja el ZIP en drop-plugins o no se replica el look»*.

### Fase 5 — Docs

- Actualizar `incidents/README.md`, `docs/consola-replica.md`, `docs/guia-de-uso.md`.
- **Este archivo** pasa a estado «ejecutado» con fecha y checklist.

---

## 8. Riesgos (para no sorprenderte al ejecutar)

| Riesgo | Mitigación |
|---|---|
| Un ticket pisa Woo 11.0.1 y rompe el caso SR-108688 | Aplicar pila es **explícito**; botón volver a pila del cliente. |
| Plugin premium / nulled | `fuente: premium_no_instalar`. Nunca buscar cracks. |
| Versión que ya no está en .org | Error claro + pedir ZIP al cliente en `drop-plugins/`. |
| 80 plugins | Límite (pregunta 7) + progreso en consola. |
| PHP distinto | Advertencia; no fingir que cambió. |
| Child theme propietario | No se puede bajar de .org; se documenta. |
| Activar plugins incompatibles entre sí | El laboratorio **quiere** ver el fatal: es el punto. Restaurar pila después. |

---

## 9. Preguntas — respóndelas cuando quieras (antes de ejecutar)

Contesta con el número. Si dices «ejecuta» sin responder, me detengo y las vuelvo a pedir.

1. **¿Schema 1.1 compatible** (recomendado) o tiramos 1.0 y migrarmos SR-108688 a la fuerza?

2. **Aislamiento:** ¿una sola WordPress que **cambia de pila** al aplicar un ticket (como ahora), o quieres **snapshot/restore** por ticket para no mezclar clientes?

3. **Plugins que no vienen en el JSON:** ¿estricto (desactivar el resto), acumulativo (solo sumar/cambiar), o lista blanca del lab (C)?

4. **Chilexpress:** ¿siempre desde el ZIP del repo (`fuente: repo`) aunque el cliente ponga otra versión, o intentamos esa versión solo si deja un ZIP en `drop-plugins/`?

5. **¿Instalar temas de wordpress.org** en la misma pasada, o en v1 solo plugins + WP + Woo?

6. **¿Snapshot automático** de plugins antes de aplicar un ticket (ocupa disco en `wp-content/cxp-snapshots/`)?

7. **Tope de plugins** a instalar en un apply (sugerencia: 40). ¿Otro número? ¿Sin tope?

8. **PHP:** ¿solo avisar el mismatch, o el apply debe **negarse** si `pila.php` ≠ runtime?

9. **IDs:** ¿respetar el `ticket_id` que ponga el cliente (`SR-108688`, `JIRA-12`) o forzar siempre prefijo `CXP-` y guardar el de ellos en `origen.ref_externa`?

10. **Al crear ticket, ¿generar** `incidents/planes/{id}/README.md` vacío (sí/no)?

11. **WooCommerce:** ¿debe ir en `plugins[]` **y** en `pila.woocommerce`, o basta uno de los dos? (Si ambos, ¿cuál gana si no coinciden?)

12. **¿El cliente debe rellenar slugs** o prefieres que ellos solo pongan el **nombre comercial** y el lab resuelva (con parada si hay ambigüedad)?

13. **MU-plugins `cxp-*`:** ¿siempre quedan (recomendado, si no se pierde la consola) o un ticket puede pedir «sitio pelado»?

14. **Cuando apliquemos un ticket, ¿vaciar pedidos / carrito / transients** para no mezclar datos de SR-108688 con el cliente nuevo?

15. **Prioridad de la primera incidencia extra** además de SR-108688: ¿algún cliente real ya en cola, o primero el ejemplo `_EJEMPLO-1.1`?

---

## 10. Cómo decirme que lo ejecute

Cuando las respuestas de la §9 estén (aunque sea «usa tus recomendados en 1, 2A, 3C, …»), escribe algo inequívoco:

> Ejecuta el plan `incidents/planes/laboratorio-multicliente/PLAN-importar-incidencias.md`  
> Respuestas: 1=… 2=… (o «recomendados»)

Hasta ese mensaje: **no** cambiar schema, plantilla, ni PHP de tickets. Este Markdown se queda.

---

## 11. Recomendados (si más adelante dices «usa recomendados»)

Para no dejar el plan a medias, esto es lo que aplicaría **solo si lo autorizas**:

| # | Recomendado |
|---|---|
| 1 | Schema 1.1 compatible |
| 2 | Una WP + snapshot rápido antes de apply |
| 3 | Lista blanca C (MU lab + SQLite; el resto según JSON) |
| 4 | Chilexpress solo `repo` 1.4.0 o ZIP del cliente, nunca .org |
| 5 | v1: plugins + WP + Woo; temas .org en fase 4 |
| 6 | Sí, snapshot pre-apply |
| 7 | Tope 40 |
| 8 | Avisar, no bloquear (el fatal puede ser precisamente PHP+plugin) |
| 9 | Respetar `ticket_id` del cliente; sanitizar filename |
| 10 | Sí, stub de plan |
| 11 | Gana `plugins[].woocommerce` si hay conflicto; `pila.woocommerce` es atajo |
| 12 | Nombre + slug opcional; resolver y parar si hay duda |
| 13 | MU `cxp-*` siempre |
| 14 | Vaciar carrito sí; pedidos no (salvo que el ticket lo pida) |
| 15 | Ejemplo 1.1 primero, luego clientes reales |

---

## 12. Fuera de alcance (salvo que lo pidas)

- Parchear PHP de Chilexpress Oficial.
- Multi-contenedor / una WP por cliente.
- Importar base de datos de producción.
- Instalar plugins de fuentes ilegales.
- Cambiar PHP en caliente sin reiniciar `start.sh` / Docker.
