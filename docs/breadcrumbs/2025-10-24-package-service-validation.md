# Breadcrumb
- **Task asked:** Fix the package form error that claims no services were selected even when multiple services are checked.
- **Plan:** Inspect the package save handler, confirm why selections vanish, and patch validation without touching schema or UI.
- **Files changed:** plugin/bb-groomflow/includes/admin/class-packages-admin.php; build/bb-groomflow-0.1.0-dev.zip (regenerated).
- **Commands executed:** php -l plugin/bb-groomflow/includes/admin/class-packages-admin.php; bash scripts/build_plugin_zip.sh; docker compose cp ../build/bb-groomflow-0.1.0-dev.zip wordpress:/var/www/html/bb-groomflow-0.1.0-dev.zip; docker compose run --rm -T wpcli wp plugin install /var/www/html/bb-groomflow-0.1.0-dev.zip --force --activate.
- **Tests & results:** Syntax check passes; plugin rebuild and reinstall succeed. Root cause traced to strict comparisons against service IDs stored as strings—`array_map( 'intval', … )` now normalizes the valid ID list so selected services persist.
- **Tips & Tricks:** When validating checkbox selections sourced from the database, cast both user input and canonical IDs to the same type before doing strict comparisons.
- **Remaining work:** Run the full package form happy-path in the browser to verify services stay selected and ordering saves as expected.
