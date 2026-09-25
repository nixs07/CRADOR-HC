<?php
/**
 * Plantilla HTML comun (encabezado y pie).
 */

function vista_inicio(string $titulo): void
{
    $u = usuario_actual();
    $pagina = basename($_SERVER['SCRIPT_NAME'] ?? '');
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · CRADOR-HC</title>
    <link rel="stylesheet" href="css/estilo.css">
</head>
<body>
<header class="barra">
    <div class="barra-marca">
        <strong>CRADOR-HC</strong>
        <span>Contingencia SIHOS · E.S.E. Hospital Sagrado Corazón de Jesús</span>
    </div>
    <?php if ($u): ?>
    <nav class="barra-menu">
        <a href="index.php" class="<?= $pagina === 'index.php' ? 'activo' : '' ?>">Tablero</a>
        <a href="pacientes.php" class="<?= in_array($pagina, ['pacientes.php', 'paciente_nuevo.php', 'admision_nueva.php'], true) ? 'activo' : '' ?>">Pacientes</a>
        <?php foreach (MODULOS_DETALLE as $clave => $m): ?>
            <a href="admisiones.php?modulo=<?= e($clave) ?>" class="<?= ($pagina === 'admisiones.php' && ($_GET['modulo'] ?? '') === $clave) ? 'activo' : '' ?>"><?= e($m['nombre']) ?></a>
        <?php endforeach; ?>
        <?php if (es_admin()): ?>
            <a href="catalogos.php" class="<?= $pagina === 'catalogos.php' ? 'activo' : '' ?>">Catálogos</a>
        <?php endif; ?>
    </nav>
    <div class="barra-usuario">
        <span title="<?= e($u['Login']) ?>"><?= e($u['Nombre']) ?><?= es_admin() ? ' · Administrador' : '' ?></span>
        <form method="post" action="logout.php" class="en-linea">
            <?= csrf_campo() ?>
            <button type="submit" class="boton boton-claro">Salir</button>
        </form>
    </div>
    <?php endif; ?>
</header>
<main class="contenido">
    <?php foreach (flash_obtener() as $f): ?>
        <div class="alerta alerta-<?= e($f['tipo']) ?>"><?= e($f['mensaje']) ?></div>
    <?php endforeach; ?>
<?php
}

function vista_fin(): void
{
    ?>
</main>
<footer class="pie">CRADOR-HC · Registro clínico de contingencia. Los datos se cargan a SIHOS cuando el sistema vuelva.</footer>
</body>
</html>
<?php
}
