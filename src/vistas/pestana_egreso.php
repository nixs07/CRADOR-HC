<?php
/**
 * Egreso (Urgencias 26, Observación 22): SaliInte, como SIHOS (docs/RECORRIDO_SIHOS.md §3): Fecha · Hora ·
 * Estadía · Profesional · Estado · Causa · Destino · Incapacidad (días) · Diagnósticos Egreso, Rela 1-3 y Complic.
 * con Tipo · Muerte (Diagnóstico, Fecha, Hora) · Plan de Manejo Ambulatorio y Observaciones · Guardar ·
 * Modificar · Cerrar Historia · Limpiar · Imprimir, y las listas de pendientes.
 * Guardar/Modificar NO cierran la historia; se cierra con "Cerrar Historia" (confirmación en la página).
 * Variables: $a, $mod, $u, $editable, $aqui, $tab, $F, $E, $egreso, $pendientes, $consultas.
 */
if (isset($F['egreso'])) {
    $d = $F['egreso'];
} elseif ($egreso) {
    // Modificar: el formulario trae el egreso guardado
    $d = ['FechSali' => $egreso['FechSali'], 'HoraSali' => $egreso['HoraSali'], 'EstaSali' => $egreso['EstaSali'],
          'CausSali' => $egreso['CausSali'], 'DestSali' => $egreso['DestSali'], 'DiasInca' => $egreso['DiasInca'],
          'DiagEgre' => $egreso['DiagEgre'], 'EgreRel1' => $egreso['DiagRel1'], 'EgreRel2' => $egreso['DiagRel2'],
          'EgreRel3' => $egreso['DiagRel3'], 'EgreComp' => $egreso['DiagComp'], 'EgreTipoDiag' => $egreso['TipoDiag'],
          'EgreTipoRel1' => $egreso['TipoDia1'], 'EgreTipoRel2' => $egreso['TipoDia2'], 'EgreTipoRel3' => $egreso['TipoDia3'],
          'EgreTipoComp' => $egreso['TipoDia4'], 'DiagMuer' => $egreso['DiagMuer'], 'FechMuer' => $egreso['FechMuer'],
          'HoraMuer' => $egreso['HoraMuer'], 'ObseSali' => $egreso['ObseSali']];
} else {
    $d = ['FechSali' => date('Y-m-d'), 'HoraSali' => date('H:i'), 'CausSali' => '1', 'DestSali' => '01', 'EstaSali' => '1',
          'EgreTipoDiag' => '2', 'DiagEgre' => $a['DiagIngr']];
}
$er = $E['egreso'] ?? [];
// Codigo del estado "muerto" en el catalogo EstaSali (para mostrar los datos de muerte)
$codMuerto = '';
foreach (lista('EstaSali') as $c => $n) {
    if (estado_salida_muerto($c)) { $codMuerto = (string) $c; break; }
}
$fin = $egreso ? strtotime($egreso['FechSali'] . ' ' . $egreso['HoraSali']) : time();
$seg = max(0, $fin - strtotime($a['FechIngr'] . ' ' . $a['HoraIngr']));
$estadia = intdiv($seg, 86400) . ' día(s), ' . intdiv($seg % 86400, 3600) . ' hora(s)';
$porCerrar = array_values(array_filter($consultas, fn ($c) => ($c['FechCier'] ?? '0000-00-00') === '0000-00-00'));
?>
<?= panel_abrir('egreso', pestana_titulo($mod, 'egreso'), 'log-out', $tab,
    (int) $a['Cerrado'] === 1 ? 'Historia cerrada' : ($egreso ? 'Egreso registrado: falta Cerrar Historia' : 'Salida del paciente')) ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo">
    <?= errores_resumen($er) ?>
    <form method="post" action="<?= e($aqui) ?>&amp;tab=egreso" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="egreso">
        <div class="barra-registro">
            <div class="br-fecha"><?= campos_fecha_hora('FechSali', 'HoraSali', $d, $er) ?></div>
            <div class="br-campo"><label>Estadía</label><span><?= e($estadia) ?></span></div>
            <div class="br-campo br-profesional"><label>Profesional</label><span><?= e($egreso['CodiProf'] ?? ($u['Nombre'] ?? $u['Login'])) ?></span></div>
        </div>
        <div class="rejilla">
            <?= campo_lista('EstaSali', 'Estado', 'EstaSali', $d, $er) ?>
            <?= campo_lista('CausSali', 'Causa', 'CausSali', $d, $er) ?>
            <?= campo_lista('DestSali', 'Destino', 'DestSali', $d, $er) ?>
            <?php $diasInca = dias_incapacidad($a['ConsAdmi']); ?>
            <div class="campo-lectura" title="Se toma de la pestaña Incapacidad (suma de los días registrados)"><span class="cl-etiqueta">Incapacidad (días)</span>
                <span class="cl-valor" id="DiasInca"><?= $diasInca > 0 ? $diasInca . ' día(s)' : 'Sin incapacidad' ?></span></div>
        </div>
        <h3 class="subtitulo-panel"><?= icono('file-text') ?>Diagnósticos</h3>
        <?= tabla_diagnosticos([['Egreso', 'DiagEgre', 'EgreTipoDiag'], ['Rela 1', 'EgreRel1', 'EgreTipoRel1'], ['Rela 2', 'EgreRel2', 'EgreTipoRel2'],
                                ['Rela 3', 'EgreRel3', 'EgreTipoRel3'], ['Complic.', 'EgreComp', 'EgreTipoComp']], $d, $er, true, 'egre-') ?>
        <?php if ($codMuerto !== ''): ?>
        <div class="subgrupo" data-mostrar-si="EstaSali=<?= e($codMuerto) ?>">
            <h3><?= icono('triangle-alert') ?>Muerte</h3>
            <div class="rejilla">
                <?= campo_buscador('DiagMuer', 'Diagnóstico', $d, $er, 'diagnosticos') ?>
                <?= campos_fecha_hora('FechMuer', 'HoraMuer', $d, $er, false) ?>
            </div>
        </div>
        <?php endif; ?>
        <?= campo_texto('ObseSali', 'Plan de Manejo Ambulatorio y Observaciones', $d, $er, 3) ?>
        <div class="acciones acciones-panel">
            <?php if (!$egreso): ?>
                <button type="submit" class="boton boton-primario"><?= icono('save') ?>Guardar</button>
                <button type="button" class="boton boton-claro" disabled title="Aún no hay egreso para modificar"><?= icono('pencil') ?>Modificar</button>
            <?php else: ?>
                <button type="button" class="boton boton-claro" disabled title="El egreso ya está guardado: use Modificar"><?= icono('save') ?>Guardar</button>
                <button type="submit" class="boton boton-primario"><?= icono('pencil') ?>Modificar</button>
            <?php endif; ?>
            <a href="<?= e($aqui) ?>&amp;tab=egreso&amp;cerrar=1" class="boton boton-peligro" data-abrir-ventana="cerrar-historia"><?= icono('log-out') ?>Cerrar Historia</a>
            <button type="reset" class="boton boton-claro"><?= icono('refresh-cw') ?>Limpiar</button>
            <button type="button" class="boton boton-claro" disabled title="No disponible"><?= icono('printer') ?>Imprimir</button>
        </div>
    </form>
    </div>
