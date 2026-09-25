<?php
/**
 * Triage de Urgencias (con signos vitales de la toma No. 1, como en SIHOS).
 */
require __DIR__ . '/../src/inicio.php';
require __DIR__ . '/../src/atencion.php';
require __DIR__ . '/../src/formulario.php';

$u = requiere_login();
$a = admision_o_404($_GET['id'] ?? null);
$volver = 'admision.php?id=' . urlencode($a['ConsAdmi']);

if (modulo_de_servicio($a['ServEgre']) !== 'urg') {
    flash('error', 'El triage solo se registra en Urgencias.');
    redirigir($volver);
}
if (!admision_editable($a)) {
    flash('error', 'La admisión no se puede modificar.');
    redirigir($volver);
}
if (triage_de_admision($a['ConsAdmi'])) {
    flash('aviso', 'La admisión ya tiene triage.');
    redirigir($volver . '#triage');
}

$t = ['FechTria' => date('Y-m-d'), 'HoraTria' => date('H:i'), 'CodiDiag' => $a['DiagIngr'], 'CondTria' => '1'];
$s = [];
$e = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    [$t, $et] = triage_validar();
    [$s, $es] = signos_validar(false);
    $e = $et + $es;
    if (!$e && $t['FechTria'] . ' ' . $t['HoraTria'] < $a['FechIngr'] . ' ' . $a['HoraIngr']) {
        $e['HoraTria'] = 'El triage no puede ser anterior al ingreso (' . fecha_hora($a['FechIngr'] . ' ' . $a['HoraIngr']) . ').';
    }
    if (!$e) {
        triage_guardar($a, $t, $s, $u['Login']);
        flash('ok', 'Triage registrado.');
        redirigir($volver . '#triage');
    }
}

vista_inicio('Triage');
?>
<p class="migas"><a href="<?= e($volver) ?>">← Volver a la admisión</a></p>
<?php encabezado_admision($a); ?>
<h1>Triage</h1>
<?= errores_resumen($e) ?>

<form method="post" class="formulario" data-signos data-una-vez>
    <?= csrf_campo() ?>
    <fieldset>
        <legend>Valoración</legend>
        <div class="rejilla">
            <div><label for="FechTria">Fecha</label>
                <input type="date" id="FechTria" name="FechTria" value="<?= v($t, 'FechTria') ?>" max="<?= date('Y-m-d') ?>" class="<?= ce($e, 'FechTria') ?>" required><?= me($e, 'FechTria') ?></div>
            <div><label for="HoraTria">Hora</label>
                <input type="time" id="HoraTria" name="HoraTria" value="<?= e(substr($t['HoraTria'] ?? '', 0, 5)) ?>" class="<?= ce($e, 'HoraTria') ?>" required><?= me($e, 'HoraTria') ?></div>
            <div><label for="ClasTria">Clasificación</label>
                <select id="ClasTria" name="ClasTria" class="<?= ce($e, 'ClasTria') ?>" required><?= opciones_arreglo(lista('ClasTria'), $t['ClasTria'] ?? '') ?></select><?= me($e, 'ClasTria') ?></div>
            <div><label for="CondTria">Conducta</label>
                <select id="CondTria" name="CondTria" class="<?= ce($e, 'CondTria') ?>" required><?= opciones('CondTria', $t['CondTria'] ?? '') ?></select><?= me($e, 'CondTria') ?></div>
        </div>
        <label for="MotiCons">Motivo de consulta (palabras del paciente) *</label>
        <textarea id="MotiCons" name="MotiCons" rows="2" maxlength="5000" class="<?= ce($e, 'MotiCons') ?>" required><?= v($t, 'MotiCons') ?></textarea><?= me($e, 'MotiCons') ?>
        <label for="HallClin">Hallazgos clínicos *</label>
        <textarea id="HallClin" name="HallClin" rows="4" maxlength="5000" class="<?= ce($e, 'HallClin') ?>" required><?= v($t, 'HallClin') ?></textarea><?= me($e, 'HallClin') ?>
        <div class="rejilla">
            <div><label for="CodiDiag">Diagnóstico (CIE-10)</label>
                <input type="text" id="CodiDiag" name="CodiDiag" value="<?= v($t, 'CodiDiag') ?>" maxlength="8" data-diagnostico autocomplete="off" class="<?= ce($e, 'CodiDiag') ?>" placeholder="Código o nombre">
                <div class="nota-campo" id="CodiDiag-nombre"><?= e(diagnostico_nombre($t['CodiDiag'] ?? '') ?? '') ?></div><?= me($e, 'CodiDiag') ?></div>
        </div>
        <label for="Conducta">Observaciones de la conducta</label>
        <textarea id="Conducta" name="Conducta" rows="2" maxlength="5000"><?= v($t, 'Conducta') ?></textarea>
    </fieldset>

    <fieldset>
        <legend>Signos vitales</legend>
        <?php campos_signos($s, $e); ?>
    </fieldset>

    <div class="acciones">
        <button type="submit" class="boton boton-primario">Guardar triage</button>
        <a href="<?= e($volver) ?>" class="boton boton-claro">Cancelar</a>
    </div>
</form>
<script src="js/formularios.js"></script>
<?php
vista_fin();
