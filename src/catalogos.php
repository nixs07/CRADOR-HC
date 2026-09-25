<?php
/**
 * Actualizacion de catalogos: copia desde la base ORIGEN de SIHOS a la base local
 * las tablas definidas en sql/02_catalogos.sql y sql/03_catalogos_listas.sql.
 *
 * Como se hace (por cada tabla):
 *   1. Se leen las columnas de la tabla local y de la tabla en SIHOS; se copian
 *      las columnas comunes, por nombre (si SIHOS agrega columnas, no se rompe).
 *   2. Se crea una tabla temporal cont_tmp_<Tabla> igual a la local (vacia) y se
 *      llena con INSERT por lotes leyendo SIHOS.
 *   3. Solo si la copia termino bien, se intercambia en un solo paso
 *      (RENAME TABLE) la tabla local por la nueva. Si algo falla, la tabla local
 *      queda EXACTAMENTE como estaba: nunca se queda un catalogo vacio.
 *   4. Cada tabla queda registrada en cont_catalogo_actualizacion.
 *
 * Si SIHOS no responde al inicio, no se toca nada y se registra el error.
 */

/** Columnas que NO se copian (pesadas o con claves de otros sistemas). */
const CATALOGO_EXCLUIR_COLUMNAS = [
    'Paciente' => ['Foto', 'Huella'],                     // fotos y huellas: pesadas, no se usan
    'Usuarios' => ['huella', 'ContraSiifa'],              // huella y clave de SIIFA
    'CodiInst' => ['Pass', 'PassBloq', 'SoftPin', 'SoftPinNomiElec', 'SoftPinDocEqui',
                   'ContRips', 'TokenRipsJson'],          // credenciales de correo, DIAN y SISPRO
];

/** Filtros de filas por tabla (condicion WHERE en SIHOS). */
const CATALOGO_FILTROS = [
    'Usuarios' => 'Activo = 1',   // solo usuarios activos
];

/** Filas maximas por INSERT (se reduce si la tabla tiene muchas columnas). */
const CATALOGO_LOTE = 500;

/** Nombre del candado para que no corran dos actualizaciones a la vez. */
const CATALOGO_CANDADO = 'crador_actualizar_catalogos';

/**
 * Lista de tablas de catalogo, en el orden en que aparecen en sql/02 y sql/03.
 * Se toma de los mismos scripts para que nunca se desincronice.
 */
function catalogo_tablas(): array
{
    $tablas = [];
    foreach (['02_catalogos.sql', '03_catalogos_listas.sql'] as $archivo) {
        $sql = @file_get_contents(RAIZ . '/sql/' . $archivo);
        if ($sql === false) {
            throw new RuntimeException("No se encontró sql/$archivo");
        }
        preg_match_all('/CREATE TABLE `([A-Za-z0-9_]+)`/', $sql, $m);
        foreach ($m[1] as $t) {
            $tablas[] = $t;
        }
    }
    return array_values(array_unique($tablas));
}

/** Nombres de columnas de una tabla (lanza excepcion si la tabla no existe). */
function tabla_columnas(PDO $pdo, string $tabla): array
{
    $cols = [];
    foreach ($pdo->query('SHOW COLUMNS FROM `' . $tabla . '`') as $fila) {
        $cols[] = $fila['Field'];
    }
    return $cols;
}

/**
 * Ejecuta la actualizacion completa.
 *
 * @param string        $usuario  Login que ejecuta ('CLI' si es tarea programada)
 * @param string        $origen   'web' o 'cli'
 * @param callable|null $avisar   funcion(string $linea) para mostrar avance
 * @param array|null    $solo     limitar a estas tablas (null = todas)
 * @return array ['resultado' => ok|parcial|error, 'ejecucion_id' => int|null, 'mensaje' => string, 'tablas' => [...]]
 */
