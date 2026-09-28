<?php
/**
 * Notas Enfermería (Urgencias 9, Observación 7) y Notas Médicas (Urgencias 10, Observación 8, Consulta Externa 14):
 * HojaEnfe. Como SIHOS (iguales): Nueva · No. · Fecha · Hora · Revisada (Reviza, UsuaRevi, FechRevi, HoraRevi) ·
 * Profesional · Actividad (HojaEnfe.Procedim) · Nota · Guardar · Consultar · Imprimir · Cancelar. Sin selector de
 * tipo: TipoNota fijo 1 (enfermería) o 2 (médica). Los campos de Notas Médicas llevan el
 * sufijo "Med" para que los dos formularios puedan estar en la misma página.
 * Variables: $vista (notas_enfermeria | notas_medicas), $a, $mod, $editable, $aqui, $tab, $F, $E, $notas, $u.
 */
$medica = $vista === 'notas_medicas';
$px = $medica ? 'Med' : '';
$pestana = $medica ? 'medica' : 'enfermeria';
$enviada = ($F['nota']['pestana'] ?? '') === $pestana;
$d = $enviada ? $F['nota'] : ['FechNota' . $px => date('Y-m-d'), 'HoraNota' . $px => date('H:i')];
$er = $enviada ? ($E['nota'] ?? []) : [];
// Notas de esta pestaña: las médicas por su tipo; en enfermería, las demás
$tipoMedica = tipo_nota('medica');
$propias = array_values(array_filter($notas, fn ($n) => $medica ? (string) $n['TipoNota'] === (string) $tipoMedica
                                                               : (string) $n['TipoNota'] !== (string) $tipoMedica));
$anteriores = [];
foreach ($propias as $n) {
    $anteriores['reg-nota-' . (int) $n['ConsHoEn']] = (int) $n['ConsHoEn'] . ' · ' . fecha_hora($n['FechNota'] . ' ' . $n['HoraNota']);
}
?>
<?= panel_abrir($vista, pestana_titulo($mod, $vista), $medica ? 'stethoscope' : 'clipboard-list', $tab,
    count($propias) . ' ' . (count($propias) === 1 ? 'nota registrada' : 'notas registradas')) ?>
<?php if ($editable): ?>
    <div class="panel-cuerpo">
    <?= errores_resumen($er) ?>
    <form method="post" action="<?= e($aqui) ?>&amp;tab=<?= e($vista) ?>" class="formulario formulario-panel" data-una-vez>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="nota">
        <input type="hidden" name="NotaPestana" value="<?= e($pestana) ?>">
        <?= barra_registro('Nueva', $anteriores, 'FechNota' . $px, 'HoraNota' . $px, $d, $er,
            '<div class="br-campo casillas">' . casilla('Reviza' . $px, 'Revisada', $d) . '</div>', ($u['Nombre'] ?? $u['Login'])) ?>
        <div class="rejilla"><?= campo_buscador('NotaActi' . $px, 'Actividad', $d + ['NotaActi' . $px => $d['Procedim'] ?? ''], $er, 'procedimientos') ?></div>
        <?= campo_texto('NotaEnfe' . $px, 'Nota', $d, $er, 6, true, 10000) ?>
        <?= botonera(['Guardar', 'Consultar', 'Imprimir', 'Cancelar']) ?>
    </form>
    </div>
<?php endif; ?>

<h3 class="titulo-tabla"><?= icono('history') ?>Notas registradas</h3>
<?php if (!$propias): ?>
    <?= panel_vacio($medica ? 'No hay notas médicas.' : 'No hay notas de enfermería.') ?>
<?php else: ?>
    <div class="registros">
    <?php foreach ($propias as $n): ?>
        <div class="registro registro-abierto" id="reg-nota-<?= (int) $n['ConsHoEn'] ?>">
            <div class="registro-cabeza"><span class="contador"><?= (int) $n['ConsHoEn'] ?></span>
                <strong><?= e(fecha_hora($n['FechNota'] . ' ' . $n['HoraNota'])) ?></strong>
                <span class="etiqueta"><?= e($n['NombTipo'] ?? $n['TipoNota']) ?></span>
                <?php if (trim((string) $n['Procedim']) !== ''): ?><span class="etiqueta"><?= e($n['Procedim']) ?></span><?php endif; ?>
                <?php if ((int) $n['Reviza'] === 1): ?><span class="etiqueta etiqueta-abierta"><?= icono('circle-check') ?>Revisada · <?= e($n['UsuaRevi']) ?></span><?php endif; ?>
                <small><?= e($n['UsuaDigi']) ?></small></div>
            <p class="registro-nota texto-largo"><?= e($n['NotaEnfe']) ?></p>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
</section>
