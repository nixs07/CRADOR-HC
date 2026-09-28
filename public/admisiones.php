<?php
/**
 * Admisiones: todas las admisiones de los 3 módulos (abiertas, cerradas y anuladas) con filtros, para médicos y
 * administrador. Clic en la fila abre la historia en su módulo (cerrada en solo lectura). ?csv=1 exporta el filtro.
 * Enlaces viejos con ?modulo=… (sin filtros) siguen llevando a la ventana Historias del módulo.
 */
require __DIR__ . '/../src/inicio.php';
require __DIR__ . '/../src/historia.php';
require __DIR__ . '/../src/formulario.php';

requiere_login();

$g = fn (string $k, int $max = 60): string => is_string($_GET[$k] ?? null) ? mb_substr(trim($_GET[$k]), 0, $max) : '';
if ($g('modulo') !== '' && isset(MODULOS_DETALLE[$g('modulo')]) && !isset($_GET['filtrar'])) {
    redirigir('atencion.php?modulo=' . urlencode($g('modulo')) . '&historias=1');
}

$f = [
    'desde'  => fecha_valida($g('desde', 10)) ? $g('desde', 10) : date('Y-m-d', strtotime('-7 days')),
    'hasta'  => fecha_valida($g('hasta', 10)) ? $g('hasta', 10) : date('Y-m-d'),
    'modulo' => isset(MODULOS_DETALLE[$g('mod', 3)]) ? $g('mod', 3) : '',
    'estado' => in_array($g('estado', 10), ['abierta', 'cerrada', 'anulada'], true) ? $g('estado', 10) : '',
    'serv'   => '',
    'q'      => $g('q'),
];
$serviciosTodos = [];
foreach (MODULOS_DETALLE as $clave => $m) {
    foreach ($m['servicios'] as $cs) {
        $serviciosTodos[$cs] = lista_nombre('Serv', $cs) ?: $cs;
    }
}
$f['serv'] = isset($serviciosTodos[$g('serv', 3)]) ? $g('serv', 3) : '';

$modulo_de = fn (array $r): string => MODULOS_DETALLE[modulo_de_servicio($r['ServEgre']) ?? '']['nombre'] ?? '';

// --- Exportar a CSV (separador ; y BOM para que Excel lea las tildes) -----------------------
if (isset($_GET['csv'])) {
    [$filas] = admisiones_listado($f, 1, 0);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="admisiones_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Admisión', 'Fecha ingreso', 'Hora ingreso', 'Módulo', 'Servicio', 'Tipo doc.', 'Documento', 'Paciente',
                   'Edad', 'EPS', 'Dx ingreso', 'Profesional', 'Estado', 'Carga a SIHOS'], ';');
    foreach ($filas as $r) {
        fputcsv($out, [$r['ConsAdmi'], $r['FechIngr'], substr((string) $r['HoraIngr'], 0, 5), $modulo_de($r),
            $r['NombServ'] ?? $r['ServEgre'], $r['TipoDocu'], $r['NumeUsua'], paciente_nombre($r),
            edad_texto($r['ValoEdad'], $r['UnidEdad']), $r['NombAdmi'] ?? $r['CodiAdmi'], diag_ingreso($r), $r['UsuaDigi'],
            admision_estado($r)[0], $r['estado_carga'] ?? ''], ';');
    }
    exit;
}

$porPagina = 50;
$pagina = max(1, (int) $g('pagina', 6));
[$filas, $total] = admisiones_listado($f, $pagina, $porPagina);
$paginas = max(1, (int) ceil($total / $porPagina));
$params = array_filter($f + ['mod' => $f['modulo'], 'filtrar' => '1'], fn ($v, $k) => $v !== '' && $k !== 'modulo', ARRAY_FILTER_USE_BOTH);
$url = fn (array $mas = []): string => 'admisiones.php?' . http_build_query(array_merge($params, $mas));

vista_inicio('Admisiones');
?>
<div class="cabecera-pagina">
    <div>
        <div class="antetitulo"><?= icono('clipboard-list') ?>Urgencias · Observación · Consulta Externa</div>
        <h1>Admisiones</h1>
        <p>Todas las admisiones registradas aquí (abiertas, cerradas y anuladas). Clic en una fila para abrir la historia.</p>
    </div>
    <div class="acciones">
        <a href="<?= e($url(['csv' => '1'])) ?>" class="boton boton-claro"><?= icono('file-text') ?>Exportar CSV</a>
    </div>
</div>

