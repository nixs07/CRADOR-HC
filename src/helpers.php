<?php
/**
 * Funciones de ayuda generales.
 */

/** Escapa texto para mostrarlo en HTML. Usar SIEMPRE al imprimir datos. */
function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Redirige a otra pagina de la app y termina. */
function redirigir(string $pagina): void
{
    header('Location: ' . $pagina);
    exit;
}

/** Guarda un mensaje para mostrar en la siguiente pagina. $tipo: ok / error / aviso */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

/** Devuelve y borra los mensajes pendientes. */
function flash_obtener(): array
{
    $mensajes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $mensajes;
}

/** Fecha y hora legible: 2026-09-25 14:30:00 -> 25/09/2026 14:30 */
function fecha_hora(?string $valor): string
{
    if (!$valor || strpos($valor, '0000-00-00') === 0) {
        return '—';
    }
    $t = strtotime($valor);
    return $t ? date('d/m/Y H:i', $t) : $valor;
}

/** Numero con separador de miles */
function numero($n): string
{
    return number_format((float) $n, 0, ',', '.');
}

/** IP del cliente (para el registro de accesos). */
function ip_cliente(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? 'cli', 0, 45);
}
