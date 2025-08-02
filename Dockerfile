FROM php:8.4-apache

RUN apt-get update && \
    apt-get install -y libpq-dev zlib1g-dev && \
    
    docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql&& \
    docker-php-ext-configure gd && \
    docker-php-ext-install pdo pdo_pgsql pgsql gd && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

RUN pecl install apcu \
    && docker-php-ext-install opcache \
    && docker-php-ext-enable apcu

RUN <<EOF cat >> $PHP_INI_DIR/conf.d/apcu.ini
[apcu]
apc.enable=1
apc.enable_cli=1
EOF

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY wwwroot/ /var/www/html/
