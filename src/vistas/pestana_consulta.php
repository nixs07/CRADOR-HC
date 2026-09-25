<?php
/**
 * Pestaña 2. Consultas: motivo, enfermedad actual, antecedentes, examen físico, diagnósticos y plan
 * (RipsCons + Antecede + EstaGene). Variables: $a, $editable, $aqui, $tab, $F, $E, $consultas.
 */
$d = $F['consulta'] ?? ['FechCons' => date('Y-m-d'), 'HoraCons' => date('H:i'), 'FinaCons' => '10', 'TipoDiag' => '1',
                        'CodiDiag' => $a['DiagIngr'], 'MotiCons' => $triage['MotiCons'] ?? ''];
$er = $E['consulta'] ?? [];
?>
<?= panel_abrir('consulta', '2. Consultas', 'stethoscope', $tab, count($consultas) . ' ' . (count($consultas) === 1 ? 'consulta registrada' : 'consultas registradas')) ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo">
    <?= errores_resumen($er) ?>
    <form method="post" action="<?= e($aqui) ?>&amp;tab=consulta" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="consulta">
        <h3 class="titulo-form"><?= icono('plus') ?>Nueva consulta <span class="legend-nota">Anamnesis, antecedentes, examen físico y diagnóstico</span></h3>
        <div class="rejilla">
            <?= campos_fecha_hora('FechCons', 'HoraCons', $d, $er) ?>
            <?= campo_lista('FinaCons', 'Finalidad de la consulta', 'FinaCons', $d, $er) ?>
            <?= campo_buscador('TipoCons', 'Código de la consulta (CUPS)', $d, $er, 'procedimientos') ?>
        </div>
        <?= campo_texto('MotiCons', 'Motivo de consulta', $d, $er, 2, true, 5000, 'ConsMoti') ?>
        <?= campo_texto('EnfeActu', 'Enfermedad actual', $d, $er, 4, true) ?>
        <?= campo_texto('ReviSist', 'Revisión por sistemas', $d, $er, 2, false, 5000, 'ConsRevi') ?>

        <div class="subgrupo">
            <h3><?= icono('history') ?>Antecedentes <small class="legend-nota">Marque "Sí" y describa</small></h3>
            <div class="rejilla-sino">
                <?php foreach (ANTECEDENTES + ['AlerSiNo' => ['Alérgicos', 'AlerDesc']] as $c => [$etq, $desc]): $si = (string) ($d[$c] ?? '') === '1'; ?>
                    <div class="sino">
                        <span class="sino-etiqueta"><?= e($etq) ?></span>
                        <label class="opcion"><input type="radio" name="<?= e($c) ?>" value="1"<?= $si ? ' checked' : '' ?>> Sí</label>
                        <label class="opcion"><input type="radio" name="<?= e($c) ?>" value="2"<?= $si ? '' : ' checked' ?>> No refiere</label>
                        <input type="text" name="<?= e($desc) ?>" value="<?= v($d, $desc) ?>" maxlength="2000" aria-label="Descripción de antecedentes <?= e($etq) ?>" placeholder="Descripción" class="<?= ce($er, $desc) ?>">
                        <?= me($er, $desc) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="subgrupo">
            <h3><?= icono('activity') ?>Examen físico <small class="legend-nota">Normal, anormal (describa) o sin examinar</small></h3>
            <?= campo_texto('EstaGene', 'Estado general', $d, $er, 2) ?>
            <div class="rejilla-sino">
                <?php foreach (EXAMEN_SISTEMAS as $c => [$etq, $desc]): $val = (string) ($d[$c] ?? ''); ?>
                    <div class="sino">
                        <span class="sino-etiqueta"><?= e($etq) ?></span>
                        <label class="opcion"><input type="radio" name="<?= e($c) ?>" value="1"<?= $val === '1' ? ' checked' : '' ?>> Normal</label>
                        <label class="opcion"><input type="radio" name="<?= e($c) ?>" value="2"<?= $val === '2' ? ' checked' : '' ?>> Anormal</label>
                        <label class="opcion"><input type="radio" name="<?= e($c) ?>" value=""<?= $val === '' ? ' checked' : '' ?>> Sin examinar</label>
                        <input type="text" name="<?= e($desc) ?>" value="<?= v($d, $desc) ?>" maxlength="2000" aria-label="Hallazgos en <?= e($etq) ?>" placeholder="Hallazgos" class="<?= ce($er, $desc) ?>">
                        <?= me($er, $desc) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="subgrupo">
            <h3><?= icono('file-text') ?>Diagnósticos</h3>
            <div class="rejilla">
                <?= campo_buscador('CodiDiag', 'Diagnóstico principal (CIE-10)', $d, $er, 'diagnosticos', true, '', 'ConsDiag') ?>
                <?= campo_lista('TipoDiag', 'Tipo de diagnóstico', 'TipoDiag', $d, $er) ?>
                <?= campo_buscador('CodiRel1', 'Relacionado 1', $d, $er, 'diagnosticos') ?>
                <?= campo_lista('TipoDia1', 'Tipo relacionado 1', 'TipoDiag', $d, $er, false) ?>
                <?= campo_buscador('CodiRel2', 'Relacionado 2', $d, $er, 'diagnosticos') ?>
                <?= campo_lista('TipoDia2', 'Tipo relacionado 2', 'TipoDiag', $d, $er, false) ?>
            </div>
        </div>
        <?= campo_texto('ObseReco', 'Plan de manejo y recomendaciones', $d, $er, 3) ?>
        <?= botones_panel('Guardar consulta') ?>
    </form>
    </div>
