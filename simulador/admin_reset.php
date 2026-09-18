<?php
/**
 * Admin: limpia blacklist y rate limits.
 * Uso:
 *   /simulador/admin_reset.php?diag=mi_diag_2026_x9k2                → ver estado
 *   /simulador/admin_reset.php?diag=mi_diag_2026_x9k2&clear=ip       → limpiar tu IP del blacklist
 *   /simulador/admin_reset.php?diag=mi_diag_2026_x9k2&clear=all      → limpiar TODO (blacklist + rate limits + tokens usados)
 */

if (!isset($_GET['diag']) || !hash_equals('mi_diag_2026_x9k2', (string)$_GET['diag'])) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');

$blacklist_file = __DIR__ . '/blocked_ips.txt';
$my_ip = $_SERVER['REMOTE_ADDR'] ?? '';

echo "=== ADMIN RESET ===\n";
echo "Tu IP: $my_ip\n\n";

// 1) Estado actual
echo "--- Blacklist actual ---\n";
if (is_file($blacklist_file)) {
    $ips = array_filter(array_map('trim', file($blacklist_file)));
    echo "IPs bloqueadas: " . count($ips) . "\n";
    if (in_array($my_ip, $ips, true)) {
        echo "⚠️ TU IP ESTÁ BLOQUEADA\n";
    }
    foreach (array_slice($ips, 0, 20) as $ip) echo "  - $ip\n";
} else {
    echo "(no existe blocked_ips.txt)\n";
}

$rate_dir  = sys_get_temp_dir() . '/pros_rate';
$rate_fid  = sys_get_temp_dir() . '/pros_rate_fid';
$rate_init = sys_get_temp_dir() . '/sim_rate_init';
$rate_sub  = sys_get_temp_dir() . '/sim_rate_submit';
$tokens    = sys_get_temp_dir() . '/sim_used_tokens';

$count_files = function($dir) {
    if (!is_dir($dir)) return 0;
    return count(glob("$dir/*") ?: []);
};

echo "\n--- Rate limit counters ---\n";
echo "pros_rate (IP):        " . $count_files($rate_dir) . " archivos\n";
echo "pros_rate_fid (DID):   " . $count_files($rate_fid) . " archivos\n";
echo "sim_rate_init:         " . $count_files($rate_init) . " archivos\n";
echo "sim_rate_submit:       " . $count_files($rate_sub) . " archivos\n";
echo "sim_used_tokens:       " . $count_files($tokens) . " archivos\n";

$clear = $_GET['clear'] ?? '';

if ($clear === 'ip') {
    echo "\n--- Limpiando SOLO tu IP ---\n";
    // Quitar del blacklist
    if (is_file($blacklist_file)) {
        $ips = array_filter(array_map('trim', file($blacklist_file)));
        $ips = array_values(array_filter($ips, fn($x) => $x !== $my_ip));
        file_put_contents($blacklist_file, implode("\n", $ips) . ($ips ? "\n" : ''), LOCK_EX);
        echo "✅ Removida $my_ip del blacklist\n";
    }
    // Limpiar rate counters de esta IP
    $safe = preg_replace('/[^0-9a-fA-F:.]/', '_', $my_ip);
    foreach ([$rate_dir, $rate_init, $rate_sub] as $d) {
        foreach (glob("$d/$safe*") ?: [] as $f) @unlink($f);
    }
    echo "✅ Rate counters de $my_ip limpiados\n";
    echo "\n→ Ahora podés recargar la página del simulador y volver a intentar\n";
} elseif ($clear === 'all') {
    echo "\n--- Limpiando TODO ---\n";
    if (is_file($blacklist_file)) {
        @unlink($blacklist_file);
        echo "✅ blocked_ips.txt eliminado\n";
    }
    foreach ([$rate_dir, $rate_fid, $rate_init, $rate_sub, $tokens] as $d) {
        if (is_dir($d)) {
            foreach (glob("$d/*") ?: [] as $f) @unlink($f);
            echo "✅ Limpiado $d\n";
        }
    }
    // Limpiar acciones antiguas
    $acciones = __DIR__ . '/acciones';
    if (is_dir($acciones)) {
        foreach (glob("$acciones/*.txt") ?: [] as $f) @unlink($f);
        echo "✅ Limpiado acciones/\n";
    }
    echo "\n→ Sistema reseteado. Podés probar de nuevo\n";
} else {
    echo "\nAcciones disponibles:\n";
    echo "  &clear=ip   → limpia solo tu IP del blacklist y sus rate counters\n";
    echo "  &clear=all  → RESET COMPLETO (blacklist + todos los rate limits + tokens usados)\n";
}
