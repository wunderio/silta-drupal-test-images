FROM wodby/drupal-php:7.1-3.3.1

COPY --chown=www-data:www-data . /var/www/html
USER www-data
