FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    apache2 \
    php8.1 \
    libapache2-mod-php8.1 \
    php8.1-mysql \
    php8.1-curl \
    php8.1-mbstring \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN rm -f /var/www/html/index.html

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

COPY start.sh /start.sh
RUN chmod +x /start.sh

CMD ["/start.sh"]
