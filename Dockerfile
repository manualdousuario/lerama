FROM php:8.0-fpm

RUN apt-get update && apt-get install -y nginx cron nano procps unzip \
    && docker-php-ext-install pdo_mysql
	
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configura o PHP-FPM para enviar logs para o coletor de logs do Docker
RUN ln -sf /dev/stdout /var/log/php-fpm.access.log \
    && ln -sf /dev/stderr /var/log/php-fpm.error.log

# Configura o log de erros do PHP
RUN echo "error_log = /dev/stderr" >> /usr/local/etc/php/php.ini

COPY default.conf /etc/nginx/sites-available/default

RUN mkdir -p /app
COPY app/ /app/

WORKDIR /app
RUN composer install --no-interaction --optimize-autoloader

# Copia e configura permissões do script de inicialização
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

RUN mkdir -p /app/logs

# Configura o cron para usar o logger para coleta de logs do Docker
RUN touch /app/logs/cron.log && ln -sf /dev/stdout /app/logs/cron.log
RUN echo '0 * * * * root php "/app/cron/fetchFeeds.php" 2>&1 | logger -t cron-fetchfeeds' >> /etc/crontab

RUN chown -R www-data:www-data /app && chmod -R 755 /app

# Garante que os logs do nginx sejam direcionados para o coletor de logs do Docker
RUN ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
