# Despliegue manual en Dokploy

Esta guía asume que publicas el repo **público** [alexcautivo/chilexpress-woo-replica](https://github.com/alexcautivo/chilexpress-woo-replica) y creas la aplicación a mano en [Dokploy](https://dokploy.com/).

Autor del laboratorio: **Alexander Alejandro Cautivo Ramos** · [alexander.cautivo@aeolabs.io](mailto:alexander.cautivo@aeolabs.io)

---

## Qué debe quedar en Dokploy

| Campo Dokploy | Valor |
|---|---|
| Provider | GitHub · este repositorio |
| Tipo | **Docker Compose** (recomendado) o **Dockerfile** |
| Compose file | `docker-compose.dokploy.yml` |
| Dockerfile (si no usas compose) | `docker/Dockerfile` |
| Puerto interno | **80** (Apache) |
| Dominio | el que asigne Dokploy o tu DNS |
| HTTPS | el proxy de Dokploy (Let's Encrypt) |

La imagen **ya incluye** WordPress 7.0.3, Woo 11.0.1, Chilexpress 1.4.0 y los MU-plugins. No hace falta montar el código desde el host.

Persistencia:

- volumen `wp_database` → SQLite (`wp-content/database`)
- volumen `wp_uploads` → medios

Si recreas el deploy **sin** esos volúmenes, la tienda vuelve a semilla vacía.

---

## Paso a paso (Compose)

1. En Dokploy: **Create Service** → **Compose**.
2. Conecta GitHub y elige `alexcautivo/chilexpress-woo-replica`, rama `main`.
3. Compose path: `docker-compose.dokploy.yml`.
4. Build context: la raíz del repo (donde está `docker/Dockerfile`).
5. **Environment** (obligatorio y recomendado):

```env
WP_HOME=https://tu-dominio.ejemplo
CXP_AUTO_LOGIN=0
DB_ENGINE=sqlite
PHP_VERSION=8.4.19
CXP_API_KEY_GEO=
CXP_API_KEY_RATE=
CXP_API_KEY_OT=
```

`WP_HOME` **tiene que ser la URL pública exacta** (https, sin barra final). Si no, permalinks, checkout y cookies se rompen.

6. Asigna el dominio al servicio. Puerto del contenedor: `80`.
7. Deploy. Espera el healthcheck (`curl` a `/`).
8. Abre `https://tu-dominio/wp-admin/` e inicia sesión:
   - usuario `admin`
   - contraseña `admin` (cámbiala en Usuarios de WordPress en cuanto el sitio sea alcanzable)

9. Abre la **Consola réplica** y verifica PHP 8.4.x / WP 7.0.3 / WC 11.0.1 / Chilexpress 1.4.0.

---

## Paso a paso (solo Dockerfile)

Si Dokploy no usa compose:

1. Tipo **Application** → Dockerfile.
2. Dockerfile: `docker/Dockerfile`.
3. Mismas variables de entorno.
4. Puerto **80**.
5. Añade volúmenes persistentes a mano:
   - `/var/www/html/wp-content/database`
   - `/var/www/html/wp-content/uploads`

Sin el volumen de database, SQLite se pierde en cada redeploy.

---

## Variables

| Variable | Default local | En Dokploy |
|---|---|---|
| `WP_HOME` | `http://127.0.0.1:8080` | `https://tu-dominio` |
| `CXP_AUTO_LOGIN` | `1` (localhost) | **`0`** |
| `DB_ENGINE` | `sqlite` | `sqlite` (o `mysql` + profile) |
| `PHP_VERSION` | `8.4.19` | `8.4.19` (rebuild si la cambias) |
| `CXP_API_KEY_GEO` | vacío | key staging cobertura |
| `CXP_API_KEY_RATE` | vacío | key staging cotizador |
| `CXP_API_KEY_OT` | vacío | key staging OT |
| `CXP_REMOTE_SHOP_URL` | vacío | opcional, otro WP de prueba |
| `WORDPRESS_PORT` | `8080` | Dokploy suele ignorarlo y usar su proxy |

MySQL: en compose local `docker compose --profile mysql up`. En Dokploy, si quieres MariaDB, añade el profile/servicio `db` y `DB_ENGINE=mysql`. El laboratorio está pensado en **SQLite** para un contenedor único.

---

## Después del primer deploy

1. **Cambia la contraseña** de `admin`.
2. Pega las API keys (consola → APIs, o env y luego «Cargar keys del entorno»).
3. Checkout: sigue siendo clásico. Prueba **Llenar datos válidos**.
4. No actives auto-login (`CXP_AUTO_LOGIN=1`) salvo en una red privada.

Rebuild cuando cambies `PHP_VERSION` o el Dockerfile. Un push a `main` + Redeploy en Dokploy actualiza MU-plugins y docs; los volúmenes conservan pedidos y media.

---

## Problemas frecuentes

| Síntoma | Qué revisar |
|---|---|
| CSS/admin en HTTP mezclado | `WP_HOME` no es `https://tu-dominio` |
| Loop de login / cookies | `WP_HOME` distinto al dominio del proxy |
| Consola dice otra WP | **Volver a versiones del cliente** (necesita red a wordpress.org y disco escribible) |
| Cotizador no cotiza | Keys vacías o ambiente production sin keys de prod |
| Sitio en blanco tras «Replicar caída» | `https://tu-dominio/__sr108688/restore` |
| Documentos «No está …md» | La imagen debe copiar `docs/` a `/var/www/docs` (`CXP_DOCS_DIR`) |

---

## Local vs Dokploy

| | Local (`docker compose`) | Dokploy (`docker-compose.dokploy.yml`) |
|---|---|---|
| Código | bind-mount `./wordpress` | copiado en la imagen |
| Auto-login | sí (localhost) | no |
| Puerto | 8080→80 | proxy → 80 |
| Datos | carpeta del repo | volúmenes Docker |

Los dos usan el mismo `docker/Dockerfile`.
