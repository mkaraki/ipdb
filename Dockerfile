FROM composer AS reqs

WORKDIR /var/www/html/

COPY composer.json composer.lock /var/www/html/

RUN composer install --ignore-platform-reqs

FROM php:8.4-apache

RUN  --mount=type=bind,from=mlocati/php-extension-installer:latest,source=/usr/bin/install-php-extensions,target=/usr/local/bin/install-php-extensions \
    install-php-extensions mysqli apcu opcache excimer

RUN <<EOF cat >> $PHP_INI_DIR/conf.d/apcu.ini
[apcu]
apc.enable=1
apc.enable_cli=1
EOF

RUN a2enmod rewrite

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --exclude=vendor . /var/www/html/
COPY _config.dist.php /var/www/html/_config.php
COPY --from=reqs /var/www/html/vendor /var/www/html/vendor
