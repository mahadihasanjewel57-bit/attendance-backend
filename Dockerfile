FROM php:8.2-apache

# Install required PHP extension
RUN docker-php-ext-install mysqli

# Enable only safe modules (NO MPM changes)
RUN a2enmod rewrite

# Copy project files
COPY . /var/www/html/

# Fix permissions
RUN chown -R www-data:www-data /var/www/html
