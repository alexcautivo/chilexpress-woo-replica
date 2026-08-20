#!/usr/bin/env bash
# Seccion 2.8 / 3.4 del informe: reinstalacion real de WooCommerce 11.0.1
# (equivalente a una actualizacion in-place) con Chilexpress 1.4.0 activo.
set -euo pipefail
cd "$(dirname "$0")"

echo "Reinstalando WooCommerce 11.0.1 --force con Chilexpress activo..."
set +e
docker compose exec -T wpcli wp plugin install woocommerce --version=11.0.1 --force --activate
status=$?
set -e

echo
echo "HTTP portada:"
curl -sI --max-time 20 http://127.0.0.1:8080/ | head -15
echo
echo "HTTP admin-ajax.php:"
curl -sI --max-time 20 http://127.0.0.1:8080/wp-admin/admin-ajax.php | head -15
echo
echo "Logs del contenedor wordpress (ultimas lineas):"
docker compose logs --tail=40 wordpress
exit "$status"
