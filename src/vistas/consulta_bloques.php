<?php
/**
 * Bloques del formulario de consulta (RipsCons + Antecede + EstaGene + SignVita), con las secciones, campos,
 * orden y opciones de SIHOS (docs/RECORRIDO_SIHOS.md §3 y §5). En Urgencias y Observación van como acordeones
 * de la pestaña "Consultas", cada uno con su Guardar; en Consulta Externa cada bloque es una pestaña.
 * Los campos de SIHOS sin columna o sin catálogo en CRADOR-HC se muestran deshabilitados (campo_sin_columna).
 */

/** Anamnesis: (Fecha · Hora en la barra) · Profesional · Tipo (CE: Actividad) · Finalidad · Motivo · Enfermedad Actual. */
function consulta_bloque_anamnesis(array $d, array $er, bool $esCE, string $profesional): void
{ ?>
    <div class="rejilla">
        <div class="campo-lectura"><span class="cl-etiqueta">Profesional</span><span class="cl-valor"><?= e($profesional) ?></span></div>
        <?= campo_buscador('TipoCons', $esCE ? 'Actividad' : 'Tipo', $d, $er, 'procedimientos', true) ?>
        <?= campo_lista('FinaCons', 'Finalidad', 'FinaCons', $d, $er) ?>
    </div>
    <?= campo_texto('MotiCons', $esCE ? 'Motivo de Consulta' : 'Motivo', $d, $er, 2, true, 5000, 'ConsMoti') ?>
    <?= campo_texto('EnfeActu', 'Enfermedad Actual', $d, $er, 4, true) ?>
<?php }

/** Lista Si | No | No Sabe | No Corresponde de un antecedente. */
function antecedente_opciones(string $c, array $d, string $etq): string
{
    $v = (string) ($d[$c] ?? '2');
    $h = '<select id="ante-' . e($c) . '" name="' . e($c) . '" aria-label="' . e($etq) . '">';
    foreach (ANTE_OPCIONES as $k => $n) {
        $h .= '<option value="' . e($k) . '"' . ((string) $k === $v ? ' selected' : '') . '>' . e($n) . '</option>';
    }
    return $h . '</select>';
}

/**
 * Antecedentes en el orden de SIHOS, con los campos adicionales de cada uno. Los que no tienen columna en
 * Antecede (método de planificación, parentesco, ventanas, tipo de alergia...) se ven deshabilitados.
 * Consulta Externa: sin Andrológicos ni Conciliación, y con FUR y Fecha Probable del Parto en Obstétricos.
 */
function consulta_bloque_antecedentes(array $d, array $er, bool $esCE): void
{
    $extras = [
        'MetoPlan' => [['Método', 'select']],
        'Familiar' => [['Parentesco', 'select'], ['Diagnóstico CIE-10', 'text']],
        'Patologi' => [['Hipertensión crónica, Diabetes, LES, Síndrome metabólico, ERC, Trombofilia/TVP, Anemia de células falciformes', 'text']],
        'Obstetri' => [['Preeclampsia en gestación previa, Sepsis en gestaciones previas', 'text']],
        'AlerSiNo' => [['Tipo de Alergia', 'select'], ['Alergia a Medicamentos', 'text']],
        'Farmacol' => [['Tipo Medicamento', 'select']],
        'FactRies' => [['Tipo', 'select']],
    ];
    ?>
    <div class="tabla-antecedentes">
        <div class="ta-cabeza"><span>Antecedente</span><span>Si | No | No Sabe | No Corresponde</span><span>Descripción</span></div>
        <?php foreach (antecedentes_modulo($esCE) as $c => [$etq, $desc]): ?>
            <div class="ta-fila">
                <label class="ta-etiqueta" for="ante-<?= e($c) ?>"><?= e($etq) ?></label>
                <?= antecedente_opciones($c, $d, $etq) ?>
                <div class="ta-desc">
                    <?php if ($desc !== null): ?>
                        <input type="text" id="ante-<?= e($desc) ?>" name="<?= e($desc) ?>" value="<?= v($d, $desc) ?>" maxlength="2000"
                               aria-label="Descripción de <?= e($etq) ?>" class="<?= ce($er, $desc) ?>"><?= me($er, $desc) ?>
                    <?php endif; ?>
                    <?php if ($c === 'Obstetri' && $esCE): ?>
                        <div class="rejilla rejilla-4">
                            <div><label for="FechRegl">FUR</label><input type="date" id="FechRegl" name="FechRegl" value="<?= v($d, 'FechRegl') ?>" class="<?= ce($er, 'FechRegl') ?>"><?= me($er, 'FechRegl') ?></div>
                            <div><label for="FechPart">Fecha Probable del Parto</label><input type="date" id="FechPart" name="FechPart" value="<?= v($d, 'FechPart') ?>" class="<?= ce($er, 'FechPart') ?>"><?= me($er, 'FechPart') ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($extras[$c])): ?>
                        <div class="rejilla rejilla-4">
                            <?php foreach ($extras[$c] as [$et, $tipo]): ?><?= campo_sin_columna($et, $tipo) ?><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="ta-fila">
            <span class="ta-etiqueta">Reconciliación Medicamentosa</span>
            <?= campo_sin_columna('Reconciliación Medicamentosa', 'checkbox') ?>
            <div class="ta-desc">
                <div class="rejilla rejilla-4">
                    <?php foreach (['Medicamento', 'Dosis', 'Frecuencia', 'Vía adminis.', 'Nota'] as $et): ?><?= campo_sin_columna($et, 'text') ?><?php endforeach; ?>
                </div>
                <p class="nota-campo">Tabla RecoMedi de SIHOS: falta su estructura (docs/consultas_sihos.sql).</p>
            </div>
        </div>
    </div>
