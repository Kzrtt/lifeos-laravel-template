# LifeOS (Laravel 12 modular) — imagem única de PHP-FPM usada pelo serviço `app`.
FROM php:8.4-fpm-alpine

RUN apk add --no-cache postgresql-client unzip git icu-libs libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql mbstring opcache \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-lifeos.ini

WORKDIR /var/www/html
COPY . .

# app-modules/* entram como repositórios "path" (symlink), então precisam existir antes do install.
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts \
    && composer dump-autoload --optimize --no-scripts \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
