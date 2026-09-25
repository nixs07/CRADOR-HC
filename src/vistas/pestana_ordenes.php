<?php
/**
 * Pestaña 5. Órdenes médicas:
 *  a) orden médica en texto libre (EncaData/DetaData TipoObje 7; no aplica en Consulta Externa);
 *  b) órdenes de procedimientos, laboratorios e imágenes (EncaOrde + DetaOrde).
 * Variables: $a, $editable, $aqui, $tab, $F, $E, $ordenesMedicas, $ordenes.
 */
$dm = $F['orden_medica'] ?? ['FechOrMe' => date('Y-m-d'), 'HoraOrMe' => date('H:i')];
$em = $E['orden_medica'] ?? [];
$do = $F['ordenes'] ?? ['FechOrde' => date('Y-m-d'), 'HoraOrde' => date('H:i'), 'items' => []];
$eo = $E['ordenes'] ?? [];
$conTexto = orden_medica_item($a) !== null;
$itemsOrden = $do['items'] ?: [['OrdProc' => '', 'OrdFina' => '1', 'OrdCant' => '1', 'OrdObse' => '']];

$filaOrden = function (array $it) {
    ob_start(); ?>
    <div class="fila-item" data-fila>
        <span class="fila-numero" data-fila-numero>1</span>
        <div class="fila-campos">
            <div class="c-medicamento"><label>Procedimiento / laboratorio / imagen <span class="obligatorio" aria-hidden="true">*</span>
                <input type="text" name="OrdProc[]" value="<?= e($it['OrdProc']) ?>" maxlength="15" data-buscar="procedimientos" autocomplete="off" placeholder="Código CUPS o nombre"></label>
                <div class="nota-campo"><?= e($it['OrdProc'] ? (procedimiento_nombre($it['OrdProc']) ?? '') : '') ?></div></div>
            <div><label>Finalidad <select name="OrdFina[]"><?= opciones('FinaProc', $it['OrdFina']) ?></select></label></div>
            <div><label>Cantidad <input type="number" name="OrdCant[]" value="<?= e($it['OrdCant']) ?>" min="1" max="999"></label></div>
            <div class="c-ancho"><label>Observación <input type="text" name="OrdObse[]" value="<?= e($it['OrdObse']) ?>" maxlength="70"></label></div>
        </div>
        <button type="button" class="boton-icono" data-quitar-fila aria-label="Quitar ítem" title="Quitar"><?= icono('x') ?></button>
    </div>
    <?php return ob_get_clean();
};
?>
<?= panel_abrir('ordenes', '5. Órdenes médicas', 'file-text', $tab, count($ordenesMedicas) . ' en texto · ' . count($ordenes) . ' de procedimientos') ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo panel-dos">
    <?php if ($conTexto): ?>
    <form method="post" action="<?= e($aqui) ?>&amp;tab=ordenes" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="orden_medica">
        <h3 class="titulo-form"><?= icono('file-text') ?>Orden médica <span class="legend-nota">Texto libre (dieta, líquidos, cuidados, control de signos…)</span></h3>
        <?= errores_resumen($em) ?>
        <div class="rejilla rejilla-fecha"><?= campos_fecha_hora('FechOrMe', 'HoraOrMe', $dm, $em) ?></div>
        <?= campo_texto('TextoOrden', 'Orden', $dm, $em, 5, true, 10000) ?>
        <?= botones_panel('Guardar orden médica') ?>
    </form>
    <?php endif; ?>

    <form method="post" action="<?= e($aqui) ?>&amp;tab=ordenes" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="ordenes">
        <h3 class="titulo-form"><?= icono('clipboard-list') ?>Órdenes de procedimientos, laboratorios e imágenes</h3>
        <?= errores_resumen($eo) ?>
        <div class="rejilla">
            <?= campos_fecha_hora('FechOrde', 'HoraOrde', $do, $eo) ?>
            <?= campo_buscador('OrdeDiag', 'Diagnóstico (CIE-10)', $do + ['OrdeDiag' => $do['CodiDiag'] ?? $a['DiagIngr']], $eo, 'diagnosticos') ?>
        </div>
        <div class="subgrupo" data-filas>
            <?= me($eo, 'OrdProc') ?>
            <div data-filas-cuerpo>
                <?php foreach ($itemsOrden as $it) { echo $filaOrden($it + ['OrdProc' => '', 'OrdFina' => '', 'OrdCant' => '1', 'OrdObse' => '']); } ?>
            </div>
            <template><?= $filaOrden(['OrdProc' => '', 'OrdFina' => '1', 'OrdCant' => '1', 'OrdObse' => '']) ?></template>
            <button type="button" class="boton boton-claro boton-chico" data-agregar-fila><?= icono('plus') ?>Agregar ítem</button>
        </div>
        <?= campo_texto('ObseOrdeProc', 'Observación general', $do + ['ObseOrdeProc' => $do['ObseOrde'] ?? ''], $eo, 2) ?>
        <?= botones_panel('Guardar órdenes') ?>
    </form>
    </div>
<?php endif; ?>

<?php if ($conTexto): ?>
<h3 class="titulo-tabla"><?= icono('history') ?>Órdenes médicas registradas</h3>
<?php if (!$ordenesMedicas): ?>
    <?= panel_vacio('No hay órdenes médicas registradas.') ?>
<?php else: ?>
    <div class="registros">
    <?php foreach ($ordenesMedicas as $o): ?>
        <div class="registro registro-abierto">
            <div class="registro-cabeza"><span class="contador"><?= (int) $o['ConsData'] ?></span>
                <strong><?= e(fecha_hora($o['Fecha'] . ' ' . $o['Hora'])) ?></strong><small><?= e($o['UsuaAsis']) ?></small></div>
            <p class="registro-nota texto-largo"><?= e($o['Texto']) ?></p>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php endif; ?>

<h3 class="titulo-tabla"><?= icono('history') ?>Órdenes de procedimientos registradas</h3>
<?php if (!$ordenes): ?>
    <?= panel_vacio('No hay órdenes de procedimientos registradas.') ?>
<?php else: ?>
    <div class="tabla-contenedor">
    <table class="tabla">
        <thead><tr><th>Orden</th><th>Fecha</th><th>Procedimiento</th><th>Finalidad</th><th class="num">Cant.</th><th>Observación</th><th>Registró</th></tr></thead>
        <tbody>
        <?php foreach ($ordenes as $o): foreach ($o['items'] as $it): ?>
            <tr>
                <td><span class="contador"><?= (int) $o['ConsOrde'] ?></span> <small>ítem <?= (int) $it['Item'] ?></small></td>
                <td class="sin-salto"><?= e(fecha_hora($o['Fecha'] . ' ' . $o['Hora'])) ?></td>
                <td><strong><?= e($it['CodiProc']) ?></strong> · <?= e($it['NombProc'] ?? '') ?></td>
                <td><?= e($it['NombFina'] ?? $it['CodiFina']) ?></td>
                <td class="num"><?= (int) $it['CantSumi'] ?></td>
                <td><?= e($it['ObseProc']) ?></td>
                <td><?= e($o['UsuaDigi']) ?></td>
            </tr>
        <?php endforeach; endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</section>
