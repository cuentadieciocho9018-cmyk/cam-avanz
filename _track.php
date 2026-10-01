<?php
// =====================================================================
// _track.php — Tracking de visitas para panel.php
// Loguea cada visita en visits.log (JSON por línea):
//   ts, ip, cc (país), city, slug (?e=), ua, ref, st (ok/camo/social)
// Geo por IP con caché en geo_cache.json (ip-api.com, timeout bajo).
// Se incluye desde index.php / index2.php / simulador/*.
// NO imprime nada, nunca lanza errores al cliente.
// =====================================================================

if (!function_exists('trk_client_ip')) {

function trk_client_ip(): string {
    // Heroku/proxy: X-Forwarded-For trae la IP real como primer valor.
    // Cloudflare (si se usa delante): CF-Connecting-IP.
    $cands = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR']  ?? '',
        $_SERVER['REMOTE_ADDR']           ?? '',
    ];
    foreach ($cands as $c) {
        foreach (explode(',', (string)$c) as $ip) {
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '';
}

function trk_is_private(string $ip): bool {
    return !filter_var(
        $ip, FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    );
}

// ---- Geo: país + ciudad con caché por IP -------------------------------
function trk_geo(string $ip): array {
    $out = ['cc' => '-', 'city' => '-'];
    if ($ip === '' || trk_is_private($ip)) { $out['cc'] = 'LO'; $out['city'] = 'local'; return $out; }

    $cacheFile = __DIR__ . '/geo_cache.json';
    $cache = [];
    if (is_file($cacheFile)) {
        $raw = @file_get_contents($cacheFile);
        $cache = $raw ? (json_decode($raw, true) ?: []) : [];
    }
    if (isset($cache[$ip]) && is_array($cache[$ip])) {
        return ['cc' => $cache[$ip]['cc'] ?? '-', 'city' => $cache[$ip]['city'] ?? '-'];
    }

    // Lookup externo (gratis, 45 req/min — el caché lo hace casi irrelevante)
    $j = null;
    $ctx = stream_context_create(['http' => ['timeout' => 1.2, 'ignore_errors' => true]]);
    $raw = @file_get_contents(
        'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,countryCode,city&lang=es',
        false, $ctx
    );
    if ($raw) { $j = json_decode($raw, true); }
    if (is_array($j) && ($j['status'] ?? '') === 'success') {
        $out = ['cc' => (string)($j['countryCode'] ?? '-'), 'city' => (string)($j['city'] ?? '-')];
    }

    $cache[$ip] = $out;
    // Podar caché para que no crezca sin control (mantiene ~3000 IPs)
    if (count($cache) > 3000) { $cache = array_slice($cache, -1500, null, true); }
    @file_put_contents($cacheFile, json_encode($cache), LOCK_EX);

    return $out;
}

// ---- Slug de campaña/imagen: ?e=xxx ------------------------------------
function trk_slug(): string {
    $s = $_GET['e'] ?? ($_GET['img'] ?? '');
    $s = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$s);
    return substr($s, 0, 40);
}

// ---- Log principal -----------------------------------------------------
// $st: 'ok' (pasó el gate), 'camo' (camouflage), 'social' (bot OG),
//      'pg'  (pageview interno en /simulador/)
function track_visit(string $st): void {
    try {
        // No loguear herramientas internas del admin
        if (isset($_GET['diag'])) return;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') return;

        $ip  = trk_client_ip();
        $geo = trk_geo($ip);

        $row = [
            'ts'   => time(),
            'ip'   => $ip,
            'cc'   => $geo['cc'],
            'city' => $geo['city'],
            'e'    => trk_slug(),
            'ua'   => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
            'ref'  => substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 200),
            'st'   => $st,
        ];
        @file_put_contents(
            __DIR__ . '/visits.log',
            json_encode($row, JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND | LOCK_EX
        );
    } catch (\Throwable $e) {
        // Tracking jamás debe romper el flujo del gate
    }
}

}
