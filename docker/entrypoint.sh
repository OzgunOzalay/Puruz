#!/bin/sh
# One image, three roles: web (FrankenPHP), worker (queue) and scheduler (cron jobs).
set -e

cd /app

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Generate one with: docker compose run --rm app php artisan key:generate --show" >&2
    exit 1
fi

role="$1"

case "$role" in
    web)
        if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
            php artisan migrate --force
        fi
        php artisan optimize
        exec frankenphp run --config /etc/frankenphp/Caddyfile
        ;;
    worker)
        php artisan optimize
        exec php artisan queue:work --sleep=2 --tries=3 --backoff=30 --max-time=3600
        ;;
    scheduler)
        php artisan optimize
        exec php artisan schedule:work
        ;;
    *)
        exec "$@"
        ;;
esac
