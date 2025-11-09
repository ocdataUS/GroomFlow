# SPEC — Bubbles & Bows GroomFlow (Standalone WordPress Plugin)

**Vision:** Deliver a calm, Apple‑like grooming operations suite that replaces corkboards and spreadsheets. GroomFlow centers the **animal client** and guides the team through every stage of the visit with a responsive, accessible Kanban UI, rich customer records, and flexible customization for non-technical staff.

> **Terminology:** All UI copy, documentation, and new code must refer to the animal being groomed as the “Client.” Dogs remain our primary example data, but the platform needs to feel species-agnostic (cats, bunnies, miniature ponies, etc.). Back-end identifiers, REST routes, and database schema should migrate toward `client` naming in upcoming work.

> **Living document:** Mike iterates quickly. When requirements shift, update this spec, roadmap, and sprint docs immediately so future agents stay aligned.

---

## Core Pillars

### 1. Flexible Board Views
- Unlimited views configurable in the admin.
- Each view defines the stage order, column labels, capacity limits, refresh interval, and whether it is internal, lobby, or kiosk.
- Internal pages can display a compact view switcher for staff. Lobby/kiosk views are fixed, tokenized, and auto-refresh on a configurable cadence.

### 2. Rich Client & Visit Records
- **Clients (Animals):** name, species, breed/type, coat, weight, sex, date of birth, temperament, behavior flags (emoji/icon chips), preferred groomer, vaccination/medical notes, photo gallery.
- **Guardians:** multi-contact fields, communication preference, address, notes.
- **Visits (tickets):** check-in/out timestamps, assigned staff, stage timers, private/public notes, reusable instructions, photos, attachments.
- **Behavior Flags:** admin-managed definitions such as `🚨 DNPWWO`, `🚨 Peemail subscriber`, including icon, color, and severity.

### 3. Services & Packages
- Services are global objects with icon, color, estimated duration, optional price, and tags (e.g., “Bath”, “Teeth”, “Nail Grind”).
- Packages bundle services (e.g., “Full Groom”) and include optional add-ons.
- Visits link to both the package and flattened services for reporting.

### 4. Polished Kanban Experience
- Drag-and-drop with keyboard fallback, animated column transitions, momentum-like card movement.
- Quick move buttons (Next/Back) directly on the card.
- Live stage timers that shift from green → yellow → red based on thresholds defined in settings.
- Soft capacity alerts when a stage exceeds configured limits.
- Card summary shows client photo, name, drop-off time, service icons, flags, guardian initials; clicking opens a modal editor.
- Modal tabs for Summary, Services, Notes, History, Photos, with inline editing and audit trail preview.

### 5. Elementor & Shortcode Delivery
- Shortcode `[bbgf_board]` supports `view`, `allow_view_switcher`, `refresh`, and public token arguments.
- Elementor widget exposes deep control:
  - Content: choose view, toggle switcher, mask guardian data, set refresh interval.
  - Style: granular typography, spacing, colors for columns/cards/headers, conditional styling (overdue, flagged, capacity).
  - Advanced: repeater to define metadata order, custom badge templates, optional HTML slots for advanced layouts.
- All controls are intuitive for non-developers; defaults ship with a modern neutral theme.

### 6. Notifications & Reporting
- Stage-triggered notifications managed in admin with HTML templates and merge tags (client name, guardian, stage, notes). Each trigger supports sending to the guardian, a custom distribution list, or both. The template editor surfaces a merge-tag helper so non-technical admins always know which placeholders are available.
- Email delivery in v1 (`wp_mail`); hooks provided for SMS or external integrations.
- Reporting dashboard with KPI cards (in-progress counts, average time per stage, overdue tickets, popular services) and simple charts.
- CSV/PDF exports for daily summaries, service breakdown, visit logs.

### 7. Future Integrations
- Normalized custom tables ready for API connectors (e.g., 123Pet, Gingr).
- REST API designed for external use with capability checks and token-based access.
- WP-CLI entry points for batch syncs/imports.
- Pluggable hooks (`bbgf_visit_created`, `bbgf_stage_changed`, `bbgf_notification_sent`) to extend without core edits.

---

## Non-functional Requirements
- WordPress 6.4+; PHP 8.1+; text domain `bb-groomflow`.
- Strict adherence to WordPress Coding Standards; PHPCS enforced.
- Accessibility: keyboard DnD fallback, focus management, aria-live updates, WCAG AA contrast, reduced motion respect, 48px touch targets.
- Performance: board interactions must remain smooth with ~200 active visits; REST endpoints support incremental fetch via `modified_after` parameter.
- Manual packaging only (`bash scripts/build_plugin_zip.sh`); QA always installs the ZIP in Docker.
- No binding the plugin directory into Docker volumes—use copy operations to keep QA fast.

---

## Roles & Capabilities

