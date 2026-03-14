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
    # .htaccess is pre-seeded via bind mount (htaccess-seed) so rewrite rules are
    # active immediately. After --staging, complete the WordPress install wizard at
    # http://localhost:8080, then activate the plugin via Plugins → Installed Plugins.
    # Proxy routes (/llms.txt etc.) will work once the plugin is activated.
    echo "Staging setup notes:"
    echo "  1. Complete WordPress install at http://localhost:8080"
    echo "  2. Activate the Flowblinq GEO plugin via Plugins → Installed Plugins"
    echo "  3. Proxy routes will be live: /llms.txt, /llms-full.txt, /.well-known/ucp.json"
    echo "  .htaccess is pre-seeded — no permalink save required."
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
