# Breadcrumb
- **Task asked:** Add intuitive recipient controls for notification triggers so admins can target guardian emails or custom lists while keeping the codebase PHPCS clean.
- **Plan:** Confirm existing trigger behavior → extend admin form/list/service logic for multi-recipient strategies → update SPEC/API/DB docs → run PHPCS, rebuild/install ZIP in Docker, and sanity-check recipient resolution via WP-CLI.
- **Files changed:** plugin/bb-groomflow/includes/admin/class-notification-triggers-admin.php; plugin/bb-groomflow/includes/admin/views/notification-triggers-page.php; plugin/bb-groomflow/includes/admin/class-notification-triggers-list-table.php; plugin/bb-groomflow/includes/admin/class-notifications-list-table.php; plugin/bb-groomflow/includes/notifications/class-notifications-service.php; plugin/bb-groomflow/assets/css/admin.css; plugin/bb-groomflow/assets/js/admin.js; SPEC.md; docs/API.md; docs/DB_SCHEMA.md
- **Commands executed:** `qa-phpcs plugin/bb-groomflow` → `/opt/qa/artifacts/phpcs-1761761796.txt`; `npm run build`; `bash scripts/build_plugin_zip.sh`; `docker compose up -d`; `docker compose run --rm -T wpcli sh -c "cat > /tmp/bbgf.zip && wp plugin install /tmp/bbgf.zip --force --activate" < ../build/bb-groomflow-0.1.0-dev.zip`; `docker compose run --rm -T wpcli wp eval-file /var/www/html/bbgf-recipient-check.php` (reflection sanity check, file removed afterwards)
- **Tests & results:** PHPCS passes aside from known schema-change warnings (artifact above). Reflection QA via `wp eval-file` returned:
  ```json
  {
    "normalized": "team@example.com, concierge@example.com, staff@example.org",
    "guardian_plus": [
      "guardian@example.com",
      "team@example.com",
      "concierge@example.com",
      "staff@example.org"
    ],
    "custom_only": [
      "team@example.com",
      "concierge@example.com",
      "staff@example.org"
    ],
    "guardian_only": [
      "guardian@example.com"
    ]
  }
  ```
- **Tips & Tricks:** Use `docker compose run --rm -T --user root wpcli sh -c 'cat > /var/www/html/<file>.php'` to stage temporary eval scripts, then call `wp eval-file` for private-method checks without a browser. When installing the ZIP, pipe it through `cat` into the wpcli container to avoid host-volume limitations.
- **Remaining work:** Walk the notification trigger form in the WP admin UI to confirm the radio/textarea UX and copy feel right; follow up with end-to-end stage-change tests once real email transport/logging is wired.
