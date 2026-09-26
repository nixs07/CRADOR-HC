<?php
/**
 * Datos para las listas que dependen de otro campo (respuestas JSON).
 *   api.php?que=municipios&depa=86
 *   api.php?que=contratos&admi=ESS118
 *   api.php?que=estratos&admi=ESS118&aten=3&afil=D
 *   api.php?que=camas&serv=008
 *   api.php?que=diagnosticos&q=R10
 *   api.php?que=procedimientos&q=8902   (CodiProc activos, por código o nombre)
 *   api.php?que=suministros&q=ACETA    (CodiSumi activos, por código o nombre)
 * Solo lectura y solo con sesion iniciada.
 */
require __DIR__ . '/../src/inicio.php';
require __DIR__ . '/../src/historia.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!usuario_actual()) {
    http_response_code(401);
    echo json_encode(['error' => 'Sesión vencida. Recargue la página e ingrese de nuevo.']);
    exit;
}

$g = function (string $k, int $max = 30): string {
    $v = $_GET[$k] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
};

/** Convierte [codigo => nombre] en lista ordenada [{c, n}] (JSON conserva el orden). */
$aLista = function (array $datos): array {
    $r = [];
    foreach ($datos as $c => $n) {
        $r[] = ['c' => (string) $c, 'n' => $n];
    }
    return $r;
};

switch ($_GET['que'] ?? '') {
    case 'municipios':
        $r = $aLista(municipios($g('depa', 2)));
        break;
    case 'contratos':
        $r = $aLista(contratos($g('admi', 6)));
        break;
    case 'estratos':
        $r = $aLista(estratos($g('admi', 6), (int) $g('aten', 1), $g('afil', 1)));
        break;
    case 'camas':
        $r = $aLista(camas($g('serv', 3)));
        break;
    case 'diagnosticos':
        $r = array_map(fn ($f) => ['c' => $f['CodiDiag'], 'n' => $f['NombCaus']], diagnosticos_buscar($g('q', 60)));
        break;
    case 'procedimientos':
        $r = procedimientos_buscar($g('q', 60));
        break;
    case 'suministros':
        $r = suministros_buscar($g('q', 60));
        break;
    default:
        http_response_code(400);
        $r = ['error' => 'Consulta no válida'];
}
echo json_encode($r, JSON_UNESCAPED_UNICODE);
