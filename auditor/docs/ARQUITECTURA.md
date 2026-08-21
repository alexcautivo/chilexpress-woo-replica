# Arquitectura — Auditor multiplataforma Chilexpress

Entregable obligatorio de la **§59** del prompt (`PROMPT-ORIGINAL.md`). Se escribe **antes** de modificar código.

Autor del laboratorio: Alexander Alejandro Cautivo Ramos · [Aeolabs.io](https://aeolabs.io)

---

## 1. Arquitectura actual (antes de este trabajo)

| Pieza | Ruta | Qué hace |
|---|---|---|
| Laboratorio WordPress | `wordpress/` + `docker/Dockerfile` + `docker-compose*.yml` | Una sola tienda WordPress + WooCommerce + Chilexpress con consola de depuración |
| Consola réplica | `wordpress/wp-content/mu-plugins/cxp-*` | Tickets, pila, flujo declarativo, comparación, PDF |
| Incidencias | `incidents/` (schema 1.1, templates, tickets, runs, planes) | Contrato JSON cliente → reproducción |
| Chilexpress | `chilexpress-oficial/` (árbol, **sin ZIP**) | Copia intacta 1.4.0 |
| Pruebas | `tools/test-functional.mjs`, `tools/test-incident-lab.mjs` | HTTP contra el laboratorio |
| Planes multiplataforma | `incidents/planes/laboratorio-multiplataforma/` | Solo especificación, sin código |

**Limitaciones respecto al prompt:**

1. El artefacto auditado es un **árbol de repo**, no un **ZIP oficial con SHA-256**.
2. Solo existe WooCommerce; no hay adapters de Magento, PrestaShop ni Shopify.
3. No hay matriz plataforma × plugin.
4. No hay modos `INSPECT / PLAN / AUDIT / REGRESSION / MATRIX / FIX / FULL`.
5. No hay exit codes ni modelo de resultado común.
6. No hay capa de IA opcional.
7. El laboratorio es persistente y mutable; el auditor necesita entornos **desechables y reproducibles**.

Conclusión: el laboratorio actual **no se reemplaza**. El auditor es un componente nuevo y separado (`auditor/`) que puede coexistir.

---

## 2. Planes leídos (FASE 1)

| Plan | Ruta | Estado |
|---|---|---|
| WooCommerce | `incidents/planes/laboratorio-multicliente/PLAN-importar-incidencias.md` + `incidents/planes/SR-108688/` | Existe (implementado parcialmente) |
| Magento | `incidents/planes/laboratorio-multiplataforma/PLAN-magento.md` | Existe (solo especificación) |
| PrestaShop | `incidents/planes/laboratorio-multiplataforma/PLAN-prestashop.md` | Existe (solo especificación) |
| Shopify | `incidents/planes/laboratorio-multiplataforma/PLAN-shopify.md` | Existe (solo especificación) |
| Marco común | `incidents/planes/laboratorio-multiplataforma/PLAN-laboratorio-multiplataforma.md` | Existe |

**No se reemplazan.** El Core los **lee en tiempo de ejecución** (`src/core/plans.mjs`) y los expone en el modo `PLAN`.

**Contradicciones detectadas y su resolución:**

| Contradicción | Resolución |
|---|---|
| Los planes de tienda dicen "no ejecutar hasta que se pida"; el prompt pide implementar FASES 9–11 | Se implementan los **adapters** y sus labs, pero devuelven `SKIP` con motivo hasta que exista artefacto/credenciales. Nunca `PASS` falso |
| Plan WooCommerce usa SQLite y pila mutable | El auditor usa **MariaDB fija + entorno desechable**; el laboratorio de incidencias sigue con SQLite |
| No existe "Plan WooCommerce" con ese nombre | Se toma como plan WooCommerce la unión de `laboratorio-multicliente` + `SR-108688` |
| No hay ZIP oficial en el repo | `auditor pack` genera un artefacto local marcado `ARTIFACT_ORIGIN=locally-packed`, nunca confundible con un release oficial |

---

## 3. Arquitectura propuesta

```
ARTEFACTO (ZIP)
   ↓
INSPECCIÓN        sha256 · estructura · plataforma · versión · requisitos
   ↓
PLAN              plan de plataforma + matriz a probar
   ↓
ENTORNO           Docker aislado y desechable, versiones fijas
   ↓
PRUEBAS           deterministas (instalación, activación, rutas críticas)
   ↓
REGRESIONES       SR-108688 y casos futuros
   ↓
RESULTADO         PASS / FAIL / ERROR / SKIP / UNKNOWN + exit code
   ↓
IA (opcional)     análisis, nunca cambia el resultado
   ↓
REPORTE           reports/AUDIT-ID/
```

Separación estricta:

- **Core** — orquesta, no sabe de plataformas.
- **Adapters** — saben de una plataforma, devuelven el modelo común.
- **AI** — capa aparte, opcional, sin poder de decisión.
- **Report** — serializa; no interpreta.

---

## 4. Estructura de directorios

```
auditor/
  bin/auditor.mjs              CLI
  src/
    core/
      artifact.mjs             SHA-256, metadata, copia inmutable
      zip.mjs                  lector ZIP sin dependencias
      inspect.mjs              estructura, hooks, requisitos
      detect.mjs               detección de plataforma
      version.mjs              versión + VERSION_CONFLICT
      plans.mjs                lectura de los 4 planes
      result.mjs               modelo común, estados, exit codes
      report.mjs               reports/AUDIT-ID/
      redact.mjs               [REDACTED] antes de IA
      config.mjs               configuración de IA
      docker.mjs               compose, healthchecks, digests
      matrix.mjs               plataforma × plugin
      registry.mjs             registro de adapters
    adapters/
      woocommerce.mjs          implementado (Docker real)
      prestashop.mjs           lab + SKIP documentado
      magento.mjs              lab + SKIP documentado
      shopify.mjs              lab + SKIP documentado
    ai/
      index.mjs                fachada, límites, fallback
      provider.mjs             abstracción AI_PROVIDER
      anthropic.mjs            proveedor Claude
    regression/
      sr-108688.mjs            regresión permanente
  labs/{woocommerce,prestashop,magento,shopify}/docker-compose.yml
  artifacts/incoming/          ZIPs del usuario (no versionados)
  reports/                     salidas por AUDIT_ID (no versionadas)
  docs/
    PROMPT-ORIGINAL.md
    ARQUITECTURA.md
    PLAN-DEPLOY-DOKPLOY.md
  .env.example
  package.json
  README.md
```

---

## 5. Flujo de auditoría

1. Recibir ruta del ZIP.
2. SHA-256, tamaño, fecha, nombre → `metadata.json`.
3. Inspección del ZIP (sin ejecutarlo).
4. Detección de plataforma (o `UNKNOWN`, o `--platform=`).
5. Detección de versión (o `VERSION_CONFLICT`).
6. Lectura del plan de esa plataforma.
7. Cálculo de `SUPPORT_STATUS` frente a la versión de plataforma a probar.
8. Levantar el lab correspondiente con versiones fijas.
9. Healthchecks encadenados.
10. Instalar el artefacto **desde el ZIP original** (montado solo lectura).
11. Ejecutar pruebas y rutas críticas.
12. Ejecutar regresiones.
13. Calcular estado determinista y exit code.
14. IA opcional.
15. Escribir reporte y destruir el entorno.

---

## 6. Flujo Docker

```
docker compose -p cxp-auditor-<plataforma> up -d
   ↓ DB READY        (healthcheck del motor)
   ↓ PLATFORM READY  (HTTP + CLI de la plataforma)
   ↓ PLUGIN INSTALLED
   ↓ PLUGIN INITIALIZED
   ↓ AUDITOR READY
   ↓ TESTS
docker compose -p cxp-auditor-<plataforma> down -v
```

Sin `sleep` como mecanismo principal: espera activa con timeout y comprobación de condición.

Aislamiento: nombre de proyecto propio, red propia, volúmenes propios, base de datos propia y puerto propio por plataforma.

---

## 7. Flujo ZIP

- El ZIP se **lee**, nunca se ejecuta en el host.
- Se monta en el contenedor como `:ro`.
- El original no se modifica ni se mueve.
- El modo `FIX` trabaja siempre sobre una copia en `reports/AUDIT-ID/artifacts/`.
- `ARTIFACT_ORIGIN` distingue `official-zip` de `locally-packed`.

---

## 8. Flujo IA

```
resultado determinista → contexto mínimo → redacción [REDACTED] → proveedor → ai-analysis.json
```

- Sin API key: `AI_STATUS=DISABLED`, la auditoría sigue.
- Con error de IA: `AI_STATUS=UNAVAILABLE`, la auditoría sigue.
- La IA **no** puede cambiar `DETERMINISTIC_RESULT`.
- Niveles `OFF/BASIC/STANDARD/DEEP`, por defecto `STANDARD`.
- `DEEP` exige `AI_DEEP_CONSENT=true`.

---

## 9. Matriz de plataformas

| Plataforma | Adapter | Lab | Estado inicial |
|---|---|---|---|
| WooCommerce | `woocommerce.mjs` | WordPress + MariaDB + WP-CLI | Implementado |
| PrestaShop | `prestashop.mjs` | PrestaShop + MariaDB | Lab listo, auditoría `SKIP` sin artefacto |
| Magento | `magento.mjs` | Magento + MariaDB + OpenSearch | Lab listo, auditoría `SKIP` sin artefacto |
| Shopify | `shopify.mjs` | Sondas de API | `SKIP` sin credenciales |

---

## 10. Matriz de versiones (WooCommerce, inicial)

| WordPress | WooCommerce | Chilexpress | Expectativa |
|---|---|---|---|
| 6.8 | 9.8.5 | 1.4.0 | dentro de rango |
| 6.8 | 10.6.2 | 1.4.0 | límite documentado |
| 6.8 | 11.0.1 | 1.4.0 | `OUT_OF_SUPPORTED_RANGE` (SR-108688) |

Cada combinación produce su propio `AUDIT_ID` y su propio resultado.

---

## 11. Estrategia de regresiones

- SR-108688 es permanente: `src/regression/sr-108688.mjs`.
- Determina si el artefacto **reproduce**, **corrige** o **cambia** el comportamiento.
- Nunca oculta el fatal, nunca crea stubs, nunca toca WooCommerce core.
- Nuevas regresiones se añaden como módulos con el mismo contrato.

---

## 12. Estrategia de seguridad

- ZIP tratado como no confiable: solo lectura, dentro de Docker.
- Contenedores de plataforma sin API keys de IA.
- Redacción de secretos antes de cualquier llamada a IA.
- Config de IA fuera de git (`auditor/.auditor/`), nunca impresa.
- `.env.example` sin secretos reales.

---

## 13. Estrategia de reportes

`reports/AUDIT-ID/` con `result.json`, `report.txt`, `diagnostics.json`, `test-results.json`, `metadata.json`, `logs/`, `artifacts/` y `ai-analysis.json` solo si la IA se usó.

El `report.txt` separa **Resultado determinista**, **Diagnóstico técnico**, **Análisis IA** y **Recomendaciones**.

---

## 14. Estrategia Dokploy

Nada se despliega ahora. Se documenta en `PLAN-DEPLOY-DOKPLOY.md` la ruta LOCAL → STAGING → DOKPLOY → PRODUCCIÓN y el manejo de `ANTHROPIC_API_KEY` como secreto.

---

## 15. Archivos modificados

| Archivo | Cambio |
|---|---|
| `.gitignore` | Ignorar `auditor/reports/`, `auditor/artifacts/incoming/*`, `auditor/.auditor/` |
| `README.md` | Sección del auditor |
| `incidents/planes/laboratorio-multiplataforma/README.md` | Enlace al auditor |

## 16. Archivos nuevos

Todo el árbol `auditor/` descrito en §4.

---

## 17. Riesgos

| Riesgo | Mitigación |
|---|---|
| Descarga de imágenes Docker pesadas (Magento) | Labs bajo demanda; `SKIP` explícito si no hay imágenes |
| Tags de imagen que cambian | Se registra el **digest** resuelto en `metadata.json` |
| Falsos PASS | `PASS` exige la lista completa de la §55; cualquier fatal fuerza `FAIL` |
| Puertos ocupados | Puerto configurable por lab y por variable de entorno |
| Windows/rutas | Rutas normalizadas; ZIP montado por carpeta, no por archivo suelto |
| Coste de IA | Límites de peticiones, tokens y timeout; `DEEP` requiere consentimiento |

---

## 18. Plan de migración

1. El auditor nace **aislado** en `auditor/`; nada del laboratorio actual se rompe.
2. El laboratorio de incidencias sigue siendo la herramienta para **incidencias de clientes**.
3. El auditor es la herramienta para **compatibilidad de un artefacto**.
4. Convergencia futura: el runner de incidencias podrá invocar `auditor audit` y adjuntar `result.json` al PDF técnico.
