# Replica Docker — Informe tecnico celularesenventa.cl

Entorno aislado segun el informe del 2026-08-20. **No usa el PHP portable anterior.**

## Pila (seccion 1 del informe)

| Componente | Version |
|---|---|
| Imagen WordPress | `wordpress:7.0.4-php8.4-apache` (`wordpress:php8.4-apache` en el informe; se fija 7.0.4) |
| PHP | 8.4.x (rama 8.4, como produccion 8.4.19) |
| WordPress core | 7.0.4 (bundle de la imagen; produccion era 7.0.3) |
| Base de datos | `mariadb:11` |
| WooCommerce | 11.0.1 (wordpress.org) |
| Chilexpress | Oficial v1.4.0 (`woocommerce-plugin-1.4.0-RELEASE.zip`, sin modificar) |
| WP-CLI | `wordpress:cli-php8.4` |
| Puerto | 8080 |

Tema Woodmart Child: no se instala. El error ocurre en `plugins_loaded`, independiente del tema.

## Requisitos

Docker Desktop (con WSL2 en Windows). En esta maquina no estaba instalado.

## Levantar

```bash
bash setup.sh
```

Eso hace exactamente los pasos 1-3 del informe:

1. `docker compose up -d` (db, wordpress, wpcli)
2. `wp core install`
3. `wp plugin install woocommerce --version=11.0.1 --activate`
4. Copia/activa el ZIP real del cliente en `chilexpress-oficial/`

Sitio: http://127.0.0.1:8080  
Admin: http://127.0.0.1:8080/wp-admin  (`admin` / `admin`)

## Clonar (GitHub)

```bash
git clone https://github.com/alexcautivo/chilexpress-woo-replica.git
cd chilexpress-woo-replica
cp wordpress/wp-config.sample.php wordpress/wp-config.php
bash start.sh
```

No se versionan PHP portable (`runtime/`), ZIPs (`downloads/`), SQLite ni `wp-config.php`.


## Que esperar (seccion 2.4)

Con archivos intactos el sitio **no falla**. No es incompatibilidad dura de version.

Para la ventana de actualizacion in-place (secciones 2.8 / 3.4):

```bash
bash replicate-error.sh
```

Equivale a `wp plugin install woocommerce --version=11.0.1 --force` con Chilexpress activo.
