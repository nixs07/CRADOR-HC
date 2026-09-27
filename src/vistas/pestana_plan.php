<?php
/**
 * Plan de Manejo (Urgencias 20, Observación 25), como SIHOS (docs/RECORRIDO_SIHOS.md §3): Fecha · Hora · Destino ·
 * Recomendaciones y Plan de Manejo · bloque Código Dorado (RipsCons.Accinme, ContCuid, EstaCodo, Especif, ObserCd)
 * · Guardar · Imprimir · Consultar. Sin selector de consulta: es el plan de la consulta más reciente (el mismo
 * RipsCons.ObseReco del acordeón de Consultas). En Consulta Externa la pestaña 7 es parte del formulario de la
 * consulta (pestana_consulta.php). Variables: $a, $mod, $editable, $aqui, $tab, $F, $E, $consultas.
 */
require_once __DIR__ . '/consulta_bloques.php';
$er = $E['plan'] ?? [];
$c = $consultas[0] ?? null;
foreach ($consultas as $x) {
    if (!$c || (int) $x['ConsCons'] > (int) $c['ConsCons']) $c = $x;
}
$d = $F['plan'] ?? ($c ? ['ObseRecoPlan' => $c['ObseReco'], 'PlanDest' => sprintf('%02d', (int) $c['DestSali']),
                          'PlanConducta' => $c['Conducta'], 'EstaCodo' => $c['EstaCodo'],
                          'Especif' => $c['Especif'] ?? '', 'ObserCd' => $c['ObserCd'] ?? ''] : []);
if (isset($F['plan']['ObseReco'])) {
    $d['ObseRecoPlan'] = $F['plan']['ObseReco'];
    $d['PlanDest'] = $F['plan']['DestSali'];
    $d['PlanConducta'] = $F['plan']['Conducta'];
}
?>
<?= panel_abrir('plan', pestana_titulo($mod, 'plan'), 'clipboard-plus', $tab, $c ? 'Consulta No. ' . (int) $c['ConsCons'] : 'Sin consulta') ?>
<?php if (!$c): ?>
    <?= panel_vacio('Registre primero la consulta (pestaña ' . pestana_titulo($mod, 'consulta') . ').') ?>
<?php elseif ($editable): ?>
    <div class="panel-cuerpo">
    <?= errores_resumen($er) ?>
    <form method="post" action="<?= e($aqui) ?>&amp;tab=plan" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="plan">
        <div class="barra-registro">
            <div class="br-campo"><label>Fecha</label><span><?= e(date('d/m/Y', strtotime($c['FechCons']))) ?></span></div>
            <div class="br-campo"><label>Hora</label><span><?= e(substr($c['HoraCons'], 0, 5)) ?></span></div>
        </div>
        <div class="rejilla">
            <?= campo_lista('PlanDest', 'Destino', 'DestSali', $d, $er, true) ?>
            <?= campo_lista('PlanConducta', 'Conducta', 'Conducta', $d, $er, true) ?>
        </div>
        <?= campo_texto('ObseRecoPlan', 'Recomendaciones y Plan de Manejo', $d, $er, 6, true) ?>
        <?php bloque_codigo_dorado($d, $er, true); ?>
        <?= botonera(['Guardar', 'Imprimir', 'Consultar']) ?>
    </form>
    </div>
<?php else: ?>
    <dl class="datos"><dt>Destino</dt><dd><?= e(lista_nombre('DestSali', sprintf('%02d', (int) $c['DestSali']))) ?></dd>
        <dt>Recomendaciones y Plan de Manejo</dt><dd class="texto-largo"><?= texto_registro($c['ObseReco']) ?></dd></dl>
<?php endif; ?>
</section>
