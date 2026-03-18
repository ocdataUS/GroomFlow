# Documentation Map — GroomFlow

Follow this reading order when coming in cold. Everything else is reference.

## Start Here
- `AGENTS.md` — entrypoint and fast onboarding path.
- `docs/ASANA_TOOLBOX.md` — mandatory task audit/lock/update workflow before coding.
- `AGENT_HANDOFF.md` — current continuation state, blockers, and next action.
- Newest file under `docs/breadcrumbs/` — latest slice history and artifacts.
- `README.md` — system overview + code map.
- `SPEC.md` — product scope and behaviour (current state only).
- `QA_TOOLBELT.md` — required QA commands and artifact locations.
- `docs/workflow.md` — Docker + ZIP install loop, SSH setup.

## Mandatory Before Coding
- Check GroomFlow Asana `Doing` and `ToDo`; read the chosen task’s details, comments, and attachments before changing code.
- Read `AGENT_HANDOFF.md` and the newest breadcrumb so you know the last committed direction and the last interrupted direction.
- Run `git status` and confirm the actual default branch/base before creating a new branch. The repo currently uses `main`.
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
- Add new entries using `docs/BREADCRUMBS_TEMPLATE.md` and include QA artifact paths.

Planning backlogs/roadmaps were removed for the beta; operate from assigned tasks and regressions. The code map in `README.md` is the single source for where features live.
