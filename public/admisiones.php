<?php
/**
 * Pacientes con admision abierta en un modulo (censo).
 *   admisiones.php?modulo=urg | obs | ce
 * Urgencias se ordena por clasificacion de triage y luego por hora de ingreso.
 */
require __DIR__ . '/../src/inicio.php';
require __DIR__ . '/../src/atencion.php';

requiere_login();
$mod = modulo_o_404($_GET['modulo'] ?? null);
$filas = admisiones_abiertas($mod);

vista_inicio($mod['nombre']);

// Conteo por clasificacion de triage (solo Urgencias), con las mismas filas de la lista
$porTriage = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 0 => 0];
foreach ($filas as $f) {
    $c = (int) $f['ClasTria'];
    $porTriage[isset($porTriage[$c]) ? $c : 0]++;
}
?>
<div class="cabecera-pagina">
    <div>
        <div class="antetitulo"><?= icono(MODULOS_ICONO[$mod['clave']] ?? 'clipboard-list') ?>Pacientes con admisión abierta</div>
        <h1><?= e($mod['nombre']) ?> · <?= count($filas) ?> abiertas</h1>
        <p>Servicio <?= e(implode(', ', $mod['servicios'])) ?><?= $mod['triage'] ? ' · ordenado por clasificación de triage y hora de ingreso' : ' · ordenado por hora de ingreso' ?></p>
    </div>
    <div class="acciones">
        <a href="pacientes.php" class="boton boton-primario"><?= icono('plus') ?>Nueva admisión</a>
    </div>
</div>

<?php if ($mod['triage'] && $filas): ?>
<div class="triage-resumen">
    <?php foreach ([1, 2, 3, 4, 5] as $c): ?>
        <div class="triage-caja triage-<?= $c ?>"><span>Triage <?= triage_romano($c) ?></span><strong><?= $porTriage[$c] ?></strong></div>
    <?php endforeach; ?>
    <div class="triage-caja triage-0"><span>Sin triage</span><strong><?= $porTriage[0] ?></strong></div>
</div>
<?php endif; ?>

<?php if (!$filas): ?>
    <div class="alerta alerta-aviso"><?= icono('info') ?><div>No hay admisiones abiertas en <?= e($mod['nombre']) ?>.</div></div>
<?php else: ?>
<div class="tabla-contenedor tabla-tarjetas">
<table class="tabla">
    <thead><tr>
        <th>Paciente</th>
        <?php if ($mod['triage']): ?><th>Triage</th><?php endif; ?>
        <?php if ($mod['cama']): ?><th>Cama</th><?php endif; ?>
        <th>Ingreso</th><th>Edad</th><th>EPS</th><th>Dx ingreso</th><th class="num">Signos</th><th>Admisión</th>
    </tr></thead>
    <tbody>
    <?php foreach ($filas as $f): $url = 'admision.php?id=' . urlencode($f['ConsAdmi']); ?>
        <tr>
            <td class="celda-principal celda-paciente"><a href="<?= e($url) ?>"><?= e(paciente_nombre($f)) ?></a><br>
                <small><?= e($f['TipoDocu'] . ' ' . $f['NumeUsua']) ?></small></td>
            <?php if ($mod['triage']): ?>
                <td data-etiqueta="Triage"><?php if ($f['ClasTria']): ?>
                    <span class="etiqueta triage-<?= (int) $f['ClasTria'] ?>"><?= e(triage_romano($f['ClasTria'])) ?></span>
                <?php else: ?><a href="triage.php?id=<?= e(urlencode($f['ConsAdmi'])) ?>" class="sin-triage"><?= icono('circle-alert') ?>Sin triage</a><?php endif; ?></td>
            <?php endif; ?>
            <?php if ($mod['cama']): ?><td data-etiqueta="Cama"><?php if ($f['CamaActu']): ?><span class="chip"><?= icono('bed-double') ?><?= e($f['CamaActu']) ?></span><?php else: ?>—<?php endif; ?></td><?php endif; ?>
            <td data-etiqueta="Ingreso" class="sin-salto"><?= e(fecha_hora($f['FechIngr'] . ' ' . $f['HoraIngr'])) ?></td>
            <td data-etiqueta="Edad" class="sin-salto"><?= e(edad_texto($f['ValoEdad'], $f['UnidEdad'])) ?> <?= e($f['SexoUsua']) ?></td>
            <td data-etiqueta="EPS"><?= e($f['NombAdmi']) ?></td>
            <td data-etiqueta="Dx ingreso"><?= $f['DiagIngr'] ? '<span class="celda-codigo">' . e($f['DiagIngr']) . '</span>' : '—' ?></td>
            <td data-etiqueta="Signos" class="num"><?= (int) $f['signos'] ?></td>
            <td data-etiqueta="Admisión"><a href="<?= e($url) ?>" class="celda-codigo"><?= e($f['ConsAdmi']) ?></a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php
vista_fin();
