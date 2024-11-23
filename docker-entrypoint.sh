#!/bin/bash

###########################################
# Sintoniza Docker Entrypoint
# 
# Este script inicializa o container do Sintoniza:
# - Valida e configura variáveis de ambiente
# - Configura SMTP e outras configurações opcionais
# - Inicia serviços (Cron, PHP-FPM e Nginx)
###########################################

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Função para logs de sucesso
log_success() {
    echo -e "${GREEN}[✓] $1${NC}"
}

# Função para logs de erro
log_error() {
    echo -e "${RED}[✗] $1${NC}"
    exit 1
}

# Função para logs de informação
log_info() {
    echo -e "${YELLOW}[i] $1${NC}"
}

echo -e "\n${YELLOW}=== Iniciando Container Sintoniza ===${NC}\n"

# === Validação de Variáveis de Ambiente ===
log_info "Validando variáveis de ambiente..."

check_env_var() {
    var_name=$1
    if [ -z "${!var_name}" ]; then
        log_error "A variável de ambiente $var_name não está definida ou está vazia."
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

log_success "Todas as variáveis de ambiente obrigatórias estão definidas"

# === Configuração de Variáveis de Ambiente ===
log_info "Configurando arquivo de variáveis de ambiente..."

# Variáveis obrigatórias
echo "DB_HOST=${DB_HOST}" >> /app/.env
echo "DB_USERNAME=${DB_USERNAME}" >> /app/.env
echo "DB_PASSWORD=${DB_PASSWORD}" >> /app/.env
echo "DB_NAME=${DB_NAME}" >> /app/.env
echo "SITE_URL=${SITE_URL}" >> /app/.env
echo "SITE_NAME=${SITE_NAME}" >> /app/.env
echo "ADMIN_PASSWORD=${ADMIN_PASSWORD}" >> /app/.env

log_success "Variáveis de ambiente configuradas"

# === Inicialização dos Serviços ===
echo -e "\n${YELLOW}=== Iniciando serviços ===${NC}\n"

# Iniciando Cron
log_info "Iniciando serviço Cron..."
service cron restart
log_success "Serviço Cron iniciado"

# Funções de verificação de serviços
check_nginx() {
    if ! pgrep nginx > /dev/null; then
        log_error "Falha ao iniciar Nginx"
    else
        log_success "Nginx iniciado com sucesso"
    fi
}

check_php_fpm() {
    if ! pgrep php-fpm > /dev/null; then
        log_error "Falha ao iniciar PHP-FPM"
    else
        log_success "PHP-FPM iniciado com sucesso"
    fi
}

# Diretório PHP-FPM
if [ ! -d /var/run/php ]; then
    log_info "Criando diretório PHP-FPM..."
    mkdir -p /var/run/php
    chown -R www-data:www-data /var/run/php
    log_success "Diretório PHP-FPM criado"
fi

# Iniciando PHP-FPM
log_info "Iniciando PHP-FPM..."
php-fpm &
sleep 3
check_php_fpm

# Verificando configuração Nginx
log_info "Verificando configuração do Nginx..."
nginx -t
if [ $? -ne 0 ]; then
    log_error "Configuração do Nginx inválida"
else
    log_success "Configuração do Nginx válida"
fi

# Iniciando Nginx
log_info "Iniciando Nginx..."
nginx -g "daemon off;" &
sleep 3
check_nginx

echo -e "\n${GREEN}=== Container Sintoniza inicializado ===${NC}\n"

wait -n

exit $?
