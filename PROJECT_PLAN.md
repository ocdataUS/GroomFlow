# PROJECT_PLAN — GroomFlow Refactor Backlog

Each slice is standalone. Claim one, log it in `docs/REFACTOR_LOG.md`, and keep this list updated.

| Slice | Description & Target Files | QA Expectations | Status |
| --- | --- | --- | --- |
| **S1 · PHPCS & Coding Standards Remediation** | Clean up WordPress Coding Standards violations (custom capability annotations, schema-change comments, indentation, short ternaries) across `plugin/bb-groomflow/includes/class-plugin.php`, `includes/cli/class-visits-command.php`, `includes/data/class-visit-service.php`, `includes/notifications/class-notifications-service.php`. Ensure PHPCS runs clean without blanket ignores. | `qa-phpcs plugin/bb-groomflow`; manual admin dashboard smoke to ensure no regressions. | Ready |
| **S2 · Uninstall & Data Cleanup** | Implement real uninstall logic in `plugin/bb-groomflow/uninstall.php` (drop custom tables, delete `bbgf_*` options, with safeguards). Add user-facing setting/constant if needed. Update docs. | Install plugin → create sample data → uninstall via wp-admin + `wp plugin uninstall bb-groomflow --deactivate --uninstall`; verify tables/options removed. | Ready |
| **S3 · Plugin Bootstrap Modularization** | Break `includes/class-plugin.php` into smaller services (assets, admin menu, REST, CLI) to reduce ignores and improve testability. Update autoload/bootstrap wiring. | Full rebuild/install; run manual admin happy-path + board view load; `qa-phpcs`. | Planned |
| **S4 · CLI & Seed Data Hardening** | Update `includes/cli/class-visits-command.php` + related helpers to use `wp_rand()`, proper timestamps, prepared statements, and better error reporting. Document commands in `docs/workflow.md`. | `qa-phpcs`; run `docker compose run --rm -T wpcli wp bbgf visits seed-demo --count=4 --force`; verify visits populated. | Planned |
| **S5 · Documentation & QA Enforcement** | After code refactors, ensure README, SPEC, TECH_READINESS, QA_TOOLBELT stay current; add any missing Playwright/manual guidance. | `qa-phpcs` for docs (spellcheck optional); manual verification that docs reflect latest flow. | Planned |

Add new slices or change statuses as work progresses. Every slice must list explicit QA so the next agent knows how to verify.
