<?php
/**
 * Listas desplegables a partir de los catalogos locales (copiados de SIHOS).
 *
 * Regla: todo campo con codigo se escoge de su catalogo y se VALIDA en el
 * servidor con lista_valida() antes de guardar (ver docs/REGLAS.md).
 */

/**
 * Definicion de cada lista: nombre => [tabla, columna codigo, columna nombre, condicion WHERE].
 * Las condiciones son fijas (no llevan datos del usuario).
 */
const LISTAS = [
    'TipoDocu' => ['TipoDocu', 'CodiTipo', 'NombTipo', ''],
    'Sexo'     => ['CodiSexo', 'CodiSexo', 'NombSexo', ''],
    'Depa'     => ['CodiDepa', 'CodiDepa', 'NombDepa', ''],
    'Zona'     => ['CodiZona', 'CodiZona', 'NombZona', ''],
    'Admi'     => ['CodiAdmi', 'CodiAdmi', 'NombAdmi', 'EstaAdmi = 1'],
    'TipoUsua' => ['TipoUsua', 'CodiTipo', 'NombTipo', ''],
    'TipoAfil' => ['TipoAfil', 'CodiTipo', 'NombTipo', ''],
    'Estr'     => ['CodiEstr', 'CodiEstr', 'NombEstr', ''],
    'ViaIngre' => ['ViaIngre', 'CodiVia', 'NombVia', ''],
    'CausExte' => ['CausExte', 'CodiMoti', 'NombMoti', ''],
    'CondUsua' => ['CondUsua', 'CodiCond', 'NombCond', ''],
    'GrupAten' => ['GrupAten', 'CodiGrup', 'NombGrup', ''],
    'TipoAcom' => ['TipoAcom', 'CodiTipo', 'NombTipo', ''],
    'Parentes' => ['Parentes', 'CodiPare', 'NombPare', ''],
    'Serv'     => ['CodiServ', 'CodiServ', 'NombServ', ''],
    'ClasTria' => ['ClasTria', 'CodiTria', 'NombTria', ''],
    'CondTria' => ['CondTria', 'CodiTipo', 'NombTipo', ''],
    // Pestañas de la historia (fase 2, bloque 2)
    'FinaCons' => ['FinaCons', 'CodiFina', 'NombFina', '(Activo = 1 OR Activo IS NULL)'],
    'TipoDiag' => ['TipoDiag', 'CodiDiag', 'NombDiag', ''],
    'ViaAdmi'  => ['ViaAdmi', 'CodiVia', 'NombVia', '(activo = 1 OR activo IS NULL)'],
    'UnidMedi' => ['UnidMedi', 'CodiUnid', 'NombUnid', ''],
    'CodiTiem' => ['CodiTiem', 'CodiTiem', 'NombTiem', '(activo = 1 OR activo IS NULL)'],
    'FinaProc' => ['FinaProc', 'CodiFina', 'NombFina', 'Activo = 1'],
    'TipoNota' => ['TipoNota', 'CodiTipo', 'NombTipo', ''],
    'CausSali' => ['CausSali', 'CodiCaus', 'NombCaus', ''],
    'DestSali' => ['DestSali', 'CodiDest', 'NombDest', 'Activo = 1'],
    'EstaSali' => ['EstaSali', 'Codigo', 'EstaSali', ''],
    'TipoEgre' => ['TipoEgre', 'CodiTipo', 'NombTipo', ''],
    'CodiUnid' => ['CodiUnid', 'CodiUnid', 'NombUnid', ''],
    'MotiRemi' => ['MotiRemi', 'CodiMoti', 'NombMoti', ''],
    'ModaSoli' => ['ModaSoli', 'CodiModa', 'NombModa', ''],
    'TipoInca' => ['TipoInca', 'CodiTipo', 'NombTipo', ''],
    'Espe'     => ['CodiEspe', 'CodiEspe', 'NombEspe', ''],
    'Cons'     => ['CodiCons', 'CodiCons', 'NombCons', '(EstaCons = 1 OR EstaCons IS NULL)'],
    // Catalogos de sql/04_listas_permisos.sql (estructuras reales, docs/RESULTADO_CONSULTAS_SIHOS.md)
    'InstRemi' => ['InstRemi', 'CodInsRe', 'NombInst', ''],       // Remision.InstRemi guarda CodInsRe
    'EstaIngr' => ['EstaIngr', 'CodiEsta', 'NombEsta', ''],
    // Listas genericas (priv_listas_elementos por tipo). Conducta, tipo de alergia y factor de riesgo guardan el id
    'Conducta'     => ['priv_listas_elementos', 'id', 'nombre', 'prv_lista_tipo_id = 33 AND (activo = 1 OR activo IS NULL)'],
    'TipoAlergia'  => ['priv_listas_elementos', 'id', 'nombre', 'prv_lista_tipo_id = 13 AND (activo = 1 OR activo IS NULL)'],
    'FactorRiesgo' => ['priv_listas_elementos', 'id', 'nombre', 'prv_lista_tipo_id = 14 AND (activo = 1 OR activo IS NULL)'],
    'PregAnte'     => ['priv_listas_elementos', 'id', 'nombre', 'prv_lista_tipo_id = 45 AND (activo = 1 OR activo IS NULL)'],
    // "Cada" de la prescripcion hospitalaria (DetaPres.HoraApli): 0 = AHORA, 1..24 horas. Estructura PROVISIONAL
    // (sql/06_hora_apli.sql): columnas por confirmar con SHOW CREATE TABLE HoraApli en SIHOS
    'HoraApli'     => ['HoraApli', 'CodiHora', 'NombHora', ''],
    // Codigo Dorado: EstaCodo guarda el CODIGO como numero (lista 47)
    'EstaCodo'     => ['priv_listas_elementos', 'codigo', 'nombre', 'prv_lista_tipo_id = 47 AND (activo = 1 OR activo IS NULL)'],
];

