#!/usr/bin/env bash
# Run the WordPress.org Plugin Check (PCP) tool against flowblinq-ai-boost.
# This is the same tool the WP.org review team uses — running it before
# submission catches almost everything they would flag.
#
# Spins up the existing testing stack (mysql + wordpress container with
# the plugin bind-mounted), auto-installs WordPress via wp-cli, installs
# the plugin-check plugin from wordpress.org, runs the check, prints
# results to stdout. Non-zero exit on any error/warning category.

set -euo pipefail
cd "$(dirname "$0")"

NETWORK="testing_fqgeo-test-net"
SITE_URL="http://wordpress"

echo "=== bringing up wordpress + mysql + mock-upstream ==="
docker compose up -d wordpress

echo ""
echo "=== waiting for wordpress to be reachable ==="
for i in {1..30}; do
  if docker compose exec -T wordpress curl -sf -o /dev/null http://localhost/; then
    echo "  wordpress is up"
    break
  fi
  sleep 2
done

# Get the wordpress container name (compose-prefixed)
WP_CONTAINER=$(docker compose ps -q wordpress)

run_wp() {
  docker run --rm \
    --network "$NETWORK" \
    --volumes-from "$WP_CONTAINER" \
    -e WORDPRESS_DB_HOST=mysql \
    -e WORDPRESS_DB_USER=root \
    -e WORDPRESS_DB_PASSWORD=testpass \
    -e WORDPRESS_DB_NAME=wordpress \
    --user 33:33 \
    wordpress:cli \
    wp --path=/var/www/html --allow-root "$@"
}

echo ""
echo "=== install wordpress (idempotent) ==="
run_wp core is-installed 2>/dev/null \
  || run_wp core install \
       --url="$SITE_URL" \
       --title="Plugin Check Test" \
       --admin_user=admin \
       --admin_password=admin \
       --admin_email=admin@test.local \
       --skip-email

echo ""
echo "=== install + activate plugin-check from wordpress.org ==="
run_wp plugin install plugin-check --activate --force

echo ""
echo "=== activate flowblinq-ai-boost plugin (already bind-mounted) ==="
run_wp plugin activate flowblinq-ai-boost

echo ""
echo "=== run plugin-check against flowblinq-ai-boost ==="
echo ""
run_wp plugin check flowblinq-ai-boost --severity=4 --format=table || PCP_EXIT=$?

echo ""
echo "=== done ==="
echo "To inspect the staging WP site: http://localhost:8080 (admin/admin)"
echo "To stop everything: docker compose down -v"

exit ${PCP_EXIT:-0}
