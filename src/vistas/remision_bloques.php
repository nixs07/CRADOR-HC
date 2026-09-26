<?php
/**
 * Formulario y lista de remisiones (Remision), con los campos de SIHOS en su orden: Especialidad, Institución,
 * Acepta (Nombre, Cargo), Autorización, Motivo, Incluir Ambulancia, Fecha y Hora Aceptación y el texto.
 * Se usa en la pestaña Remisiones (Urgencias 15, Consulta Externa 7) y dentro del Egreso de Observación.
 */

function remision_formulario(string $accionUrl, array $dr, array $err, array $anteriores): void
{ ?>
    <form method="post" action="<?= e($accionUrl) ?>" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="remision">
        <?= errores_resumen($err) ?>
        <?= barra_registro('Nuevo', $anteriores, 'FechRemi', 'HoraRemi', $dr, $err,
            '<div class="br-campo"><label for="RemiAuto">Autorización</label><input type="text" id="RemiAuto" name="RemiAuto" value="'
            . v($dr + ['RemiAuto' => $dr['NumeAuto'] ?? ''], 'RemiAuto') . '" maxlength="15"></div>') ?>
        <div class="rejilla">
            <?= campo_lista('EspeRemi', 'Especialidad', 'Espe', $dr, $err, false) ?>
            <div><label for="InstDest">Institución</label>
                <input type="text" id="InstDest" name="InstDest" value="<?= v($dr, 'InstDest') ?>" maxlength="200"></div>
            <div><label for="NombAcep">Acepta (Nombre)</label><input type="text" id="NombAcep" name="NombAcep" value="<?= v($dr, 'NombAcep') ?>" maxlength="80"></div>
            <div><label for="CargAcep">Cargo</label><input type="text" id="CargAcep" name="CargAcep" value="<?= v($dr, 'CargAcep') ?>" maxlength="40"></div>
            <?= campo_lista('RemiMoti', 'Motivo', 'MotiRemi', $dr, $err) ?>
            <?= campo_lista('ModaSoli', 'Modalidad de la solicitud', 'ModaSoli', $dr, $err) ?>
            <div><span class="etiqueta-campo">Ambulancia</span>
                <div class="casillas"><?= casilla('Ambulanc', 'Incluir Ambulancia', $dr) ?></div></div>
            <div><label for="PlacAmbu">Placa de la ambulancia</label><input type="text" id="PlacAmbu" name="PlacAmbu" value="<?= v($dr, 'PlacAmbu') ?>" maxlength="10" class="<?= ce($err, 'PlacAmbu') ?>"><?= me($err, 'PlacAmbu') ?></div>
            <div><label for="FechAcep">Fecha Aceptación</label>
                <input type="date" id="FechAcep" name="FechAcep" value="<?= e(($dr['FechAcep'] ?? '') === '0000-00-00' ? '' : ($dr['FechAcep'] ?? '')) ?>" max="<?= date('Y-m-d') ?>" class="<?= ce($err, 'FechAcep') ?>"><?= me($err, 'FechAcep') ?></div>
            <div><label for="HoraAcep">Hora Aceptación</label>
                <input type="time" id="HoraAcep" name="HoraAcep" value="<?= e(substr((string) ($dr['HoraAcep'] ?? ''), 0, 5)) ?>" class="<?= ce($err, 'HoraAcep') ?>"><?= me($err, 'HoraAcep') ?></div>
        </div>
        <?= campo_texto('MotiRemiTexto', 'Resumen clínico / motivo de la remisión', $dr + ['MotiRemiTexto' => $dr['MotiRemi'] ?? ''], $err, 4, true) ?>
        <div class="rejilla">
            <?= campo_buscador('DiagRemi', 'Diagnóstico de remisión (CIE-10)', $dr, $err, 'diagnosticos', true) ?>
            <?= campo_lista('RemiTipoDiag', 'Tipo de diagnóstico', 'TipoDiag', $dr + ['RemiTipoDiag' => $dr['TipoDiag'] ?? '2'], $err) ?>
        </div>
        <?= campo_texto('OtroMoti', 'Otro motivo u observación', $dr, $err, 2, false, 2000) ?>
        <?= botones_panel('Guardar remisión') ?>
    </form>
<?php }

function remision_lista(array $remisiones): void
{ ?>
    <h3 class="titulo-tabla"><?= icono('history') ?>Remisiones registradas</h3>
    <?php if (!$remisiones): ?>
        <?= panel_vacio('No hay remisiones.') ?>
    <?php else: ?>
        <div class="registros">
        <?php foreach ($remisiones as $r): ?>
            <div class="registro registro-abierto" id="reg-remi-<?= (int) $r['CodiRemi'] ?>">
                <div class="registro-cabeza"><span class="contador"><?= (int) $r['CodiRemi'] ?></span>
                    <strong><?= e(fecha_hora($r['FechSali'] . ' ' . $r['HoraSali'])) ?></strong>
                    <span class="etiqueta"><?= e($r['NombMoti'] ?? $r['RemiMoti']) ?></span>
                    <small><?= e($r['NombModa'] ?? $r['ModaSoli']) ?><?= (int) $r['Ambulanc'] ? ' · ambulancia ' . e($r['PlacAmbu']) : '' ?> · <?= e(diag_texto($r['DiagRemi'])) ?> · <?= e($r['UsuaDigi']) ?></small></div>
                <p class="registro-nota texto-largo"><?= e($r['MotiRemi']) ?><?= $r['NombAcep'] ? "\nAcepta: " . e($r['NombAcep'] . ($r['CargAcep'] ? ' (' . $r['CargAcep'] . ')' : '')) : '' ?><?= ($r['FechAcep'] ?? '0000-00-00') !== '0000-00-00' ? ' · ' . e(fecha_hora($r['FechAcep'] . ' ' . $r['HoraAcep'])) : '' ?></p>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif;
}

function remision_anteriores(array $remisiones): array
{
    $r = [];
    foreach ($remisiones as $x) {
        $r['reg-remi-' . (int) $x['CodiRemi']] = (int) $x['CodiRemi'] . ' · ' . fecha_hora($x['FechSali'] . ' ' . $x['HoraSali']);
    }
    return $r;
}

function remision_datos(array $a, array $F, array $E): array
{
    return [$F['remision'] ?? ['FechRemi' => date('Y-m-d'), 'HoraRemi' => date('H:i'), 'RemiTipoDiag' => '2', 'DiagRemi' => $a['DiagIngr']],
            $E['remision'] ?? []];
}
