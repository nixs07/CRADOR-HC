<?php
/**
 * Plantilla HTML comun: barra lateral de navegacion, barra superior y pie.
 * Todo (fuente, iconos, CSS y JS) se sirve desde public/: funciona sin internet.
 */

/**
 * Icono SVG del sprite public/img/iconos.svg (iconos Lucide, licencia ISC).
 * Ej.: icono('siren'). Los nombres disponibles son los <symbol id="i-..."> del sprite.
 */
function icono(string $nombre, string $clase = ''): string
{
    return '<svg class="icono ' . e($clase) . '" aria-hidden="true" focusable="false">'
         . '<use href="img/iconos.svg#i-' . e($nombre) . '"></use></svg>';
}

/** Icono de cada modulo clinico. */
const MODULOS_ICONO = ['urg' => 'siren', 'obs' => 'bed-double', 'ce' => 'stethoscope'];

/** Numero romano de la clasificacion de triage (1..5). */
function triage_romano($clas): string
{
    return ['', 'I', 'II', 'III', 'IV', 'V'][(int) $clas] ?? (string) $clas;
}

/** Iniciales para el avatar del usuario: "MEDICO DE PRUEBA" -> "MP". */
function iniciales(string $nombre): string
{
    $partes = array_values(array_filter(preg_split('/\s+/', trim($nombre)), fn ($p) => mb_strlen($p) > 2));
    if (!$partes) {
        return mb_strtoupper(mb_substr($nombre, 0, 2));
    }
    $ini = mb_substr($partes[0], 0, 1) . (isset($partes[1]) ? mb_substr(end($partes), 0, 1) : '');
    return mb_strtoupper($ini);
}

function vista_inicio(string $titulo): void
{
    $u = usuario_actual();
    $pagina = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $moduloActual = $_GET['modulo'] ?? '';
    // Enlace activo del menu
    $activo = function (bool $cond): string {
        return $cond ? ' class="activo" aria-current="page"' : '';
    };
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <title><?= e($titulo) ?> · CRADOR-HC</title>
    <link rel="icon" type="image/png" href="img/logo.png">
    <link rel="preload" href="fonts/plus-jakarta-sans-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="css/estilo.css">
</head>
<body class="<?= $u ? 'con-menu' : 'sin-menu' ?>">
<a class="saltar" href="#contenido">Saltar al contenido</a>
<?php if ($u): ?>
<aside class="lateral" id="menu-lateral" aria-label="Menú principal">
<div class="lateral-interno">
    <div class="lateral-marca">
        <img src="img/logo.png" alt="" width="40" height="42">
        <div>
            <strong>CRADOR-HC</strong>
            <span>Contingencia SIHOS</span>
        </div>
        <button type="button" class="boton-icono lateral-cerrar" data-menu-cerrar aria-label="Cerrar menú"><?= icono('x') ?></button>
    </div>
    <nav class="menu">
        <div class="menu-grupo">Atención</div>
        <a href="index.php"<?= $activo($pagina === 'index.php') ?>><?= icono('layout-dashboard') ?><span>Tablero</span></a>
        <a href="pacientes.php"<?= $activo(in_array($pagina, ['pacientes.php', 'paciente_nuevo.php', 'admision_nueva.php'], true)) ?>><?= icono('users') ?><span>Pacientes</span></a>

        <div class="menu-grupo">Módulos</div>
        <?php foreach (MODULOS_DETALLE as $clave => $m): ?>
            <a href="admisiones.php?modulo=<?= e($clave) ?>"<?= $activo($pagina === 'admisiones.php' && $moduloActual === $clave) ?>>
                <?= icono(MODULOS_ICONO[$clave] ?? 'clipboard-list') ?><span><?= e($m['nombre']) ?></span></a>
        <?php endforeach; ?>

        <?php if (es_admin()): ?>
            <div class="menu-grupo">Administración</div>
            <a href="catalogos.php"<?= $activo($pagina === 'catalogos.php') ?>><?= icono('database') ?><span>Catálogos</span></a>
            <span class="menu-deshabilitado" aria-disabled="true" title="Disponible en la fase 3"><?= icono('cloud-upload') ?><span>Cargar a SIHOS</span><small>Fase 3</small></span>
        <?php endif; ?>
    </nav>
    <div class="lateral-pie">
        <div class="lateral-hospital">
            <?= icono('hospital') ?>
            <span>E.S.E. Hospital Sagrado Corazón de Jesús</span>
        </div>
    </div>
</div>
</aside>
<div class="velo" data-menu-cerrar hidden></div>
<?php endif; ?>

<div class="principal">
    <?php if ($u): ?>
    <header class="superior">
        <button type="button" class="boton-icono boton-menu" data-menu-abrir aria-controls="menu-lateral" aria-expanded="false" aria-label="Abrir menú">
            <?= icono('menu') ?>
        </button>
        <a href="index.php" class="superior-marca"><img src="img/logo.png" alt="" width="28" height="30"><strong>CRADOR-HC</strong></a>
        <div class="superior-contexto">
            <span class="chip chip-contingencia"><?= icono('wifi-off') ?>Registro de contingencia</span>
            <span class="superior-fecha"><?= icono('calendar') ?><?= e(date('d/m/Y')) ?></span>
        </div>
        <div class="superior-usuario">
            <span class="avatar" aria-hidden="true"><?= e(iniciales($u['Nombre'])) ?></span>
            <span class="usuario-datos" title="<?= e($u['Login']) ?>">
                <strong><?= e($u['Nombre']) ?></strong>
                <small><?= e($u['Login']) ?><?= es_admin() ? ' · Administrador' : '' ?></small>
            </span>
            <form method="post" action="logout.php" class="en-linea">
                <?= csrf_campo() ?>
                <button type="submit" class="boton boton-claro boton-salir" title="Cerrar sesión"><?= icono('log-out') ?><span>Salir</span></button>
            </form>
        </div>
    </header>
    <?php endif; ?>
    <main class="contenido" id="contenido" tabindex="-1">
        <?php foreach (flash_obtener() as $f): ?>
            <div class="alerta alerta-<?= e($f['tipo']) ?>" role="status"><?= icono(['ok' => 'circle-check', 'error' => 'circle-alert'][$f['tipo']] ?? 'triangle-alert') ?><div><?= e($f['mensaje']) ?></div></div>
        <?php endforeach; ?>
<?php
}

function vista_fin(): void
{
    ?>
    </main>
    <footer class="pie">CRADOR-HC · Registro clínico de contingencia. Los datos se cargan a SIHOS cuando el sistema vuelva.</footer>
</div>
<script src="js/menu.js"></script>
</body>
</html>
<?php
}
