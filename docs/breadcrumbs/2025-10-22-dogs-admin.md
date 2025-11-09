# Breadcrumb
- **Task asked:** Build the WordPress admin CRUD surface for client profiles (Sprint 1).
- **Plan:** 1) Add Clients admin/list table classes. 2) Wire menu + form handling with nonces/capability checks. 3) Render list/detail view templates. 4) Re-run PHPCS and reinstall plugin to verify activation. 5) Document progress.
- **Files changed:** plugin/bb-groomflow/includes/class-plugin.php; plugin/bb-groomflow/includes/admin/class-dogs-admin.php; plugin/bb-groomflow/includes/admin/class-dogs-list-table.php; plugin/bb-groomflow/includes/admin/views/dogs-page.php; plugin/bb-groomflow/includes/admin/views/flags-page.php; plugin/bb-groomflow/includes/admin/class-flags-admin.php; plugin/bb-groomflow/includes/admin/class-flags-list-table.php; CHANGELOG.md; docs/breadcrumbs/2025-10-22-dogs-admin.md (this file).
- **Commands executed:** npm run build; bash scripts/build_plugin_zip.sh; docker compose run --rm -T wpcli wp plugin install /var/www/html/bb-groomflow-0.1.0-dev.zip --force --activate; qa-phpcs plugin/bb-groomflow.
- **Tests & results:** `qa-phpcs plugin/bb-groomflow` → /opt/qa/artifacts/phpcs-1761174856.txt (clean). Manual verification pending (log-in to admin to CRUD clients).
- **Tips & Tricks:** Use the new Clients admin at `/wp-admin/admin.php?page=bbgf-clients`. Guardians dropdown pulls from `bb_guardians`, so seed guardians first when testing.
- **Remaining work:** CRUD for guardians/services/packages/views, settings UI, deeper QA.
