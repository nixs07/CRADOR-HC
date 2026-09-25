<?php
/**
 * Ficha de la admision: encabezado, datos del ingreso y pestanas clinicas.
 * Fase 2 (primer bloque): triage y signos vitales. Las demas pestanas llegan en los
 * siguientes bloques (anamnesis y diagnosticos, ordenes, formula, notas, evolucion, egreso).
 */
require __DIR__ . '/../src/inicio.php';
require __DIR__ . '/../src/atencion.php';
require __DIR__ . '/../src/formulario.php';

requiere_login();
$a = admision_o_404($_GET['id'] ?? null);
$claveMod = modulo_de_servicio($a['ServEgre']);
$mod = $claveMod ? MODULOS_DETALLE[$claveMod] : null;
$triage = triage_de_admision($a['ConsAdmi']);
$signos = signos_de_admision($a['ConsAdmi']);
$editable = admision_editable($a);
$id = urlencode($a['ConsAdmi']);

vista_inicio('Admisión ' . $a['ConsAdmi']);
?>
<?php if ($claveMod): ?>
    <p class="migas"><a href="admisiones.php?modulo=<?= e($claveMod) ?>">← <?= e($mod['nombre']) ?></a></p>
<?php endif; ?>
<?php encabezado_admision($a); ?>

<?php if (!$editable): ?>
    <div class="alerta alerta-aviso">Esta admisión está cerrada, anulada o ya se cargó a SIHOS: solo se puede consultar.</div>
<?php endif; ?>

<nav class="pestanas">
    <?php if ($mod && $mod['triage']): ?><a href="#triage">Triage</a><?php endif; ?>
    <a href="#signos">Signos vitales (<?= count($signos) ?>)</a>
    <a href="#ingreso">Datos del ingreso</a>
    <span class="pestana-proxima" title="Siguiente bloque de la fase 2">Anamnesis y diagnósticos</span>
    <span class="pestana-proxima" title="Siguiente bloque de la fase 2">Órdenes y procedimientos</span>
    <span class="pestana-proxima" title="Siguiente bloque de la fase 2">Fórmula y medicamentos</span>
    <span class="pestana-proxima" title="Siguiente bloque de la fase 2">Notas de enfermería</span>
    <span class="pestana-proxima" title="Siguiente bloque de la fase 2">Evolución</span>
    <span class="pestana-proxima" title="Siguiente bloque de la fase 2">Egreso</span>
</nav>

<?php if ($mod && $mod['triage']): ?>
<section id="triage" class="seccion">
    <div class="titulo-con-acciones">
        <h2>Triage</h2>
        <?php if (!$triage && $editable): ?><a href="triage.php?id=<?= e($id) ?>" class="boton boton-primario">Registrar triage</a><?php endif; ?>
    </div>
    <?php if (!$triage): ?>
        <div class="alerta alerta-aviso">El paciente aún no tiene triage.</div>
    <?php else: ?>
        <dl class="datos">
            <dt>Fecha y hora</dt><dd><?= e(fecha_hora($triage['FechTria'] . ' ' . $triage['HoraTria'])) ?> · <?= e($triage['UsuaDigi']) ?></dd>
            <dt>Clasificación</dt><dd><span class="etiqueta triage-<?= (int) $triage['ClasTria'] ?>"><?= e(lista_nombre('ClasTria', $triage['ClasTria'])) ?></span>
                · Conducta: <?= e(lista_nombre('CondTria', $triage['CondTria'])) ?></dd>
            <dt>Motivo de consulta</dt><dd class="texto-largo"><?= e($triage['MotiCons']) ?></dd>
            <dt>Hallazgos clínicos</dt><dd class="texto-largo"><?= e($triage['HallClin']) ?></dd>
            <dt>Diagnóstico</dt><dd><?= e($triage['CodiDiag'] ? $triage['CodiDiag'] . ' · ' . (diagnostico_nombre($triage['CodiDiag']) ?? '') : '—') ?></dd>
            <?php if (trim((string) $triage['Conducta']) !== ''): ?>
                <dt>Observaciones de conducta</dt><dd class="texto-largo"><?= e($triage['Conducta']) ?></dd>
            <?php endif; ?>
        </dl>
    <?php endif; ?>
</section>
<?php endif; ?>

