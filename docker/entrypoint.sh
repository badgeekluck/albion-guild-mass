#!/bin/sh
set -e

cd /var/www

READY_FLAG=storage/framework/.docker-ready

# Only the "app" container (APP_ROLE=app) prepares the project. The reverb and
# queue containers wait for it via the compose healthcheck.
if [ "${APP_ROLE:-none}" = "app" ]; then
    rm -f "$READY_FLAG"

    if [ ! -f .env ]; then
        echo "[entrypoint] .env not found, copying .env.example"
        cp .env.example .env
    fi

    if [ ! -f vendor/autoload.php ] || [ composer.lock -nt vendor/autoload.php ]; then
        echo "[entrypoint] Installing composer dependencies"
        composer install --no-interaction --prefer-dist
    fi

    if ! grep -q '^APP_KEY=base64:' .env; then
        echo "[entrypoint] Generating APP_KEY"
        php artisan key:generate --force
    fi

    mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    chmod -R ug+rwX,o+rwX storage bootstrap/cache

    echo "[entrypoint] Waiting for the database"
    until php artisan db:show >/dev/null 2>&1; do
        sleep 2
    done

    php artisan migrate --force
    php artisan db:seed --class=GameRoleSeeder --force

    if [ "${APP_DEV_SEED:-false}" = "true" ]; then
        php artisan db:seed --class=DevSeeder --force
    fi

    php artisan optimize:clear >/dev/null

    # Warm the item icon cache in the background (icons are also fetched lazily on first view).
    (php artisan app:cache-item-icons >/dev/null 2>&1 &)

    touch "$READY_FLAG"
    echo "[entrypoint] Ready"
fi

exec "$@"
