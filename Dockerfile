FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql
RUN a]2enmod rewrite

COPY . /var/www/html/
COPY public/ /var/www/html/

RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
