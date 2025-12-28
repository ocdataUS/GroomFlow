# Documentation Map — GroomFlow

Follow this reading order when coming in cold. Everything else is reference.

## Start Here
- `AGENTS.md` — entrypoint and fast onboarding path.
- `README.md` — system overview + code map.
- `SPEC.md` — product scope and behaviour (current state only).
- `QA_TOOLBELT.md` — required QA commands and artifact locations.
- `docs/workflow.md` — Docker + ZIP install loop, SSH setup.

## Reference (pull as needed)
- `docs/ARCHITECTURE.md` — plugin structure and service map.
- `docs/API.md` — REST endpoints.
- `docs/DB_SCHEMA.md` — custom tables and versioning.
- `docs/SECURITY.md` — capability, nonce, and sanitization rules.
- `docs/UX_GUIDE.md` — UI guidelines and accessibility notes.
- `docker/README.md` — container usage and WP-CLI examples.
- `scripts/qa_smoke.sh` — packaged QA script.

## Breadcrumbs
- Read the newest file under `docs/breadcrumbs/` before coding.
- Add new entries using `docs/BREADCRUMBS_TEMPLATE.md` and include QA artifact paths.

Planning backlogs/roadmaps were removed for the beta; operate from assigned tasks and regressions. The code map in `README.md` is the single source for where features live.
