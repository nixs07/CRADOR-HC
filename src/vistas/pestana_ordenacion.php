<?php
/**
 * Ordenación (Urgencias 7, Observación 5, Consulta Externa 6): órdenes de procedimientos, laboratorios e
 * imágenes (EncaOrde + DetaOrde), con los campos de SIHOS: Solicitar autorización para EPS, Finalidad,
 * Ambulatoria, DXP y DXR1 a DXR4; ítems Código, Nombre, Cant y Nota.
 * Variables: $a, $mod, $editable, $aqui, $tab, $F, $E, $ordenes.
 */
$do = $F['ordenes'] ?? ['FechOrde' => date('Y-m-d'), 'HoraOrde' => date('H:i'), 'CodiFina' => '10', 'items' => []];
$eo = $E['ordenes'] ?? [];
$itemsOrden = $do['items'] ?: [['OrdProc' => '', 'OrdCant' => '1', 'OrdObse' => '']];
$anteriores = [];
foreach ($ordenes as $o) {
    $anteriores['reg-orden-' . (int) $o['ConsOrde']] = (int) $o['ConsOrde'] . ' · ' . fecha_hora($o['Fecha'] . ' ' . $o['Hora']);
}

$filaOrden = function (array $it) {
    ob_start(); ?>
    <div class="fila-item" data-fila>
        <span class="fila-numero" data-fila-numero>1</span>
        <div class="fila-campos">
            <div class="c-medicamento"><label>Código <span class="obligatorio" aria-hidden="true">*</span>
                <input type="text" name="OrdProc[]" value="<?= e($it['OrdProc']) ?>" maxlength="15" data-buscar="procedimientos" autocomplete="off" placeholder="Código CUPS o nombre"></label>
                <div class="nota-campo"><?= e($it['OrdProc'] ? (procedimiento_nombre($it['OrdProc']) ?? '') : '') ?></div></div>
            <div><label>Cant <input type="number" name="OrdCant[]" value="<?= e($it['OrdCant']) ?>" min="1" max="999"></label></div>
            <div class="c-ancho"><label>Nota <input type="text" name="OrdObse[]" value="<?= e($it['OrdObse']) ?>" maxlength="70"></label></div>
        </div>
        <button type="button" class="boton-icono" data-quitar-fila aria-label="Quitar ítem" title="Quitar"><?= icono('x') ?></button>
    </div>
    <?php return ob_get_clean();
};
$dxDef = ['OrdeDiag' => $do['CodiDiag'] ?? $a['DiagIngr']];
foreach ([1, 2, 3, 4] as $i) {
    $dxDef["OrdeRel$i"] = $do["CodiRel$i"] ?? '';
}
?>
<?= panel_abrir('ordenacion', pestana_titulo($mod, 'ordenacion'), 'clipboard-list', $tab,
    count($ordenes) . ' ' . (count($ordenes) === 1 ? 'orden' : 'órdenes') . ' de procedimientos, laboratorios e imágenes') ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo">
    <?= errores_resumen($eo) ?>
    <form method="post" action="<?= e($aqui) ?>&amp;tab=ordenacion" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="ordenes">
        <?= barra_registro('Nuevo', $anteriores, 'FechOrde', 'HoraOrde', $do, $eo) ?>
        <div class="rejilla">
            <div class="casillas"><?= casilla('Autoriza', 'Solicitar Autorización Para EPS', $do) ?></div>
            <?= campo_lista('OrdeFina', 'Finalidad', 'FinaCons', $do + ['OrdeFina' => $do['CodiFina'] ?? '10'], $eo, false) ?>
            <div class="casillas"><?= casilla('OrdeAmbu', 'Ambulatoria', $do) ?></div>
        </div>
        <div class="rejilla">
            <?= campo_buscador('OrdeDiag', 'DXP', $do + $dxDef, $eo, 'diagnosticos') ?>
            <?php foreach ([1, 2, 3, 4] as $i): ?><?= campo_buscador("OrdeRel$i", "DXR$i", $do + $dxDef, $eo, 'diagnosticos') ?><?php endforeach; ?>
        </div>
        <div class="subgrupo" data-filas>
            <?= me($eo, 'OrdProc') ?>
            <div data-filas-cuerpo>
                <?php foreach ($itemsOrden as $it) { echo $filaOrden($it + ['OrdProc' => '', 'OrdCant' => '1', 'OrdObse' => '']); } ?>
            </div>
            <template><?= $filaOrden(['OrdProc' => '', 'OrdCant' => '1', 'OrdObse' => '']) ?></template>
            <button type="button" class="boton boton-claro boton-chico" data-agregar-fila><?= icono('plus') ?>Agregar ítem</button>
        </div>
        <?= campo_texto('ObseOrdeProc', 'Observaciones', $do + ['ObseOrdeProc' => $do['ObseOrde'] ?? ''], $eo, 2) ?>
        <?= botones_panel('Guardar orden') ?>
    </form>
    </div>
<?php endif; ?>

<h3 class="titulo-tabla"><?= icono('history') ?>Órdenes registradas</h3>
<?php if (!$ordenes): ?>
    <?= panel_vacio('No hay órdenes registradas.') ?>
<?php else: ?>
    <div class="tabla-contenedor">
    <table class="tabla">
        <thead><tr><th>Orden</th><th>Fecha</th><th>Código</th><th>Nombre</th><th class="num">Cant</th><th class="num">Realizados</th><th>Nota</th><th>Prof.</th></tr></thead>
        <tbody>
        <?php foreach ($ordenes as $o): foreach ($o['items'] as $k => $it): ?>
            <tr<?= $k === 0 ? ' id="reg-orden-' . (int) $o['ConsOrde'] . '"' : '' ?>>
                <td><span class="contador"><?= (int) $o['ConsOrde'] ?></span> <small>ítem <?= (int) $it['Item'] ?></small>
                    <?php if ($k === 0 && ((int) $o['Autoriza'] || (int) $o['OrdeAmbu'])): ?><small class="bloque"><?= (int) $o['Autoriza'] ? 'Autorización EPS ' : '' ?><?= (int) $o['OrdeAmbu'] ? 'Ambulatoria' : '' ?></small><?php endif; ?></td>
                <td class="sin-salto"><?= e(fecha_hora($o['Fecha'] . ' ' . $o['Hora'])) ?></td>
                <td><strong><?= e($it['CodiProc']) ?></strong></td>
                <td><?= e($it['NombProc'] ?? '') ?></td>
                <td class="num"><?= (int) $it['CantSumi'] ?></td>
                <td class="num"><?= (int) $it['CantReal'] ?></td>
                <td><?= e($it['ObseProc']) ?></td>
                <td><?= e($it['CodiProf'] ?: $o['UsuaDigi']) ?></td>
            </tr>
        <?php endforeach; endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</section>
