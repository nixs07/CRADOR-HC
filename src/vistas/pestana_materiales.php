<?php
/**
 * Materiales (Urgencias 16, Observación 18): HojaMate en REJILLA como SIHOS: Nuevo · Fecha · Hora · Plantilla y
 * 5 filas Fecha · Hora · Código · Nombre · Cant · Unidad · Indicaciones (+ Orden / Item / Factura informativos).
 * Variables: $a, $mod, $editable, $aqui, $tab, $F, $E, $materiales.
 */
$et = $E['material'] ?? [];
$vacia = ['FechMate' => date('Y-m-d'), 'HoraMate' => date('H:i'), 'CodiMate' => '', 'CantMate' => '', 'UnidMate' => '', 'MateObse' => ''];
$filas = $F['material']['filas'] ?? [];
while (count($filas) < 5) {
    $filas[] = $vacia;
}
$fila = function (array $f) use ($vacia) {
    $f += $vacia;
    ob_start(); ?>
    <tr data-fila>
        <td><input type="date" name="FechMate[]" value="<?= e($f['FechMate']) ?>" max="<?= date('Y-m-d') ?>" aria-label="Fecha"></td>
        <td class="c-num"><input type="time" name="HoraMate[]" value="<?= e(substr((string) $f['HoraMate'], 0, 5)) ?>" aria-label="Hora"></td>
        <td class="c-cod"><input type="text" name="CodiMate[]" value="<?= e($f['CodiMate']) ?>" maxlength="20" data-buscar="suministros" autocomplete="off" aria-label="Código"></td>
        <td class="nota-campo"><?= e($f['CodiMate'] ? (suministro_nombre($f['CodiMate']) ?? '') : '') ?></td>
        <td class="c-num"><input type="number" name="CantMate[]" value="<?= e($f['CantMate']) ?>" step="any" min="0" inputmode="decimal" aria-label="Cant"></td>
        <td class="c-sel"><select name="UnidMate[]" aria-label="Unidad"><?= opciones('CodiUnid', $f['UnidMate']) ?></select></td>
        <td><input type="text" name="MateObse[]" value="<?= e($f['MateObse']) ?>" maxlength="255" aria-label="Indicaciones"></td>
        <td class="nota-campo"></td><td class="nota-campo"></td><td class="nota-campo"></td>
        <td><button type="button" class="boton-icono" data-quitar-fila aria-label="Quitar fila" title="Quitar"><?= icono('x') ?></button></td>
    </tr>
    <?php return ob_get_clean();
};
?>
<?= panel_abrir('materiales', pestana_titulo($mod, 'materiales'), 'clipboard-plus', $tab,
    count($materiales) . ' ' . (count($materiales) === 1 ? 'material registrado' : 'materiales registrados')) ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo">
    <form method="post" action="<?= e($aqui) ?>&amp;tab=materiales" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="material">
        <?= errores_resumen($et) ?>
        <div class="barra-registro">
            <button type="reset" class="boton boton-claro boton-chico"><?= icono('plus') ?>Nuevo</button>
            <div class="br-campo"><label>Fecha</label><span><?= date('d/m/Y') ?></span></div>
            <div class="br-campo"><label>Hora</label><span><?= date('H:i') ?></span></div>
            <div class="br-derecha"><?= boton_no_aplica('Plantilla', true) ?></div>
        </div>
        <div data-filas>
            <?= me($et, 'CodiMate') ?>
            <div class="rejilla-grilla">
            <table>
                <thead><tr><th>Fecha</th><th>Hora</th><th>Código</th><th>Nombre</th><th>Cant</th><th>Unidad</th><th>Indicaciones</th><th>Orden</th><th>Item</th><th>Factura</th><th></th></tr></thead>
                <tbody data-filas-cuerpo><?php foreach ($filas as $f) { echo $fila($f); } ?></tbody>
            </table>
            </div>
            <template><?= $fila($vacia) ?></template>
            <button type="button" class="boton boton-claro boton-chico" data-agregar-fila><?= icono('plus') ?>Agregar</button>
        </div>
        <?= botonera(['Guardar', 'Cancelar', 'Imprimir']) ?>
    </form>
    </div>
<?php endif; ?>

<h3 class="titulo-tabla"><?= icono('history') ?>Materiales usados</h3>
<?php if (!$materiales): ?>
    <?= panel_vacio('No hay materiales registrados.') ?>
<?php else: ?>
    <div class="tabla-contenedor">
    <table class="tabla">
        <thead><tr><th>#</th><th>Fecha</th><th>Material</th><th class="num">Cantidad</th><th>Observación</th><th>Registró</th></tr></thead>
        <tbody>
        <?php foreach ($materiales as $m): ?>
            <tr id="reg-mate-<?= (int) $m['ConsHoMa'] ?>">
                <td><span class="contador"><?= (int) $m['ConsHoMa'] ?></span></td>
                <td class="sin-salto"><?= e(fecha_hora($m['FechMate'] . ' ' . $m['HoraMate'])) ?></td>
                <td><strong><?= e($m['CodiMate']) ?></strong> · <?= e($m['NombSumi'] ?? '') ?></td>
                <td class="num"><?= e((float) $m['CantMate']) ?> <?= e(lista_nombre('CodiUnid', $m['UnidMate'])) ?></td>
                <td><?= e($m['IndiAdic']) ?></td>
                <td><?= e($m['UsuaAsis']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</section>
