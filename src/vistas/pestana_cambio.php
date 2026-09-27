<?php
/**
 * Cambio de Atención (Observación 23): traslado de cama (TrasCama) como PESTAÑA, igual que SIHOS
 * (docs/RECORRIDO_SIHOS.md §4): Nuevo · No. · Fecha · Hora · Atención Origen (Servicio, Centro de Costos, Cama,
 * Estadística Días/Horas) · Atención Destino (Institución, Servicio, Entorno de atención, Centro de costos, Cama) ·
 * Guardar · Cancelar · Imprimir · tabla No. · Fecha · Hora · Servicio Origen · Cama Origen · Días · Horas · Módulo ·
 * Institución · Servicio Destino · Cama Destino. Solo se traslada dentro de la institución (CoinDest vacío).
 * Variables: $a, $mod, $editable, $aqui, $tab, $F, $E.
 */
$trasDatos = $F['traslado'] ?? ['FechTras' => date('Y-m-d'), 'HoraTras' => date('H:i'), 'ServDest' => $a['ServEgre']];
$trasErr = $E['traslado'] ?? [];
$trasLista = traslados_de_admision($a['ConsAdmi']);
$trasServicios = array_intersect_key(lista('Serv'), array_flip($mod['servicios']));
[$fi, $hi] = traslado_inicio_tramo($a['ConsAdmi'], $a);
$seg = max(0, time() - strtotime("$fi $hi"));
$anteriores = [];
foreach ($trasLista as $t) {
    $anteriores['reg-tras-' . (int) $t['ConsTras']] = (int) $t['ConsTras'] . ' · ' . fecha_hora($t['FechSali'] . ' ' . $t['HoraSali']);
}
?>
<?= panel_abrir('cambio', pestana_titulo($mod, 'cambio'), 'bed-double', $tab,
    count($trasLista) . ' ' . (count($trasLista) === 1 ? 'cambio registrado' : 'cambios registrados') . ' · cama actual ' . ($a['CamaActu'] ?: '—')) ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo">
    <?= errores_resumen($trasErr) ?>
    <form method="post" action="<?= e($aqui) ?>&amp;tab=cambio" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="traslado">
        <?= barra_registro('Nuevo', $anteriores, 'FechTras', 'HoraTras', $trasDatos, $trasErr) ?>
        <div class="panel-dos-columnas">
            <fieldset class="grupo-sihos">
                <legend>Atención Origen</legend>
                <div class="rejilla">
                    <?= campo_lectura('Servicio', lista_nombre('Serv', $a['ServEgre'])) ?>
                    <?= campo_lectura('Centro de Costos', $a['CentEgre']) ?>
                    <?= campo_lectura('Cama', $a['CamaActu']) ?>
                    <?= campo_lectura('Estadística', intdiv($seg, 86400) . ' días, ' . intdiv($seg % 86400, 3600) . ' horas') ?>
                </div>
            </fieldset>
            <fieldset class="grupo-sihos">
                <legend>Atención Destino</legend>
                <div class="rejilla">
                    <?= campo_lectura('Institución', CODI_INST) ?>
                    <div><label for="ServDest">Servicio</label>
                        <select id="ServDest" name="ServDest" class="<?= ce($trasErr, 'ServDest') ?>" required><?= opciones_arreglo($trasServicios, $trasDatos['ServDest'] ?? '', false) ?></select><?= me($trasErr, 'ServDest') ?></div>
                    <?= campo_lectura('Entorno de atención', $a['EntoAten']) ?>
                    <?= campo_lectura('Centro de costos', '') ?>
                    <div><label for="CamaDest">Cama</label>
                        <select id="CamaDest" name="CamaDest" class="<?= ce($trasErr, 'CamaDest') ?>" required
                                data-depende="api.php?que=camas" data-de="ServDest" data-param="serv">
                            <?= opciones_arreglo(array_filter(camas($trasDatos['ServDest'] ?? $a['ServEgre']), fn ($n) => strpos($n, '(ocupada)') === false), $trasDatos['CamaDest'] ?? '') ?>
                        </select><?= me($trasErr, 'CamaDest') ?></div>
                </div>
            </fieldset>
        </div>
        <?= botonera(['Guardar', 'Cancelar', 'Imprimir']) ?>
    </form>
    </div>
<?php endif; ?>

<h3 class="titulo-tabla"><?= icono('history') ?>Cambios de atención</h3>
<?php if (!$trasLista): ?>
    <?= panel_vacio('No hay cambios de atención.') ?>
<?php else: ?>
    <div class="tabla-contenedor">
    <table class="tabla">
        <thead><tr><th>No.</th><th>Fecha</th><th>Hora</th><th>Servicio Origen</th><th>Cama Origen</th><th class="num">Días</th><th class="num">Horas</th>
            <th>Módulo</th><th>Institución</th><th>Servicio Destino</th><th>Cama Destino</th></tr></thead>
        <tbody>
        <?php foreach ($trasLista as $t): ?>
            <tr id="reg-tras-<?= (int) $t['ConsTras'] ?>">
                <td><span class="contador"><?= (int) $t['ConsTras'] ?></span></td>
                <td class="sin-salto"><?= e(date('d/m/Y', strtotime($t['FechSali']))) ?></td>
                <td><?= e(substr($t['HoraSali'], 0, 5)) ?></td>
                <td><?= e(lista_nombre('Serv', $t['CodiServ'])) ?></td>
                <td><?= e($t['CamaOrig']) ?></td>
                <td class="num"><?= (int) $t['Dias'] ?></td>
                <td class="num"><?= (int) $t['Horas'] ?></td>
                <td><?= (int) $t['CodiModu'] ?></td>
                <td><?= e($t['CoinDest'] ?: CODI_INST) ?></td>
                <td><?= e(lista_nombre('Serv', $t['ServEgre'])) ?></td>
                <td><strong><?= e($t['CamaDest']) ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</section>
