#!/usr/bin/env bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="docs/context/context-pack.json"

read_file() {
	local path="$1"
	if [[ -f "${path}" ]]; then
		sed 's/\r$//' "${path}"
	fi
}

asana_json() {
	local fallback="$1"
	shift

	if ! command -v ocasana >/dev/null 2>&1; then
		printf '%s' "${fallback}"
		return
	fi

	local raw
	raw="$("$@" 2>/dev/null || true)"
	raw="$(printf '%s\n' "${raw}" | sed -n '/^[[:space:]]*[\[{]/{:a;p;n;ba}')"

	if [[ -z "${raw}" ]] || ! jq -e . >/dev/null 2>&1 <<<"${raw}"; then
		printf '%s' "${fallback}"
		return
	fi

	printf '%s' "${raw}"
}

latest_breadcrumb=""
if ls -1t "${ROOT_DIR}"/docs/breadcrumbs/*.md >/dev/null 2>&1; then
	latest_breadcrumb="$(ls -1t "${ROOT_DIR}"/docs/breadcrumbs/*.md | head -n 1)"
fi

git_branch="$(git -C "${ROOT_DIR}" branch --show-current 2>/dev/null || true)"
git_status="$(git -C "${ROOT_DIR}" status --short --branch 2>/dev/null || true)"

asana_todo="$(asana_json '[]' ocasana tasks list --section 1212222472279794 --profile ocdata --json)"
asana_doing="$(asana_json '[]' ocasana tasks list --section 1212222470604323 --profile ocdata --json)"
asana_done_recent="$(asana_json '[]' ocasana tasks list --section 1212222470855273 --profile ocdata --limit 5 --json)"

mkdir -p "${ROOT_DIR}/docs/context"
jq -n \
  --arg generated_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  --arg agents "$(read_file "${ROOT_DIR}/AGENTS.md")" \
  --arg handoff "$(read_file "${ROOT_DIR}/AGENT_HANDOFF.md")" \
  --arg asana_toolbox "$(read_file "${ROOT_DIR}/docs/ASANA_TOOLBOX.md")" \
  --arg workflow "$(read_file "${ROOT_DIR}/docs/workflow.md")" \
  --arg qa_toolbelt "$(read_file "${ROOT_DIR}/QA_TOOLBELT.md")" \
  --arg spec "$(read_file "${ROOT_DIR}/SPEC.md")" \
  --arg api "$(read_file "${ROOT_DIR}/docs/API.md")" \
  --arg db "$(read_file "${ROOT_DIR}/docs/DB_SCHEMA.md")" \
  --arg sec "$(read_file "${ROOT_DIR}/docs/SECURITY.md")" \
  --arg bind "$(read_file "${ROOT_DIR}/docs/FRONTEND_BINDINGS.md")" \
  --arg latest_breadcrumb_path "${latest_breadcrumb#${ROOT_DIR}/}" \
  --arg latest_breadcrumb "$(read_file "${latest_breadcrumb}")" \
  --arg git_branch "${git_branch}" \
  --arg git_status "${git_status}" \
  --argjson asana_todo "${asana_todo}" \
  --argjson asana_doing "${asana_doing}" \
  --argjson asana_done_recent "${asana_done_recent}" \
  '{
    generated_at: $generated_at,
    docs: {
      agents: $agents,
      asana_toolbox: $asana_toolbox,
      workflow: $workflow,
      qa_toolbelt: $qa_toolbelt,
      spec: $spec,
      api: $api,
      db: $db,
      security: $sec,
      bindings: $bind
    },
    continuity: {
      handoff: $handoff,
      latest_breadcrumb_path: $latest_breadcrumb_path,
      latest_breadcrumb: $latest_breadcrumb
    },
    repo: {
      git_branch: $git_branch,
      git_status: $git_status
    },
    asana: {
      todo: $asana_todo,
      doing: $asana_doing,
      done_recent: $asana_done_recent
    }
  }' > "${ROOT_DIR}/${OUT}"
echo "Wrote ${ROOT_DIR}/${OUT}"
