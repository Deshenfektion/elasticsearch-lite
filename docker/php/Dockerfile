FROM composer:2.8 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader

FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY web ./web
RUN npm run build

FROM php:8.4-fpm-alpine

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql opcache \
    && pecl install apcu-5.1.24 \
    && docker-php-ext-enable apcu \
    && apk del .build-deps

COPY docker/php/php.ini /usr/local/etc/php/conf.d/eslite.ini

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY composer.json composer.lock ./
COPY bin ./bin
COPY config ./config
COPY database ./database
COPY src ./src
COPY public ./public
COPY --from=assets /app/public/assets ./public/assets
COPY --from=assets /app/public/index.html ./public/index.html
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint bin/console \
    && chown -R www-data:www-data /app

EXPOSE 9000

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
