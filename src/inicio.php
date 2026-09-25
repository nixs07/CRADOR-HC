<?php
/**
 * Arranque comun: toda pagina de public/ empieza con
 *     require __DIR__ . '/../src/inicio.php';
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/vista.php';

// Cabeceras basicas de seguridad
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// Error no controlado: mensaje claro al usuario, detalle solo en el log del servidor
set_exception_handler(function (Throwable $e): void {
    error_log('CRADOR: ' . $e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    $sinBd = $e instanceof PDOException && stripos($e->getMessage(), 'SQLSTATE[HY000] [') !== false;
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Error · CRADOR-HC</title>'
       . '<link rel="stylesheet" href="css/estilo.css"></head><body><main class="contenido">'
       . '<div class="alerta alerta-error"><strong>'
       . ($sinBd ? 'No hay conexión con la base de datos local de la contingencia.'
                 : 'Ocurrió un error inesperado.')
       . '</strong><br>Avise a sistemas. Hora: ' . date('d/m/Y H:i:s') . '</div>'
       . '<p><a href="index.php">Volver al inicio</a></p></main></body></html>';
});

iniciar_sesion();
