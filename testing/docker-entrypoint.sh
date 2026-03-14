#!/usr/bin/env bash
set -euo pipefail

DB_HOST="${WORDPRESS_DB_HOST:-mysql}"
DB_NAME="${WORDPRESS_DB_NAME:-wordpress_test}"
DB_USER="${WORDPRESS_DB_USER:-root}"
DB_PASS="${WORDPRESS_DB_PASSWORD:-testpass}"
WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"

echo "[entrypoint] Waiting for MySQL at ${DB_HOST}..."
MAX_TRIES=30
TRIES=0
until mysqladmin ping -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" --silent 2>/dev/null; do
    TRIES=$((TRIES + 1))
    if [ "${TRIES}" -ge "${MAX_TRIES}" ]; then
        echo "[entrypoint] MySQL not ready after ${MAX_TRIES} attempts. Exiting."
        exit 1
    fi
    echo "[entrypoint] MySQL not ready yet (attempt ${TRIES}/${MAX_TRIES}), waiting 2s..."
    sleep 2
done
echo "[entrypoint] MySQL is ready."

# Install WordPress test suite (idempotent)
if [ ! -d "${WP_TESTS_DIR}/includes" ]; then
    echo "[entrypoint] Installing WordPress test suite..."
    bash /usr/local/bin/install-wp-tests.sh \
        "${DB_NAME}" "${DB_USER}" "${DB_PASS}" "${DB_HOST}" latest true
    echo "[entrypoint] WordPress test suite installed."
else
    echo "[entrypoint] WordPress test suite already installed."
fi

echo "[entrypoint] Running PHPUnit..."
exec /app/vendor/bin/phpunit "$@"
