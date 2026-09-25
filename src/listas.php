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
];

/** Devuelve [codigo => nombre] de una lista, ordenada por nombre (se guarda en memoria por peticion). */
function lista(string $nombre): array
{
    static $cache = [];
    if (!isset($cache[$nombre])) {
        [$tabla, $cod, $nom, $where] = LISTAS[$nombre];
        $sql = "SELECT `$cod` AS c, `$nom` AS n FROM `$tabla`" . ($where ? " WHERE $where" : '') . " ORDER BY `$nom`";
        $cache[$nombre] = [];
        foreach (db()->query($sql) as $f) {
            $cache[$nombre][(string) $f['c']] = (string) $f['n'];
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

/** Busca diagnosticos CIE-10 activos por codigo o nombre (maximo 30). */
function diagnosticos_buscar(string $texto): array
{
    $texto = trim($texto);
    if (mb_strlen($texto) < 2) {
        return [];
    }
    $st = db()->prepare("SELECT CodiDiag, NombCaus FROM CausMorb
                          WHERE (Activo = 1 OR Activo IS NULL) AND (CodiDiag LIKE ? OR NombCaus LIKE ?)
                          ORDER BY (CodiDiag LIKE ?) DESC, CodiDiag LIMIT 30");
    $st->execute([$texto . '%', '%' . $texto . '%', $texto . '%']);
    return $st->fetchAll();
}

/** Nombre de un diagnostico, o null si el codigo no existe o esta inactivo. */
function diagnostico_nombre(?string $codigo): ?string
{
    if ($codigo === null || trim($codigo) === '') {
        return null;
    }
    $st = db()->prepare('SELECT NombCaus FROM CausMorb WHERE CodiDiag = ? AND (Activo = 1 OR Activo IS NULL)');
    $st->execute([strtoupper(trim($codigo))]);
    $n = $st->fetchColumn();
    return $n === false ? null : (string) $n;
}
