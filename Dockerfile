FROM dunglas/frankenphp:php8.3

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    libpq-dev \
    nodejs \
    npm

RUN docker-php-ext-install pdo pdo_pgsql

COPY . .

RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer

RUN composer install --no-interaction --prefer-dist --no-dev

WORKDIR /app/coordina-frontend

RUN npm install
RUN npm run build

WORKDIR /app

RUN chmod -R 775 storage bootstrap/cache

EXPOSE 8080

CMD php artisan serve --host=0.0.0.0 --port=${PORT:-8080}