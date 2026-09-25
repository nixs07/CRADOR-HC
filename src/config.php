<?php
/**
 * Configuracion de la aplicacion.
 *
 * Los valores salen de las variables de entorno (Docker las pasa desde .env).
 * Si la app corre fuera de Docker, se lee directamente el archivo .env de la
 * raiz del proyecto. Una variable de entorno ya definida tiene prioridad.
 */

define('RAIZ', dirname(__DIR__));

/** Lee el archivo .env (formato CLAVE=valor, lineas con # son comentarios). */
function cargar_env(string $archivo): void
{
    if (!is_readable($archivo)) {
        return;
    }
    foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);                       // quita tambien el \r de Windows
        if ($linea === '' || $linea[0] === '#' || strpos($linea, '=') === false) {
            continue;
        }
        [$clave, $valor] = array_map('trim', explode('=', $linea, 2));
        // Quitar comillas envolventes si las tiene
        if (strlen($valor) >= 2 && ($valor[0] === '"' || $valor[0] === "'") && substr($valor, -1) === $valor[0]) {
            $valor = substr($valor, 1, -1);
        }
        if (getenv($clave) === false) {
            putenv("$clave=$valor");
            $_ENV[$clave] = $valor;
        }
    }
}

cargar_env(RAIZ . '/.env');

/** Devuelve un valor de configuracion o $defecto si no existe. */
function config(string $clave, ?string $defecto = null): ?string
{
    $valor = getenv($clave);
    return ($valor === false || $valor === '') ? $defecto : $valor;
}

/** Login de SIHOS con rol administrador (ADMIN_LOGIN del .env). */
function admin_login(): string
{
    return strtoupper(trim(config('ADMIN_LOGIN', 'NIXON07')));
}

/**
 * Modulos del tablero: nombre => codigos de servicio (Admision.CodiServ).
 * Ver docs/REGLAS.md.
 */
const MODULOS = [
    'Urgencias'                 => ['007'],
    'Observación e Internación' => ['008'],
    'Consulta Externa'          => ['001', '013'],
];

date_default_timezone_set(config('TZ', 'America/Bogota'));
