<?php
require __DIR__ . '/../src/inicio.php';
require __DIR__ . '/../src/tablero.php';
require __DIR__ . '/../src/catalogos.php';

$u = requiere_login();

$modulos  = tablero_abiertas_por_modulo();
$hoy      = tablero_admisiones_hoy();
$cargas   = tablero_cargas();
$sihos    = sihos_estado(isset($_GET['verificar']));
$catalogo = catalogo_ultima_actualizacion();

vista_inicio('Tablero');
?>
<h1>Tablero</h1>

<?php if ($sihos['arriba']): ?>
    <div class="alerta alerta-aviso">
        SIHOS está respondiendo. Si ya está en servicio normal, registre la atención directamente en SIHOS.
    </div>
<?php endif; ?>

<h2>Admisiones abiertas por módulo</h2>
<div class="tarjetas">
    <?php foreach ($modulos as $nombre => $m): ?>
        <div class="tarjeta">
            <div class="tarjeta-titulo"><?= e($nombre) ?></div>
            <div class="tarjeta-numero"><?= numero($m['abiertas']) ?></div>
            <div class="tarjeta-nota">Servicio <?= e(implode(', ', $m['servicios'])) ?> · abiertas</div>
        </div>
    <?php endforeach; ?>
</div>

<h2>Resumen</h2>
<div class="tarjetas">
    <div class="tarjeta">
        <div class="tarjeta-titulo">Admisiones del día</div>
        <div class="tarjeta-numero"><?= numero($hoy) ?></div>
        <div class="tarjeta-nota">Ingreso hoy <?= e(date('d/m/Y')) ?>, sin anuladas</div>
    </div>

    <div class="tarjeta <?= $cargas['pendiente'] > 0 ? 'tarjeta-aviso' : '' ?>">
        <div class="tarjeta-titulo">Pendientes por cargar a SIHOS</div>
        <div class="tarjeta-numero"><?= numero($cargas['pendiente']) ?></div>
        <div class="tarjeta-nota">
            Cargadas: <?= numero($cargas['cargada']) ?>
            <?php if ($cargas['error'] > 0): ?>
                · <span class="texto-error">Con error: <?= numero($cargas['error']) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="tarjeta <?= $sihos['arriba'] ? 'tarjeta-ok' : 'tarjeta-error' ?>">
        <div class="tarjeta-titulo">Conexión con SIHOS</div>
        <div class="tarjeta-estado">
            <span class="punto <?= $sihos['arriba'] ? 'punto-ok' : 'punto-error' ?>"></span>
            <?= $sihos['arriba'] ? 'Arriba' : 'Caída' ?>
        </div>
        <div class="tarjeta-nota">
            <?= e($sihos['mensaje']) ?><br>
            Verificado: <?= e(fecha_hora($sihos['verificado'])) ?> ·
            <a href="index.php?verificar=1">verificar ahora</a>
        </div>
    </div>

    <div class="tarjeta">
        <div class="tarjeta-titulo">Última actualización de catálogos</div>
        <div class="tarjeta-estado"><?= e($catalogo ? fecha_hora($catalogo['fin']) : 'Nunca') ?></div>
        <div class="tarjeta-nota">
            <?php if ($catalogo): ?>
                <?= e($catalogo['resultado'] === 'ok' ? 'Completa' : 'Parcial (con errores)') ?>
                · por <?= e($catalogo['usuario']) ?>
            <?php else: ?>
                Los catálogos no se han copiado desde SIHOS.
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (es_admin()): ?>
    <h2>Administración</h2>
    <div class="panel-admin">
        <form method="post" action="catalogos.php"
              onsubmit="return confirm('Se copiarán los catálogos desde SIHOS. Puede tardar varios minutos. ¿Continuar?');">
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="actualizar">
            <button type="submit" class="boton boton-primario" <?= $sihos['arriba'] ? '' : 'title="SIHOS parece caído; se intentará de todas formas"' ?>>
                Actualizar catálogos
            </button>
        </form>
        <a href="catalogos.php" class="boton boton-claro">Ver historial de catálogos</a>
        <span class="boton boton-deshabilitado" aria-disabled="true" title="Disponible en la fase 3">Cargar a SIHOS</span>
    </div>
<?php endif; ?>
<?php
vista_fin();
