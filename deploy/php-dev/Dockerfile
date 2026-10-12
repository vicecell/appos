# Pinned PHP toolchain for local checks and CI parity. Dev only; not a production image.
FROM php:8.3-cli-alpine
RUN apk add --no-cache git unzip \
    && docker-php-ext-install pdo_mysql
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
