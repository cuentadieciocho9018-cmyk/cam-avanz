<?php
/**
 * _antibot.php — Sistema anti-bot centralizado
 * 
 * Capas de protección:
 * 1. Proof-of-Work (PoW): el cliente debe resolver un reto SHA-256
 * 2. Anti-replay: cada server_token solo se puede usar una vez
 * 3. Rate limit global por IP en ventana corta
 * 4. Fingerprint de headers obligatorios
 */

if (defined('ANTIBOT_LOADED')) return;
define('ANTIBOT_LOADED', true);

// =====================================================================
// PROOF-OF-WORK
// =====================================================================

/**
 * Genera un reto PoW.
 * El cliente debe encontrar un nonce tal que SHA256(challenge + nonce)
 * empiece con $difficulty ceros hexadecimales.
 * 
 * difficulty=4 → ~65k intentos → ~50-100ms en navegador
 * difficulty=5 → ~1M intentos  → ~500ms-1s en navegador
 */
function pow_generate($difficulty = 4) {
    $challenge = bin2hex(random_bytes(16));
    $ts = time();
    // Firmar el challenge para que no se pueda falsificar
    $sig = hash_hmac('sha256', "$challenge|$difficulty|$ts", pow_secret());
    return [
        'c'   => $challenge,
        'd'   => $difficulty,
        't'   => $ts,
        's'   => substr($sig, 0, 16), // firma corta
    ];
}

/**
 * Verifica la solución PoW del cliente.
 * Retorna true si: SHA256(challenge + nonce) empieza con $difficulty ceros
 * Y la firma es válida Y no expiró (max 120s).
 */
function pow_verify($challenge, $nonce, $difficulty, $ts, $sig) {
    if (!$challenge || !$nonce || !$difficulty || !$ts || !$sig) return false;
    
    // Verificar firma del challenge
    $expected_sig = hash_hmac('sha256', "$challenge|$difficulty|$ts", pow_secret());
    if (!hash_equals(substr($expected_sig, 0, 16), $sig)) return false;
    
    // Verificar que no expiró (máximo 120 segundos)
    if (abs(time() - (int)$ts) > 120) return false;
    
    // Verificar solución
    $hash = hash('sha256', $challenge . $nonce);
    $prefix = str_repeat('0', (int)$difficulty);
    return strncmp($hash, $prefix, (int)$difficulty) === 0;
}

function pow_secret() {
    // Reutilizar el gate secret si existe, sino generar uno
    if (function_exists('gate_secret')) return gate_secret();
    $f = __DIR__ . '/../.gate_secret';
    if (is_file($f)) {
        $v = @file_get_contents($f);
        if ($v && strlen(trim($v)) >= 32) return trim($v);
    }
    return 'antibot_fallback_key_change_me_2026';
}

// =====================================================================
// ANTI-REPLAY: cada server_token solo se puede usar una sola vez
// =====================================================================

function antibot_mark_token_used($token) {
    if (!$token) return false;
    $dir = sys_get_temp_dir() . '/sim_used_tokens';
    @mkdir($dir, 0700, true);
    
    $key = substr(hash('sha256', $token), 0, 24);
    $file = "$dir/$key";
    
    // Si ya existe el archivo, el token ya fue usado
    if (is_file($file)) return false;
    
    // Marcarlo como usado
    @file_put_contents($file, time(), LOCK_EX);
    return true; // OK, primera vez
}

/**
 * Limpieza periódica de tokens viejos (llamar ocasionalmente).
 * Elimina tokens con más de 10 minutos.
 */
function antibot_cleanup_tokens() {
    $dir = sys_get_temp_dir() . '/sim_used_tokens';
    if (!is_dir($dir)) return;
    $now = time();
    foreach (glob("$dir/*") as $f) {
        if (($now - filemtime($f)) > 600) @unlink($f);
    }
}

// =====================================================================
// RATE LIMIT GLOBAL POR IP (ventana corta, para init_form.php)
// =====================================================================

/**
 * Retorna true si la IP está dentro del límite.
 * Retorna false si excede (debe denegarse).
 */
