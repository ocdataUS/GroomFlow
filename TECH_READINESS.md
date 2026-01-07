# TECH_READINESS — GroomFlow Beta

Quick reference to keep installs reproducible and marketplace compliant.

## Environment
- **OS:** WSL Ubuntu
- **PHP:** 8.2 inside Docker WP
- **WordPress:** 6.9.0 (`docker/docker-compose.yml`)
- **Tooling:** QA wrappers in `/opt/qa/bin` (see `QA_TOOLBELT.md`)

## Essential Commands
| Purpose | Command |
| --- | --- |
| Build assets + ZIP | `bash scripts/build_plugin_zip.sh` (run `npm run build` first if bundles changed) |
| Start Docker | `cd docker && docker compose up -d` |
| Install ZIP in Docker | `docker compose cp ../build/bb-groomflow-0.1.0-dev.zip wordpress:/var/www/html/bb-groomflow.zip`<br>`docker compose run --rm -T wpcli wp plugin install /var/www/html/bb-groomflow.zip --force --activate` |
| Seed demo data | `docker compose run --rm -T wpcli wp bbgf visits seed-demo --count=8 --force` |
| QA smoke (build/install + board/notifications/stats) | `bash scripts/qa_smoke.sh` (use `SKIP_ASSET_BUILD=1` to reuse bundles) |
| PHPCS | `qa-phpcs plugin/bb-groomflow` |
| Optional PHPCBF | `qa-phpcbf plugin/bb-groomflow` |

## QA Expectations
1. **Automation:** `qa-phpcs plugin/bb-groomflow` must pass (WordPress standards). Document any intentional ignores.
2. **Manual Admin Happy-Path:** Inside Docker, create + edit + confirm persistence for Clients, Guardians, Services, Packages, Flags, Views, Settings. Pay extra attention to Package service selections/order.
3. **Board Verification (when touched):** Load internal + lobby views; confirm timers, stage moves, and refresh; capture artifacts if UI changes.
4. **Artifacts:** Save outputs under `/opt/qa/artifacts/<name>` and reference them in breadcrumbs and `qa/QA_LOG.md`.

## Release/Handoff Checklist
- Packaged ZIP installed in Docker (no bind mounts), demo data reseeded.
- `qa-phpcs plugin/bb-groomflow` clean; run `bash scripts/qa_smoke.sh` when functionality shifts.
- Admin happy-path complete; any CLI/QA transcripts saved to `/opt/qa/artifacts`.
- Breadcrumb updated with actions and artifact paths; no roadmap/sprint docs required.

For SSH setup, Docker tips, and troubleshooting, use `docs/workflow.md`.
