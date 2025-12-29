# API Reference — `bb-groomflow/v1`

## Scope
- **Lives here:** REST routes, payload shapes, auth/capability expectations, public vs internal notes.
- **Not here:** Frontend patterns, data schema design, QA or deployment steps (see `AGENTS.md` map).

All endpoints require HTTPS, WordPress nonce authentication (for logged-in users), and capability checks. Public lobby endpoints accept a token (read-only, masked data).

## Board & Visits

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/board` | Fetch visits for a view. Query params: `view` (slug), `stages[]`, `modified_after` (ISO8601), `public_token`. Response includes the view descriptor, ordered stage metadata (`key`, `label`, `capacity`, `timer_thresholds`, `visit_count`), the visit arrays per stage, a `visibility` mask summary, and a `last_updated` timestamp. |
| `POST` | `/visits` | Create a new visit (intake). Payload handles new/existing client/guardian, selected services/packages, initial stage, instructions, flags. |
| `GET` | `/visits/{id}` | Fetch a single visit including related services, flags, photos, and the full stage history (descending by most recent move). Public/lobby reads must supply `view` + `public_token` and receive a masked, history-free payload. |
| `PATCH` | `/visits/{id}` | Update visit details (notes, assigned staff, services, flags, guardian contact). |
| `POST` | `/visits/{id}/move` | Move visit to new stage. Body: `to_stage`, optional `comment`, `notify` flag. Response returns `{ visit, history_entry }` so the UI can update both the card and timeline immediately. |
| `POST` | `/visits/{id}/photo` | Upload photo; accepts multipart form with `file` or existing media ID. Returns attachment metadata and updated visit photo list. |
| `POST` | `/visits/{id}/checkout` | Mark visit as completed/checked out with optional comment; removes it from active board payloads. |

## Directory Data

| Method | Endpoint | Notes |
| --- | --- | --- |
| `GET/POST/PATCH` | `/clients`, `/clients/{id}` | Manage client profiles. `GET` supports search filters (name, breed) and pagination. |
| `GET/POST/PATCH` | `/guardians`, `/guardians/{id}` | Manage guardian contacts. |
| `GET/POST/PATCH/DELETE` | `/services`, `/services/{id}` | Manage service catalog (icon, color, duration, price). |
| `GET/POST/PATCH/DELETE` | `/packages`, `/packages/{id}` | Manage service packages and their items. |
| `GET/POST/PATCH/DELETE` | `/flags`, `/flags/{id}` | Manage behavior/alert flags. |
| `GET/POST/PATCH/DELETE` | `/views`, `/views/{id}` | Manage board views, stage ordering, capacity, tokens. |
| `GET/POST/PATCH` | `/visits`, `/visits/{id}` | Intake a new visit, fetch detailed visit data (with history), and update existing visits (notes, staff, services, flags). |
| `POST` | `/visits/{id}/move` | Move a visit to a new stage while recording history and timers. |
| `GET` | `/board` | Fetch visits for a specific view (supports polling windows and public tokens). |

`GET /clients` accepts:

- `search` — fuzzy match against client name and breed fields.
- `page` / `per_page` — pagination controls (1–100 per page, default 20).

`GET /guardians` accepts:

- `search` — fuzzy match against first name, last name, email, or phone values.
- `page` / `per_page` — pagination controls (1–100 per page, default 20).

`GET /services` accepts:

- `search` — fuzzy match against service name, description text, or tags.
- `page` / `per_page` — pagination controls (1–100 per page, default 20).

`GET /packages` accepts:

- `search` — fuzzy match against package name or included service names.
- `page` / `per_page` — pagination controls (1–100 per page, default 20).

`GET /flags` accepts:

- `search` — fuzzy match against flag name or description.
- `page` / `per_page` — pagination controls (1–100 per page, default 20).

`GET /views` accepts:

- `search` — fuzzy match against view name.
- `page` / `per_page` — pagination controls (1–100 per page, default 20).

`POST /visits` accepts:

- `client_id` **or** `client` payload (name + optional guardian) to handle intake.
- `guardian_id` or `guardian` payload for lightweight guardian capture.
- `view_id` (optional), `current_stage`, `status`, `instructions`, `notes`, `services[]`, `flags[]`.

`PATCH /visits/{id}` accepts the same fields as create, all optional.

`POST /visits/{id}/move` body:

- `to_stage` (required stage key)
- `comment` (optional)

`POST /visits/{id}/move` response:

- `visit` — refreshed visit payload (matches `GET /visits/{id}` without re-fetching).
- `history_entry` — the newly created stage history record so the UI can append to the timeline immediately.

Stage history entries contain:

- `id`
- `from_stage` / `to_stage` — each with `key` and human-friendly `label`
- `comment`
- `changed_by` / `changed_by_name`
- `changed_at` (RFC3339)
- `elapsed_seconds`

> **Public tokens:** All write endpoints (`POST /visits`, `PATCH /visits/{id}`, `POST /visits/{id}/move`, `POST /visits/{id}/photo`) reject requests that include `public_token`. Lobby/kiosk boards are read-only.

`GET /board` accepts:

- `view` — view slug (defaults to first view if omitted).
- `stages[]` — limit to specific stage keys.
- `modified_after` — ISO8601 timestamp for incremental polling.
- `public_token` — hashed token for lobby/kiosk views (bypasses capability checks).

`GET /board` response:

- `view` — basic view metadata (`id`, `slug`, `name`, `type`, `allow_switcher`, `refresh_interval`, `show_guardian`).
- `stages` — ordered array; each item contains:
  - `key`, `label`, `sort_order`
  - `capacity.soft`, `capacity.hard`, `capacity.is_soft_exceeded`, `capacity.is_hard_exceeded`, `capacity.available_soft`, `capacity.available_hard`
  - `timer_thresholds.green|yellow|red`
  - `visit_count`
  - `visits` — visit payloads scoped to the stage
- `visibility` — booleans indicating whether guardian details or sensitive fields were masked (`mask_guardian`, `mask_sensitive`).
- `readonly` — boolean indicating whether the board should be treated as read-only (public token or lobby/kiosk view).
- `is_public` — boolean flag signaling the response was served via a public token.
- `last_updated` — MySQL datetime (UTC) derived from the freshest visit change.

Visit payloads include:

- `photos[]` — zero or more media objects attached to the visit. Each photo contains:
  - `id` — attachment post ID.
  - `url` — full-size image URL.
  - `mime_type` — attachment mime type.
  - `thumbnail` — optional array with `url`, `width`, `height` when a thumbnail rendition exists.
- `history[]` — present when the request includes `include_history` (e.g., `GET /visits/{id}` and stage-move responses). Entries expose `id`, `from_stage`/`to_stage` objects (with `key` + `label`), `comment`, `changed_by`, `changed_by_name`, `changed_at`, and `elapsed_seconds`.
- `timer_elapsed_seconds` — live timer derived from the stored elapsed value plus the active stage runtime.

`POST /visits/{id}/photo` returns a JSON object with the newly linked `photo` plus the refreshed `visit` payload so the UI can update in place. Provide either a multipart `file` upload or an existing `attachment_id` already in the media library.

## Reporting & Settings

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/stats/daily` | Daily summary stats (visit counts, average durations). |
| `GET` | `/stats/stage-averages` | Average time per stage across date range (query params: `start`, `end`, `view`). |
| `GET` | `/stats/service-mix` | Frequency of services/packages over date range. |
| `POST` | `/exports/csv` | Generate CSV export; body describes report type and filters. |
| `POST` | `/exports/pdf` | Generate PDF summary (returns signed URL or base64). |

