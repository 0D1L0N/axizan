FROM php:8.4-fpm

WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    libzip-dev \
    libsqlite3-dev \
    zip \
    unzip \
    && docker-php-ext-install \
    pdo_pgsql \
    pdo_sqlite \
    zip \
    && rm -rf /var/lib/apt/lists/*

# Note sur Redis : le cache et la file passent par Redis, mais l'extension
# native phpredis n'est pas installee ici — le canal PECL renvoie des
# metadonnees invalides (« Package "redis" does not have REST info xml
# available »), y compris apres channel-update.
#
# On utilise donc predis/predis, client ecrit en PHP pur, declare dans
# composer.json et selectionne par REDIS_CLIENT=predis. Il est plus lent que
# phpredis, sans consequence a ce volume. Pour repasser sur l'extension le
# jour ou PECL refonctionne : installer l'extension ici et remettre
# REDIS_CLIENT=phpredis.

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application
COPY . .

# Laravel requires these writable directories while Composer discovers packages.
RUN mkdir -p bootstrap/cache storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs

# Install dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/storage \
    && chmod -R 755 /app/bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
