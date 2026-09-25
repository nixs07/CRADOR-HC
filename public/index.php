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
$triage   = tablero_triage_urgencias();

// Clave del modulo (urg/obs/ce) a partir del nombre que devuelve tablero_abiertas_por_modulo()
$claves = array_flip(array_map(fn ($d) => $d['nombre'], MODULOS_DETALLE));

vista_inicio('Tablero');
?>
<div class="cabecera-pagina">
    <div>
        <div class="antetitulo"><?= icono('layout-dashboard') ?>Contingencia SIHOS</div>
        <h1>Tablero</h1>
        <p>Admisiones abiertas y estado de la carga a SIHOS · <?= e(date('d/m/Y')) ?></p>
    </div>
    <div class="acciones">
        <a href="pacientes.php" class="boton boton-primario"><?= icono('plus') ?>Nueva admisión</a>
    </div>
</div>

<?php if ($sihos['arriba']): ?>
    <div class="alerta alerta-aviso">
        SIHOS está respondiendo. Si ya está en servicio normal, registre la atención directamente en SIHOS.
    </div>
<?php endif; ?>

<h2>Admisiones abiertas por módulo</h2>
<div class="tarjetas">
    <?php foreach ($modulos as $nombre => $m):
        $clave = $claves[$nombre] ?? null; ?>
        <?php if ($clave): ?><a class="tarjeta" href="admisiones.php?modulo=<?= e($clave) ?>"><?php else: ?><div class="tarjeta"><?php endif; ?>
            <div class="tarjeta-cabeza">
                <span class="tarjeta-icono icono-<?= e($clave ?? 'neutro') ?>"><?= icono(MODULOS_ICONO[$clave] ?? 'clipboard-list') ?></span>
                <div class="tarjeta-titulo"><?= e($nombre) ?></div>
            </div>
            <div class="tarjeta-numero"><?= numero($m['abiertas']) ?><small>abiertas</small></div>
            <div class="tarjeta-pie">
                <span>Servicio <?= e(implode(', ', $m['servicios'])) ?></span>
                <?php if ($clave): ?><span class="enlace-falso">Ver lista <?= icono('chevron-right') ?></span><?php endif; ?>
            </div>
        <?php if ($clave): ?></a><?php else: ?></div><?php endif; ?>
    <?php endforeach; ?>
</div>

<h2>Urgencias por clasificación de triage</h2>
<div class="triage-resumen">
    <?php foreach ([1, 2, 3, 4, 5] as $c): ?>
        <div class="triage-caja triage-<?= $c ?>"><span>Triage <?= triage_romano($c) ?></span><strong><?= numero($triage[$c]) ?></strong></div>
    <?php endforeach; ?>
    <div class="triage-caja triage-0"><span>Sin triage</span><strong><?= numero($triage[0]) ?></strong></div>
</div>

<h2>Resumen</h2>
<div class="tarjetas">
    <div class="tarjeta">
        <div class="tarjeta-cabeza">
            <span class="tarjeta-icono icono-obs"><?= icono('calendar') ?></span>
            <div class="tarjeta-titulo">Admisiones del día</div>
        </div>
        <div class="tarjeta-numero"><?= numero($hoy) ?></div>
        <div class="tarjeta-nota">Ingreso hoy <?= e(date('d/m/Y')) ?>, sin anuladas</div>
    </div>

    <div class="tarjeta <?= $cargas['pendiente'] > 0 ? 'tarjeta-aviso' : '' ?>">
        <div class="tarjeta-cabeza">
            <span class="tarjeta-icono <?= $cargas['pendiente'] > 0 ? 'icono-aviso' : 'icono-neutro' ?>"><?= icono('cloud-upload') ?></span>
            <div class="tarjeta-titulo">Pendientes por cargar a SIHOS</div>
        </div>
        <div class="tarjeta-numero"><?= numero($cargas['pendiente']) ?></div>
        <div class="tarjeta-nota">
            Cargadas: <?= numero($cargas['cargada']) ?>
            <?php if ($cargas['error'] > 0): ?>
                · <span class="texto-error">Con error: <?= numero($cargas['error']) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="tarjeta <?= $sihos['arriba'] ? 'tarjeta-ok' : 'tarjeta-error' ?>">
        <div class="tarjeta-cabeza">
            <span class="tarjeta-icono <?= $sihos['arriba'] ? 'icono-ok' : 'icono-error' ?>"><?= icono($sihos['arriba'] ? 'wifi' : 'wifi-off') ?></span>
            <div class="tarjeta-titulo">Conexión con SIHOS</div>
        </div>
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
        <div class="tarjeta-cabeza">
            <span class="tarjeta-icono icono-ce"><?= icono('database') ?></span>
            <div class="tarjeta-titulo">Última actualización de catálogos</div>
        </div>
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
    <div class="panel">
        <div class="panel-cuerpo panel-admin">
        <form method="post" action="catalogos.php"
              onsubmit="return confirm('Se copiarán los catálogos desde SIHOS. Puede tardar varios minutos. ¿Continuar?');">
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="actualizar">
            <button type="submit" class="boton boton-primario" <?= $sihos['arriba'] ? '' : 'title="SIHOS parece caído; se intentará de todas formas"' ?>>
                <?= icono('refresh-cw') ?>Actualizar catálogos
            </button>
        </form>
        <a href="catalogos.php" class="boton boton-claro"><?= icono('history') ?>Ver historial de catálogos</a>
        <span class="boton boton-deshabilitado" aria-disabled="true" title="Disponible en la fase 3"><?= icono('cloud-upload') ?>Cargar a SIHOS</span>
        </div>
    </div>
<?php endif; ?>
<?php
vista_fin();