`GET /stats/daily` accepts:

- `date` — target day in `Y-m-d` (defaults to today, UTC).

Response:

- `date` — day evaluated.
- `summary.total` / `summary.in_progress` / `summary.completed`.
- `averages.duration_seconds` and `averages.duration_minutes` (completed visits with both check-in/out).

`GET /stats/stage-averages` accepts:

- `start` — start date (defaults to 7 days prior, UTC).
- `end` — end date (defaults to today, UTC).
- `view` — optional view slug limiter.

Response:

- `range.start`, `range.end`.
- `view` when filter applied (otherwise `null`).
- `stages[]` objects: `stage_key`, `label`, `average_seconds`, `average_minutes`, `sample_size`.

`GET /stats/service-mix` accepts:

- `start` — start date (defaults to 30 days prior, UTC).
- `end` — end date (defaults to today, UTC).
- `view` — optional view slug limiter.

Response:

- `range.start`, `range.end`.
- `view` when filter applied (otherwise `null`).
- `services[]` objects: `id`, `name`, `icon`, `color`, `count`, `percentage`.
- `packages[]` objects (when data exists): `id`, `name`, `icon`, `color`, `count`, `percentage`.

## Notifications

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET/POST/PATCH/DELETE` | `/notifications`, `/notifications/{id}` | Manage notification templates. |
| `GET/POST/PATCH/DELETE` | `/notification-triggers`, `/notification-triggers/{id}` | Map stages to templates/channels. |

`POST /notification-triggers` accepts:

- `trigger_stage` — required stage key (matches `wp_bb_view_stages.stage_key`).
- `notification_id` — template ID to send.
- `recipient_type` — one of `guardian_primary`, `guardian_primary_and_custom`, or `custom_email`.
- `recipient_email` — optional comma/newline separated list used when the type requires custom recipients.
- `enabled` — boolean flag to toggle delivery without deleting the row.
- Template merge tags: `{{client_name}}`, `{{guardian_first_name}}`, `{{guardian_last_name}}`, `{{guardian_full_name}}`, `{{guardian_email}}`, `{{visit_stage}}`, `{{visit_comment}}`, `{{visit_id}}`, `{{site_name}}`. These are available in subjects, HTML, and plain-text bodies; admins can copy them from the notifications UI helper sidebar.

## Authentication & Security

- Logged-in users: WordPress nonce via `X-WP-Nonce` header + capability checks.
- Public boards: `public_token` query parameter (signed hash stored on `bbgf_views`). Only `GET /board` allowed; response masks guardian info, removes private notes/instructions/assigned staff, and hides high-severity/internal flags.
- All write operations validate input with JSON schema (`rest_validate_value_from_schema`) and sanitize outputs.
- Responses include `updated_at` to support incremental polling (`modified_after`).

Document new endpoints here as they are introduced. Update examples when payloads change.
