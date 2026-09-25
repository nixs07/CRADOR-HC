<?php
/**
 * Conexiones a base de datos (PDO).
 *
 *  - db()        : base LOCAL de la contingencia (siempre disponible).
 *  - db_sihos()  : base ORIGEN de SIHOS (solo lectura, puede estar caida).
 *
 * Ambas conexiones usan utf8: MySQL convierte automaticamente desde/hacia las
 * tablas latin1, asi la pagina trabaja en UTF-8 y los datos quedan iguales.
 */

/** Opciones comunes de PDO. */
function pdo_opciones(int $timeout = 5): array
{
    return [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => $timeout,
    ];
}

/** Ajustes de sesion MySQL: sin modo estricto (fechas 0000-00-00) y hora de Colombia. */
function pdo_ajustar_sesion(PDO $pdo): void
{
    $pdo->exec("SET NAMES utf8");
    $pdo->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
    $pdo->exec("SET time_zone = " . $pdo->quote(date('P')));
}

/** Conexion a la base local (una sola por peticion). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8',
            config('DB_HOST', 'db'),
            config('DB_PUERTO', '3306'),
            config('DB_NOMBRE', 'crador_hc')
        );
        $pdo = new PDO($dsn, config('DB_USUARIO', 'crador'), config('DB_CLAVE', ''), pdo_opciones());
        pdo_ajustar_sesion($pdo);
    }
    return $pdo;
}

/**
 * Nueva conexion a la base ORIGEN de SIHOS. Lanza PDOException si no responde
 * dentro de SIHOS_TIMEOUT segundos.
 */
function db_sihos(?int $timeout = null): PDO
{
    $host = config('SIHOS_HOST');
    if ($host === null) {
        throw new RuntimeException('No está configurado SIHOS_HOST en el archivo .env');
    }
    $timeout = $timeout ?? (int) config('SIHOS_TIMEOUT', '3');
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8',
        $host,
        config('SIHOS_PUERTO', '3306'),
        config('SIHOS_NOMBRE', 'sihos')
    );
    $pdo = new PDO($dsn, config('SIHOS_USUARIO', ''), config('SIHOS_CLAVE', ''), pdo_opciones(max(1, $timeout)));
    pdo_ajustar_sesion($pdo);
    return $pdo;
}

/**
 * Estado de la conexion con SIHOS: ['arriba' => bool, 'mensaje' => string, 'verificado' => 'Y-m-d H:i:s'].
 * El resultado se guarda en la sesion unos segundos para no esperar el timeout en cada pagina.
 */
function sihos_estado(bool $forzar = false, int $cacheSegundos = 60): array
{
    $cache = $_SESSION['sihos_estado'] ?? null;
    if (!$forzar && $cache && (time() - $cache['ts']) < $cacheSegundos) {
        return $cache;
    }
    $inicio = microtime(true);
    try {
        $pdo = db_sihos();
        $pdo->query('SELECT 1')->fetchColumn();
        $estado = ['arriba' => true, 'mensaje' => sprintf('Responde en %.2f s', microtime(true) - $inicio)];
    } catch (Throwable $e) {
        $estado = ['arriba' => false, 'mensaje' => mensaje_error_conexion($e)];
    }
    $estado['verificado'] = date('Y-m-d H:i:s');
    $estado['ts'] = time();
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['sihos_estado'] = $estado;
    }
    return $estado;
}

/** Traduce los errores de conexion mas comunes a un mensaje claro (sin exponer claves). */
function mensaje_error_conexion(Throwable $e): string
{
    $m = $e->getMessage();
    if (stripos($m, 'timed out') !== false || stripos($m, '2002') !== false || stripos($m, 'refused') !== false
        || stripos($m, 'No route') !== false || stripos($m, 'getaddrinfo') !== false || stripos($m, 'server has gone away') !== false) {
        return 'SIHOS no responde (servidor caído o sin red).';
    }
    if (stripos($m, '1045') !== false || stripos($m, 'Access denied') !== false) {
        return 'SIHOS responde pero rechazó el usuario o la clave del .env.';
    }
    if (stripos($m, '1049') !== false || stripos($m, 'Unknown database') !== false) {
        return 'SIHOS responde pero no existe la base indicada en SIHOS_NOMBRE.';
    }
    if ($e instanceof RuntimeException) {
        return $m;
    }
    return 'No fue posible conectar con SIHOS: ' . preg_replace('/\s+/', ' ', $m);
}
