FROM php:7.0-apache

RUN usermod -u 1000 www-data
RUN groupmod -g 1000 www-data

RUN apt-get update \
    && apt-get install -y libicu-dev \
    && docker-php-ext-install intl

RUN docker-php-ext-install mysqli pdo_mysql mbstring

ENV PHP_TIMEZONE America/Montevideo

ENV APACHE_DOC_ROOT /var/www/html

RUN a2enmod rewrite

RUN apt-get update \
    && apt-get install -y ssmtp \
    && apt-get clean \
    && echo "FromLineOverride=YES" >> /etc/ssmtp/ssmtp.conf \
    && echo 'sendmail_path = "/usr/sbin/ssmtp -t"' > /usr/local/etc/php/conf.d/mail.ini
