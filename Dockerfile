FROM php:8.2-apache

# Remove conflicting MPM modules
RUN a2dismod mpm_event || true
RUN a2dismod mpm_worker || true
RUN a2enmod mpm_prefork || true

# Install mysqli
RUN docker-php-ext-install mysqli

# Copy project
COPY . /var/www/html/
