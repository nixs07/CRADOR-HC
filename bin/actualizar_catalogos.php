<?php
/**
 * Actualiza los catalogos locales copiandolos desde SIHOS (linea de comandos).
 *
 * Uso (dentro de Docker):
 *   docker compose exec -T app php bin/actualizar_catalogos.php
 *   docker compose exec -T app php bin/actualizar_catalogos.php --tablas=Usuarios,CodiEspe
 *
 * Codigo de salida: 0 = todo bien, 1 = error (SIHOS caido o todas fallaron), 2 = parcial.
 * Pensado para programarse cada noche (Programador de tareas de Windows o cron).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/catalogos.php';

$opciones = getopt('', ['tablas::', 'ayuda']);
if (isset($opciones['ayuda'])) {
    echo "Uso: php bin/actualizar_catalogos.php [--tablas=Tabla1,Tabla2]\n";
    echo "Tablas de catalogo: " . implode(', ', catalogo_tablas()) . "\n";
    exit(0);
}
$solo = !empty($opciones['tablas']) ? array_filter(array_map('trim', explode(',', $opciones['tablas']))) : null;

set_time_limit(0);
echo '[' . date('Y-m-d H:i:s') . "] Inicio de actualizacion de catalogos\n";

try {
    $r = actualizar_catalogos('CLI', 'cli', function (string $linea): void {
        echo $linea, "\n";
    }, $solo);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}

echo '[' . date('Y-m-d H:i:s') . '] Fin: ' . strtoupper($r['resultado']) . ' - ' . $r['mensaje'] . "\n";
exit(['ok' => 0, 'parcial' => 2][$r['resultado']] ?? 1);
