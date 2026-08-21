# Plan — laboratorio multiplataforma Chilexpress

**Estado:** solo plan. **No ejecutar** hasta que Alexander lo pida por plataforma (ejemplo: *«ejecuta el plan Magento»*).

**No borrar esta carpeta.** Es la especificación de trabajo; se actualiza cuando respondas las preguntas del plan maestro.

Autor: **Alexander Alejandro Cautivo Ramos** · [Aeolabs.io](https://aeolabs.io)

El laboratorio WordPress + WooCommerce **sigue siendo el único implementado**. Magento, PrestaShop y Shopify se documentan aquí para copiar el mismo modelo de incidencias, semilla de tienda y despliegue Docker/Dokploy, **sin mezclar código** hasta que se active cada plan.

| Archivo | Qué cubre | Cuándo ejecutarlo |
|---|---|---|
| [PLAN-laboratorio-multiplataforma.md](PLAN-laboratorio-multiplataforma.md) | Contrato común, semilla compartida, JSON, Docker, preguntas | Nunca solo: es el marco |
| [PLAN-magento.md](PLAN-magento.md) | Magento 2 + módulo Chilexpress | Cuando lo indiques |
| [PLAN-prestashop.md](PLAN-prestashop.md) | PrestaShop + módulo Chilexpress | Cuando lo indiques |
| [PLAN-shopify.md](PLAN-shopify.md) | Shopify (app / checkout / simulación local) | Cuando lo indiques |

Referencias Azure DevOps (piden login; no se pudieron leer en esta sesión):

- Magento: https://chilexpresscode.visualstudio.com/Magento
- PrestaShop: https://chilexpresscode.visualstudio.com/Prestashop
- Shopify: https://chilexpresscode.visualstudio.com/Shopify *(URL recortada en el chat; confirmar)*

Laboratorio ya vivo: [README raíz](../../../README.md) y [plan WordPress multi-cliente](../laboratorio-multicliente/PLAN-importar-incidencias.md).
