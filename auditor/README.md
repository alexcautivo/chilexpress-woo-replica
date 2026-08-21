# Auditor multiplataforma Chilexpress

Responde una sola pregunta, con evidencia reproducible:

> ¿Este artefacto concreto de Chilexpress es compatible con esta versión concreta de esta plataforma?

Entregas un ZIP oficial y obtienes `PASS`, `FAIL`, `ERROR`, `SKIP` o `UNKNOWN`, con reporte, logs y exit code.

Autor: **Alexander Alejandro Cautivo Ramos** · [Aeolabs.io](https://aeolabs.io)

Especificación: [`docs/PROMPT-ORIGINAL.md`](docs/PROMPT-ORIGINAL.md) · Diseño: [`docs/ARQUITECTURA.md`](docs/ARQUITECTURA.md)

---

## Reglas que no se negocian

- El **ZIP es el artefacto**: no se sustituye por código de git ni se modifica.
- Un **fatal nunca termina en exit code 0**.
- La **IA no puede convertir FAIL en PASS**. Es opcional y separable.
- Sin API key el auditor funciona igual.
- Nunca se crean stubs ni se parchea el core de la plataforma para lograr un `PASS`.

---

## Uso

```bash
cd auditor

# 1. Dejar el ZIP oficial
#    artifacts/incoming/woocommerce-plugin-1.4.0-RELEASE.zip

# 2. Analizar sin Docker
node bin/auditor.mjs inspect artifacts/incoming/woocommerce-plugin-1.4.0-RELEASE.zip

# 3. Ver qué debería probarse
node bin/auditor.mjs plan artifacts/incoming/woocommerce-plugin-1.4.0-RELEASE.zip

# 4. Auditoría real en Docker
node bin/auditor.mjs audit artifacts/incoming/woocommerce-plugin-1.4.0-RELEASE.zip --platform-version=11.0.1

# 5. Matriz de versiones
node bin/auditor.mjs matrix artifacts/incoming/woocommerce-plugin-1.4.0-RELEASE.zip --versions=9.8.5,10.6.2,11.0.1
```

| Modo | Qué hace | Docker |
|---|---|---|
| `inspect` | Hash, estructura, plataforma, versión, requisitos | No |
| `plan` | Qué debería probarse según el plan de la plataforma | No |
| `audit` | Instala y prueba de verdad | Sí |
| `regression` | Casos conocidos (SR-108688) | Sí |
| `matrix` | Varias versiones de plataforma | Sí |
| `fix` | Compara ZIP original vs corregido, sobre copias | No |
| `full` | Todo lo anterior | Sí |

Utilidades: `pack`, `lab up|down`, `ai configure|status`, `platforms`.

---

## Exit codes

| Código | Significado |
|---|---|
| 0 | PASS |
| 1 | FAIL / incompatibilidad |
| 2 | ERROR de entorno o dependencia |
| 3 | ERROR del propio auditor |
| 4 | SKIP |
| 5 | UNKNOWN |

---

## Plataformas

| Plataforma | Estado | Nota |
|---|---|---|
| WooCommerce | Auditoría Docker completa | WordPress + MariaDB + WP-CLI, versiones fijas |
| PrestaShop | Comprobaciones estáticas + lab listo | La auditoría dinámica devuelve `SKIP` hasta tener artefacto y versión objetivo |
| Magento | Comprobaciones estáticas + lab listo | Necesita imagen propia y Composer autenticado |
| Shopify | Comprobaciones estáticas | No es PHP autoalojado: exige tienda de desarrollo y credenciales |

`SKIP` significa "no se probó", nunca "está bien".

---

## Regresión SR-108688

Permanente, en `src/regression/sr-108688.mjs`. Con WooCommerce 11 × Chilexpress 1.4.0 determina si el artefacto:

- `REPRODUCED` — vuelve a aparecer `ProductTaxStatus not found`
- `FIXED` — el plugin queda activo y no hay fatal
- `CHANGED` — hay otro fatal distinto
- `NOT_APPLICABLE` — WooCommerce anterior a la serie 11
- `INCONCLUSIVE` — el plugin no llegó a activarse

---

## IA opcional

```bash
node bin/auditor.mjs ai configure   # provider, modelo, nivel, API key
node bin/auditor.mjs ai status      # nunca imprime la key
```

Niveles: `OFF`, `BASIC`, `STANDARD` (por defecto), `DEEP`. `DEEP` exige consentimiento explícito.

Antes de enviar cualquier contexto se aplica redacción `[REDACTED]` de keys, tokens, cookies, JWT, correos, RUT y tarjetas. Si la IA falla, el reporte marca `AI_STATUS=UNAVAILABLE` y la auditoría continúa.

La configuración vive en `auditor/.auditor/` y no se versiona.

---

## Reportes

```
reports/AUDIT-ID/
  result.json        modelo común
  report.txt         legible, separa determinista de IA
  diagnostics.json   hallazgos, regresiones, healthchecks
  test-results.json  cada prueba con PASS/FAIL
  metadata.json      versiones, imagen, digest, SHA-256
  ai-analysis.json   solo si la IA se usó
  logs/              debug.log, activación, sondas HTTP
  artifacts/         copias de trabajo, nunca el original
```

---

## Artefacto de prueba local

Si aún no tienes el ZIP oficial:

```bash
node bin/auditor.mjs pack ../chilexpress-oficial --out=chilexpress-woocommerce-1.4.0-LOCAL.zip
```

Queda marcado como `ARTIFACT_ORIGIN=locally-packed` y el reporte avisa de que **no representa al paquete distribuido**.

---

## Relación con el laboratorio de incidencias

Son herramientas distintas y no se pisan:

| | Laboratorio de incidencias (`incidents/`) | Auditor (`auditor/`) |
|---|---|---|
| Pregunta | ¿Por qué falla la tienda de este cliente? | ¿Es compatible este artefacto? |
| Entrada | JSON del cliente | ZIP oficial |
| Entorno | WordPress persistente con SQLite | Labs desechables con MariaDB |
| Salida | PDF cliente y técnico | `result.json` + exit code |
