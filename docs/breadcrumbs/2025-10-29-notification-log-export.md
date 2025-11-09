# Breadcrumb
- **Task asked:** Improve Notification Activity UX with exportable audit data (filters, date ranges, search, CSV download).
- **Plan:** Extend list table filters → add CSV export pipeline with nonce protection → verify output + rebuild/install.
- **Files changed:** plugin/bb-groomflow/includes/admin/class-notification-logs-admin.php; plugin/bb-groomflow/includes/admin/class-notification-logs-list-table.php; plugin/bb-groomflow/includes/admin/views/notification-logs-page.php; CHANGELOG.md
- **Commands executed:** `npm run build`; `bash scripts/build_plugin_zip.sh`; `docker compose run --rm -T wpcli sh -c "cat > /tmp/bbgf.zip && wp plugin install /tmp/bbgf.zip --force --activate" < ../build/bb-groomflow-0.1.0-dev.zip`; `qa-phpcs plugin/bb-groomflow` → `/opt/qa/artifacts/phpcs-1761778710.txt`
- **Tests & results:** Manual export download returns CSV honoring filters and search; PHPCS clean except known schema warnings. Verified filter form retains values and export link respects nonces.
- **Tips & Tricks:** Use `remove_query_arg`/`add_query_arg` when constructing filter links to avoid dropping pagination; wrap CSV writes in WordPress sanitization to keep PHPCS satisfied.
- **Remaining work:** Add resend actions or bulk exports (JSON/S3) for operations teams; eventually surface notification logs in reporting dashboard.
