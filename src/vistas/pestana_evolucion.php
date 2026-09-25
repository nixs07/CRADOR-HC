<?php
/**
 * Pestaña 8. Evolución (EvolInte), formato SOAP. No aplica en Consulta Externa.
 * Variables: $a, $editable, $aqui, $tab, $F, $E, $evoluciones.
 */
$d = $F['evolucion'] ?? ['FechEvol' => date('Y-m-d'), 'HoraEvol' => date('H:i'), 'EvolTipoDiag' => '2', 'EvolDiag' => $a['DiagIngr']];
$er = $E['evolucion'] ?? [];
?>
<?= panel_abrir('evolucion', '8. Evolución', 'history', $tab, count($evoluciones) . ' ' . (count($evoluciones) === 1 ? 'evolución' : 'evoluciones')) ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo">
    <?= errores_resumen($er) ?>
    <form method="post" action="<?= e($aqui) ?>&amp;tab=evolucion" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="evolucion">
        <h3 class="titulo-form"><?= icono('plus') ?>Nueva evolución <span class="legend-nota">Subjetivo, objetivo, análisis y plan</span></h3>
        <div class="rejilla">
            <?= campos_fecha_hora('FechEvol', 'HoraEvol', $d, $er) ?>
            <?= campo_buscador('EvolProc', 'Código de la atención (CUPS, opcional)', $d, $er, 'procedimientos') ?>
        </div>
        <?= campo_texto('Subjetivo', 'Subjetivo', $d, $er, 3) ?>
        <?= campo_texto('Objetivo', 'Objetivo', $d, $er, 3) ?>
        <?= campo_texto('Analisis', 'Análisis', $d, $er, 3, true) ?>
        <?= campo_texto('PlanMane', 'Plan de manejo', $d, $er, 3, true) ?>
        <div class="rejilla">
            <?= campo_buscador('EvolDiag', 'Diagnóstico (CIE-10)', $d, $er, 'diagnosticos', true) ?>
            <?= campo_lista('EvolTipoDiag', 'Tipo de diagnóstico', 'TipoDiag', $d, $er) ?>
            <?= campo_buscador('EvolRel1', 'Relacionado', $d, $er, 'diagnosticos') ?>
            <?= campo_lista('EvolTipoRel1', 'Tipo relacionado', 'TipoDiag', $d, $er, false) ?>
        </div>
        <div class="casillas">
            <label class="opcion"><input type="checkbox" name="ContSign" value="1"<?= !empty($d['ContSign']) ? ' checked' : '' ?>> Control de signos vitales</label>
            <label class="opcion"><input type="checkbox" name="ContLiqu" value="1"<?= !empty($d['ContLiqu']) ? ' checked' : '' ?>> Control de líquidos</label>
        </div>
        <?= botones_panel('Guardar evolución') ?>
    </form>
    </div>
<?php endif; ?>

<h3 class="titulo-tabla"><?= icono('history') ?>Evoluciones registradas</h3>
<?php if (!$evoluciones): ?>
    <?= panel_vacio('No hay evoluciones registradas.') ?>
<?php else: ?>
    <div class="registros">
    <?php foreach ($evoluciones as $v): ?>
        <details class="registro"<?= $v === $evoluciones[0] ? ' open' : '' ?>>
            <summary><span class="contador"><?= (int) $v['ConsEvol'] ?></span>
                <strong><?= e(fecha_hora($v['FechEvol'] . ' ' . $v['HoraEvol'])) ?></strong>
                <span><?= e(diag_texto($v['CodiDiag'])) ?></span><small><?= e($v['UsuaDigi']) ?></small></summary>
            <dl class="datos">
                <dt>Subjetivo</dt><dd class="texto-largo"><?= texto_registro($v['Subjetivo']) ?></dd>
                <dt>Objetivo</dt><dd class="texto-largo"><?= texto_registro($v['Objetivo']) ?></dd>
                <dt>Análisis</dt><dd class="texto-largo"><?= texto_registro($v['Analisis']) ?></dd>
                <dt>Plan de manejo</dt><dd class="texto-largo"><?= texto_registro($v['PlanMane']) ?></dd>
                <dt>Controles</dt><dd><?= (int) $v['ContSign'] ? 'Signos vitales. ' : '' ?><?= (int) $v['ContLiqu'] ? 'Líquidos.' : '' ?><?= !(int) $v['ContSign'] && !(int) $v['ContLiqu'] ? '—' : '' ?></dd>
            </dl>
        </details>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
</section>
