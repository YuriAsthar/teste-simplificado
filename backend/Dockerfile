FROM php:8.4-fpm

# Accept host UID/GID so the container runtime user matches the file owner
ARG UID=1000
ARG GID=1000

# Install system dependencies for PostgreSQL, zip, RabbitMQ, Kafka, and other extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip \
    git \
    curl \
    netcat-openbsd \
    librdkafka-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql pgsql zip sockets

# Install Redis extension via PECL
RUN pecl install redis && docker-php-ext-enable redis

# Install rdkafka extension via PECL
RUN pecl install rdkafka && docker-php-ext-enable rdkafka

# Copy Composer from official Composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Align www-data user/group with the host UID/GID so volume writes keep host ownership
RUN groupmod -g ${GID} www-data && usermod -u ${UID} -g ${GID} www-data \
    && mkdir -p storage bootstrap/cache \
    && chown -R www-data:www-data /var/www/html

# Switch to non-root user for FPM
USER www-data

# Expose artisan serve port
EXPOSE 8000

CMD ["php-fpm"]
