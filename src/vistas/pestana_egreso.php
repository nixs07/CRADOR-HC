<?php
/**
 * Pestaña 9. Egreso (SaliInte) y cierre de la admisión. En Consulta Externa es "Cerrar atención":
 * solo marca la admisión como cerrada (ver supuestos en docs/REGLAS.md).
 * Tambien remision (Remision) e incapacidad (IncaPaci), antes del egreso.
 * Variables: $a, $editable, $aqui, $tab, $F, $E, $egreso, $mod, $remisiones, $incapacidades.
 */
$esCE = $mod['clave'] === 'ce';
$d = $F['egreso'] ?? ['FechSali' => date('Y-m-d'), 'HoraSali' => date('H:i'), 'CausSali' => '1', 'DestSali' => '01', 'EstaSali' => '1',
                      'TipoEgre' => '1', 'EgreTipoDiag' => '2', 'DiagEgre' => $a['DiagIngr']];
$er = $E['egreso'] ?? [];
$dr = $F['remision'] ?? ['FechRemi' => date('Y-m-d'), 'HoraRemi' => date('H:i'), 'RemiTipoDiag' => '2', 'DiagRemi' => $a['DiagIngr']];
$err = $E['remision'] ?? [];
$di = $F['incapacidad'] ?? ['FechInca' => date('Y-m-d'), 'HoraInca' => date('H:i'), 'TipoInca' => '1', 'OrigInca' => '1'];
$eri = $E['incapacidad'] ?? [];
// Codigo del estado "muerto" en el catalogo EstaSali (para mostrar los datos de muerte)
$codMuerto = '';
foreach (lista('EstaSali') as $c => $n) {
    if (estado_salida_muerto($c)) { $codMuerto = (string) $c; break; }
}
?>
<?= panel_abrir('egreso', $esCE ? '9. Cerrar atención' : '9. Egreso', 'log-out', $tab,
    (int) $a['Cerrado'] === 1 ? 'Admisión cerrada' : ($esCE ? 'Cierra la atención de Consulta Externa' : 'Salida del paciente y cierre de la admisión')) ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo panel-dos">
    <details class="subseccion"<?= $err ? ' open' : '' ?>>
        <summary><?= icono('hospital') ?>Remisión a otra institución</summary>
        <form method="post" action="<?= e($aqui) ?>&amp;tab=egreso" class="formulario formulario-panel" data-una-vez>
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="remision">
            <?= errores_resumen($err) ?>
            <div class="rejilla">
                <?= campos_fecha_hora('FechRemi', 'HoraRemi', $dr, $err) ?>
                <?= campo_lista('RemiMoti', 'Motivo de remisión', 'MotiRemi', $dr, $err) ?>
                <?= campo_lista('ModaSoli', 'Modalidad de la solicitud', 'ModaSoli', $dr, $err) ?>
                <?= campo_lista('EspeRemi', 'Especialidad que recibe', 'Espe', $dr, $err, false) ?>
                <div class="c-ancho-rejilla"><label for="InstDest">Institución destino</label>
                    <input type="text" id="InstDest" name="InstDest" value="<?= v($dr, 'InstDest') ?>" maxlength="200"></div>
                <?= campo_buscador('DiagRemi', 'Diagnóstico de remisión (CIE-10)', $dr, $err, 'diagnosticos', true) ?>
                <?= campo_lista('RemiTipoDiag', 'Tipo de diagnóstico', 'TipoDiag', $dr + ['RemiTipoDiag' => $dr['TipoDiag'] ?? '2'], $err) ?>
            </div>
            <?= campo_texto('MotiRemiTexto', 'Resumen clínico / motivo', $dr + ['MotiRemiTexto' => $dr['MotiRemi'] ?? ''], $err, 3, true) ?>
            <?= campo_texto('OtroMoti', 'Otro motivo u observación', $dr, $err, 2, false, 2000) ?>
            <div class="rejilla">
                <div><label for="NombAcep">Persona que acepta</label><input type="text" id="NombAcep" name="NombAcep" value="<?= v($dr, 'NombAcep') ?>" maxlength="80"></div>
                <div><label for="CargAcep">Cargo</label><input type="text" id="CargAcep" name="CargAcep" value="<?= v($dr, 'CargAcep') ?>" maxlength="40"></div>
                <div><label for="RemiAuto">Autorización</label><input type="text" id="RemiAuto" name="RemiAuto" value="<?= v($dr + ['RemiAuto' => $dr['NumeAuto'] ?? ''], 'RemiAuto') ?>" maxlength="15"></div>
                <div><span class="etiqueta-campo">Traslado</span>
                    <label class="opcion"><input type="checkbox" name="Ambulanc" value="1"<?= !empty($dr['Ambulanc']) ? ' checked' : '' ?>> En ambulancia</label></div>
                <div><label for="PlacAmbu">Placa de la ambulancia</label><input type="text" id="PlacAmbu" name="PlacAmbu" value="<?= v($dr, 'PlacAmbu') ?>" maxlength="10" class="<?= ce($err, 'PlacAmbu') ?>"><?= me($err, 'PlacAmbu') ?></div>
            </div>
            <?= botones_panel('Guardar remisión') ?>
        </form>
    </details>

    <details class="subseccion"<?= $eri ? ' open' : '' ?>>
        <summary><?= icono('file-text') ?>Incapacidad</summary>
        <form method="post" action="<?= e($aqui) ?>&amp;tab=egreso" class="formulario formulario-panel" data-una-vez>
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="incapacidad">
            <?= errores_resumen($eri) ?>
            <div class="rejilla">
                <?= campos_fecha_hora('FechInca', 'HoraInca', $di, $eri) ?>
                <?= campo_lista('TipoInca', 'Tipo de incapacidad', 'TipoInca', $di, $eri) ?>
                <div><label for="OrigInca">Origen</label>
                    <select id="OrigInca" name="OrigInca" class="<?= ce($eri, 'OrigInca') ?>" required>
                        <option value="1"<?= (string) ($di['OrigInca'] ?? '1') === '1' ? ' selected' : '' ?>>Común</option>
                        <option value="2"<?= (string) ($di['OrigInca'] ?? '') === '2' ? ' selected' : '' ?>>Laboral</option>
                    </select><?= me($eri, 'OrigInca') ?></div>
                <div><label for="DiasIncaPaci">Días <span class="obligatorio" aria-hidden="true">*</span></label>
                    <input type="number" id="DiasIncaPaci" name="DiasIncaPaci" value="<?= e($di['DiasInca'] ?? '') ?>" min="1" max="540" class="<?= ce($eri, 'DiasIncaPaci') ?>" required><?= me($eri, 'DiasIncaPaci') ?></div>
            </div>
            <?= campo_texto('ObseInca', 'Observaciones', $di, $eri, 2) ?>
            <?= botones_panel('Guardar incapacidad') ?>
        </form>
    </details>

    <?= errores_resumen($er) ?>
    <form method="post" action="<?= e($aqui) ?>&amp;tab=egreso" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="egreso">
        <div class="rejilla">
            <?= campos_fecha_hora('FechSali', 'HoraSali', $d, $er) ?>
            <?php if (!$esCE): ?>
                <?= campo_lista('CausSali', 'Causa de salida', 'CausSali', $d, $er) ?>
                <?= campo_lista('DestSali', 'Destino', 'DestSali', $d, $er) ?>
                <?= campo_lista('EstaSali', 'Estado a la salida', 'EstaSali', $d, $er) ?>
                <?= campo_lista('TipoEgre', 'Tipo de egreso', 'TipoEgre', $d, $er) ?>
            <?php endif; ?>
        </div>
        <?php if (!$esCE): ?>
            <div class="rejilla">
                <?= campo_buscador('DiagEgre', 'Diagnóstico de egreso (CIE-10)', $d, $er, 'diagnosticos', true) ?>
                <?= campo_lista('EgreTipoDiag', 'Tipo de diagnóstico', 'TipoDiag', $d + ['EgreTipoDiag' => $d['TipoDiag'] ?? '2'], $er) ?>
                <?= campo_buscador('EgreRel1', 'Relacionado', $d + ['EgreRel1' => $d['DiagRel1'] ?? ''], $er, 'diagnosticos') ?>
                <?= campo_lista('EgreTipoRel1', 'Tipo relacionado', 'TipoDiag', $d, $er, false) ?>
                <div><label for="DiasInca">Días de incapacidad</label>
                    <input type="number" id="DiasInca" name="DiasInca" value="<?= v($d, 'DiasInca') ?>" min="0" max="99" class="<?= ce($er, 'DiasInca') ?>"><?= me($er, 'DiasInca') ?></div>
            </div>
            <?php if ($codMuerto !== ''): ?>
            <div class="subgrupo" data-mostrar-si="EstaSali=<?= e($codMuerto) ?>">
                <h3><?= icono('triangle-alert') ?>Datos de la muerte</h3>
                <div class="rejilla">
                    <?= campo_buscador('DiagMuer', 'Causa de muerte (CIE-10)', $d, $er, 'diagnosticos') ?>
                    <?= campos_fecha_hora('FechMuer', 'HoraMuer', $d, $er, false) ?>
                </div>
            </div>
            <?php endif; ?>
            <?= campo_texto('ObseSali', 'Observaciones de la salida', $d, $er, 3) ?>
        <?php endif; ?>
        <div class="confirmar <?= ce($er, 'ConfEgre') ?>">
            <label class="opcion"><input type="checkbox" name="ConfEgre" value="1" required>
                <strong>Confirmo <?= $esCE ? 'el cierre de la atención' : 'el egreso' ?>:</strong> la admisión <?= e($a['ConsAdmi']) ?> quedará cerrada y ya no se podrá modificar<?= $mod['cama'] ? '; la cama ' . e($a['CamaActu']) . ' queda libre' : '' ?>.</label>
            <?= me($er, 'ConfEgre') ?>
        </div>
        <div class="acciones acciones-panel">
            <button type="submit" class="boton boton-peligro"><?= icono('log-out') ?><?= $esCE ? 'Cerrar atención' : 'Guardar egreso y cerrar admisión' ?></button>
        </div>
    </form>
    </div>
