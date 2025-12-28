# HOTFIX_PROTOCOL — GroomFlow v0 Beta

Purpose: tightly control post-beta changes that unblock users without altering product scope.

Allowed changes:
- Security fixes, data corruption stops, install/activation failures
- Critical regressions in existing flows (admin CRUD, board/REST/CLI, notifications)
- Dependency bumps required for security or compatibility

Disallowed changes:
- New features, UI redesigns, schema shifts, API contract changes
- Roadmap work, experiments, or speculative refactors
- Non-critical optimizations or polish

Execution steps:
1. Confirm the issue qualifies (see allowed list) and capture repro + impact.
2. Branch from the latest release tag or current beta branch: `git checkout -b hotfix/<topic>`.
3. Build and install the ZIP in Docker; reproduce and fix only the qualifying issue.
4. Run `qa-phpcs plugin/bb-groomflow` and the admin happy-path; log artifacts and impacts in `docs/breadcrumbs/` plus `qa/QA_LOG.md`.
5. Summarize the fix, QA evidence, and deploy intent in the branch description/PR; do not bundle unrelated changes.
