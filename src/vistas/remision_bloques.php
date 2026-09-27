<?php
/**
 * Formulario y lista de remisiones (Remision), como SIHOS (docs/RECORRIDO_SIHOS.md §3): Nuevo · No. · Fecha · Hora ·
 * Autorización · Especialidad (lista) · Institución (lista del catálogo "Instituciones de Remisión", PROVISIONAL) ·
 * Acepta (Nombre) · Cargo · Modalidad · Motivo (+ otro) · Incluir Ambulancia · Fecha y Hora aceptación · texto.
 * Pestaña Remisiones de los 3 módulos. **Sin verificar** contra registros reales (no hay remisiones recientes).
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
            <?= campo_lista('InstRemi', 'Institución', 'InstRemi', $dr, $err, false) ?>
            <div><label for="NombAcep">Acepta (Nombre)</label><input type="text" id="NombAcep" name="NombAcep" value="<?= v($dr, 'NombAcep') ?>" maxlength="80"></div>
            <div><label for="CargAcep">Cargo</label><input type="text" id="CargAcep" name="CargAcep" value="<?= v($dr, 'CargAcep') ?>" maxlength="40"></div>
            <?= campo_lista('ModaSoli', 'Modalidad', 'ModaSoli', $dr, $err) ?>
            <?= campo_lista('RemiMoti', 'Motivo', 'MotiRemi', $dr, $err) ?>
            <div><label for="OtroMoti">Otro motivo</label><input type="text" id="OtroMoti" name="OtroMoti" value="<?= v($dr, 'OtroMoti') ?>" maxlength="2000"></div>
            <div><span class="etiqueta-campo">&nbsp;</span><div class="casillas"><?= casilla('Ambulanc', 'Incluir Ambulancia', $dr) ?></div></div>
            <div><label for="FechAcep">Fecha aceptación</label>
                <input type="date" id="FechAcep" name="FechAcep" value="<?= e(($dr['FechAcep'] ?? '') === '0000-00-00' ? '' : ($dr['FechAcep'] ?? '')) ?>" max="<?= date('Y-m-d') ?>" class="<?= ce($err, 'FechAcep') ?>"><?= me($err, 'FechAcep') ?></div>
            <div><label for="HoraAcep">Hora aceptación</label>
                <input type="time" id="HoraAcep" name="HoraAcep" value="<?= e(substr((string) ($dr['HoraAcep'] ?? ''), 0, 5)) ?>" class="<?= ce($err, 'HoraAcep') ?>"><?= me($err, 'HoraAcep') ?></div>
        </div>
        <?= campo_texto('MotiRemiTexto', 'Texto', $dr + ['MotiRemiTexto' => $dr['MotiRemi'] ?? ''], $err, 4, true) ?>
        <?= botonera(['Guardar', 'Consultar', 'Imprimir', 'Cancelar']) ?>
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
                    <small><?= e($r['NombModa'] ?? $r['ModaSoli']) ?><?= trim((string) $r['InstRemi']) !== '' ? ' · ' . e(lista_nombre('InstRemi', $r['InstRemi'])) : '' ?><?= (int) $r['Ambulanc'] ? ' · con ambulancia' : '' ?> · <?= e($r['UsuaDigi']) ?></small></div>
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
