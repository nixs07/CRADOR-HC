<?php
/**
 * Historia (pantalla de trabajo de la admision), organizada como en SIHOS:
 * encabezado de la admision arriba y pestanas numeradas debajo. Cada pestana muestra
 * su formulario dentro de la misma pantalla; al guardar se vuelve a la misma pestana.
 *   admision.php?id=C26092500001&tab=triage | signos
 * Los formularios envian accion=triage o accion=signos a esta misma pagina.
 */
require __DIR__ . '/../src/inicio.php';
require __DIR__ . '/../src/atencion.php';
require __DIR__ . '/../src/formulario.php';

$u = requiere_login();
$a = admision_o_404($_GET['id'] ?? null);
$claveMod = modulo_de_servicio($a['ServEgre']);
$mod = $claveMod ? MODULOS_DETALLE[$claveMod] : null;
$editable = admision_editable($a);
$id = urlencode($a['ConsAdmi']);
$aqui = 'admision.php?id=' . $id;
modulo_elegir($claveMod);
$tieneTriage = $mod && $mod['triage'];

$triage = triage_de_admision($a['ConsAdmi']);
$signos = signos_de_admision($a['ConsAdmi']);

// Pestana activa: ?tab= (o la de la accion enviada). Por defecto triage en Urgencias sin triage.
$pestanasValidas = $tieneTriage ? ['triage', 'signos'] : ['signos'];
$tab = in_array($_GET['tab'] ?? '', $pestanasValidas, true) ? $_GET['tab']
     : (($tieneTriage && !$triage) ? 'triage' : 'signos');

// Valores iniciales de los formularios
$t  = ['FechTria' => date('Y-m-d'), 'HoraTria' => date('H:i'), 'CodiDiag' => $a['DiagIngr'], 'CondTria' => '1'];
$st = [];   // signos del triage (toma No. 1)
$eT = [];   // errores del formulario de triage
$ultima = $signos[0] ?? null;
$sv = ['FechToma' => date('Y-m-d'), 'HoraToma' => date('H:i'),
       'Peso' => $ultima['Peso'] ?? '', 'Talla' => $ultima['Talla'] ?? ''];
$eS = [];   // errores del formulario de signos

// --- Guardar (POST) -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $accion = $_POST['accion'] ?? '';
    $ingreso = $a['FechIngr'] . ' ' . $a['HoraIngr'];

    if (!$editable) {
        flash('error', 'La admisión no se puede modificar.');
        redirigir($aqui);
    }

    if ($accion === 'triage') {
        $tab = 'triage';
        if (!$tieneTriage) {
            flash('error', 'El triage solo se registra en Urgencias.');
            redirigir($aqui);
        }
        if ($triage) {
            flash('aviso', 'La admisión ya tiene triage.');
            redirigir($aqui . '&tab=triage');
        }
        [$t, $et] = triage_validar();
        [$st, $es] = signos_validar(false);
        $eT = $et + $es;
        if (!$eT && $t['FechTria'] . ' ' . $t['HoraTria'] < $ingreso) {
            $eT['HoraTria'] = 'El triage no puede ser anterior al ingreso (' . fecha_hora($ingreso) . ').';
        }
        if (!$eT) {
            triage_guardar($a, $t, $st, $u['Login']);
            flash('ok', 'Triage registrado.');
            redirigir($aqui . '&tab=triage');
        }
    } elseif ($accion === 'signos') {
        $tab = 'signos';
        [$sv, $eS] = signos_validar(true);
        if (!$eS && $sv['FechToma'] . ' ' . $sv['HoraToma'] < $ingreso) {
            $eS['HoraToma'] = 'La toma no puede ser anterior al ingreso (' . fecha_hora($ingreso) . ').';
        }
        if (!$eS) {
            $n = signos_guardar($a, $sv, $u['Login']);
            flash('ok', "Toma de signos No. $n registrada.");
            redirigir($aqui . '&tab=signos');
        }
    }
}

// Si el triage aun no existe, su formulario (con signos) y el de signos quedan en la misma
// pagina: los campos de signos de la pestana Signos llevan prefijo en el id (no en el name).
$prefijoSignos = ($tieneTriage && !$triage && $editable) ? 'toma-' : '';

vista_inicio('Admisión ' . $a['ConsAdmi']);
?>
<?php if ($claveMod): ?>
    <p class="migas"><a href="admisiones.php?modulo=<?= e($claveMod) ?>"><?= icono('arrow-left') ?>Volver a historias abiertas · <?= e($mod['nombre']) ?></a></p>
<?php endif; ?>
<?php encabezado_admision($a, true); ?>

<?php if (!$editable): ?>
    <div class="alerta alerta-aviso"><?= icono('lock') ?><div>Esta admisión está cerrada, anulada o ya se cargó a SIHOS: solo se puede consultar.</div></div>
<?php endif; ?>

<?php pestanas_historia($a, $tab, count($signos)); ?>

