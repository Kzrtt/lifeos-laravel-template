#!/bin/sh
# Prepara o ambiente do container: APP_KEY persistente, espera do banco, migrations e caches.
set -e
cd /var/www/html

# APP_KEY: usa a do ambiente; senão gera uma vez e guarda no volume de storage (sobrevive a rebuilds).
if [ -z "${APP_KEY:-}" ]; then
    KEYFILE=storage/app/.app_key
    if [ ! -s "$KEYFILE" ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$KEYFILE"
        echo "[entrypoint] APP_KEY gerada em $KEYFILE"
    fi
    export APP_KEY="$(cat "$KEYFILE")"
fi

mkdir -p storage/app/private storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

until pg_isready -h "${DB_HOST:-db}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-lifeos}" >/dev/null 2>&1; do
    echo "[entrypoint] aguardando o PostgreSQL..."
    sleep 2
done

if [ "$1" = "php-fpm" ]; then
    php artisan package:discover --ansi
    php artisan migrate --force
    php artisan storage:link 2>/dev/null || true
    php artisan config:cache
    php artisan route:cache
fi

exec "$@"
