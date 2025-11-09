# DB Schema — GroomFlow

All tables use the WordPress table prefix. Manage schema via `dbDelta` (see `plugin/bb-groomflow/includes/database/class-schema.php`) and bump `BBGF_DB_VERSION` on change. Provide upgrade routines that migrate existing installs (never destructive).

| Table | Purpose | Key Columns |
| --- | --- | --- |
| `wp_bb_clients` | Client profile master record | `id`, `name`, `slug`, `guardian_id`, `breed`, `weight`, `sex`, `dob`, `temperament`, `preferred_groomer`, `notes`, `meta` (JSON), `created_at`, `updated_at` |
| `wp_bb_guardians` | Primary/secondary contact data | `id`, `first_name`, `last_name`, `email`, `phone_mobile`, `phone_alt`, `preferred_contact`, `address`, `notes`, `created_at`, `updated_at` |
| `wp_bb_services` | Individual services | `id`, `name`, `slug`, `icon`, `color`, `duration_minutes`, `price`, `description`, `flags` (JSON), `created_at`, `updated_at` |
| `wp_bb_service_packages` | Bundled services | `id`, `name`, `slug`, `icon`, `color`, `price`, `description`, `created_at`, `updated_at` |
| `wp_bb_service_package_items` | Mapping package → services | `package_id`, `service_id`, `sort_order` |
| `wp_bb_flags` | Behavior/alert definitions | `id`, `name`, `slug`, `emoji`, `color`, `severity`, `description`, `created_at`, `updated_at` |
| `wp_bb_visits` | Active/past tickets | `id`, `client_id`, `guardian_id`, `view_id`, `current_stage`, `status`, `check_in_at`, `check_out_at`, `assigned_staff`, `instructions`, `private_notes`, `public_notes`, `timer_started_at`, `timer_elapsed_seconds`, `created_at`, `updated_at` |
| `wp_bb_visit_services` | Visit ↔ services mapping | `visit_id`, `service_id`, `package_id`, `added_by`, `added_at` |
| `wp_bb_visit_flags` | Visit-specific applied flags | `visit_id`, `flag_id`, `notes`, `added_by`, `added_at` |
| `wp_bb_stage_history` | Stage movement audit trail | `id`, `visit_id`, `from_stage`, `to_stage`, `comment`, `changed_by`, `changed_at`, `elapsed_seconds` |
| `wp_bb_views` | Board configuration | `id`, `name`, `slug`, `type` (internal/lobby/kiosk), `allow_switcher`, `refresh_interval`, `show_guardian`, `public_token_hash`, `last_token_rotated_at`, `settings` (JSON), `created_at`, `updated_at` |
| `wp_bb_view_stages` | Stage ordering & capacity per view | `view_id`, `stage_key`, `label`, `sort_order`, `capacity_soft_limit`, `capacity_hard_limit`, `timer_threshold_green`, `timer_threshold_yellow`, `timer_threshold_red` |
| `wp_bb_notifications` | Template storage | `id`, `name`, `channel` (email), `subject`, `body_html`, `body_text`, `created_at`, `updated_at` |
| `wp_bb_notification_triggers` | Stage → template mapping | `trigger_stage`, `notification_id`, `recipient_type` (guardian/custom/both), `recipient_email` (comma-separated list), `enabled`, `conditions` (JSON), `created_at`, `updated_at` |
| `wp_bb_notification_logs` | Delivery history | `notification_id`, `trigger_id`, `visit_id`, `stage`, `channel`, `recipients`, `subject`, `status`, `error_message`, `sent_at`, `created_at` |


### Conventions
- Use `INT UNSIGNED AUTO_INCREMENT` for primary keys unless noted.
- Store timestamps in UTC (DATETIME). Use `current_time( 'mysql', true )`.
- `meta` JSON columns keep future-proof data without schema churn. Validate on read/write.
- Add foreign key indices even if not declared (GoDaddy hosts may not support FK constraints).
- Keep `updated_at` current for polling diff queries.
