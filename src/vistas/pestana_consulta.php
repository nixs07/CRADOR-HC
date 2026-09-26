<?php
/**
 * Consultas (RipsCons + Antecede + EstaGene + SignVita), como en SIHOS:
 *  - Urgencias (2.Consultas) y Observación (1.Consultas): una pestaña con acordeones Anamnesis, Antecedentes,
 *    Revisión por Sistema y Exámen, Laboratorios y Diagnósticos, Plan de Manejo y Recomendaciones.
 *  - Consulta Externa: pestañas 1.Anamnesis, 2.Rev.Sistemas y Ex.Físico, 3.Antecedentes y
 *    4.Laboratorios y Diagnósticos (con el plan de manejo), todas dentro del MISMO formulario.
 * Variables: $a, $mod, $editable, $aqui, $tab, $F, $E, $triage, $consultas, $u.
 */
require_once __DIR__ . '/consulta_bloques.php';

$esCE = $mod['clave'] === 'ce';
$d = $F['consulta'] ?? ['FechCons' => date('Y-m-d'), 'HoraCons' => date('H:i'), 'FinaCons' => '10', 'TipoDiag' => '1',
                        'CodiDiag' => $a['DiagIngr'], 'MotiCons' => $triage['MotiCons'] ?? ''];
$d += is_array($d['signos'] ?? null) ? $d['signos'] : [];   // signos escritos (si hubo errores)
if (isset($d['DestSali']) && !isset($d['ConsDest'])) {
    $d['ConsDest'] = $d['DestSali'];
}
$er = $E['consulta'] ?? [];
$sub = count($consultas) . ' ' . (count($consultas) === 1 ? 'consulta registrada' : 'consultas registradas');
$anteriores = consulta_anteriores($consultas);
$accion = $aqui . '&tab=' . ($esCE ? 'anamnesis' : 'consulta');

if (!$esCE): ?>
<?= panel_abrir('consulta', pestana_titulo($mod, 'consulta'), 'stethoscope', $tab, $sub) ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo">
    <?= errores_resumen($er) ?>
    <form method="post" action="<?= e($accion) ?>" class="formulario formulario-panel" data-signos data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="consulta">
        <?= barra_registro('Nuevo', $anteriores, 'FechCons', 'HoraCons', $d, $er) ?>
        <?php
        $abrir = function (string $titulo, string $icono, bool $abierto) use ($er) {
            return '<details class="acordeon"' . ($abierto || $er ? ' open' : '') . '><summary>' . icono($icono) . e($titulo) . '</summary><div class="acordeon-cuerpo">';
        };
        ?>
        <?= $abrir('Anamnesis', 'file-text', true) ?><?php consulta_bloque_anamnesis($d, $er, false); ?></div></details>
        <?= $abrir('Antecedentes', 'history', false) ?><?php consulta_bloque_antecedentes($d, $er); ?></div></details>
        <?= $abrir('Revisión por Sistema y Exámen', 'activity', false) ?><?php consulta_bloque_revision($d, $er, 'cons-'); ?></div></details>
        <?= $abrir('Laboratorios y Diagnósticos', 'clipboard-list', true) ?><?php consulta_bloque_laboratorios($d, $er); ?></div></details>
        <?= $abrir('Plan de Manejo y Recomendaciones', 'clipboard-plus', true) ?><?php consulta_bloque_plan($d, $er); ?></div></details>
        <?= botones_panel('Guardar consulta') ?>
    </form>
    </div>
<?php endif; ?>
<?php consulta_registros($consultas); ?>
</section>
<?php return; endif;

// --- Consulta Externa: cuatro pestañas de un mismo formulario -----------------------------
$bloques = [
    'anamnesis'    => ['file-text', 'Anamnesis'],
    'revision'     => ['activity', 'Revisión por sistemas y examen físico'],
    'antecedentes' => ['history', 'Antecedentes'],
    'laboratorios' => ['clipboard-list', 'Laboratorios, diagnósticos y plan de manejo'],
];
if ($editable): ?>
<form method="post" action="<?= e($accion) ?>" class="formulario formulario-ce" id="form-consulta" data-signos data-una-vez novalidate>
    <?= csrf_campo() ?>
    <input type="hidden" name="accion" value="consulta">
<?php endif;
foreach ($bloques as $id => [$icono, $sub]): ?>
    <?= panel_abrir($id, pestana_titulo($mod, $id), $icono, $tab, $sub) ?>
    <?php if ($editable): ?>
        <div class="panel-cuerpo formulario-panel">
        <?= errores_resumen($er) ?>
        <?php if ($id === 'anamnesis'): ?>
            <?= barra_registro('Nuevo', $anteriores, 'FechCons', 'HoraCons', $d, $er) ?>
            <?php consulta_bloque_anamnesis($d, $er, true); ?>
        <?php elseif ($id === 'revision'): ?>
            <?php consulta_bloque_revision($d, $er, 'cons-'); ?>
        <?php elseif ($id === 'antecedentes'): ?>
            <?php consulta_bloque_antecedentes($d, $er); ?>
        <?php else: ?>
            <?php consulta_bloque_laboratorios($d, $er); ?>
            <h3 class="subtitulo-panel"><?= icono('clipboard-plus') ?>Plan de Manejo y Recomendaciones</h3>
            <?php consulta_bloque_plan($d, $er); ?>
        <?php endif; ?>
        <p class="ayuda">Las pestañas 1 a 4 son una sola consulta: se guardan juntas con el botón Guardar consulta.</p>
        <?= botones_panel('Guardar consulta') ?>
        </div>
    <?php elseif ($id !== 'anamnesis'): ?>
        <?= panel_vacio('Las consultas registradas se ven completas en la pestaña 1. Anamnesis.') ?>
    <?php endif; ?>
    <?php if ($id === 'anamnesis') { consulta_registros($consultas); } ?>
    </section>
<?php endforeach;
if ($editable): ?>
</form>
<?php endif;
