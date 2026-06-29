FROM php:8.4-fpm

# Install system dependencies for PostgreSQL, zip, and other extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql pgsql zip

# Install Redis extension via PECL
RUN pecl install redis && docker-php-ext-enable redis

# Copy Composer from official Composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Create storage and bootstrap/cache directories with proper permissions
RUN mkdir -p storage bootstrap/cache && chown -R www-data:www-data /var/www/html

# Switch to non-root user for FPM
USER www-data

# Expose PHP-FPM port
EXPOSE 9000

CMD ["php-fpm"]
