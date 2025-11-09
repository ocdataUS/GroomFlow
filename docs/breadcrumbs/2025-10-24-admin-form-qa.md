# Breadcrumb
- **Task asked:** Run the full happy-path QA checklist: create and edit Clients, Guardians, Services, Packages, Flags, Views, and Settings to confirm form data persists and edit screens pre-populate correctly.
- **Plan:** 1) Use WP-CLI to submit each admin form handler (mirrors `admin_init` posts with proper nonces/capabilities). 2) Verify database rows with `wp db query`. 3) Pull the edit screens via authenticated `curl` to confirm the expected values render. 4) Log settings option payload and view stage rows for future reference.
- **Commands executed:**  
  `docker compose run --rm -T wpcli wp eval 'wp_set_current_user(1); … do_action("admin_init");'` (multiple runs for guardians, clients, services, packages, flags, views, settings)  
  `docker compose run --rm -T wpcli wp db query "SELECT …"` (guardians, clients, services, packages, package items, flags, views, view stages, `bbgf_settings`)  
  `curl -s -b /tmp/bbgf-cookies.txt http://localhost:8083/wp-admin/admin.php?page=…` (edit screens for every entity)  
  `curl -s -c /tmp/bbgf-cookies.txt http://localhost:8083/wp-login.php` (establish session for HTML checks)  
  `docker compose run --rm -T wpcli wp option get bbgf_settings --format=json`
- **Tests & results:**  
  • Guardian ID 3 (`QA Guardian`) created, updated (`preferred_contact` → `sms`), and edit form shows `555-2222` + updated notes.  
  • Client ID 3 (`QA Client`) linked to guardian 3, weight updated to `96.00`, temperament/groomer reflected on the edit screen.  
  • Service ID 4 (`QA Wash`) updated (duration 50, price 90, color `#22c55e`) with values present in the edit UI.  
  • Package ID 3 (`QA Bundle`) saves service association (service 4) and edit screen shows the checkbox checked with order input persisted.  
  • Flag ID 6 (`QA Alert`) updated to emoji ⚠️, color `#f87171`, severity `medium`; edit table reflects new styling.  
  • View ID 2 (`QA Floor`) stores three stages (`check-in`, `spa-finish`, `checkout`) and settings JSON `{"accent_color":"#6366f1","background_color":"#e2e8f0"}`; edit page shows staged rows with expected labels.  
  • Global settings option `bbgf_settings` now carries the QA values (poll interval 50, branding colors `#1d4ed8/#9333ea`, notifications from `QA Desk <qa@salon.test>`); settings form displays the updated entries.  
- **Tips & Tricks:** When using WP-CLI to simulate admin form posts, set `wp_set_current_user(1)` and populate `$_POST`+`$_REQUEST` with a fresh nonce via `wp_create_nonce()`—`admin_init` will handle the rest and you can ignore the redirect warning.
- **Remaining work:** Delete/cleanup the QA fixtures (IDs: guardian 3, client 3, service 4, package 3, flag 6, view 2) before release; schedule the deeper schema/REST rename (`dogs` → `clients`) for an upcoming sprint.
