<?php
/**
 * Egreso (Urgencias 26, Observación 22): SaliInte y cierre de la admisión, con los campos de SIHOS en su orden:
 * Fecha, Hora, Estadía, Estado, Causa, Destino, Incapacidad (días), Diagnósticos (Egreso, Rela 1-3, Complic.)
 * y Plan de Manejo Ambulatorio y Observaciones. En Observación la remisión va aquí (la pestaña 16 de SIHOS
 * no se ve en las capturas). En Consulta Externa no hay egreso: se usa "Cerrar Historia" del encabezado.
 * Variables: $a, $mod, $editable, $aqui, $tab, $F, $E, $egreso, $remisiones.
 */
$d = $F['egreso'] ?? ['FechSali' => date('Y-m-d'), 'HoraSali' => date('H:i'), 'CausSali' => '1', 'DestSali' => '01', 'EstaSali' => '1',
                      'TipoEgre' => '1', 'EgreTipoDiag' => '2', 'DiagEgre' => $a['DiagIngr']];
$er = $E['egreso'] ?? [];
// Codigo del estado "muerto" en el catalogo EstaSali (para mostrar los datos de muerte)
$codMuerto = '';
foreach (lista('EstaSali') as $c => $n) {
    if (estado_salida_muerto($c)) { $codMuerto = (string) $c; break; }
}
$seg = max(0, time() - strtotime($a['FechIngr'] . ' ' . $a['HoraIngr']));
$estadia = intdiv($seg, 86400) . ' día(s), ' . intdiv($seg % 86400, 3600) . ' hora(s)';
$conRemision = $mod['clave'] === 'obs';
if ($conRemision) {
    require_once __DIR__ . '/remision_bloques.php';
    [$dr, $err] = remision_datos($a, $F, $E);
}
?>
<?= panel_abrir('egreso', pestana_titulo($mod, 'egreso'), 'log-out', $tab,
    (int) $a['Cerrado'] === 1 ? 'Admisión cerrada' : 'Salida del paciente y cierre de la historia') ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo panel-dos">
    <?php if ($conRemision): ?>
    <details class="subseccion"<?= $err ? ' open' : '' ?>>
        <summary><?= icono('hospital') ?>Remisión a otra institución</summary>
        <?php remision_formulario($aqui . '&tab=egreso', $dr, $err, remision_anteriores($remisiones)); ?>
    </details>
    <?php endif; ?>

    <?= errores_resumen($er) ?>
    <form method="post" action="<?= e($aqui) ?>&amp;tab=egreso" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="egreso">
        <?= barra_registro('Nuevo', [], 'FechSali', 'HoraSali', $d, $er,
            '<div class="br-campo"><label>Estadía</label><span>' . e($estadia) . '</span></div>') ?>
        <div class="rejilla">
            <?= campo_lista('EstaSali', 'Estado', 'EstaSali', $d, $er) ?>
            <?= campo_lista('CausSali', 'Causa', 'CausSali', $d, $er) ?>
            <?= campo_lista('DestSali', 'Destino', 'DestSali', $d, $er) ?>
            <div><label for="DiasInca">Incapacidad: Día(s)</label>
                <input type="number" id="DiasInca" name="DiasInca" value="<?= v($d, 'DiasInca') ?>" min="0" max="99" class="<?= ce($er, 'DiasInca') ?>"><?= me($er, 'DiasInca') ?></div>
            <?= campo_lista('TipoEgre', 'Tipo de egreso', 'TipoEgre', $d, $er) ?>
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
        <h3 class="subtitulo-panel"><?= icono('file-text') ?>Diagnósticos</h3>
        <?= tabla_diagnosticos([['Egreso', 'DiagEgre', 'EgreTipoDiag'], ['Rela 1', 'EgreRel1', 'EgreTipoRel1'], ['Rela 2', 'EgreRel2', 'EgreTipoRel2'],
                                ['Rela 3', 'EgreRel3', 'EgreTipoRel3'], ['Complic.', 'EgreComp', 'EgreTipoComp']], $d, $er, true, 'egre-') ?>
        <?= campo_texto('ObseSali', 'Plan de Manejo Ambulatorio y Observaciones', $d, $er, 3) ?>
        <div class="confirmar <?= ce($er, 'ConfEgre') ?>">
            <label class="opcion"><input type="checkbox" name="ConfEgre" value="1" required>
                <strong>Confirmo el egreso:</strong> la admisión <?= e($a['ConsAdmi']) ?> quedará cerrada y ya no se podrá modificar<?= $mod['cama'] ? '; la cama ' . e($a['CamaActu']) . ' queda libre' : '' ?>.</label>
            <?= me($er, 'ConfEgre') ?>
        </div>
        <div class="acciones acciones-panel">
            <button type="submit" class="boton boton-peligro"><?= icono('log-out') ?>Cerrar Historia</button>
            <button type="reset" class="boton boton-claro"><?= icono('refresh-cw') ?>Limpiar</button>
        </div>
    </form>
    </div>
<?php elseif ($egreso): ?>
    <dl class="datos">
        <dt>Fecha y hora de salida</dt><dd><?= e(fecha_hora($egreso['FechSali'] . ' ' . $egreso['HoraSali'])) ?> · <?= e($egreso['UsuaDigi']) ?></dd>
        <dt>Estancia</dt><dd><?= (int) $egreso['DiasEsta'] ?> días, <?= (int) $egreso['HoraEsta'] ?> horas</dd>
        <dt>Causa / destino</dt><dd><?= e(lista_nombre('CausSali', $egreso['CausSali'])) ?> · <?= e(lista_nombre('DestSali', $egreso['DestSali'])) ?></dd>
        <dt>Estado / tipo de egreso</dt><dd><?= e(lista_nombre('EstaSali', $egreso['EstaSali'])) ?> · <?= e(lista_nombre('TipoEgre', $egreso['TipoEgre'])) ?></dd>
        <dt>Diagnósticos</dt><dd><?= e(diag_texto($egreso['DiagEgre'])) ?><?php foreach (['DiagRel1', 'DiagRel2', 'DiagRel3', 'DiagComp'] as $c) { if (trim((string) $egreso[$c]) !== '') echo '<br>' . ($c === 'DiagComp' ? 'Complicación: ' : '') . e(diag_texto($egreso[$c])); } ?></dd>
        <?php if ($egreso['DiagMuer']): ?><dt>Causa de muerte</dt><dd><?= e(diag_texto($egreso['DiagMuer'])) ?> · <?= e(fecha_hora($egreso['FechMuer'] . ' ' . $egreso['HoraMuer'])) ?></dd><?php endif; ?>
        <dt>Plan de manejo ambulatorio y observaciones</dt><dd class="texto-largo"><?= texto_registro($egreso['ObseSali']) ?></dd>
    </dl>
<?php elseif ((int) $a['Cerrado'] === 1): ?>
    <?= panel_vacio('Atención cerrada el ' . fecha_hora($a['FechCier'] . ' ' . $a['HoraCier']) . ' por ' . $a['UsuaCier'] . '.') ?>
<?php else: ?>
    <?= panel_vacio('La admisión no se puede modificar.') ?>
<?php endif; ?>

<?php if ($conRemision) { remision_lista($remisiones); } ?>
</section>
