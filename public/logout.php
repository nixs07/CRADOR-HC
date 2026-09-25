<?php
require __DIR__ . '/../src/inicio.php';

// Solo por POST con token, para que un enlace externo no pueda cerrar la sesion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    cerrar_sesion();
    iniciar_sesion();
    flash('ok', 'Sesión cerrada.');
}
redirigir('login.php');
