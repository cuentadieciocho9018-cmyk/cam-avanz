<?php
/**
 * Self-healing del webhook de Telegram.
 *
 * Objetivo: que el sistema funcione en CUALQUIER hosting sin config manual.
 *
 * Estrategia:
 *   1. Al iniciar el contenedor (auto_webhook.php) se intenta con env vars
 *   2. En cada request HTTP a init_form.php se llama a wh_selfheal_if_needed()
 *      que verifica (máximo 1 vez cada 10 min) si el webhook registrado en
 *      Telegram apunta al HTTP_HOST actual. Si no, lo re-registra.
 *
 * Con esto la app funciona en Heroku, Render, Railway, Fly, cPanel, VPS,
 * o el que sea, sin tocar código.
 */

if (defined('WH_SELFHEAL_LOADED')) return;
define('WH_SELFHEAL_LOADED', true);

/**
 * Devuelve la URL base (https://host) detectada por env vars del hosting.
 * '' si ninguna coincide.
 */
function wh_detect_base_url_from_env() {
    // Custom / override manual
    foreach (['APP_URL', 'WEBHOOK_BASE_URL', 'PUBLIC_URL'] as $k) {
        $v = getenv($k);
        if ($v) return $v;
    }
    // Render
    if ($v = getenv('RENDER_EXTERNAL_URL')) return $v;
    // Heroku (con Dyno Metadata habilitado)
    if ($v = getenv('HEROKU_APP_DEFAULT_DOMAIN_NAME')) return "https://$v";
    if ($v = getenv('HEROKU_APP_NAME')) return "https://$v.herokuapp.com";
    // Railway
    if ($v = getenv('RAILWAY_PUBLIC_DOMAIN')) return "https://$v";
    if ($v = getenv('RAILWAY_STATIC_URL')) return $v;
    // Fly.io
    if ($v = getenv('FLY_APP_NAME')) return "https://$v.fly.dev";
    // Vercel (por si acaso)
    if ($v = getenv('VERCEL_URL')) return "https://$v";
    return '';
}

/**
 * Normaliza una URL base agregando /simulador si no lo tiene.
 */
function wh_normalize_base($url) {
    $base = rtrim($url, '/');
    if (substr($base, -10) !== '/simulador') $base .= '/simulador';
    return $base;
}

/**
 * Devuelve la URL base derivada del request HTTP actual.
 * Solo disponible cuando estamos sirviendo un request (no en CLI).
 */
function wh_detect_base_url_from_request() {
    if (empty($_SERVER['HTTP_HOST'])) return '';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
          || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    // Detectar path base: si el script está en /simulador/xxx.php, la base termina en /simulador
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($script));
    if ($dir === '/' || $dir === '.') $dir = '';
    return "$scheme://$host$dir";
}

/**
 * Registra el webhook en Telegram apuntando a $webhook_url.
 * Retorna el JSON decodificado de la respuesta o null.
 */
function wh_register($webhook_url, $token, $secret) {
    $params = [
        'url'                  => $webhook_url,
        'secret_token'         => $secret,
        'max_connections'      => 10,
        'allowed_updates'      => json_encode(['message', 'callback_query']),
        'drop_pending_updates' => 'true',
    ];
    $resp = @file_get_contents(
        "https://api.telegram.org/bot$token/setWebhook?" . http_build_query($params)
    );
    return $resp ? json_decode($resp, true) : null;
}

/**
 * Consulta a Telegram cuál es el webhook actualmente registrado.
 * Retorna la URL registrada o '' si no pudo.
 */
function wh_get_current_url($token) {
    $resp = @file_get_contents("https://api.telegram.org/bot$token/getWebhookInfo");
    if (!$resp) return '';
    $data = json_decode($resp, true);
    return $data['result']['url'] ?? '';
}

/**
 * Self-heal: se llama desde init_form.php (u otros endpoints).
 * Verifica máximo 1 vez cada N segundos que el webhook apunte al host actual.
 * Si no, lo re-registra automáticamente.
 *
 * Es "best-effort": si algo falla, no rompe el request del usuario.
 */
function wh_selfheal_if_needed($token, $secret, $interval = 600) {
    // Solo tiene sentido dentro de un request HTTP
    if (empty($_SERVER['HTTP_HOST'])) return;

    // Throttle: max 1 verificación cada $interval seg
    $marker = sys_get_temp_dir() . '/wh_selfheal_last';
    if (is_file($marker) && (time() - filemtime($marker)) < $interval) return;
    @touch($marker);

    $current_base = wh_detect_base_url_from_request();
    if (!$current_base) return;
    // Asegurar /simulador
    if (substr(rtrim($current_base, '/'), -10) !== '/simulador') {
        $current_base = rtrim($current_base, '/') . '/simulador';
    }
    $desired_url = rtrim($current_base, '/') . '/bot.php';

    $registered = wh_get_current_url($token);

    if ($registered !== $desired_url) {
        $result = wh_register($desired_url, $token, $secret);
        // Log discreto
        $ok = $result && !empty($result['ok']) ? 'ok' : 'fail';
        @file_put_contents(
            __DIR__ . '/webhook_selfheal.log',
            date('Y-m-d H:i:s') . " | $ok | prev=$registered | new=$desired_url\n",
            FILE_APPEND | LOCK_EX
        );
    }
}