/** Devuelve [codigo => nombre] de una lista, ordenada por nombre (se guarda en memoria por peticion). */
/** Listas de catalogos agregados despues (sql/04): si la tabla aun no existe en una instalacion, quedan vacias. */
const LISTAS_PROVISIONALES = ['InstRemi', 'EstaIngr', 'Conducta', 'TipoAlergia', 'FactorRiesgo', 'PregAnte', 'EstaCodo', 'HoraApli'];

function lista(string $nombre): array
{
    static $cache = [];
    if (!isset($cache[$nombre])) {
        [$tabla, $cod, $nom, $where] = LISTAS[$nombre];
        $sql = "SELECT `$cod` AS c, `$nom` AS n FROM `$tabla`" . ($where ? " WHERE $where" : '') . " ORDER BY `$nom`";
        $cache[$nombre] = [];
        try {
            foreach (db()->query($sql) as $f) {
                $cache[$nombre][(string) $f['c']] = (string) $f['n'];
            }
        } catch (PDOException $e) {
            // Catalogos de sql/04 que aun no existan en la instalacion: la lista queda vacia
            if (!in_array($nombre, LISTAS_PROVISIONALES, true)) {
                throw $e;
            }
        }
    }
    return $cache[$nombre];
}

/** true si $valor es un codigo que existe en la lista. */
function lista_valida(string $nombre, $valor): bool
{
    return $valor !== null && $valor !== '' && array_key_exists((string) $valor, lista($nombre));
}

/** Nombre de un codigo (o el mismo codigo si no esta en el catalogo). */
function lista_nombre(string $nombre, $valor): string
{
    $l = lista($nombre);
    return $l[(string) $valor] ?? (string) $valor;
}

