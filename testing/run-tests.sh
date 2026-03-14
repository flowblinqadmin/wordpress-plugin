#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

case "${1:-all}" in
  --integration) ARGS="--group integration" ;;
  --security)    ARGS="--group security" ;;
  --staging)     docker compose up -d; echo "Staging: http://localhost:8080"; exit 0 ;;
  --down)        docker compose down -v; exit 0 ;;
  all)           ARGS="" ;;
  *)             echo "Usage: $0 [--integration|--security|--staging|--down]"; exit 1 ;;
esac

docker compose up -d mysql mock-upstream
echo "Waiting for MySQL healthcheck..."
docker compose run --rm wp-test ${ARGS:-}
echo "Tests complete."
