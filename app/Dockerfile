FROM php:8.3-apache-bookworm
RUN docker-php-ext-install pdo pdo_mysql \
 && a2enmod rewrite headers \
 && sed -ri 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
 && sed -ri 's/:80>/:8080>/' /etc/apache2/sites-available/000-default.conf \
 && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf
COPY --chown=www-data:www-data . /var/www/html/
RUN printf '%s\n' 'ServerTokens Prod' 'ServerSignature Off' > /etc/apache2/conf-available/hardening.conf \
 && a2enconf hardening \
 && echo 'session.save_path=/tmp' > /usr/local/etc/php/conf.d/session-path.ini \
 && chown -R www-data:www-data /var/www/html
USER www-data
EXPOSE 8080
