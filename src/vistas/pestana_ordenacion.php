<?php
/**
 * Ordenación (Urgencias 7, Observación 6, Consulta Externa 6): órdenes de procedimientos, laboratorios e
 * imágenes (EncaOrde + DetaOrde), como SIHOS: (Solicitar Autorización para EPS) y Salida deshabilitados (Autoriza y
 * OrdeSali siempre 0, verificado), Finalidad, Ambulatoria, DXP y DXR1-4 (listas con los diagnósticos de la
 * atención), rejilla Código · Nombre · Cant · Susp · Nota · Tomar A (Cada, sin columna) y Observaciones.
 * Variables: $a, $mod, $editable, $aqui, $tab, $F, $E, $ordenes, $consultas.
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
    <tr data-fila>
        <td class="c-cod"><input type="text" name="OrdProc[]" value="<?= e($it['OrdProc']) ?>" maxlength="15" data-buscar="procedimientos" autocomplete="off" aria-label="Código"></td>
        <td class="nota-campo"><?= e($it['OrdProc'] ? (procedimiento_nombre($it['OrdProc']) ?? '') : '') ?></td>
        <td class="c-num"><input type="number" name="OrdCant[]" value="<?= e($it['OrdCant']) ?>" min="1" max="999" aria-label="Cant"></td>
        <td><input type="checkbox" disabled title="Suspender: no aplica en contingencia" aria-label="Susp"></td>
        <td><input type="text" name="OrdObse[]" value="<?= e($it['OrdObse']) ?>" maxlength="70" aria-label="Nota"></td>
        <td><input type="text" disabled title="Tomar A (Cada): sin columna en DetaOrde" aria-label="Tomar A (Cada)"></td>
        <td><button type="button" class="boton-icono" data-quitar-fila aria-label="Quitar fila" title="Quitar"><?= icono('x') ?></button></td>
    </tr>
    <?php return ob_get_clean();
};
$dx = diagnosticos_atencion($a, $consultas);
$selDx = function (string $name, string $etq, ?string $valor) use ($dx) {
    $h = '<div class="br-campo"><label for="' . e($name) . '">' . e($etq) . '</label><select id="' . e($name) . '" name="' . e($name) . '"><option value="">—</option>';
    foreach ($dx as $c => $t) {
        $h .= '<option value="' . e($c) . '"' . ((string) $valor === (string) $c ? ' selected' : '') . '>' . e($t) . '</option>';
    }
    return $h . '</select></div>';
};
$vaciaOrden = ['OrdProc' => '', 'OrdCant' => '1', 'OrdObse' => ''];
if (!$do['items']) {
    $itemsOrden = array_fill(0, 6, $vaciaOrden);
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
        <?= barra_registro('Nuevo', $anteriores, 'FechOrde', 'HoraOrde', $do, $eo, '', '', ['Plantillas', 'Sugerido', 'Protocolo']) ?>
        <div class="rejilla">
            <?= campo_sin_columna('(Solicitar Autorización para EPS)', 'checkbox') ?>
            <?= campo_lista('OrdeFina', 'Finalidad', 'FinaCons', $do + ['OrdeFina' => $do['CodiFina'] ?? '10'], $eo, false) ?>
            <?= campo_sin_columna('Salida', 'checkbox') ?>
            <div class="casillas"><?= casilla('OrdeAmbu', 'Ambulatoria', $do) ?></div>
        </div>
        <?php if (!$dx): ?><div class="alerta alerta-aviso"><?= icono('triangle-alert') ?><div>No hay diagnósticos.</div></div><?php endif; ?>
        <div class="barra-registro barra-dx">
            <?= $selDx('OrdeDiag', 'DXP', $do['CodiDiag'] ?? array_key_first($dx)) ?>
            <?php foreach ([1, 2, 3, 4] as $k): ?><?= $selDx("OrdeRel$k", "DXR$k", $do["CodiRel$k"] ?? '') ?><?php endforeach; ?>
        </div>
        <div data-filas>
            <?= me($eo, 'OrdProc') ?>
            <div class="rejilla-grilla">
            <table>
                <thead><tr><th>Código</th><th>Nombre</th><th>Cant</th><th>Susp</th><th>Nota</th><th>Tomar A (Cada)</th><th></th></tr></thead>
                <tbody data-filas-cuerpo>
                    <?php foreach ($itemsOrden as $it) { echo $filaOrden($it + $vaciaOrden); } ?>
                </tbody>
            </table>
            </div>
            <template><?= $filaOrden($vaciaOrden) ?></template>
            <button type="button" class="boton boton-claro boton-chico" data-agregar-fila><?= icono('plus') ?>Agregar</button>
        </div>
        <?= campo_texto('ObseOrdeProc', 'Observaciones', $do + ['ObseOrdeProc' => $do['ObseOrde'] ?? ''], $eo, 2) ?>
        <?= botonera(['Guardar', 'Consultar', 'Imprimir', 'Cancelar']) ?>
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
                    <?php if ($k === 0 && (int) $o['OrdeAmbu']): ?><small class="bloque"><?= (int) $o['OrdeAmbu'] ? 'Ambulatoria' : '' ?></small><?php endif; ?></td>
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
