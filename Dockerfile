FROM php:8.0-fpm

RUN apt-get update && apt-get install -y nginx cron nano procps \
    && docker-php-ext-install pdo_mysql
	
COPY default.conf /etc/nginx/sites-available/default

COPY src/ /var/www/html/

COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

RUN touch /var/log/lerama.log
RUN echo '* * * * * root php "/var/www/html/cron/fetchFeeds.php" >> /var/log/lerama.log 2>&1' >> /etc/crontab

RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]