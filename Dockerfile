FROM php:8.4-cli
RUN docker-php-ext-install pdo_mysql
WORKDIR /app
COPY . /app
EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/router.php"]
