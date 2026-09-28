<?php
/**
 * Seleccion del modulo de trabajo (como en SIHOS: Gestion > Urgencias, Observacion, Consulta Externa).
 * El profesional llega aqui despues del ingreso. El modulo queda en la sesion al entrar a su pantalla.
 */
require __DIR__ . '/../src/inicio.php';
require __DIR__ . '/../src/tablero.php';

$u = requiere_login();
$abiertas = tablero_abiertas_por_modulo();
$actual = modulo_actual();

// Descripcion corta de lo que se registra en cada modulo (lo que ya existe en la app)
$descripcion = [
    'urg' => 'Admisión, triage y signos vitales.',
    'obs' => 'Admisión con cama y signos vitales.',
    'ce'  => 'Admisión y signos vitales.',
];

vista_inicio('Elegir módulo');
?>
<div class="cabecera-pagina cabecera-centrada">
    <div>
        <div class="antetitulo"><?= icono('hospital') ?>E.S.E. Hospital Sagrado Corazón de Jesús</div>
        <h1>¿En qué módulo va a trabajar?</h1>
        <p>Hola, <?= e($u['Nombre']) ?>. Elija el módulo para ver sus historias abiertas. Puede cambiarlo cuando quiera.</p>
    </div>
</div>

<div class="modulos">
    <?php foreach (MODULOS_DETALLE as $clave => $m):
        $n = $abiertas[$m['nombre']]['abiertas'] ?? 0; ?>
        <a class="modulo modulo-<?= e($clave) ?><?= $actual === $clave ? ' modulo-actual' : '' ?>" href="atencion.php?modulo=<?= e($clave) ?>">
            <span class="modulo-icono"><?= icono(MODULOS_ICONO[$clave]) ?></span>
            <span class="modulo-nombre"><?= e($m['nombre']) ?></span>
            <span class="modulo-texto"><?= e($descripcion[$clave] ?? '') ?></span>
            <span class="modulo-conteo"><strong><?= numero($n) ?></strong> <?= $n === 1 ? 'historia abierta' : 'historias abiertas' ?></span>
            <span class="modulo-ir">Entrar <?= icono('chevron-right') ?></span>
            <?php if ($actual === $clave): ?><span class="modulo-marca">Módulo actual</span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>
<?php
vista_fin();
