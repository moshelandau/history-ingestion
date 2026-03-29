FROM php:8.4-cli-alpine

# Install system dependencies
RUN apk add --no-cache git

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install dependencies first (layer caching)
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader

# Copy application code
COPY . .

ENTRYPOINT ["php"]
CMD ["bin/pipeline", "--help"]
