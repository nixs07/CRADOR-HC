<?php
/**
 * Pacientes con admision abierta en un modulo (censo).
 *   admisiones.php?modulo=urg | obs | ce
 * Urgencias se ordena por clasificacion de triage y luego por hora de ingreso.
 */
require __DIR__ . '/../src/inicio.php';
require __DIR__ . '/../src/atencion.php';

requiere_login();
$mod = modulo_o_404($_GET['modulo'] ?? null);
$filas = admisiones_abiertas($mod);

vista_inicio($mod['nombre']);
?>
<div class="titulo-con-acciones">
    <h1><?= e($mod['nombre']) ?> · <?= count($filas) ?> abiertas</h1>
    <a href="pacientes.php" class="boton boton-primario">+ Nueva admisión</a>
</div>

<?php if (!$filas): ?>
    <div class="alerta alerta-aviso">No hay admisiones abiertas en <?= e($mod['nombre']) ?>.</div>
<?php else: ?>
<div class="tabla-contenedor">
<table class="tabla">
    <thead><tr>
        <?php if ($mod['triage']): ?><th>Triage</th><?php endif; ?>
        <?php if ($mod['cama']): ?><th>Cama</th><?php endif; ?>
        <th>Ingreso</th><th>Paciente</th><th>Edad</th><th>EPS</th><th>Dx ingreso</th><th>Signos</th><th>Admisión</th>
    </tr></thead>
    <tbody>
    <?php foreach ($filas as $f): $url = 'admision.php?id=' . urlencode($f['ConsAdmi']); ?>
        <tr>
            <?php if ($mod['triage']): ?>
                <td><?php if ($f['ClasTria']): ?>
                    <span class="etiqueta triage-<?= (int) $f['ClasTria'] ?>"><?= e(['', 'I', 'II', 'III', 'IV', 'V'][(int) $f['ClasTria']] ?? $f['ClasTria']) ?></span>
                <?php else: ?><a href="triage.php?id=<?= e(urlencode($f['ConsAdmi'])) ?>" class="texto-error">Sin triage</a><?php endif; ?></td>
            <?php endif; ?>
            <?php if ($mod['cama']): ?><td><?= e($f['CamaActu'] ?: '—') ?></td><?php endif; ?>
            <td class="sin-salto"><?= e(fecha_hora($f['FechIngr'] . ' ' . $f['HoraIngr'])) ?></td>
            <td><a href="<?= e($url) ?>"><?= e(paciente_nombre($f)) ?></a><br>
                <small><?= e($f['TipoDocu'] . ' ' . $f['NumeUsua']) ?></small></td>
            <td class="sin-salto"><?= e(edad_texto($f['ValoEdad'], $f['UnidEdad'])) ?> <?= e($f['SexoUsua']) ?></td>
            <td><?= e($f['NombAdmi']) ?></td>
            <td><?= e($f['DiagIngr'] ?: '—') ?></td>
            <td class="num"><?= (int) $f['signos'] ?></td>
            <td><a href="<?= e($url) ?>"><?= e($f['ConsAdmi']) ?></a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php
vista_fin();
