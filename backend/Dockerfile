# PHP 8.4 : minimum exigé par les bundles Doctrine de composer.lock.
FROM php:8.4-fpm-alpine

# Extensions systeme requises par Symfony + Doctrine MySQL
RUN apk add --no-cache \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
    && docker-php-ext-install \
        intl \
        pdo_mysql \
        zip \
        opcache \
        mbstring \
    && rm -rf /var/cache/apk/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Environnement de production dès le build, sinon cache:clear charge les bundles de dev.
ENV APP_ENV=prod \
    APP_DEBUG=0

# Dependances d'abord (cache Docker)
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Code applicatif
COPY . .

# .env est exclu de l'image ; Symfony l'exige, les variables de Compose l'emportent.
RUN cp .env.example .env

# Scripts post-install (cache:clear, assets:install)
RUN composer run-script post-install-cmd --no-interaction \
    && chown -R www-data:www-data var/

# Clés JWT générées au démarrage par l'entrypoint, pas dans l'image.
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
