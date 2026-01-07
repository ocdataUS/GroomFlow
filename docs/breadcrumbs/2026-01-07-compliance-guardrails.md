# 2026-01-07 — Compliance Guardrails

- Added `scripts/qa_fast.sh` fast gate and repo-local `scripts/qa-phpcs` so custom capabilities are recognized in local PHPCS runs.
- Updated `phpcs.xml.dist` to register GroomFlow capabilities; added `phpcs-compat.xml.dist` for PHPCompatibilityWP (PHP 8.2+).
- Logged intentional PHPCS suppressions in `docs/COMPLIANCE_EXCEPTIONS.md`.
- Introduced `Query_Helpers` and `Visit_Repository`; `Visit_Service` delegates board/visit row SQL and uses shared pagination normalization.
- Removed inline capability suppressions now covered by the ruleset.
- QA: `SKIP_ASSET_BUILD=1 bash scripts/qa_smoke.sh`; manual admin happy-path via WP-CLI edits + settings reset.

Artifacts: `/opt/qa/artifacts/qa-smoke-20260107T173204Z.txt`, `/opt/qa/artifacts/phpcs-1767807140.txt`, `/opt/qa/artifacts/manual-admin-happy-path-20260107T173416.txt`.
