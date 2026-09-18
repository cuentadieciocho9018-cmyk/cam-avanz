#!/bin/bash
# Registrar webhook de Telegram automáticamente al iniciar
php /var/www/html/simulador/auto_webhook.php

# Keep-alive: evitar que Render/Heroku duerma el servicio
/keep_alive.sh &

# Iniciar Apache en primer plano
exec apache2-foreground
