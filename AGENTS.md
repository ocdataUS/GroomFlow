# AGENTS — GroomFlow Entrypoint (Docker)
This is the repo entrypoint for real work. GroomFlow now uses an Asana-first task model: Asana is the live PM system, repo docs hold the durable method, and `AGENT_HANDOFF.md` holds the current continuation snapshot.

---

## Fast Onboarding (linear path)
1. **Read:** this file, `docs/OPERATING_MODEL.md`, `docs/ASANA_TOOLBOX.md`, `AGENT_HANDOFF.md`, and the most relevant recent breadcrumb if one exists.
2. **Asana intake:** inspect GroomFlow `Active`, `Blocked`, `PM Review`, then `Ready`; continue or create exactly one task for the work. Read the task notes, comments, and attachments before touching code.
3. **Task brief:** make sure the Asana task notes cover `Goal`, `Context`, `Constraints`, and `Done When`. Lock the task and move it to `Active`.
4. **Prep repo:** `git status`; `git branch -a`; stay on `main` unless you are explicitly told to use another branch; copy `docker/.env.example` → `docker/.env` if missing.
5. **Start Docker:** `cd docker && docker compose up -d`.
6. **Build ZIP:** from repo root run `bash scripts/build_plugin_zip.sh` (run `npm run build` first if bundles are stale).
7. **Install in Docker:**
   ```bash
   cd docker
   ZIP=../build/bb-groomflow-0.1.0-dev.zip   # adjust to latest build
   docker compose cp "$ZIP" wordpress:/var/www/html/bb-groomflow.zip
   docker compose run --rm -T wpcli wp plugin install /var/www/html/bb-groomflow.zip --force --activate
   docker compose run --rm -T wpcli wp bbgf visits seed-demo --count=8 --force   # demo data
   ```
8. **QA:** run `bash scripts/qa_fast.sh`, then `scripts/qa-phpcs plugin/bb-groomflow`, then the full admin happy-path (create + edit + confirm persistence for Clients, Guardians, Services, Packages, Flags, Views, Settings). Use `QA_TOOLBELT.md` for commands and artifacts.
9. **Stop cleanly:** update Asana with status/artifacts/next step, update `AGENT_HANDOFF.md`, run `bash scripts/generate_context_pack.sh`, and add a breadcrumb only when the task changed behaviour, captured meaningful QA, or needs a durable decision trail.

## Documentation Map (How to Find What You Need)
- PM model & continuity → `docs/OPERATING_MODEL.md`, `docs/ASANA_TOOLBOX.md`, `AGENT_HANDOFF.md`
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
- Continuity & history → `AGENT_HANDOFF.md`, `docs/breadcrumbs/` — current handoff plus durable task journals.

## Working Mode — Beta Stabilization
- v0 is feature-complete; prioritize regressions and hotfixes over net-new work.
- Treat Asana as the source of active task state. Treat `AGENT_HANDOFF.md` as the current resume snapshot.
- Use packaged ZIPs in Docker only—never bind the plugin directory into containers.
- Keep WordPress standards intact: sanitize/escape, use `$wpdb->prepare`, enqueue assets via hooks, and ensure `scripts/qa-phpcs plugin/bb-groomflow` passes before handoff.
- After any install, reseed demo data and rerun the admin happy-path.
- Update SPEC/docs only when behaviour changes; avoid future-roadmap drafts.

## QA & Handoff
- Required before handoff: packaged ZIP installed in Docker, demo data seeded, `scripts/qa-phpcs plugin/bb-groomflow` clean (document any intentional ignores), admin happy-path confirmed, artifacts stored under `/opt/qa/artifacts`, and results logged in Asana + `qa/QA_LOG.md` with a breadcrumb only when durable narrative is needed.
- Required before handoff: Asana task updated with status + artifact paths + exact next step, `AGENT_HANDOFF.md` updated, and `docs/context/context-pack.json` refreshed via `bash scripts/generate_context_pack.sh`.
- Keep commits conventional; never commit build ZIPs. Push via SSH as needed (setup in `docs/workflow.md`).

## Documentation Governance — Document as You Discover
- Update the doc that owns a domain whenever you uncover non-obvious logic, behavioural quirks, or resolved contradictions.
- Documentation updates are part of task completion; do not defer them.
- Route details into existing docs rather than creating new ones when coverage exists.
- Keep updates concise and scoped to the domain doc; avoid duplicating content across files.
- Use breadcrumbs for significant task history when they add durable value; do not treat them as the live PM system.

## Long-Running Task Continuity
- Keep one main Codex thread per Asana task. Fork only when the work truly branches.
- Use `AGENT_HANDOFF.md` for one current continuation snapshot: task, blockers, artifacts, and exact next step. The git branch is normally `main`.
- Before compaction or interruption, update Asana first, then `AGENT_HANDOFF.md`, then regenerate the context pack.

---

GitHub.com access steps live in `docs/workflow.md`. Keep this entrypoint current so a stateless agent can start in minutes.
