FROM php:8.2-cli-alpine

WORKDIR /var/www/html

RUN apk add --no-cache \
        git \
        unzip \
        libzip-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install zip gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --no-scripts

COPY . .

RUN composer dump-autoload --no-dev --optimize \
    && php artisan package:discover --ansi

COPY docker/start.sh /usr/local/bin/start-backend
COPY docker/start-worker.sh /usr/local/bin/start-backend-worker
RUN chmod +x /usr/local/bin/start-backend /usr/local/bin/start-backend-worker

EXPOSE 8000

CMD ["start-backend"]
