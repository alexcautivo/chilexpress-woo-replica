#!/usr/bin/env bash
# Resuelve CXP_PHP / CXP_INI / CXP_PHP_VER desde PHP_VERSION o runtime/.php-version

cxp_php_setup() {
  local root="${1:?}"
  local ver="${PHP_VERSION:-}"
  if [ -z "$ver" ] && [ -f "$root/runtime/.php-version" ]; then
    ver="$(tr -d ' \t\r\n' < "$root/runtime/.php-version")"
  fi
  ver="${ver:-8.4.19}"
  CXP_PHP_VER="$ver"
  CXP_PHP=""
  CXP_INI=""

  if [ -x "$root/runtime/php-$ver/php.exe" ]; then
    CXP_PHP="$root/runtime/php-$ver/php.exe"
    CXP_INI="$root/runtime/php-$ver/php.ini"
    export PHPRC="$root/runtime/php-$ver"
    return 0
  fi
  if [ -x "$root/runtime/php-$ver/php" ]; then
    CXP_PHP="$root/runtime/php-$ver/php"
    CXP_INI="$root/runtime/php-$ver/php.ini"
    export PHPRC="$root/runtime/php-$ver"
    return 0
  fi
  if command -v php >/dev/null 2>&1; then
    CXP_PHP="$(command -v php)"
    CXP_INI=""
    echo "AVISO: no hay runtime/php-$ver. Usando PHP del sistema ($("$CXP_PHP" -r 'echo PHP_VERSION;' 2>/dev/null || echo '?'))."
    echo "Para la versión exacta: consola réplica → PHP → Preparar esta PHP, o bash tools/fetch-php.sh $ver"
    return 0
  fi
  echo "No hay PHP $ver en runtime/php-$ver/ ni php en el PATH."
  echo "Consola réplica → Versiones / recarga → PHP → Preparar esta PHP"
  echo "O: bash tools/fetch-php.sh $ver"
  return 1
}
