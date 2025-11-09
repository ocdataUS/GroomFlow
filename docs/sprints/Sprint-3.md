# Sprint 3 — Kanban UX & Modal Editing

## Goal
Deliver the interactive board experience with drag-and-drop, timers, capacity alerts, and the modal editor. Prep both admin and lobby presentations.

## Deliverables
- Front-end app (vanilla JS modules) consuming REST endpoints:
  - Drag-and-drop cards + keyboard fallback.
  - Quick move buttons for next/previous stage.
  - Polling engine with diff-based updates (use `modified_after`).
  - Localized settings (thresholds, polling interval, view metadata).
- Timer badges color-coded (green/yellow/red) using settings thresholds; capacity indicator per column.
- Toolbar: view switcher (if allowed), search/filter (client name, service, flag), manual refresh button, last updated timestamp.
- Card design: client photo (if available), services icons row, drop-off time, stage timer, alert flags. Smooth animations on move.
- Modal editor with tabs (Summary, Services, Notes, History, Photos). Inline update interactions hitting REST endpoints.
- Lobby mode support (full-screen toggle, optional guardian masking, auto-refresh indicator).
- Accessibility pass: focus management, aria-live updates, instructions for keyboard DnD and modal navigation.
- QA artifacts: video or screenshot of drag/drop, notes on polling performance.

## Acceptance Criteria
- Board loads real data, updates via polling without page refresh.
- Drag/drop and quick move buttons update the server and reflect change on refresh.
- Modal edits persist and reflect on the card once saved.
- Lobby mode respects masking options and auto-refreshes at configured interval.
- Accessibility checks logged (screen reader instructions, focus behavior).

## Work Breakdown — 2025-10-30
1. **Board bootstrap (PHP):** extend shortcode/Elementor helpers to inject an initial board payload, REST route URLs, capability flags, and nonces into `bbgfBoardSettings`; ensure lobby/read-only hints flow through.
2. **JS foundation:** replace the placeholder script with modular store/render/api layers; hydrate from the localized payload and wire settings (poll interval, thresholds, colors).
3. **Rendering layer:** build column/card templating that consumes live visit data, capacity metadata, and visibility masks; preserve existing class hooks for styles.
4. **Polling & diffing:** implement incremental fetch using `modified_after`, merge updates in place, surface loading/error toasts, and respect refresh intervals/manual refresh.
5. **Interactions:** deliver drag/drop with keyboard parity plus quick-move actions using the stage-move endpoint with optimistic updates and rollback on failure.
6. **Modal experience:** create an accessible modal shell that lazy-loads visit detail, supports inline edits (summary/services/notes/history/photos), and syncs state after save.
7. **Lobby/read-only modes:** enforce masking, disable interactions, add full-screen toggle hook, and tailor announcements/timeouts for public displays.
8. **QA + docs:** run QA toolbelt checks, document results in breadcrumb, and update relevant docs (board usage, accessibility notes) as features land.
