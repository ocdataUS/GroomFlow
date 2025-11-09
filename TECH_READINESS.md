# TECH_READINESS — GroomFlow

Use this guide to keep every slice reproducible and marketplace compliant.

## Environments
- **Local OS:** WSL Ubuntu (pre-provisioned)
- **PHP:** 8.2 inside Docker WP image
- **WordPress:** 6.8.3 (per `docker/docker-compose.yml`)
- **Browser/QA tooling:** Shared CLI wrappers in `/opt/qa/bin` (see `QA_TOOLBELT.md`)

## Required Commands
| Purpose | Command |
| --- | --- |
| Install deps | `npm install` (already run, only when package.json changes) |
| Build assets + ZIP | `bash scripts/build_plugin_zip.sh` |
| Start Docker | `cd docker && docker compose up -d` |
| Install ZIP in Docker | `docker compose cp ../build/bb-groomflow-0.1.0-dev.zip wordpress:/var/www/html/bb-groomflow.zip`<br>`docker compose run --rm -T wpcli wp plugin install /var/www/html/bb-groomflow.zip --force --activate` |
| Seed demo data | `docker compose run --rm -T wpcli wp bbgf visits seed-demo --count=8 --force` |
| PHPCS | `qa-phpcs plugin/bb-groomflow` |
| Optional PHPCBF (never commit blindly) | `qa-phpcbf plugin/bb-groomflow` |

## QA Expectations per Slice
1. **Automation:** `qa-phpcs plugin/bb-groomflow` (WordPress coding standards). Additional tools (pa11y, html-validate, etc.) if the slice touches those surfaces—note them in `PROJECT_PLAN.md`.
2. **Manual Admin Happy-Path:** Inside Docker WP run-through (create + edit + confirm persistence) for:
   - Clients
   - Guardians
   - Services
   - Packages
   - Flags
   - Views
   - Settings
3. **Board Verification:** When a slice impacts the board/REST/Elementor, load `http://localhost:${WP_PORT}` for both internal and lobby views; capture screenshots if UI shifts.

## Artifacts
- Store outputs under `/opt/qa/artifacts/` with descriptive names (e.g., `phpcs-<date>.txt`, `manual-admin-<stage>.md`).
- Reference the absolute paths inside:
  - `docs/REFACTOR_LOG.md` (per-slice entry)
  - `qa/QA_LOG.md` (table of runs)
  - Breadcrumb for the session

## Blockers / Escalation
- Environment failure (Docker won’t start, wp-cli install fails): document the command + output in `docs/NOTES.md`, flip your log entry to “Blocked,” and stop.
- Missing requirements/spec drift: annotate `PROJECT_PLAN.md` + `docs/HISTORY.md` with the issue, then mark the slice blocked.
- Never proceed with partial QA—stateless agents depend on these artifacts to trust the slice.
