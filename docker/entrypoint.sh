#!/bin/sh
# Startup: wait for the database, run migrations, create the initial admin, serve on 0.0.0.0:8000.
set -e
cd /var/www

if [ -z "$APP_KEY" ]; then
  export APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
fi

tries=0
until php artisan migrate --force --no-interaction; do
  tries=$((tries + 1))
  if [ "$tries" -ge 30 ]; then
    echo "Database not reachable or migration failed after 30 attempts" >&2
    exit 1
  fi
  echo "Waiting for the database (attempt $tries)..." >&2
  sleep 2
done

php artisan library:create-admin --no-interaction

export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"
exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
