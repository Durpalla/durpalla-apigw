# syntax=docker/dockerfile:1

FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --ignore-platform-reqs

COPY . .

ENV APP_KEY=base64:ZHVtcHlrZXlmb3Jkb2NrZXJidWlsZG9ubHl5ZWFoCg==

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs \
    && composer clear-cache

FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libxml2-dev \
        libzip-dev \
        oniguruma-dev \
    && apk add --no-cache \
        curl \
        libxml2 \
        libzip \
        oniguruma \
        procps \
        supervisor \
        tesseract-ocr \
        tesseract-ocr-data-eng \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        xml \
        zip \
    && pecl install redis mongodb-1.21.0 \
    && docker-php-ext-enable redis mongodb \
    && apk del .build-deps \
    && rm -rf /tmp/pear /var/cache/apk/*

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html

RUN mkdir -p bootstrap/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

RUN APP_KEY=base64:ZHVtcHlrZXlmb3Jkb2NrZXJidWlsZG9ubHl5ZWFoCg= \
    php artisan package:discover --ansi

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-apigw.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
COPY docker/php/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/entrypoint

EXPOSE 9000

ENTRYPOINT ["entrypoint"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
