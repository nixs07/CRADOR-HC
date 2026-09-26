<?php
/**
 * Traslado de cama (TrasCama), dentro del encabezado de la admisión. Solo Observación e Internación.
 * Variables: $a, $mod, $editable, $aqui, $tab, $F, $E.
 */
$trasDatos = $F['traslado'] ?? ['FechTras' => date('Y-m-d'), 'HoraTras' => date('H:i'), 'ServDest' => $a['ServEgre']];
$trasErr = $E['traslado'] ?? [];
$trasLista = traslados_de_admision($a['ConsAdmi']);
$trasServicios = array_intersect_key(lista('Serv'), array_flip($mod['servicios']));
?>
<details class="ea-mas"<?= $trasErr ? ' open' : '' ?> id="traslado">
    <summary><?= icono('bed-double') ?>Traslado de cama<?= $trasLista ? ' · ' . count($trasLista) . ' ' . (count($trasLista) === 1 ? 'traslado' : 'traslados') : '' ?></summary>
    <div class="et-traslado">
        <?php if ($editable): ?>
        <form method="post" action="<?= e($aqui) ?>&amp;tab=<?= e($tab) ?>" class="formulario formulario-panel" data-una-vez>
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="traslado">
            <?= errores_resumen($trasErr) ?>
            <div class="rejilla">
                <div><span class="etiqueta-campo">Cama actual</span><strong class="cama-actual"><?= icono('bed-double') ?><?= e($a['CamaActu'] ?: '—') ?></strong></div>
                <div><label for="ServDest">Servicio destino</label>
                    <select id="ServDest" name="ServDest" class="<?= ce($trasErr, 'ServDest') ?>" required><?= opciones_arreglo($trasServicios, $trasDatos['ServDest'] ?? '', false) ?></select><?= me($trasErr, 'ServDest') ?></div>
                <div><label for="CamaDest">Cama destino</label>
                    <select id="CamaDest" name="CamaDest" class="<?= ce($trasErr, 'CamaDest') ?>" required
                            data-depende="api.php?que=camas" data-de="ServDest" data-param="serv">
                        <?= opciones_arreglo(array_filter(camas($trasDatos['ServDest'] ?? $a['ServEgre']), fn ($n) => strpos($n, '(ocupada)') === false), $trasDatos['CamaDest'] ?? '') ?>
                    </select><?= me($trasErr, 'CamaDest') ?></div>
                <?= campos_fecha_hora('FechTras', 'HoraTras', $trasDatos, $trasErr) ?>
            </div>
            <div class="acciones acciones-panel"><button type="submit" class="boton boton-primario"><?= icono('bed-double') ?>Trasladar</button></div>
        </form>
        <?php endif; ?>
        <?php if ($trasLista): ?>
            <div class="tabla-contenedor">
            <table class="tabla">
                <thead><tr><th>#</th><th>Cama origen</th><th>Desde</th><th>Hasta</th><th>Estancia</th><th>Cama destino</th><th>Registró</th></tr></thead>
                <tbody>
                <?php foreach ($trasLista as $t): ?>
                    <tr>
                        <td><span class="contador"><?= (int) $t['ConsTras'] ?></span></td>
                        <td><?= e($t['CamaOrig']) ?> <small>(<?= e($t['CodiServ']) ?>)</small></td>
                        <td class="sin-salto"><?= e(fecha_hora($t['FechIngr'] . ' ' . $t['HoraIngr'])) ?></td>
                        <td class="sin-salto"><?= e(fecha_hora($t['FechSali'] . ' ' . $t['HoraSali'])) ?></td>
                        <td class="sin-salto"><?= (int) $t['Dias'] ?> d <?= (int) $t['Horas'] ?> h</td>
                        <td><strong><?= e($t['CamaDest']) ?></strong> <small>(<?= e($t['ServEgre']) ?>)</small></td>
                        <td><?= e($t['UsuaDigi']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php elseif (!$editable): ?>
            <p class="et-ayuda">Sin traslados.</p>
        <?php endif; ?>
    </div>
</details>
