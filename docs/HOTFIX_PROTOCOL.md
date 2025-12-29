# HOTFIX_PROTOCOL — GroomFlow v0 Beta

## Scope
- **Lives here:** Allowed/disallowed hotfix criteria and the exact execution steps.
- **Not here:** Feature delivery process, onboarding, or architecture details (see `AGENTS.md` map).

Purpose: hotfixes cover bug fixes, UX/layout adjustments, copy changes, and behaviour tweaks from real usage or direct feedback—handled one item at a time.

Allowed changes:
- Bug/UX/copy/behaviour fixes driven by beta feedback
- Security fixes, data corruption stops, install/activation failures
- Critical regressions in existing flows (admin CRUD, board/REST/CLI, notifications)
- Dependency bumps required for security or compatibility

Disallowed changes:
- New features, re-architecture, new systems, UI redesigns, schema shifts, API contract changes
- Roadmap work, experiments, speculative refactors
- Non-critical optimizations or polish

This is an iterative prototype-hardening loop, not an emergency-only production response; avoid large punch lists and ship one qualifying item per hotfix branch.

Execution steps:
1. Confirm the issue qualifies (see allowed list) and capture repro + impact.
2. Branch from the latest release tag or current beta branch: `git checkout -b hotfix/<topic>`.
3. Build and install the ZIP in Docker; reproduce and fix only the qualifying issue.
4. Run `qa-phpcs plugin/bb-groomflow` and the admin happy-path; log artifacts and impacts in `docs/breadcrumbs/` plus `qa/QA_LOG.md`.
5. Summarize the fix, QA evidence, and deploy intent in the branch description/PR; do not bundle unrelated changes.
