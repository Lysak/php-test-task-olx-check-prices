FROM php:8.5-fpm-bookworm AS backend

RUN apt update && apt install -y \
    zip  \
    unzip  \
    git  \
    curl  \
    libzip-dev  \
    libicu-dev  \
    zsh  \
    vim  \
    iputils-ping \
    procps \
    supervisor \
    netcat-traditional \
    gnupg \
    nano

RUN docker-php-ext-install pdo_mysql zip intl

WORKDIR /app

COPY . .

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN composer install

# Build frontend
FROM node:24 AS frontend

WORKDIR /app

COPY . .

COPY --from=backend /app/vendor /app/vendor

RUN corepack enable > /dev/null 2>&1
RUN rm -rf node_modules > /dev/null 2>&1
RUN pnpm install > /dev/null 2>&1
RUN pnpm run build > /dev/null 2>&1

# Build backend
FROM php:8.5-fpm-bookworm

RUN apt update && apt install -y \
    zip  \
    unzip  \
    git  \
    curl  \
    libzip-dev  \
    libicu-dev  \
    zsh  \
    vim  \
    iputils-ping \
    procps \
    supervisor \
    netcat-traditional \
    gnupg \
    nano \
    libssl-dev \
    pkg-config \
    libonig-dev \
    && pecl install redis \
    && docker-php-ext-enable redis

RUN docker-php-ext-install pdo_mysql zip intl

WORKDIR /var/www

COPY . .

# copy php.ini inside the image
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

COPY docker/entrypoint.sh /var/docker/entrypoint.sh
RUN chmod +x /var/docker/entrypoint.sh

COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN composer install

COPY --from=frontend /app/public /var/www/public

EXPOSE 9000
