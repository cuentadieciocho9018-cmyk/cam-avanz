<?php
/**
 * Auto-registro del webhook de Telegram al iniciar el contenedor.
 * Se ejecuta desde el entrypoint antes de arrancar Apache.
 *
 * Auto-detecta la URL real del deploy usando variables de entorno del hosting:
 *   - Render:   RENDER_EXTERNAL_URL
 *   - Heroku:   HEROKU_APP_DEFAULT_DOMAIN_NAME (con Dyno Metadata) o HEROKU_APP_NAME
 *   - Railway:  RAILWAY_PUBLIC_DOMAIN o RAILWAY_STATIC_URL
 *   - Fly.io:   FLY_APP_NAME
 *   - Custom:   APP_URL o WEBHOOK_URL
 *   - Fallback: $site_url de settings.php
 *
 * Nota: al iniciar el contenedor NO tenemos HTTP_HOST. Si ninguna env var
 * está presente, cae al site_url. Pero webhook_selfheal.php arreglará el URL
 * automáticamente en la primera visita (usando HTTP_HOST real).
 */
require_once(__DIR__ . "/settings.php");
require_once(__DIR__ . "/webhook_selfheal.php");

$detected = wh_detect_base_url_from_env();

if ($detected) {
    $base = wh_normalize_base($detected);
    $webhook_url = "$base/bot.php";
    echo "[auto_webhook] URL auto-detectada del hosting: $base\n";
} else {
    $webhook_url = rtrim($site_url, '/') . "/bot.php";
    echo "[auto_webhook] Usando URL de settings.php (sin env var del hosting)\n";
    echo "[auto_webhook] webhook_selfheal se encargará de corregir en la primera visita.\n";
}

$api_base = "https://api.telegram.org/bot$token";

echo "[auto_webhook] Registrando webhook en: $webhook_url\n";

$params = [
    'url'                  => $webhook_url,
    'secret_token'         => $webhook_secret,
    'max_connections'      => 10,
    'allowed_updates'      => json_encode(['message', 'callback_query']),
    'drop_pending_updates' => 'true',
];

$response = @file_get_contents("$api_base/setWebhook?" . http_build_query($params));
$result   = json_decode($response, true);

if ($result && !empty($result['ok'])) {
    echo "[auto_webhook] ✅ Webhook registrado correctamente\n";
} else {
    echo "[auto_webhook] ❌ Error al registrar webhook: " . ($response ?: 'sin respuesta') . "\n";
}
