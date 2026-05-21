FROM dunglas/frankenphp:php8.3

WORKDIR /app

COPY . .

RUN apt-get update && apt-get install -y \
    git unzip zip curl libpq-dev nodejs npm

RUN docker-php-ext-install pdo pdo_pgsql

RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer

RUN composer install --no-interaction --prefer-dist

RUN npm install
RUN npm run build

RUN mkdir -p storage/framework/{sessions,views,cache,testing} \
    storage/logs bootstrap/cache

RUN chmod -R 777 storage bootstrap/cache

EXPOSE 8000

CMD php artisan migrate --force && php artisan octane:frankenphp --host=0.0.0.0 --port=$PORT