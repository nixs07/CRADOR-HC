<?php
/**
 * Consultas del tablero principal.
 * Admision abierta = Cerrado = 2 y Anulado = 2 (convencion de SIHOS: 1 = si, 2 = no).
 */

/** Admisiones abiertas por modulo: ['Urgencias' => ['servicios' => [...], 'abiertas' => n], ...] */
function tablero_abiertas_por_modulo(): array
{
    $conteo = [];
    $sql = 'SELECT CodiServ, COUNT(*) AS total FROM Admision
             WHERE Cerrado = 2 AND Anulado = 2 GROUP BY CodiServ';
    foreach (db()->query($sql) as $fila) {
        $conteo[$fila['CodiServ']] = (int) $fila['total'];
    }
    $resultado = [];
    foreach (MODULOS as $modulo => $servicios) {
        $total = 0;
        foreach ($servicios as $s) {
            $total += $conteo[$s] ?? 0;
        }
        $resultado[$modulo] = ['servicios' => $servicios, 'abiertas' => $total];
    }
    return $resultado;
}

/** Admisiones con fecha de ingreso hoy (no anuladas). */
function tablero_admisiones_hoy(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM Admision WHERE FechIngr = CURDATE() AND Anulado = 2')->fetchColumn();
}

/** Conteo de cont_carga_sihos por estado: ['pendiente' => n, 'cargada' => n, 'error' => n] */
function tablero_cargas(): array
{
    $r = ['pendiente' => 0, 'cargada' => 0, 'error' => 0];
    foreach (db()->query('SELECT estado, COUNT(*) AS total FROM cont_carga_sihos GROUP BY estado') as $fila) {
        $r[$fila['estado']] = (int) $fila['total'];
    }
    return $r;
}
