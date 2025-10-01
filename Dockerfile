FROM php:8.2-fpm

RUN apt-get update && apt-get install -y zip unzip git libzip-dev             && docker-php-ext-install pdo pdo_mysql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . /var/www/html

RUN composer install --no-interaction --no-dev || true

EXPOSE 9000
CMD ["php-fpm"]
