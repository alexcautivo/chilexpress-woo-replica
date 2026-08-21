# Plan de ejecución — Magento + Chilexpress

**Estado:** no ejecutar. Frase de activación: *«ejecuta el plan Magento»*.

Wiki (login): https://chilexpresscode.visualstudio.com/Magento

---

## Objetivo

Un laboratorio Magento 2 local y en Dokploy que:

1. Reciba el mismo tipo de JSON de incidencia (versiones de Magento, PHP, tema, módulos, síntoma, pasos).
2. Aplique la pila (snapshot + versiones) **sin parchear** el módulo Chilexpress.
3. Use el **catálogo wiki**, direcciones RM y tarjeta `4242`.
4. Tenga «llenar checkout» equivalente y genere PDF cliente / técnico.

---

## Qué copiar del laboratorio Woo

| Woo | Magento equivalente |
|---|---|
| WordPress + WooCommerce | Magento Open Source 2.4.x |
| Plugin `chilexpress-oficial` | Módulo Chilexpress (Composer / `app/code`) **intacto** |
| `cxp-wiki-catalog.php` | script de seed: 5 productos, kg/cm, CLP |
| `Usar dirección` | bookmarklet / módulo lab que rellena quote address |
| `admin-ajax.php` | REST `/rest/V1/` + checkout |
| `debug.log` | `var/log/system.log`, `exception.log`, `debug.log` |
| SQLite | **MySQL 8** (Magento no corre en SQLite) + OpenSearch |
| Puerto 8080 | **8081** local; Traefik en Dokploy |

---

## Pasos cuando lo indiques (no ahora)

1. Confirmar edición (Open Source vs Commerce) y versión exacta de la wiki.
2. Inventariar el contenedor actual `chilexpress-magento-web` (versión Magento, PHP, módulo).
3. Decidir: receta en este repo vs seguir el proyecto Azure DevOps.
4. Compose: `php-fpm` o Apache, MySQL 8, OpenSearch, Redis opcional.
5. Seed: 5 SKU wiki + origen PROVIDENCIA + keys staging por env.
6. Plantilla `incidents/templates/para-el-cliente-magento.json`.
7. Runner: pasos declarativos (`open_url`, `rest_get`, `place_order`, `assert_log`).
8. Snapshot: `app/code` + `composer.lock` + dump SQL (pesado; definir retención).
9. Deploy Dokploy: servicio Compose aparte, volumen DB + media.
10. Pruebas funcionales HTTP (login admin, catálogo, cotizar LA REINA).

---

## Riesgos

- Magento es lento de build; el primer deploy en Dokploy puede tardar mucho más que Woo.
- Elasticsearch/OpenSearch es obligatorio en 2.4.
- El checkout Magento no usa `#billing_state` de Woo: el relleno automático hay que reescribirlo.
- No copiar la base de producción de un cliente.

## Preguntas específicas Magento

1. ¿Versión Magento y PHP de la wiki?
2. ¿Nombre Composer del módulo Chilexpress y versión?
3. ¿Checkout Luma, Hyvä, o Checkout de Adobe?
4. ¿Reusar el contenedor `chilexpress-magento-*` del VPS?
