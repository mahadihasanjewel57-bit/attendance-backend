FROM php:8.2-cli

WORKDIR /app

COPY . /app

# Install mysqli extension
RUN docker-php-ext-install mysqli

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app"]
