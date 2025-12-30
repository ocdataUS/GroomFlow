# UX Guide — GroomFlow

## Scope
- **Lives here:** Interaction and visual rules for boards, modals, and controls.
- **Not here:** Product requirements, REST/data contracts, deployment/QA steps (see `AGENTS.md` map).

## Tone & Feel
- Calm, Apple-inspired interface: neutral backgrounds, subtle shadows, rounded corners, tasteful accent colors.
- Motion should feel purposeful and gentle (ease-out 120–180 ms). Respect `prefers-reduced-motion`.
- Typography hierarchy: clear card titles (client name), secondary info (services, timer) with consistent scale.

## Interaction Principles
- Drag & Drop:
  - 48px minimum touch targets.
  - Provide keyboard equivalents (space to pick up, arrow to move, enter to drop).
  - Announce moves via `aria-live` (“Bella moved to Grooming”).
- Quick Actions:
  - “Next stage” and “Back” buttons on cards with tooltips and icons.
  - Confirmations appear as lightweight toasts (auto-dismiss).
- Modal:
  - Centered, full-height on desktop with sticky action footer.
  - Tabbed interface with consistent icons: Summary, Services, Notes, History, Photos.
  - Provide context (“Current stage: Drying”, “Timer: 00:12:45”) at top.

## Visual System
- Columns:
  - Sticky headers with stage label + capacity badge.
  - Background subtle gradient per stage, optionally set via view/settings.
- Cards:
  - Photo thumbnail left; rest of content stacked (name, timer, services badges).
  - Behavior flags as colored pill chips with emoji + tooltip.
  - Overdue states change border color and timer badge according to threshold.
- Lobby Mode:
  - Larger typography, auto-rotate optional, full-screen toggle button.
  - Display “Last updated” timestamp and gentle pulse on refresh.

## Accessibility Checklist
- Contrast check each board theme/config (AA minimum).
- Focus indicators clearly visible on cards, buttons, modal controls.
- Provide screen-reader instructions hidden in DOM for board usage.
- Ensure announcements for stage moves, timer warnings, capacity alerts using `wp.a11y.speak`.
- Timer color changes should also have icon or text indicator (e.g., “Overdue” label).

## Sound & Feedback
- Optional audio cues (toggle in settings) for “Ready” stage or overdue alerts; default to off.
- Toasters appear bottom-right, auto-dismiss after 4 seconds, remain focusable for keyboard users.

## Responsive Board
- Horizontal (default): center columns, allow proportional shrink; avoid horizontal scroll unless columns exceed viewport.
- Breakpoint: switch to vertical when the board cannot fit three ~260px columns plus gaps (~<830px effective width).
- Vertical: columns stack as collapsible sections; headers stay visible with name + count; first non-empty column expands by default; users can collapse to scan quickly.
- Behaviors are layout-driven (no device targeting) and share the same markup; drag/drop and card ordering remain unchanged.

Keep this guide updated as new interactions or themes are introduced. Designers and engineers should reference this before implementing UI changes.
