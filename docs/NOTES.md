# NOTES — GroomFlow Field Guide

## Tips & Tricks
- `docker compose cp` is faster than mounting the plugin directory; always copy the packaged ZIP into `/var/www/html`.
- Use `docker compose run --rm -T wpcli wp bbgf visits seed-demo --count=8 --force` after every reinstall to populate all views for manual QA.
- `qa-phpcs` outputs to `/opt/qa/artifacts`; capture the exact filename in your log entry so others can review it later.

## Known Gaps / Watch-outs
- PHPCS currently flags custom capabilities and schema changes; S1 slice will address this. Until then, expect warnings on `class-plugin.php` and `class-visits-command.php`.
- Uninstall hook is a stub (`plugin/bb-groomflow/uninstall.php`). Marketplace review will require a real cleanup path—covered by S2.

Add new insights as you discover them so future agents avoid relearning the same lessons.
