<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/_antibot.php';
// Endpoint que genera un token temporal del servidor
// Los bots no pueden obtener este token sin ejecutar JavaScript correctamente

session_start();

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Rate limit: máximo 8 peticiones a init_form por IP en 60 segundos
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!antibot_rate_check($ip, 'init', 8, 60)) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limit']);
    exit;
}

// Header check: rechazar requests con headers muy sospechosos
if (antibot_header_score() >= 12) {
    http_response_code(403);
    echo json_encode(['error' => 'denied']);
    exit;
}

// Generar token único del servidor
$server_token = bin2hex(random_bytes(32));
$timestamp = time();

// Guardar en sesión
$_SESSION['form_server_token'] = $server_token;
$_SESSION['form_server_ts'] = $timestamp;

// Generar reto Proof-of-Work
$pow = pow_generate(4); // 4 hex zeros ≈ 65k intentos ≈ 50-100ms navegador

// El endpoint real está codificado en base64 y dividido
// Solo se revela cuando se obtiene este token
$real_endpoint = 'z7k2m_secure_handler.php'; // Nuevo nombre ofuscado
$encoded = base64_encode($real_endpoint);

// Dividir en partes para dificultar la extracción
$part1 = substr($encoded, 0, 8);
$part2 = substr($encoded, 8);

echo json_encode([
    'st' => $server_token,
    'ts' => $timestamp,
    'p1' => $part1,
    'p2' => $part2,
    'pow' => $pow, // reto PoW para el cliente
]);
