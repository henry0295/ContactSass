#!/bin/bash

# ContactSass Automated Deployment Script
# Usage: ./deploy.sh [staging|production]

set -e

ENVIRONMENT=${1:-staging}
DEPLOY_DIR="/opt/contactsass"
APP_USER="contactsass"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    log_error "This script must be run as root"
fi

log_info "Starting deployment for $ENVIRONMENT environment..."

# Step 1: Update system packages
log_info "Updating system packages..."
apt-get update -qq
apt-get upgrade -y -qq

# Step 2: Install dependencies if not already installed
log_info "Installing dependencies..."
apt-get install -y -qq \
    curl \
    git \
    wget \
    zip \
    unzip \
    docker.io \
    docker-compose \
    postgresql-client \
    redis-tools

# Step 3: Enable Docker service
log_info "Enabling Docker service..."
systemctl enable docker
systemctl start docker

# Create app user if not exists
if ! id "$APP_USER" &>/dev/null; then
    log_info "Creating application user..."
    useradd -m -s /bin/bash $APP_USER
fi

# Add user to docker group
usermod -aG docker $APP_USER

# Step 4: Clone or update repository
if [ ! -d "$DEPLOY_DIR" ]; then
    log_info "Cloning repository..."
    git clone https://github.com/yourusername/ContactSass.git $DEPLOY_DIR
    chown -R $APP_USER:$APP_USER $DEPLOY_DIR
else
    log_info "Updating repository..."
    cd $DEPLOY_DIR
    sudo -u $APP_USER git fetch origin
    sudo -u $APP_USER git checkout main
    sudo -u $APP_USER git pull origin main
fi

# Step 5: Setup environment file
log_info "Setting up environment configuration..."
cd $DEPLOY_DIR

if [ ! -f .env ]; then
    cp .env.example .env
    
    # Prompt for configuration
    read -p "Enter database name (default: contactsass): " DB_NAME
    DB_NAME=${DB_NAME:-contactsass}
    
    read -p "Enter database user (default: postgres): " DB_USER
    DB_USER=${DB_USER:-postgres}
    
    read -sp "Enter database password: " DB_PASS
    echo
    
    # Generate secure keys
    APP_KEY=$(openssl rand -base64 32)
    
    # Update .env file
    sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
    sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" .env
    sed -i "s/APP_KEY=.*/APP_KEY=base64:$APP_KEY/" .env
    
    # Set environment specific settings
    if [ "$ENVIRONMENT" = "production" ]; then
        sed -i "s/APP_DEBUG=.*/APP_DEBUG=false/" .env
        sed -i "s/APP_ENV=.*/APP_ENV=production/" .env
    else
        sed -i "s/APP_DEBUG=.*/APP_DEBUG=true/" .env
        sed -i "s/APP_ENV=.*/APP_ENV=staging/" .env
    fi
    
    log_info ".env file created successfully"
else
    log_warn ".env file already exists, skipping configuration"
fi

# Step 6: Build Docker images
log_info "Building Docker images..."
sudo -u $APP_USER docker-compose build --no-cache

# Step 7: Start services
log_info "Starting services..."
sudo -u $APP_USER docker-compose up -d

# Step 8: Wait for database connection
log_info "Waiting for database connection..."
for i in {1..30}; do
    if docker exec contactsass_postgres pg_isready -U postgres >/dev/null 2>&1; then
        log_info "Database is ready"
        break
    fi
    if [ $i -eq 30 ]; then
        log_error "Database connection timeout"
    fi
    sleep 1
done

# Step 9: Run migrations
log_info "Running database migrations..."
sudo -u $APP_USER docker-compose exec -T app php artisan migrate --force

# Step 10: Seed database (optional)
log_info "Seeding database..."
sudo -u $APP_USER docker-compose exec -T app php artisan db:seed 2>/dev/null || true

# Step 11: Clear caches
log_info "Clearing application caches..."
sudo -u $APP_USER docker-compose exec -T app php artisan cache:clear
sudo -u $APP_USER docker-compose exec -T app php artisan view:clear
sudo -u $APP_USER docker-compose exec -T app php artisan config:clear

# Step 12: Set permissions
log_info "Setting file permissions..."
sudo -u $APP_USER docker-compose exec -T app chmod -R 755 storage
sudo -u $APP_USER docker-compose exec -T app chmod -R 755 bootstrap/cache

# Step 13: Setup SSL certificate (using Let's Encrypt)
if [ "$ENVIRONMENT" = "production" ]; then
    log_info "Setting up SSL certificate..."
    apt-get install -y -qq certbot python3-certbot-nginx
    
    read -p "Enter domain name (e.g., contactsass.com): " DOMAIN
    
    certbot certonly --standalone --non-interactive --agree-tos \
        --email admin@$DOMAIN -d $DOMAIN
    
    # Update nginx config with SSL
    log_warn "Please manually configure SSL in docker/conf.d/default.conf"
fi

# Step 14: Setup monitoring
log_info "Setting up monitoring..."
cat > /etc/systemd/system/contactsass-healthcheck.service << EOF
[Unit]
Description=ContactSass Health Check
After=network.target

[Service]
Type=simple
User=$APP_USER
ExecStart=/usr/bin/curl -f http://localhost:8000/health || exit 1
Restart=always
RestartSec=60

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable contactsass-healthcheck.service
systemctl start contactsass-healthcheck.service

# Step 15: Setup backups
log_info "Setting up automated backups..."
mkdir -p /backups/contactsass
chown -R $APP_USER:$APP_USER /backups/contactsass

cat > /etc/cron.daily/contactsass-backup << 'CRONEOF'
#!/bin/bash
BACKUP_DIR="/backups/contactsass"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
docker exec contactsass_postgres pg_dump -U postgres contactsass | gzip > $BACKUP_DIR/backup_$TIMESTAMP.sql.gz
find $BACKUP_DIR -mtime +30 -delete  # Keep last 30 days
CRONEOF

chmod +x /etc/cron.daily/contactsass-backup

# Step 16: Verify deployment
log_info "Verifying deployment..."
sleep 5

if curl -f http://localhost:8000/health >/dev/null 2>&1; then
    log_info "✅ Application is healthy"
else
    log_error "Application health check failed"
fi

# Summary
log_info "=========================================="
log_info "Deployment completed successfully!"
log_info "=========================================="
log_info "Environment: $ENVIRONMENT"
log_info "Application URL: http://localhost:8000"
log_info "API URL: http://localhost:8000/api"
log_info "Frontend URL: http://localhost:5173 (dev only)"
log_info ""
log_info "Useful commands:"
log_info "  - View logs: docker-compose logs -f"
log_info "  - Run artisan: docker-compose exec app php artisan <command>"
log_info "  - Access shell: docker-compose exec app sh"
log_info ""
log_info "Next steps:"
log_info "  1. Access the application and verify all features work"
log_info "  2. Configure backup retention policy"
log_info "  3. Setup monitoring alerts"
log_info "  4. Configure email/SMS/Voice provider credentials"
log_info "=========================================="

exit 0
