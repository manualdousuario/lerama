FROM shinsenter/php:8.3-fpm-nginx

# Default application envs
ENV ENABLE_CRONTAB=1
ENV APP_PATH=/app
ENV DOCUMENT_ROOT=public
ENV TZ=UTC

# Copy application files
COPY app/ ${APP_PATH}/
WORKDIR ${APP_PATH}

# Install composer dependencies
RUN composer config platform.php-64bit 8.3 && \
    composer install --no-interaction --optimize-autoloader --no-dev

# Create autorun scripts
COPY setup/ /setup/
RUN mkdir -p /startup && \
    ln -sf /setup/start.php /startup/20-migrations.php

# Copy cron jobs configuration
COPY crontab /etc/cron.d/lerama-cron
RUN chmod 0644 /etc/cron.d/lerama-cron

# Copy setup script
COPY docker-entrypoint.sh /startup/10-setup-env.sh
RUN chmod +x /startup/10-setup-env.sh

# Set permissions
RUN chown -R www-data:www-data ${APP_PATH} && \
    chmod -R 755 ${APP_PATH} && \
    mkdir -p ${APP_PATH}/${DOCUMENT_ROOT}/storage/thumbnails && \
    chown -R www-data:www-data ${APP_PATH}/${DOCUMENT_ROOT}/storage

EXPOSE 80