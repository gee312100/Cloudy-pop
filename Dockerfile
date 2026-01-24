FROM php:8.3-apache

RUN docker-php-ext-install pdo pdo_mysql

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf \
    && printf "\nServerName localhost\n" >> /etc/apache2/apache2.conf \
    && a2enmod rewrite headers

COPY public/ /var/www/html/public/
COPY api/ /var/www/html/api/
COPY db/schema.sql /docker-entrypoint-initdb.d/01-schema.sql

EXPOSE 80

CMD ["apache2-foreground"]
