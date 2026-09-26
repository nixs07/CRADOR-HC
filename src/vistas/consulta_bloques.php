<?php
/**
 * Bloques del formulario de consulta (RipsCons + Antecede + EstaGene + SignVita), con las secciones,
 * campos y etiquetas de SIHOS. En Urgencias y Observación van como acordeones de la pestaña "Consultas";
 * en Consulta Externa cada bloque es una pestaña (1 a 4) del mismo formulario.
 */

/** Anamnesis: Tipo (CE: Actividad), Finalidad, Motivo y Enfermedad actual. */
function consulta_bloque_anamnesis(array $d, array $er, bool $esCE): void
{ ?>
    <div class="rejilla">
        <?= campo_buscador('TipoCons', $esCE ? 'Actividad' : 'Tipo', $d, $er, 'procedimientos') ?>
        <?= campo_lista('FinaCons', 'Finalidad', 'FinaCons', $d, $er) ?>
    </div>
    <?= campo_texto('MotiCons', $esCE ? 'Motivo de Consulta' : 'Motivo', $d, $er, 2, true, 5000, 'ConsMoti') ?>
    <?= campo_texto('EnfeActu', 'Enfermedad Actual', $d, $er, 4, true) ?>
<?php }

/** Antecedentes: lista Sí/No y descripción, en el orden de SIHOS. */
function consulta_bloque_antecedentes(array $d, array $er): void
{ ?>
    <div class="rejilla-antecedentes">
        <?php foreach (ANTECEDENTES as $c => [$etq, $desc]): ?>
            <div class="antecedente">
                <?= campo_sino($c, $etq, $d, $er, 'ante-' . $c) ?>
                <?php if ($desc !== null): ?>
                    <div class="antecedente-desc"><label for="ante-<?= e($desc) ?>">Descripción</label>
                        <input type="text" id="ante-<?= e($desc) ?>" name="<?= e($desc) ?>" value="<?= v($d, $desc) ?>" maxlength="2000" class="<?= ce($er, $desc) ?>"><?= me($er, $desc) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php }

/** Revisión por Sistema y Exámen: revisión, sintomáticos, signos vitales, estado general, perímetros y sistemas. */
function consulta_bloque_revision(array $d, array $er, string $prefijo): void
{ ?>
    <?= campo_texto('ReviSist', 'Revisión por Sistemas', $d, $er, 2, false, 5000, 'ConsRevi') ?>
    <div class="rejilla rejilla-4">
        <?php foreach (SINTOMATICOS as $c => $etq): ?><?= campo_sino($c, $etq, $d, $er) ?><?php endforeach; ?>
    </div>
    <div class="subgrupo">
        <h3><?= icono('heart-pulse') ?>Signos Vitales <small class="legend-nota">Opcional: si escribe alguno se guarda una toma de signos ligada a la consulta</small></h3>
        <?php campos_signos($d, $er, $prefijo, false); ?>
    </div>
    <?= campo_texto('EstaGene', 'Hallazgos Estado General', $d, $er, 2) ?>
    <div class="rejilla rejilla-4">
        <div><label for="PeriAbdo">Perímetro Abdominal (0-200)</label>
            <input type="number" id="PeriAbdo" name="PeriAbdo" value="<?= v($d, 'PeriAbdo') ?>" min="0" max="200" class="<?= ce($er, 'PeriAbdo') ?>"><?= me($er, 'PeriAbdo') ?></div>
        <div><label for="PeriTorx">Perímetro Tórax (0-150)</label>
            <input type="number" id="PeriTorx" name="PeriTorx" value="<?= v($d, 'PeriTorx') ?>" min="0" max="150" class="<?= ce($er, 'PeriTorx') ?>"><?= me($er, 'PeriTorx') ?></div>
    </div>
    <div class="rejilla-examen">
        <?php foreach (EXAMEN_SISTEMAS as $c => [$etq, $desc]): $val = (string) ($d[$c] ?? ''); ?>
            <div class="examen">
                <label for="ex-<?= e($c) ?>"><?= e($etq) ?></label>
                <select id="ex-<?= e($c) ?>" name="<?= e($c) ?>">
                    <option value=""<?= $val === '' ? ' selected' : '' ?>>Sin examinar</option>
                    <option value="1"<?= $val === '1' ? ' selected' : '' ?>>Normal</option>
                    <option value="2"<?= $val === '2' ? ' selected' : '' ?>>Anormal</option>
                </select>
                <input type="text" name="<?= e($desc) ?>" value="<?= v($d, $desc) ?>" maxlength="2000" aria-label="Hallazgos en <?= e($etq) ?>" placeholder="Hallazgos" class="<?= ce($er, $desc) ?>">
                <?= me($er, $desc) ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php }

/** Laboratorios y Diagnósticos: análisis de laboratorios e imágenes y diagnósticos Principal, Rela 1 a 4. */
function consulta_bloque_laboratorios(array $d, array $er): void
{ ?>
    <?= campo_texto('LaboImag', 'Análisis de Laboratorio e Imágenes Diagnósticas', $d, $er, 3) ?>
    <?= tabla_diagnosticos([['Principal', 'CodiDiag', 'TipoDiag'], ['Rela 1', 'CodiRel1', 'TipoDia1'], ['Rela 2', 'CodiRel2', 'TipoDia2'],
                            ['Rela 3', 'CodiRel3', 'TipoDia3'], ['Rela 4', 'CodiRel4', 'TipoDia4']], $d, $er, true, 'cons-') ?>
<?php }