function actualizar_catalogos(string $usuario, string $origen, ?callable $avisar = null, ?array $solo = null): array
{
    $avisar = $avisar ?? function (string $linea): void {};
    $local = db();
    $usuario = substr($usuario, 0, 12);

    $tablas = catalogo_tablas();
    if ($solo) {
        $desconocidas = array_diff($solo, $tablas);
        if ($desconocidas) {
            return ['resultado' => 'error', 'ejecucion_id' => null, 'tablas' => [],
                    'mensaje' => 'Estas tablas no son catálogos: ' . implode(', ', $desconocidas)];
        }
        $tablas = array_values(array_intersect($tablas, $solo));
    }

    // Evitar dos actualizaciones simultaneas
    if ((int) $local->query("SELECT GET_LOCK('" . CATALOGO_CANDADO . "', 0)")->fetchColumn() !== 1) {
        return ['resultado' => 'error', 'ejecucion_id' => null, 'tablas' => [],
                'mensaje' => 'Ya hay una actualización de catálogos en curso. Espere a que termine.'];
    }

    $local->prepare("INSERT INTO cont_catalogo_ejecucion (inicio, usuario, origen, resultado) VALUES (NOW(), ?, ?, 'en_curso')")
          ->execute([$usuario, $origen]);
    $ejecucionId = (int) $local->lastInsertId();

    try {
        // 1. Conectar a SIHOS. Si no responde, no se toca nada local.
        $avisar('Conectando con SIHOS (' . config('SIHOS_HOST', '?') . ')...');
        try {
            $sihos = db_sihos();
        } catch (Throwable $e) {
            $mensaje = mensaje_error_conexion($e) . ' No se modificó ningún catálogo local.';
            cerrar_ejecucion($ejecucionId, 'error', 0, 0, 0, $mensaje);
            $avisar('ERROR: ' . $mensaje);
            return ['resultado' => 'error', 'ejecucion_id' => $ejecucionId, 'tablas' => [], 'mensaje' => $mensaje];
        }

        // 2. Copiar tabla por tabla
        $detalle = [];
        $ok = $errores = $filasTotal = 0;
        foreach ($tablas as $tabla) {
            $inicio = microtime(true);
            try {
                [$resultado, $filas, $mensaje] = copiar_tabla_catalogo($sihos, $local, $tabla);
            } catch (Throwable $e) {
                [$resultado, $filas, $mensaje] = ['error', 0, 'Se conservan los datos locales. ' . preg_replace('/\s+/', ' ', $e->getMessage())];
            }
            $segundos = round(microtime(true) - $inicio, 2);
            if ($resultado === 'error') {
                $errores++;
            } else {
                $ok++;
                $filasTotal += $filas;
            }
            $local->prepare('INSERT INTO cont_catalogo_actualizacion
                                (ejecucion_id, tabla, filas, fecha, usuario, resultado, segundos, mensaje)
                             VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)')
                  ->execute([$ejecucionId, $tabla, $filas, $usuario, $resultado, $segundos, $mensaje]);
            $detalle[] = compact('tabla', 'resultado', 'filas', 'segundos', 'mensaje');
            $avisar(sprintf('%-10s %-8s %8s filas  %6.2f s  %s', $tabla, strtoupper($resultado), numero($filas), $segundos, $mensaje));
        }

        // 3. Aviso si el administrador no quedo entre los usuarios activos
        if (in_array('Usuarios', $tablas, true)) {
            $st = $local->prepare('SELECT COUNT(*) FROM Usuarios WHERE Login = ? AND Activo = 1');
            $st->execute([admin_login()]);
            if ((int) $st->fetchColumn() === 0) {
                $avisar('AVISO: el administrador ' . admin_login() . ' no está activo en la tabla Usuarios local.');
            }
        }

        $resultadoGeneral = $errores === 0 ? 'ok' : ($ok > 0 ? 'parcial' : 'error');
        $mensaje = sprintf('%d tablas actualizadas, %d con error, %s filas copiadas.', $ok, $errores, numero($filasTotal));
        cerrar_ejecucion($ejecucionId, $resultadoGeneral, $ok, $errores, $filasTotal, $mensaje);
        $avisar($mensaje);
        return ['resultado' => $resultadoGeneral, 'ejecucion_id' => $ejecucionId, 'tablas' => $detalle, 'mensaje' => $mensaje];
    } catch (Throwable $e) {
        cerrar_ejecucion($ejecucionId, 'error', 0, 0, 0, 'Error inesperado: ' . $e->getMessage());
        throw $e;
    } finally {
        $local->query("SELECT RELEASE_LOCK('" . CATALOGO_CANDADO . "')");
    }
}

