FROM php:8.2-cli

# Install mysqli
RUN docker-php-ext-install mysqli

# Copy project
COPY . /app

WORKDIR /app

# Start PHP built-in server (NO APACHE = NO MPM ERROR)
CMD ["php", "-S", "0.0.0.0:8080"]