<form method="get" action="admisiones.php" class="buscador filtros-admisiones" role="search">
    <input type="hidden" name="filtrar" value="1">
    <div class="buscador-campo">
        <?= icono('search') ?>
        <input type="text" name="q" id="q-admisiones" value="<?= e($f['q']) ?>" placeholder="Admisión, documento o nombre"
               aria-label="Admisión, documento o nombre" data-buscar="pacientes" data-minimo="3" autocomplete="off">
    </div>
    <label>Desde <input type="date" name="desde" value="<?= e($f['desde']) ?>"></label>
    <label>Hasta <input type="date" name="hasta" value="<?= e($f['hasta']) ?>"></label>
    <select name="mod" aria-label="Módulo">
        <option value="">Todos los módulos</option>
        <?php foreach (MODULOS_DETALLE as $clave => $m): ?><option value="<?= e($clave) ?>"<?= $f['modulo'] === $clave ? ' selected' : '' ?>><?= e($m['nombre']) ?></option><?php endforeach; ?>
    </select>
    <select name="estado" aria-label="Estado">
        <?php foreach (['' => 'Todos los estados', 'abierta' => 'Abiertas', 'cerrada' => 'Cerradas', 'anulada' => 'Anuladas'] as $k => $n): ?>
            <option value="<?= e($k) ?>"<?= $f['estado'] === $k ? ' selected' : '' ?>><?= e($n) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="serv" aria-label="Servicio">
        <option value="">Todos los servicios</option>
        <?php foreach ($serviciosTodos as $c => $n): ?><option value="<?= e($c) ?>"<?= $f['serv'] === (string) $c ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="boton boton-primario"><?= icono('search') ?>Buscar</button>
    <a href="admisiones.php?filtrar=1" class="boton boton-claro"><?= icono('x') ?>Limpiar</a>
</form>

<?php if (!$filas): ?>
    <div class="alerta vacio"><?= icono('info') ?><div>No hay admisiones con esos filtros.</div></div>
<?php else: ?>
    <div class="titulo-con-acciones"><h2><?= numero($total) ?> <?= $total === 1 ? 'admisión' : 'admisiones' ?></h2></div>
    <div class="tabla-contenedor tabla-tarjetas">
    <table class="tabla tabla-admisiones tabla-listado-admisiones">
        <thead><tr><th>Admisión</th><th>Ingreso</th><th>Módulo / Servicio</th><th>Paciente</th><th>Edad</th><th>EPS</th>
            <th>Dx ingreso</th><th>Profesional</th><th>Estado</th><th>Carga a SIHOS</th></tr></thead>
        <tbody>
        <?php foreach ($filas as $r): [$et, $ec] = admision_estado($r); $u = 'atencion.php?id=' . urlencode($r['ConsAdmi']); $carga = (string) ($r['estado_carga'] ?? ''); ?>
            <tr data-href="<?= e($u) ?>">
                <td data-etiqueta="Admisión" class="celda-codigo"><a href="<?= e($u) ?>"><?= e($r['ConsAdmi']) ?></a></td>
                <td data-etiqueta="Ingreso" class="sin-salto"><?= e(fecha_hora($r['FechIngr'] . ' ' . $r['HoraIngr'])) ?></td>
                <td data-etiqueta="Módulo / Servicio"><?= e($modulo_de($r)) ?><small class="bloque"><?= e($r['NombServ'] ?? $r['ServEgre']) ?></small></td>
                <td class="celda-principal celda-paciente" data-etiqueta="Paciente"><a href="<?= e($u) ?>"><?= e(paciente_nombre($r)) ?></a>
                    <small class="bloque"><?= e($r['TipoDocu'] . ' ' . $r['NumeUsua']) ?></small></td>
                <td data-etiqueta="Edad" class="sin-salto"><?= e(edad_texto($r['ValoEdad'], $r['UnidEdad'])) ?></td>
                <td data-etiqueta="EPS"><?= e($r['NombAdmi'] ?? $r['CodiAdmi']) ?></td>
                <td data-etiqueta="Dx ingreso"><?= e(diag_texto(diag_ingreso($r))) ?></td>
                <td data-etiqueta="Profesional"><?= e($r['UsuaDigi']) ?></td>
                <td data-etiqueta="Estado"><span class="etiqueta etiqueta-<?= e($ec) ?>"><?= e($et) ?></span></td>
                <td data-etiqueta="Carga a SIHOS"><?php if ($carga !== ''): ?><span class="etiqueta etiqueta-<?= e($carga) ?>"><?= e(ucfirst($carga)) ?></span><?php else: ?>—<?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if ($paginas > 1): ?>
    <nav class="paginacion" aria-label="Páginas">
        <?php if ($pagina > 1): ?><a class="boton boton-claro boton-chico" href="<?= e($url(['pagina' => $pagina - 1])) ?>">Anterior</a><?php endif; ?>
        <span>Página <?= $pagina ?> de <?= $paginas ?></span>
        <?php if ($pagina < $paginas): ?><a class="boton boton-claro boton-chico" href="<?= e($url(['pagina' => $pagina + 1])) ?>">Siguiente</a><?php endif; ?>
    </nav>
    <?php endif; ?>
<?php endif; ?>
<script src="js/formularios.js"></script>
<?php
vista_fin();
