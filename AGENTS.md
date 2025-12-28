# AGENTS — GroomFlow Entrypoint (Docker)
This is the single starting point for beta work. Follow this path exactly; everything else is reference.

---

## Fast Onboarding (linear path)
1. **Read:** This file, then `SPEC.md` for product intent. Use the doc map below for anything else you need.
2. **Prep repo:** `git status`; copy `docker/.env.example` → `docker/.env` if missing.
3. **Start Docker:** `cd docker && docker compose up -d`.
4. **Build ZIP:** From repo root run `bash scripts/build_plugin_zip.sh` (run `npm run build` first if bundles are stale).
5. **Install in Docker:**  
   ```bash
   cd docker
   ZIP=../build/bb-groomflow-0.1.0-dev.zip   # adjust to latest build
   docker compose cp "$ZIP" wordpress:/var/www/html/bb-groomflow.zip
   docker compose run --rm -T wpcli wp plugin install /var/www/html/bb-groomflow.zip --force --activate
   docker compose run --rm -T wpcli wp bbgf visits seed-demo --count=8 --force   # demo data
   ```
6. **QA:** `qa-phpcs plugin/bb-groomflow`, then run the full admin happy-path (create + edit + confirm persistence for Clients, Guardians, Services, Packages, Flags, Views, Settings). Use `QA_TOOLBELT.md` for commands and artifacts.
7. **Breadcrumb:** Create/update `docs/breadcrumbs/<date>-<topic>.md` with actions, QA results, and artifact paths. Keep a running log in `qa/QA_LOG.md` when applicable.
8. **Plan + Asana:** Set a brief plan using the planning tool; if working an Asana task, lock it per `docs/ASANA_TOOLBOX.md` (comment `LOCKED…`, set GF Status to In progress, move to Doing).

## Doc Map (read order + reference)
- **Must read now:** `README.md` (system overview + code map), `SPEC.md` (scope/requirements), `QA_TOOLBELT.md` (QA commands), `docs/workflow.md` (Docker + install loop), `docs/HOTFIX_PROTOCOL.md` (post-beta fixes).
- **Reference when needed:** `docs/ARCHITECTURE.md`, `docs/API.md`, `docs/DB_SCHEMA.md`, `docs/SECURITY.md`, `docs/UX_GUIDE.md`, `docker/README.md`, `scripts/qa_smoke.sh`.
- **Breadcrumbs:** `docs/breadcrumbs/` (read newest before coding; use `docs/BREADCRUMBS_TEMPLATE.md` when adding one).
- **Out of scope for onboarding:** Historical sprints/roadmaps and old refactor plans have been removed.

## Working Mode — Beta Stabilization
- v0 is feature-complete; prioritize regressions and hotfixes over net-new work.
- Use packaged ZIPs in Docker only—never bind the plugin directory into containers.
- Keep WordPress standards intact: sanitize/escape, use `$wpdb->prepare`, enqueue assets via hooks, and ensure `qa-phpcs plugin/bb-groomflow` passes before handoff.
- After any install, reseed demo data and rerun the admin happy-path.
- Update SPEC/docs only when behaviour changes; avoid future-roadmap drafts.

## QA & Handoff
- Required before handoff: packaged ZIP installed in Docker, demo data seeded, `qa-phpcs plugin/bb-groomflow` clean (document any intentional ignores), admin happy-path confirmed, artifacts stored under `/opt/qa/artifacts` and referenced in the breadcrumb.
- Keep commits conventional; never commit build ZIPs. Push via SSH as needed (setup in `docs/workflow.md`).

## Long-Running Task Continuity
- Use `AGENT_HANDOFF.md` to record decisions, current state, blockers, and next actions for any task likely to exceed one session.
- Treat `AGENT_HANDOFF.md` as authoritative for continuation; closed decisions may not be reopened without Product Owner direction.
- Create/update it before stopping work; include artifact paths and branch status.

---

GitHub.com access steps live in `docs/workflow.md`. Keep this entrypoint current so a stateless agent can start in minutes.
