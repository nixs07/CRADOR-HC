<?php
/**
 * Prescripción (EncaPres + DetaPres): Urgencias 4, Observación 3, Consulta Externa 5 (Prescripción Ambulatoria).
 * Barra de SIHOS: Nuevo, No., Tipo de Prescripción, Fecha, Hora; DXP, DXR 1 y DXR 2; varios medicamentos.
 * Variables: $a, $mod, $editable, $aqui, $tab, $F, $E, $prescripciones.
 */
$d = $F['prescripcion'] ?? ['FechPres' => date('Y-m-d'), 'HoraPres' => date('H:i'), 'PresSali' => '2', 'TipoPres' => '1', 'items' => []];
$er = $E['prescripcion'] ?? [];
$items = $d['items'] ?: [['CodiSumi' => '', 'CantSumi' => '', 'UnidMedi' => '', 'CodiVia' => '', 'CantFrec' => '8', 'TiemFrec' => '1', 'CantPeDu' => '1', 'TiemPeDu' => '2', 'PresMedi' => '']];

/** Fila de un medicamento (también es la plantilla para agregar filas con JavaScript). */
$filaMedicamento = function (array $it) {
    ob_start(); ?>
    <div class="fila-item" data-fila>
        <span class="fila-numero" data-fila-numero>1</span>
        <div class="fila-campos">
            <div class="c-medicamento"><label>Medicamento <span class="obligatorio" aria-hidden="true">*</span>
                <input type="text" name="CodiSumi[]" value="<?= e($it['CodiSumi']) ?>" maxlength="20" data-buscar="suministros" autocomplete="off" placeholder="Código o nombre"></label>
                <div class="nota-campo"><?= e($it['CodiSumi'] ? (suministro_nombre($it['CodiSumi']) ?? '') : '') ?></div></div>
            <div><label>Dosis <input type="number" name="CantSumi[]" value="<?= e($it['CantSumi']) ?>" step="any" min="0" inputmode="decimal"></label></div>
            <div><label>Unidad <select name="UnidMedi[]"><?= opciones('UnidMedi', $it['UnidMedi']) ?></select></label></div>
            <div><label>Vía <select name="CodiVia[]"><?= opciones('ViaAdmi', $it['CodiVia']) ?></select></label></div>
            <div class="c-doble"><label>Cada
                <span class="doble"><input type="number" name="CantFrec[]" value="<?= e($it['CantFrec']) ?>" min="1" max="99" aria-label="Frecuencia: cada">
                <select name="TiemFrec[]" aria-label="Unidad de la frecuencia"><?= opciones('CodiTiem', $it['TiemFrec'], false) ?></select></span></label></div>
            <div class="c-doble"><label>Durante
                <span class="doble"><input type="number" name="CantPeDu[]" value="<?= e($it['CantPeDu']) ?>" min="1" max="99" aria-label="Duración">
                <select name="TiemPeDu[]" aria-label="Unidad de la duración"><?= opciones('CodiTiem', $it['TiemPeDu'], false) ?></select></span></label></div>
            <div class="c-ancho"><label>Indicación escrita (opcional)
                <input type="text" name="PresMedi[]" value="<?= e($it['PresMedi']) ?>" maxlength="1000" placeholder="Si se deja vacía se arma sola con la dosis, vía y frecuencia"></label></div>
        </div>
        <button type="button" class="boton-icono" data-quitar-fila aria-label="Quitar medicamento" title="Quitar"><?= icono('x') ?></button>
    </div>
    <?php return ob_get_clean();
};
?>
<?= panel_abrir('prescripcion', pestana_titulo($mod, 'prescripcion'), 'clipboard-plus', $tab, count($prescripciones) . ' ' . (count($prescripciones) === 1 ? 'prescripción' : 'prescripciones')) ?>
<?php if ($editable):
    $anteriores = [];
    foreach ($prescripciones as $p) {
        $anteriores['reg-pres-' . (int) $p['ConsPres']] = (int) $p['ConsPres'] . ' · ' . fecha_hora($p['Fecha'] . ' ' . $p['Hora']);
    }
    $tipoPres = '<div class="br-campo"><label for="TipoPres">Tipo de Prescripción</label><select id="TipoPres" name="TipoPres">'
              . '<option value="1"' . ((string) ($d['TipoPres'] ?? '1') !== '2' ? ' selected' : '') . '>Regular</option>'
              . '<option value="2"' . ((string) ($d['TipoPres'] ?? '') === '2' ? ' selected' : '') . '>Control</option></select></div>';
    ?>
    <div class="panel-cuerpo">
    <?= errores_resumen($er) ?>
    <form method="post" action="<?= e($aqui) ?>&amp;tab=prescripcion" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="prescripcion">
        <?= barra_registro('Nuevo', $anteriores, 'FechPres', 'HoraPres', $d, $er, $tipoPres) ?>
        <div class="rejilla">
            <?= campo_buscador('PresDiag', 'DXP', $d + ['PresDiag' => $d['CodiDiag'] ?? $a['DiagIngr']], $er, 'diagnosticos') ?>
            <?= campo_buscador('PresRel1', 'DXR 1', $d + ['PresRel1' => $d['CodiRel1'] ?? ''], $er, 'diagnosticos') ?>
            <?= campo_buscador('PresRel2', 'DXR 2', $d + ['PresRel2' => $d['CodiRel2'] ?? ''], $er, 'diagnosticos') ?>
            <div><span class="etiqueta-campo">¿Fórmula de salida?</span>
                <label class="opcion"><input type="radio" name="PresSali" value="1"<?= (string) $d['PresSali'] === '1' ? ' checked' : '' ?>> Sí</label>
                <label class="opcion"><input type="radio" name="PresSali" value="2"<?= (string) $d['PresSali'] !== '1' ? ' checked' : '' ?>> No</label></div>
        </div>
        <div class="subgrupo" data-filas>
            <h3><?= icono('clipboard-list') ?>Suministros</h3>
            <?= me($er, 'CodiSumi') ?>
            <div data-filas-cuerpo>
                <?php foreach ($items as $it) { echo $filaMedicamento($it + ['CodiSumi' => '', 'CantSumi' => '', 'UnidMedi' => '', 'CodiVia' => '', 'CantFrec' => '', 'TiemFrec' => '1', 'CantPeDu' => '', 'TiemPeDu' => '2', 'PresMedi' => '']); } ?>
            </div>
            <template><?= $filaMedicamento(['CodiSumi' => '', 'CantSumi' => '', 'UnidMedi' => '', 'CodiVia' => '', 'CantFrec' => '8', 'TiemFrec' => '1', 'CantPeDu' => '1', 'TiemPeDu' => '2', 'PresMedi' => '']) ?></template>
            <button type="button" class="boton boton-claro boton-chico" data-agregar-fila><?= icono('plus') ?>Agregar medicamento</button>
        </div>
        <?= campo_texto('ObseOrde', 'Observaciones', $d, $er, 2, false, 5000, 'PresObse') ?>
        <?= botones_panel('Guardar prescripción') ?>
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
                <?php if ((int) $p['PresSali'] === 1): ?><span class="etiqueta etiqueta-curso">Fórmula de salida</span><?php endif; ?>
                <small><?= e($p['UsuaDigi']) ?> · <?= e(diag_texto($p['CodiDiag'])) ?></small>
            </div>
            <div class="tabla-contenedor">
            <table class="tabla">
                <thead><tr><th>#</th><th>Medicamento</th><th>Indicación</th><th class="num">Dosis</th><th class="num">N.º dosis</th><th class="num">Total</th><th class="num">Aplicado</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $it): ?>
                    <tr>
                        <td><?= (int) $it['Item'] ?></td>
                        <td><strong><?= e($it['CodiSumi']) ?></strong> · <?= e($it['NombSumi'] ?? '') ?></td>
                        <td><?= e($it['PresMedi']) ?></td>
                        <td class="num"><?= e((float) $it['CantSumi']) ?> <?= e(lista_nombre('UnidMedi', $it['UnidMedi'])) ?></td>
                        <td class="num"><?= (int) $it['NumeDosi'] ?></td>
                        <td class="num"><?= e((float) $it['CantTota']) ?></td>
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
