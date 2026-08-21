# PLAN-DEPLOY-DOKPLOY

Entregable de la **§49** y **§50**. Este documento **no ejecuta nada**.

> **No se despliega el auditor en Dokploy en esta fase.** No se conecta a Dokploy, no se modifica producción y no se sube ninguna API key. El auditor funciona hoy solo en Docker local.

Autor: Alexander Alejandro Cautivo Ramos · [Aeolabs.io](https://aeolabs.io)

---

## 1. Ruta de promoción

```
LOCAL          Docker en la maquina del auditor. Labs desechables.
   ↓
STAGING        VPS interno. Auditorias programadas, sin datos de clientes.
   ↓
DOKPLOY        Servicio gestionado, expuesto solo al equipo.
   ↓
PRODUCCION     Auditoria como puerta de calidad antes de publicar un release.
```

Nada avanza de etapa sin que la anterior produzca resultados reproducibles.

---

## 2. Qué se despliega y qué no

| Componente | ¿Va a Dokploy? | Motivo |
|---|---|---|
| `auditor-core` (CLI + report engine) | Sí | Es el servicio que orquesta |
| Labs de plataforma (WooCommerce, PrestaShop, Magento) | Sí, bajo demanda | Necesitan Docker-in-Docker o un runner con socket |
| Sondas Shopify | Sí | Solo salida HTTPS |
| ZIPs de clientes | No se versionan | Se montan por volumen |
| Reportes | Volumen persistente | Evidencia auditable |
| `ANTHROPIC_API_KEY` | Solo como secreto | Nunca en imagen ni en git |

---

## 3. Requisito técnico crítico

El auditor levanta contenedores. En Dokploy eso exige una de estas dos opciones:

| Opción | Descripción | Riesgo |
|---|---|---|
| A. Socket montado | `/var/run/docker.sock` dentro del contenedor del auditor | Alto: equivale a root en el host |
| B. Runner dedicado | Una VM aparte solo para auditorías, sin otros servicios | Bajo, recomendado |

**Recomendación:** opción B. El VPS actual ya hospeda tiendas reales; darle al auditor acceso al socket comprometería todo el host.

---

## 4. Variables y secretos

Variables normales (no sensibles):

```env
AI_ENABLED=true
AI_PROVIDER=anthropic
AI_MODEL=claude-sonnet-4-5
AI_ANALYSIS_LEVEL=STANDARD
AI_MAX_REQUESTS=3
AI_MAX_TOKENS=1200
AI_TIMEOUT=45000
AUDITOR_WP_IMAGE=wordpress:6.8-php8.3-apache
AUDITOR_DB_IMAGE=mariadb:11.4
```

Secreto, **solo** por el gestor de secretos de Dokploy:

```
ANTHROPIC_API_KEY
```

Prohibido:

- subir un `.env` real al repositorio;
- poner la key en `Dockerfile` o en `docker-compose.yml`;
- hornear la key en una imagen;
- imprimir la key en logs, reportes o `ai-analysis.json`;
- pasar la key a los contenedores de plataforma (§44).

Solo `auditor-core` recibe la key. WooCommerce, PrestaShop, Magento y Shopify nunca la ven.

---

## 5. Volúmenes

| Volumen | Ruta | Contenido |
|---|---|---|
| `auditor_reports` | `/app/auditor/reports` | Evidencia por `AUDIT_ID` |
| `auditor_artifacts` | `/app/auditor/artifacts/incoming` | ZIPs a auditar |
| `auditor_config` | `/app/auditor/.auditor` | Configuración de IA |

Los reportes son la evidencia auditable: no se borran en cada redeploy.

---

## 6. Pasos futuros (cuando se autorice)

1. Crear el runner dedicado con Docker y sin otros servicios.
2. Construir la imagen `auditor-core` (Node 22 + cliente Docker).
3. Crear el servicio en Dokploy apuntando al runner.
4. Registrar `ANTHROPIC_API_KEY` como secreto.
5. Montar los tres volúmenes.
6. Ejecutar `auditor inspect` contra un ZIP conocido y comparar el SHA-256 con el resultado local.
7. Solo si coincide, habilitar `auditor audit`.
8. Documentar el `AUDIT_ID` de la primera ejecución remota.

---

## 7. Rollback (§51)

Cada despliegue registra:

| Campo | Origen |
|---|---|
| `VERSION_ANTERIOR` / `VERSION_NUEVA` | Tag de la imagen del auditor |
| `COMMIT` | SHA de git |
| `IMAGE_TAG` / `IMAGE_DIGEST` | Registro de contenedores |
| `PLUGIN_SHA256` | `metadata.json` del último `AUDIT_ID` |
| `CONFIGURATION_VERSION` | Hash de la configuración efectiva |

El rollback debe poder volver al artefacto anterior: los ZIP auditados se conservan en el volumen y su SHA-256 está en cada reporte.

---

## 8. Criterio para autorizar el despliegue

No se despliega hasta que, en local, el auditor cumpla la §56 completa: recibir ZIP, hashear, detectar, inspeccionar, crear Docker, instalar, probar, capturar errores, regresionar, reportar, destruir, recrear y obtener resultados reproducibles, con y sin IA.
