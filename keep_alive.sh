#!/bin/bash
# Keep-alive: pings the app every 4 minutes to prevent Render/Heroku from sleeping
# Runs as a background process started by entrypoint.sh

PING_URL="${RENDER_EXTERNAL_URL:-}"

# Heroku (si Dyno Metadata está habilitado)
if [ -z "$PING_URL" ] && [ -n "$HEROKU_APP_DEFAULT_DOMAIN_NAME" ]; then
    PING_URL="https://$HEROKU_APP_DEFAULT_DOMAIN_NAME"
fi

# Fallback: parsear site_url de settings.php
if [ -z "$PING_URL" ]; then
    PING_URL=$(php -r "include '/var/www/html/simulador/settings.php'; echo preg_replace('#/simulador$#', '', \$site_url);" 2>/dev/null)
fi

if [ -z "$PING_URL" ]; then
    echo "[keep_alive] No URL found, exiting"
    exit 1
fi

PING_ENDPOINT="${PING_URL}/ping.php"
INTERVAL=240  # 4 minutes (Render sleeps after 15 min idle)

echo "[keep_alive] Pinging $PING_ENDPOINT every ${INTERVAL}s"

# Wait for the server to be ready
sleep 30

while true; do
    curl -s -o /dev/null -w "%{http_code}" "$PING_ENDPOINT" 2>/dev/null
    sleep $INTERVAL
done
