# Bubbles & Bows GroomFlow

WordPress plugin + docs for the GroomFlow operations platform. Start with `AGENTS.md` and its Documentation Map; this file is a snapshot of the system and code layout only.

## Scope
- **Lives here:** High-level system snapshot, code location map, build touchpoints.
- **Not here:** Onboarding steps, task routing, QA flow (see `AGENTS.md` + map).

## System Overview
- **Data layer:** Custom tables for clients, guardians, services, packages, flags, visits, visit services/flags, stage history, views, notifications, notification triggers.
- **Admin UI:** CRUD for all entities plus settings, notification templates/triggers, and reporting screens.
- **REST API (`bb-groomflow/v1`):** Board payloads, visit intake/edit/move, CRUD for clients/guardians/services/packages/flags/views/settings, stats endpoints.
- **Frontend board:** Shortcode `[bbgf_board]` renders interactive or lobby boards; JS bundle polls REST and drives modal/edit flows.
- **CLI:** `wp bbgf ...` commands for seeding demo data, listing visits, sync scaffolds.
- **Notifications & reporting:** Stage-triggered email delivery hooks and reporting endpoints for dashboards/exports.
- **QA & release:** Manual ZIP packaging, Docker WP installs, `qa-phpcs plugin/bb-groomflow`, and full admin happy-path before handoff.

## Where Things Live
- Plugin entry: `plugin/bb-groomflow/bb-groomflow.php`, `includes/class-plugin.php`
- Admin UI: `plugin/bb-groomflow/includes/admin/`
- Data/services: `plugin/bb-groomflow/includes/data/`, `includes/models/`
- REST: `plugin/bb-groomflow/includes/api/`
- Board assets: `plugin/bb-groomflow/assets/src/` → `assets/build/`
- CLI: `plugin/bb-groomflow/includes/cli/`
- Bootstrap/services: `plugin/bb-groomflow/includes/bootstrap/`
- Notifications: `plugin/bb-groomflow/includes/notifications/`
- QA scripts: `scripts/qa_smoke.sh`, `QA_TOOLBELT.md`

For onboarding, QA, and troubleshooting, follow `AGENTS.md` and the Documentation Map; this file is reference-only.
