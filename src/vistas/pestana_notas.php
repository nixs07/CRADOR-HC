<?php
/**
 * Pestaña 7. Notas de enfermería (HojaEnfe) y administración de medicamentos prescritos (HojaMedi).
 * Tambien los materiales usados (HojaMate).
 * Variables: $a, $editable, $aqui, $tab, $F, $E, $notas, $prescritos, $aplicados, $materiales.
 */
$d = $F['nota'] ?? ['FechNota' => date('Y-m-d'), 'HoraNota' => date('H:i'), 'TipoNota' => '1'];
$er = $E['nota'] ?? [];
$dm = $F['medicamento'] ?? ['FechMedi' => date('Y-m-d'), 'HoraMedi' => date('H:i')];
$em = $E['medicamento'] ?? [];
$dt = $F['material'] ?? ['FechMate' => date('Y-m-d'), 'HoraMate' => date('H:i')];
$et = $E['material'] ?? [];
?>
<?= panel_abrir('notas', '7. Notas de enfermería', 'clipboard-list', $tab, count($notas) . ' notas · ' . count($aplicados) . ' medicamentos aplicados · ' . count($materiales) . ' materiales') ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo panel-dos">
    <form method="post" action="<?= e($aqui) ?>&amp;tab=notas" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="nota">
        <h3 class="titulo-form"><?= icono('plus') ?>Nueva nota</h3>
        <?= errores_resumen($er) ?>
        <div class="rejilla">
            <?= campos_fecha_hora('FechNota', 'HoraNota', $d, $er) ?>
            <?= campo_lista('TipoNota', 'Tipo de nota', 'TipoNota', $d, $er) ?>
        </div>
        <?= campo_texto('NotaEnfe', 'Nota', $d, $er, 5, true, 10000) ?>
        <?= botones_panel('Guardar nota') ?>
    </form>

    <form method="post" action="<?= e($aqui) ?>&amp;tab=notas" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="medicamento">
        <h3 class="titulo-form"><?= icono('heart-pulse') ?>Administración de medicamentos <span class="legend-nota">Solo lo prescrito en la pestaña 4</span></h3>
        <?= errores_resumen($em) ?>
        <?php if (!$prescritos): ?>
            <?= panel_vacio('No hay medicamentos prescritos para aplicar.') ?>
        <?php else: ?>
            <div class="rejilla">
                <?= campos_fecha_hora('FechMedi', 'HoraMedi', $dm, $em) ?>
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

    <form method="post" action="<?= e($aqui) ?>&amp;tab=notas" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="material">
        <h3 class="titulo-form"><?= icono('clipboard-plus') ?>Materiales usados</h3>
        <?= errores_resumen($et) ?>
        <div class="rejilla">
            <?= campos_fecha_hora('FechMate', 'HoraMate', $dt, $et) ?>
            <?= campo_buscador('CodiMate', 'Material o suministro', $dt, $et, 'suministros', true) ?>
            <?= campo_lista('UnidMate', 'Unidad', 'CodiUnid', $dt, $et) ?>
            <div><label for="CantMate">Cantidad <span class="obligatorio" aria-hidden="true">*</span></label>
                <input type="number" id="CantMate" name="CantMate" value="<?= v($dt, 'CantMate') ?>" step="any" min="0" inputmode="decimal" class="<?= ce($et, 'CantMate') ?>" required><?= me($et, 'CantMate') ?></div>
            <div class="c-ancho-rejilla"><label for="MateObse">Observación</label>
                <input type="text" id="MateObse" name="MateObse" value="<?= v($dt, 'MateObse') ?>" maxlength="255"></div>
        </div>
        <?= botones_panel('Registrar material') ?>
    </form>
    </div>
<?php endif; ?>

<h3 class="titulo-tabla"><?= icono('history') ?>Notas registradas</h3>
<?php if (!$notas): ?>
    <?= panel_vacio('No hay notas de enfermería.') ?>
<?php else: ?>
    <div class="registros">
    <?php foreach ($notas as $n): ?>
        <div class="registro registro-abierto">
            <div class="registro-cabeza"><span class="contador"><?= (int) $n['ConsHoEn'] ?></span>
                <strong><?= e(fecha_hora($n['FechNota'] . ' ' . $n['HoraNota'])) ?></strong>
                <span class="etiqueta"><?= e($n['NombTipo'] ?? $n['TipoNota']) ?></span><small><?= e($n['UsuaDigi']) ?></small></div>
            <p class="registro-nota texto-largo"><?= e($n['NotaEnfe']) ?></p>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<h3 class="titulo-tabla"><?= icono('history') ?>Medicamentos aplicados</h3>
<?php if (!$aplicados): ?>
    <?= panel_vacio('No hay medicamentos aplicados.') ?>
<?php else: ?>
    <div class="tabla-contenedor">
    <table class="tabla">
        <thead><tr><th>#</th><th>Fecha</th><th>Medicamento</th><th class="num">Cantidad</th><th>Vía</th><th>Observación</th><th>Aplicó</th></tr></thead>
        <tbody>
        <?php foreach ($aplicados as $m): ?>
            <tr>
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

<h3 class="titulo-tabla"><?= icono('history') ?>Materiales usados</h3>
<?php if (!$materiales): ?>
    <?= panel_vacio('No hay materiales registrados.') ?>
<?php else: ?>
    <div class="tabla-contenedor">
    <table class="tabla">
        <thead><tr><th>#</th><th>Fecha</th><th>Material</th><th class="num">Cantidad</th><th>Observación</th><th>Registró</th></tr></thead>
        <tbody>
        <?php foreach ($materiales as $m): ?>
            <tr>
                <td><span class="contador"><?= (int) $m['ConsHoMa'] ?></span></td>
                <td class="sin-salto"><?= e(fecha_hora($m['FechMate'] . ' ' . $m['HoraMate'])) ?></td>
                <td><strong><?= e($m['CodiMate']) ?></strong> · <?= e($m['NombSumi'] ?? '') ?></td>
                <td class="num"><?= e((float) $m['CantMate']) ?> <?= e(lista_nombre('CodiUnid', $m['UnidMate'])) ?></td>
                <td><?= e($m['IndiAdic']) ?></td>
                <td><?= e($m['UsuaAsis']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</section>
