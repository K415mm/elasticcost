# Stage 1: Build Frontend Assets
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# Stage 2: PHP Application
FROM php:8.4-fpm-alpine

# Install system dependencies & PHP extensions
RUN apk add --no-cache \
    curl \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    postgresql-dev \
    icu-dev \
    oniguruma-dev \
    bash \
    su-exec

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        gd \
        zip \
        bcmath \
        opcache \
        intl \
        mbstring \
        pcntl \
        sockets

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application code
COPY . /var/www

# Copy compiled assets from frontend stage
COPY --from=frontend /app/public/build /var/www/public/build

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Configure permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