<?php if ($tieneTriage): ?>
<section id="triage" class="seccion panel" data-panel="triage"<?= $tab === 'triage' ? '' : ' hidden' ?>>
    <div class="panel-cabeza">
        <h2><?= icono('siren') ?>Triage</h2>
        <?php if ($triage): ?><span class="etiqueta etiqueta-abierta"><?= icono('circle-check') ?>Registrado</span><?php endif; ?>
    </div>
    <?php if ($triage): ?>
        <dl class="datos">
            <dt>Clasificación</dt><dd><span class="etiqueta triage-<?= (int) $triage['ClasTria'] ?>"><?= e(lista_nombre('ClasTria', $triage['ClasTria'])) ?></span>
                · Conducta: <?= e(lista_nombre('CondTria', $triage['CondTria'])) ?></dd>
            <dt>Fecha y hora</dt><dd><?= e(fecha_hora($triage['FechTria'] . ' ' . $triage['HoraTria'])) ?> · <?= e($triage['UsuaDigi']) ?></dd>
            <dt>Motivo de consulta</dt><dd class="texto-largo"><?= e($triage['MotiCons']) ?></dd>
            <dt>Hallazgos clínicos</dt><dd class="texto-largo"><?= e($triage['HallClin']) ?></dd>
            <dt>Impresión diagnóstica</dt><dd><?= e($triage['CodiDiag'] ? $triage['CodiDiag'] . ' · ' . (diagnostico_nombre($triage['CodiDiag']) ?? '') : '—') ?></dd>
            <?php if (trim((string) $triage['Conducta']) !== ''): ?>
                <dt>Observaciones de conducta</dt><dd class="texto-largo"><?= e($triage['Conducta']) ?></dd>
            <?php endif; ?>
        </dl>
    <?php elseif (!$editable): ?>
        <div class="alerta vacio"><?= icono('info') ?><div>El paciente aún no tiene triage.</div></div>
    <?php else: ?>
        <div class="panel-cuerpo">
        <?= errores_resumen($eT) ?>
        <form method="post" action="<?= e($aqui) ?>&amp;tab=triage" class="formulario formulario-panel" data-signos data-una-vez>
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="triage">
            <div class="alerta vacio"><?= icono('info') ?><div>El paciente aún no tiene triage.</div></div>
            <p class="ayuda">Llene el triage aquí mismo. Se guarda también la toma de signos No. 1, como en SIHOS.
                <span class="legend-nota">Profesional: <?= e($u['Nombre']) ?></span></p>
            <div class="rejilla rejilla-fecha">
                <div><label for="FechTria">Fecha</label>
                    <input type="date" id="FechTria" name="FechTria" value="<?= v($t, 'FechTria') ?>" max="<?= date('Y-m-d') ?>" class="<?= ce($eT, 'FechTria') ?>" required><?= me($eT, 'FechTria') ?></div>
                <div><label for="HoraTria">Hora</label>
                    <input type="time" id="HoraTria" name="HoraTria" value="<?= e(substr($t['HoraTria'] ?? '', 0, 5)) ?>" class="<?= ce($eT, 'HoraTria') ?>" required><?= me($eT, 'HoraTria') ?></div>
            </div>

            <label for="MotiCons">Motivo de consulta (palabras del paciente) <span class="obligatorio" aria-hidden="true">*</span></label>
            <textarea id="MotiCons" name="MotiCons" rows="2" maxlength="5000" class="<?= ce($eT, 'MotiCons') ?>" required><?= v($t, 'MotiCons') ?></textarea><?= me($eT, 'MotiCons') ?>

            <div class="subgrupo">
                <h3><?= icono('heart-pulse') ?>Signos vitales</h3>
                <?php campos_signos($st, $eT); ?>
            </div>

            <label for="HallClin">Hallazgos clínicos <span class="obligatorio" aria-hidden="true">*</span></label>
            <textarea id="HallClin" name="HallClin" rows="4" maxlength="5000" class="<?= ce($eT, 'HallClin') ?>" required><?= v($t, 'HallClin') ?></textarea><?= me($eT, 'HallClin') ?>

            <div class="rejilla">
                <div><label for="CodiDiag">Impresión diagnóstica (CIE-10)</label>
                    <input type="text" id="CodiDiag" name="CodiDiag" value="<?= v($t, 'CodiDiag') ?>" maxlength="8" data-diagnostico autocomplete="off" class="<?= ce($eT, 'CodiDiag') ?>" placeholder="Código o nombre">
                    <div class="nota-campo" id="CodiDiag-nombre"><?= e(diagnostico_nombre($t['CodiDiag'] ?? '') ?? '') ?></div><?= me($eT, 'CodiDiag') ?></div>
                <div><label for="ClasTria">Clasificación</label>
                    <select id="ClasTria" name="ClasTria" class="<?= ce($eT, 'ClasTria') ?>" required><?= opciones_arreglo(lista('ClasTria'), $t['ClasTria'] ?? '') ?></select><?= me($eT, 'ClasTria') ?></div>
                <div><label for="CondTria">Conducta</label>
                    <select id="CondTria" name="CondTria" class="<?= ce($eT, 'CondTria') ?>" required><?= opciones('CondTria', $t['CondTria'] ?? '') ?></select><?= me($eT, 'CondTria') ?></div>
            </div>
            <label for="Conducta">Observaciones de la conducta</label>
            <textarea id="Conducta" name="Conducta" rows="2" maxlength="5000"><?= v($t, 'Conducta') ?></textarea>

            <div class="acciones acciones-panel">
                <button type="submit" class="boton boton-primario"><?= icono('save') ?>Guardar triage</button>
                <button type="reset" class="boton boton-claro"><?= icono('refresh-cw') ?>Limpiar</button>
            </div>
        </form>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<section id="signos" class="seccion panel" data-panel="signos"<?= $tab === 'signos' ? '' : ' hidden' ?>>
    <div class="panel-cabeza">
        <div>
            <h2><?= icono('heart-pulse') ?>Signos vitales</h2>
            <p><?= count($signos) ?> <?= count($signos) === 1 ? 'toma registrada' : 'tomas registradas' ?></p>
        </div>
    </div>
    <?php if ($editable): ?>
        <div class="panel-cuerpo">
        <?= errores_resumen($eS) ?>
        <form method="post" action="<?= e($aqui) ?>&amp;tab=signos" class="formulario formulario-panel" data-signos data-una-vez>
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="signos">
            <h3 class="titulo-form"><?= icono('plus') ?>Nueva toma <span class="legend-nota">Peso y talla se proponen con los de la última toma</span></h3>
            <div class="rejilla rejilla-fecha">
                <div><label for="FechToma">Fecha</label>
                    <input type="date" id="FechToma" name="FechToma" value="<?= v($sv, 'FechToma') ?>" max="<?= date('Y-m-d') ?>" class="<?= ce($eS, 'FechToma') ?>" required><?= me($eS, 'FechToma') ?></div>
                <div><label for="HoraToma">Hora</label>
                    <input type="time" id="HoraToma" name="HoraToma" value="<?= e(substr((string) ($sv['HoraToma'] ?? ''), 0, 5)) ?>" class="<?= ce($eS, 'HoraToma') ?>" required><?= me($eS, 'HoraToma') ?></div>
            </div>
            <div class="subgrupo">
                <?php campos_signos($sv, $eS, $prefijoSignos); ?>
            </div>
            <div class="acciones acciones-panel">
                <button type="submit" class="boton boton-primario"><?= icono('save') ?>Guardar signos</button>
                <button type="reset" class="boton boton-claro"><?= icono('refresh-cw') ?>Limpiar</button>
            </div>
        </form>
        </div>
    <?php endif; ?>

    <h3 class="titulo-tabla"><?= icono('history') ?>Tomas anteriores</h3>
    <?php if (!$signos): ?>
        <div class="alerta vacio"><?= icono('info') ?><div>No hay tomas de signos vitales.</div></div>
    <?php else: ?>
    <div class="tabla-contenedor">
    <table class="tabla tabla-signos">
        <thead><tr><th>Toma</th><th>Fecha y hora</th><th class="num">Peso</th><th class="num">Talla</th><th class="num">IMC</th><th class="num">FC</th><th class="num">FR</th><th class="num">T °C</th>
            <th>PA</th><th class="num">TM</th><th class="num">SatO₂</th><th class="num">Gluco</th><th class="num">Dolor</th><th>Registró</th></tr></thead>
        <tbody>
        <?php foreach ($signos as $s): ?>
            <tr>
                <td><span class="contador"><?= (int) $s['ConsSign'] ?></span></td>
                <td class="sin-salto"><?= e(fecha_hora($s['FechToma'] . ' ' . $s['HoraToma'])) ?></td>
                <td class="num"><?= (float) $s['Peso'] > 0 ? e((float) $s['Peso']) : '—' ?></td>
                <td class="num"><?= (float) $s['Talla'] > 0 ? e((float) $s['Talla']) : '—' ?></td>
                <td class="num"><?= (float) $s['MasaCorp'] > 0 ? e((float) $s['MasaCorp']) : '—' ?></td>
                <td class="num"><?= (int) $s['Pulso'] ?></td>
                <td class="num"><?= (int) $s['Respirac'] ?></td>
                <td class="num"><?= e((float) $s['Temperat']) ?></td>
                <td class="sin-salto"><strong><?= (int) $s['PANume'] ?>/<?= (int) $s['PADeno'] ?></strong></td>
                <td class="num"><?= (int) $s['TM'] ?></td>
                <td class="num"><?= (float) $s['Saturaci'] > 0 ? e((float) $s['Saturaci']) . '%' : '—' ?></td>
                <td class="num"><?= (int) $s['GlucMetr'] > 0 ? (int) $s['GlucMetr'] : '—' ?></td>
                <td class="num"><?= e((float) $s['Dolor']) ?></td>
                <td><?= e($s['UsuaDigi']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>
<script src="js/formularios.js"></script>
<?php
vista_fin();
