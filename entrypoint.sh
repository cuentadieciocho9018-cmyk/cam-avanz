#!/bin/bash
# Registrar webhook de Telegram automáticamente al iniciar
php /var/www/html/simulador/auto_webhook.php

# Iniciar Apache en primer plano
exec apache2-foreground
