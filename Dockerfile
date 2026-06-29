FROM php:8.2-fpm-alpine

RUN docker-php-ext-install mysqli

RUN apk add --no-cache nginx

RUN echo "upload_max_filesize=20M" > /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size=20M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_execution_time=300" >> /usr/local/etc/php/conf.d/uploads.ini

COPY . /var/www/html/
COPY nginx.conf /etc/nginx/nginx.conf

RUN chown -R nobody:nobody /var/www/html/
RUN mkdir -p /run/nginx

EXPOSE 8080

CMD sh -c "php-fpm -D && nginx -g 'daemon off;'"
CMD sh -c "php-fpm -D && nginx -g 'daemon off;' 2>&1"
