<?php
/**
 * Evolución (Urgencias 8, Observación 2): EvolInte, con los campos de SIHOS en su orden: Subjetivo, Objetivo,
 * fila de Signos Vitales (toma de SignVita ligada con ConsEvol), Diagnósticos Principal y Rela 1-4, Análisis,
 * Plan de Manejo y Controles Especiales. No existe en Consulta Externa.
 * Variables: $a, $mod, $editable, $aqui, $tab, $F, $E, $evoluciones, $u.
 */
$d = $F['evolucion'] ?? ['FechEvol' => date('Y-m-d'), 'HoraEvol' => date('H:i'), 'EvolTipoDiag' => '2', 'EvolDiag' => $a['DiagIngr']];
$d += is_array($d['signos'] ?? null) ? $d['signos'] : [];
$er = $E['evolucion'] ?? [];
$anteriores = [];
foreach ($evoluciones as $v) {
    $anteriores['reg-evol-' . (int) $v['ConsEvol']] = (int) $v['ConsEvol'] . ' · ' . fecha_hora($v['FechEvol'] . ' ' . $v['HoraEvol']);
}
?>
<?= panel_abrir('evolucion', pestana_titulo($mod, 'evolucion'), 'history', $tab, count($evoluciones) . ' ' . (count($evoluciones) === 1 ? 'evolución' : 'evoluciones')) ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo">
    <?= errores_resumen($er) ?>
    <form method="post" action="<?= e($aqui) ?>&amp;tab=evolucion" class="formulario formulario-panel" data-signos data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="evolucion">
        <?= barra_registro('Nueva', $anteriores, 'FechEvol', 'HoraEvol', $d, $er, '', $u['Nombre'] ?? $u['Login']) ?>
        <?= campo_texto('Subjetivo', 'Subjetivo', $d, $er, 3) ?>
        <?= campo_texto('Objetivo', 'Objetivo', $d, $er, 3) ?>
        <div class="subgrupo">
            <h3><?= icono('heart-pulse') ?>Signos Vitales <small class="legend-nota">Opcional: si escribe alguno se guarda una toma ligada a la evolución</small></h3>
            <?php campos_signos($d, $er, 'evol-', false); ?>
        </div>
        <h3 class="subtitulo-panel"><?= icono('file-text') ?>Diagnósticos</h3>
        <?= tabla_diagnosticos([['Principal', 'EvolDiag', 'EvolTipoDiag'], ['Rela 1', 'EvolRel1', 'EvolTipoRel1'], ['Rela 2', 'EvolRel2', 'EvolTipoRel2'],
                                ['Rela 3', 'EvolRel3', 'EvolTipoRel3'], ['Rela 4', 'EvolRel4', 'EvolTipoRel4']], $d, $er, true, 'evol-') ?>
        <?= campo_texto('Analisis', 'Análisis', $d, $er, 3, true) ?>
        <?= campo_texto('PlanMane', 'Plan de Manejo', $d, $er, 3, true) ?>
        <div class="rejilla">
            <?= campo_buscador('EvolProc', 'Código de la atención (CUPS, opcional)', $d, $er, 'procedimientos') ?>
            <div><span class="etiqueta-campo">Controles Especiales</span>
                <div class="casillas"><?= casilla('ContSign', 'Signos Vitales', $d) ?><?= casilla('ContLiqu', 'Líquidos', $d) ?></div></div>
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
        <details class="registro" id="reg-evol-<?= (int) $v['ConsEvol'] ?>"<?= $v === $evoluciones[0] ? ' open' : '' ?>>
            <summary><span class="contador"><?= (int) $v['ConsEvol'] ?></span>
                <strong><?= e(fecha_hora($v['FechEvol'] . ' ' . $v['HoraEvol'])) ?></strong>
                <span><?= e(diag_texto($v['CodiDiag'])) ?><?php foreach ([1, 2, 3, 4] as $i) { if (trim((string) ($v["CodiRel$i"] ?? '')) !== '') echo ' · ' . e($v["CodiRel$i"]); } ?></span><small><?= e($v['UsuaDigi']) ?></small></summary>
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
