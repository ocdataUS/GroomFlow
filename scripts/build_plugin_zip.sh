#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="bb-groomflow"
PLUGIN_SRC="${ROOT_DIR}/plugin/${PLUGIN_SLUG}"
BUILD_DIR="${ROOT_DIR}/build"

if [[ ! -d "${PLUGIN_SRC}" ]]; then
	echo "Plugin source directory not found at ${PLUGIN_SRC}" >&2
	exit 1
fi

VERSION="$(
	awk -F':' '
		/^[[:space:]\*#]*Version[[:space:]]*/ {
			gsub(/\r/, "", $2);
			sub(/^[[:space:]]*/, "", $2);
			sub(/[[:space:]]*$/, "", $2);
			print $2;
			exit;
		}
	' "${PLUGIN_SRC}/${PLUGIN_SLUG}.php"
)"

if [[ -z "${VERSION}" ]]; then
	echo "Unable to determine plugin version from ${PLUGIN_SRC}/${PLUGIN_SLUG}.php" >&2
	exit 1
fi

mkdir -p "${BUILD_DIR}"
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "${TMP_DIR}"' EXIT

rsync -a --delete \
	--exclude='.git' \
	--exclude='node_modules' \
	--exclude='vendor' \
	"${PLUGIN_SRC}/" "${TMP_DIR}/${PLUGIN_SLUG}/"

(
	cd "${TMP_DIR}" >/dev/null 2>&1
	zip -r "${BUILD_DIR}/${ZIP_NAME}" "${PLUGIN_SLUG}" >/dev/null
)

echo "✅ Created ${BUILD_DIR}/${ZIP_NAME}"
