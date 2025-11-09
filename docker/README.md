# Docker WordPress Environment

This compose stack provisions WordPress + MySQL + WP-CLI for local plugin QA. It keeps data in Docker volumes so database state persists between runs.

## Usage

```bash
cp docker/.env.example docker/.env    # or create docker/.env with project-specific values
cd docker
docker compose up -d                  # start the stack
```

Recommended `docker/.env`:

```env
COMPOSE_PROJECT_NAME=groomflow
WP_PORT=8083
WP_DB_NAME=groomflow
WP_DB_USER=groomflow
WP_DB_PASSWORD=groomflow
WP_DB_ROOT_PASSWORD=root
```

Site is available at `http://localhost:${WP_PORT}`. The stack auto-generates `wp-config.php`; no bind mounts are used, so WordPress files live entirely inside the container volume.

### WP-CLI

```bash
cd docker
docker compose run --rm wpcli wp plugin list
```

To install a freshly built ZIP (see `scripts/build_plugin_zip.sh`):

```bash
cd docker
ZIP=../build/bb-groomflow-0.1.0-dev.zip
docker compose run --rm wpcli wp plugin install "$ZIP" --force --activate
```

Stop the stack with:

```bash
cd docker
docker compose down
```

If you need a clean slate, remove volumes:

```bash
cd docker
docker compose down -v
```

### Loading the Production Snapshot

Once you have synced production assets into `docker/prod-sync/` (see `docs/workflow.md` §7), run:

```bash
bash scripts/load_prod_snapshot.sh
```

The script:

- copies `prod-sync/wp-content` into the container volume (no symlinks)
- imports `prod-sync/database.sql`
- rewrites URLs to `http://localhost:${WP_PORT}`
- creates a local admin account `codexadmin / codexlocal` if needed

Check the site:

```bash
curl -I http://localhost:${WP_PORT}
docker compose run --rm wpcli wp core version
```
