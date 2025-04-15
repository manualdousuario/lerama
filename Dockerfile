FROM php:8.3-fpm AS base

RUN apt-get update && apt-get install -y \
    nginx \
    nano \
    procps \
    psmisc \
    git \
    htop \
    nano \
    cron \
    openssl \
    libonig-dev \
    libxml2-dev \
    libssl-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libgmp-dev \
    libzip-dev \
    redis-server \
    locales \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install mysqli pdo_mysql sockets gd zip gmp bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && sed -i -e 's/# pt_BR.UTF-8 UTF-8/pt_BR.UTF-8 UTF-8/' /etc/locale.gen \
    && locale-gen \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

FROM base AS builder

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY app/ /app/

WORKDIR /app

RUN composer config platform.php-64bit 8.3
RUN composer install --no-interaction --optimize-autoloader

FROM base

COPY --from=builder /usr/local/bin/composer /usr/local/bin/composer
COPY --from=builder /app /app

COPY default.conf /etc/nginx/sites-available/default
COPY setup/ /setup/

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app

ENV TZ=America/Sao_Paulo
ENV LANG=pt_BR.UTF-8
ENV LANGUAGE=pt_BR:pt
ENV LC_ALL=pt_BR.UTF-8

EXPOSE 8077

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]