<?php
/**
 * Las historias abiertas ahora son una ventana de la pantalla del modulo (atencion.php).
 * Esta pagina solo redirige, para no romper enlaces viejos.
 */
require __DIR__ . '/../src/inicio.php';

requiere_login();
$modulo = is_string($_GET['modulo'] ?? null) ? $_GET['modulo'] : (modulo_actual() ?? '');
redirigir('atencion.php?modulo=' . urlencode($modulo) . '&historias=1');
