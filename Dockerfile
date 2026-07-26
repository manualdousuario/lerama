# syntax=docker/dockerfile:1

FROM node:22-alpine AS assets
WORKDIR /build

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY resources ./resources
RUN npm run build

FROM serversideup/php:8.4-fpm-nginx-alpine AS base
USER root
RUN install-php-extensions gd intl

FROM base AS vendor
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
    --no-scripts --no-autoloader

# Stage 4: application
FROM base
WORKDIR /var/www/html

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stack \
    LOG_LEVEL=error \
    DB_CONNECTION=mariadb \
    CACHE_STORE=file \
    SESSION_DRIVER=file \
    QUEUE_CONNECTION=sync \
    BROADCAST_CONNECTION=log \
    FILESYSTEM_DISK=local \
    MAIL_MAILER=smtp \
    PHP_OPCACHE_ENABLE=1 \
    HEALTHCHECK_PATH=/up \
    AUTORUN_ENABLED=true

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /build/public/assets ./public/assets
COPY --chmod=755 docker/entrypoint.d/ /etc/entrypoint.d/

RUN mkdir -p /etc/s6-overlay/s6-rc.d/laravel-scheduler \
        /etc/s6-overlay/s6-rc.d/user/contents.d && \
    echo 'longrun' > /etc/s6-overlay/s6-rc.d/laravel-scheduler/type && \
    printf '#!/command/with-contenv sh\nexec php /var/www/html/artisan schedule:work\n' \
        > /etc/s6-overlay/s6-rc.d/laravel-scheduler/run && \
    chmod 755 /etc/s6-overlay/s6-rc.d/laravel-scheduler/run && \
    touch /etc/s6-overlay/s6-rc.d/user/contents.d/laravel-scheduler

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative && \
    php artisan filament:assets && \
    chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 8080
