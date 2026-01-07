# 2026-01-06 — Stabilization + Ship

- Cleaned `Visit_Service` SQL handling to remove file-level PHPCS ignores; added targeted per-query safeguards and validation.
- Ran `npm run build`, rebuilt ZIP, reinstalled via `wpcli`, and reseeded demo data (`--count=8 --force`).
- Verified board/modal UX via authenticated Playwright captures (`board-before/after-*`, `modal-before/after-*` in `/opt/qa/artifacts/run-1767754528`).
- Exercised REST endpoints with cookie+nonce auth: client GET/UPDATE (main photo + flags) and history pagination for client 27 (`curl-client*.json` in run dir).
- QA log updated with artifacts; phpcs clean (`/opt/qa/artifacts/run-1767754528/phpcs-final.txt`).
