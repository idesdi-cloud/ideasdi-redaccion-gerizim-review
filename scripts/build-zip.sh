#!/usr/bin/env bash

set -u

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUTPUT_REQUESTED="${1:-/tmp/gerizim-builds}"
PLUGIN_SLUG="ideasdi-redaccion-gerizim"

cd "$ROOT"

for COMMAND in git php python3 tar sha256sum; do
  if ! command -v "$COMMAND" >/dev/null 2>&1; then
    echo "ERROR: falta la herramienta $COMMAND."
    exit 1
  fi
done

if [ -n "$(git status --porcelain)" ]; then
  echo "ERROR: el árbol Git contiene cambios."
  echo "El ZIP solo puede construirse desde un commit limpio."
  git status --short
  exit 1
fi

if ! ./scripts/test.sh; then
  echo "ERROR: la validación de Gerizim falló."
  exit 1
fi

VERSION="$(
  awk -F': ' \
    '/^[[:space:]]*\*[[:space:]]*Version:/ {
      print $2
      exit
    }' \
    ideasdi-redaccion-gerizim.php |
  tr -d '\r'
)"

if [ -z "$VERSION" ]; then
  echo "ERROR: no se pudo determinar la versión del plugin."
  exit 1
fi

COMMIT="$(git rev-parse HEAD)"
COMMIT_TIME="$(git show -s --format='%ct' HEAD)"

mkdir -p "$OUTPUT_REQUESTED"
OUTPUT_DIR="$(cd "$OUTPUT_REQUESTED" && pwd)"

ZIP_PATH="$OUTPUT_DIR/${PLUGIN_SLUG}-v${VERSION}.zip"
SHA_PATH="${ZIP_PATH}.sha256"
TEMP_DIR="$(mktemp -d /tmp/gerizim-build.XXXXXX)"

cleanup() {
  rm -rf "$TEMP_DIR"
}
trap cleanup EXIT

mkdir -p "$TEMP_DIR/$PLUGIN_SLUG"

if ! git archive --format=tar HEAD |
  tar -xf - -C "$TEMP_DIR/$PLUGIN_SLUG"; then
  echo "ERROR: no se pudo exportar el commit."
  exit 1
fi

rm -f "$ZIP_PATH" "$SHA_PATH"

python3 - \
  "$TEMP_DIR" \
  "$PLUGIN_SLUG" \
  "$ZIP_PATH" \
  "$COMMIT_TIME" <<'PY'
from pathlib import Path
import stat
import sys
import time
import zipfile

temp_dir = Path(sys.argv[1])
plugin_slug = sys.argv[2]
zip_path = Path(sys.argv[3])
commit_time = max(int(sys.argv[4]), 315532800)

root = temp_dir / plugin_slug
date_time = time.gmtime(commit_time)[:6]

files = sorted(
    path for path in root.rglob("*")
    if path.is_file()
)

with zipfile.ZipFile(
    zip_path,
    mode="w",
    compression=zipfile.ZIP_DEFLATED,
    compresslevel=9,
) as archive:
    for path in files:
        relative = Path(plugin_slug) / path.relative_to(root)
        info = zipfile.ZipInfo(str(relative), date_time=date_time)
        info.create_system = 3
        mode = stat.S_IMODE(path.stat().st_mode)
        info.external_attr = (mode & 0xFFFF) << 16
        info.compress_type = zipfile.ZIP_DEFLATED
        archive.writestr(info, path.read_bytes())

print(f"Archivos incorporados al ZIP: {len(files)}")
PY

if ! unzip -t "$ZIP_PATH" >/dev/null; then
  echo "ERROR: el ZIP generado no superó la prueba de integridad."
  exit 1
fi

BUILT_VERSION="$(
  unzip -p \
    "$ZIP_PATH" \
    "$PLUGIN_SLUG/ideasdi-redaccion-gerizim.php" |
  awk -F': ' \
    '/^[[:space:]]*\*[[:space:]]*Version:/ {
      print $2
      exit
    }' |
  tr -d '\r'
)"

if [ "$BUILT_VERSION" != "$VERSION" ]; then
  echo "ERROR: la versión interna del ZIP no coincide."
  exit 1
fi

sha256sum "$ZIP_PATH" > "$SHA_PATH"

echo
echo "BUILD_GERIZIM_OK"
echo "Commit: $COMMIT"
echo "Versión: $VERSION"
echo "ZIP: $ZIP_PATH"
echo "SHA-256: $SHA_PATH"
ls -lh "$ZIP_PATH" "$SHA_PATH"
