#!/usr/bin/env bash
# Baja PHP NTS x64 de windows.php.net a runtime/php-VERSION/ (Windows / Git Bash).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VER="${1:-}"
if [ -z "$VER" ]; then
  echo "Uso: bash tools/fetch-php.sh 8.3.33"
  echo "Luego: bash start.sh"
  exit 1
fi
VER="$(echo "$VER" | tr -d ' vV')"
DEST="$ROOT/runtime/php-$VER"
mkdir -p "$ROOT/runtime"

if [ -x "$DEST/php.exe" ]; then
  printf '%s\n' "$VER" > "$ROOT/runtime/.php-version"
  echo "Ya está $DEST"
  echo "Reinicia: Ctrl+C el servidor y bash start.sh"
  exit 0
fi

TMP="$(mktemp -d)"
cleanup() { rm -rf "$TMP"; }
trap cleanup EXIT

ok=0
for vs in vs17 vs16 vc15; do
  name="php-${VER}-nts-Win32-${vs}-x64.zip"
  for base in \
    "https://windows.php.net/downloads/releases" \
    "https://windows.php.net/downloads/releases/archives"; do
    url="$base/$name"
    echo "Probando $url"
    if curl -fL --retry 2 --connect-timeout 20 -o "$TMP/php.zip" "$url"; then
      ok=1
      break 2
    fi
  done
done

if [ "$ok" -ne 1 ]; then
  echo "No se encontró el ZIP de PHP $VER en windows.php.net (NTS x64)."
  echo "Prueba una versión publicada, p. ej. 8.2.33  8.3.33  8.4.19  8.4.24  8.5.9"
  exit 1
fi

mkdir -p "$DEST"
unzip -qo "$TMP/php.zip" -d "$DEST"

if [ ! -f "$DEST/php.ini" ]; then
  if [ -f "$DEST/php.ini-development" ]; then
    cp "$DEST/php.ini-development" "$DEST/php.ini"
  elif [ -f "$DEST/php.ini-production" ]; then
    cp "$DEST/php.ini-production" "$DEST/php.ini"
  fi
fi

if [ -f "$DEST/php.ini" ]; then
  # extension_dir relativo a PHPRC
  if grep -qE '^extension_dir' "$DEST/php.ini"; then
    sed -i 's#^extension_dir.*#extension_dir = "ext"#' "$DEST/php.ini"
  else
    printf '\nextension_dir = "ext"\n' >> "$DEST/php.ini"
  fi
  for ext in curl fileinfo gd intl mbstring exif openssl pdo_sqlite sodium sqlite3 zip; do
    sed -i "s/^;extension=${ext}$/extension=${ext}/" "$DEST/php.ini"
  done
fi

if [ ! -x "$DEST/php.exe" ]; then
  echo "El ZIP no dejó php.exe en $DEST"
  exit 1
fi

printf '%s\n' "$VER" > "$ROOT/runtime/.php-version"
echo "PHP $VER listo en $DEST"
echo "Reinicia: Ctrl+C el servidor actual y corre bash start.sh"
