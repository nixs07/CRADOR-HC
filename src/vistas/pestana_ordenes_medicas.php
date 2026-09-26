<?php
/**
 * ORDENES MEDICAS (Urgencias 5, Observación 4): orden médica en texto libre
 * (EncaData/DetaData TipoObje 7, CodiItem 131 Urgencias y 130 Observación). No existe en Consulta Externa.
 * Variables: $a, $mod, $editable, $aqui, $tab, $F, $E, $ordenesMedicas.
 */
$dm = $F['orden_medica'] ?? ['FechOrMe' => date('Y-m-d'), 'HoraOrMe' => date('H:i')];
$em = $E['orden_medica'] ?? [];
$anteriores = [];
foreach ($ordenesMedicas as $o) {
    $anteriores['reg-ordmed-' . (int) $o['ConsData']] = (int) $o['ConsData'] . ' · ' . fecha_hora($o['Fecha'] . ' ' . $o['Hora']);
}
?>
<?= panel_abrir('ordenes_medicas', pestana_titulo($mod, 'ordenes_medicas'), 'file-text', $tab,
    count($ordenesMedicas) . ' ' . (count($ordenesMedicas) === 1 ? 'orden médica' : 'órdenes médicas')) ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo">
    <?= errores_resumen($em) ?>
    <form method="post" action="<?= e($aqui) ?>&amp;tab=ordenes_medicas" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="orden_medica">
        <?= barra_registro('Nuevo', $anteriores, 'FechOrMe', 'HoraOrMe', $dm, $em) ?>
        <?= campo_texto('TextoOrden', 'Orden médica (dieta, líquidos, cuidados, control de signos…)', $dm, $em, 6, true, 10000) ?>
        <?= botones_panel('Guardar orden médica') ?>
    </form>
    </div>
<?php endif; ?>

<h3 class="titulo-tabla"><?= icono('history') ?>Órdenes médicas registradas</h3>
<?php if (!$ordenesMedicas): ?>
    <?= panel_vacio('No hay órdenes médicas registradas.') ?>
<?php else: ?>
    <div class="registros">
    <?php foreach ($ordenesMedicas as $o): ?>
        <div class="registro registro-abierto" id="reg-ordmed-<?= (int) $o['ConsData'] ?>">
            <div class="registro-cabeza"><span class="contador"><?= (int) $o['ConsData'] ?></span>
                <strong><?= e(fecha_hora($o['Fecha'] . ' ' . $o['Hora'])) ?></strong><small><?= e($o['UsuaAsis']) ?></small></div>
            <p class="registro-nota texto-largo"><?= e($o['Texto']) ?></p>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
</section>
