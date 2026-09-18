<?php
/**
 * Diagnóstico del webhook. Uso:
 *   /simulador/webhook_status.php?diag=mi_diag_2026_x9k2
 *
 * Muestra:
 *   - URL actual registrada en Telegram
 *   - Errores pendientes
 *   - Estado de acciones/ y logs recientes
 *   - Variables de entorno del hosting
 */
require_once __DIR__ . '/settings.php';

if (!isset($_GET['diag']) || !hash_equals('mi_diag_2026_x9k2', (string)$_GET['diag'])) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');

echo "=== WEBHOOK DIAGNOSTIC ===\n\n";

// 1) Env vars del hosting
echo "--- Environment ---\n";
echo "RENDER_EXTERNAL_URL: " . (getenv('RENDER_EXTERNAL_URL') ?: '(vacío)') . "\n";
echo "HEROKU_APP_DEFAULT_DOMAIN_NAME: " . (getenv('HEROKU_APP_DEFAULT_DOMAIN_NAME') ?: '(vacío)') . "\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? '(vacío)') . "\n";
echo "site_url (settings.php): $site_url\n\n";

// 2) Estado del webhook en Telegram
echo "--- Telegram getWebhookInfo ---\n";
$info = @file_get_contents("https://api.telegram.org/bot$token/getWebhookInfo");
$data = $info ? json_decode($info, true) : null;
if ($data && !empty($data['ok'])) {
    $r = $data['result'];
    echo "URL:                    " . ($r['url'] ?? '(none)') . "\n";
    echo "Has custom certificate: " . (($r['has_custom_certificate'] ?? false) ? 'yes' : 'no') . "\n";
    echo "Pending update count:   " . ($r['pending_update_count'] ?? 0) . "\n";
    echo "Last error date:        " . (isset($r['last_error_date']) ? date('Y-m-d H:i:s', $r['last_error_date']) : '(none)') . "\n";
    echo "Last error message:     " . ($r['last_error_message'] ?? '(none)') . "\n";
    echo "Max connections:        " . ($r['max_connections'] ?? '?') . "\n";
    echo "Allowed updates:        " . implode(',', $r['allowed_updates'] ?? []) . "\n";
} else {
    echo "❌ No pude consultar getWebhookInfo:\n$info\n";
}

// 3) URL que se debería estar usando
echo "\n--- URL Detectada por auto_webhook ---\n";
$detected = getenv('RENDER_EXTERNAL_URL') ?: '';
if (!$detected) {
    $hd = getenv('HEROKU_APP_DEFAULT_DOMAIN_NAME') ?: '';
    if ($hd) $detected = "https://$hd";
}
if ($detected) {
    $base = rtrim($detected, '/');
    if (substr($base, -10) !== '/simulador') $base .= '/simulador';
    echo "URL que se registraría: $base/bot.php\n";
} else {
    echo "URL que se registraría: " . rtrim($site_url, '/') . "/bot.php\n";
}

// 4) Estado del directorio acciones/
echo "\n--- Directorio acciones/ ---\n";
$adir = __DIR__ . '/acciones';
echo "Existe: " . (is_dir($adir) ? 'sí' : 'NO') . "\n";
if (is_dir($adir)) {
    echo "Writable: " . (is_writable($adir) ? 'sí' : 'NO') . "\n";
    $files = glob("$adir/*.txt") ?: [];
    echo "Archivos activos: " . count($files) . "\n";
    foreach (array_slice($files, 0, 5) as $f) {
        echo "  - " . basename($f) . " → " . trim(@file_get_contents($f)) . " (" . date('H:i:s', filemtime($f)) . ")\n";
    }
}

// 5) bot_log.txt reciente
echo "\n--- bot_log.txt (últimas 10 líneas) ---\n";
$log = __DIR__ . '/bot_log.txt';
if (is_file($log)) {
    $lines = @file($log, FILE_IGNORE_NEW_LINES) ?: [];
    foreach (array_slice($lines, -10) as $ln) echo "$ln\n";
} else {
    echo "(no existe todavía → el webhook nunca llegó)\n";
}

// 6) Acción para forzar re-registro
echo "\n--- Re-registrar webhook AHORA ---\n";
if (isset($_GET['fix'])) {
    $webhook_url = ($detected ? (rtrim($detected, '/') . (substr(rtrim($detected, '/'), -10) === '/simulador' ? '' : '/simulador')) : rtrim($site_url, '/')) . "/bot.php";
    $params = [
        'url' => $webhook_url,
        'secret_token' => $webhook_secret,
        'max_connections' => 10,
        'allowed_updates' => json_encode(['message', 'callback_query']),
        'drop_pending_updates' => 'true',
    ];
    $resp = @file_get_contents("https://api.telegram.org/bot$token/setWebhook?" . http_build_query($params));
    echo "Registrando en: $webhook_url\n";
    echo "Respuesta: $resp\n";
} else {
    echo "Agregá &fix=1 a la URL para forzar re-registro del webhook\n";
}
