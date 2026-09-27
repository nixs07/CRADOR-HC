<?php
require __DIR__ . '/../src/inicio.php';

if (usuario_actual()) {
    redirigir(pagina_inicio());
}

$error = null;
$login = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $login = (string) ($_POST['login'] ?? '');
    $error = intentar_ingreso($login, (string) ($_POST['clave'] ?? ''));
    if ($error === null) {
        redirigir(pagina_inicio());
    }
}

vista_inicio('Ingreso');
?>
<!-- Ingreso vertical y centrado: logo con HSCJ y el lema; debajo Usuario, Clave e Ingresar -->
<div class="ingreso">
    <div class="caja-login">
        <div class="ingreso-logo">
            <img src="img/logo.png" alt="Logo E.S.E. Hospital Sagrado Corazón de Jesús" width="96" height="101">
            <h1>HSCJ</h1>
            <p>Excelencia y servicio a la comunidad</p>
        </div>
        <?php if ($error): ?>
            <div class="alerta alerta-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="login.php" autocomplete="off">
            <?= csrf_campo() ?>
            <label for="login">Usuario</label>
            <div class="campo-icono"><?= icono('user') ?>
                <input type="text" id="login" name="login" maxlength="12" value="<?= e($login) ?>" required autofocus
                       autocapitalize="characters" spellcheck="false"></div>
            <label for="clave">Clave</label>
            <div class="campo-icono"><?= icono('lock') ?>
                <input type="password" id="clave" name="clave" required></div>
            <button type="submit" class="boton boton-primario boton-ancho">Ingresar</button>
        </form>
    </div>
</div>
<?php
vista_fin();
