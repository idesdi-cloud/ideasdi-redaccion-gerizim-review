#!/usr/bin/env bash

set -u

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

FAILURES=0
PHP_TOTAL=0
TEST_TOTAL=0
TEST_LOG="$(mktemp /tmp/gerizim-test.XXXXXX)"

cleanup() {
  rm -f "$TEST_LOG"
}
trap cleanup EXIT

if ! command -v php >/dev/null 2>&1; then
  echo "ERROR: PHP CLI no está disponible."
  exit 1
fi

printf '%s\n' "=== Sintaxis PHP ==="

while IFS= read -r -d '' FILE; do
  PHP_TOTAL=$((PHP_TOTAL + 1))

  if ! php -l "$FILE" >/dev/null 2>&1; then
    echo "FAIL: sintaxis $FILE"
    php -l "$FILE" 2>&1 || true
    FAILURES=$((FAILURES + 1))
  fi
done < <(
  find . \
    -path './.git' -prune -o \
    -type f -name '*.php' -print0
)

echo "Archivos PHP revisados: $PHP_TOTAL"

printf '\n%s\n' "=== Pruebas PHP ==="

while IFS= read -r -d '' TEST_FILE; do
  TEST_TOTAL=$((TEST_TOTAL + 1))
  TEST_NAME="$(basename "$TEST_FILE")"

  : > "$TEST_LOG"

  if php "$TEST_FILE" >"$TEST_LOG" 2>&1; then
    echo "PASS: $TEST_NAME"
  else
    echo "FAIL: $TEST_NAME"
    cat "$TEST_LOG"
    FAILURES=$((FAILURES + 1))
  fi
done < <(
  find tests \
    -maxdepth 1 \
    -type f \
    -name '*.php' \
    -print0 |
  sort -z
)

echo
echo "Pruebas ejecutadas: $TEST_TOTAL"

printf '\n%s\n' "=== Regresión editorial ==="

VERSION="$(
  awk -F': ' \
    '/^[[:space:]]*\*[[:space:]]*Version:/ {
      print $2
      exit
    }' \
    ideasdi-redaccion-gerizim.php |
  tr -d '\r'
)"

RELEASE="${VERSION#0.4.0-}"
MANIFEST="REGRESION-EDITORIAL-${RELEASE}.sha256"

echo "Versión detectada: ${VERSION:-NO DETECTADA}"
echo "Manifiesto esperado: $MANIFEST"

if [ -f "$MANIFEST" ]; then
  if sha256sum -c "$MANIFEST"; then
    echo "REGRESION_EDITORIAL_OK"
  else
    echo "FAIL: regresión editorial"
    FAILURES=$((FAILURES + 1))
  fi
else
  echo "FAIL: no existe $MANIFEST"
  FAILURES=$((FAILURES + 1))
fi

printf '\n%s\n' "=== Resultado ==="

if [ "$FAILURES" -eq 0 ]; then
  echo "VALIDACION_GERIZIM_OK"
  exit 0
fi

echo "VALIDACION_GERIZIM_FALLIDA: $FAILURES fallo(s)"
exit 1
