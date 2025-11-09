# Documentation Index — GroomFlow

Use this map to orient yourself before diving into sprint work.

## Core Briefing
- `AGENTS.md` — runbook, first-hour checklist, daily loop.
- `SPEC.md` — product vision, scope, milestones, roles/caps.
- `README.md` — repository overview, where plugin code lives.

## Planning & Roadmap
- `docs/ROADMAP.md` — milestone overview by sprint.
- `docs/sprints/` — sprint-specific acceptance criteria (start with Sprint-0).
- `docs/BREADCRUMBS_TEMPLATE.md` — required breadcrumb format.
- `docs/CHANGE_MANAGEMENT.md` — how to handle mid-sprint pivots and update docs.

## Architecture & Implementation
- `docs/ARCHITECTURE.md` — plugin structure, data flow, and integration touchpoints.
- `docs/API.md` — REST endpoints (keep in sync with SPEC + sprint work).
- `docs/DB_SCHEMA.md` — custom tables + versioning.
- `docs/SECURITY.md` — caps, nonces, and sanitization rules.
- `docs/FRONTEND_BINDINGS.md` — IDs/classes expected by the UI.
- `docs/UX_GUIDE.md` — accessibility, motion, tone guidance.

## Delivery Workflow
- `docs/workflow.md` — git + SSH setup, build/install loop, QA flow.
- `docker/README.md` — Docker stack usage, WP-CLI commands.
- `QA_TOOLBELT.md` — QA expectations and toolbelt reference.
- `CODESTYLE.md` — linting/style requirements (`phpcs.xml.dist` applies WordPress standards).
- `scripts/load_prod_snapshot.sh` — hydrate Docker volumes from `docker/prod-sync/`.

## Reference Assets
- `docs/context/` — generated context packs (run `scripts/generate_context_pack.sh` when needed).
- `docs/breadcrumbs/` — historical run logs; create a new file each working session.

Keep this index aligned with repo changes. If you add or rename docs, update the list so new agents never guess where key information lives.
