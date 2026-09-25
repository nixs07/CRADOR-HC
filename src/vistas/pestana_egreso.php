<?php
/**
 * Pestaña 9. Egreso (SaliInte) y cierre de la admisión. En Consulta Externa es "Cerrar atención":
 * solo marca la admisión como cerrada (ver supuestos en docs/REGLAS.md).
 * Variables: $a, $editable, $aqui, $tab, $F, $E, $egreso, $mod.
 */
$esCE = $mod['clave'] === 'ce';
$d = $F['egreso'] ?? ['FechSali' => date('Y-m-d'), 'HoraSali' => date('H:i'), 'CausSali' => '1', 'DestSali' => '01', 'EstaSali' => '1',
                      'TipoEgre' => '1', 'EgreTipoDiag' => '2', 'DiagEgre' => $a['DiagIngr']];
$er = $E['egreso'] ?? [];
// Codigo del estado "muerto" en el catalogo EstaSali (para mostrar los datos de muerte)
$codMuerto = '';
foreach (lista('EstaSali') as $c => $n) {
    if (estado_salida_muerto($c)) { $codMuerto = (string) $c; break; }
}
?>
<?= panel_abrir('egreso', $esCE ? '9. Cerrar atención' : '9. Egreso', 'log-out', $tab,
    (int) $a['Cerrado'] === 1 ? 'Admisión cerrada' : ($esCE ? 'Cierra la atención de Consulta Externa' : 'Salida del paciente y cierre de la admisión')) ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo">
    <?= errores_resumen($er) ?>
    <form method="post" action="<?= e($aqui) ?>&amp;tab=egreso" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="egreso">
        <div class="rejilla">
            <?= campos_fecha_hora('FechSali', 'HoraSali', $d, $er) ?>
            <?php if (!$esCE): ?>
                <?= campo_lista('CausSali', 'Causa de salida', 'CausSali', $d, $er) ?>
                <?= campo_lista('DestSali', 'Destino', 'DestSali', $d, $er) ?>
                <?= campo_lista('EstaSali', 'Estado a la salida', 'EstaSali', $d, $er) ?>
                <?= campo_lista('TipoEgre', 'Tipo de egreso', 'TipoEgre', $d, $er) ?>
            <?php endif; ?>
        </div>
        <?php if (!$esCE): ?>
            <div class="rejilla">
                <?= campo_buscador('DiagEgre', 'Diagnóstico de egreso (CIE-10)', $d, $er, 'diagnosticos', true) ?>
                <?= campo_lista('EgreTipoDiag', 'Tipo de diagnóstico', 'TipoDiag', $d + ['EgreTipoDiag' => $d['TipoDiag'] ?? '2'], $er) ?>
                <?= campo_buscador('EgreRel1', 'Relacionado', $d + ['EgreRel1' => $d['DiagRel1'] ?? ''], $er, 'diagnosticos') ?>
                <?= campo_lista('EgreTipoRel1', 'Tipo relacionado', 'TipoDiag', $d, $er, false) ?>
                <div><label for="DiasInca">Días de incapacidad</label>
                    <input type="number" id="DiasInca" name="DiasInca" value="<?= v($d, 'DiasInca') ?>" min="0" max="99" class="<?= ce($er, 'DiasInca') ?>"><?= me($er, 'DiasInca') ?></div>
            </div>
            <?php if ($codMuerto !== ''): ?>
            <div class="subgrupo" data-mostrar-si="EstaSali=<?= e($codMuerto) ?>">
                <h3><?= icono('triangle-alert') ?>Datos de la muerte</h3>
                <div class="rejilla">
                    <?= campo_buscador('DiagMuer', 'Causa de muerte (CIE-10)', $d, $er, 'diagnosticos') ?>
                    <?= campos_fecha_hora('FechMuer', 'HoraMuer', $d, $er, false) ?>
                </div>
            </div>
            <?php endif; ?>
            <?= campo_texto('ObseSali', 'Observaciones de la salida', $d, $er, 3) ?>
        <?php endif; ?>
        <div class="confirmar <?= ce($er, 'ConfEgre') ?>">
            <label class="opcion"><input type="checkbox" name="ConfEgre" value="1" required>
                <strong>Confirmo <?= $esCE ? 'el cierre de la atención' : 'el egreso' ?>:</strong> la admisión <?= e($a['ConsAdmi']) ?> quedará cerrada y ya no se podrá modificar<?= $mod['cama'] ? '; la cama ' . e($a['CamaActu']) . ' queda libre' : '' ?>.</label>
            <?= me($er, 'ConfEgre') ?>
        </div>
        <div class="acciones acciones-panel">
            <button type="submit" class="boton boton-peligro"><?= icono('log-out') ?><?= $esCE ? 'Cerrar atención' : 'Guardar egreso y cerrar admisión' ?></button>
        </div>
    </form>
    </div>
<?php elseif ($egreso): ?>
    <dl class="datos">
        <dt>Fecha y hora de salida</dt><dd><?= e(fecha_hora($egreso['FechSali'] . ' ' . $egreso['HoraSali'])) ?> · <?= e($egreso['UsuaDigi']) ?></dd>
        <dt>Estancia</dt><dd><?= (int) $egreso['DiasEsta'] ?> días, <?= (int) $egreso['HoraEsta'] ?> horas</dd>
        <dt>Causa / destino</dt><dd><?= e(lista_nombre('CausSali', $egreso['CausSali'])) ?> · <?= e(lista_nombre('DestSali', $egreso['DestSali'])) ?></dd>
        <dt>Estado / tipo de egreso</dt><dd><?= e(lista_nombre('EstaSali', $egreso['EstaSali'])) ?> · <?= e(lista_nombre('TipoEgre', $egreso['TipoEgre'])) ?></dd>
        <dt>Diagnóstico de egreso</dt><dd><?= e(diag_texto($egreso['DiagEgre'])) ?><?= trim((string) $egreso['DiagRel1']) !== '' ? '<br>' . e(diag_texto($egreso['DiagRel1'])) : '' ?></dd>
        <?php if ($egreso['DiagMuer']): ?><dt>Causa de muerte</dt><dd><?= e(diag_texto($egreso['DiagMuer'])) ?> · <?= e(fecha_hora($egreso['FechMuer'] . ' ' . $egreso['HoraMuer'])) ?></dd><?php endif; ?>
        <dt>Observaciones</dt><dd class="texto-largo"><?= texto_registro($egreso['ObseSali']) ?></dd>
    </dl>
<?php elseif ((int) $a['Cerrado'] === 1): ?>
    <?= panel_vacio('Atención cerrada el ' . fecha_hora($a['FechCier'] . ' ' . $a['HoraCier']) . ' por ' . $a['UsuaCier'] . '.') ?>
<?php else: ?>
    <?= panel_vacio('La admisión no se puede modificar.') ?>
<?php endif; ?>
</section>
