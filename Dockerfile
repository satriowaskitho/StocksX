# Stage 1: Node.js build for frontend assets
FROM node:20-alpine AS node-builder

WORKDIR /app

# Copy package files
COPY package*.json ./

# Install Node dependencies
RUN npm install

# Copy source files needed for Vite build
COPY . .

# Build Vite assets (this compiles Tailwind CSS)
RUN npm run build

# Stage 2: Composer dependencies
FROM serversideup/php:8.3-cli AS composer-builder

USER root

# Install intl extension (required for Filament during composer install)
RUN install-php-extensions intl

WORKDIR /app

# Copy application files FIRST
COPY . .

# Create Laravel storage directories with correct structure
RUN mkdir -p storage/framework/cache/data \
    && mkdir -p storage/framework/sessions \
    && mkdir -p storage/framework/views \
    && mkdir -p storage/logs \
    && mkdir -p bootstrap/cache

# Install Composer dependencies without running scripts first
RUN composer install --no-interaction --optimize-autoloader --no-dev --prefer-dist --no-scripts

# Now run package discovery manually (after directories exist)
RUN php artisan package:discover --ansi || true

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# Stage 3: Production image
FROM serversideup/php:8.3-fpm-nginx

ENV PHP_OPCACHE_ENABLE=1

USER root

# Install intl extension (required for Filament at runtime)
RUN install-php-extensions intl

# Set working directory
WORKDIR /var/www/html

# Copy application files from composer-builder stage
COPY --chown=www-data:www-data --from=composer-builder /app /var/www/html

# Copy built Vite assets from node-builder stage
COPY --chown=www-data:www-data --from=node-builder /app/public/build /var/www/html/public/build

# Set comprehensive permissions for Laravel
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \;

# Switch to www-data user
USER www-data

# Expose port (serversideup/php:8.3-fpm-nginx uses 8080 by default)
EXPOSE 8080