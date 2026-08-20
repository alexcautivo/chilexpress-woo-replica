# Credenciales — réplica Chilexpress Woo

Copiadas el 2026-08-20. Ticket **SR-108688** (celularesenventa.cl).

Documentos para el cliente (diagnóstico, respuesta, instrucciones): carpeta `docs/` o consola réplica → **Documentos para el cliente**. Guía de arranque: [`README.md`](README.md).

## WordPress local (esta máquina)

| | |
|---|---|
| Sitio | http://127.0.0.1:8080 |
| Admin | http://127.0.0.1:8080/wp-admin/ |
| Usuario | `admin` |
| Contraseña | `admin` |
| Email | `alexander.cautivo+testwordpress@aeolabs.io` |
| Título del sitio | Celulares, Tablets Rugged y Notebooks Resistentes en Chile |
| Tema | Woodmart Child 1.0.0 · padre Woodmart 8.5.7 |
| Auto-login | Sí, en `/wp-admin` (MU-plugin `cxp-auto-login.php`) |

Arranque: `bash start.sh`

## WordPress publicado (clon de prueba)

| | |
|---|---|
| Sitio | https://chilexpress-woo-test.5-78-137-25.sslip.io/ |
| Admin | https://chilexpress-woo-test.5-78-137-25.sslip.io/wp-admin/ |
| Pedidos | https://chilexpress-woo-test.5-78-137-25.sslip.io/wp-admin/admin.php?page=wc-orders |
| Módulos / API keys | https://chilexpress-woo-test.5-78-137-25.sslip.io/wp-admin/admin.php?page=chilexpress_woo_oficial_menu |
| Usuario | `admin` |
| Contraseña | `OxFpjdVhI35Aq9d1eHK` |
| WordPress | 7.1 |
| WooCommerce | 11.0.1 |
| Chilexpress Oficial | 1.4.0 |
| Tema | Woodmart Child 1.0.0 |
| Idioma | es_CL |

La pantalla **Configuración General** del remoto devolvió error crítico (no se pudo copiar TCC/RUT desde ahí). Las API keys sí se copiaron desde **Habilitación de Módulos**.

## APIs Chilexpress (staging)

Portal: https://developers.wschilexpress.com/new-products  
Perfil: https://developers.wschilexpress.com/developer  
Header: `Ocp-Apim-Subscription-Key`  
Base staging: `https://qaservices.wschilexpress.com/`

### Keys del WordPress publicado (las que usa el local ahora)

Copiadas de Habilitación de Módulos, ambiente **staging**, los 3 módulos habilitados.

| Producto | Campo del plugin | API KEY |
|---|---|---|
| Cobertura / Georeferencia | `api_key_georeferencia_value` | `a6979b4160c6465f85776f43b6c40ffb` |
| Cotizador | `api_key_cotizador_value` | `6a144300d4a54800ad354078c1a536d4` |
| Envíos / OT | `api_key_generacion_ot_value` | `5a77a19b76a24297ba01c158286641b7` |

### Keys por defecto del ZIP oficial 1.4.0

El plugin las muestra en el formulario si el option está vacío. No son las del shop publicado (salvo OT, que coincide).

| Producto | API KEY |
|---|---|
| Cobertura | `134b01b545bc4fb29a994cddedca9379` |
| Cotizador | `fd46aa18a9fe44c6b49626692605a2e8` |
| Envíos / OT | `5a77a19b76a24297ba01c158286641b7` |

Key extra embebida en el plugin para GetArticulos / Día Hábil: `9c853753ce314c81934c4f966dad7755`

## Chilexpress — datos de tienda (semilla local)

No se pudieron leer del remoto (fatal en Configuración General). Semilla oficial de prueba:

| Campo | Valor |
|---|---|
| Ambiente | `staging` |
| TCC | `18578680` |
| RUT seller / marketplace | `96756430` (sin puntos, sin DV) |
| Origen / devolución | RM · PROVIDENCIA (`PROV`) |
| Calle | Avenida Providencia 1208, Oficina 302 |
| Remitente | Tienda Replica Providencia |
| Teléfono remitente | `222234567` |
| Email remitente | `alexander.cautivo+testwordpress@aeolabs.io` |
| Dimensiones default | 20 × 15 × 10 cm, 0.8 kg |

## Productos wiki (kg / cm)

| Producto | Precio | Peso | L×An×Al |
|---|---|---|---|
| Audífonos Bluetooth | 19990 | 0.2 kg | 15×10×5 |
| Teclado mecánico | 49990 | 0.9 kg | 45×15×4 |
| Monitor 24 pulgadas | 129990 | 3.5 kg | 60×40×15 |
| Notebook 15 pulgadas | 549990 | 2.2 kg | 40×30×8 |
| Silla ergonómica | 189990 | 12 kg | 70×65×30 |

| Campo | Valor |
|---|---|
| Nombre | Juan Espoz |
| Email | `alexander.cautivo+testwordpress@aeolabs.io` |
| Teléfono | `912345678` |
| Región | RM |
| Comuna | `LA REINA` (código Chilexpress `LARE`) |
| Calle | Avenida Larrain |
| Número | `5862` (solo dígitos) |
| Complemento | Casa |
| Tarjeta debug | `4242 4242 4242 4242` · 12/34 · CVC 123 |
| Tarjeta alt | `4111 1111 1111 1111` |
| Pago extra | Contra entrega (COD) |

## SQLite local (no es MariaDB)

`wordpress/wp-content/database/.ht.sqlite`  
`wp-config.php` no se versiona. Copia desde `wordpress/wp-config.sample.php`.

DB sample del archivo:

| | |
|---|---|
| DB_NAME | `wordpress` |
| DB_USER | `wordpress` |
| DB_PASSWORD | `wordpress` |
| Motor real | SQLite |

## GitHub

https://github.com/alexcautivo/chilexpress-woo-replica

Repo privado. Este archivo tiene contraseñas reales del WordPress publicado.
