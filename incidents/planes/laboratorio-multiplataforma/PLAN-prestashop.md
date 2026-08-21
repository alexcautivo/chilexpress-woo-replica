# Plan de ejecución — PrestaShop + Chilexpress

**Estado:** no ejecutar. Frase de activación: *«ejecuta el plan PrestaShop»*.

Wiki (login): https://chilexpresscode.visualstudio.com/Prestashop

---

## Objetivo

Laboratorio PrestaShop 8.x (imagen `prestashop/prestashop:8-apache` ya vista en Dokploy) con:

1. JSON de incidencia (PS + PHP + tema + módulos + Chilexpress).
2. Aplicar versiones de módulos desde ZIP local / Addons cuando exista fuente legal.
3. Misma semilla wiki, LA REINA, Larrain 5862, tarjeta `4242`.
4. PDF cliente y técnico.

---

## Qué copiar del laboratorio Woo

| Woo | PrestaShop equivalente |
|---|---|
| `wp-content/plugins` | `/modules` |
| Chilexpress Oficial | módulo Chilexpress en `/modules/...` **sin parchear** |
| Catálogo wiki | import CSV o script `prestashop-cli` / ObjectModel |
| Checkout clásico | checkout nativo PS (no FO de bloques Woo) |
| `debug.log` | `var/logs`, modo debug PS |
| SQLite | MySQL (PS oficial) |
| Puerto 8080 | **8082** local |

---

## Pasos cuando lo indiques (no ahora)

1. Leer versión PS y módulo Chilexpress de la wiki.
2. Inventariar `chilexpress-prestashop-web` en el VPS.
3. Compose: `prestashop/prestashop:8-apache` + MariaDB, `PS_DOMAIN` / `PS_FOLDER_ADMIN`.
4. Seed 5 productos wiki (peso en kg, dimensiones cm, precio CLP).
5. Carrier Chilexpress activo en zona Chile; comuna con nombre de cobertura.
6. Plantilla `incidents/templates/para-el-cliente-prestashop.json`.
7. Relleno automático: JS lab inyectado o módulo `cxp_lab` (equivalente MU-plugin).
8. Snapshot: `modules` + `themes` + dump SQL.
9. Dokploy: servicio Compose propio, no mezclar con el WordPress 8080.
10. Tests: home, product, checkout, cotización, admin.

---

## Riesgos

- PrestaShop exige MySQL; no reutilizar SQLite de Woo.
- El admin tiene URL aleatoria (`admin-xxx`); hay que fijarla en el lab.
- Módulos premium no se descargan desde Addons sin cuenta.

## Preguntas específicas PrestaShop

1. ¿PS 8.1 / 8.2 y PHP?
2. ¿Nombre de carpeta del módulo Chilexpress?
3. ¿Checkout por defecto o un tema (Warehouse, Elementor, etc.)?
4. ¿Reusar `chilexpress-prestashop-web` o imagen nueva en este repo?
