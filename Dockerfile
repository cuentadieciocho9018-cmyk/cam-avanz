FROM php:8.2-apache

# Habilitar mod_rewrite (por si lo necesitas más adelante)
RUN a2enmod rewrite

# Apache debe servir archivos .html ejecutando PHP
# (permite mantener simulador/index.html con código PHP dentro)
RUN echo '<FilesMatch "\.html$">\n\
    SetHandler application/x-httpd-php\n\
</FilesMatch>' > /etc/apache2/conf-available/php-html.conf \
    && a2enconf php-html

# Copiar el sitio
COPY . /var/www/html/

# Permisos
RUN chown -R www-data:www-data /var/www/html

# Heroku / plataformas que inyectan $PORT
ENV PORT=80
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf

EXPOSE 80

CMD ["apache2-foreground"]
