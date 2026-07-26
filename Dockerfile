# PF-010 Docker Development Environment
#
# Development-only PHP-FPM image for the OneLegalPro Laravel application.
# Not intended for production deployment (see docs/PROJECT_STATUS.md and README.md).
#
# Base image is multi-arch (linux/amd64 and linux/arm64), so this builds
# natively on both Apple Silicon and amd64 hosts without a hardcoded platform.
#
# composer.json declares "php": "^8.3" (>=8.3, <9.0). composer.lock (the
# authoritative lock file) actually resolved several symfony/* packages that
# require PHP >=8.4.1, so 8.4 is the minimum version within the ^8.3 range
# that composer.lock will actually install on. See PF-010 completion report.
FROM php:8.4-fpm-bookworm

# System packages required to compile the PHP extensions this application needs:
# - libpq-dev: PostgreSQL client headers for pdo_pgsql/pgsql (PostgreSQL is the
#   authoritative database per docs/architecture and config/database.php).
# - libzip-dev/unzip: required by the zip extension and by Composer archive installs.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libzip-dev \
        unzip \
        git \
    && docker-php-ext-install pdo_pgsql pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Composer, copied from the official Composer image rather than installed via a
# downloaded install script.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

# Deterministic install from the authoritative composer.lock. No --no-dev: this
# is a development image and needs PHPUnit, Pint, Faker, etc.
RUN composer install --no-interaction --no-progress --prefer-dist \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/php.dev.ini /usr/local/etc/php/conf.d/99-onelegalpro-dev.ini

# The php-fpm master process starts as root (required to bind and manage worker
# processes) but the built-in pool config (www.conf) already runs all PHP
# request-handling workers as the non-root www-data user/group.
EXPOSE 9000

CMD ["php-fpm"]
