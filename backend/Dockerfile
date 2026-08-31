FROM php:8.2-fpm-alpine

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

# Dependances d'abord (cache Docker)
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Code applicatif
COPY . .

# Scripts post-install (cache:clear, assets:install)
RUN composer run-script post-install-cmd --no-interaction \
    && chown -R www-data:www-data var/

# Generer les cles JWT si absentes
RUN mkdir -p config/jwt \
    && if [ ! -f config/jwt/private.pem ]; then \
        openssl genpkey -algorithm RSA -out config/jwt/private.pem -aes256 \
            -pass pass:${JWT_PASSPHRASE:-VoLoJwT2026!} 2>/dev/null; \
        openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem \
            -passin pass:${JWT_PASSPHRASE:-VoLoJwT2026!} 2>/dev/null; \
        chown www-data:www-data config/jwt/*.pem; \
    fi

EXPOSE 9000

CMD ["php-fpm"]