/** Plan de Manejo y Recomendaciones: destino y plan. */
function consulta_bloque_plan(array $d, array $er): void
{ ?>
    <div class="rejilla">
        <?= campo_lista('ConsDest', 'Destino', 'DestSali', $d, $er, false) ?>
    </div>
    <?= campo_texto('ObseReco', 'Plan de Manejo y Recomendaciones', $d, $er, 3) ?>
<?php }

/** Consultas registradas (lista con detalle desplegable). */
function consulta_registros(array $consultas): void
{ ?>
    <h3 class="titulo-tabla"><?= icono('history') ?>Consultas registradas</h3>
    <?php if (!$consultas): ?>
        <?= panel_vacio('No hay consultas registradas.') ?>
    <?php else: ?>
        <div class="registros">
        <?php foreach ($consultas as $c): ?>
            <details class="registro" id="reg-consulta-<?= (int) $c['ConsCons'] ?>">
                <summary>
                    <span class="contador"><?= (int) $c['ConsCons'] ?></span>
                    <strong><?= e(fecha_hora($c['FechCons'] . ' ' . $c['HoraCons'])) ?></strong>
                    <span><?= e(diag_texto($c['CodiDiag'])) ?></span>
                    <small><?= e($c['NombFina'] ?? $c['FinaCons']) ?> · <?= e($c['UsuaCons']) ?></small>
                </summary>
                <dl class="datos">
                    <dt>Motivo</dt><dd class="texto-largo"><?= texto_registro($c['MotiCons']) ?></dd>
                    <dt>Enfermedad actual</dt><dd class="texto-largo"><?= texto_registro($c['EnfeActu']) ?></dd>
                    <?php if ($c['antecedentes']): $an = $c['antecedentes']; ?>
                        <dt>Antecedentes</dt><dd><?php
                            $lista = [];
                            foreach (ANTECEDENTES as $col => [$etq, $desc]) {
                                if ((int) $an[$col] === 1) $lista[] = $etq . ($desc !== null && trim((string) $an[$desc]) !== '' ? ': ' . $an[$desc] : '');
                            }
                            echo $lista ? e(implode(' · ', $lista)) : 'No refiere';
                        ?></dd>
                    <?php endif; ?>
                    <?php if (trim((string) $c['ReviSist']) !== ''): ?><dt>Revisión por sistemas</dt><dd class="texto-largo"><?= texto_registro($c['ReviSist']) ?></dd><?php endif; ?>
                    <?php $sint = array_filter(SINTOMATICOS, fn ($k) => (int) ($c[$k] ?? 0) === 1, ARRAY_FILTER_USE_KEY);
                    if ($sint): ?><dt>Sintomáticos</dt><dd><?= e(implode(' · ', $sint)) ?></dd><?php endif; ?>
                    <?php if ($c['examen']): $ex = $c['examen']; ?>
                        <dt>Examen físico</dt><dd><?= texto_registro($ex['EstaGene']) ?><?php
                            $anor = [];
                            foreach (EXAMEN_SISTEMAS as $col => [$etq, $desc]) {
                                if ((int) $ex[$col] === 2) $anor[] = $etq . ': ' . $ex[$desc];
                            }
                            echo $anor ? '<br><strong>Anormal:</strong> ' . e(implode(' · ', $anor)) : '';
                        ?></dd>
                    <?php endif; ?>
                    <?php if ((int) ($c['PeriAbdo'] ?? 0) > 0 || (int) ($c['PeriTorx'] ?? 0) > 0): ?>
                        <dt>Perímetros</dt><dd>Abdominal <?= (int) $c['PeriAbdo'] ?> cm · Tórax <?= (int) $c['PeriTorx'] ?> cm</dd>
                    <?php endif; ?>
                    <?php if (trim((string) ($c['LaboImag'] ?? '')) !== ''): ?><dt>Laboratorios e imágenes</dt><dd class="texto-largo"><?= texto_registro($c['LaboImag']) ?></dd><?php endif; ?>
                    <dt>Diagnósticos</dt><dd><?= e(diag_texto($c['CodiDiag'])) ?> (<?= e(lista_nombre('TipoDiag', $c['TipoDiag'])) ?>)<?php
                        foreach ([1, 2, 3, 4] as $i) { if (trim((string) $c["CodiRel$i"]) !== '') echo '<br>' . e(diag_texto($c["CodiRel$i"])); } ?></dd>
                    <dt>Destino</dt><dd><?= e(lista_nombre('DestSali', sprintf('%02d', (int) $c['DestSali']))) ?: '—' ?></dd>
                    <dt>Plan de manejo</dt><dd class="texto-largo"><?= texto_registro($c['ObseReco']) ?></dd>
                </dl>
            </details>
        <?php endforeach; ?>
        </div>
    <?php endif;
}

/** Opciones del selector de registros anteriores de la barra. */
function consulta_anteriores(array $consultas): array
{
    $r = [];
    foreach ($consultas as $c) {
        $r['reg-consulta-' . (int) $c['ConsCons']] = (int) $c['ConsCons'] . ' · ' . fecha_hora($c['FechCons'] . ' ' . $c['HoraCons']);
    }
    return $r;
}
