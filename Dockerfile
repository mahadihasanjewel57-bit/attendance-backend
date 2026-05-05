FROM php:8.2-apache

# Install mysqli only
RUN docker-php-ext-install mysqli

# Disable conflicting MPM modules (FIX FOR YOUR ERROR)
RUN a2dismod mpm_event || true
RUN a2dismod mpm_worker || true
RUN a2enmod mpm_prefork

# Enable rewrite (safe)
RUN a2enmod rewrite

# Copy files
COPY . /var/www/html/

WORKDIR /var/www/html

EXPOSE 80
