#!/bin/bash
set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_success() {
    echo -e "${GREEN}[✓] $1${NC}"
}

log_error() {
    echo -e "${RED}[✗] $1${NC}"
    exit 1
}

log_info() {
    echo -e "${YELLOW}[i] $1${NC}"
}

check_nginx() {
    log_info "Checking Nginx process status..."
    local max_attempts=5
    local attempt=0

    while [ $attempt -lt $max_attempts ]; do
        if pgrep nginx > /dev/null; then
            log_success "Nginx started successfully"
            return 0
        fi
        
        log_info "Waiting for Nginx to start... (Attempt $((attempt+1))/$max_attempts)"
        sleep 3
        attempt=$((attempt+1))
    done

    log_error "Failed to start Nginx after $max_attempts attempts"
}

check_php_fpm() {
    log_info "Checking PHP-FPM process status..."
    local max_attempts=5
    local attempt=0

    while [ $attempt -lt $max_attempts ]; do
        if pgrep php-fpm > /dev/null; then
            log_success "PHP-FPM started successfully"
            return 0
        fi
        
        log_info "Waiting for PHP-FPM to start... (Attempt $((attempt+1))/$max_attempts)"
        sleep 3
        attempt=$((attempt+1))
    done

    log_error "Failed to start PHP-FPM after $max_attempts attempts"
}

check_redis() {
    log_info "Checking Redis connection..."
    local max_attempts=5
    local attempt=0
    local redis_host=${REDIS_HOST:-localhost}
    local redis_port=${REDIS_PORT:-6379}
    local redis_password=${REDIS_PASSWORD:-""}

    while [ $attempt -lt $max_attempts ]; do
        if [ -n "$redis_password" ]; then
            if redis-cli -h $redis_host -p $redis_port -a $redis_password ping 2>/dev/null | grep -q "PONG"; then
                log_success "Redis connection successful"
                return 0
            fi
        else
            if redis-cli -h $redis_host -p $redis_port ping 2>/dev/null | grep -q "PONG"; then
                log_success "Redis connection successful"
                return 0
            fi
        fi
        
        log_info "Redis not responding... (Attempt $((attempt+1))/$max_attempts)"
        
        if [ "$redis_host" = "localhost" ] || [ "$redis_host" = "127.0.0.1" ]; then
            log_info "Attempting to start Redis locally..."
            if command -v redis-server >/dev/null 2>&1; then
                redis-server --daemonize yes
                log_info "Redis server started"
            else
                log_info "Redis server not installed, cannot start automatically"
            fi
        fi
        
        sleep 3
        attempt=$((attempt+1))
    done

    log_error "Failed to connect to Redis after $max_attempts attempts"
}

echo -e "\n${YELLOW}Lerama: Starting${NC}\n"

# Set timezone
if [ -n "$TZ" ]; then
    log_info "Setting timezone to $TZ..."
    ln -snf /usr/share/zoneinfo/$TZ /etc/localtime
    echo $TZ > /etc/timezone
    
    echo "date.timezone = $TZ" > /usr/local/etc/php/conf.d/timezone.ini
    log_success "Timezone set to $TZ for both system and PHP"
else
    log_info "No TZ environment variable set, using UTC as default timezone"

    ln -snf /usr/share/zoneinfo/UTC /etc/localtime
    echo "UTC" > /etc/timezone
    
    echo "date.timezone = UTC" > /usr/local/etc/php/conf.d/timezone.ini
    log_success "Timezone set to UTC for both system and PHP"
fi

# Create or update .env file with environment variables
log_info "Setting up environment variables in /app/.env..."
cat > /app/.env << EOL
APP_NAME=Lerama
APP_URL=${APP_URL:-https://lerama.lab}
DB_HOST=${MYSQL_HOST:-localhost}
DB_PORT=${MYSQL_PORT:-3306}
DB_NAME=${MYSQL_DATABASE:-lerama}
DB_USER=${MYSQL_USERNAME:-root}
DB_PASS=${MYSQL_PASSWORD:-root}
ADMIN_USERNAME=${ADMIN_USERNAME:-admin}
ADMIN_PASSWORD=${ADMIN_PASSWORD:-admin}
REDIS_HOST=${REDIS_HOST:-localhost}
REDIS_PORT=${REDIS_PORT:-6379}
REDIS_PASSWORD=${REDIS_PASSWORD:-}
EOL

log_success "Environment variables set in /app/.env"

log_info "Starting PHP-FPM..."
php-fpm &
check_php_fpm

# Ensure /app/storage directory exists and has correct permissions
log_info "Checking /app/public/storage directory..."
if [ ! -d /app/public/storage ]; then
    log_info "Creating /app/public/storage directory..."
    mkdir -p /app/public/storage
    log_success "/app/public/storage directory created"
fi

# Database
log_info "Checking database tables..."
php /setup/check-db.php

# NGINX
log_info "Checking Nginx configuration..."
nginx -t
if [ $? -ne 0 ]; then
    log_error "Invalid Nginx configuration"
else
    log_success "Valid Nginx configuration"
fi

log_info "Starting Nginx..."
nginx -g "daemon off;" &
check_nginx

# Cron
log_info "Setting up cron jobs..."

# Create a directory for cron scripts
mkdir -p /app/cron-scripts
chmod 755 /app/cron-scripts

cat > /app/cron-scripts/feed_process.sh << 'EOL'
#!/bin/bash
echo "$(date): Starting feed:process cron job"
/usr/local/bin/php /app/bin/lerama feed:process 2>&1 | sed "s/^/[feed:process] /"
echo "$(date): Finished feed:process cron job"
EOL

# Make the scripts executable
chmod +x /app/cron-scripts/*.sh

# Set up crontab to use the wrapper scripts and redirect output to Docker logs
(
    echo "0 * * * * /app/cron-scripts/feed_process.sh >> /proc/1/fd/1 2>> /proc/1/fd/2"
) | crontab -

service cron restart

log_success "Cron jobs added with stdout logging"

# Set correct permissions for /app/storage
log_info "Setting permissions for /app/public/storage..."
chown -R www-data:www-data /app/public/storage
chmod -R 755 /app/public/storage
log_success "Permissions set for /app/public/storage"

# PHP-FPM
if [ ! -d /var/run/php ]; then
    log_info "Creating PHP-FPM directory..."
    mkdir -p /var/run/php
    chown -R www-data:www-data /var/run/php
    log_success "PHP-FPM directory created"
fi

# Check Redis connection
log_info "Checking Redis service..."
check_redis

echo -e "\n${GREEN}Lerama: Initialized ===${NC}\n"

wait -n

