FROM php:8.0-fpm

RUN apt-get update && apt-get install -y nginx cron nano procps \
    && docker-php-ext-install pdo_mysql
	
COPY default.conf /etc/nginx/sites-available/default

COPY src/ /var/www/html/

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

COPY crontab /etc/cron.d/lerama
RUN chmod 0644 /etc/cron.d/lerama
RUN crontab /etc/cron.d/lerama
RUN touch /var/log/lerama.log

RUN sed -i "s|'localhost'|'${DB_HOST}'|g" /var/www/html/config/appConfig.php \
    && sed -i "s|'root'|'${DB_USERNAME}'|g" /var/www/html/config/appConfig.php \
    && sed -i "s|''|'${DB_PASSWORD}'|g" /var/www/html/config/appConfig.php \
    && sed -i "s|'lerama'|'${DB_NAME}'|g" /var/www/html/config/appConfig.php \
    && sed -i "s|'https://lerama.test'|'${SITE_URL}'|g" /var/www/html/config/appConfig.php \
    && sed -i "s|'Lerama'|'${SITE_NAME}'|g" /var/www/html/config/appConfig.php

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]