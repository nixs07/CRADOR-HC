<?php
/**
 * Remisiones (Urgencias 15, Consulta Externa 7): Remision. En Observación la remisión va dentro del Egreso.
 * Variables: $a, $mod, $editable, $aqui, $tab, $F, $E, $remisiones.
 */
require_once __DIR__ . '/remision_bloques.php';
[$dr, $err] = remision_datos($a, $F, $E);
?>
<?= panel_abrir('remisiones', pestana_titulo($mod, 'remisiones'), 'hospital', $tab,
    count($remisiones) . ' ' . (count($remisiones) === 1 ? 'remisión' : 'remisiones')) ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo"><?php remision_formulario($aqui . '&tab=remisiones', $dr, $err, remision_anteriores($remisiones)); ?></div>
<?php endif; ?>
<?php remision_lista($remisiones); ?>
</section>