Plugin-defined roles:
- `bb_manager` – full access.
- `bb_reception` – intake, edit visits, move stages, view reports.
- `bb_bather` / `bb_groomer` – move assigned visits, add notes/photos, update services.
- `bb_lobby` – read-only board access for lobby displays.

Capabilities (mapped via settings UI):
- `bbgf_view_board`, `bbgf_move_stages`, `bbgf_edit_visits`, `bbgf_manage_views`, `bbgf_manage_services`, `bbgf_manage_flags`, `bbgf_manage_notifications`, `bbgf_view_reports`, `bbgf_manage_settings`.
- Sensitive notes and flags are automatically hidden from public/lobby views.

---

## Data Model (Custom Tables)

| Table | Purpose |
| --- | --- |
| `wp_bb_clients` | Core client profile (metadata JSON for extensibility). |
| `wp_bb_guardians` | Guardian contact records. |
| `wp_bb_services` | Individual services with icon/color/duration. |
| `wp_bb_service_packages` | Package definitions. |
| `wp_bb_service_package_items` | Mapping packages → services. |
| `wp_bb_flags` | Behavior/alert definitions. |
| `wp_bb_visits` | Active/past tickets (client_id, guardian_id, stage, timers). |
| `wp_bb_visit_services` | Visit-to-service linking (flattened). |
| `wp_bb_visit_flags` | Visit-specific applied flags. |
| `wp_bb_stage_history` | Full audit log of stage moves & comments. |
| `wp_bb_views` | Board view definitions, public token hashes, refresh settings. |
| `wp_bb_view_stages` | View → stage order and capacity config. |
| `wp_bb_notifications` | Template store (HTML/text). |
| `wp_bb_notification_triggers` | Mapping of stages → templates/channels. |

Schema managed via `dbDelta` with `BBGF_DB_VERSION`. Provide install/upgrade routines and seed starter data (default stages, services, flags).

---

## REST API (`bb-groomflow/v1`)

- `GET /board` – fetch visits for a view; supports `view`, `stages[]`, `modified_after`, `public_token`.
- `POST /visits` – create intake; handles new/existing clients & guardians.
- `PATCH /visits/{id}` – update notes, instructions, assigned staff, services.
- `POST /visits/{id}/move` – change stage with comment; records history and recalculates timers.
- `POST /visits/{id}/photo` – upload attachment; returns media data.
- `GET/POST/PATCH /clients`, `/guardians`, `/services`, `/packages`, `/flags`.
- `GET/POST/PATCH /views` – manage view configuration and tokens.
- `GET /stats/daily`, `GET /stats/stage-averages`, `GET /stats/service-mix`.
- Authentication: WP nonces for logged-in users, token parameter for public boards (read-only, masked).
- All endpoints check capabilities and sanitize/safely return data.

---

## Settings & Localization

Option `bbgf_settings` includes:
- Global defaults (thresholds, capacity limits, polling interval, branding presets).
- Notification defaults (from email, subject prefix, enable stage triggers).
- Lobby options (mask guardian names, show client photo, full-screen button).
- Elementor defaults (card style preset, stage label format).

Settings localized to JS (`wp_localize_script`) and Elementor widget controls.

---

## Visual & UX Guidelines

- Columns use layered gradients, rounded corners, sticky headers, optional column background images.
- Cards animate with transform/opacity transitions (<180 ms) when dropped; micro-interactions on hover.
- Behavior flags render as colored pill chips with tooltip descriptions.
- Modal design: blurred backdrop, segmented controls, sticky action bar (“Save”, “Send Ready Email”, “Move Stage”).
- Lobby mode: full-screen toggle, large text/photo, “Last refreshed” indicator, optional queue numbering.
- Quick access toolbar for staff: search client name, filter by flag/service, manual refresh button.

---

## Milestones (Sprints)

1. **Sprint 0 – Enhanced Bootstrap**  
   Admin shell, roles/caps, asset pipeline, REST namespace stub, Elementor widget skeleton, placeholder Kanban.
2. **Sprint 1 – Data Foundations & Admin CRUD**  
   Custom tables, CRUD UIs for clients/guardians/services/packages/flags/views, settings page with thresholds/polling, seed data using the client-first terminology.
3. **Sprint 2 – REST & Business Logic**  
   Intake endpoints, stage move logic, history logging, public token support, CLI scaffolds, capability enforcement.
4. **Sprint 3 – Kanban UX & Modal Editing**  
   Implement fully interactive board with drag/drop, timers, capacity alerts, modal editor, polling engine, lobby mode.
5. **Sprint 4 – Elementor & Shortcode Polish**  
   Deep Elementor controls, view switcher, metadata repeater, conditional styling, shortcode parity, public/private display options.
6. **Sprint 5 – Notifications, Reporting, QA**  
   Stage-triggered emails, notification templates, KPI dashboard, CSV/PDF export, accessibility/perf polish, automated smoke tests.

Each sprint requires: updated docs, QA artifacts via `QA_TOOLBELT.md`, packaged ZIP installed in Docker, breadcrumb entry, and CHANGELOG update.
