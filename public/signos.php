<?php
/**
 * Registrar una toma de signos vitales en una admision abierta.
 * Peso y talla se proponen con los de la ultima toma (como hace SIHOS).
 */
require __DIR__ . '/../src/inicio.php';
require __DIR__ . '/../src/atencion.php';
require __DIR__ . '/../src/formulario.php';

$u = requiere_login();
$a = admision_o_404($_GET['id'] ?? null);
$volver = 'admision.php?id=' . urlencode($a['ConsAdmi']);

if (!admision_editable($a)) {
    flash('error', 'La admisión no se puede modificar.');
    redirigir($volver);
}

$ultima = signos_de_admision($a['ConsAdmi'])[0] ?? null;
$s = ['FechToma' => date('Y-m-d'), 'HoraToma' => date('H:i'),
      'Peso' => $ultima['Peso'] ?? '', 'Talla' => $ultima['Talla'] ?? ''];
$e = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    [$s, $e] = signos_validar(true);
    if (!$e && $s['FechToma'] . ' ' . $s['HoraToma'] < $a['FechIngr'] . ' ' . $a['HoraIngr']) {
        $e['HoraToma'] = 'La toma no puede ser anterior al ingreso (' . fecha_hora($a['FechIngr'] . ' ' . $a['HoraIngr']) . ').';
    }
    if (!$e) {
        $n = signos_guardar($a, $s, $u['Login']);
        flash('ok', "Toma de signos No. $n registrada.");
        redirigir($volver . '#signos');
    }
}

vista_inicio('Signos vitales');
$usuario = usuario_actual();
?>
<p class="migas"><a href="<?= e($volver) ?>"><?= icono('arrow-left') ?>Volver a la historia</a></p>
<?php encabezado_admision($a); ?>
<?php pestanas_historia($a, 'signos', count(signos_de_admision($a['ConsAdmi']))); ?>
<?= errores_resumen($e) ?>

<form method="post" class="formulario formulario-historia" data-signos data-una-vez>
    <?= csrf_campo() ?>
    <fieldset>
        <legend>Nueva toma de signos vitales <small class="legend-nota">Profesional: <?= e($usuario['Nombre']) ?></small></legend>
        <p class="ayuda">Peso y talla se proponen con los de la última toma.</p>
        <div class="rejilla rejilla-fecha">
            <div><label for="FechToma">Fecha</label>
                <input type="date" id="FechToma" name="FechToma" value="<?= v($s, 'FechToma') ?>" max="<?= date('Y-m-d') ?>" class="<?= ce($e, 'FechToma') ?>" required><?= me($e, 'FechToma') ?></div>
            <div><label for="HoraToma">Hora</label>
                <input type="time" id="HoraToma" name="HoraToma" value="<?= e(substr((string) ($s['HoraToma'] ?? ''), 0, 5)) ?>" class="<?= ce($e, 'HoraToma') ?>" required><?= me($e, 'HoraToma') ?></div>
        </div>
        <div class="subgrupo">
            <?php campos_signos($s, $e); ?>
        </div>
    </fieldset>
    <div class="acciones">
        <button type="submit" class="boton boton-primario"><?= icono('save') ?>Guardar signos</button>
        <a href="<?= e($volver) ?>" class="boton boton-claro"><?= icono('arrow-left') ?>Volver</a>
    </div>
</form>
<script src="js/formularios.js"></script>
<?php
vista_fin();
