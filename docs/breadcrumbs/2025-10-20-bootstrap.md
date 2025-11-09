# Breadcrumb
- **Task asked:** Stand up the GroomFlow repository baseline, document onboarding, and confirm GitHub SSH access.
- **Plan:** 1) Review docs and outline scaffold. 2) Create plugin skeleton, build tooling, and Docker stack. 3) Refresh onboarding docs. 4) Configure SSH remote. 5) Record QA artifacts and breadcrumb.
- **Files changed:** .gitignore; phpcs.xml.dist; plugin/bb-groomflow/*; scripts/build_plugin_zip.sh; docker/*; AGENTS.md; README.md; CHANGELOG.md; docs/workflow.md; QA_TOOLBELT.md; docs/breadcrumbs/2025-10-20-bootstrap.md.
- **Commands executed:** bash scripts/build_plugin_zip.sh; git init && git branch -m main; eval "$(ssh-agent -s)" && ssh-add ~/.ssh/id_ed25519_codex; ssh -o StrictHostKeyChecking=accept-new -T git@github.com.
- **Tests & results:** Manual build script produced build/bb-groomflow-0.1.0-dev.zip; SSH handshake with GitHub succeeded (expected "no shell access" response).
- **Tips & Tricks:** Set `WPCS_DIR` before running `qa-phpcs` so the bundled ruleset resolves WordPress Coding Standards.
- **Remaining work:** Sprint 0 implementation (settings shell, Elementor stub), automated QA runs, Elementor plugin install flow once artifact provided.