<section id="signos" class="seccion">
    <div class="titulo-con-acciones">
        <h2>Signos vitales</h2>
        <?php if ($editable): ?><a href="signos.php?id=<?= e($id) ?>" class="boton boton-primario">Registrar signos</a><?php endif; ?>
    </div>
    <?php if (!$signos): ?>
        <div class="alerta alerta-aviso">No hay tomas de signos vitales.</div>
    <?php else: ?>
    <div class="tabla-contenedor">
    <table class="tabla">
        <thead><tr><th>Toma</th><th>Fecha y hora</th><th>PA</th><th>PAM</th><th>FC</th><th>FR</th><th>T °C</th><th>SatO₂</th>
            <th>Peso</th><th>Talla</th><th>IMC</th><th>Dolor</th><th>Gluco</th><th>Registró</th></tr></thead>
        <tbody>
        <?php foreach ($signos as $s): ?>
            <tr>
                <td class="num"><?= (int) $s['ConsSign'] ?></td>
                <td class="sin-salto"><?= e(fecha_hora($s['FechToma'] . ' ' . $s['HoraToma'])) ?></td>
                <td class="sin-salto"><?= (int) $s['PANume'] ?>/<?= (int) $s['PADeno'] ?></td>
                <td class="num"><?= (int) $s['TM'] ?></td>
                <td class="num"><?= (int) $s['Pulso'] ?></td>
                <td class="num"><?= (int) $s['Respirac'] ?></td>
                <td class="num"><?= e((float) $s['Temperat']) ?></td>
                <td class="num"><?= (float) $s['Saturaci'] > 0 ? e((float) $s['Saturaci']) . '%' : '—' ?></td>
                <td class="num"><?= (float) $s['Peso'] > 0 ? e((float) $s['Peso']) : '—' ?></td>
                <td class="num"><?= (float) $s['Talla'] > 0 ? e((float) $s['Talla']) : '—' ?></td>
                <td class="num"><?= (float) $s['MasaCorp'] > 0 ? e((float) $s['MasaCorp']) : '—' ?></td>
                <td class="num"><?= e((float) $s['Dolor']) ?></td>
                <td class="num"><?= (int) $s['GlucMetr'] > 0 ? (int) $s['GlucMetr'] : '—' ?></td>
                <td><?= e($s['UsuaDigi']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>

<section id="ingreso" class="seccion">
    <h2>Datos del ingreso</h2>
    <dl class="datos">
        <dt>Servicio</dt><dd><?= e($a['NombServ'] ?? $a['ServEgre']) ?><?= $a['CamaActu'] ? ' · cama ' . e($a['CamaActu']) : '' ?></dd>
        <dt>Vía de ingreso</dt><dd><?= e(lista_nombre('ViaIngre', $a['ViaIngre'])) ?></dd>
        <dt>Causa externa</dt><dd><?= e(lista_nombre('CausExte', $a['CausExte'])) ?></dd>
        <dt>Diagnóstico de ingreso</dt><dd><?= e($a['DiagIngr'] ? $a['DiagIngr'] . ' · ' . (diagnostico_nombre($a['DiagIngr']) ?? '') : '—') ?></dd>
        <dt>Tipo de usuario / afiliación</dt><dd><?= e(lista_nombre('TipoUsua', $a['TipoUsua'])) ?> · <?= e(lista_nombre('TipoAfil', $a['TipoAfil'])) ?> · categoría <?= e($a['CodiEstr']) ?></dd>
        <dt>Autorización</dt><dd><?= e($a['NumeAuto'] ?: '—') ?></dd>
        <dt>Grupo poblacional</dt><dd><?= e(lista_nombre('GrupAten', $a['GrupoAte'])) ?> · <?= e(lista_nombre('CondUsua', $a['CondUsua'])) ?></dd>
        <dt>Acompañante</dt><dd><?= e(lista_nombre('TipoAcom', $a['TipoAcom'])) ?><?= $a['NombAcom'] ? ' · ' . e($a['NombAcom']) . ' (' . e(lista_nombre('Parentes', $a['Parentes'])) . ') ' . e($a['TeleAcom']) : '' ?></dd>
        <dt>Motivo</dt><dd class="texto-largo"><?= e($a['MotiCons']) ?></dd>
        <dt>Registró</dt><dd><?= e($a['UsuaDigi']) ?> · <?= e(fecha_hora($a['FechDigi'] . ' ' . $a['HoraDigi'])) ?></dd>
        <dt>Carga a SIHOS</dt><dd><span class="etiqueta etiqueta-<?= e($a['estado_carga'] ?? 'pendiente') ?>"><?= e($a['estado_carga'] ?? 'pendiente') ?></span></dd>
    </dl>
</section>
<?php
vista_fin();
