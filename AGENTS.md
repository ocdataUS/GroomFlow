# AGENTS — GroomFlow Entrypoint (Docker)
This is the single starting point for beta work. Follow this path exactly; everything else is reference.

---

## Fast Onboarding (linear path)
1. **Read:** This file and the Documentation Map below; open only the docs that match your task.
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

## Documentation Map (How to Find What You Need)
- Product intent & visit flows → `SPEC.md` — requirements, terminology, guardrails.
- Architecture & directories → `docs/ARCHITECTURE.md` — component boundaries and where code lives.
- REST endpoints & controllers → `docs/API.md` — routes, payloads, capabilities, public vs internal.
- Data schema & invariants → `docs/DB_SCHEMA.md` — tables, keys, timestamps, constraints.
- Frontend assets & build → `README.md` — system snapshot, code map, asset build locations.
- Docker install/build loop → `docs/workflow.md`, `docker/README.md` — ZIP build, compose usage, install steps.
- QA & release → `QA_TOOLBELT.md`, `scripts/qa_smoke.sh` — tooling, commands, artifact expectations.
- Hotfix policy → `docs/HOTFIX_PROTOCOL.md` — allowed/disallowed hotfix scope and steps.
- UX patterns → `docs/UX_GUIDE.md` — interaction/visual rules for board, modals, controls.
- Security & access → `docs/SECURITY.md` — capability/nonce rules, public token handling.
- Continuity & history → `AGENT_HANDOFF.md`, `docs/breadcrumbs/` — handoffs and historical notes (reference-only).

## Working Mode — Beta Stabilization
- v0 is feature-complete; prioritize regressions and hotfixes over net-new work.
- Use packaged ZIPs in Docker only—never bind the plugin directory into containers.
- Keep WordPress standards intact: sanitize/escape, use `$wpdb->prepare`, enqueue assets via hooks, and ensure `qa-phpcs plugin/bb-groomflow` passes before handoff.
- After any install, reseed demo data and rerun the admin happy-path.
- Update SPEC/docs only when behaviour changes; avoid future-roadmap drafts.

## QA & Handoff
- Required before handoff: packaged ZIP installed in Docker, demo data seeded, `qa-phpcs plugin/bb-groomflow` clean (document any intentional ignores), admin happy-path confirmed, artifacts stored under `/opt/qa/artifacts` and referenced in the breadcrumb.
- Keep commits conventional; never commit build ZIPs. Push via SSH as needed (setup in `docs/workflow.md`).

## Documentation Governance — Document as You Discover
- Update the doc that owns a domain whenever you uncover non-obvious logic, behavioural quirks, or resolved contradictions.
- Documentation updates are part of task completion; do not defer them.
- Route details into existing docs rather than creating new ones when coverage exists.
- Keep updates concise and scoped to the domain doc; avoid duplicating content across files.
- Use breadcrumbs for slice history; do not treat them as canonical instructions.

## Long-Running Task Continuity
- Use `AGENT_HANDOFF.md` to record decisions, current state, blockers, and next actions for any task likely to exceed one session.
- Treat `AGENT_HANDOFF.md` as authoritative for continuation; closed decisions may not be reopened without Product Owner direction.
- Create/update it before stopping work; include artifact paths and branch status.

---

GitHub.com access steps live in `docs/workflow.md`. Keep this entrypoint current so a stateless agent can start in minutes.
