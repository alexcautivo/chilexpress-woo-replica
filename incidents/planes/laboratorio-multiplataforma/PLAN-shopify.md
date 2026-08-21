# Plan de ejecución — Shopify + Chilexpress

**Estado:** no ejecutar. Frase de activación: *«ejecuta el plan Shopify»*.

Wiki (URL del chat recortada; asumir): https://chilexpresscode.visualstudio.com/Shopify  
Confirmar antes de ejecutar.

---

## Objetivo

Shopify **no se instala como WordPress**. No hay core PHP on-prem. El laboratorio sería una de estas formas (tú eliges en las preguntas):

| Modelo | Qué es | Útil para |
|---|---|---|
| A. Tienda de desarrollo Shopify + app Chilexpress | Real, necesita Partner / Dev Dashboard | Fallos de app, checkout extensions, webhooks |
| B. Mock local (Node) que simula Admin + Storefront + CarrierService | Docker 100% local, deployable | Cotizar, OT, incidencias de contrato API |
| C. Híbrido: mock para flujo + tienda dev para demos | Más trabajo | Lo más parecido al lab Woo |

El JSON de incidencia sigue existiendo: plan Shopify, theme, apps instaladas, checkout extensibility, síntoma, pasos.

---

## Qué copiar del laboratorio Woo

| Woo | Shopify equivalente |
|---|---|
| Plugins + versiones | apps + `shopify app` TOML + versión de API (`2025-01`, etc.) |
| Tema Woodmart | theme OS 2.0 + checkout extensibility |
| Catálogo wiki | 5 products GraphQL Admin / CSV |
| Usar dirección | Checkout UI extension o script de lab en storefront |
| Tarjeta 4242 | pagos de prueba Shopify (Bogus Gateway / Shopify Payments test) |
| `debug.log` | logs de app (stdout del contenedor) + Admin GraphQL errores |
| Puerto 8080 | **8083** para el mock / app tunnel |

---

## Pasos cuando lo indiques (no ahora)

1. Confirmar URL Azure DevOps y tipo de integración (CarrierService, checkout, app embebida).
2. Elegir modelo A, B o C.
3. Semilla: mismos 5 productos, CLP, kg/cm, origin PROVIDENCIA.
4. Plantilla `incidents/templates/para-el-cliente-shopify.json` (apps, theme, checkout, POS si aplica).
5. Flujo declarativo: abrir checkout, fijar shipping address LA REINA, disparar CarrierService, leer rates PREX/CHEX.
6. Docker: app Node/PHP + ngrok/cloudflare tunnel **o** mock HTTP.
7. Dokploy: solo si eliges mock/app self-hosted; una tienda Shopify real no cabe en un Dockerfile de core.
8. PDF cliente / técnico con request/response de CarrierService (sin keys).

---

## Riesgos

- Shopify Checkout Extensibility no se “instala una versión de WordPress”.
- Túneles (`shopify app dev`) no son triviales en Dokploy.
- No guardar tokens de Partner en git.

## Preguntas específicas Shopify

1. ¿Confirmas la URL del proyecto Azure DevOps?
2. ¿App pública, custom, o solo CarrierService?
3. ¿Modelo A, B o C?
4. ¿Checkout extensibility ya está en los clientes, o checkout legacy?