<?php elseif ($egreso): ?>
    <dl class="datos">
        <dt>Fecha y hora de salida</dt><dd><?= e(fecha_hora($egreso['FechSali'] . ' ' . $egreso['HoraSali'])) ?> · <?= e($egreso['UsuaDigi']) ?></dd>
        <dt>Estancia</dt><dd><?= (int) $egreso['DiasEsta'] ?> días, <?= (int) $egreso['HoraEsta'] ?> horas</dd>
        <dt>Causa / destino</dt><dd><?= e(lista_nombre('CausSali', $egreso['CausSali'])) ?> · <?= e(lista_nombre('DestSali', $egreso['DestSali'])) ?></dd>
        <dt>Estado / tipo de egreso</dt><dd><?= e(lista_nombre('EstaSali', $egreso['EstaSali'])) ?> · <?= e(lista_nombre('TipoEgre', $egreso['TipoEgre'])) ?></dd>
        <dt>Diagnóstico de egreso</dt><dd><?= e(diag_texto($egreso['DiagEgre'])) ?><?= trim((string) $egreso['DiagRel1']) !== '' ? '<br>' . e(diag_texto($egreso['DiagRel1'])) : '' ?></dd>
        <?php if ($egreso['DiagMuer']): ?><dt>Causa de muerte</dt><dd><?= e(diag_texto($egreso['DiagMuer'])) ?> · <?= e(fecha_hora($egreso['FechMuer'] . ' ' . $egreso['HoraMuer'])) ?></dd><?php endif; ?>
        <dt>Observaciones</dt><dd class="texto-largo"><?= texto_registro($egreso['ObseSali']) ?></dd>
    </dl>
