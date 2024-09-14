FROM php:8.0-fpm

RUN apt-get update && apt-get install -y nginx cron nano procps unzip gi \
    && docker-php-ext-install pdo_mysql
	
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY default.conf /etc/nginx/sites-available/default
COPY src/ /var/www/html/

RUN echo "DB_HOST=${DB_HOST}" >> /var/www/html/.env && \
    echo "DB_USERNAME=${DB_USERNAME}" >> /var/www/html/.env && \
    echo "DB_PASSWORD=${DB_PASSWORD}" >> /var/www/html/.env && \
    echo "DB_NAME=${DB_NAME}" >> /var/www/html/.env && \
    echo "ADMIN_PASSWORD=${ADMIN_PASSWORD}" >> /var/www/html/.env && \
    echo "SITE_URL=${SITE_URL}" >> /var/www/html/.env && \
    echo "SITE_NAME=${SITE_NAME}" >> /var/www/html/.env

WORKDIR /var/www/html
RUN composer install --no-interaction --optimize-autoloader

COPY validate_env.sh /usr/local/bin/validate_env.sh
RUN chmod +x /usr/local/bin/validate_env.sh

COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

RUN touch /var/log/lerama.log
RUN echo '* * * * * root php "/var/www/html/cron/fetchFeeds.php" >> /var/log/lerama.log 2>&1' >> /etc/crontab

RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["/bin/bash", "-c", "/usr/local/bin/validate_env.sh && /usr/local/bin/start.sh"]