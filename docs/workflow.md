# Workflow — GroomFlow Plugin Delivery

This guide walks a stateless agent from shell login to a deployed ZIP running inside Docker WordPress, ready to test and push to GitHub.

## 1. First Login Checklist

1. Read `AGENTS.md`, `SPEC.md`, and all docs in `docs/` — required before coding.
2. Run `git status` to confirm a clean working tree before starting any sprint work.
3. If missing, copy `docker/.env.example` to `docker/.env` and adjust ports or credentials as needed.

## 2. GitHub Access (SSH Only)

Use the pre-generated SSH keypair to authenticate pushes without prompting for approval.

```bash
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_ed25519_codex
ssh -T git@github.com          # accept GitHub host fingerprint when prompted
git remote set-url origin git@github.com:ocdataUS/GroomFlow.git
```

Need the public key? `cat ~/.ssh/id_ed25519_codex.pub` — it is already registered as **codex-servicetrade**. Repeat the `ssh-agent` + `ssh-add` step for every new shell before pushing.

## 3. Build → Install → Verify

All coding happens against the plugin sources under `plugin/bb-groomflow`. Builds are manual only.

Front-end assets compile with Vite. Run `npm install` once per environment, then `npm run build` to refresh `plugin/bb-groomflow/assets/build/` before packaging a ZIP.

```bash
bash scripts/build_plugin_zip.sh               # → build/bb-groomflow-<version>.zip
```

Spin up the Docker WordPress stack before installing the ZIP.

```bash
cd docker
docker compose up -d
```

Once WordPress is healthy, install the ZIP with WP-CLI inside the stack.

```bash
cd docker
ZIP=../build/bb-groomflow-0.1.0-dev.zip        # adjust to the latest build filename
docker compose run --rm -T wpcli \
  wp plugin install "$ZIP" --force --activate
```

Need a quick data snapshot? Run the bundled helpers:

```bash
docker compose run --rm -T wpcli wp bbgf visits list --limit=10
docker compose run --rm -T wpcli wp bbgf sync prepare
```

After testing, shut down containers with `docker compose down` (pass `-v` to reset volumes).

Need demo data back on the board? Use the bundled seed command once Docker WP is running:

```bash
cd docker
docker compose run --rm -T wpcli wp bbgf visits seed-demo --count=8
```

`--count` controls how many demo visits each view receives (min 1, max 12). Pass `--force` to clear `visits`, `visit_services`, `visit_flags`, and `stage_history` before reseeding—the CLI now logs how many rows it removed so you know the reset worked. The command creates sample clients, guardians, and visits across every configured view so the lobby/interactive boards stay populated with realistic timestamps.

## 4. QA / QC Flow

Follow `QA_TOOLBELT.md` before handing off work.

1. Run PHPCS: `qa-phpcs plugin/bb-groomflow`
2. Execute any manual a11y/performance steps required by the sprint
3. Capture relevant artifacts in `/opt/qa/artifacts`
4. Install and smoke test inside Docker WordPress with the packaged ZIP
5. After every install, run a **full happy-path pass** through the admin forms (Stages, Guardians, Clients, Services, Packages, Flags, Views, Settings). For each screen:
   - Create a new record and submit it.
   - Re-open the record in edit mode and confirm all values (emoji, colors, selectors, text fields) pre-populate correctly.
   - Document any failures in the breadcrumb and open follow-up tasks immediately.
   - Pay extra attention to Packages: verify the selected services remain checked, the order values persist, and no validation errors appear after saving.
6. Use WP-CLI / QA toolbelt helpers (see `QA_TOOLBELT.md`) to automate or script extended form regression checks when possible.

Document results inside the breadcrumb for the run.

## 5. Releasing & Pushing

1. Confirm `git status` is clean; review `build/` artifacts (never commit ZIPs).
2. Commit using Conventional Commits.
3. Push to GitHub:
   ```bash
   git push origin main
   ```
4. Update `CHANGELOG.md` and docs alongside code changes.
5. Leave a breadcrumb (`docs/breadcrumbs/<date>-<topic>.md`) summarizing the run.

