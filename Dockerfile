FROM php:8.2-cli

WORKDIR /app
COPY . /app

RUN docker-php-ext-install mysqli

CMD php -S 0.0.0.0:$PORT -t /app
