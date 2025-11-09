# Bubbles & Bows GroomFlow

This repository ships both the documentation pack and the WordPress plugin that powers the GroomFlow Kanban experience. Follow the stateless slice ritual in `AGENTS.md`, then work through the rest of the reading stack before touching code.

## Repository Layout
- **Documentation index:** `docs/README.md`
- **Vision & product spec:** `SPEC.md`
- **Architecture:** `docs/ARCHITECTURE.md`
- **Sprint plans & breadcrumbs:** `docs/sprints/`, `docs/breadcrumbs/`
- **Plugin source:** `plugin/bb-groomflow/`
- **Bootstrap services:** `plugin/bb-groomflow/includes/bootstrap/` (`Assets_Service`, `Admin_Menu_Service`, `Rest_Service`, `Cli_Service`)
- **Build tooling:** `scripts/build_plugin_zip.sh`
- **Docker runtime:** `docker/docker-compose.yml`, `docs/workflow.md`
- **QA toolbelt:** `QA_TOOLBELT.md`

## Slice & QA Workflow
1. Read the full stack (`AGENTS.md` → `README.md` → `PROJECT_PLAN.md` → … `docs/breadcrumbs/*`).
2. Branch from `dev` (`git checkout -b refactor/<slice>`), log the slice in `docs/REFACTOR_LOG.md`, and outline target files/QA.
3. Build assets (`npm run build` when front-end files change) and package the plugin:
   ```bash
   bash scripts/build_plugin_zip.sh
   cd docker
   docker compose up -d
   docker compose cp ../build/bb-groomflow-0.1.0-dev.zip wordpress:/var/www/html/bb-groomflow.zip
   docker compose run --rm -T wpcli wp plugin install /var/www/html/bb-groomflow.zip --force --activate
   docker compose run --rm -T wpcli wp bbgf visits seed-demo --count=8 --force   # optional but recommended
   ```
4. Run QA exactly as listed in `TECH_READINESS.md` and `QA_TOOLBELT.md`:
   - `qa-phpcs plugin/bb-groomflow` (artifact saved under `/opt/qa/artifacts/phpcs-*.txt`).
   - Manual admin happy-path (Clients, Guardians, Services, Packages, Flags, Views, Settings → create + edit).
   - Any slice-specific tooling noted in `PROJECT_PLAN.md`.
5. Log every QA artifact path in `docs/REFACTOR_LOG.md` and `qa/QA_LOG.md`, update docs (`PROJECT_PLAN.md`, `docs/HISTORY.md`, `docs/NOTES.md`, `CHANGELOG.md` when behavior changes), and drop a breadcrumb summarizing the run.
6. Keep public APIs (`bbgf()->bootstrap_elementor()`, CLI commands, uninstall hooks) stable unless every call site is updated.

## Packaging & Deployment Guardrails
- Manual builds only — never bind the plugin directory into Docker. Always install the packaged ZIP inside the container as shown above.
- New bootstrap services keep `Plugin` slim; external consumers still call the existing wrappers (`bbgf()->bootstrap_elementor()`, `bbgf()->get_placeholder_board_markup()`).
- `wp bbgf visits seed-demo` (CLI) now uses prepared statements and timer-aware payloads so demo boards immediately show realistic elapsed time.
- The uninstall routine drops all GroomFlow tables/options unless `BBGF_PRESERVE_DATA_ON_UNINSTALL` or the `bbgf_allow_uninstall_cleanup` filter is engaged; document the intent before testing.
- Store every CLI/QA transcript in `/opt/qa/artifacts/<descriptive-name>` and reference those paths in your logs so future agents can audit the evidence.

For deeper context on goals, UX, data model, and QA expectations, read `SPEC.md`, `TECH_READINESS.md`, and `QA_TOOLBELT.md` in full before shipping a slice.
