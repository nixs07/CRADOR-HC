<?php
/**
 * Medicamentos (Urgencias 11, Observación 10): administración de los medicamentos prescritos (HojaMedi).
 * Variables: $a, $mod, $editable, $aqui, $tab, $F, $E, $prescritos, $aplicados.
 */
$dm = $F['medicamento'] ?? ['FechMedi' => date('Y-m-d'), 'HoraMedi' => date('H:i')];
$em = $E['medicamento'] ?? [];
$anteriores = [];
foreach ($aplicados as $m) {
    $anteriores['reg-medi-' . (int) $m['ConsHoMe']] = (int) $m['ConsHoMe'] . ' · ' . fecha_hora($m['FechMedi'] . ' ' . $m['HoraMedi']) . ' · ' . $m['CodiMedi'];
}
?>
<?= panel_abrir('medicamentos', pestana_titulo($mod, 'medicamentos'), 'heart-pulse', $tab,
    count($aplicados) . ' ' . (count($aplicados) === 1 ? 'aplicación registrada' : 'aplicaciones registradas')) ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo">
    <form method="post" action="<?= e($aqui) ?>&amp;tab=medicamentos" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="medicamento">
        <?= errores_resumen($em) ?>
        <?php if (!$prescritos): ?>
            <?= panel_vacio('No hay medicamentos prescritos para aplicar.') ?>
        <?php else: ?>
            <?= barra_registro('Nuevo', $anteriores, 'FechMedi', 'HoraMedi', $dm, $em) ?>
            <p class="ayuda">Solo se aplica lo prescrito en la pestaña <?= e(pestana_titulo($mod, 'prescripcion')) ?>.</p>
            <div class="rejilla">
                <div class="c-ancho-rejilla"><label for="MediPres">Medicamento prescrito <span class="obligatorio" aria-hidden="true">*</span></label>
                    <select id="MediPres" name="MediPres" class="<?= ce($em, 'MediPres') ?>" required>
                        <option value="">— Seleccione —</option>
                        <?php foreach ($prescritos as $llave => $pr): ?>
                            <option value="<?= e($llave) ?>"<?= ($dm['MediPres'] ?? '') === $llave ? ' selected' : '' ?>>Prescripción <?= (int) $pr['ConsPres'] ?> · <?= e($pr['PresMedi']) ?></option>
                        <?php endforeach; ?>
                    </select><?= me($em, 'MediPres') ?></div>
                <div><label for="CantMedi">Cantidad aplicada <span class="obligatorio" aria-hidden="true">*</span></label>
                    <input type="number" id="CantMedi" name="CantMedi" value="<?= v($dm, 'CantMedi') ?>" step="any" min="0" inputmode="decimal" class="<?= ce($em, 'CantMedi') ?>" required><?= me($em, 'CantMedi') ?></div>
                <div class="c-ancho-rejilla"><label for="MediObse">Observación</label>
                    <input type="text" id="MediObse" name="MediObse" value="<?= v($dm, 'MediObse') ?>" maxlength="255"></div>
            </div>
            <?= botones_panel('Registrar aplicación') ?>
        <?php endif; ?>
    </form>

    </div>
<?php endif; ?>

<h3 class="titulo-tabla"><?= icono('history') ?>Aplicaciones registradas</h3>
<?php if (!$aplicados): ?>
    <?= panel_vacio('No hay medicamentos aplicados.') ?>
<?php else: ?>
    <div class="tabla-contenedor">
    <table class="tabla">
        <thead><tr><th>#</th><th>Fecha</th><th>Medicamento</th><th class="num">Cantidad</th><th>Vía</th><th>Observación</th><th>Aplicó</th></tr></thead>
        <tbody>
        <?php foreach ($aplicados as $m): ?>
            <tr id="reg-medi-<?= (int) $m['ConsHoMe'] ?>">
                <td><span class="contador"><?= (int) $m['ConsHoMe'] ?></span></td>
                <td class="sin-salto"><?= e(fecha_hora($m['FechMedi'] . ' ' . $m['HoraMedi'])) ?></td>
                <td><strong><?= e($m['CodiMedi']) ?></strong> · <?= e($m['NombSumi'] ?? '') ?></td>
                <td class="num"><?= e((float) $m['CantMedi']) ?> <?= e(lista_nombre('UnidMedi', $m['UnidMedi'])) ?></td>
                <td><?= e(lista_nombre('ViaAdmi', $m['ViaAdmi'])) ?></td>
                <td><?= e($m['IndiAdic']) ?></td>
                <td><?= e($m['UsuaAsis']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>

</section>
