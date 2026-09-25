<?php
require __DIR__ . '/../src/inicio.php';

if (usuario_actual()) {
    redirigir('index.php');
}

$error = null;
$login = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $login = (string) ($_POST['login'] ?? '');
    $error = intentar_ingreso($login, (string) ($_POST['clave'] ?? ''));
    if ($error === null) {
        redirigir('index.php');
    }
}

vista_inicio('Ingreso');
?>
<section class="caja-login">
    <h1>Ingreso</h1>
    <p class="ayuda">Use el mismo usuario y clave de SIHOS.</p>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="login.php" autocomplete="off">
        <?= csrf_campo() ?>
        <label for="login">Usuario</label>
        <input type="text" id="login" name="login" maxlength="12" value="<?= e($login) ?>" required autofocus
               autocapitalize="characters" spellcheck="false">
        <label for="clave">Clave</label>
        <input type="password" id="clave" name="clave" required>
        <button type="submit" class="boton boton-primario boton-ancho">Ingresar</button>
    </form>
</section>
<?php
vista_fin();