<?php elseif ($egreso): ?>
    <dl class="datos">
        <dt>Fecha y hora de salida</dt><dd><?= e(fecha_hora($egreso['FechSali'] . ' ' . $egreso['HoraSali'])) ?> · <?= e($egreso['UsuaDigi']) ?></dd>
        <dt>Estadía</dt><dd><?= (int) $egreso['DiasEsta'] ?> días, <?= (int) $egreso['HoraEsta'] ?> horas</dd>
        <dt>Estado · causa · destino</dt><dd><?= e(lista_nombre('EstaSali', $egreso['EstaSali'])) ?> · <?= e(lista_nombre('CausSali', $egreso['CausSali'])) ?> · <?= e(lista_nombre('DestSali', $egreso['DestSali'])) ?></dd>
        <dt>Diagnósticos</dt><dd><?= e(diag_texto($egreso['DiagEgre'])) ?><?php foreach (['DiagRel1', 'DiagRel2', 'DiagRel3', 'DiagComp'] as $c) { if (trim((string) $egreso[$c]) !== '') echo '<br>' . ($c === 'DiagComp' ? 'Complicación: ' : '') . e(diag_texto($egreso[$c])); } ?></dd>
        <?php if ($egreso['DiagMuer']): ?><dt>Muerte</dt><dd><?= e(diag_texto($egreso['DiagMuer'])) ?> · <?= e(fecha_hora($egreso['FechMuer'] . ' ' . $egreso['HoraMuer'])) ?></dd><?php endif; ?>
        <dt>Plan de manejo ambulatorio y observaciones</dt><dd class="texto-largo"><?= texto_registro($egreso['ObseSali']) ?></dd>
    </dl>
<?php elseif ((int) $a['Cerrado'] === 1): ?>
    <?= panel_vacio('Historia cerrada el ' . fecha_hora($a['FechCier'] . ' ' . $a['HoraCier']) . ' por ' . $a['UsuaCier'] . '.') ?>
<?php else: ?>
    <?= panel_vacio('La admisión no se puede modificar.') ?>
<?php endif; ?>

<!-- Pendientes, como SIHOS -->
<h3 class="titulo-tabla"><?= icono('clipboard-list') ?>Insumos y medicamentos pendientes por descargar</h3>
<?= panel_vacio('No disponible (inventario).') ?>
<h3 class="titulo-tabla"><?= icono('clipboard-list') ?>Ayudas diagnósticas pendientes por interpretar</h3>
<?php if (!$pendientes): ?>
    <?= panel_vacio('No hay ayudas diagnósticas pendientes.') ?>
<?php else: ?>
    <div class="tabla-contenedor"><table class="tabla"><thead><tr><th>Orden</th><th>Ítem</th><th>Código</th><th>Nombre</th><th class="num">Cant</th><th class="num">Realizados</th></tr></thead><tbody>
    <?php foreach ($pendientes as $o): ?>
        <tr><td><?= (int) $o['ConsOrde'] ?></td><td><?= (int) $o['Item'] ?></td><td><?= e($o['CodiProc']) ?></td><td><?= e($o['NombProc'] ?? '') ?></td>
            <td class="num"><?= (int) $o['CantSumi'] ?></td><td class="num"><?= (int) $o['CantReal'] ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
<?php endif; ?>
<h3 class="titulo-tabla"><?= icono('clipboard-list') ?>Consultas pendientes por cerrar</h3>
<?php if (!$porCerrar): ?>
    <?= panel_vacio('No hay consultas pendientes por cerrar.') ?>
<?php else: ?>
    <div class="tabla-contenedor"><table class="tabla"><thead><tr><th>No.</th><th>Fecha</th><th>Tipo</th><th>Profesional</th></tr></thead><tbody>
    <?php foreach ($porCerrar as $c): ?>
        <tr><td><?= (int) $c['ConsCons'] ?></td><td><?= e(fecha_hora($c['FechCons'] . ' ' . $c['HoraCons'])) ?></td><td><?= e($c['TipoCons']) ?></td><td><?= e($c['UsuaCons']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
<?php endif; ?>
</section>
