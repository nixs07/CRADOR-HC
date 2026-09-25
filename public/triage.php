<?php
/**
 * El triage ahora se registra dentro de la historia (atencion.php, pestana 1. Triage).
 * Esta pagina solo redirige, para no romper enlaces viejos.
 */
require __DIR__ . '/../src/inicio.php';

requiere_login();
$id = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';
redirigir('atencion.php?id=' . urlencode($id) . '&tab=triage');