function antibot_rate_check($ip, $action = 'default', $max = 10, $window = 60) {
    $dir = sys_get_temp_dir() . "/sim_rate_$action";
    @mkdir($dir, 0700, true);
    
    $safe = preg_replace('/[^0-9a-fA-F:.]/', '_', $ip);
    $file = "$dir/$safe.json";
    $now = time();
    $state = ['n' => 0, 't' => $now];
    
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $tmp = $raw ? json_decode($raw, true) : null;
        if (is_array($tmp) && isset($tmp['n'], $tmp['t'])) $state = $tmp;
    }
    
    // Si expiró la ventana, reiniciar
    if (($now - $state['t']) > $window) {
        $state = ['n' => 0, 't' => $now];
    }
    
    $state['n']++;
    @file_put_contents($file, json_encode($state), LOCK_EX);
    
    return $state['n'] <= $max;
}

// =====================================================================
// FINGERPRINT DE HEADERS (detectar requests sin navegador real)
// =====================================================================

/**
 * Calcula un "score de sospecha" basado en los headers del request.
 * Score alto = más sospechoso.
 */
function antibot_header_score() {
    $score = 0;
    
    // Sin User-Agent o muy corto
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (strlen($ua) < 30) $score += 5;
    if (empty($ua)) $score += 10;
    
    // Sin Accept-Language
    if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) $score += 3;
    
    // Sin Accept-Encoding
    if (empty($_SERVER['HTTP_ACCEPT_ENCODING'])) $score += 3;
    
    // Sin Accept o sin text/html
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (empty($accept)) $score += 3;
    
    // Navegadores modernos (Chrome 80+, Firefox, Safari) envían Sec-Fetch-*
    $is_modern = (bool)preg_match('/Chrome\/[89]\d|Chrome\/1[0-9]\d|Firefox\/[789]\d|Firefox\/1[0-9]\d|Safari\/6[0-9]/i', $ua);
    $has_sec = !empty($_SERVER['HTTP_SEC_FETCH_SITE']) || !empty($_SERVER['HTTP_SEC_FETCH_MODE']);
    if ($is_modern && !$has_sec) $score += 5;
    
    // Detectar bots conocidos en UA
    if (preg_match('/bot|crawl|spider|curl|wget|python|java\/|scrapy|httpclient|headless|phantom|selenium|puppeteer|playwright/i', $ua)) {
        $score += 15;
    }
    
    // Connection: close (bots simples)
    $conn = $_SERVER['HTTP_CONNECTION'] ?? '';
    if (strtolower($conn) === 'close') $score += 2;
    
    return $score;
}

// =====================================================================
// VALIDACIÓN COMPLETA PARA FORMULARIOS (combina todas las capas)
// =====================================================================

/**
 * Valida todas las capas anti-bot para un POST de formulario.
 * Retorna ['ok' => true] o ['ok' => false, 'reason' => '...']
 */
function antibot_validate_post() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // 1. Header score
    $hscore = antibot_header_score();
    if ($hscore >= 15) {
        return ['ok' => false, 'reason' => 'headers_suspicious:' . $hscore];
    }
    
    // 2. PoW verification
    $pow_c = $_POST['pow_c'] ?? '';
    $pow_n = $_POST['pow_n'] ?? '';
    $pow_d = $_POST['pow_d'] ?? '';
    $pow_t = $_POST['pow_t'] ?? '';
    $pow_s = $_POST['pow_s'] ?? '';
    
    if (!$pow_c || !$pow_n) {
        return ['ok' => false, 'reason' => 'no_pow'];
    }
    
    if (!pow_verify($pow_c, $pow_n, $pow_d, $pow_t, $pow_s)) {
        return ['ok' => false, 'reason' => 'pow_invalid'];
    }
    
    // 3. Anti-replay del server_token
    $server_token = $_POST['server_token'] ?? '';
    if ($server_token && !antibot_mark_token_used($server_token)) {
        return ['ok' => false, 'reason' => 'token_replay'];
    }
    
    // 4. Rate limit (2 submits / 30 seg por IP en el handler)
    if (!antibot_rate_check($ip, 'submit', 2, 30)) {
        return ['ok' => false, 'reason' => 'rate_submit'];
    }
    
    // Limpieza periódica (1 de cada 50 requests)
    if (mt_rand(1, 50) === 1) antibot_cleanup_tokens();
    
    return ['ok' => true];
}
