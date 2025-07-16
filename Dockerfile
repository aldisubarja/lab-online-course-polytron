# FROM php:8.2-apache

# # Install required extensions
# RUN docker-php-ext-install mysqli pdo pdo_mysql

# # Enable Apache mod_rewrite
# RUN a2enmod rewrite

# # Install additional tools
# RUN apt-get update && apt-get install -y \
#     libzip-dev \
#     zip \
#     unzip \
#     && docker-php-ext-install zip

# # Set working directory
# WORKDIR /var/www/html

# # Copy application files
# COPY . /var/www/html/

# # Set permissions
# RUN chown -R www-data:www-data /var/www/html \
#     && chmod -R 755 /var/www/html

FROM php:8.2-apache

# Install required extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Allow .htaccess to function
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

# Install additional tools
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install zip

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html
