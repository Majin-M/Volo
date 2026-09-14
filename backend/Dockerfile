# PHP 8.4 et non 8.2, et ce n'est pas un confort.
#
# composer.lock verrouille doctrine/doctrine-bundle ^8.4 et
# doctrine/doctrine-migrations-bundle ^8.4 : sous 8.2, l'etape
# `composer install --no-dev` ci-dessous ECHOUE. Cette image ne se
# construisait donc pas, ce qui n'avait jamais ete constate faute de
# deploiement. Verifiable par `composer why-not php 8.2`.
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

# Environnement de production impose DES LA CONSTRUCTION.
#
# Le `APP_ENV: prod` de docker-compose.yml n'existe qu'a l'EXECUTION. Au build,
# Symfony lisait le `.env` copie depuis le poste (APP_ENV=dev), chargeait donc
# DebugBundle — que `--no-dev` n'a pas installe — et `cache:clear` echouait :
# "Class Symfony\Bundle\DebugBundle\DebugBundle not found".
ENV APP_ENV=prod \
    APP_DEBUG=0

# Dependances d'abord (cache Docker)
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Code applicatif
COPY . .

# `.env` est exclu de l'image (.dockerignore) : on le reconstitue depuis le
# modele versionne, exactement comme la CI. Symfony exige ce fichier pour
# demarrer, mais ses valeurs ne sont que des replis : les variables injectees
# par docker-compose.yml a l'execution l'emportent toujours sur `.env`.
RUN cp .env.example .env

# Scripts post-install (cache:clear, assets:install)
RUN composer run-script post-install-cmd --no-interaction \
    && chown -R www-data:www-data var/

# Les cles JWT ne sont plus generees ICI, et c'est un correctif, pas un
# deplacement de confort.
#
# L'ancienne etape faisait `openssl ... -pass pass:${JWT_PASSPHRASE:-...}`
# dans un `RUN`, sans qu'aucun `ARG JWT_PASSPHRASE` ne soit declare : au
# moment du build cette variable est TOUJOURS vide, donc le repli etait
# systematiquement utilise. Compose injecte la vraie passphrase a
# l'EXECUTION — le jour ou quelqu'un en definit une, elle ne correspond plus
# a la cle scellee dans l'image et la signature JWT casse.
#
# Elles sont desormais generees par docker-entrypoint.sh, au demarrage, et
# vivent dans un volume : une reconstruction d'image ne les change plus.
# Auparavant, chaque reconstruction deconnectait tous les utilisateurs.

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
