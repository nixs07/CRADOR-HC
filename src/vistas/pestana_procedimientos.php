<?php
/**
 * Pestaña 6. Procedimientos realizados (HojaProc).
 * Variables: $a, $editable, $aqui, $tab, $F, $E, $procedimientos.
 */
$d = $F['procedimiento'] ?? ['FechProc' => date('Y-m-d'), 'HoraProc' => date('H:i'), 'CodiFina' => '2', 'ProcTipoDiag' => '1', 'DiagPrin' => $a['DiagIngr']];
$er = $E['procedimiento'] ?? [];
?>
<?= panel_abrir('procedimientos', '6. Procedimientos', 'activity', $tab, count($procedimientos) . ' ' . (count($procedimientos) === 1 ? 'procedimiento realizado' : 'procedimientos realizados')) ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo">
    <?= errores_resumen($er) ?>
    <form method="post" action="<?= e($aqui) ?>&amp;tab=procedimientos" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="procedimiento">
        <h3 class="titulo-form"><?= icono('plus') ?>Registrar procedimiento realizado</h3>
        <div class="rejilla">
            <?= campos_fecha_hora('FechProc', 'HoraProc', $d, $er) ?>
            <?= campo_buscador('CodiProc', 'Procedimiento (CUPS)', $d, $er, 'procedimientos', true) ?>
            <?= campo_lista('CodiFina', 'Finalidad', 'FinaProc', $d, $er) ?>
            <?= campo_buscador('DiagPrin', 'Diagnóstico principal (CIE-10)', $d, $er, 'diagnosticos', true) ?>
            <?= campo_lista('ProcTipoDiag', 'Tipo de diagnóstico', 'TipoDiag', $d + ['ProcTipoDiag' => $d['TipoDiag'] ?? '1'], $er) ?>
            <?= campo_buscador('DiagRela', 'Diagnóstico relacionado', $d, $er, 'diagnosticos') ?>
        </div>
        <?= campo_texto('IndiAdic', 'Descripción / observaciones', $d, $er, 3) ?>
        <?= botones_panel('Guardar procedimiento') ?>
    </form>
    </div>
<?php endif; ?>

<h3 class="titulo-tabla"><?= icono('history') ?>Procedimientos realizados</h3>
<?php if (!$procedimientos): ?>
    <?= panel_vacio('No hay procedimientos registrados.') ?>
<?php else: ?>
    <div class="tabla-contenedor">
    <table class="tabla">
        <thead><tr><th>#</th><th>Fecha</th><th>Procedimiento</th><th>Finalidad</th><th>Diagnóstico</th><th>Observaciones</th><th>Registró</th></tr></thead>
        <tbody>
        <?php foreach ($procedimientos as $p): ?>
            <tr>
                <td><span class="contador"><?= (int) $p['ConsHoPr'] ?></span></td>
                <td class="sin-salto"><?= e(fecha_hora($p['FechProc'] . ' ' . $p['HoraProc'])) ?></td>
                <td><strong><?= e($p['CodiProc']) ?></strong> · <?= e($p['NombProc'] ?? '') ?></td>
                <td><?= e($p['NombFina'] ?? $p['CodiFina']) ?></td>
                <td><?= e(diag_texto($p['DiagPrin'])) ?></td>
                <td><?= e($p['IndiAdic']) ?></td>
                <td><?= e($p['UsuaAsis']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</section>