function cerrar_ejecucion(int $id, string $resultado, int $ok, int $errores, int $filas, string $mensaje): void
{
    db()->prepare('UPDATE cont_catalogo_ejecucion
                      SET fin = NOW(), resultado = ?, tablas_ok = ?, tablas_error = ?, filas_total = ?, mensaje = ?
                    WHERE id = ?')
        ->execute([$resultado, $ok, $errores, $filas, $mensaje, $id]);
}

/**
 * Copia una tabla de SIHOS a la base local.
 * @return array [resultado ok|omitida, filas, mensaje]
 */
function copiar_tabla_catalogo(PDO $sihos, PDO $local, string $tabla): array
{
    $colsLocal = tabla_columnas($local, $tabla);
    try {
        $colsOrigen = tabla_columnas($sihos, $tabla);
    } catch (PDOException $e) {
        throw new RuntimeException("La tabla $tabla no existe o no se puede leer en SIHOS (" . $e->getMessage() . ').');
    }

    $excluir = CATALOGO_EXCLUIR_COLUMNAS[$tabla] ?? [];
    $columnas = array_values(array_diff(array_intersect($colsLocal, $colsOrigen), $excluir));
    if (!$columnas) {
        throw new RuntimeException("La tabla $tabla no tiene columnas en común con SIHOS.");
    }
    $notas = [];
    $faltan = array_diff($colsLocal, $colsOrigen, $excluir);
    if ($faltan) {
        $notas[] = 'Columnas que no existen en SIHOS (quedan con su valor por defecto): ' . implode(', ', $faltan) . '.';
    }

    $lista = '`' . implode('`, `', $columnas) . '`';
    $tmp = 'cont_tmp_' . $tabla;
    $viejo = 'cont_old_' . $tabla;
    $porLote = max(1, min(CATALOGO_LOTE, intdiv(60000, count($columnas))));  // limite de 65.535 parametros

    // NO_AUTO_VALUE_ON_ZERO: respetar codigos 0 en columnas auto_increment
    $local->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION,NO_AUTO_VALUE_ON_ZERO'");
    $local->exec("DROP TABLE IF EXISTS `$tmp`");
    $local->exec("CREATE TABLE `$tmp` LIKE `$tabla`");

    $filas = 0;
    try {
        $filaSql = '(' . implode(', ', array_fill(0, count($columnas), '?')) . ')';
        $sqlLote = "INSERT INTO `$tmp` ($lista) VALUES " . implode(', ', array_fill(0, $porLote, $filaSql));
        $stLote = $local->prepare($sqlLote);

        $where = isset(CATALOGO_FILTROS[$tabla]) ? ' WHERE ' . CATALOGO_FILTROS[$tabla] : '';
        // Lectura sin buffer: las filas llegan de a poco, no se carga toda la tabla en memoria
        $sihos->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $consulta = $sihos->query("SELECT $lista FROM `$tabla`$where");

        $local->beginTransaction();
        $lote = [];
        $enLote = 0;
        while (($fila = $consulta->fetch(PDO::FETCH_NUM)) !== false) {
            foreach ($fila as $v) {
                $lote[] = $v;
            }
            $enLote++;
            if ($enLote === $porLote) {
                $stLote->execute($lote);
                $filas += $enLote;
                $lote = [];
                $enLote = 0;
            }
        }
        $consulta->closeCursor();
        $sihos->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        if ($enLote > 0) {
            $local->prepare("INSERT INTO `$tmp` ($lista) VALUES " . implode(', ', array_fill(0, $enLote, $filaSql)))
                  ->execute($lote);
            $filas += $enLote;
        }
        $local->commit();

        // Proteccion: si SIHOS devolvio 0 filas y la tabla local tiene datos, no se reemplaza
        $filasLocales = (int) $local->query("SELECT COUNT(*) FROM `$tabla`")->fetchColumn();
        if ($filas === 0 && $filasLocales > 0) {
            $local->exec("DROP TABLE IF EXISTS `$tmp`");
            return ['omitida', 0, "SIHOS devolvió 0 filas; se conservan las " . numero($filasLocales) . ' filas locales.'];
        }

        // Intercambio en un solo paso
        $local->exec("DROP TABLE IF EXISTS `$viejo`");
        $local->exec("RENAME TABLE `$tabla` TO `$viejo`, `$tmp` TO `$tabla`");
        $local->exec("DROP TABLE IF EXISTS `$viejo`");
    } catch (Throwable $e) {
        if ($local->inTransaction()) {
            $local->rollBack();
        }
        try {
            if (isset($consulta)) {
                $consulta->closeCursor();
            }
            $sihos->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        } catch (Throwable $ignorar) {
        }
        $local->exec("DROP TABLE IF EXISTS `$tmp`");
        throw $e;
    } finally {
        $local->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
    }

    if ($filas === 0) {
        $notas[] = 'SIHOS no tiene filas en esta tabla.';
    }
    return ['ok', $filas, implode(' ', $notas)];
}

/** Ultima ejecucion terminada (ok o parcial), para el tablero. */
function catalogo_ultima_actualizacion(): ?array
{
    $fila = db()->query("SELECT * FROM cont_catalogo_ejecucion
                          WHERE resultado IN ('ok', 'parcial') ORDER BY id DESC LIMIT 1")->fetch();
    return $fila ?: null;
}
