FROM shinsenter/php:8.3-fpm-nginx

# Default application envs
ENV ENABLE_CRONTAB=1
ENV APP_PATH=/app
ENV DOCUMENT_ROOT=public
ENV TZ=UTC
ENV ENABLE_TUNING_FPM=1
ENV DISABLE_AUTORUN_SCRIPTS=0

# Copy application files
COPY app/ ${APP_PATH}/
WORKDIR ${APP_PATH}

# Install composer dependencies
RUN composer config platform.php-64bit 8.3 && \
    composer install --no-interaction --optimize-autoloader --no-dev

# Copy crontab template
COPY crontab.tpl /crontab.tpl
RUN chmod 0644 /crontab.tpl

# Copy startup scripts
COPY /startup/*
RUN chmod +x /startup/*

# Set permissions
RUN chown -R www-data:www-data ${APP_PATH} && \
    chmod -R 755 ${APP_PATH} && \
    mkdir -p ${APP_PATH}/${DOCUMENT_ROOT}/storage/thumbnails && \
    chown -R www-data:www-data ${APP_PATH}/${DOCUMENT_ROOT}/storage

EXPOSE 80