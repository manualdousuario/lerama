FROM php:8.0-fpm

RUN apt-get update && apt-get install -y nginx cron nano procps unzip git \
    && docker-php-ext-install pdo_mysql
	
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY default.conf /etc/nginx/sites-available/default
COPY src/ /var/www/html/

WORKDIR /var/www/html
RUN composer install --no-interaction --optimize-autoloader

COPY env.sh /usr/local/bin/env.sh
RUN chmod +x /usr/local/bin/env.sh

COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

RUN touch /var/log/lerama.log
RUN echo '* * * * * root php "/var/www/html/cron/fetchFeeds.php" >> /var/log/lerama.log 2>&1' >> /etc/crontab

RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["/bin/bash", "-c", "/usr/local/bin/env.sh && /usr/local/bin/start.sh"]