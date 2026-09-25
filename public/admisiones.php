<?php
/**
 * Historias abiertas del modulo (como la ventana "Historias Abiertas" de SIHOS).
 *   admisiones.php?modulo=urg | obs | ce   [&serv=007] [&q=documento o nombre]
 * Urgencias se ordena por clasificacion de triage y luego por hora de ingreso.
 * Abrir esta lista deja el modulo elegido en la sesion.
 */
require __DIR__ . '/../src/inicio.php';
require __DIR__ . '/../src/atencion.php';

requiere_login();
$clave = $_GET['modulo'] ?? modulo_actual();
if ($clave === null) {
    redirigir('modulo.php');
}
$mod = modulo_o_404($clave);
modulo_elegir($mod['clave']);

$todas = admisiones_abiertas($mod);
$filas = $todas;
$total = count($todas);

// Filtros: servicio y busqueda por documento o nombre
$serv = is_string($_GET['serv'] ?? null) && in_array($_GET['serv'], $mod['servicios'], true) ? $_GET['serv'] : '';
$q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 60) : '';
if ($serv !== '') {
    $filas = array_values(array_filter($filas, fn ($f) => $f['ServEgre'] === $serv));
}
if ($q !== '') {
    $buscar = mb_strtoupper($q);
    $filas = array_values(array_filter($filas, fn ($f) =>
        str_contains($f['NumeUsua'], $q)
        || str_contains($f['ConsAdmi'], $buscar)
        || str_contains(mb_strtoupper(paciente_nombre($f)), $buscar)));
}

// Conteo por clasificacion de triage (solo Urgencias), con todas las historias abiertas
$porTriage = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 0 => 0];
foreach ($todas as $f) {
    $c = (int) $f['ClasTria'];
    $porTriage[isset($porTriage[$c]) ? $c : 0]++;
}
$servicios = array_intersect_key(lista('Serv'), array_flip($mod['servicios']));

vista_inicio($mod['nombre']);
?>
<div class="cabecera-pagina">
    <div>
        <div class="antetitulo"><?= icono(MODULOS_ICONO[$mod['clave']] ?? 'clipboard-list') ?><?= e($mod['nombre']) ?></div>
        <h1>Historias abiertas · <?= $total ?></h1>
        <p><?= $mod['triage'] ? 'Ordenadas por clasificación de triage y hora de ingreso.' : 'Ordenadas por hora de ingreso.' ?> Toque una fila para abrir la historia.</p>
    </div>
    <div class="acciones">
        <a href="pacientes.php" class="boton boton-primario"><?= icono('plus') ?>Nueva admisión</a>
    </div>
</div>

<?php if ($mod['triage'] && $total): ?>
<div class="triage-resumen">
    <?php foreach ([1, 2, 3, 4, 5] as $c): ?>
        <div class="triage-caja triage-<?= $c ?>"><span>Triage <?= triage_romano($c) ?></span><strong><?= $porTriage[$c] ?></strong></div>
    <?php endforeach; ?>
    <div class="triage-caja triage-0"><span>Sin triage</span><strong><?= $porTriage[0] ?></strong></div>
</div>
<?php endif; ?>

<form method="get" class="buscador filtros" role="search">
    <input type="hidden" name="modulo" value="<?= e($mod['clave']) ?>">
    <div class="buscador-campo">
        <?= icono('search') ?>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Buscar por documento, nombre o admisión" aria-label="Buscar por documento, nombre o admisión">
    </div>
    <select name="serv" aria-label="Servicio" class="filtro-servicio">
        <option value="">Todos los servicios</option>
        <?php foreach ($servicios as $c => $n): ?>
            <option value="<?= e($c) ?>"<?= $serv === (string) $c ? ' selected' : '' ?>><?= e($c . ' · ' . $n) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="boton boton-claro"><?= icono('search') ?>Filtrar</button>
    <?php if ($q !== '' || $serv !== ''): ?>
        <a href="admisiones.php?modulo=<?= e($mod['clave']) ?>" class="boton boton-claro">Quitar filtros</a>
    <?php endif; ?>
</form>

<?php if (!$total): ?>
    <div class="alerta alerta-aviso"><?= icono('info') ?><div>No hay admisiones abiertas en <?= e($mod['nombre']) ?>.</div></div>
<?php elseif (!$filas): ?>
    <div class="alerta alerta-aviso"><?= icono('info') ?><div>Ninguna historia abierta coincide con el filtro.</div></div>
<?php else: ?>
<div class="tabla-contenedor tabla-tarjetas">
<table class="tabla tabla-historias">
    <thead><tr>
        <th>Paciente</th>
        <?php if ($mod['triage']): ?><th>Triage</th><?php endif; ?>
        <th>Servicio</th>
        <?php if ($mod['cama']): ?><th>Cama</th><?php endif; ?>
        <th>Admisión</th><th>Fecha</th><th>Duración</th><th>Edad</th><th>Estado</th><th>Profesional</th>
    </tr></thead>
    <tbody>
    <?php foreach ($filas as $f): $url = 'admision.php?id=' . urlencode($f['ConsAdmi']); ?>
        <tr data-href="<?= e($url) ?>">
            <td class="celda-principal celda-paciente"><a href="<?= e($url) ?>"><?= e(paciente_nombre($f)) ?></a>
                <small class="bloque"><?= e($f['TipoDocu'] . ' ' . $f['NumeUsua']) ?> · <?= e($f['NombAdmi']) ?></small></td>
            <?php if ($mod['triage']): ?>
                <td data-etiqueta="Triage"><?php if ($f['ClasTria']): ?>
                    <span class="etiqueta triage-<?= (int) $f['ClasTria'] ?>"><?= e(triage_romano($f['ClasTria'])) ?></span>
                <?php else: ?><a href="<?= e($url) ?>&amp;tab=triage" class="sin-triage"><?= icono('circle-alert') ?>Sin triage</a><?php endif; ?></td>
            <?php endif; ?>
            <td data-etiqueta="Servicio"><?= e($f['NombServ'] ?? $f['ServEgre']) ?></td>
            <?php if ($mod['cama']): ?><td data-etiqueta="Cama"><?php if ($f['CamaActu']): ?><span class="chip"><?= icono('bed-double') ?><?= e($f['CamaActu']) ?></span><?php else: ?>—<?php endif; ?></td><?php endif; ?>
            <td data-etiqueta="Admisión" class="celda-codigo"><?= e($f['ConsAdmi']) ?></td>
            <td data-etiqueta="Fecha" class="sin-salto"><?= e(fecha_hora($f['FechIngr'] . ' ' . $f['HoraIngr'])) ?></td>
            <td data-etiqueta="Duración" class="sin-salto"><span class="duracion"><?= icono('clock') ?><?= e(duracion_desde($f['FechIngr'], $f['HoraIngr'])) ?></span></td>
            <td data-etiqueta="Edad" class="sin-salto"><?= e(edad_texto($f['ValoEdad'], $f['UnidEdad'])) ?> · <?= e($f['SexoUsua']) ?></td>
            <td data-etiqueta="Estado"><span class="etiqueta etiqueta-curso">En curso</span></td>
            <td data-etiqueta="Profesional"><?= e($f['UsuaDigi']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php
vista_fin();
