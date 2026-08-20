#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=tools/php-runtime.sh
. "$ROOT/tools/php-runtime.sh"
cxp_php_setup "$ROOT" || exit 1
PHP="$CXP_PHP"
INI="${CXP_INI:-}"

cd "$ROOT"

if [ -n "$INI" ]; then
  echo "PHP $($PHP -c "$INI" -r 'echo PHP_VERSION;')  (runtime $CXP_PHP_VER)"
  "$PHP" -c "$INI" install-wp.php
else
  echo "PHP $($PHP -r 'echo PHP_VERSION;')"
  "$PHP" install-wp.php
fi

echo
echo "Levantando WordPress en http://127.0.0.1:8080"
echo "Admin: http://127.0.0.1:8080/wp-admin/   (admin / admin)"
echo "Pedidos: http://127.0.0.1:8080/wp-admin/admin.php?page=wc-orders"
echo "Chilexpress APIs: http://127.0.0.1:8080/wp-admin/admin.php?page=chilexpress_woo_oficial_menu"
echo "PHP: cambia la versión en la consola réplica o PHP_VERSION=8.3.33 bash start.sh"
echo "Ctrl+C para detener."
echo

cd "$ROOT/wordpress"
if [ -n "$INI" ]; then
  exec "$PHP" -c "$INI" -S 127.0.0.1:8080 router.php
fi
exec "$PHP" -S 127.0.0.1:8080 router.php
