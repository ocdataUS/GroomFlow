# Sprint 1 — Data Foundations & Admin CRUD

## Goal
Create the persistent schema and admin interfaces so staff can manage clients, guardians, services, packages, flags, and views without touching code.

## Deliverables
- Custom tables via `dbDelta` (`bb_clients`, `bb_guardians`, `bb_services`, `bb_service_packages`, `bb_service_package_items`, `bb_flags`, `bb_views`, `bb_view_stages`, `bb_visits`, `bb_visit_services`, `bb_visit_flags`, `bb_stage_history`).
- Activation/upgrade routines with `BBGF_DB_VERSION` and seed data (default stages, starter flags, sample services).
- Admin CRUD screens using WP_List_Table + modal forms for:
  - Clients (link to guardians, temperament, notes).
  - Guardians (contacts, preferred communication).
  - Services & Packages (icon, color, duration, price optional).
  - Flags (icon/emoji, color, severity).
  - Views (name, slug, stage assignment/order, capacity limits, refresh interval, public/private).
- Global settings page storing `bbgf_settings` (thresholds, polling interval, lobby options, branding presets).
- Capability checks + nonces on all admin actions.
- Documentation updates: schema diagram, CRUD usage.

## Acceptance Criteria
- Tables exist and version increments handled; data persists across plugin activation/deactivation cycles.
- Admin screens allow create/update/delete for each entity with validation and feedback.
- Views can assign stages and capacity values; data stored correctly.
- Settings saved + retrievable; localized to JS placeholder board (even if not yet fully used).
- QA: smoke test CRUD actions in Docker, record artifact path in breadcrumb.

## Progress (2025-10-29)
- ✅ Schema bootstrap + seed data (DB versioning hooked into activation).
- ✅ Admin CRUD live for Flags, Clients, Guardians, Services, Packages, and Views with search + badging polish.
- ✅ Settings page validated (thresholds, polling interval, lobby toggles, branding colors).
- ✅ Sprint 1 deliverables met; all CRUD tooling verified in Docker and ready for product-owner testing.
- ⏭️ Next: Transition to Sprint 2 REST scaffolding (intake endpoints, stage move logic).
