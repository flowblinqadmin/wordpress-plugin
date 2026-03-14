#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

case "${1:-all}" in
  --integration)   ARGS="--group integration" ;;
  --security)      ARGS="--group security" ;;
  --staging)
    docker compose up -d
    echo "Staging: http://localhost:8080"
    echo "Run '$0 --staging-setup' after WordPress finishes installing to activate the plugin and flush rewrite rules."
    exit 0
    ;;
  --staging-setup)
    # Activate the plugin via WP-CLI and flush rewrite rules so pretty permalink
    # routes (/llms.txt etc.) work. Run this once after --staging + WP setup wizard.
    echo "Activating plugin and flushing rewrite rules via WP-CLI..."
    docker compose run --rm \
      -e WORDPRESS_DB_HOST=mysql \
      -e WORDPRESS_DB_NAME=wordpress \
      -e WORDPRESS_DB_USER=root \
      -e WORDPRESS_DB_PASSWORD=testpass \
      --entrypoint wp \
      wordpress \
      --allow-root --path=/var/www/html \
      plugin activate flowblinq-geo
    docker compose run --rm \
      --entrypoint wp \
      wordpress \
      --allow-root --path=/var/www/html \
      rewrite flush
    echo "Plugin activated. Proxy routes should now work at http://localhost:8080/llms.txt"
    exit 0
    ;;
  --down)          docker compose down -v; exit 0 ;;
  all)             ARGS="" ;;
  *)
    echo "Usage: $0 [--integration|--security|--staging|--staging-setup|--down]"
    exit 1
    ;;
esac

docker compose up -d mysql mock-upstream
echo "Waiting for MySQL healthcheck..."
docker compose run --rm wp-test ${ARGS:-}
echo "Tests complete."
