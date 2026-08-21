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

# Dokploy monta /var/www/incidents en un volumen vacio: hay que sembrarlo.
# En local esa ruta es un bind-mount del repo, asi que la siembra NUNCA
# sobrescribe: solo rellena lo que falta (cp -n). Pisar aqui destruiria el
# arbol de trabajo del operador.
INCIDENT_SEED=/var/www/seed-incidents
INCIDENT_DIR=/var/www/incidents
if [ -d "$INCIDENT_SEED" ]; then
  mkdir -p "$INCIDENT_DIR"/schema "$INCIDENT_DIR"/templates "$INCIDENT_DIR"/tickets "$INCIDENT_DIR"/planes "$INCIDENT_DIR"/runs
  for part in schema templates tickets planes; do
    cp -an "$INCIDENT_SEED/$part/." "$INCIDENT_DIR/$part/" 2>/dev/null || true
  done
  cp -an "$INCIDENT_SEED"/README.md "$INCIDENT_DIR"/README.md 2>/dev/null || true
fi

SEED=/var/www/seed/.ht.sqlite
DB=wp-content/database/.ht.sqlite
if [ -f "$SEED" ] && [ ! -s "$DB" ]; then
  cp "$SEED" "$DB"
fi

chown -R www-data:www-data wp-content/database wp-content/uploads wp-content/cache 2>/dev/null || true
chown -R www-data:www-data "$INCIDENT_DIR" 2>/dev/null || true

exec "$@"
