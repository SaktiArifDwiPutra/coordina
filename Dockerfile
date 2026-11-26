FROM dunglas/frankenphp:php8.3

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    libpq-dev \
    libzip-dev \
    libreoffice-writer \
    fonts-liberation \
    fonts-dejavu \
    nodejs \
    npm \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_pgsql zip

COPY . .

WORKDIR /app/backend

RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer

RUN composer install --no-interaction --prefer-dist --no-dev

WORKDIR /app/frontend

RUN npm install
RUN npm run build

WORKDIR /app/backend

RUN chmod -R 775 storage bootstrap/cache

EXPOSE 8080

CMD php artisan serve --host=0.0.0.0 --port=${PORT:-8080}