#!/bin/sh
# Development entrypoint: gets a fresh checkout to a running API.
set -e

[ -f .env ] || cp .env.example .env
# `vendor` is a Docker volume (see docker-compose.yml), empty on first start.
[ -f vendor/autoload.php ] || composer install --no-interaction --prefer-dist --optimize-autoloader

# Generated once, kept in the (git-ignored) .env file.
grep -q '^APP_KEY=base64' .env || php artisan key:generate --no-interaction

php artisan migrate --force

# PHP's built-in server: fine for development, not for production
# (use php-fpm behind nginx or FrankenPHP there).
# --no-reload matters here: without it `artisan serve` hides the container's
# environment from the web process, which then falls back to the .env file
# (SQLite) while the migrations above ran on MySQL.
exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
