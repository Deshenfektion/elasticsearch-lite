#!/bin/sh
set -e

if [ "${DB_DRIVER:-mysql}" = "mysql" ]; then
  attempts=0
  until php -r 'new PDO(sprintf("mysql:host=%s;port=%s", getenv("DB_HOST") ?: "mysql", getenv("DB_PORT") ?: "3306"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' 2>/dev/null; do
    attempts=$((attempts + 1))
    if [ "$attempts" -ge 60 ]; then
      echo "database did not become reachable in time" >&2
      exit 1
    fi
    echo "waiting for the database ($attempts)"
    sleep 1
  done
fi

php bin/console migrate

if [ "${SEED_ON_BOOT:-true}" = "true" ]; then
  php bin/console seed
fi

exec "$@"
