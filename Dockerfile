FROM php:8.2-apache

RUN docker-php-ext-install mysqli

RUN a2dismod mpm_event mpm_worker && a2enmod mpm_prefork rewrite

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html/

EXPOSE 80
