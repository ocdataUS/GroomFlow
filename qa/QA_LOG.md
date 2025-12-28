# QA_LOG — Automation & Manual Evidence

| Date | Agent | Slice | Scenario / Command | Artifacts | Result |
| --- | --- | --- | --- | --- | --- |
| 2025-12-28 | codex | Visit lifecycle hardening | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1766959360.txt` | PASS |
| 2025-12-28 | codex | Visit lifecycle hardening | Manual visit lifecycle + modals walkthrough | `/opt/qa/artifacts/manual-visit-lifecycle-20251228T220301Z.txt` | PASS |
| 2025-12-28 | codex | Visit lifecycle hardening | `wp eval rest_do_request(POST /bb-groomflow/v1/visits/2059/checkout)` | `/opt/qa/artifacts/rest-checkout-20251228T220436Z.txt` | PASS |
| 2025-12-25 | codex | Intake modal tabs | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1766698745.txt` | PASS |
| 2025-12-25 | codex | Intake modal tabs | `node scripts/test_intake_modal.js` | `/opt/qa/artifacts/intake-modal-smoke-20251225T213833Z.txt` | PASS |
| 2025-12-25 | codex | Intake modal tabs | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1766696857.txt` | PASS |
| 2025-12-25 | codex | Intake modal tabs | `node scripts/test_intake_modal.js` | `/opt/qa/artifacts/intake-modal-smoke-20251225T210817Z.txt` | PASS |
| 2025-12-25 | codex | Intake modal | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1766695233.txt` | PASS |
| 2025-12-25 | codex | Intake modal | Check-in modal headless smoke (`node scripts/test_intake_modal.js`) | `/opt/qa/artifacts/intake-modal-smoke-20251225T203704Z.txt` | PASS |
| 2025-12-24 | codex | Photo modal upload fix (primary toggle) | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1766610536.txt` | PASS |
| 2025-12-24 | codex | Photo modal upload fix | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1766609611.txt` | PASS |
| 2025-12-24 | codex | Photo modal upload fix | Photo tab headless smoke (`node scripts/test_modal_photo_tab.js`) | `/opt/qa/artifacts/photo-modal-smoke-20251224T204943Z.txt` | PASS |
| 2025-12-24 | codex | Photo UX main badge follow-up | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1766599870.txt` | PASS |
| 2025-12-24 | codex | Photo UX main badge follow-up | Board payload stage counts (`docker compose run --rm -T wpcli wp eval ...`) | `/opt/qa/artifacts/board-stage-counts-20251224T181318Z.txt` | PASS |
| 2025-12-23 | codex | Kanban UX refresh | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1766472465.txt` | PASS |
| 2025-12-23 | codex | Kanban UX refresh | Manual admin happy-path (WP-CLI edits across Clients→Settings) | `/opt/qa/artifacts/manual-admin-happy-path-20251223T064823.txt` | PASS |
| 2025-12-02 | codex | Release readiness checklist & compliance | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1764705101.txt` | PASS |
| 2025-12-02 | codex | Release readiness checklist & compliance | Manual admin happy-path (WP-CLI updates for Clients→Settings) | `/opt/qa/artifacts/manual-admin-happy-path-20251202T195200.txt` | PASS |
| 2025-12-02 | codex | S12 · QA automation | `bash scripts/qa_smoke.sh` | `/opt/qa/artifacts/qa-smoke-20251202T193642Z.txt` | PASS |
| 2025-12-02 | codex | S12 · QA automation | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1764704214.txt` | PASS |
| 2025-12-02 | codex | S12 · QA automation | Manual admin happy-path (WP-CLI updates for Clients→Settings) | `/opt/qa/artifacts/manual-admin-happy-path-20251202T193829.txt` | PASS |
| 2025-12-02 | codex | Board caching TTL safeguards | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1764702851.txt` | PASS |
| 2025-12-02 | codex | Board caching TTL safeguards | Manual admin happy-path (WP-CLI updates for Clients→Settings) | `/opt/qa/artifacts/manual-admin-happy-path-20251202T191431.txt` | PASS |
| 2025-12-02 | codex | Board caching TTL safeguards | Board payload cache flush/diff (`wp eval` REST patch + board fetch) | `/opt/qa/artifacts/board-cache-20251202T191544.txt` | PASS |
| 2025-12-02 | codex | Modal inline-edit QA verification | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1764700791.txt` | PASS |
| 2025-12-02 | codex | Modal inline-edit QA verification | Manual admin happy-path (WP-CLI updates for Clients→Settings) | `/opt/qa/artifacts/manual-admin-happy-path-20251202T184004.txt` | PASS |
| 2025-12-02 | codex | Modal inline-edit QA verification | Modal notes/services REST save + board snapshot (`wp eval ...`) | `/opt/qa/artifacts/modal-qa-20251202T184049.txt` | PASS |
| 2025-12-02 | codex | Modal inline-edit QA verification | `wp bbgf visits list --limit=5` | `/opt/qa/artifacts/board-list-20251202T184057.txt` | PASS |
| 2025-12-02 | codex | S11 · Board controls completion | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1764699521.txt` | PASS |
| 2025-12-02 | codex | S11 · Board controls completion | Manual admin happy-path (WP-CLI updates for Clients→Settings) | `/opt/qa/artifacts/manual-admin-happy-path-20251202T181902.txt` | PASS |
| 2025-12-02 | codex | S11 · Board controls completion | `wp bbgf visits list --limit=5` | `/opt/qa/artifacts/board-list-20251202T182008.txt` | PASS |
| 2025-12-02 | codex | Visit_Service PHPCS hardening | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1764696502.txt` | PASS |
| 2025-12-02 | codex | Visit_Service PHPCS hardening | Manual admin happy-path (WP-CLI updates for Clients→Settings) | `/opt/qa/artifacts/manual-admin-happy-path-20251202T172926Z.txt` | PASS |
| 2025-12-02 | codex | Visit_Service PHPCS hardening | `wp bbgf visits list --limit=5` | `/opt/qa/artifacts/board-list-20251202T173003Z.txt` | PASS |
| 2025-11-09 | maint | S7 · Board Polling & REST Wiring | `qa-phpcs plugin/bb-groomflow` | `/opt/qa/artifacts/phpcs-1762671186.txt` | PASS |
| 2025-11-09 | maint | S7 · Board Polling & REST Wiring | `wp eval … build_board_payload` | `/opt/qa/artifacts/board-payload-20251109T005409.json` | PASS |
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
