#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET_DIR="${ROOT_DIR}/plugin/bb-groomflow"
PATH="${ROOT_DIR}/scripts:${PATH}"

log() {
	echo "$@"
}

log "== GroomFlow QA fast =="
log "Running PHPCS..."
qa-phpcs "${TARGET_DIR}"

if [[ "${SKIP_PHP_LINT:-0}" == "1" ]]; then
	log "Skipping PHP lint (SKIP_PHP_LINT=1)."
	exit 0
fi

if [[ "${LINT_CHANGED:-0}" == "1" ]]; then
	log "Linting changed PHP files (LINT_CHANGED=1)."
	mapfile -t php_files < <(git -C "${ROOT_DIR}" diff --name-only --diff-filter=ACMRTUXB | rg '\.php$' || true)
else
	log "Linting plugin PHP files."
	mapfile -t php_files < <(rg --files --absolute-path -g '*.php' "${TARGET_DIR}")
fi

if [[ ${#php_files[@]} -eq 0 ]]; then
	log "No PHP files found to lint."
	exit 0
fi

for php_file in "${php_files[@]}"; do
	if [[ "${LINT_CHANGED:-0}" == "1" ]]; then
		php -l "${ROOT_DIR}/${php_file}"
	else
		php -l "${php_file}"
	fi
done
