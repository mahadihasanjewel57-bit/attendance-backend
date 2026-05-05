FROM php:8.2-apache

# Enable mysqli
RUN docker-php-ext-install mysqli

# Enable Apache rewrite (safe)
RUN a2enmod rewrite

# Copy project
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
