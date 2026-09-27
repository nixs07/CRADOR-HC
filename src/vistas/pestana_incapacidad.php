<?php
/**
 * Incapacidad (Urgencias 17, Observación 21, Consulta Externa 13): IncaPaci, como SIHOS: Nueva · Tipo · No. · Fecha ·
 * Hora · Alcance · Origen · Fecha inicial · Días · Fecha final · Incapacidad retroactiva · Grupo de servicios ·
 * Modalidad de prestación · Maternidad (FePoPart, EdadGest, Embarazo múltiple, NaciVivo) · Nota. Sin catálogo
 * local (deshabilitados): Alcance, retroactiva, grupo, modalidad y embarazo múltiple.
 * Variables: $a, $mod, $editable, $aqui, $tab, $F, $E, $incapacidades, $u.
 */
$di = $F['incapacidad'] ?? ['FechInca' => date('Y-m-d'), 'HoraInca' => date('H:i'), 'TipoInca' => '1', 'OrigInca' => '1'];
$eri = $E['incapacidad'] ?? [];
$anteriores = [];
foreach ($incapacidades as $i) {
    $anteriores['reg-inca-' . (int) $i['ConsInca']] = (int) $i['ConsInca'] . ' · ' . fecha_hora($i['FechInca'] . ' ' . $i['HoraInca']);
}
?>
<?= panel_abrir('incapacidad', pestana_titulo($mod, 'incapacidad'), 'file-text', $tab,
    count($incapacidades) . ' ' . (count($incapacidades) === 1 ? 'incapacidad' : 'incapacidades')) ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo">
    <form method="post" action="<?= e($aqui) ?>&amp;tab=incapacidad" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="incapacidad">
        <?= errores_resumen($eri) ?>
        <?= barra_registro('Nueva', ['-'], '', '', $di, $eri, campo_lista('TipoInca', 'Tipo', 'TipoInca', $di, $eri, true, 'br-campo')
            . selector_anteriores($anteriores) . '<div class="br-fecha">' . campos_fecha_hora('FechInca', 'HoraInca', $di, $eri) . '</div>') ?>
        <h3 class="subtitulo-panel"><?= icono('file-text') ?>Datos de la incapacidad</h3>
        <div class="rejilla">
            <?= campo_sin_columna('Alcance', 'select') ?>
            <div><label for="OrigInca">Origen</label>
                <select id="OrigInca" name="OrigInca" class="<?= ce($eri, 'OrigInca') ?>" required>
                    <option value="1"<?= (string) ($di['OrigInca'] ?? '1') === '1' ? ' selected' : '' ?>>Común</option>
                    <option value="2"<?= (string) ($di['OrigInca'] ?? '') === '2' ? ' selected' : '' ?>>Laboral</option>
                </select><?= me($eri, 'OrigInca') ?></div>
            <div><span class="etiqueta-campo">Fecha inicial</span><div class="calculado"><strong data-calc-inicial>= Fecha</strong></div></div>
            <div><label for="DiasIncaPaci">Días <span class="obligatorio" aria-hidden="true">*</span></label>
                <input type="number" id="DiasIncaPaci" name="DiasIncaPaci" value="<?= e($di['DiasInca'] ?? '') ?>" min="1" max="540" class="<?= ce($eri, 'DiasIncaPaci') ?>" required
                       data-fecha-final="FechInca" aria-describedby="inca-final"><?= me($eri, 'DiasIncaPaci') ?></div>
            <div><span class="etiqueta-campo">Fecha final</span><div class="calculado"><strong id="inca-final" data-calc-final>—</strong></div></div>
        </div>
        <h3 class="subtitulo-panel"><?= icono('clipboard-list') ?>Clasificación</h3>
        <div class="rejilla">
            <?= campo_sin_columna('Incapacidad retroactiva', 'select') ?>
            <?= campo_sin_columna('Grupo de servicios', 'select') ?>
            <?= campo_sin_columna('Modalidad de prestación de servicios', 'select') ?>
        </div>
        <h3 class="subtitulo-panel"><?= icono('heart-pulse') ?>Maternidad</h3>
        <div class="rejilla">
            <div><label for="FePoPart">Fecha probable del parto</label><input type="date" id="FePoPart" name="FePoPart" value="<?= e(($di['FePoPart'] ?? '') === '0000-00-00' ? '' : ($di['FePoPart'] ?? '')) ?>" class="<?= ce($eri, 'FePoPart') ?>"><?= me($eri, 'FePoPart') ?></div>
            <div><label for="EdadGest">Edad gestacional</label><input type="number" id="EdadGest" name="EdadGest" value="<?= (int) ($di['EdadGest'] ?? 0) ?: '' ?>" min="0" max="45" class="<?= ce($eri, 'EdadGest') ?>"><?= me($eri, 'EdadGest') ?></div>
            <?= campo_sin_columna('Embarazo múltiple', 'select') ?>
            <div><label for="NaciVivo">Nacidos vivos</label><input type="number" id="NaciVivo" name="NaciVivo" value="<?= (int) ($di['NaciVivo'] ?? 0) ?: '' ?>" min="0" max="9" class="<?= ce($eri, 'NaciVivo') ?>"><?= me($eri, 'NaciVivo') ?></div>
        </div>
        <?= campo_texto('ObseInca', 'Nota', $di, $eri, 3) ?>
        <?= botonera(['Guardar', 'Consultar', 'Imprimir', 'Cancelar']) ?>
    </form>
    </div>
<?php endif; ?>

<h3 class="titulo-tabla"><?= icono('history') ?>Incapacidades registradas</h3>
<?php if (!$incapacidades): ?>
    <?= panel_vacio('No hay incapacidades.') ?>
<?php else: ?>
    <div class="tabla-contenedor">
    <table class="tabla">
        <thead><tr><th>No.</th><th>Fecha inicial</th><th>Tipo</th><th>Origen</th><th class="num">Días</th><th>Fecha final</th><th>Nota</th><th>Profesional</th></tr></thead>
        <tbody>
        <?php foreach ($incapacidades as $i): ?>
            <tr id="reg-inca-<?= (int) $i['ConsInca'] ?>">
                <td><span class="contador"><?= (int) $i['ConsInca'] ?></span></td>
                <td class="sin-salto"><?= e(fecha_hora($i['FechInca'] . ' ' . $i['HoraInca'])) ?></td>
                <td><?= e($i['NombTipo'] ?? $i['TipoInca']) ?></td>
                <td><?= (int) $i['OrigInca'] === 2 ? 'Laboral' : 'Común' ?></td>
                <td class="num"><?= (int) $i['DiasInca'] ?></td>
                <td class="sin-salto"><?= e(date('d/m/Y', strtotime($i['FechInca'] . ' +' . max(0, (int) $i['DiasInca'] - 1) . ' days'))) ?></td>
                <td><?= e($i['ObseInca']) ?></td>
                <td><?= e($i['UsuaDigi']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</section>
