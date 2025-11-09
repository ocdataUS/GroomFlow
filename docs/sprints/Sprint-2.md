# Sprint 2 — REST & Business Logic

## Goal
Wire the back end workflow: intake endpoints, stage transitions, history, and public boards with token access. Provide the REST backbone for the upcoming front-end work.

## Deliverables
- REST controllers under `bb-groomflow/v1` for:
  - `GET /board` (view aware, supports `modified_after`, optional `public_token`).
  - `POST /visits`, `PATCH /visits/{id}`, `POST /visits/{id}/move`, `POST /visits/{id}/photo`.
  - `GET/POST/PATCH /clients`, `/guardians`, `/services`, `/packages`, `/flags`, `/views`.
  - `GET /stats/daily`, `GET /stats/stage-averages`.
- Business logic services for intake creation, stage transitions, timer calculations, capacity tracking.
- Stage history writing (user ID, from/to stage, comment, elapsed time).
- Public board token support (masked guardian info, optional client alias, read-only).
- WP-CLI scaffolding (`wp bbgf visits list`, `wp bbgf sync prepare` stubs).
- Comprehensive capability/nonce enforcement + error responses.
- Update docs with endpoint reference + sample payloads.

## Acceptance Criteria
- REST endpoints return real data from custom tables (no mock data).
- Stage move endpoint records history and updates timers.
- Public token yields masked data and denies write operations.
- Automated unit/integration tests for key controllers (where feasible) or manual API smoke test logged in QA artifacts.
- Documentation enumerates endpoints, payloads, and auth requirements.
