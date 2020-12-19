FROM php:7.3.22-apache-stretch


RUN apt-get update -yqq && \
    apt-get install -y apt-utils zip unzip && \
    apt-get install -y nano && \
    apt-get install -y libzip-dev libpq-dev && \
    a2enmod rewrite && \
    docker-php-ext-install pdo_mysql && \
    docker-php-ext-install pgsql && \
    docker-php-ext-configure zip --with-libzip && \
    docker-php-ext-install zip && \
    pecl install xdebug && \
    docker-php-ext-enable xdebug && \
    rm -rf /var/lib/apt/lists/*

RUN php -r "readfile('http://getcomposer.org/installer');" | php -- --install-dir=/usr/bin/ --filename=composer

COPY default.conf /etc/apache2/sites-enabled/000-default.conf

WORKDIR /var/www/app

CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]

EXPOSE 80