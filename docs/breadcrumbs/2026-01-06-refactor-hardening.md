# 2026-01-06 — Refactor Hardening

- Repaired Visit_Service dynamic IN clause SQL to avoid inline PHPCS comments in queries and kept targeted ignores.
- Hardened client update handling so PATCH flag updates preserve existing fields.
- Improved capture_artifacts reliability (visible card wait + cleanup on exit).
- Rebuilt assets, packaged ZIP, reinstalled plugin, and reseeded demo data.
- Verified flags update, main photo persistence, and client history pagination via REST proofs.
- Captured board/modal screenshots after update and after-photo state.

Artifacts: `/opt/qa/artifacts/refactor-1767762104` (phpcs, build/install logs, REST proofs, board/modal captures).
