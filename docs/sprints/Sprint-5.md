# Sprint 5 — Notifications, Reporting & QA

## Goal
Close the loop with communications, insights, and quality assurance. Deliver a complete v1 ready for stakeholder demos and future integrations.

## Deliverables
- Notifications UI:
  - Manage email templates per stage with merge tags (client, guardian, services, stage, notes).
  - Trigger mapping (Ready, Checkout, Follow-Up) with enable/disable toggles.
  - Hook for SMS integration (document usage).
- Email sending via `wp_mail` with templated HTML; log sends to history table.
- Reporting dashboard:
  - KPI cards (visits in progress, average time per stage last 24h, overdue count, popular services).
  - Charts (bar/line) using enqueue-friendly chart library.
  - Filters (date range, view).
- Export endpoints: CSV + PDF for daily summary and visit detail.
- QA & polish:
  - Accessibility audit (pa11y, manual keyboard sweep).
  - Performance check (board load under threshold, clean console).
- Automated smoke tests (Cypress-lite or equivalent) for board load + card move.
  - Final PHPCS and packaging checks.
- Documentation updates (user-facing setup guide, admin manual, API reference refinement).

## Acceptance Criteria
- Notification templates send correctly when stage changes (Ready).
- KPI dashboard surfaces accurate counts/times; exports download and open successfully.
- QA artifacts recorded (pa11y report, performance notes, e2e test run).
- CHANGELOG updated and final ZIP installed in Docker for demo.
