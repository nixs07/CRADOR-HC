<?php
/**
 * Prescripción (EncaPres + DetaPres), como SIHOS (docs/RECORRIDO_SIHOS.md §3 y §5), en REJILLA:
 *  - Urgencias 4 / Observación 3 (hospitalaria): Nuevo · No. · Tipo de Prescripción · Fecha · Hora · Sugerido ·
 *    Protocolo · Plantilla · DXP · DXR 1-4 · rejilla Código · Nombre · Susp · Cantidad por dosis · Unidad · Vía ·
 *    Cada · A partir de · Número (Dosis) · Cantidad solicitada · Unidad · Nota · Medi. Prin. · Entregado ·
 *    Observaciones · Responsable de la entrega · Guardar · Imprimir · Consultar · Limpiar · Eliminar · Cancelar.
 *  - Consulta Externa 5 "Prescripción A" (ambulatoria): Código · Nombre · Susp · Dosis · Vía · Frecuencia ·
 *    Periodo de duración · Cada · Total (Dosis) · Cantidad solicitada · Unidad · Nota; Tipo Regular | Control.
 * Sin casilla "Fórmula de salida": PresSali = 1 en Urgencias/Observación y 2 en Consulta Externa.
 * Variables: $a, $mod, $editable, $aqui, $tab, $F, $E, $prescripciones, $consultas.
 */
$amb = $mod['clave'] === 'ce';
$d = $F['prescripcion'] ?? ['FechPres' => date('Y-m-d'), 'HoraPres' => date('H:i'), 'TipoPres' => '1', 'items' => []];
$er = $E['prescripcion'] ?? [];
$dx = diagnosticos_atencion($a, $consultas);
$vacia = ['CodiSumi' => '', 'CantSumi' => '', 'UnidMedi' => '', 'CodiVia' => '', 'CantFrec' => '8', 'TiemFrec' => '1',
          'CantPeDu' => '1', 'TiemPeDu' => '2', 'HoraInic' => '', 'NumeDosi' => '', 'CantSoli' => '', 'MediPrin' => '0', 'PresMedi' => ''];
$items = $d['items'] ?: array_fill(0, 6, $vacia);   // rejilla de 6 filas, como SIHOS

