<?php
// Endpoint ligero de keep-alive para evitar que Render/Heroku duerma el servicio
http_response_code(200);
header('Content-Type: text/plain');
echo 'ok';
