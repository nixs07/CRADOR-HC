<?php
/**
 * Procedimientos (Urgencias 6, Observación 9, Consulta Externa 11): procedimientos realizados (HojaProc), con
 * los campos de SIHOS: Actividad, Finalidad, Cant, Realizado?, Descripción y diagnósticos Principal, Rela 1-3, Compl.
 * Como SIHOS: Nuevo · Fecha · Hora · Actividad · Finalidad · Cant · Id Estudio (sin columna) · Realizado? ·
 * Descripción · Diagnósticos · Guardar · Cancelar · Imprimir · Consultar · Revisado (HojaProc.ContRevi) · lista.
 * El selector "Atiende la orden" no está en SIHOS: quedó oculto (procedimiento_validar aún acepta OrdenItem).
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
        <?= barra_registro('Nuevo', ['-'], 'FechProc', 'HoraProc', $d, $er) ?>
        <div class="rejilla">
            <?= campo_buscador('CodiProc', 'Actividad', $d, $er, 'procedimientos', true) ?>
            <?= campo_lista('CodiFina', 'Finalidad', 'FinaProc', $d, $er) ?>
            <div><label for="CantProc">Cant</label>
                <input type="number" id="CantProc" name="CantProc" value="<?= v($d, 'CantProc') ?>" min="1" max="999" class="<?= ce($er, 'CantProc') ?>"><?= me($er, 'CantProc') ?></div>
            <?= campo_sin_columna('Id Estudio', 'text') ?>
            <div class="casillas"><?= casilla('ProcReal', 'Realizado?', $d) ?></div>
        </div>
        <?= campo_texto('IndiAdic', 'Descripción', $d, $er, 4, true) ?>
        <h3 class="subtitulo-panel"><?= icono('file-text') ?>Diagnósticos</h3>
        <?= tabla_diagnosticos([['Principal', 'DiagPrin', 'ProcTipoDiag'], ['Rela 1', 'DiagRela', 'ProcTipoDiaR'], ['Rela 2', 'DiagRel1', 'ProcTipoDia1'],
                                ['Rela 3', 'DiagRel2', 'ProcTipoDia2'], ['Compl', 'DiagComp', 'ProcTipoDiaC']], $d, $er, true, 'proc-') ?>
        <?= botonera(['Guardar', 'Cancelar', 'Imprimir', 'Consultar']) ?>
        <div class="casillas"><?= casilla('ProcRevi', 'Revisado', $d) ?></div>
    </form>
    </div>
<?php endif; ?>

<h3 class="titulo-tabla"><?= icono('history') ?>Procedimientos realizados</h3>
<?php if (!$procedimientos): ?>
    <?= panel_vacio('No hay procedimientos registrados.') ?>
<?php else: ?>
    <div class="tabla-contenedor">
    <table class="tabla">
        <thead><tr><th>No.</th><th>Fecha</th><th>Hora</th><th>Sede</th><th>Nombre</th><th>Fina.</th><th class="num">Cant</th><th>DXP</th>
            <th class="num">Orden</th><th class="num">Item</th><th class="num">Liqu</th><th class="num">Cons.</th><th>Profesional</th></tr></thead>
        <tbody>
        <?php foreach ($procedimientos as $p): ?>
            <tr id="reg-proc-<?= (int) $p['ConsHoPr'] ?>">
                <td><span class="contador"><?= (int) $p['ConsHoPr'] ?></span></td>
                <td class="sin-salto"><?= e(date('d/m/Y', strtotime($p['FechProc']))) ?></td>
                <td><?= e(substr($p['HoraProc'], 0, 5)) ?></td>
                <td><?= e($p['CodiInst']) ?></td>
                <td><strong><?= e($p['CodiProc']) ?></strong> · <?= e($p['NombProc'] ?? '') ?></td>
                <td><?= e($p['NombFina'] ?? $p['CodiFina']) ?></td>
                <td class="num"><?= (int) $p['CantProc'] ?></td>
                <td><?= e($p['DiagPrin']) ?></td>
                <td class="num"><?= (int) $p['NumeOrde'] ?: '' ?></td>
                <td class="num"><?= (int) $p['Item'] ?: '' ?></td>
                <td class="num"><?= (int) $p['NumeLiqu'] ?></td>
                <td class="num"><?= (int) $p['ConsCons'] ?></td>
                <td><?= e($p['UsuaAsis']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</section>
