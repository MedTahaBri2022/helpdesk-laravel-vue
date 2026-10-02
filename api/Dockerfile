# Development image: PHP CLI with the extensions Laravel needs, plus Composer.
# The source code is mounted by docker-compose, not copied.
FROM php:8.3-cli-alpine

RUN apk add --no-cache git unzip \
    && docker-php-ext-install pdo_mysql bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Compiled PHP is cached between requests: without it every request re-parses
# the whole framework.
RUN docker-php-ext-install opcache
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache-dev.ini

WORKDIR /app
EXPOSE 8000

CMD ["sh", "docker/entrypoint.sh"]
