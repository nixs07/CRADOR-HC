<?php
/**
 * Historia (ficha de la admision), organizada como en SIHOS: encabezado de la admision
 * arriba y pestanas numeradas debajo.
 * Fase 2 (primer bloque): triage y signos vitales. Las demas pestanas llegan en los
 * siguientes bloques (anamnesis y diagnosticos, ordenes, formula, notas, evolucion, egreso).
 */
require __DIR__ . '/../src/inicio.php';
require __DIR__ . '/../src/atencion.php';
require __DIR__ . '/../src/formulario.php';

requiere_login();
$a = admision_o_404($_GET['id'] ?? null);
$claveMod = modulo_de_servicio($a['ServEgre']);
$mod = $claveMod ? MODULOS_DETALLE[$claveMod] : null;
$triage = triage_de_admision($a['ConsAdmi']);
$signos = signos_de_admision($a['ConsAdmi']);
$editable = admision_editable($a);
$id = urlencode($a['ConsAdmi']);
modulo_elegir($claveMod);
$tieneTriage = $mod && $mod['triage'];

vista_inicio('Admisión ' . $a['ConsAdmi']);
?>
<?php if ($claveMod): ?>
    <p class="migas"><a href="admisiones.php?modulo=<?= e($claveMod) ?>"><?= icono('arrow-left') ?>Historias abiertas · <?= e($mod['nombre']) ?></a></p>
<?php endif; ?>
<?php encabezado_admision($a, true); ?>

<?php if (!$editable): ?>
    <div class="alerta alerta-aviso"><?= icono('lock') ?><div>Esta admisión está cerrada, anulada o ya se cargó a SIHOS: solo se puede consultar.</div></div>
<?php endif; ?>

<?php pestanas_historia($a, $tieneTriage ? 'triage' : 'signos', count($signos), true); ?>

<?php if ($tieneTriage): ?>
<section id="triage" class="seccion panel">
    <div class="panel-cabeza">
        <h2><?= icono('siren') ?>Triage</h2>
        <?php if (!$triage && $editable): ?><a href="triage.php?id=<?= e($id) ?>" class="boton boton-primario"><?= icono('plus') ?>Registrar triage</a><?php endif; ?>
    </div>
    <?php if (!$triage): ?>
        <div class="alerta vacio"><?= icono('info') ?><div>El paciente aún no tiene triage.</div></div>
    <?php else: ?>
        <dl class="datos">
            <dt>Clasificación</dt><dd><span class="etiqueta triage-<?= (int) $triage['ClasTria'] ?>"><?= e(lista_nombre('ClasTria', $triage['ClasTria'])) ?></span>
                · Conducta: <?= e(lista_nombre('CondTria', $triage['CondTria'])) ?></dd>
            <dt>Fecha y hora</dt><dd><?= e(fecha_hora($triage['FechTria'] . ' ' . $triage['HoraTria'])) ?> · <?= e($triage['UsuaDigi']) ?></dd>
            <dt>Motivo de consulta</dt><dd class="texto-largo"><?= e($triage['MotiCons']) ?></dd>
            <dt>Hallazgos clínicos</dt><dd class="texto-largo"><?= e($triage['HallClin']) ?></dd>
            <dt>Impresión diagnóstica</dt><dd><?= e($triage['CodiDiag'] ? $triage['CodiDiag'] . ' · ' . (diagnostico_nombre($triage['CodiDiag']) ?? '') : '—') ?></dd>
            <?php if (trim((string) $triage['Conducta']) !== ''): ?>
                <dt>Observaciones de conducta</dt><dd class="texto-largo"><?= e($triage['Conducta']) ?></dd>
            <?php endif; ?>
        </dl>
    <?php endif; ?>
</section>
<?php endif; ?>

<section id="signos" class="seccion panel">
    <div class="panel-cabeza">
        <div>
            <h2><?= icono('heart-pulse') ?>Signos vitales</h2>
            <p><?= count($signos) ?> <?= count($signos) === 1 ? 'toma registrada' : 'tomas registradas' ?></p>
        </div>
        <?php if ($editable): ?><a href="signos.php?id=<?= e($id) ?>" class="boton boton-primario"><?= icono('plus') ?>Registrar signos</a><?php endif; ?>
    </div>
    <?php if (!$signos): ?>
        <div class="alerta vacio"><?= icono('info') ?><div>No hay tomas de signos vitales.</div></div>
    <?php else: ?>
    <div class="tabla-contenedor">
    <table class="tabla tabla-signos">
        <thead><tr><th>Toma</th><th>Fecha y hora</th><th class="num">Peso</th><th class="num">Talla</th><th class="num">IMC</th><th class="num">FC</th><th class="num">FR</th><th class="num">T °C</th>
            <th>PA</th><th class="num">TM</th><th class="num">SatO₂</th><th class="num">Gluco</th><th class="num">Dolor</th><th>Registró</th></tr></thead>
        <tbody>
        <?php foreach ($signos as $s): ?>
            <tr>
                <td><span class="contador"><?= (int) $s['ConsSign'] ?></span></td>
                <td class="sin-salto"><?= e(fecha_hora($s['FechToma'] . ' ' . $s['HoraToma'])) ?></td>
                <td class="num"><?= (float) $s['Peso'] > 0 ? e((float) $s['Peso']) : '—' ?></td>
                <td class="num"><?= (float) $s['Talla'] > 0 ? e((float) $s['Talla']) : '—' ?></td>
                <td class="num"><?= (float) $s['MasaCorp'] > 0 ? e((float) $s['MasaCorp']) : '—' ?></td>
                <td class="num"><?= (int) $s['Pulso'] ?></td>
                <td class="num"><?= (int) $s['Respirac'] ?></td>
                <td class="num"><?= e((float) $s['Temperat']) ?></td>
                <td class="sin-salto"><strong><?= (int) $s['PANume'] ?>/<?= (int) $s['PADeno'] ?></strong></td>
                <td class="num"><?= (int) $s['TM'] ?></td>
                <td class="num"><?= (float) $s['Saturaci'] > 0 ? e((float) $s['Saturaci']) . '%' : '—' ?></td>
                <td class="num"><?= (int) $s['GlucMetr'] > 0 ? (int) $s['GlucMetr'] : '—' ?></td>
                <td class="num"><?= e((float) $s['Dolor']) ?></td>
                <td><?= e($s['UsuaDigi']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>
<?php
vista_fin();