<?php elseif ((int) $a['Cerrado'] === 1): ?>
    <?= panel_vacio('Atención cerrada el ' . fecha_hora($a['FechCier'] . ' ' . $a['HoraCier']) . ' por ' . $a['UsuaCier'] . '.') ?>
<?php else: ?>
    <?= panel_vacio('La admisión no se puede modificar.') ?>
<?php endif; ?>

<h3 class="titulo-tabla"><?= icono('history') ?>Remisiones</h3>
<?php if (!$remisiones): ?>
    <?= panel_vacio('No hay remisiones.') ?>
<?php else: ?>
    <div class="registros">
    <?php foreach ($remisiones as $r): ?>
        <div class="registro registro-abierto">
            <div class="registro-cabeza"><span class="contador"><?= (int) $r['CodiRemi'] ?></span>
                <strong><?= e(fecha_hora($r['FechSali'] . ' ' . $r['HoraSali'])) ?></strong>
                <span class="etiqueta"><?= e($r['NombMoti'] ?? $r['RemiMoti']) ?></span>
                <small><?= e($r['NombModa'] ?? $r['ModaSoli']) ?><?= (int) $r['Ambulanc'] ? ' · ambulancia ' . e($r['PlacAmbu']) : '' ?> · <?= e(diag_texto($r['DiagRemi'])) ?> · <?= e($r['UsuaDigi']) ?></small></div>
            <p class="registro-nota texto-largo"><?= e($r['MotiRemi']) ?><?= $r['NombAcep'] ? "\nAcepta: " . e($r['NombAcep'] . ($r['CargAcep'] ? ' (' . $r['CargAcep'] . ')' : '')) : '' ?></p>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<h3 class="titulo-tabla"><?= icono('history') ?>Incapacidades</h3>
<?php if (!$incapacidades): ?>
    <?= panel_vacio('No hay incapacidades.') ?>
<?php else: ?>
    <div class="tabla-contenedor">
    <table class="tabla">
        <thead><tr><th>#</th><th>Fecha</th><th>Tipo</th><th>Origen</th><th class="num">Días</th><th>Observaciones</th><th>Registró</th></tr></thead>
        <tbody>
        <?php foreach ($incapacidades as $i): ?>
            <tr>
                <td><span class="contador"><?= (int) $i['ConsInca'] ?></span></td>
                <td class="sin-salto"><?= e(fecha_hora($i['FechInca'] . ' ' . $i['HoraInca'])) ?></td>
                <td><?= e($i['NombTipo'] ?? $i['TipoInca']) ?></td>
                <td><?= (int) $i['OrigInca'] === 2 ? 'Laboral' : 'Común' ?></td>
                <td class="num"><?= (int) $i['DiasInca'] ?></td>
                <td><?= e($i['ObseInca']) ?></td>
                <td><?= e($i['UsuaDigi']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</section>
