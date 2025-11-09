# Breadcrumb
- **Task asked:** Stabilize Sprint 0 board preview after restart, fix Elementor widget visibility, and ensure responsive styling looks polished before moving on.
- **Plan:** 1) Rebuild/install plugin in fresh Docker session. 2) Refactor Elementor bootstrap so widget registers reliably without CLI fatals. 3) Tune placeholder board layout for narrower viewports and verify with QA screenshots. 4) Document updated workflow expectations.
- **Files changed:** plugin/bb-groomflow/includes/class-plugin.php; plugin/bb-groomflow/assets/src/style.scss; build/bb-groomflow-0.1.0-dev.zip (regenerated); docs/breadcrumbs/2025-10-22-elementor-responsive.md (this file); README.md (if updated?); docs/workflow.md (if updated?).
- **Commands executed:** docker compose up -d; npm run build; bash scripts/build_plugin_zip.sh; docker compose run --rm -T wpcli wp plugin install ... --force --activate; qa-screenshot http://localhost:8083/groomflow-test/; qa-phpcs plugin/bb-groomflow; wp eval (to verify Elementor widget).
- **Tests & results:** Screenshot `/opt/qa/artifacts/shot-1761151465.png` (desktop) and `/opt/qa/artifacts/shot-1761080702.png`; `qa-phpcs` → `/opt/qa/artifacts/phpcs-1761151745.txt`; `wp eval` confirmed widget registered (output `FOUND`).
- **Tips & Tricks:** After Docker restarts, reinstall the ZIP to refresh compiled assets; if Elementor widget disappears, run `wp eval` snippet from this breadcrumb to sanity-check registration.
- **Remaining work:** Proceed to Sprint 1 data layer once the placeholder passes product review.
