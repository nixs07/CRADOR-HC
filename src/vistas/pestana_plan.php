<?php
/**
 * Plan de Manejo (Urgencias 20, Observación 25): el "Plan de Manejo y Recomendaciones" (RipsCons.ObseReco) de
 * una consulta ya guardada. Se escoge la consulta en "No." (por defecto la última) y se edita su plan; es el
 * mismo campo del acordeón de la pestaña Consultas. En Consulta Externa la pestaña 7 es parte del formulario
 * de la consulta (pestana_consulta.php). Variables: $a, $mod, $editable, $aqui, $tab, $F, $E, $consultas.
 */
$er = $E['plan'] ?? [];
$ultima = $consultas[0] ?? null;
$sel = (int) ($F['plan']['ConsCons'] ?? ($ultima['ConsCons'] ?? 0));
$texto = $F['plan']['ObseRecoPlan'] ?? null;
foreach ($consultas as $c) {
    if ((int) $c['ConsCons'] === $sel && $texto === null) {
        $texto = (string) $c['ObseReco'];
    }
}
?>
<?= panel_abrir('plan', pestana_titulo($mod, 'plan'), 'clipboard-plus', $tab, 'Plan de manejo y recomendaciones de la consulta') ?>
<?php if (!$consultas): ?>
    <?= panel_vacio('Registre primero la consulta (pestaña ' . pestana_titulo($mod, 'consulta') . ').') ?>
<?php elseif ($editable): ?>
    <div class="panel-cuerpo">
    <?= errores_resumen($er) ?>
    <form method="post" action="<?= e($aqui) ?>&amp;tab=plan" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="plan">
        <div class="barra-registro">
            <div class="br-campo"><label for="PlanCons">No. (consulta)</label>
                <select id="PlanCons" name="PlanCons" data-plan-consulta class="<?= ce($er, 'PlanCons') ?>">
                    <?php foreach ($consultas as $c): ?>
                        <option value="<?= (int) $c['ConsCons'] ?>" data-texto="<?= e($c['ObseReco']) ?>"<?= (int) $c['ConsCons'] === $sel ? ' selected' : '' ?>>
                            <?= (int) $c['ConsCons'] ?> · <?= e(fecha_hora($c['FechCons'] . ' ' . $c['HoraCons'])) ?> · <?= e($c['UsuaCons']) ?></option>
                    <?php endforeach; ?>
                </select><?= me($er, 'PlanCons') ?></div>
            <div class="br-derecha">
                <button type="button" class="boton boton-claro boton-chico" disabled title="No aplica en contingencia"><?= icono('file-text') ?>Imprimir</button>
                <small>no aplica en contingencia</small></div>
        </div>
        <?= campo_texto('ObseRecoPlan', 'Plan de Manejo y Recomendaciones', ['ObseRecoPlan' => $texto], $er, 8, true) ?>
        <?= botones_panel('Guardar plan de manejo') ?>
    </form>
    </div>
<?php endif; ?>

<?php if ($consultas): ?>
<h3 class="titulo-tabla"><?= icono('history') ?>Plan de manejo de cada consulta</h3>
<div class="registros">
    <?php foreach ($consultas as $c): ?>
        <div class="registro registro-abierto" id="reg-plan-<?= (int) $c['ConsCons'] ?>">
            <div class="registro-cabeza"><span class="contador"><?= (int) $c['ConsCons'] ?></span>
                <strong><?= e(fecha_hora($c['FechCons'] . ' ' . $c['HoraCons'])) ?></strong><small><?= e($c['UsuaCons']) ?></small></div>
            <p class="registro-nota texto-largo"><?= texto_registro($c['ObseReco']) ?></p>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
</section>
