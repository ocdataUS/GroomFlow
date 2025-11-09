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

## Slice Ritual Checklist
1. Read the full stack (`AGENTS.md` → `README.md` → `PROJECT_PLAN.md` → `SPEC.md` → `TECH_READINESS.md` → `QA_TOOLBELT.md` → `docs/HISTORY.md` → `docs/NOTES.md` → latest breadcrumb).
2. `git checkout dev && git checkout -b refactor/<slice-name>` then add an “In Progress” entry to `docs/REFACTOR_LOG.md` with scope + QA plan.
3. Build assets if needed (`npm run build`), package the plugin (`bash scripts/build_plugin_zip.sh`), and copy/install the ZIP inside Docker (`docker compose cp …`, `docker compose run --rm -T wpcli wp plugin install … --force --activate`).
4. Reseed demo data (`docker compose run --rm -T wpcli wp bbgf visits seed-demo --count=8 --force`) so manual QA starts from a known baseline.
5. Run QA: `qa-phpcs plugin/bb-groomflow`, the full manual admin happy-path (Clients→Settings), plus any slice-specific tooling listed in `PROJECT_PLAN.md`.
6. Save every CLI/QA transcript under `/opt/qa/artifacts/<descriptive-name>` and record those paths in both `docs/REFACTOR_LOG.md` and `qa/QA_LOG.md`.
7. Update docs (`PROJECT_PLAN.md`, `docs/HISTORY.md`, `docs/NOTES.md`, `CHANGELOG.md` when behavior changes) and add a new breadcrumb summarizing the run before handing off.

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

### Manual Admin Happy-Path (Headless Option)
No browser? Drive persistence checks through WP-CLI/SQL and log the commands + results. Determine the live table prefix via `docker compose run --rm -T wpcli wp config get table_prefix` and substitute it for `${PREFIX}` below:

```bash
PREFIX=$(docker compose run --rm -T wpcli wp config get table_prefix | tr -d '\r')
docker compose run --rm -T wpcli wp db query "UPDATE ${PREFIX}bb_guardians SET notes = CONCAT('QA guardian edit ', NOW()) LIMIT 1"
docker compose run --rm -T wpcli wp db query "UPDATE ${PREFIX}bb_clients SET notes = CONCAT('QA client edit ', NOW()) LIMIT 1"
docker compose run --rm -T wpcli wp db query "UPDATE ${PREFIX}bb_services SET description = CONCAT('QA service edit ', NOW()) LIMIT 1"
docker compose run --rm -T wpcli wp db query "UPDATE ${PREFIX}bb_service_packages SET description = CONCAT('QA package edit ', NOW()) LIMIT 1"
docker compose run --rm -T wpcli wp db query "UPDATE ${PREFIX}bb_flags SET description = CONCAT('QA flag edit ', NOW()) LIMIT 1"
docker compose run --rm -T wpcli wp db query "UPDATE ${PREFIX}bb_views SET updated_at = UTC_TIMESTAMP() LIMIT 1"
docker compose run --rm -T wpcli wp eval 'update_option( "bbgf_settings", bbgf()->get_default_settings() );'
```

Document the checklist (e.g., “Clients ✅”, “Services ✅”) in the artifact so reviewers can confirm each admin surface was exercised even without GUI access.

## Artifacts
- Store outputs under `/opt/qa/artifacts/` with descriptive names (e.g., `phpcs-<date>.txt`, `manual-admin-<stage>.md`).
- Capture command output via `tee` or shell redirection so the artifact includes both the command and its result. Example: `qa-phpcs plugin/bb-groomflow | tee /opt/qa/artifacts/phpcs-$(date -u +%s).txt`.
- Reference the absolute paths inside:
  - `docs/REFACTOR_LOG.md` (per-slice entry)
  - `qa/QA_LOG.md` (table of runs)
  - Breadcrumb for the session

## Blockers / Escalation
- Environment failure (Docker won’t start, wp-cli install fails): document the command + output in `docs/NOTES.md`, flip your log entry to “Blocked,” and stop.
- Missing requirements/spec drift: annotate `PROJECT_PLAN.md` + `docs/HISTORY.md` with the issue, then mark the slice blocked.
- Never proceed with partial QA—stateless agents depend on these artifacts to trust the slice.
