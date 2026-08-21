# Guía de uso — laboratorio WordPress + Chilexpress

Laboratorio para reproducir incidencias de WooCommerce (cualquier cliente). El caso de referencia es Chilexpress Oficial 1.4.0 + Woo 11 (`ProductTaxStatus` no encontrado), ticket **SR-108688** ([celularesenventa.cl](https://celularesenventa.cl)).

Autor: **Alexander Alejandro Cautivo Ramos** · [alexander.cautivo@aeolabs.io](mailto:alexander.cautivo@aeolabs.io)

No es un hosting de producción. Chilexpress Oficial **no se parchea** aquí: el laboratorio enseña *por qué* cae y *cómo* volver a un Woo completo.

---

## Qué tienes delante

```
┌─────────────────────────────────────────────────────────┐
│  Tienda (Woodmart Child)  ·  Shop  ·  Cart  ·  Checkout │
│                                                         │
│  Checkout CLÁSICO [woocommerce_checkout]                │
│  Botón amarillo «Llenar datos válidos»                  │
├─────────────────────────────────────────────────────────┤
│  ▸ CONSOLA RÉPLICA  (barra negra inferior, clic)        │
│    Incidencias · Documentos · Pila · SR-108688 · Lab    │
│    APIs · Pedidos / OT · Plugins · Créditos             │
└─────────────────────────────────────────────────────────┘
```

| Pieza | Para qué |
|---|---|
| WordPress 7.0.3 + Woo 11.0.1 + PHP 8.4.19 | Misma pila que el correo del cliente |
| Chilexpress Oficial 1.4.0 intacto | El plugin que dispara el fatal en el update |
| MU-plugins `cxp-*` | Laboratorio: consola, semilla, checkout, docs |
| Consola réplica | El tablero de toda la app de pruebas |
| Docker / Dokploy | El mismo árbol, desplegable a un dominio |

---

## Arranque rápido (Windows / Git Bash)

Hace falta PHP **8.4.19** (en este repo, `runtime/php-8.4.19/` no va en git: es el binario portable local).

```bash
git clone https://github.com/alexcautivo/chilexpress-woo-replica.git
cd chilexpress-woo-replica
cp wordpress/wp-config.sample.php wordpress/wp-config.php
bash start.sh
```

Abre **http://127.0.0.1:8080**

| | |
|---|---|
| Admin | http://127.0.0.1:8080/wp-admin/  *(barra final)* |
| Usuario / clave | `admin` / `admin` |
| Auto-login | Sí, solo si la URL es localhost (o `CXP_AUTO_LOGIN=1`) |

Linux/macOS con PHP 8.4.19 del sistema:

```bash
cp wordpress/wp-config.sample.php wordpress/wp-config.php
php install-wp.php
cd wordpress && php -S 127.0.0.1:8080 router.php
```

## Arranque con Docker (misma máquina)

```bash
cp .env.example .env
docker compose -f docker-compose.local.yml up --build
```

Sigue en http://127.0.0.1:8080. Detalle de variables: [dokploy.md](dokploy.md) (también vale en local).

---

## Recorrido de una sesión de prueba

### 1. Mira la pila

Abre la **Consola réplica** (barra inferior). Debe decir algo como:

`PHP 8.4.19 · WP 7.0.3 · WC 11.0.1 · Chilexpress 1.4.0 · Woodmart Child 1.0.0`

Si no coincide: panel **Pila / versiones** → **Volver a versiones del cliente**.

### 2. Recorre la tienda

- [Shop](http://127.0.0.1:8080/shop/) — catálogo wiki (kg/cm): audífonos, teclado, monitor, notebook, silla.
- Añade el **teclado** (0,9 kg, 45×15×4 cm) al carrito.
- Ve a **Checkout**. Si el formulario se ve raro: botón **Llenar datos válidos** (barra amarilla).
- Destino esperado: RM · **LA REINA** · Avenida Larrain **5862**.
- Cotizador staging (si hay API keys): PREX / CHEX reales, no cifras inventadas.
- Pago: tarjeta debug `4242 4242 4242 4242` / 12/34 / 123.

**No cambies el checkout a Woo Blocks.** El JS oficial de Chilexpress solo habla con `#billing_state` / `#billing_city`.

### 3. Genera una OT

Tras el pedido: consola → **Pedidos locales** o Editar pedido → **Generar OT** (botón amarillo). En HPOS el listado clásico a veces no muestra el botón del plugin; por eso existe el atajo de la consola.

### 4. Reproduce el fatal del cliente (opcional)

Consola → **SR-108688** → Replicar caída exacta. El sitio puede quedar en blanco en `admin-ajax.php`. Emergencia:

http://127.0.0.1:8080/__sr108688/restore

Eso **esconde/restaura** el enum de Woo; no toca PHP de Chilexpress.

### 5. Genera documentos para el ticket

Consola → **Documentos para el cliente**. Cada botón copia Markdown con las versiones *actuales* de este contenedor.

### 6. Reproduce otra incidencia

1. Consola → **Incidencias** → copia la plantilla JSON 1.1.
2. Importa la respuesta del cliente con **Crear ticket**. Esto no modifica el sitio.
3. Revisa **Vista previa** y entrega en `drop-plugins/` los ZIP privados autorizados que indique el ticket.
4. Pulsa **Aplicar pila**. La consola guarda un snapshot e instala WordPress, plugins y tema en las versiones exactas.
5. Si pide otra PHP, reinicia `start.sh` o reconstruye Docker y vuelve a pulsar **Aplicar pila** para continuar el mismo run.
6. Pulsa **Ejecutar flujo**. Se abre una pestaña de Chrome y se capturan errores de navegador, HTTP y `debug.log`.
7. Revisa **Resultado**: reportado vs reproducido, marcadores que coinciden y causa probable basada en reglas.
8. Descarga **PDF cliente** o **PDF técnico** y termina con **Restaurar snapshot**.

Los pasos del JSON son declarativos y limitados al mismo sitio del laboratorio. No se ejecuta PHP ni shell enviado por el cliente.

---

## APIs Chilexpress (staging)

Portal: https://developers.wschilexpress.com/new-products  
Header: `Ocp-Apim-Subscription-Key`  
Base: `https://qaservices.wschilexpress.com/`

Pega las keys en la consola (panel APIs) o en Dokploy:

| Variable | Producto |
|---|---|
| `CXP_API_KEY_GEO` | Cobertura / georeferencia |
| `CXP_API_KEY_RATE` | Cotizador |
| `CXP_API_KEY_OT` | Envíos / OT |

Semilla de tienda (datos de prueba del plugin, no secretos de producción): TCC `18578680`, RUT `96756430`, origen Providencia (`PROV`).

---

## Qué no hace este laboratorio

- No parchea `chilexpress-oficial`.
- No envía correo real con `php -S` (no hay MTA).
- SQLite no corre el SQL de *reserved stock* de Woo: hold de checkout = 0 minutos.
- PHP se cambia desde la consola (**Preparar esta PHP**) y reiniciando `start.sh`, o en Docker (`.env` → `PHP_VERSION` + rebuild).

Manual de cada botón: [consola-replica.md](consola-replica.md).  
Despliegue en Dokploy: [dokploy.md](dokploy.md).  
Incidencias (JSON → ticket, planes): carpeta `incidents/` del repo.