/** Opciones <option> de una lista, marcando la seleccionada. */
function opciones(string $nombre, $seleccion = null, bool $vacia = true, bool $conCodigo = false): string
{
    $html = $vacia ? '<option value="">— Seleccione —</option>' : '';
    foreach (lista($nombre) as $c => $n) {
        $sel = ((string) $seleccion === (string) $c) ? ' selected' : '';
        $html .= '<option value="' . e($c) . '"' . $sel . '>' . e($conCodigo ? "$c · $n" : $n) . '</option>';
    }
    return $html;
}

/** Opciones a partir de un arreglo [codigo => nombre]. */
function opciones_arreglo(array $datos, $seleccion = null, bool $vacia = true): string
{
    $html = $vacia ? '<option value="">— Seleccione —</option>' : '';
    foreach ($datos as $c => $n) {
        $sel = ((string) $seleccion === (string) $c) ? ' selected' : '';
        $html .= '<option value="' . e($c) . '"' . $sel . '>' . e($n) . '</option>';
    }
    return $html;
}

// ---------------------------------------------------------------------
// Listas que dependen de otro campo
// ---------------------------------------------------------------------

/** Municipios de un departamento: [CodiMuni => NombMuni] */
function municipios(string $depa): array
{
    $st = db()->prepare('SELECT CodiMuni, NombMuni FROM CodiMuni WHERE CodiDepa = ? ORDER BY NombMuni');
    $st->execute([$depa]);
    return array_column($st->fetchAll(), 'NombMuni', 'CodiMuni');
}

/**
 * Contratos activos de una EPS: [NumeCont => "NumeCont · descripcion"].
 * "Activo" = EstaCont 1. No se usan las fechas: en SIHOS hay contratos con
 * fecha de fin vencida que se siguen usando todos los dias.
 */
