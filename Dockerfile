FROM php:8.2-fpm-alpine

RUN docker-php-ext-install mysqli

RUN apk add --no-cache nginx

COPY . /var/www/html/
COPY nginx.conf /etc/nginx/nginx.conf

RUN chown -R nobody:nobody /var/www/html/
RUN mkdir -p /run/nginx

EXPOSE 8080

CMD sh -c "php-fpm -D && nginx -g 'daemon off;'"
