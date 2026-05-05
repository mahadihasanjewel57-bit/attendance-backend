FROM php:8.2-apache

# Install mysqli extension
RUN docker-php-ext-install mysqli

# Enable rewrite (safe for APIs)
RUN a2enmod rewrite

# Copy project files
COPY . /var/www/html/

# Ensure permissions
RUN chown -R www-data:www-data /var/www/html
