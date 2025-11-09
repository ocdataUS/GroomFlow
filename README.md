# Bubbles & Bows GroomFlow

This repository now ships both the documentation pack and the baseline WordPress plugin scaffold for the GroomFlow Kanban experience. Start at `AGENTS.md`, read every document, and follow the sprint roadmap in `docs/sprints/`.

- **Documentation Index:** `docs/README.md`
- **Vision:** Calm, Apple-like salon workflow centered on the animal client (`SPEC.md`).
- **Architecture:** `docs/ARCHITECTURE.md`
- **Plugin Source:** `plugin/bb-groomflow/`
- **Build:** `bash scripts/build_plugin_zip.sh` → `build/bb-groomflow-<version>.zip`
- **Assets:** `npm install` then `npm run build` (Vite) to refresh `plugin/bb-groomflow/assets/build/`
- **Environment:** `docker/docker-compose.yml` + `docs/workflow.md`
- **QA/QC:** Use the shared toolbelt (`QA_TOOLBELT.md`) before delivering.

Manual builds only — always ship a packaged ZIP into Docker WordPress for verification.
