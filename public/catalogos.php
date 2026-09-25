<?php
require __DIR__ . '/../src/inicio.php';
require __DIR__ . '/../src/catalogos.php';

$u = requiere_admin();

// --- Ejecutar actualizacion -------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'actualizar') {
    csrf_verificar();
    set_time_limit(0);
    ignore_user_abort(true);      // si cierran el navegador, la copia termina igual
    session_write_close();        // no bloquear la sesion mientras copia

    $r = actualizar_catalogos($u['Login'], 'web');

    session_start();
    $tipo = ['ok' => 'ok', 'parcial' => 'aviso', 'error' => 'error'][$r['resultado']] ?? 'aviso';
    flash($tipo, 'Actualización de catálogos: ' . $r['mensaje']);
    redirigir('catalogos.php' . ($r['ejecucion_id'] ? '?ejecucion=' . $r['ejecucion_id'] : ''));
}

// --- Consultas para mostrar -------------------------------------------
$pdo = db();
$ejecuciones = $pdo->query('SELECT * FROM cont_catalogo_ejecucion ORDER BY id DESC LIMIT 20')->fetchAll();

$idVer = isset($_GET['ejecucion']) ? (int) $_GET['ejecucion'] : ($ejecuciones[0]['id'] ?? 0);
$ejecucion = null;
$detalle = [];
if ($idVer) {
    $st = $pdo->prepare('SELECT * FROM cont_catalogo_ejecucion WHERE id = ?');
    $st->execute([$idVer]);
    $ejecucion = $st->fetch() ?: null;
    $st = $pdo->prepare('SELECT * FROM cont_catalogo_actualizacion WHERE ejecucion_id = ? ORDER BY id');
    $st->execute([$idVer]);
    $detalle = $st->fetchAll();
}

$tablas = catalogo_tablas();

vista_inicio('Catálogos');
?>
<h1>Catálogos</h1>
<p class="ayuda">
    Copia desde SIHOS (<?= e(config('SIHOS_HOST', 'sin configurar')) ?>) las <?= count($tablas) ?> tablas de catálogos
    de <code>sql/02_catalogos.sql</code> y <code>sql/03_catalogos_listas.sql</code>.
    De <code>Usuarios</code> solo se copian los activos. Si SIHOS no responde, no se borra nada local.
    También se puede programar cada noche con <code>php bin/actualizar_catalogos.php</code>.
</p>

<form method="post" action="catalogos.php" class="panel-admin"
      onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Actualizando... no cierre esta página';">
    <?= csrf_campo() ?>
    <input type="hidden" name="accion" value="actualizar">
    <button type="submit" class="boton boton-primario">Actualizar catálogos ahora</button>
</form>

<?php if ($ejecucion): ?>
    <h2>Ejecución #<?= e($ejecucion['id']) ?> · <?= e(fecha_hora($ejecucion['inicio'])) ?></h2>
    <p>
        <span class="etiqueta etiqueta-<?= e($ejecucion['resultado']) ?>"><?= e($ejecucion['resultado']) ?></span>
        <?= e($ejecucion['mensaje']) ?> (por <?= e($ejecucion['usuario']) ?>, <?= e($ejecucion['origen']) ?>)
    </p>
    <?php if ($detalle): ?>
        <div class="tabla-contenedor">
        <table class="tabla">
            <thead><tr><th>Tabla</th><th>Resultado</th><th class="num">Filas</th><th class="num">Segundos</th><th>Mensaje</th></tr></thead>
            <tbody>
            <?php foreach ($detalle as $d): ?>
                <tr>
                    <td><?= e($d['tabla']) ?></td>
                    <td><span class="etiqueta etiqueta-<?= e($d['resultado']) ?>"><?= e($d['resultado']) ?></span></td>
                    <td class="num"><?= numero($d['filas']) ?></td>
                    <td class="num"><?= e($d['segundos']) ?></td>
                    <td><?= e($d['mensaje']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<h2>Historial</h2>
<?php if (!$ejecuciones): ?>
    <p>Todavía no se han actualizado los catálogos.</p>
<?php else: ?>
    <div class="tabla-contenedor">
    <table class="tabla">
        <thead><tr><th>#</th><th>Inicio</th><th>Fin</th><th>Usuario</th><th>Resultado</th><th class="num">Tablas OK</th><th class="num">Con error</th><th class="num">Filas</th></tr></thead>
        <tbody>
        <?php foreach ($ejecuciones as $x): ?>
            <tr>
                <td><a href="catalogos.php?ejecucion=<?= e($x['id']) ?>"><?= e($x['id']) ?></a></td>
                <td><?= e(fecha_hora($x['inicio'])) ?></td>
                <td><?= e(fecha_hora($x['fin'])) ?></td>
                <td><?= e($x['usuario']) ?> (<?= e($x['origen']) ?>)</td>
                <td><span class="etiqueta etiqueta-<?= e($x['resultado']) ?>"><?= e($x['resultado']) ?></span></td>
                <td class="num"><?= numero($x['tablas_ok']) ?></td>
                <td class="num"><?= numero($x['tablas_error']) ?></td>
                <td class="num"><?= numero($x['filas_total']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
<?php
vista_fin();
