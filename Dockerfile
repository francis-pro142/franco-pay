FROM php:8.2-apache

WORKDIR /var/www/html

# Install PHP extensions required by FRANCO PAY
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy project into container
COPY . /var/www/html/

# Make /public the Apache web root
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' /etc/apache2/sites-available/000-default.conf

# Allow .htaccess and access to the public directory
RUN printf '<Directory /var/www/html/public>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n' > /etc/apache2/conf-available/franco-pay.conf

RUN a2enconf franco-pay

EXPOSE 80

CMD ["apache2-foreground"]
