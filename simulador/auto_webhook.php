<?php
/**
 * Auto-registro del webhook de Telegram al iniciar el contenedor.
 * Se ejecuta desde el entrypoint antes de arrancar Apache.
 */
require_once(__DIR__ . "/settings.php");

$webhook_url = rtrim($site_url, '/') . "/bot.php";
$api_base    = "https://api.telegram.org/bot$token";

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
