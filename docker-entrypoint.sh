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
    echo -e "${GREEN}[✓] $1${NC}" | tee -a /dev/stdout
}

# Função para logs de erro
log_error() {
    echo -e "${RED}[✗] $1${NC}" | tee -a /dev/stderr
    exit 1
}

# Função para logs de informação
log_info() {
    echo -e "${YELLOW}[i] $1${NC}" | tee -a /dev/stdout
}

echo -e "\n${YELLOW}=== Iniciando Container Sintoniza ===${NC}\n" | tee -a /dev/stdout

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

# === Configuração de Logs ===
log_info "Configurando sistema de logs..."

# Ensure log directories exist with proper permissions
mkdir -p /var/log/nginx
mkdir -p /var/log/php-fpm
chown -R www-data:www-data /var/log/nginx /var/log/php-fpm

# Configure logrotate for nginx
cat > /etc/logrotate.d/nginx << EOF
/var/log/nginx/*.log {
    daily
    missingok
    rotate 7
    compress
    delaycompress
    notifempty
    create 0640 www-data adm
    sharedscripts
    postrotate
        [ ! -f /var/run/nginx.pid ] || kill -USR1 \`cat /var/run/nginx.pid\`
    endscript
}
EOF

# Configure logrotate for PHP-FPM
cat > /etc/logrotate.d/php-fpm << EOF
/var/log/php-fpm/*.log {
    daily
    missingok
    rotate 7
    compress
    delaycompress
    notifempty
    create 0640 www-data adm
    sharedscripts
    postrotate
        [ ! -f /var/run/php-fpm.pid ] || kill -USR1 \`cat /var/run/php-fpm.pid\`
    endscript
}
EOF

log_success "Sistema de logs configurado"

# === Inicialização dos Serviços ===
echo -e "\n${YELLOW}=== Iniciando serviços ===${NC}\n" | tee -a /dev/stdout

# Iniciando Cron com redirecionamento de logs
log_info "Iniciando serviço Cron..."
service cron restart 2>&1 | logger -t cron
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

# Iniciando PHP-FPM com redirecionamento de logs
log_info "Iniciando PHP-FPM..."
php-fpm --allow-to-run-as-root 2>&1 | logger -t php-fpm &
sleep 3
check_php_fpm

# Verificando configuração Nginx
log_info "Verificando configuração do Nginx..."
nginx -t 2>&1 | logger -t nginx-config
if [ $? -ne 0 ]; then
    log_error "Configuração do Nginx inválida"
else
    log_success "Configuração do Nginx válida"
fi

# Iniciando Nginx em primeiro plano com redirecionamento de logs
log_info "Iniciando Nginx..."
exec nginx -g "daemon off;" 2>&1 | logger -t nginx

echo -e "\n${GREEN}=== Container Sintoniza inicializado ===${NC}\n" | tee -a /dev/stdout

wait -n

exit $?
