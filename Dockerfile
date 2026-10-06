FROM php:8.2-apache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install zip and other common extensions
RUN docker-php-ext-install opcache

# Copy app files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/storage \
    && chmod 777 /var/www/html/storage

# Apache config: enable .htaccess and set document root
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/app.conf \
    && a2enconf app

EXPOSE 80
