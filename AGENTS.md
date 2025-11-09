# AGENTS — Runbook for GroomFlow Development (Docker)
Read EVERY doc before coding. Work in Docker WP. Do ALL QA/QC using `QA_TOOLBELT.md`. Always build a ZIP to `build/` and install into Docker WP. Leave breadcrumbs.

---

## Start Here (First Hour)
1. **Read:** Finish this file, then `README.md`, `SPEC.md`, and the doc map in `docs/README.md`. Follow links as you go.
2. **Know the plan:** Open `docs/sprints/Sprint-0.md` (or the next sprint in sequence). Every task must start with a written plan (use the planning tool).
3. **Regressions first:** We are in **break/fix mode**. Before starting new sprint work, triage open regressions in the backlog/breadcrumbs, reproduce them locally, and confirm expected behaviour from SPEC. Flag blockers immediately.
4. **Prep git:** Follow the SSH setup in `docs/workflow.md` §2 before running `git status`.
5. **Prep Docker:** If needed, copy `docker/.env.example` → `docker/.env`, then bring the stack up (`cd docker && docker compose up -d`).
6. **Bootstrap plugin:** Build a ZIP (`bash scripts/build_plugin_zip.sh`) and install it inside Docker WP using the `wpcli` service (`docs/workflow.md` §3) to confirm the environment works.
7. **Document:** Create or update a breadcrumb (`docs/breadcrumbs/`) as soon as you start real work. Add new insights under **Tips & Tricks** in `QA_TOOLBELT.md`. Before coding, read the latest handoff breadcrumb (see `docs/breadcrumbs/` — newest file) after finishing the docs listed above.

If the user prompt only says “Start with AGENTS.md” or “get ready for testing/feedback”, complete the list above, then surface a plan for the next sprint deliverable or regression fix (whichever is higher priority).

---

## Mission
Implement and maintain the plugin per `SPEC.md` using native WP patterns. Manual builds only. **All code must be 100 % WordPress-compliant**—run `qa-phpcs plugin/bb-groomflow` before every handoff, document any unavoidable sniffs, and fix regressions without hacks or “monkey code.”

## Current Focus — Regression Fix Mode
- Triage regressions from recent refactors (board wiring, bootstrap services, uninstall/CLI, docs).
- Reproduce issues inside Docker WP using the packaged ZIP (never run from the raw plugin tree).
- Fix the bug, rebuild/install the ZIP, rerun QA (PHPCS + manual admin/board walkthrough), and capture artifacts.
- Update relevant sprint doc(s) or SPEC if behaviour changed; log findings in breadcrumbs so the next agent sees what was fixed.

## Default Daily Loop
- Review the active sprint doc and set a plan (planning tool required), but address break/fix tickets first.
- Code against `plugin/bb-groomflow/`; keep changes scoped to the sprint item or regression you’re fixing.
- Build a ZIP and install it in Docker WP whenever functionality changes.
- Run QA/QC from `QA_TOOLBELT.md`; archive artifact paths in the breadcrumb.
- After each install, run the full admin form happy-path (create + edit for Clients, Guardians, Services, Packages, Flags, Views, Settings) and note results in your breadcrumb.
- Update docs (including this runbook) with anything future agents need.
- Commit with Conventional Commits and push via SSH when work is accepted.

## Quick References
- `SPEC.md` — product requirements, scope, and milestones.
- `docs/README.md` — documentation index + reading order reminders.
- `docs/ARCHITECTURE.md` — plugin structure, data flow, extensibility points.
- `docs/workflow.md` — end-to-end flow (SSH auth, Docker WP, build/install, QA, push, production sync).
- `docs/BREADCRUMBS_TEMPLATE.md` — breadcrumb format.
- `docs/sprints/` — sprint backlog. Work them in order unless the user says otherwise, but do not ignore regressions.
- `docs/CHANGE_MANAGEMENT.md` — how to handle Mike’s pivots without fragmenting the plan.
- `docker/README.md` — stack usage and WP-CLI examples.
- `scripts/build_plugin_zip.sh` — builds `build/bb-groomflow-<version>.zip`.
- `scripts/load_prod_snapshot.sh` — copies production wp-content + DB into Docker volumes.

## Working with Mike (Product Owner)
- Expect rapid iteration. If a request drops mid-sprint, pause and assess impact before coding.
- Clarify requirements until you are certain; summarize back so Mike can confirm.
- Evaluate upstream/downstream effects (schema, REST, Elementor, docs) and call them out—Mike wants honest feedback.
- Update the plan tool, SPEC, roadmap, and sprint docs to match the new direction. Log rationale in the breadcrumb.
- It’s OK to propose alternatives or push back gently; keep it collaborative and solution-oriented.

## Compliance Guardrails
- `qa-phpcs plugin/bb-groomflow` must pass before every handoff. Fix issues instead of suppressing them unless the sniff is genuinely invalid (document why).
- Never bypass WordPress APIs (no direct `$_POST` use without sanitization, no raw SQL without `$wpdb->prepare`, no script/style enqueues outside hooks).
- Manual QA is mandatory: packaged ZIP → Docker install → demo data seed → full admin happy-path + board walkthrough.
- Log every QA artifact (CLIs, screenshots, PHPCS outputs) in the breadcrumb and `qa/QA_LOG.md`.

## Rules
1. Plan-first per sprint (even during regression fixes).  
2. Use Docker WP with packaged ZIP installs.  
3. Build: `bash scripts/build_plugin_zip.sh` → `build/bb-groomflow-*.zip`; Install with WP‑CLI.  
4. QA/QC with the toolbelt and keep PHPCS clean.  
5. Keep docs current and write breadcrumbs.  
6. Append tips to `QA_TOOLBELT.md`.  
7. No auto build CI.

## DoD
AC met; regression verified; PHPCS/tests clean; manual a11y/perf done; ZIP installed; admin form create/edit + board run recorded; breadcrumb + docs + tips updated; WP marketplace compliance maintained.

---

GitHub.com access steps now live in `docs/workflow.md`. Update both documents whenever the process changes so future agents stay unblocked.
