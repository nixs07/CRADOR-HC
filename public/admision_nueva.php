<?php
/**
 * La nueva admision ahora se crea en el encabezado de la pantalla del modulo (atencion.php).
 * Esta pagina solo redirige, para no romper enlaces viejos.
 */
require __DIR__ . '/../src/inicio.php';

requiere_login();
$g = fn ($k) => is_string($_GET[$k] ?? null) ? $_GET[$k] : '';
redirigir('atencion.php?modulo=' . urlencode($g('modulo')) . '&TipoDocu=' . urlencode($g('TipoDocu'))
    . '&NumeUsua=' . urlencode($g('NumeUsua')));
