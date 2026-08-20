#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
PHP="$ROOT/runtime/php-8.4.19/php.exe"
INI="$ROOT/runtime/php-8.4.19/php.ini"
export PHPRC="$ROOT/runtime/php-8.4.19"

cd "$ROOT"

echo "PHP $($PHP -c "$INI" -r 'echo PHP_VERSION;')"
"$PHP" -c "$INI" install-wp.php

echo
echo "Levantando WordPress en http://127.0.0.1:8080"
echo "Admin: http://127.0.0.1:8080/wp-admin/   (admin / admin)"
echo "Pedidos: http://127.0.0.1:8080/wp-admin/admin.php?page=wc-orders"
echo "Chilexpress APIs: http://127.0.0.1:8080/wp-admin/admin.php?page=chilexpress_woo_oficial_menu"
echo "Ctrl+C para detener."
echo

cd "$ROOT/wordpress"
exec "$PHP" -c "$INI" -S 127.0.0.1:8080 router.php
