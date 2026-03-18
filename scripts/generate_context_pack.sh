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
	raw="$(printf '%s\n' "${raw}" | sed -n '/^[[:space:]]*[\[{][[:space:]]*$/,$p')"

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

asana_inbox="$(asana_json '[]' ocasana tasks list --section 1212172738940524 --profile ocdata --json)"
asana_ready="$(asana_json '[]' ocasana tasks list --section 1212222472279794 --profile ocdata --json)"
asana_active="$(asana_json '[]' ocasana tasks list --section 1212222470604323 --profile ocdata --json)"
asana_blocked="$(asana_json '[]' ocasana tasks list --section 1213732788211928 --profile ocdata --json)"
asana_pm_review="$(asana_json '[]' ocasana tasks list --section 1213718066198088 --profile ocdata --json)"
asana_closed_recent="$(asana_json '[]' ocasana tasks list --section 1212222470855273 --profile ocdata --limit 5 --json)"
asana_project_brief="$(asana_json '{"project":"1212222472264357","brief":null}' ocasana projects briefs-get --project 1212222472264357 --profile ocdata --json)"

mkdir -p "${ROOT_DIR}/docs/context"
jq -n \
  --arg generated_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  --arg agents "$(read_file "${ROOT_DIR}/AGENTS.md")" \
  --arg handoff "$(read_file "${ROOT_DIR}/AGENT_HANDOFF.md")" \
  --arg asana_toolbox "$(read_file "${ROOT_DIR}/docs/ASANA_TOOLBOX.md")" \
  --arg operating_model "$(read_file "${ROOT_DIR}/docs/OPERATING_MODEL.md")" \
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
  --argjson asana_inbox "${asana_inbox}" \
  --argjson asana_ready "${asana_ready}" \
  --argjson asana_active "${asana_active}" \
  --argjson asana_blocked "${asana_blocked}" \
  --argjson asana_pm_review "${asana_pm_review}" \
  --argjson asana_closed_recent "${asana_closed_recent}" \
  --argjson asana_project_brief "${asana_project_brief}" \
  '{
    generated_at: $generated_at,
    docs: {
      agents: $agents,
      asana_toolbox: $asana_toolbox,
      operating_model: $operating_model,
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
      project_brief: $asana_project_brief,
      inbox: $asana_inbox,
      ready: $asana_ready,
      active: $asana_active,
      blocked: $asana_blocked,
      pm_review: $asana_pm_review,
      closed_recent: $asana_closed_recent
    }
  }' > "${ROOT_DIR}/${OUT}"
echo "Wrote ${ROOT_DIR}/${OUT}"
