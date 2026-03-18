# Documentation Map — GroomFlow

Follow this reading order when coming in cold. Everything else is reference.

## Start Here
- `AGENTS.md` — entrypoint and fast onboarding path.
- `docs/OPERATING_MODEL.md` — durable project-management model for tasks, handoffs, and continuity.
- `docs/ASANA_TOOLBOX.md` — exact Asana workflow and CLI commands.
- `AGENT_HANDOFF.md` — current continuation state, blockers, and next action.
- Most relevant recent file under `docs/breadcrumbs/` — durable task history when the current work depends on it.
- `README.md` — system overview + code map.
- `SPEC.md` — product scope and behaviour (current state only).
- `QA_TOOLBELT.md` — required QA commands and artifact locations.
- `docs/workflow.md` — Docker + ZIP install loop, SSH setup.

## Mandatory Before Coding
- Check GroomFlow Asana `Active`, `Blocked`, `PM Review`, then `Ready`; read the chosen task’s notes, comments, and attachments before changing code.
- Make sure the task itself states `Goal`, `Context`, `Constraints`, and `Done When`.
- Read `AGENT_HANDOFF.md` and any relevant breadcrumb so you know the last committed direction and the last interrupted direction.
- Run `git status` and confirm you are on `main` unless the user explicitly asked for another branch.
- Refresh `docs/context/context-pack.json` at handoff or before context compaction with `bash scripts/generate_context_pack.sh`.

## Reference (pull as needed)
- `docs/ARCHITECTURE.md` — plugin structure and service map.
- `docs/API.md` — REST endpoints.
- `docs/DB_SCHEMA.md` — custom tables and versioning.
- `docs/SECURITY.md` — capability, nonce, and sanitization rules.
- `docs/UX_GUIDE.md` — UI guidelines and accessibility notes.
- `docker/README.md` — container usage and WP-CLI examples.
- `scripts/qa_smoke.sh` — packaged QA script.

## Breadcrumbs
- Add entries using `docs/BREADCRUMBS_TEMPLATE.md` only when the task needs a durable decision/QA journal.

Planning backlogs/roadmaps were removed for the beta. Operate from Asana tasks, regressions, and the current handoff state. The code map in `README.md` is the single source for where features live.
