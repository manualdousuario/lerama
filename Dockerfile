FROM shinsenter/php:8.4-fpm-nginx

# Default application envs
ENV ENABLE_CRONTAB=1
ENV APP_PATH=/app
ENV DOCUMENT_ROOT=public
ENV TZ=UTC
ENV ENABLE_TUNING_FPM=1
ENV DISABLE_AUTORUN_SCRIPTS=0
ENV CRONTAB_PROCESS_FEEDS="0 */4 * * *"
ENV CRONTAB_FEED_STATUS="0 0 * * *"
ENV CRONTAB_PROXY="0 0 * * *"

# Copy application files
COPY app/ ${APP_PATH}/
WORKDIR ${APP_PATH}

# Install composer dependencies
RUN composer config platform.php-64bit 8.4 && \
    composer install --no-interaction --optimize-autoloader --no-dev

# Create crontab
RUN mkdir -p /var/log/lerama && \
    echo '${CRONTAB_PROCESS_FEEDS} /usr/local/bin/php /app/bin/lerama feed:process | tee -a /var/log/lerama/feed_process.log' >> /etc/crontab.d/lerama && \
    echo '${CRONTAB_FEED_STATUS} /usr/local/bin/php /app/bin/lerama feed:check-status | tee -a /var/log/lerama/check_status.log' >> /etc/crontab.d/lerama && \
    echo '${CRONTAB_PROXY} /usr/local/bin/php /app/bin/lerama proxy:update | tee -a /var/log/lerama/proxy_update.log' >> /etc/crontab.d/lerama && \
    chmod 0644 /etc/crontab.d/lerama

# Set permissions
RUN chown -R www-data:www-data ${APP_PATH} && \
    chmod -R 755 ${APP_PATH} && \
    mkdir -p ${APP_PATH}/${DOCUMENT_ROOT}/storage/thumbnails && \
    chown -R www-data:www-data ${APP_PATH}/${DOCUMENT_ROOT}/storage

EXPOSE 80