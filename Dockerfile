# syntax=docker/dockerfile:1

# 1) PHP dependencies (no dev packages). Platform checks are skipped here because the
#    runtime stage below is where the real PHP extensions live.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# 2) Front-end build (Tailwind scans Blade views and the Filament sources in vendor/).
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY --from=vendor /app /app
RUN npm run build

# 3) Runtime: FrankenPHP = Caddy web server with PHP built in, automatic HTTPS.
FROM dunglas/frankenphp:1-php8.3 AS runtime

RUN install-php-extensions pdo_mysql gd intl zip opcache redis bcmath exif pcntl \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-puruz.ini
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/puruz-entrypoint

WORKDIR /app
COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build

RUN rm -f .env && mkdir -p storage/app/public storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && php artisan package:discover --ansi \
    && php artisan filament:assets \
    && php artisan storage:link \
    && useradd --create-home --uid 1000 puruz \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && chown -R puruz:puruz /app/storage /app/bootstrap/cache /data/caddy /config/caddy \
    && chmod +x /usr/local/bin/puruz-entrypoint

USER puruz

ENTRYPOINT ["puruz-entrypoint"]
CMD ["web"]
