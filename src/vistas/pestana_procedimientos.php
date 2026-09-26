<?php
/**
 * Procedimientos (Urgencias 6, Observación 9, Consulta Externa 11): procedimientos realizados (HojaProc), con
 * los campos de SIHOS: Actividad, Finalidad, Cant, Realizado?, Descripción y diagnósticos Principal, Rela 1-3, Compl.
 * Se puede ligar a un ítem de orden pendiente (DetaOrde): llena NumeOrde/Item y suma CantReal.
 * Variables: $a, $mod, $editable, $aqui, $tab, $F, $E, $procedimientos, $pendientes.
 */
$d = $F['procedimiento'] ?? ['FechProc' => date('Y-m-d'), 'HoraProc' => date('H:i'), 'CodiFina' => '2', 'ProcTipoDiag' => '1', 'DiagPrin' => $a['DiagIngr'], 'CantProc' => '1', 'ProcReal' => 1];
$anteriores = [];
foreach ($procedimientos as $p) {
    $anteriores['reg-proc-' . (int) $p['ConsHoPr']] = (int) $p['ConsHoPr'] . ' · ' . fecha_hora($p['FechProc'] . ' ' . $p['HoraProc']) . ' · ' . $p['CodiProc'];
}
$er = $E['procedimiento'] ?? [];
?>
<?= panel_abrir('procedimientos', pestana_titulo($mod, 'procedimientos'), 'activity', $tab, count($procedimientos) . ' ' . (count($procedimientos) === 1 ? 'procedimiento realizado' : 'procedimientos realizados')) ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo">
    <?= errores_resumen($er) ?>
    <form method="post" action="<?= e($aqui) ?>&amp;tab=procedimientos" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="procedimiento">
        <?= barra_registro('Nuevo', $anteriores, 'FechProc', 'HoraProc', $d, $er) ?>
        <?php if ($pendientes): ?>
            <div><label for="OrdenItem">Atiende la orden (opcional)</label>
                <select id="OrdenItem" name="OrdenItem" class="<?= ce($er, 'OrdenItem') ?>">
                    <option value="">— No viene de una orden —</option>
                    <?php foreach ($pendientes as $llave => $o): ?>
                        <option value="<?= e($llave) ?>"<?= ($d['OrdenItem'] ?? '') === $llave ? ' selected' : '' ?>>Orden <?= (int) $o['ConsOrde'] ?> ítem <?= (int) $o['Item'] ?> · <?= e($o['CodiProc'] . ' ' . ($o['NombProc'] ?? '')) ?> (<?= (int) $o['CantReal'] ?>/<?= (int) $o['CantSumi'] ?> realizados)</option>
                    <?php endforeach; ?>
                </select><?= me($er, 'OrdenItem') ?>
                <div class="nota-campo">Si escoge un ítem, el procedimiento se toma de la orden.</div></div>
        <?php endif; ?>
        <div class="rejilla">
            <?= campo_buscador('CodiProc', 'Actividad', $d, $er, 'procedimientos', !$pendientes) ?>
            <?= campo_lista('CodiFina', 'Finalidad', 'FinaProc', $d, $er) ?>
            <div><label for="CantProc">Cant</label>
                <input type="number" id="CantProc" name="CantProc" value="<?= v($d, 'CantProc') ?>" min="1" max="999" class="<?= ce($er, 'CantProc') ?>"><?= me($er, 'CantProc') ?></div>
            <div class="casillas"><?= casilla('ProcReal', 'Realizado?', $d) ?></div>
        </div>
        <?= campo_texto('IndiAdic', 'Descripción', $d, $er, 4) ?>
        <h3 class="subtitulo-panel"><?= icono('file-text') ?>Diagnósticos</h3>
        <?= tabla_diagnosticos([['Principal', 'DiagPrin', 'ProcTipoDiag'], ['Rela 1', 'DiagRela', 'ProcTipoDiaR'], ['Rela 2', 'DiagRel1', 'ProcTipoDia1'],
                                ['Rela 3', 'DiagRel2', 'ProcTipoDia2'], ['Compl', 'DiagComp', 'ProcTipoDiaC']], $d, $er, true, 'proc-') ?>
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
        <thead><tr><th>No.</th><th>Fecha</th><th>Nombre</th><th>Fina.</th><th class="num">Cant</th><th>Realizado</th><th>DXP</th><th>Orden</th><th>Descripción</th><th>Profesional</th></tr></thead>
        <tbody>
        <?php foreach ($procedimientos as $p): ?>
            <tr id="reg-proc-<?= (int) $p['ConsHoPr'] ?>">
                <td><span class="contador"><?= (int) $p['ConsHoPr'] ?></span></td>
                <td class="sin-salto"><?= e(fecha_hora($p['FechProc'] . ' ' . $p['HoraProc'])) ?></td>
                <td><strong><?= e($p['CodiProc']) ?></strong> · <?= e($p['NombProc'] ?? '') ?></td>
                <td><?= e($p['NombFina'] ?? $p['CodiFina']) ?></td>
                <td class="num"><?= (int) $p['CantProc'] ?></td>
                <td><?= (int) $p['ProcReal'] ? 'Sí' : 'No' ?></td>
                <td><?= e($p['DiagPrin']) ?></td>
                <td><?= (int) $p['NumeOrde'] ? 'Orden ' . (int) $p['NumeOrde'] . ' ítem ' . (int) $p['Item'] : '—' ?></td>
                <td><?= e($p['IndiAdic']) ?></td>
                <td><?= e($p['UsuaAsis']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</section>
