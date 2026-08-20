#!/usr/bin/env bash
# Instala WordPress + WooCommerce 11.0.1 + Chilexpress Oficial 1.4.0
# segun el informe tecnico (secciones 1 y 2).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker no esta instalado. Instala Docker Desktop y vuelve a correr este script."
  exit 1
fi

if [ ! -f chilexpress-oficial/chilexpress-woo-oficial.php ]; then
  echo "Extrayendo plugin real del cliente..."
  unzip -o drop-plugins/woocommerce-plugin-1.4.0-RELEASE.zip -d .
fi

echo "Levantando db + wordpress + wpcli..."
docker compose up -d

echo "Esperando a que WordPress copie sus archivos..."
for i in $(seq 1 60); do
  if docker compose exec -T wpcli wp core is-installed >/dev/null 2>&1; then
    break
  fi
  if docker compose exec -T wpcli test -f wp-settings.php >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

if ! docker compose exec -T wpcli wp core is-installed >/dev/null 2>&1; then
  echo "Instalando WordPress..."
  docker compose exec -T wpcli wp core install \
    --url="http://127.0.0.1:8080" \
    --title="Replica Chilexpress SR-108688" \
    --admin_user="admin" \
    --admin_password="admin" \
    --admin_email="admin@local.test" \
    --skip-email
fi

echo "Instalando WooCommerce 11.0.1 (wordpress.org)..."
docker compose exec -T wpcli wp plugin install woocommerce --version=11.0.1 --activate --force

echo "Activando Chilexpress Oficial 1.4.0 (plugin real del cliente, sin modificar)..."
docker compose exec -T wpcli wp plugin activate chilexpress-oficial

echo
echo "=== Versiones ==="
docker compose exec -T wordpress php -v | head -1
docker compose exec -T wpcli wp core version
docker compose exec -T wpcli wp plugin get woocommerce --field=version
docker compose exec -T wpcli wp plugin get chilexpress-oficial --field=version
echo
echo "Sitio:  http://127.0.0.1:8080"
echo "Admin:  http://127.0.0.1:8080/wp-admin   (admin / admin)"
echo
echo "Prueba 1 del informe: con archivos intactos el sitio NO debe fallar."
echo "Para replicar la ventana de actualizacion: bash replicate-error.sh"
