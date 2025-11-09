# Sprint 0 — Enhanced Bootstrap

## Goal
Lay the groundwork for GroomFlow: admin entry points, capability scaffolding, asset pipeline, and a polished placeholder board that communicates the UX direction.

## Deliverables
- Admin menu: “GroomFlow Dashboard” with submenus for Clients, Guardians, Services, Packages, Views, Settings, Reports (stub pages allowed).
- Register plugin capabilities and map them to roles (`bb_manager`, `bb_reception`, `bb_bather`, `bb_groomer`, `bb_lobby`).
- Asset pipeline (enqueue CSS/JS, set up build script using @wordpress/scripts or Vite).
- REST namespace `bb-groomflow/v1` with a health-check endpoint.
- Elementor widget skeleton: registers in Elementor, exposes view selector placeholder, renders mock board.
- Placeholder Kanban board (shortcode + widget preview) showing sample columns/cards with animations.
- Update docs/breadcrumb with sprint outcome.

## Acceptance Criteria
- Plugin activates without notices in Docker WordPress.
- Admin menu appears with capability restrictions (only admins by default).
- `[bbgf_board]` shortcode renders the placeholder board.
- Elementor widget is selectable in the editor and outputs the same placeholder board on the front end.
- Build tooling documented in README/workflow.

## Status
- ✅ Placeholder admin shell, Kanban preview, and Elementor widget skeleton shipped (2025-10-21).
- ✅ REST namespace online with `/health` endpoint returning plugin version and timestamp.
- ✅ Asset pipeline documented (`npm run build` via Vite) and QA/packaging validated inside Docker WP.
