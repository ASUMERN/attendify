# Use official PHP image with Apache
FROM php:8.2-apache

# Install SQLite extensions
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    sqlite3 \
    && docker-php-ext-install pdo_sqlite

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy project files into web root
COPY . /var/var/www/html/
COPY . /var/www/html/

# Fix permissions for the SQLite data directory so PHP can write to it
RUN mkdir -p /var/www/html/data && \
    chown -R www-data:www-data /var/www/html/data && \
    chmod -R 775 /var/www/html/data

EXPOSE 80