<?php }

/** Revisión por Sistema y Exámen, en el orden del módulo (Consulta Externa con Índice Cintura-Cadera). */
function consulta_bloque_revision(array $d, array $er, string $prefijo, bool $esCE): void
{
    $sino = fn ($c) => campo_sino($c, SINTOMATICOS[$c], $d, $er);
    ?>
    <?= campo_texto('ReviSist', $esCE ? 'Revisión Por Sistema' : 'Revisión por Sistemas', $d, $er, 2, false, 5000, 'ConsRevi') ?>
    <div class="rejilla rejilla-4">
        <?php if ($esCE): ?>
            <?= $sino('SintResp') ?><?= $sino('SintNerv') ?><?= $sino('SintPiel') ?>
            <?= campo_sin_columna('Tuberculosis Multidrogoresistente', 'select', '', 'No') ?>
            <?= campo_sin_columna('Lepra', 'select', '', 'No') ?>
        <?php else: ?>
            <?= $sino('SintResp') ?>
            <?= campo_sin_columna('Tuberculosis Multidrogoresistente', 'select', '', 'No') ?>
            <?= $sino('SintPiel') ?>
            <?= campo_sin_columna('Lepra', 'select', '', 'No') ?>
            <?= $sino('SintNerv') ?>
        <?php endif; ?>
        <?= campo_sin_columna('Tipo de Discapacidad', 'select') ?>
    </div>
    <div class="subgrupo">
        <h3><?= icono('heart-pulse') ?>Signos Vitales</h3>
        <?php campos_signos($d, $er, $prefijo, false); ?>
    </div>
    <?= campo_texto('EstaGene', $esCE ? 'Estado General' : 'Hallazgos Estado General', $d, $er, 2) ?>
    <div class="rejilla rejilla-4">
        <div><label for="PeriAbdo">Perímetro Abdominal (0-200)</label>
            <input type="number" id="PeriAbdo" name="PeriAbdo" value="<?= v($d, 'PeriAbdo') ?>" min="0" max="200" class="<?= ce($er, 'PeriAbdo') ?>"><?= me($er, 'PeriAbdo') ?></div>
        <div><label for="PeriTorx">Perímetro Tórax (0-150)</label>
            <input type="number" id="PeriTorx" name="PeriTorx" value="<?= (int) ($d['PeriTorx'] ?? 0) ?: '' ?>" min="0" max="150" class="<?= ce($er, 'PeriTorx') ?>"><?= me($er, 'PeriTorx') ?></div>
    </div>
    <?php if ($esCE): ?>
        <div class="subgrupo" data-icc>
            <h3><?= icono('activity') ?>Índice Cintura-Cadera</h3>
            <div class="rejilla rejilla-4">
                <div><label for="PeriCint">Perímetro Cintura</label><input type="number" id="PeriCint" name="PeriCint" value="<?= (float) ($d['PeriCint'] ?? 0) ?: '' ?>" step="any" min="0" class="<?= ce($er, 'PeriCint') ?>"><?= me($er, 'PeriCint') ?></div>
                <div><label for="PeriCade">Perímetro Cadera</label><input type="number" id="PeriCade" name="PeriCade" value="<?= (float) ($d['PeriCade'] ?? 0) ?: '' ?>" step="any" min="0" class="<?= ce($er, 'PeriCade') ?>"><?= me($er, 'PeriCade') ?></div>
                <div class="calculado"><span>ICC</span><strong data-calc="icc">—</strong></div>
            </div>
        </div>
    <?php endif; ?>
    <div class="rejilla-examen">
        <?php foreach (examen_sistemas($esCE) as $c => [$etq, $desc]):
            $val = array_key_exists($c, $d) ? ($d[$c] === null ? '' : (string) $d[$c]) : '1'; ?>
            <div class="examen">
                <label for="ex-<?= e($c) ?>"><?= e($etq) ?></label>
                <select id="ex-<?= e($c) ?>" name="<?= e($c) ?>">
                    <?php foreach (EXAMEN_OPCIONES as $k => $n): ?>
                        <option value="<?= e($k) ?>"<?= (string) $k === $val ? ' selected' : '' ?>><?= e($n) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="<?= e($desc) ?>" value="<?= v($d, $desc) ?>" maxlength="2000" aria-label="Hallazgos en <?= e($etq) ?>" class="<?= ce($er, $desc) ?>">
                <?= me($er, $desc) ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php }

