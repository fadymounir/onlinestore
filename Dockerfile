FROM php:8.4-cli

WORKDIR /var/www

# ---- OS packages -----------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl zip unzip ca-certificates \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
        libonig-dev libxml2-dev libzip-dev libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# ---- PHP extensions (Laravel 13 + Filament 5) ------------------------------
RUN docker-php-ext-configure gd --with-jpeg --with-freetype --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring zip bcmath gd intl exif pcntl opcache \
    && pecl install redis \
    && docker-php-ext-enable redis

# ---- Node 20 (Vite / Tailwind asset builds) --------------------------------
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# ---- Composer --------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# ---- PHP ini tuned for an uploads-heavy store dashboard --------------------
RUN { \
      echo "upload_max_filesize=64M"; \
      echo "post_max_size=64M"; \
      echo "memory_limit=512M"; \
      echo "max_execution_time=120"; \
    } > /usr/local/etc/php/conf.d/99-app.ini

# ---- SQLite, for the in-memory test database (phpunit.xml) ----------------
RUN apt-get update && apt-get install -y --no-install-recommends libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Source is bind-mounted from the host in docker-compose.yml, so no COPY here:
# the mount would shadow it anyway. Dependencies are installed via `composer
# install` inside the running container.

ARG APP_PORT=9000
ENV APP_PORT=${APP_PORT}
EXPOSE ${APP_PORT}

CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=${APP_PORT}"]
