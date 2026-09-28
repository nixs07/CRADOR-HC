<?php
/**
 * Consultas (RipsCons + Antecede + EstaGene + SignVita), como en SIHOS (docs/RECORRIDO_SIHOS.md §3 y §5):
 *  - Urgencias (2) y Observación (1): barra Nuevo · No. · Fecha · Hora · Cargos · Consultar · Imprimir y cinco
 *    acordeones (Anamnesis, Antecedentes, Revisión por Sistema y Exámen, Laboratorios y Diagnósticos, Plan de
 *    Manejo y Recomendaciones), cada uno con su Guardar; al final "Cerrar Consulta" y la Duración.
 *  - Consulta Externa: pestañas SEPARADAS 1 Anamnesis, 2 Rev.Sistemas y Ex.Físico, 3 Antecedentes,
 *    4 Laboratorios y Diagnósticos y 7 Plan de Manejo, cada una con su Guardar (un mismo formulario).
 * El formulario edita la consulta abierta (Urgencias/Observación: la última sin cerrar; Consulta Externa: la última)
 * hasta que se pulse "Nuevo". ?cons=N abre la consulta N; ?cons=0, una consulta nueva.
 * TipoCons por defecto: Urgencias 890701, Observación 89060102, Consulta Externa 890201 (verificado).
 * Variables: $a, $mod, $u, $editable, $aqui, $tab, $F, $E, $triage, $consultas.
 */
require_once __DIR__ . '/consulta_bloques.php';

$esCE = $mod['clave'] === 'ce';
$tabCons = $esCE ? 'anamnesis' : 'consulta';
$nueva = ['FechCons' => date('Y-m-d'), 'HoraCons' => date('H:i'), 'FinaCons' => '10', 'TipoDiag' => '1',
          'TipoCons' => ['urg' => '890701', 'obs' => '89060102', 'ce' => '890201'][$mod['clave']],
          // El motivo del triage solo se copia en la PRIMERA consulta de la admisión
          'CodiDiag' => $a['DiagIngr'], 'MotiCons' => $consultas ? '' : ($triage['MotiCons'] ?? '')];
// Consulta que se edita
$editando = null;
if (isset($F['consulta'])) {
    $d = $F['consulta'];
    $d += is_array($d['signos'] ?? null) ? $d['signos'] : [];
    if (isset($d['DestSali']) && !isset($d['ConsDest'])) $d['ConsDest'] = $d['DestSali'];
    $editando = $d['editar'] ?? null;
} else {
    $pedida = isset($_GET['cons']) ? (int) $_GET['cons'] : -1;
    foreach ($consultas as $c) {
        $abierta = $esCE || ($c['FechCier'] ?? '0000-00-00') === '0000-00-00';
        if (($pedida > 0 && (int) $c['ConsCons'] === $pedida && $abierta) || ($pedida < 0 && $abierta && !$editando)) {
            $editando = $c;
            if ($pedida > 0) break;
        }
    }
    $d = $editando ? consulta_a_datos($editando) : $nueva;
}
$er = $E['consulta'] ?? [];
$reco = reconciliacion_de_paciente($a['TipoDocu'], $a['NumeUsua']);
$consEdit = (int) ($editando['ConsCons'] ?? 0);
$sub = count($consultas) . ' ' . (count($consultas) === 1 ? 'consulta registrada' : 'consultas registradas')
     . ($consEdit ? ' · editando la consulta No. ' . $consEdit : ' · consulta nueva');
$anteriores = consulta_anteriores($consultas);
$accion = $aqui . '&tab=' . $tabCons;
$profesional = $u['Nombre'] ?? $u['Login'];
$urlNueva = $aqui . '&tab=' . $tabCons . '&cons=0';
$guardarSeccion = fn (string $seccion) => botonera(['Guardar'], ['Guardar' => $seccion]);