/** Laboratorios y Diagnósticos: análisis y diagnósticos Principal y Rela 1 a 4 (con Tipo). */
function consulta_bloque_laboratorios(array $d, array $er): void
{ ?>
    <?= campo_texto('LaboImag', 'Análisis de Laboratorio e Imágenes Diagnósticas', $d, $er, 3) ?>
    <?= tabla_diagnosticos([['Principal', 'CodiDiag', 'TipoDiag'], ['Rela 1', 'CodiRel1', 'TipoDia1'], ['Rela 2', 'CodiRel2', 'TipoDia2'],
                            ['Rela 3', 'CodiRel3', 'TipoDia3'], ['Rela 4', 'CodiRel4', 'TipoDia4']], $d, $er, false, 'cons-') ?>
<?php }

/**
 * Plan de Manejo y Recomendaciones del acordeón (Urgencias/Observación): Destino · Conducta · Plan. En Consulta
 * Externa (pestaña 7) va Destino fijo, el plan y el bloque Código Dorado (como la pestaña Plan de Manejo).
 */
function consulta_bloque_plan(array $d, array $er, bool $esCE): void
{ ?>
    <div class="rejilla">
        <?php if ($esCE): ?>
            <?= campo_sin_columna('Destino', 'select', '', 'Siempre 4 en Consulta Externa') ?>
        <?php else: ?>
            <?= campo_lista('ConsDest', 'Destino', 'DestSali', $d, $er, false) ?>
            <?= campo_sin_columna('Conducta', 'select') ?>
        <?php endif; ?>
    </div>
    <?= campo_texto('ObseReco', $esCE ? 'Recomendaciones y Plan de Manejo' : 'Plan de Manejo y Recomendaciones', $d, $er, 4, !$esCE) ?>
<?php }

/** Bloque Código Dorado (RipsCons.Accinme, ContCuid, EstaCodo, Especif, ObserCd). */
function bloque_codigo_dorado(array $d, array $er, bool $editable = true): void
{ ?>
    <div class="subgrupo">
        <h3><?= icono('triangle-alert') ?>Código Dorado</h3>
        <div class="rejilla">
            <?= campo_sin_columna('Acciones inmediatas', 'select') ?>
            <?= campo_sin_columna('Continuidad del cuidado', 'select') ?>
            <?= campo_sin_columna('Estado (Riesgo Alto / Moderado / Bajo)', 'select') ?>
        </div>
        <?php if ($editable): ?>
            <?= campo_texto('Especif', 'Especifique', $d, $er, 2) ?>
            <?= campo_texto('ObserCd', 'Observaciones', $d, $er, 2) ?>
        <?php else: ?>
            <?= campo_sin_columna('Especifique', 'textarea') ?><?= campo_sin_columna('Observaciones', 'textarea') ?>
        <?php endif; ?>
    </div>
<?php }

