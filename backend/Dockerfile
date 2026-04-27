FROM php:8.2-cli

WORKDIR /var/www/html

# System dependencies for Laravel + OCR helpers.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        python3 \
        python3-pip \
    && docker-php-ext-install pdo_mysql zip bcmath pcntl intl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy manifests first for better layer caching.
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --prefer-dist --no-progress || true

COPY package.json package-lock.json* ./
COPY scripts/python/requirements.txt /tmp/requirements.txt
RUN pip3 install --no-cache-dir -r /tmp/requirements.txt || true

COPY . .

EXPOSE 8000

CMD ["sh", "-lc", "composer install --no-interaction --prefer-dist --no-progress && php artisan key:generate --force && php artisan serve --host=0.0.0.0 --port=8000"]