if (!$esCE): ?>
<?= panel_abrir('consulta', pestana_titulo($mod, 'consulta'), 'stethoscope', $tab, $sub) ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo">
    <?= errores_resumen($er) ?>
    <form method="post" action="<?= e($accion) ?>" class="formulario formulario-panel" data-signos data-una-vez novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="consulta">
        <input type="hidden" name="ConsConsEdit" value="<?= $consEdit ?>">
        <?= barra_registro('Nuevo', $anteriores, 'FechCons', 'HoraCons', $d, $er, '', '', ['Cargos', 'Consultar', 'Imprimir'], $urlNueva) ?>
        <?php
        $abrir = function (string $titulo, string $icono, bool $abierto) use ($er) {
            return '<details class="acordeon"' . ($abierto || $er ? ' open' : '') . '><summary>' . icono($icono) . e($titulo) . '</summary><div class="acordeon-cuerpo">';
        };
        ?>
        <?= $abrir('Anamnesis', 'file-text', true) ?><?php consulta_bloque_anamnesis($d, $er, false, $profesional); ?><?= $guardarSeccion('anamnesis') ?></div></details>
        <?= $abrir('Antecedentes', 'history', false) ?><?php consulta_bloque_antecedentes($d, $er, false, $reco); ?><?= $guardarSeccion('antecedentes') ?></div></details>
        <?= $abrir('Revisión por Sistema y Exámen', 'activity', false) ?><?php consulta_bloque_revision($d, $er, 'cons-', false); ?><?= $guardarSeccion('revision') ?></div></details>
        <?= $abrir('Laboratorios y Diagnósticos', 'clipboard-list', true) ?><?php consulta_bloque_laboratorios($d, $er); ?><?= $guardarSeccion('laboratorios') ?></div></details>
        <?= $abrir('Plan de Manejo y Recomendaciones', 'clipboard-plus', true) ?><?php consulta_bloque_plan($d, $er, false); ?><?= $guardarSeccion('plan') ?></div></details>
        <div class="cerrar-consulta">
            <button type="submit" name="boton" value="cerrar" class="boton boton-peligro"><?= icono('lock') ?>Cerrar Consulta</button>
            <span class="duracion"><?= icono('clock') ?>Duración: <strong><?= e(consulta_duracion($editando)) ?></strong></span>
        </div>
    </form>
    </div>
<?php endif; ?>
<?php consulta_registros($consultas); ?>
</section>
<?php return; endif;

// --- Consulta Externa: pestañas separadas de un mismo formulario ------------------------------------
$bloques = [
    'anamnesis'    => ['file-text', 'Anamnesis'],
    'revision'     => ['activity', 'Revisión por sistemas y examen físico'],
    'antecedentes' => ['history', 'Antecedentes'],
    'laboratorios' => ['clipboard-list', 'Laboratorios y diagnósticos'],
    'plan'         => ['clipboard-plus', 'Plan de manejo y recomendaciones'],
];
$botonesCE = fn (string $seccion) => botonera(['Guardar', 'Consultar', 'Imprimir', 'Cancelar'], ['Guardar' => $seccion]);
if ($editable): ?>
<form method="post" action="<?= e($accion) ?>" class="formulario formulario-ce" id="form-consulta" data-signos data-una-vez novalidate>
    <?= csrf_campo() ?>
    <input type="hidden" name="accion" value="consulta">
    <input type="hidden" name="ConsConsEdit" value="<?= $consEdit ?>">
<?php endif;
foreach ($bloques as $id => [$icono, $sub2]): ?>
    <?= panel_abrir($id, pestana_titulo($mod, $id), $icono, $tab, $sub2 . ($consEdit ? ' · consulta No. ' . $consEdit : ' · consulta nueva')) ?>
    <?php if ($editable): ?>
        <div class="panel-cuerpo formulario-panel">
        <?= errores_resumen($er) ?>
        <?php if ($id === 'anamnesis'): ?>
            <?= barra_registro('Nuevo', $anteriores, 'FechCons', 'HoraCons', $d, $er, '', '', [], $urlNueva) ?>
            <?php consulta_bloque_anamnesis($d, $er, true, $profesional); ?>
        <?php elseif ($id === 'revision'): ?>
            <?php consulta_bloque_revision($d, $er, 'cons-', true); ?>
        <?php elseif ($id === 'antecedentes'): ?>
            <?php consulta_bloque_antecedentes($d, $er, true, $reco); ?>
        <?php elseif ($id === 'laboratorios'): ?>
            <div class="barra-registro">
                <div class="br-campo"><label>Fecha</label><span><?= e(date('d/m/Y', strtotime($d['FechCons']))) ?></span></div>
                <div class="br-campo"><label>Hora</label><span><?= e(substr((string) $d['HoraCons'], 0, 5)) ?></span></div>
            </div>
            <div class="rejilla">
                <div><span class="etiqueta-campo">Laboratorios</span><p class="nota-campo">Resultados de laboratorio: no disponible.</p></div>
                <div><span class="etiqueta-campo">Últimos Diagnósticos</span><p class="nota-campo"><?php
                    $ult = array_values(array_filter(array_map(fn ($c) => trim((string) $c['CodiDiag']), $consultas)));
                    echo $ult ? e(implode(' · ', array_map('diag_texto', array_slice(array_unique($ult), 0, 5)))) : 'Sin diagnósticos anteriores.'; ?></p></div>
            </div>
            <?php consulta_bloque_laboratorios($d, $er); ?>
        <?php else: ?>
            <div class="barra-registro">
                <div class="br-campo"><label>Fecha</label><span><?= e(date('d/m/Y', strtotime($d['FechCons']))) ?></span></div>
                <div class="br-campo"><label>Hora</label><span><?= e(substr((string) $d['HoraCons'], 0, 5)) ?></span></div>
            </div>
            <?php consulta_bloque_plan($d, $er, true); ?>
            <?php bloque_codigo_dorado($d, $er, true); ?>
        <?php endif; ?>
        <?= $botonesCE($id) ?>
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
