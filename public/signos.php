<?php
/**
 * Los signos vitales ahora se registran dentro de la historia (admision.php, pestana Signos vitales).
 * Esta pagina solo redirige, para no romper enlaces viejos.
 */
require __DIR__ . '/../src/inicio.php';

requiere_login();
$id = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';
redirigir('admision.php?id=' . urlencode($id) . '&tab=signos');
