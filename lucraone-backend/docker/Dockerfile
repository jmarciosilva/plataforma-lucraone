FROM php:8.3-fpm-alpine

WORKDIR /app

RUN apk add --no-cache \
    curl \
    git \
    mysql-client \
    unzip \
    libzip-dev \
    zip \
    nginx \
    supervisor \
    autoconf \
    g++ \
    make

RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    zip \
    bcmath \
    ctype \
    fileinfo

RUN pecl install redis && docker-php-ext-enable redis

RUN apk del --no-cache autoconf g++ make

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /app

RUN composer install --no-interaction --no-plugins --no-scripts

RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0"]
