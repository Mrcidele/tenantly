# syntax=docker/dockerfile:1.7

############################
# Base: FrankenPHP + PHP 8.4
############################
FROM dunglas/frankenphp:1-php8.4 AS base

RUN install-php-extensions \
        pdo_pgsql pgsql redis pcntl intl zip bcmath opcache sockets \
    && apt-get update \
    && apt-get install -y --no-install-recommends postgresql-client unzip git \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini $PHP_INI_DIR/conf.d/zz-tenantly.ini

WORKDIR /app

############################
# Desenvolvimento
############################
FROM base AS dev

RUN install-php-extensions xdebug
ENV XDEBUG_MODE=off
CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8000", "--watch"]

############################
# Assets do frontend
############################
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY resources ./resources
COPY vite.config.* tsconfig*.json ./
RUN npm run build

############################
# Produção
############################
FROM base AS prod

ENV APP_ENV=production APP_DEBUG=false
COPY docker/php/php.prod.ini $PHP_INI_DIR/conf.d/zz-tenantly-prod.ini

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 8000
HEALTHCHECK --interval=10s --timeout=3s --retries=5 CMD php -r "exit(@file_get_contents('http://127.0.0.1:8000/up') === false ? 1 : 0);"
CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8000", "--workers=auto", "--max-requests=1000"]
