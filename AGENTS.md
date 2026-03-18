# AGENTS — GroomFlow Entrypoint (Docker)
This is the single starting point for beta work. Follow this path exactly; everything else is reference.

---

## Fast Onboarding (linear path)
1. **Read:** This file, `docs/README.md`, `AGENT_HANDOFF.md`, and the newest file under `docs/breadcrumbs/`; open only the domain docs that match your task after that.
2. **Plan + Asana audit:** Set a brief plan, then follow `docs/ASANA_TOOLBOX.md` before writing code. Always inspect GroomFlow `Doing` + `ToDo`, read the chosen task’s details/comments/attachments, and lock/create the task before proceeding.
3. **Prep repo:** `git status`; `git branch -a`; confirm the current default branch (currently `main`) before creating a work branch; copy `docker/.env.example` → `docker/.env` if missing.
4. **Start Docker:** `cd docker && docker compose up -d`.
5. **Build ZIP:** From repo root run `bash scripts/build_plugin_zip.sh` (run `npm run build` first if bundles are stale).
6. **Install in Docker:**
   ```bash
   cd docker
   ZIP=../build/bb-groomflow-0.1.0-dev.zip   # adjust to latest build
   docker compose cp "$ZIP" wordpress:/var/www/html/bb-groomflow.zip
   docker compose run --rm -T wpcli wp plugin install /var/www/html/bb-groomflow.zip --force --activate
   docker compose run --rm -T wpcli wp bbgf visits seed-demo --count=8 --force   # demo data
   ```
7. **QA:** Run `bash scripts/qa_fast.sh`, then `scripts/qa-phpcs plugin/bb-groomflow`, then the full admin happy-path (create + edit + confirm persistence for Clients, Guardians, Services, Packages, Flags, Views, Settings). Use `QA_TOOLBELT.md` for commands and artifacts.
8. **Breadcrumb + handoff:** Create/update `docs/breadcrumbs/<date>-<topic>.md`, keep `qa/QA_LOG.md` current, update `AGENT_HANDOFF.md`, and refresh `bash scripts/generate_context_pack.sh` before you stop.

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
- Treat Asana + `AGENT_HANDOFF.md` as mandatory state, not optional reference. Do the task audit before code, QA, or docs.
- Use packaged ZIPs in Docker only—never bind the plugin directory into containers.
- Keep WordPress standards intact: sanitize/escape, use `$wpdb->prepare`, enqueue assets via hooks, and ensure `scripts/qa-phpcs plugin/bb-groomflow` passes before handoff.
- After any install, reseed demo data and rerun the admin happy-path.
- Update SPEC/docs only when behaviour changes; avoid future-roadmap drafts.

## QA & Handoff
- Required before handoff: packaged ZIP installed in Docker, demo data seeded, `scripts/qa-phpcs plugin/bb-groomflow` clean (document any intentional ignores), admin happy-path confirmed, artifacts stored under `/opt/qa/artifacts` and referenced in the breadcrumb.
- Required before handoff: Asana task comment updated with status + artifact paths; `AGENT_HANDOFF.md` updated with task/branch/blockers/next action; `docs/context/context-pack.json` refreshed via `bash scripts/generate_context_pack.sh`.
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
- Create/update it before stopping work; include Asana task/link/status, branch, `git status`, latest breadcrumb, artifact paths, blockers, exact next step, and any runtime mismatch the next agent must know.

## Context Compaction Ritual
- Before ending a slice or when context gets tight, update `AGENT_HANDOFF.md` first, then run `bash scripts/generate_context_pack.sh`.
- The handoff must be specific enough that a fresh agent can resume from one next action, one branch, and one Asana task without replaying the whole project history.
- If work is interrupted mid-slice, leave the exact next command, screen, or file to open in both Asana and `AGENT_HANDOFF.md`.

---

GitHub.com access steps live in `docs/workflow.md`. Keep this entrypoint current so a stateless agent can start in minutes.
