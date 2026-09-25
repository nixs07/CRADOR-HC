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
<div class="ingreso">
    <section class="ingreso-marca" aria-label="CRADOR-HC">
        <div class="ingreso-logo">
            <img src="img/logo.png" alt="Logo E.S.E. Hospital Sagrado Corazón de Jesús" width="72" height="76">
            <div>
                <strong>CRADOR-HC</strong>
                <span>E.S.E. Hospital Sagrado Corazón de Jesús</span>
            </div>
        </div>
        <h2>Registro clínico de <em>contingencia</em></h2>
        <p>Registre la atención mientras SIHOS no está disponible. La información se carga a SIHOS cuando el sistema vuelva.</p>
        <ul class="ingreso-puntos">
            <li><span class="tarjeta-icono icono-urg"><?= icono('siren') ?></span>
                <div><strong>Urgencias, Observación y Consulta Externa</strong>Admisión, triage y signos vitales.</div></li>
            <li><span class="tarjeta-icono icono-obs"><?= icono('database') ?></span>
                <div><strong>Mismas tablas y códigos de SIHOS</strong>Los catálogos se copian desde SIHOS.</div></li>
            <li><span class="tarjeta-icono icono-ce"><?= icono('wifi-off') ?></span>
                <div><strong>Funciona sin internet</strong>Corre en la red local del hospital.</div></li>
        </ul>
    </section>

    <section class="ingreso-form">
        <div class="caja-login">
            <h1>Ingreso</h1>
            <p class="ayuda">Use el mismo usuario y clave de SIHOS.</p>
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
            <p class="ingreso-nota"><?= icono('info') ?><span>Solo pueden ingresar los usuarios activos en SIHOS.</span></p>
        </div>
    </section>
</div>
<?php
vista_fin();
