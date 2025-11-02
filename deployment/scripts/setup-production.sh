#!/bin/bash

# VoIP Platform Production Setup Script
set -e

echo "Setting up VoIP Platform Production Environment..."

# Variables
APP_DIR="/var/www/freepbxapiportal"
NGINX_CONF="/etc/nginx/sites-available/voip-platform"
PHP_FPM_CONF="/etc/php/8.2/fpm/pool.d/voip-platform.conf"
REDIS_CONF_DIR="/etc/redis"

# Update system packages
echo "Updating system packages..."
apt-get update && apt-get upgrade -y

# Install required packages
echo "Installing required packages..."
apt-get install -y nginx php8.2-fpm php8.2-mysql php8.2-redis php8.2-curl \
    php8.2-json php8.2-mbstring php8.2-xml php8.2-zip php8.2-gd \
    php8.2-intl php8.2-bcmath redis-server mysql-client supervisor \
    certbot python3-certbot-nginx

# Copy Nginx configuration
echo "Configuring Nginx..."
cp $APP_DIR/deployment/nginx/freepbx-voip-platform.conf $NGINX_CONF
ln -sf $NGINX_CONF /etc/nginx/sites-enabled/
nginx -t

# Copy PHP-FPM configuration
echo "Configuring PHP-FPM..."
cp $APP_DIR/deployment/php-fpm/voip-platform.conf $PHP_FPM_CONF

# Setup Redis Cluster
echo "Setting up Redis cluster..."
mkdir -p $REDIS_CONF_DIR
cp $APP_DIR/deployment/redis/redis-cluster.conf $REDIS_CONF_DIR/
cp $APP_DIR/deployment/redis/redis-7001.conf $REDIS_CONF_DIR/
cp $APP_DIR/deployment/redis/redis-7002.conf $REDIS_CONF_DIR/

# Create Redis systemd services
for port in 7000 7001 7002; do
    cat > /etc/systemd/system/redis-$port.service << EOF
[Unit]
Description=Redis In-Memory Data Store (Port $port)
After=network.target

[Service]
User=redis
Group=redis
ExecStart=/usr/bin/redis-server /etc/redis/redis-$port.conf
ExecStop=/usr/bin/redis-cli -p $port shutdown
Restart=always

[Install]
WantedBy=multi-user.target
EOF
done

# Set proper permissions
echo "Setting file permissions..."
chown -R www-data:www-data $APP_DIR
chmod -R 755 $APP_DIR
chmod -R 775 $APP_DIR/storage
chmod -R 775 $APP_DIR/bootstrap/cache

# Install Composer dependencies
echo "Installing Composer dependencies..."
cd $APP_DIR
sudo -u www-data composer install --no-dev --optimize-autoloader

# Generate application key
echo "Generating application key..."
sudo -u www-data php artisan key:generate --force

# Run database migrations
echo "Running database migrations..."
sudo -u www-data php artisan migrate --force

# Cache configuration
echo "Caching configuration..."
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# Start services
echo "Starting services..."
systemctl daemon-reload
systemctl enable nginx php8.2-fpm redis-7000 redis-7001 redis-7002
systemctl start nginx php8.2-fpm redis-7000 redis-7001 redis-7002

# Initialize Redis cluster
echo "Initializing Redis cluster..."
sleep 5
redis-cli --cluster create 127.0.0.1:7000 127.0.0.1:7001 127.0.0.1:7002 \
    --cluster-replicas 0 --cluster-yes

# Setup SSL certificate (Let's Encrypt)
echo "Setting up SSL certificate..."
certbot --nginx -d voip-platform.local --non-interactive --agree-tos \
    --email admin@voip-platform.local || echo "SSL setup skipped - configure manually"

# Setup log rotation
echo "Setting up log rotation..."
cat > /etc/logrotate.d/voip-platform << EOF
/var/log/nginx/voip-platform.*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 www-data adm
    postrotate
        systemctl reload nginx
    endscript
}

$APP_DIR/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
EOF

echo "Production environment setup completed!"
echo "Please update the following:"
echo "1. Configure SSL certificates"
echo "2. Update database credentials in .env"
echo "3. Configure Redis cluster password"
echo "4. Set up monitoring and backups"