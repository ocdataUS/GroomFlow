# Workflow — GroomFlow Delivery (Docker)

Reference for moving from shell login to a tested ZIP running in Docker WordPress. The fast path lives in `AGENTS.md`; use this file when you need details or troubleshooting.

## Scope
- **Lives here:** Docker/ZIP install loop, build commands, push/SSH troubleshooting.
- **Not here:** Product requirements, REST/UX details, architecture (see `AGENTS.md` map).

## Quick Start
```bash
git status
cp docker/.env.example docker/.env   # if missing
cd docker && docker compose up -d
cd ..
bash scripts/build_plugin_zip.sh
cd docker
ZIP=../build/bb-groomflow-0.1.0-dev.zip
docker compose cp "$ZIP" wordpress:/var/www/html/bb-groomflow.zip
docker compose run --rm -T wpcli wp plugin install /var/www/html/bb-groomflow.zip --force --activate
docker compose run --rm -T wpcli wp bbgf visits seed-demo --count=8 --force
qa-phpcs plugin/bb-groomflow       # then run the admin happy-path
```

## GitHub Access (SSH)
Use the pre-generated SSH keypair.
```bash
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_ed25519_codex
ssh -T git@github.com          # accept the fingerprint if prompted
git remote set-url origin git@github.com:ocdataUS/GroomFlow.git
```
Repeat `ssh-agent` + `ssh-add` for new shells before pushing.

## Build & Install Notes
- Build assets with `npm run build` when front-end files change; packaging uses `scripts/build_plugin_zip.sh`.
- Always install the packaged ZIP inside Docker; never bind-mount `plugin/bb-groomflow/`.
- After installing, reseed demo data (`docker compose run --rm -T wpcli wp bbgf visits seed-demo --count=8 --force`) so boards show realistic timers.

## QA Flow (see `QA_TOOLBELT.md` for commands)
1. `qa-phpcs plugin/bb-groomflow` (WordPress standards).
2. Manual admin happy-path: create + edit + confirm persistence for Clients, Guardians, Services, Packages, Flags, Views, Settings.
3. When behaviour changes, run `bash scripts/qa_smoke.sh` or targeted WP-CLI/browser checks; save artifacts to `/opt/qa/artifacts` and log in breadcrumbs + `qa/QA_LOG.md`.

## Release & Push
1. Keep ZIPs out of git; confirm `git status` is clean.
2. Commit using Conventional Commits.
3. Push via SSH (`git push origin <branch>`).
4. Update breadcrumbs with actions, QA results, and artifact paths.

## Troubleshooting
- **SSH auth failed:** rerun `ssh-agent` + `ssh-add`; confirm the remote uses SSH.
- **ZIP not regenerated:** bump version if needed and rerun `bash scripts/build_plugin_zip.sh`.
- **Docker volume stale:** `docker compose down -v` to reset; reinstall ZIP and reseed.
- **PHPCS missing WPCS:** set `WPCS_DIR` to the WordPress Coding Standards install (e.g., `/usr/share/php/PHP_CodeSniffer/Standards`).

## Production Sync (reference)
When explicitly asked to sync GoDaddy managed WP, use the preconfigured helpers:
```bash
~/.secrets/codex_bubbles_ssh.sh whoami
~/.secrets/codex_bubbles_ssh.sh wp plugin list
bash scripts/load_prod_snapshot.sh    # loads snapshot into Docker with local URL + admin
```
Do not push to production without explicit direction.
