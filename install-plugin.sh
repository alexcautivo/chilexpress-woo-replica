#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
PHP="$ROOT/runtime/php-8.4.19/php.exe"
INI="$ROOT/runtime/php-8.4.19/php.ini"
export PHPRC="$ROOT/runtime/php-8.4.19"

cd "$ROOT"

echo "Instalando plugins desde drop-plugins/ ..."
shopt -s nullglob
found=0
for zip in "$ROOT/drop-plugins"/*.zip "$ROOT/drop-plugins"/*.ZIP; do
  found=1
  echo "  -> $(basename "$zip")"
  "$PHP" -c "$INI" -r "
    \$zip = new ZipArchive();
    if (\$zip->open('$zip') !== true) { fwrite(STDERR, 'No se pudo abrir el zip\n'); exit(1); }
    \$zip->extractTo('$ROOT/wordpress/wp-content/plugins');
    \$zip->close();
  "
done

if [ "$found" -eq 0 ]; then
  echo "No hay ZIP en drop-plugins/."
  echo "Copia ahi el plugin Chilexpress (chilexpress-oficial / chilexpress-woo-oficial) y vuelve a ejecutar este script."
  exit 0
fi

echo "Listo. Reinicia el servidor (start.sh) y recarga wp-admin para activarlo, o corre:"
echo "  $PHP -c $INI install-wp.php"