function contratos(string $codiAdmi): array
{
    $st = db()->prepare('SELECT NumeCont, DescCont FROM Contrato
                          WHERE CodiInst = ? AND CodiAdmi = ? AND EstaCont = 1 ORDER BY NumeCont');
    $st->execute([CODI_INST, $codiAdmi]);
    $r = [];
    foreach ($st as $f) {
        $r[$f['NumeCont']] = $f['NumeCont'] . ' · ' . $f['DescCont'];
    }
    return $r;
}

/** Categorias (CodiEstr) permitidas para EPS + tipo de atencion + tipo de afiliacion (tabla AdmiEstr). */
function estratos(string $codiAdmi, int $tipoAten, string $tipoAfil): array
{
    $st = db()->prepare('SELECT DISTINCT a.CodiEstr, IFNULL(e.NombEstr, a.CodiEstr) AS NombEstr
                           FROM AdmiEstr a LEFT JOIN CodiEstr e ON e.CodiEstr = a.CodiEstr
                          WHERE a.CodiInst = ? AND a.CodiAdmi = ? AND a.TipoAten = ? AND a.TipoAfil = ? AND a.EstaEstr = 1
                          ORDER BY a.CodiEstr');
    $st->execute([CODI_INST, $codiAdmi, $tipoAten, $tipoAfil]);
    return array_column($st->fetchAll(), 'NombEstr', 'CodiEstr');
}

/** Camas activas de un servicio: [CodiCama => "CodiCama · nombre (ocupada)"] */
function camas(string $codiServ): array
{
    $st = db()->prepare('SELECT c.CodiCama, c.NombCama,
                                (SELECT a.ConsAdmi FROM Admision a
                                  WHERE a.CodiInst = c.CodiInst AND a.CamaActu = c.CodiCama
                                    AND a.Cerrado = 2 AND a.Anulado = 2 LIMIT 1) AS ocupada
                           FROM CodiCama c
                          WHERE c.CodiInst = ? AND c.CodiServ = ? AND c.Activa = 1
                          ORDER BY c.CodiCama');
    $st->execute([CODI_INST, $codiServ]);
    $r = [];
    foreach ($st as $f) {
        $r[$f['CodiCama']] = $f['CodiCama'] . ' · ' . $f['NombCama'] . ($f['ocupada'] ? ' (ocupada)' : '');
    }
    return $r;
}

/**
 * Catálogos de los buscadores (autocompletar): tabla, columna del código, columna del nombre y filtro de activos.
 * La MISMA definición sirve para buscar (lo que ofrece la lista) y para validar en el servidor (lo que se acepta):
 * así lo que el buscador ofrece es exactamente lo que se guarda.
 * El código se compara limpio (sin espacios, tabuladores ni saltos de línea): en catálogos copiados de SIHOS
 * algunos códigos pueden traer caracteres de sobra (p. ej. "Z002\r") que la lista mostraba pero la validación
 * exacta rechazaba.
 */
const BUSCADORES = [
    'diagnosticos'   => ['CausMorb', 'CodiDiag', 'NombCaus', '(Activo = 1 OR Activo IS NULL)'],
    'procedimientos' => ['CodiProc', 'CodiProc', 'NombProc', 'Activo = 1'],
    'suministros'    => ['CodiSumi', 'CodiSumi', 'NombSumi', 'SumiActi = 1'],
];

/** Expresión SQL del código limpio (sin espacios, tabuladores ni saltos de línea). */
function buscador_codigo_sql(string $col): string
{
    return "UPPER(TRIM(REPLACE(REPLACE(REPLACE(`$col`, CHAR(13), ''), CHAR(10), ''), CHAR(9), '')))";
}

/** Busca en el catálogo por código o nombre (máximo 30): [['c' => código limpio, 'n' => nombre], ...]. */
function buscador_buscar(string $que, string $texto, array $extra = []): array
{
    $texto = trim($texto);
    if (!isset(BUSCADORES[$que]) || mb_strlen($texto) < 2) {
        return [];
    }
    [$tabla, $cod, $nom, $activo] = BUSCADORES[$que];
    $c = buscador_codigo_sql($cod);
    // $extra: [alias => columna] que se devuelven con cada fila (p. ej. unidad y vía del suministro)
    $mas = '';
    foreach ($extra as $alias => $col) {
        $mas .= ", MIN(`$col`) AS `$alias`";
    }
    $st = db()->prepare("SELECT $c AS c, MIN(`$nom`) AS n$mas FROM `$tabla`
                          WHERE $activo AND ($c LIKE ? OR `$nom` LIKE ?)
                          GROUP BY c ORDER BY (c LIKE ?) DESC, c LIMIT 30");
    $st->execute([mb_strtoupper($texto) . '%', '%' . $texto . '%', mb_strtoupper($texto) . '%']);
    return array_map(function ($f) use ($extra) {
        $r = ['c' => (string) $f['c'], 'n' => (string) $f['n']];
        foreach (array_keys($extra) as $alias) {
            $r[$alias] = (string) $f[$alias];
        }
        return $r;
    }, $st->fetchAll());
}

/** Nombre de un código activo del catálogo, o null si no existe o no está activo (misma regla que buscador_buscar). */
function buscador_nombre(string $que, ?string $codigo): ?string
{
    $codigo = strtoupper(trim(str_replace(["\r", "\n", "\t"], '', (string) $codigo)));
    if ($codigo === '' || !isset(BUSCADORES[$que])) {
        return null;
    }
    [$tabla, $cod, $nom, $activo] = BUSCADORES[$que];
    $st = db()->prepare("SELECT `$nom` FROM `$tabla` WHERE $activo AND " . buscador_codigo_sql($cod) . " = ? LIMIT 1");
    $st->execute([$codigo]);
    $n = $st->fetchColumn();
    return $n === false ? null : (string) $n;
}

/** Busca diagnosticos CIE-10 activos por codigo o nombre (maximo 30). */
function diagnosticos_buscar(string $texto): array
{
    return array_map(fn ($f) => ['CodiDiag' => $f['c'], 'NombCaus' => $f['n']], buscador_buscar('diagnosticos', $texto));
}

/** Nombre de un diagnostico, o null si el codigo no existe o esta inactivo. */
function diagnostico_nombre(?string $codigo): ?string
{
    return buscador_nombre('diagnosticos', $codigo);
}
