# NOTES — GroomFlow Field Guide

## Tips & Tricks
- `docker compose cp` is faster than mounting the plugin directory; always copy the packaged ZIP into `/var/www/html`.
- Use `docker compose run --rm -T wpcli wp bbgf visits seed-demo --count=8 --force` after every reinstall to populate all views for manual QA.
- `qa-phpcs` outputs to `/opt/qa/artifacts`; capture the exact filename in your log entry so others can review it later.
- Schema upgrades should flow through `Schema::get_table_sql()` + `dbDelta()` instead of ad-hoc `ALTER TABLE` calls so PHPCS stays clean and we benefit from the centralized definitions.
- Need to preserve data during uninstall testing? Define `BBGF_PRESERVE_DATA_ON_UNINSTALL` as `true` in `wp-config.php` or hook the `bbgf_allow_uninstall_cleanup` filter to skip the new teardown routine.
- `Plugin` now bootstraps lightweight services (`BBGF\Bootstrap\Assets_Service`, `Admin_Menu_Service`, `Rest_Service`, `Cli_Service`). Call `bbgf()->bootstrap_elementor()` or `bbgf()->get_placeholder_board_markup()` if you need the old helpers—both proxy to the assets service while keeping public APIs stable.
- `wp bbgf visits seed-demo` now logs each view it seeds, truncates via prepared statements when `--force` is used, and writes visit timers based on the generated `check_in_at` timestamp so the board immediately shows elapsed time that matches the demo narrative.
- `BBGF\Data\Visit_Service::create_visit()` will honor `created_at`, `updated_at`, and `timer_started_at` values passed in (falling back to `now()` if missing); use this when importing historical visits so timers stay accurate.
- Need to log the manual admin happy-path without a browser? Grab the table prefix via `docker compose run --rm -T wpcli wp config get table_prefix`, run the WP-CLI update queries listed in `TECH_READINESS.md` / `QA_TOOLBELT.md`, and pipe the transcript to `/opt/qa/artifacts/manual-admin-happy-path-<timestamp>.txt` so auditors can confirm each form’s persistence.
- Board previews now localize strings + ARIA labels through `bbgf_board_script_settings` (JS payload) and `bbgf_placeholder_board_data` (placeholder dataset) filters; hook these to swap default timers, placeholder cards, or localization before Elementor/shortcode rendering.

## Known Gaps / Watch-outs
- PHPCS currently flags custom capabilities and schema changes; S1 slice will address this. Until then, expect warnings on `class-plugin.php` and `class-visits-command.php`.

Add new insights as you discover them so future agents avoid relearning the same lessons.
