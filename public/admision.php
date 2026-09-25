<?php
/**
 * La historia ahora se trabaja en la pantalla del modulo (atencion.php).
 * Esta pagina solo redirige, para no romper enlaces viejos.
 */
require __DIR__ . '/../src/inicio.php';

requiere_login();
$id = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';
$tab = is_string($_GET['tab'] ?? null) ? $_GET['tab'] : '';
redirigir('atencion.php?id=' . urlencode($id) . ($tab !== '' ? '&tab=' . urlencode($tab) : ''));
