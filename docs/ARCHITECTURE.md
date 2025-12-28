# Architecture — GroomFlow Plugin

## Overview

GroomFlow is a standalone WordPress plugin that operates entirely within its own namespace (`BBGF`). All business entities live in custom database tables; WordPress core is leveraged for users, roles/capabilities, media, and settings. The front-end Kanban is powered by vanilla JavaScript modules bundled once and enqueued via standard WP asset APIs.

```
plugin/bb-groomflow/
├── bb-groomflow.php          # bootstrap, constants, activation hooks
├── includes/
│   ├── class-bbgf-plugin.php # singleton entry point (register hooks)
│   ├── api/                  # REST controllers (WP_REST_Controller subclasses)
│   ├── database/             # schema definitions, install/upgrade helpers
│   ├── admin/                # admin menu, list tables, forms
│   ├── data/                 # repositories/services for CRUD logic
│   ├── models/               # value objects (Client, Visit, Service, View)
│   └── cli/                  # WP-CLI commands
├── assets/
│   ├── css/                  # admin + front styles
│   └── js/                   # board app bundle
├── languages/                # translation files
└── uninstall.php             # cleanup hook
```

## Data Flow

1. **Admin CRUD**
   - Admin UI uses standard WP admin pages + `WP_List_Table`.
   - Forms post to custom endpoints secured by nonces + capabilities.
   - Data layer (`includes/data/*`) interacts with custom tables using `$wpdb`.
   - After mutations, hooks (e.g., `bbgf_visit_updated`) fire for integrations.

2. **REST API**
   - Namespaced controllers under `bb-groomflow/v1`.
   - JSON schema defined with `rest_validate_value_from_schema` for data integrity.
   - Incremental updates supported via `modified_at` timestamps.
   - Public views require a token; data is masked at controller level.

3. **Front-end Board**
   - Scripts localized with:
     - active view config (stages, labels, capacity thresholds),
     - global settings (poll interval, timer thresholds),
     - user capabilities (move stage, edit visit).
   - Board app maintains in-memory state keyed by visit ID; polling merges updates.
   - Drag/drop triggers REST `move` endpoint; optimistic updates preview change.
   - Modal editor fetches visit detail on open (lazy load), submitting via REST `PATCH`.

4. **Notifications & Reporting**
   - Stage move service dispatches notification jobs (email now, hooks for later).
   - Reports use read-only repository methods with aggregation queries.
   - CSV/PDF exports hit dedicated REST routes with nonce auth.

## State & Caching

- Visits table keeps `updated_at` for polling diffs.
- Transients optional for expensive reports (invalidate on visit mutations).
- Object-cache drop-ins (e.g., GoDaddy) are supported; avoid storing serialized closures.

## Security

- Capabilities enforced server-side for every action.
- Nonces required for all admin forms & REST POST/PATCH calls.
- Public views read-only, mask guardian data, optional alias for client name.
- Uploaded photos stored in WP media library with visit metadata.

## Extensibility

- Actions/filters:
  - `bbgf_visit_created`, `bbgf_visit_updated`, `bbgf_visit_stage_changed` (fired after the REST move endpoint saves the new stage).
  - `bbgf_board_response_payload` filter for altering REST responses.
  - `bbgf_notification_stage_queued` to intercept stage-triggered notifications before delivery.
  - `bbgf_notification_channels` for adding Slack/SMS.
- WP-CLI commands under `bbgf` namespace for batch imports, snapshots, maintenance.

## Build & Deployment

- JS built via `npm run build` before packaging.
- PHPCS via `vendor/bin/phpcs --standard=phpcs.xml.dist plugin/bb-groomflow`.
- Packaged ZIP: `bash scripts/build_plugin_zip.sh` → `build/bb-groomflow-<version>.zip`.
- Docker QA: `bash scripts/load_prod_snapshot.sh` to mirror production, then install ZIP using WP-CLI inside the container.

Keep this document updated as modules or integration points change.