## 6. Troubleshooting

- **SSH auth failed**: rerun `ssh-agent` + `ssh-add` and confirm the remote URL uses SSH.
- **ZIP not regenerated**: ensure version constant in `bb-groomflow.php` is updated; rerun the build script.
- **Docker volume stale**: `docker compose down -v` to reset the database.
- **PHPCS missing WPCS**: export `WPCS_DIR` to point at the WordPress Coding Standards install (e.g., `/usr/share/php/PHP_CodeSniffer/Standards`).

Keep this document updated whenever workflow steps change.

## 7. Production Sync (GoDaddy Managed WP)

Use the helper script to connect to the live Bubbles & Bows site. Secrets are pre-configured on this machine.

```bash
~/.secrets/codex_bubbles_ssh.sh whoami           # smoke test access
~/.secrets/codex_bubbles_ssh.sh wp plugin list   # confirm WP-CLI works
```

- Environment: `~/.secrets/bubbles_wp.env`
- Host key: `~/.secrets/known_hosts_bubbles`
- Password file: `~/.secrets/bubbles_wp.pass` (already 0600)

### 7.1 Pulling Production Assets

1. Stream `wp-content` straight into `docker/prod-sync/wp-content` (no remote tar left behind):
   ```bash
   source ~/.secrets/bubbles_wp.env
   mkdir -p docker/prod-sync
   sshpass -f ~/.secrets/bubbles_wp.pass \
     ssh -p "$SSH_PORT" -o StrictHostKeyChecking=yes \
     -o UserKnownHostsFile=~/.secrets/known_hosts_bubbles \
     -o PreferredAuthentications=password -o PubkeyAuthentication=no \
     "${SSH_USER}@${SSH_HOST}" \
     "tar -C ${DOC_ROOT} -cf - wp-content" \
     | tar -C docker/prod-sync -xf -
   ```
   (All variables sourced from `~/.secrets/bubbles_wp.env`.)

2. Export the production database directly to your workspace:
   ```bash
   ~/.secrets/codex_bubbles_ssh.sh wp db export - --single-transaction \
     --default-character-set=utf8mb4 > docker/prod-sync/database.sql
   ```

3. (Optional) Archive `wp-config.php` for reference:
   ```bash
   sshpass -f ~/.secrets/bubbles_wp.pass \
     ssh -p "$SSH_PORT" -o StrictHostKeyChecking=yes \
     -o UserKnownHostsFile=~/.secrets/known_hosts_bubbles \
     -o PreferredAuthentications=password -o PubkeyAuthentication=no \
     "${SSH_USER}@${SSH_HOST}" \
     "cat ${DOC_ROOT}/wp-config.php" \
     > docker/prod-sync/wp-config.prod.php
   ```

### 7.2 Loading the Snapshot into Docker

- Ensure `docker/.env` exists with:
  ```env
  COMPOSE_PROJECT_NAME=groomflow
  WP_PORT=8083
  WP_DB_NAME=groomflow
  WP_DB_USER=groomflow
  WP_DB_PASSWORD=groomflow
  WP_DB_ROOT_PASSWORD=root
  ```

- Run the loader script to seed Docker with the production snapshot (no bind mounts, everything copied into the volume):
  ```bash
  bash scripts/load_prod_snapshot.sh
  ```
  This performs:
  - fresh `docker compose up -d`
  - moves stock `wp-content` aside and copies `docker/prod-sync/wp-content`
  - imports `docker/prod-sync/database.sql` into MySQL
  - search-replaces the site URL to `http://localhost:${WP_PORT}`
  - creates a local admin account `codexadmin / codexlocal` if missing

- Verify the site responds:
  ```bash
  curl -I http://localhost:${WP_PORT}
  docker compose run --rm wpcli wp core version
  ```

The WordPress volume now contains a self-contained copy of production assets — there are no symlinks back to this repo. Repeat the loader script whenever you refresh production data. NEVER push changes back to production unless explicitly instructed.
