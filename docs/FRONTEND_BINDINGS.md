# Front-end Bindings

Selectors used by the GroomFlow board and modal. Keep these stable so the JS app, Elementor widget, and theming stay in sync.

## Board Container
- `#bbgf-board-root` — top-level wrapper for the shortcode/widget output.
- `.bbgf-board` — board grid wrapper (holds columns).
- `.bbgf-board--loading` — optional state class while data fetches.

## Columns
- `.bbgf-column` — individual stage column (`data-stage`, `data-capacity-soft`, `data-capacity-hard`).
- `.bbgf-column-header` — sticky header area (stage label, capacity indicator).
- `.bbgf-column-body` — scrollable card region.
- `.bbgf-capacity-badge` — soft/hard capacity indicator.

## Cards
- `.bbgf-card` — visit card (`data-visit-id`, `data-stage`, `data-updated-at`).
- `.bbgf-card-header` — top row (photo + primary info).
- `.bbgf-card-photo` — client photo thumbnail.
- `.bbgf-card-name` — client name element.
- `.bbgf-card-timer` — stage timer badge (color-coded via CSS vars).
- `.bbgf-card-services` — service badge container.
- `.bbgf-card-flags` — behavior flag chips.
- `.bbgf-card-notes` — truncated notes/alerts (optional).
- `.bbgf-move-prev`, `.bbgf-move-next` — quick stage move buttons.

## Toolbar & Controls
- `#bbgf-board-toolbar` — toolbar container.
- `.bbgf-toolbar-view` — view selector dropdown.
- `.bbgf-toolbar-search` — search input wrapper.
- `.bbgf-toolbar-filters` — filter buttons (flags, services).
- `.bbgf-refresh-button` — manual refresh control.
- `.bbgf-last-updated` — timestamp indicator.

## Modal
- `#bbgf-modal` — modal root element.
- `.bbgf-modal__header` — modal header (client info + actions).
- `.bbgf-modal__tabs` — tab button container.
- `.bbgf-modal-tab` — tab button (`data-tab`).
- `.bbgf-modal-panel` — tab panel (`data-panel`).
- `.bbgf-history-list` — timeline of stage moves/comments.
- `.bbgf-photo-grid` — photo gallery.
- `.bbgf-modal-actions` — footer actions (save, send notification, move stage).

## States
- `.bbgf-card--overdue`, `.bbgf-card--flagged`, `.bbgf-card--capacity-warning`.
- `.bbgf-column--over-capacity`, `.bbgf-board--lobby` (public styles), `.bbgf-board--readonly`.

When introducing new UI elements or states, append them here so engineers and designers have a single source of truth.
