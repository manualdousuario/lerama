#!/bin/bash

echo "Iniciando rotinas."
service cron restart

check_nginx() {
    if ! pgrep nginx > /dev/null; then
        echo "Falha ao iniciar webservice."
        exit 1
    else
        echo "Webservice iniciou."
    fi
}

check_php_fpm() {
    if ! pgrep php-fpm > /dev/null; then
        echo "Falha ao iniciar o PHP."
        exit 1
    else
        echo "PHP iniciou."
    fi
}

if [ ! -d /var/run/php ]; then
    mkdir -p /var/run/php
    chown -R www-data:www-data /var/run/php
fi

echo "Iniciando PHP..."
php-fpm &

sleep 3

check_php_fpm

sleep 3

echo "Testando configuração do webservice..."
nginx -t
if [ $? -ne 0 ]; then
    echo "Configuração do webservice invalida."
    exit 1
else
    echo "Configuração valida do webservice."
fi

echo "Iniciando webservice..."
nginx -g "daemon off;" &

sleep 3

check_nginx

wait -n

exit $?
