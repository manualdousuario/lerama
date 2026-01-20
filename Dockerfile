FROM shinsenter/php:8.4-fpm-nginx

# Default application envs
ENV ENABLE_CRONTAB=1
ENV APP_PATH=/app
ENV DOCUMENT_ROOT=public
ENV TZ=UTC
ENV ENABLE_TUNING_FPM=1
ENV DISABLE_AUTORUN_SCRIPTS=0
ENV CRONTAB_PROCESS_FEEDS="* * * * *"
ENV CRONTAB_FEED_STATUS="0 0 * * *"
ENV CRONTAB_PROXY="0 0 * * *"

# Copy application files
COPY app/ ${APP_PATH}/
WORKDIR ${APP_PATH}

# Install composer dependencies
RUN composer config platform.php-64bit 8.4 && \
    composer install --no-interaction --optimize-autoloader --no-dev

# Crontab
RUN mkdir -p /etc/crontab.d/ && \
    echo "${CRONTAB_PROCESS_FEEDS} /usr/local/bin/php /app/bin/lerama feed:process | tee /tmp/feed_process.log" >> /etc/crontab.d/lerama && \
    echo "${CRONTAB_FEED_STATUS} /usr/local/bin/php /app/bin/lerama feed:check-status | tee /tmp/check_status.log" >> /etc/crontab.d/lerama && \
    echo "${CRONTAB_PROXY} /usr/local/bin/php /app/bin/lerama proxy:update | tee /tmp/proxy_update.log" >> /etc/crontab.d/lerama

# Set permissions
RUN chown -R www-data:www-data ${APP_PATH} && \
    chmod -R 755 ${APP_PATH} && \
    mkdir -p ${APP_PATH}/${DOCUMENT_ROOT}/storage/thumbnails && \
    chown -R www-data:www-data ${APP_PATH}/${DOCUMENT_ROOT}/storage

EXPOSE 80