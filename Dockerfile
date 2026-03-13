# Base image with PHP 8.2 and extensions
FROM php:8.2-fpm-alpine AS app

# Install system dependencies
RUN apk add --no-cache \
    curl \
    git \
    zip \
    unzip \
    postgresql-dev \
    redis \
    npm \
    && docker-php-ext-install pdo pdo_pgsql \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy application code
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Copy environment file
COPY .env.example .env

# Generate app key (Laravel)
RUN php artisan key:generate 2>/dev/null || true

# Set permissions
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Frontend build stage
FROM node:18-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# Final stage - Production image
FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    redis \
    nginx \
    supervisor \
    curl

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql \
    && pecl install redis \
    && docker-php-ext-enable redis

# Copy Composer and dependencies from app stage
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY --from=app /app /app

# Copy frontend build from frontend stage
COPY --from=frontend /app/dist /app/public/dist

WORKDIR /app

# Copy nginx config
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/conf.d /etc/nginx/conf.d

# Copy supervisor config
COPY docker/supervisord.conf /etc/supervisord.conf

# Create necessary directories
RUN mkdir -p /app/storage/logs \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Expose ports
EXPOSE 80 9000

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Start supervisor (manages PHP-FPM, nginx, queue workers)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
