# Sprint 4 — Elementor & Shortcode Polish

## Goal
Expose GroomFlow boards as a premium Elementor widget and ensure shortcode parity. Empower admins to customize layout, metadata, and styling without code.

## Deliverables
- Elementor widget content controls:
  - View selector (single or allow switcher).
  - Toggle for view switcher visibility, guardian masking, lobby mode.
  - Refresh interval override, column count, stage filter.
- Style controls:
  - Typography & spacing for column headers, cards, metadata rows.
  - Colors for columns, timers, capacity warnings, alert flags.
  - Conditional styling (overdue highlight, flagged client accent, capacity exceeded background).
- Advanced controls:
  - Repeater to choose metadata blocks and order (e.g., name, services, timer, guardian chip).
  - Optional HTML slots/short text fields for custom callouts.
  - Toggle to include/exclude behavior flags, notes snippet.
- Shortcode updates to mirror widget options (`[bbgf_board view="..." show_switcher="true" refresh="15" lobby="false"]`).
- Ensure Elementor preview mode pulls sample data (mock or real via REST) without breaking canvas performance.
- Documentation: widget usage guide, shortcode parameter reference.

## Acceptance Criteria
- Elementor widget renders correctly in editor and front end with selected styles.
- Admin can rearrange metadata and see the effect immediately.
- Shortcode output matches widget when equivalent parameters set.
- Public views respect masking settings defined in widget/shortcode.
- QA: export Elementor JSON snippet to confirm portability; record styling screenshot.
