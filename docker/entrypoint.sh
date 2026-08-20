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
chown -R www-data:www-data wp-content/database wp-content/uploads wp-content/cache 2>/dev/null || true

exec "$@"
