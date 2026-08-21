#!/bin/bash
set -euo pipefail

cd /var/www/html

if [ ! -f wp-config.php ] && [ -f wp-config.sample.php ]; then
  cp wp-config.sample.php wp-config.php
fi

if [ -f /usr/local/bin/patch-config.php ]; then
  php /usr/local/bin/patch-config.php || true
elif [ -f docker/patch-config.php ]; then
  php docker/patch-config.php || true
fi

mkdir -p wp-content/database wp-content/uploads wp-content/cache

# Dokploy monta /var/www/incidents en un volumen persistente. Refresca el
# contrato y la documentación del laboratorio, pero no pisa tickets/runs
# creados por el operador.
INCIDENT_SEED=/var/www/seed-incidents
INCIDENT_DIR=/var/www/incidents
if [ -d "$INCIDENT_SEED" ]; then
  mkdir -p "$INCIDENT_DIR"/{schema,templates,tickets,planes,runs}
  cp -a "$INCIDENT_SEED"/schema/. "$INCIDENT_DIR"/schema/ 2>/dev/null || true
  cp -a "$INCIDENT_SEED"/templates/. "$INCIDENT_DIR"/templates/ 2>/dev/null || true
  cp -an "$INCIDENT_SEED"/tickets/. "$INCIDENT_DIR"/tickets/ 2>/dev/null || true
  cp -an "$INCIDENT_SEED"/planes/. "$INCIDENT_DIR"/planes/ 2>/dev/null || true
  cp -a "$INCIDENT_SEED"/README.md "$INCIDENT_DIR"/README.md 2>/dev/null || true
fi

SEED=/var/www/seed/.ht.sqlite
DB=wp-content/database/.ht.sqlite
if [ -f "$SEED" ] && [ ! -s "$DB" ]; then
  cp "$SEED" "$DB"
fi

chown -R www-data:www-data wp-content/database wp-content/uploads wp-content/cache 2>/dev/null || true
chown -R www-data:www-data "$INCIDENT_DIR" 2>/dev/null || true

exec "$@"
