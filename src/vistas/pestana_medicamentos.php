<?php
/**
 * Medicamentos (Urgencias 11, Observación 10, Consulta Externa 16): aplicación de lo prescrito (HojaMedi), en
 * REJILLA como SIHOS: No. (prescripción) · Fecha · Hora y una fila por medicamento prescrito con Fecha/Hora
 * aplicación · Fecha/Hora planeado · Código · Nombre · Vía · Cantidad · Unidad · Observaciones · Profesional ·
 * Módulo · Suspensión. Se aplican las filas con cantidad. ?pres=N escoge la prescripción (por defecto la última).
 * Variables: $a, $mod, $u, $editable, $aqui, $tab, $F, $E, $prescritos, $aplicados.
 */
$em = $E['medicamento'] ?? [];
$porPres = [];
foreach ($prescritos as $llave => $pr) {
    $porPres[(int) $pr['ConsPres']][$llave] = $pr;
}
$presSel = isset($_GET['pres'], $porPres[(int) $_GET['pres']]) ? (int) $_GET['pres'] : ($porPres ? max(array_keys($porPres)) : 0);
?>
<?= panel_abrir('medicamentos', pestana_titulo($mod, 'medicamentos'), 'heart-pulse', $tab,
    count($aplicados) . ' ' . (count($aplicados) === 1 ? 'aplicación registrada' : 'aplicaciones registradas')) ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo">
    <?= errores_resumen($em) ?>
    <?php if (!$prescritos): ?>
        <?= panel_vacio('No hay medicamentos prescritos para aplicar.') ?>
    <?php else: ?>
        <form method="get" action="atencion.php" class="barra-registro" data-auto-envio>
            <input type="hidden" name="id" value="<?= e($a['ConsAdmi']) ?>"><input type="hidden" name="tab" value="medicamentos">
            <div class="br-campo"><label for="pres">No.</label>
                <select id="pres" name="pres">
                    <?php foreach (array_keys($porPres) as $np): ?><option value="<?= $np ?>"<?= $np === $presSel ? ' selected' : '' ?>><?= $np ?></option><?php endforeach; ?>
                </select></div>
            <div class="br-campo"><label>Fecha</label><span><?= date('d/m/Y') ?></span></div>
            <div class="br-campo"><label>Hora</label><span><?= date('H:i') ?></span></div>
            <noscript><button type="submit" class="boton boton-claro boton-chico">Ver</button></noscript>
        </form>
        <form method="post" action="<?= e($aqui) ?>&amp;tab=medicamentos&amp;pres=<?= $presSel ?>" class="formulario formulario-panel" data-una-vez>
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="medicamento">
            <div class="rejilla-grilla">
            <table>
                <thead><tr><th>Fecha aplicación</th><th>Hora</th><th>Fecha planeado</th><th>Hora</th><th>Código</th><th>Nombre</th><th>Vía</th>
                    <th>Cantidad</th><th>Unidad</th><th>Observaciones</th><th>Profesional</th><th>Módulo</th><th>Suspensión</th></tr></thead>
                <tbody>
                <?php foreach ($porPres[$presSel] ?? [] as $llave => $pr): ?>
                    <tr>
                        <td><input type="hidden" name="MediItem[]" value="<?= e($llave) ?>">
                            <input type="date" name="FechMedi[]" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" aria-label="Fecha de aplicación"></td>
                        <td class="c-num"><input type="time" name="HoraMedi[]" value="<?= date('H:i') ?>" aria-label="Hora de aplicación"></td>
                        <td><input type="date" name="FechPlan[]" value="<?= date('Y-m-d') ?>" aria-label="Fecha planeada"></td>
                        <td class="c-num"><input type="time" name="HoraPlan[]" value="<?= e(substr((string) $pr['HoraInic'], 0, 5)) ?>" aria-label="Hora planeada"></td>
                        <td><strong><?= e($pr['CodiSumi']) ?></strong></td>
                        <td><?= e($pr['NombSumi'] ?? '') ?><div class="nota-campo"><?= e($pr['PresMedi']) ?></div></td>
                        <td><?= e(lista_nombre('ViaAdmi', $pr['CodiVia'])) ?></td>
                        <td class="c-num"><input type="number" name="CantMedi[]" value="" step="any" min="0" inputmode="decimal" aria-label="Cantidad a aplicar de <?= e($pr['CodiSumi']) ?>"></td>
                        <td><?= e(lista_nombre('UnidMedi', $pr['UnidMedi'])) ?></td>
                        <td><input type="text" name="MediObse[]" maxlength="255" aria-label="Observaciones"></td>
                        <td><?= e($u['Login']) ?></td>
                        <td><?= e($mod['nombre']) ?></td>
                        <td><?= $pr['FechSusp'] !== '0000-00-00' ? e($pr['FechSusp']) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p class="nota-campo">Se registran las filas con cantidad. Solo se aplica lo prescrito en la pestaña <?= e(pestana_titulo($mod, 'prescripcion')) ?>.</p>
            <?= botonera(['Guardar', 'Consultar', 'Imprimir', 'Cancelar']) ?>
        </form>
    <?php endif; ?>
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
