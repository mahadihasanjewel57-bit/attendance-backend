FROM php:8.2-cli

WORKDIR /app
COPY . /app

RUN docker-php-ext-install mysqli

EXPOSE 8080

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t /app"]