<?php endif; ?>

<h3 class="titulo-tabla"><?= icono('history') ?>Consultas registradas</h3>
<?php if (!$consultas): ?>
    <?= panel_vacio('No hay consultas registradas.') ?>
<?php else: ?>
    <div class="registros">
    <?php foreach ($consultas as $c): ?>
        <details class="registro">
            <summary>
                <span class="contador"><?= (int) $c['ConsCons'] ?></span>
                <strong><?= e(fecha_hora($c['FechCons'] . ' ' . $c['HoraCons'])) ?></strong>
                <span><?= e(diag_texto($c['CodiDiag'])) ?></span>
                <small><?= e($c['NombFina'] ?? $c['FinaCons']) ?> · <?= e($c['UsuaCons']) ?></small>
            </summary>
            <dl class="datos">
                <dt>Motivo de consulta</dt><dd class="texto-largo"><?= texto_registro($c['MotiCons']) ?></dd>
                <dt>Enfermedad actual</dt><dd class="texto-largo"><?= texto_registro($c['EnfeActu']) ?></dd>
                <?php if (trim((string) $c['ReviSist']) !== ''): ?><dt>Revisión por sistemas</dt><dd class="texto-largo"><?= texto_registro($c['ReviSist']) ?></dd><?php endif; ?>
                <?php if ($c['antecedentes']): $an = $c['antecedentes']; ?>
                    <dt>Antecedentes</dt><dd><?php
                        $lista = [];
                        foreach (ANTECEDENTES + ['AlerSiNo' => ['Alérgicos', 'AlerDesc']] as $col => [$etq, $desc]) {
                            if ((int) $an[$col] === 1) $lista[] = $etq . ': ' . $an[$desc];
                        }
                        echo $lista ? e(implode(' · ', $lista)) : 'No refiere';
                    ?></dd>
                <?php endif; ?>
                <?php if ($c['examen']): $ex = $c['examen']; ?>
                    <dt>Examen físico</dt><dd><?= texto_registro($ex['EstaGene']) ?><?php
                        $anor = [];
                        foreach (EXAMEN_SISTEMAS as $col => [$etq, $desc]) {
                            if ((int) $ex[$col] === 2) $anor[] = $etq . ': ' . $ex[$desc];
                        }
                        echo $anor ? '<br><strong>Anormal:</strong> ' . e(implode(' · ', $anor)) : '';
                    ?></dd>
                <?php endif; ?>
                <dt>Diagnósticos</dt><dd><?= e(diag_texto($c['CodiDiag'])) ?> (<?= e(lista_nombre('TipoDiag', $c['TipoDiag'])) ?>)<?php
                    foreach ([1, 2, 3, 4] as $i) { if (trim((string) $c["CodiRel$i"]) !== '') echo '<br>' . e(diag_texto($c["CodiRel$i"])); } ?></dd>
                <dt>Plan de manejo</dt><dd class="texto-largo"><?= texto_registro($c['ObseReco']) ?></dd>
            </dl>
        </details>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
</section>