/** Consultas registradas (lista con detalle desplegable). */
function consulta_registros(array $consultas): void
{ ?>
    <h3 class="titulo-tabla"><?= icono('history') ?>Consultas registradas</h3>
    <?php if (!$consultas): ?>
        <?= panel_vacio('No hay consultas registradas.') ?>
    <?php else: ?>
        <div class="registros">
        <?php foreach ($consultas as $c): $cerrada = ($c['FechCier'] ?? '0000-00-00') !== '0000-00-00'; ?>
            <details class="registro" id="reg-consulta-<?= (int) $c['ConsCons'] ?>">
                <summary>
                    <span class="contador"><?= (int) $c['ConsCons'] ?></span>
                    <strong><?= e(fecha_hora($c['FechCons'] . ' ' . $c['HoraCons'])) ?></strong>
                    <span><?= e(diag_texto($c['CodiDiag'])) ?></span>
                    <span class="etiqueta<?= $cerrada ? ' etiqueta-cerrada' : ' etiqueta-abierta' ?>"><?= $cerrada ? 'Cerrada' : 'Abierta' ?></span>
                    <small><?= e($c['NombFina'] ?? $c['FinaCons']) ?> · <?= e($c['UsuaCons']) ?></small>
                </summary>
                <dl class="datos">
                    <dt>Tipo</dt><dd><?= e($c['TipoCons'] . ' · ' . (procedimiento_nombre($c['TipoCons']) ?? '')) ?></dd>
                    <dt>Motivo</dt><dd class="texto-largo"><?= texto_registro($c['MotiCons']) ?></dd>
                    <dt>Enfermedad actual</dt><dd class="texto-largo"><?= texto_registro($c['EnfeActu']) ?></dd>
                    <?php if ($c['antecedentes']): $an = $c['antecedentes']; ?>
                        <dt>Antecedentes</dt><dd><?php
                            $lista = [];
                            foreach (ANTECEDENTES as $col => [$etq, $desc]) {
                                if ((int) ($an[$col] ?? 0) === 1) $lista[] = $etq . ($desc !== null && trim((string) $an[$desc]) !== '' ? ': ' . $an[$desc] : '');
                            }
                            echo $lista ? e(implode(' · ', $lista)) : 'No refiere';
                        ?></dd>
                    <?php endif; ?>
                    <?php if (trim((string) $c['ReviSist']) !== ''): ?><dt>Revisión por sistemas</dt><dd class="texto-largo"><?= texto_registro($c['ReviSist']) ?></dd><?php endif; ?>
                    <?php if ($c['examen']): $ex = $c['examen']; ?>
                        <dt>Examen físico</dt><dd><?= texto_registro($ex['EstaGene']) ?><?php
                            $anor = [];
                            foreach (EXAMEN_SISTEMAS as $col => [$etq, $desc]) {
                                if ((int) $ex[$col] === 2) $anor[] = $etq . ': ' . $ex[$desc];
                            }
                            echo $anor ? '<br><strong>Anormal:</strong> ' . e(implode(' · ', $anor)) : '';
                        ?></dd>
                    <?php endif; ?>
                    <?php if (trim((string) ($c['LaboImag'] ?? '')) !== ''): ?><dt>Laboratorios e imágenes</dt><dd class="texto-largo"><?= texto_registro($c['LaboImag']) ?></dd><?php endif; ?>
                    <dt>Diagnósticos</dt><dd><?= e(diag_texto($c['CodiDiag'])) ?><?php
                        foreach ([1, 2, 3, 4] as $i) { if (trim((string) $c["CodiRel$i"]) !== '') echo '<br>' . e(diag_texto($c["CodiRel$i"])); } ?></dd>
                    <dt>Plan de manejo</dt><dd class="texto-largo"><?= texto_registro($c['ObseReco']) ?></dd>
                    <?php if ($cerrada): ?><dt>Cerrada</dt><dd><?= e(fecha_hora($c['FechCier'] . ' ' . $c['HoraCier'])) ?> · <?= e($c['UsuaCier']) ?></dd><?php endif; ?>
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

/** Duración de la consulta (desde su fecha y hora hasta el cierre o ahora). */
function consulta_duracion(?array $c): string
{
    if (!$c) {
        return '—';
    }
    $fin = ($c['FechCier'] ?? '0000-00-00') !== '0000-00-00' ? strtotime($c['FechCier'] . ' ' . $c['HoraCier']) : time();
    $seg = max(0, $fin - strtotime($c['FechCons'] . ' ' . $c['HoraCons']));
    return sprintf('%d h %02d min', intdiv($seg, 3600), intdiv($seg % 3600, 60));
}
