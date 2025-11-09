# AGENTS — GroomFlow Refactor Playbook

All work on this plugin follows the stateless slice ritual below. If you just landed, slow down and complete every checklist item before writing code.

---

## Required Reading (in order)
1. `AGENTS.md` (this file)
2. `README.md`
3. `PROJECT_PLAN.md`
4. `SPEC.md`
5. `TECH_READINESS.md`
6. `QA_TOOLBELT.md`
7. `docs/HISTORY.md`
8. `docs/NOTES.md`
9. Latest breadcrumb in `docs/breadcrumbs/`

Do not branch or edit files until the reading stack is finished.

---

## Environment Warm-up
1. **Git/SSH:** Follow `docs/workflow.md` §2 to load the SSH key and confirm `git remote -v` points to the SSH URL.
2. **Docker:** Ensure `docker/.env` exists, then `cd docker && docker compose up -d`. Containers must be healthy before moving on.
3. **Baseline ZIP:** From repo root, run `bash scripts/build_plugin_zip.sh`.
4. **Install ZIP:**  
   ```
   cd docker
   docker compose cp ../build/bb-groomflow-0.1.0-dev.zip wordpress:/var/www/html/bb-groomflow.zip
   docker compose run --rm -T wpcli wp plugin install /var/www/html/bb-groomflow.zip --force --activate
   ```
5. **Seed data (optional but recommended):** `docker compose run --rm -T wpcli wp bbgf visits seed-demo --count=8 --force`.

Record any issues in `docs/NOTES.md`.

---

## Slice Workflow
1. **Pick or resume a slice** from `PROJECT_PLAN.md`. If the previous agent marked a slice “Blocked” in `docs/REFACTOR_LOG.md`, address that first.
2. **Branch:** `git checkout -b refactor/<slice-name>` from `dev`.
3. **Log “In Progress”:** Add a new entry to `docs/REFACTOR_LOG.md` with the slice name, scope, target files, planned QA, and today’s date.
4. **Study:** Read every file you will touch. Map helpers/consumers and note expectations inside your log entry.
5. **Implement:** Keep public APIs stable unless every call site is updated. Document non-obvious decisions with concise inline comments.
6. **Rebuild + Install:** After code changes, run `bash scripts/build_plugin_zip.sh` and reinstall the ZIP into Docker (commands above).
7. **QA:** Follow `TECH_READINESS.md` + `QA_TOOLBELT.md` — at minimum:
   - `qa-phpcs plugin/bb-groomflow`
   - Manual admin happy-path (Clients, Guardians, Services, Packages, Flags, Views, Settings → create + edit)
   - Any slice-specific automation listed in `PROJECT_PLAN.md`
8. **Artifacts:** Copy CLI/QA outputs to `/opt/qa/artifacts/<descriptive-name>` and record the paths in both `docs/REFACTOR_LOG.md` and `qa/QA_LOG.md`.
9. **Documentation sweep:** Update:
   - `PROJECT_PLAN.md` status for the slice
   - `docs/HISTORY.md` (what landed, why it matters, QA evidence)
   - `docs/NOTES.md` (tips/gotchas)
   - `CHANGELOG.md` if structure or behavior changed
10. **Breadcrumb:** Add a new entry in `docs/breadcrumbs/` summarizing the run (use `docs/BREADCRUMBS_TEMPLATE.md`).

---

## Handoff Checklist
- [ ] `docs/REFACTOR_LOG.md` entry marked **Completed** or **Blocked** with full context
- [ ] QA artifacts logged in both `docs/REFACTOR_LOG.md` and `qa/QA_LOG.md`
- [ ] `PROJECT_PLAN.md`, `docs/HISTORY.md`, `docs/NOTES.md`, `CHANGELOG.md` updated as needed
- [ ] ZIP rebuilt and installed inside Docker after the final change
- [ ] `qa-phpcs plugin/bb-groomflow` run against the current tree
- [ ] Manual admin happy-path recorded
- [ ] `git status` clean; branch merged into `dev`; `dev` pushed

If any box stays unchecked, you are not done—leave the slice “Blocked” with details and do not merge.

---

## Communication
- Always end your final message with **exactly one line**: `READY FOR NEXT SLICE` or `BLOCKED ON THIS SLICE: <reason>`.
- Document blockers immediately in `docs/REFACTOR_LOG.md`, update `PROJECT_PLAN.md`, and notify the Product Owner if additional context is required.
- Never push raw tool output or artifacts into the repo; only log their filesystem paths.

Follow this ritual every time so the next stateless agent can resume without guessing.
