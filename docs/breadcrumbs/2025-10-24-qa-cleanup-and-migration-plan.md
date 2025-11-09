# Breadcrumb
- **Task asked:** Clean out the QA fixtures created during admin form testing and outline the end-to-end Dogs → Clients migration plan.
- **Plan:** 1) Delete the temporary records (guardian 3, client 3, service 4, package 3, flag 6, view 2) plus dependent rows via WP-CLI SQL. 2) Capture a standalone migration plan document detailing schema changes, REST aliases, upgrade steps, and QA requirements.
- **Files changed:** docs/clients-migration-plan.md.
- **Commands executed:**  
  `docker compose run --rm -T wpcli wp db query "DELETE FROM …"` (guardians, dogs, services, service_package_items, service_packages, flags, view_stages, views)  
  `docker compose run --rm -T wpcli wp db query "SELECT …"` to verify cleanup.
- **Tests & results:** All QA records removed—top rows now match pre-qa state (only IDs 1–2 remain across guardians, clients, services, packages, flags; view table back to ID 1). New plan doc committed for future sprint work.
- **Tips & Tricks:** When using WP-CLI `wp db query` inside Docker, remember the randomized table prefix (`wp_7ptz4bz8ht_…`); query `SHOW TABLES LIKE '%guardians%'` first if you’re unsure.
- **Remaining work:** Execute the migration plan (schema rename + REST updates) in an upcoming sprint and communicate the breaking-change window.
