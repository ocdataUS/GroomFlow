#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_DIR="${ROOT_DIR}/docker"
WP_CONTENT_SRC="${COMPOSE_DIR}/prod-sync/wp-content"
DB_DUMP="${COMPOSE_DIR}/prod-sync/database.sql"
PROD_DOMAIN="https://bubblesandbows.life"

if [[ ! -d "${WP_CONTENT_SRC}" ]]; then
	echo "Missing ${WP_CONTENT_SRC}; run the production sync first." >&2
	exit 1
fi

if [[ ! -f "${DB_DUMP}" ]]; then
	echo "Missing ${DB_DUMP}; export the production database first." >&2
	exit 1
fi

pushd "${COMPOSE_DIR}" >/dev/null 2>&1

docker compose down
docker compose up -d

# Clean up any previous stock backup so we do not fill the volume.
docker compose exec wordpress bash -lc 'if [ -d /var/www/html/wp-content-stock ]; then rm -rf /var/www/html/wp-content-stock; fi'

# Move the vanilla wp-content out of the way before loading production assets.
docker compose exec wordpress bash -lc 'if [ -d /var/www/html/wp-content ]; then mv /var/www/html/wp-content /var/www/html/wp-content-stock; fi'

# Copy production wp-content into the container (no bind mounts).
docker compose cp "${WP_CONTENT_SRC}/." wordpress:/var/www/html/wp-content
docker compose exec wordpress chown -R www-data:www-data /var/www/html/wp-content

# Import the production database snapshot.
cat "${DB_DUMP}" | docker compose exec -T db mysql -u"${WP_DB_USER:-groomflow}" -p"${WP_DB_PASSWORD:-groomflow}" "${WP_DB_NAME:-groomflow}"

LOCAL_URL="http://localhost:${WP_PORT:-8080}"

# Update core site URLs and run a search-replace so assets resolve locally.
docker compose run --rm wpcli wp option update siteurl "${LOCAL_URL}"
docker compose run --rm wpcli wp option update home "${LOCAL_URL}"
docker compose run --rm wpcli wp search-replace "${PROD_DOMAIN}" "${LOCAL_URL}" --skip-columns=guid --precise --all-tables --skip-plugins --skip-themes

# Ensure a local admin account exists for development logins.
docker compose run --rm wpcli wp user get codexadmin --field=ID >/dev/null 2>&1 || \
  docker compose run --rm wpcli wp user create codexadmin codexadmin@example.com --role=administrator --user_pass=codexlocal

popd >/dev/null 2>&1

echo "✅ Local environment seeded with production snapshot."