$filaMed = function (array $it) use ($amb, $vacia) {
    $it += $vacia;
    ob_start(); ?>
    <tr data-fila>
        <td class="c-cod"><input type="text" name="CodiSumi[]" value="<?= e($it['CodiSumi']) ?>" maxlength="20" data-buscar="suministros" autocomplete="off" aria-label="Código"></td>
        <td class="nota-campo"><?= e($it['CodiSumi'] ? (suministro_nombre($it['CodiSumi']) ?? '') : '') ?></td>
        <td><input type="checkbox" disabled title="Suspender: no aplica en contingencia" aria-label="Susp"></td>
        <td class="c-num"><input type="number" name="CantSumi[]" value="<?= e($it['CantSumi']) ?>" step="any" min="0" inputmode="decimal" aria-label="<?= $amb ? 'Dosis' : 'Cantidad por dosis' ?>"></td>
        <td class="c-sel"><select name="UnidMedi[]" aria-label="Unidad" data-llena="u"><?= opciones('UnidMedi', $it['UnidMedi']) ?></select></td>
        <td class="c-sel"><select name="CodiVia[]" aria-label="Vía" data-llena="v"><?= opciones('ViaAdmi', $it['CodiVia']) ?></select></td>
        <td class="c-doble"><span class="doble"><input type="number" name="CantFrec[]" value="<?= e($it['CantFrec']) ?>" min="1" max="99" aria-label="<?= $amb ? 'Frecuencia' : 'Cada' ?>">
            <select name="TiemFrec[]" aria-label="Unidad de la frecuencia"><?= opciones('CodiTiem', $it['TiemFrec'], false) ?></select></span></td>
        <?php if ($amb): ?>
            <td class="c-doble"><span class="doble"><input type="number" name="CantPeDu[]" value="<?= e($it['CantPeDu']) ?>" min="1" max="99" aria-label="Periodo de duración">
                <select name="TiemPeDu[]" aria-label="Unidad del periodo"><?= opciones('CodiTiem', $it['TiemPeDu'], false) ?></select></span></td>
            <td class="nota-campo">Se calcula</td>
            <td class="nota-campo">Se calcula</td>
        <?php else: ?>
            <td class="c-num"><input type="time" name="HoraInic[]" value="<?= e(substr((string) $it['HoraInic'], 0, 5)) ?>" aria-label="A partir de"></td>
            <td class="c-num"><input type="number" name="NumeDosi[]" value="<?= e($it['NumeDosi']) ?>" min="1" aria-label="Número (Dosis)"></td>
        <?php endif; ?>
        <td class="c-num"><input type="number" name="CantSoli[]" value="<?= e($it['CantSoli']) ?>" min="0" aria-label="Cantidad solicitada"></td>
        <td><input type="text" disabled value="" title="Unidad de la cantidad solicitada: sin columna en DetaPres" aria-label="Unidad"></td>
        <td><input type="text" name="PresMedi[]" value="<?= e($it['PresMedi']) ?>" maxlength="1000" aria-label="Nota"></td>
        <?php if (!$amb): ?>
            <td><select name="MediPrin[]" aria-label="Medicamento principal"><option value="0">No</option><option value="1"<?= (string) $it['MediPrin'] === '1' ? ' selected' : '' ?>>Sí</option></select></td>
            <td class="num">0</td>
        <?php endif; ?>
        <td><button type="button" class="boton-icono" data-quitar-fila aria-label="Quitar fila" title="Quitar"><?= icono('x') ?></button></td>
    </tr>
    <?php return ob_get_clean();
};
$selDx = function (string $name, string $etq, ?string $valor) use ($dx) {
    $h = '<div class="br-campo"><label for="' . e($name) . '">' . e($etq) . '</label><select id="' . e($name) . '" name="' . e($name) . '"><option value="">—</option>';
    foreach ($dx as $c => $t) {
        $h .= '<option value="' . e($c) . '"' . ((string) $valor === (string) $c ? ' selected' : '') . '>' . e($t) . '</option>';
    }
    return $h . '</select></div>';
};
?>
<?= panel_abrir('prescripcion', pestana_titulo($mod, 'prescripcion'), 'clipboard-plus', $tab, count($prescripciones) . ' ' . (count($prescripciones) === 1 ? 'prescripción' : 'prescripciones')) ?>
<?php if ($editable):
    $anteriores = [];
    foreach ($prescripciones as $p) {
        $anteriores['reg-pres-' . (int) $p['ConsPres']] = (int) $p['ConsPres'] . ' · ' . fecha_hora($p['Fecha'] . ' ' . $p['Hora']);
    }
    $tipoPres = '<div class="br-campo"><label for="TipoPres">Tipo de Prescripción</label><select id="TipoPres" name="TipoPres">'
              . '<option value="1"' . (!in_array((string) ($d['TipoPres'] ?? '1'), ['2', '3'], true) ? ' selected' : '') . '>Regular</option>'
              . '<option value="2"' . ((string) ($d['TipoPres'] ?? '') === '2' ? ' selected' : '') . '>Control</option>'
              // Domiciliaria = 3: supuesto (RESULTADO_CONSULTAS_SIHOS.md §4)
              . ($amb ? '' : '<option value="3"' . ((string) ($d['TipoPres'] ?? '') === '3' ? ' selected' : '') . '>Domiciliaria</option>') . '</select></div>';
    ?>
    <div class="panel-cuerpo">
    <?= errores_resumen($er) ?>
    <form method="post" action="<?= e($aqui) ?>&amp;tab=prescripcion" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="prescripcion">
        <?= barra_registro('Nuevo', $anteriores, 'FechPres', 'HoraPres', $d, $er, $tipoPres, '',
            $amb ? ['Plantilla', 'Experiencia', 'Protocolo'] : ['Sugerido', 'Protocolo', 'Plantilla']) ?>
        <?php if (!$dx): ?><div class="alerta alerta-aviso"><?= icono('triangle-alert') ?><div>No hay diagnósticos.</div></div><?php endif; ?>
        <div class="barra-registro barra-dx">
            <?= $selDx('PresDiag', 'DXP', $d['CodiDiag'] ?? array_key_first($dx)) ?>
            <?php foreach ([1, 2, 3, 4] as $k): ?><?= $selDx("PresRel$k", "DXR $k", $d["CodiRel$k"] ?? '') ?><?php endforeach; ?>
        </div>
        <div data-filas>
            <?= me($er, 'CodiSumi') ?>
            <div class="rejilla-grilla">
            <table>
                <thead><tr><th>Código</th><th>Nombre</th><th>Susp</th>
                    <?php if ($amb): ?>
                        <th>Dosis</th><th>Unidad</th><th>Vía</th><th>Frecuencia</th><th>Periodo de duración</th><th>Cada</th><th>Total (Dosis)</th>
                    <?php else: ?>
                        <th>Cantidad por dosis</th><th>Unidad</th><th>Vía</th><th>Cada</th><th>A partir de</th><th>Número (Dosis)</th>
                    <?php endif; ?>
                    <th>Cantidad solicitada</th><th>Unidad</th><th>Nota</th><?php if (!$amb): ?><th>Medi. Prin.</th><th>Entregado</th><?php endif; ?><th></th></tr></thead>
                <tbody data-filas-cuerpo>
                    <?php foreach ($items as $it) { echo $filaMed($it); } ?>
                </tbody>
            </table>
            </div>
            <template><?= $filaMed($vacia) ?></template>
            <button type="button" class="boton boton-claro boton-chico" data-agregar-fila><?= icono('plus') ?>Agregar</button>
        </div>
        <?= campo_texto('ObseOrde', 'Observaciones', $d, $er, 2, false, 5000, 'PresObse') ?>
        <div class="rejilla"><div><label for="PersEntr">Responsable de la entrega</label>
            <input type="text" id="PersEntr" name="PersEntr" value="<?= v($d, 'PersEntr') ?>" maxlength="15"></div></div>
        <?= botonera(['Guardar', 'Imprimir', 'Consultar', 'Limpiar', 'Eliminar', 'Cancelar']) ?>
    </form>
    </div>
