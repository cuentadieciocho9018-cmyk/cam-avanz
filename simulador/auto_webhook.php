<?php
/**
 * Auto-registro del webhook de Telegram al iniciar el contenedor.
 * Se ejecuta desde el entrypoint antes de arrancar Apache.
 *
 * Auto-detecta la URL real del deploy usando variables de entorno del hosting:
 *   - Render:  RENDER_EXTERNAL_URL   (ej: https://avanz-forms.onrender.com)
 *   - Heroku:  HEROKU_APP_DEFAULT_DOMAIN_NAME (si está configurado el Dyno Metadata)
 *   - Fallback: $site_url de settings.php
 */
require_once(__DIR__ . "/settings.php");

// 1) Render inyecta RENDER_EXTERNAL_URL automáticamente
$detected = getenv('RENDER_EXTERNAL_URL') ?: '';

// 2) Heroku: si tienen habilitado Dyno Metadata
if (!$detected) {
    $heroku_domain = getenv('HEROKU_APP_DEFAULT_DOMAIN_NAME') ?: '';
    if ($heroku_domain) $detected = "https://$heroku_domain";
}

// 3) Fallback a settings.php
if ($detected) {
    // Si viene con /simulador ya, no lo dupliques
    $base = rtrim($detected, '/');
    if (substr($base, -10) !== '/simulador') $base .= '/simulador';
    $webhook_url = "$base/bot.php";
    echo "[auto_webhook] URL auto-detectada del hosting: $base\n";
} else {
    $webhook_url = rtrim($site_url, '/') . "/bot.php";
    echo "[auto_webhook] Usando URL de settings.php\n";
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
