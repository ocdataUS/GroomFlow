# QA_LOG — Automation & Manual Evidence

| Date | Agent | Slice | Scenario / Command | Artifacts | Result |
| --- | --- | --- | --- | --- | --- |
| 2025-11-09 | maint | Baseline cleanup & S7 planning | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1762669738.txt` | PASS |
| 2025-11-09 | codex | S6 · Board Localization & Placeholder Accessibility | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1762668016.txt` | PASS |
| 2025-11-09 | codex | S6 · Board Localization & Placeholder Accessibility | Manual admin happy-path (WP-CLI updates for Clients→Settings) | `/opt/qa/artifacts/manual-admin-happy-path-20251109T060122-s6.txt` | PASS |
| 2025-11-08 | codex | S5 · Documentation & QA Enforcement | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1762667282.txt` | PASS |
| 2025-11-08 | codex | S5 · Documentation & QA Enforcement | Manual admin happy-path (WP-CLI updates for Clients→Settings) | `/opt/qa/artifacts/manual-admin-happy-path-20251109T054819-s5.txt` | PASS |
| 2025-11-08 | codex | S4 · CLI & Seed Data Hardening | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1762666565.txt` | PASS |
| 2025-11-08 | codex | S4 · CLI & Seed Data Hardening | Manual admin happy-path (Clients→Settings create/edit via wp-cli data mutations) | `/opt/qa/artifacts/manual-admin-happy-path-20251108-s4.txt` | PASS (data writes confirmed) |
| 2025-11-08 | codex | S4 · CLI & Seed Data Hardening | `wp bbgf visits seed-demo --count=4 --force` | `/opt/qa/artifacts/seed-demo-20251108233016.txt` | PASS |
| 2025-11-08 | codex | S3 · Plugin Bootstrap Modularization | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1762665491.txt` | PASS |
| 2025-11-08 | codex | S3 · Plugin Bootstrap Modularization | Manual admin happy-path (Clients→Settings create/edit + dashboard shell) | `/opt/qa/artifacts/manual-admin-happy-path-20251108-s3.txt` | PASS |
| 2025-11-08 | codex | S2 · Uninstall & Data Cleanup | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1762654708.txt` | PASS |
| 2025-11-08 | codex | S2 · Uninstall & Data Cleanup | Manual admin happy-path (Clients→Settings create/edit) | `/opt/qa/artifacts/manual-admin-happy-path-20251108-s2.txt` | PASS |
| 2025-11-08 | onboarding | Sentinel Prep | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-bbgf-20251108T174330.txt` | WARNINGS (expected custom capability notices) |
| 2025-11-08 | codex | S1 · PHPCS & Coding Standards | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1762654083.txt` | PASS |
| 2025-11-08 | codex | S1 · PHPCS & Coding Standards | Manual admin happy-path (Clients→Settings create/edit) | `/opt/qa/artifacts/manual-admin-happy-path-20251108.txt` | PASS |

Add one row per QA action (automation or manual walkthrough). Reference the exact artifact path so reviewers can inspect the evidence later.
