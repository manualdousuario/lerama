#!/bin/bash

echo "Validando variáveis de ambiente."

check_env_var() {
    var_name=$1
    if [ -z "${!var_name}" ]; then
        echo "Error: A variável de ambiente $var_name não está definida ou está vazia." >&2
        exit 1
    fi
}

REQUIRED_ENV_VARS=(
    "DB_HOST"
    "DB_USERNAME"
    "DB_PASSWORD"
    "DB_NAME"
    "ADMIN_PASSWORD"
    "SITE_URL"
    "SITE_NAME"
)

for var in "${REQUIRED_ENV_VARS[@]}"; do
    check_env_var "$var"
done

echo "Todas as variáveis de ambiente obrigatórias estão definidas."

echo "Criando variáveis arquivo de variaveis de ambiente."

echo "DB_HOST=${DB_HOST}" >> /var/www/html/.env
echo "DB_USERNAME=${DB_USERNAME}" >> /var/www/html/.env
echo "DB_PASSWORD=${DB_PASSWORD}" >> /var/www/html/.env
echo "DB_NAME=${DB_NAME}" >> /var/www/html/.env
echo "ADMIN_PASSWORD=${ADMIN_PASSWORD}" >> /var/www/html/.env
echo "SITE_URL=${SITE_URL}" >> /var/www/html/.env
echo "SITE_NAME=${SITE_NAME}" >> /var/www/html/.env

echo "Variáveis de ambiente salvas com sucesso."
