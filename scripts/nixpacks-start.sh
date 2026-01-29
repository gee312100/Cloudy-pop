#!/usr/bin/env bash
set -euo pipefail

export DB_HOST="${DB_HOST:-${MYSQLHOST:-${MARIADB_HOST:-db}}}"
export DB_PORT="${DB_PORT:-${MYSQLPORT:-${MARIADB_PORT:-3306}}}"
export DB_NAME="${DB_NAME:-${MYSQLDATABASE:-${MARIADB_DATABASE:-cloudypop}}}"
export DB_USER="${DB_USER:-${MYSQLUSER:-${MARIADB_USER:-cloudypop}}}"
export DB_PASS="${DB_PASS:-${MYSQLPASSWORD:-${MARIADB_PASSWORD:-cloudypop}}}"

PORT="${PORT:-8080}"

exec php -S "0.0.0.0:${PORT}" -t .