<?php endif; ?>

<h3 class="titulo-tabla"><?= icono('history') ?>Prescripciones registradas</h3>
<?php if (!$prescripciones): ?>
    <?= panel_vacio('No hay prescripciones registradas.') ?>
<?php else: ?>
    <div class="registros">
    <?php foreach ($prescripciones as $p): ?>
        <div class="registro registro-abierto" id="reg-pres-<?= (int) $p['ConsPres'] ?>">
            <div class="registro-cabeza">
                <span class="contador"><?= (int) $p['ConsPres'] ?></span>
                <strong><?= e(fecha_hora($p['Fecha'] . ' ' . $p['Hora'])) ?></strong>
                <span class="etiqueta"><?= (int) $p['TipoPres'] === 2 ? 'Control' : 'Regular' ?></span>
                <small><?= e($p['UsuaDigi']) ?> · <?= e(diag_texto($p['CodiDiag'])) ?></small>
            </div>
            <div class="tabla-contenedor">
            <table class="tabla">
                <thead><tr><th>#</th><th>Código</th><th>Nombre</th><th class="num">Dosis</th><th>Vía</th><th>Cada</th><th class="num">N.º dosis</th><th class="num">Solicitada</th><th>Nota</th><th class="num">Aplicado</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $it): ?>
                    <tr>
                        <td><?= (int) $it['Item'] ?></td>
                        <td><strong><?= e($it['CodiSumi']) ?></strong><?= (int) $it['MediPrin'] ? ' <span class="etiqueta">Principal</span>' : '' ?></td>
                        <td><?= e($it['NombSumi'] ?? '') ?></td>
                        <td class="num"><?= e((float) $it['CantSumi']) ?> <?= e(lista_nombre('UnidMedi', $it['UnidMedi'])) ?></td>
                        <td><?= e(lista_nombre('ViaAdmi', $it['CodiVia'])) ?></td>
                        <td><?= (int) $it['CantFrec'] ?> <?= e(lista_nombre('CodiTiem', $it['TiemFrec'])) ?> · desde <?= e(substr($it['HoraInic'], 0, 5)) ?></td>
                        <td class="num"><?= (int) $it['NumeDosi'] ?></td>
                        <td class="num"><?= (int) $it['CantSoli'] ?></td>
                        <td><?= e($it['PresMedi']) ?></td>
                        <td class="num"><?= e((float) $it['CantApli']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php if (trim((string) $p['ObseOrde']) !== ''): ?><p class="registro-nota"><?= e($p['ObseOrde']) ?></p><?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
</section>
