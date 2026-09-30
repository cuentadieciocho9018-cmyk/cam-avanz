FROM php:8.2-apache

# Habilitar mod_rewrite y mod_headers (para .htaccess + security headers)
RUN a2enmod rewrite headers

# Apache debe servir archivos .html ejecutando PHP
# (permite mantener simulador/index.html con código PHP dentro)
RUN echo '<FilesMatch "\.html$">\n\
    SetHandler application/x-httpd-php\n\
</FilesMatch>' > /etc/apache2/conf-available/php-html.conf \
    && a2enconf php-html

# Permitir que .htaccess sobreescriba configuración (necesario para
# proteger settings.php, logs y otros archivos sensibles)
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/allow-htaccess.conf \
    && a2enconf allow-htaccess

# Copiar el sitio
COPY . /var/www/html/

# Permisos
RUN chown -R www-data:www-data /var/www/html

# curl para el keep-alive ping + mbstring para PHP (mb_strlen/mb_substr)
RUN apt-get update && apt-get install -y --no-install-recommends curl libonig-dev \
    && docker-php-ext-install mbstring \
    && rm -rf /var/lib/apt/lists/*

# Scripts de inicio
COPY entrypoint.sh /entrypoint.sh
COPY keep_alive.sh /keep_alive.sh
RUN chmod +x /entrypoint.sh /keep_alive.sh

# Heroku / plataformas que inyectan $PORT
ENV PORT=80
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf

EXPOSE 80

CMD ["/entrypoint.sh"]
