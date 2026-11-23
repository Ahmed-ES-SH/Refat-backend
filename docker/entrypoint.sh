#!/bin/sh
set -eu

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -qE '^APP_KEY=base64:' .env 2>/dev/null; then
    php artisan key:generate --no-interaction --force
fi

lock_hash="$(sha256sum composer.lock | awk '{print $1}')"
installed_hash="$(cat vendor/.composer-lock-hash 2>/dev/null || true)"

if [ ! -f vendor/autoload.php ] || [ "$lock_hash" != "$installed_hash" ]; then
    echo "[backend] composer.lock changed, installing dependencies..."
    composer install --no-interaction --prefer-dist
    printf '%s' "$lock_hash" > vendor/.composer-lock-hash
fi

php artisan config:clear --no-interaction >/dev/null 2>&1 || true

if [ "${SKIP_MIGRATIONS:-false}" != "true" ]; then
    echo "[backend] waiting for the database and running migrations..."
    attempt=1

    until php artisan migrate --force --no-interaction; do
        if [ "$attempt" -ge 30 ]; then
            echo "[backend] database is not reachable, giving up." >&2
            exit 1
        fi

        attempt=$((attempt + 1))
        sleep 2
    done
fi

exec "$@"
