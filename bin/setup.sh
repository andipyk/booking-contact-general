#!/usr/bin/env bash
# Bring up the stack and provision WordPress from nothing.
#
# Safe to re-run: every step checks whether it is already done, so this doubles
# as the repair command after editing the plugin list or interrupting a run.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
  echo "Missing .env — copy .env.example to .env and fill in values first." >&2
  exit 1
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

wp() { ./bin/wp "$@"; }

echo "==> Building the shared WordPress image"
# A plain build without a provenance attestation: the same files give the same
# image ID in every project, so they all run one image.
docker build --quiet --provenance=false \
  -t wp-portfolio-wordpress:7.1-php8.4 docker/wordpress >/dev/null

echo "==> Starting containers"
docker compose up -d --wait

if wp core is-installed >/dev/null 2>&1; then
  echo "==> WordPress already installed, skipping core install"
else
  echo "==> Installing WordPress"
  wp core install \
    --url="$WP_URL" \
    --title="$WP_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
fi

echo "==> Installing plugins from the directory"
wp plugin install seo-by-rank-math redis-cache --activate

echo "==> Activating the studio plugin and theme"
wp plugin activate ess-core
wp theme activate eastern-standard

echo "==> Enabling the persistent object cache"
wp redis enable || echo "    (redis drop-in not enabled; continuing)"

echo "==> Removing the default clutter"
wp plugin delete akismet hello >/dev/null 2>&1 || true
wp post delete 1 --force 2>/dev/null || true   # "Hello world!"
wp post delete 2 --force 2>/dev/null || true   # "Sample Page"
wp comment delete 1 --force 2>/dev/null || true

echo "==> Pretty permalinks (clean REST URLs and CPT archives)"
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard

echo "==> Seeding demo content"
# --user=admin matters: block and template writes performed as an anonymous
# WP-CLI user are silently dropped by some of the editor's save paths.
wp eval-file bin/seed-content.php --user=admin

echo "==> Configuring Rank Math"
wp eval-file bin/configure-seo.php --user=admin

echo "==> Importing the n8n workflow"
./bin/import-n8n.sh || echo "    (n8n import skipped; run ./bin/import-n8n.sh once n8n is up)"

echo "==> Flushing caches"
wp cache flush || true
wp rewrite flush --hard

cat <<EOF

==> Done.

    Site:        ${WP_URL}
    wp-admin:    ${WP_URL}/wp-admin  (user: ${WP_ADMIN_USER})
    Mailpit:     http://localhost:${MAILPIT_PORT:-8102}
    n8n:         http://localhost:${N8N_PORT:-8103}
    WP-CLI:      bin/wp <command>
    phpMyAdmin:  docker compose --profile tools up -d
                 then http://localhost:${PMA_PORT:-8101}

EOF
