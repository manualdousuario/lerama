#!/bin/bash

echo "Validando variaveis de ambiente."

# Função para verificar se a variável de ambiente está definida
check_env_var() {
    var_name=$1
    if [ -z "${!var_name}" ]; then
        echo "Error: A variável de ambiente $var_name não está definida." >&2
        exit 1
    fi
}

# Lista de variáveis de ambiente obrigatórias
REQUIRED_ENV_VARS=(
    "DB_HOST"
    "DB_USERNAME"
    "DB_PASSWORD"
    "DB_NAME"
    "ADMIN_PASSWORD"
    "SITE_URL"
    "SITE_NAME"
)

# Validar cada variável de ambiente
for var in "${REQUIRED_ENV_VARS[@]}"; do
    check_env_var "$var"
done

echo "Todas as variáveis de ambiente obrigatórias estão definidas."