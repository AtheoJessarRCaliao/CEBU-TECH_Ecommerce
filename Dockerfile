FROM php:8.3-cli-alpine

# Install SQLite PDO extension and build dependencies
RUN apk add --no-cache sqlite-dev \
    && docker-php-ext-install pdo pdo_sqlite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Ensure storage and upload directories exist and are writable
RUN mkdir -p storage public/assets/images \
    && chmod -R 777 storage public/assets/images

# Default port for Render
EXPOSE 10000

# Auto-seed database on start and run the PHP server on Render's dynamic $PORT
CMD ["sh", "-c", "php scripts/seed_and_assets.php --reset-db && php -S 0.0.0.0:${PORT:-10000} -t public"]
