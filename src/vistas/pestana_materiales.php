<?php
/**
 * Materiales (Urgencias 16, Observación 18): materiales y suministros usados (HojaMate).
 * Variables: $a, $mod, $editable, $aqui, $tab, $F, $E, $materiales.
 */
$dt = $F['material'] ?? ['FechMate' => date('Y-m-d'), 'HoraMate' => date('H:i')];
$et = $E['material'] ?? [];
$anteriores = [];
foreach ($materiales as $m) {
    $anteriores['reg-mate-' . (int) $m['ConsHoMa']] = (int) $m['ConsHoMa'] . ' · ' . fecha_hora($m['FechMate'] . ' ' . $m['HoraMate']) . ' · ' . $m['CodiMate'];
}
?>
<?= panel_abrir('materiales', pestana_titulo($mod, 'materiales'), 'clipboard-plus', $tab,
    count($materiales) . ' ' . (count($materiales) === 1 ? 'material registrado' : 'materiales registrados')) ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo">
    <form method="post" action="<?= e($aqui) ?>&amp;tab=materiales" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="material">
        <?= errores_resumen($et) ?>
        <?= barra_registro('Nuevo', $anteriores, 'FechMate', 'HoraMate', $dt, $et) ?>
        <div class="rejilla">
            <?= campo_buscador('CodiMate', 'Material o suministro', $dt, $et, 'suministros', true) ?>
            <?= campo_lista('UnidMate', 'Unidad', 'CodiUnid', $dt, $et) ?>
            <div><label for="CantMate">Cantidad <span class="obligatorio" aria-hidden="true">*</span></label>
                <input type="number" id="CantMate" name="CantMate" value="<?= v($dt, 'CantMate') ?>" step="any" min="0" inputmode="decimal" class="<?= ce($et, 'CantMate') ?>" required><?= me($et, 'CantMate') ?></div>
            <div class="c-ancho-rejilla"><label for="MateObse">Observación</label>
                <input type="text" id="MateObse" name="MateObse" value="<?= v($dt, 'MateObse') ?>" maxlength="255"></div>
        </div>
        <?= botones_panel('Registrar material') ?>
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
