# Credenciales — laboratorio (no son de producción)

Ticket **SR-108688**. Autor: Alexander Alejandro Cautivo Ramos · alexander.cautivo@aeolabs.io

Este archivo es seguro para un repo **público**: no incluye subscription keys ni contraseñas de tiendas desplegadas.

## WordPress de este laboratorio

| | |
|---|---|
| Usuario | `admin` |
| Contraseña | `admin` |
| Email | `alexander.cautivo+testwordpress@aeolabs.io` |
| Auto-login | solo localhost, o `CXP_AUTO_LOGIN=1` |

**Cambia `admin` en cuanto el contenedor sea público (Dokploy).**

## APIs Chilexpress (staging)

No se guardan en git. Tres sitios donde ponerlas:

1. Consola réplica → panel **APIs Chilexpress**
2. Variables de entorno (Docker / Dokploy):

```env
CXP_API_KEY_GEO=
CXP_API_KEY_RATE=
CXP_API_KEY_OT=
```

3. Pantalla del plugin: *Chilexpress → Habilitación de Módulos*

Portal: https://developers.wschilexpress.com/new-products  
Base staging: `https://qaservices.wschilexpress.com/`  
Header: `Ocp-Apim-Subscription-Key`

Si no pegas keys, el laboratorio usa las de *ejemplo* que trae el ZIP oficial 1.4.0 (pueden no cotizar en tu cuenta).

## Semilla de tienda (datos de prueba del plugin)

| Campo | Valor |
|---|---|
| Ambiente | staging |
| TCC | `18578680` |
| RUT | `96756430` |
| Origen | RM · PROVIDENCIA (`PROV`) |
| Calle | Avenida Providencia 1208, Oficina 302 |

## Checkout de prueba

Juan Espoz · RM · LA REINA (`LARE`) · Avenida Larrain 5862 · tarjeta `4242 4242 4242 4242` / 12/34 / 123.

## SQLite

`wordpress/wp-content/database/.ht.sqlite` (no versionado).  
`wp-config.php` se copia desde `wordpress/wp-config.sample.php`.
