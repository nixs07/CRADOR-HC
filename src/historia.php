<?php
/**
 * Pestañas de la historia (fase 2, bloque 2): consultas, prescripción, órdenes médicas,
 * procedimientos, notas de enfermería, administración de medicamentos, evolución y egreso.
 *
 * Reglas (ver docs/REGLAS.md):
 *  - Tablas y columnas IGUALES a SIHOS. Se llenan todas las columnas NOT NULL sin valor por defecto.
 *  - Todo campo con código se valida contra su catálogo en el servidor.
 *  - Los consecutivos (ConsCons, ConsPres, ConsOrde, ConsHoPr, ConsHoEn, ConsHoMe, ConsEvol...) son
 *    POR ADMISIÓN: MAX + 1 dentro de la misma transacción (SELECT ... FOR UPDATE).
 *  - No se liquida: NumeLiqu, ConsDeFa y CantFact quedan en 0.
 *  - UsuaDigi / UsuaModi = login real del profesional.
 *  - Cada guardado es una transacción: entra completo o no entra.
 */

require_once __DIR__ . '/atencion.php';

// ---------------------------------------------------------------------
// Utilidades comunes
// ---------------------------------------------------------------------

/** Columnas de digitación: fecha, hora y usuario que digitó y modificó. */
function hc_digitacion(string $login): array
{
    $f = date('Y-m-d');
    $h = date('H:i:s');
    return ['FechDigi' => $f, 'HoraDigi' => $h, 'UsuaDigi' => $login,
            'FechModi' => $f, 'HoraModi' => $h, 'UsuaModi' => $login];
}

/** INSERT a partir de un arreglo [columna => valor]. Las columnas vienen del código, nunca del usuario. */
function hc_insertar(PDO $pdo, string $tabla, array $fila): void
{
    $cols = array_keys($fila);
    $sql = 'INSERT INTO `' . $tabla . '` (`' . implode('`, `', $cols) . '`) VALUES ('
         . implode(', ', array_fill(0, count($cols), '?')) . ')';
    $pdo->prepare($sql)->execute(array_values($fila));
}

/** Siguiente consecutivo por admisión (MAX + 1), bloqueando las filas de la admisión. */
function hc_siguiente(PDO $pdo, string $tabla, string $columna, string $consAdmi, string $extra = '', array $params = []): int
{
    $st = $pdo->prepare("SELECT IFNULL(MAX(`$columna`), 0) + 1 FROM `$tabla`
                          WHERE CodiInst = ? AND ConsAdmi = ? $extra FOR UPDATE");
    $st->execute(array_merge([CODI_INST, $consAdmi], $params));
    return (int) $st->fetchColumn();
}

/** Ejecuta $fn dentro de una transacción y devuelve su resultado. */
function hc_transaccion(callable $fn)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $r = $fn($pdo);
        $pdo->commit();
        return $r;
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

/** CodiModu de SIHOS de la admisión (6 Urgencias, 8 Observación, 5 Consulta Externa). */
function hc_modulo(array $a): int
{
    $m = modulo_de_servicio($a['ServEgre']);
    return $m ? (int) MODULOS_DETALLE[$m]['CodiModu'] : 0;
}

/**
 * Valida fecha y hora del formulario: formato, no futura y no anterior al ingreso.
 * Deja en $d[$cf] la fecha (Y-m-d) y en $d[$ch] la hora (H:i:s).
 */
function hc_fecha_hora(array $a, array &$d, array &$e, string $cf, string $ch, string $que): void
{
    $d[$cf] = campo($cf, 10);
    $d[$ch] = hora_valida(campo($ch, 8)) ?? campo($ch, 8);
    if (!fecha_valida($d[$cf])) {
        $e[$cf] = "Fecha de $que no válida.";
    } elseif (!hora_valida($d[$ch])) {
        $e[$ch] = "Hora de $que no válida.";
    } elseif ($d[$cf] . ' ' . $d[$ch] > date('Y-m-d H:i:s', time() + 300)) {
        $e[$cf] = "La fecha y hora de $que no puede ser futura.";
    } elseif ($d[$cf] . ' ' . $d[$ch] < $a['FechIngr'] . ' ' . $a['HoraIngr']) {
        $e[$ch] = "La $que no puede ser anterior al ingreso (" . fecha_hora($a['FechIngr'] . ' ' . $a['HoraIngr']) . ').';
    }
}

/** Diagnóstico CIE-10 del POST: vacío o un código activo de CausMorb. */
function hc_diagnostico(string $campo, bool $obligatorio, array &$e, string $etiqueta = 'el diagnóstico'): string
{
    $v = strtoupper(campo($campo, 8));
    if ($v === '') {
        if ($obligatorio) {
            $e[$campo] = "Escriba $etiqueta (CIE-10).";
        }
    } elseif (diagnostico_nombre($v) === null) {
        $e[$campo] = "El código $v no existe en el CIE-10 activo.";
    }
    return $v;
}

/** Código del POST que debe existir en una lista (catálogo). */
function hc_de_lista(string $lista, string $campo, bool $obligatorio, array &$e, string $mensaje, int $max = 3): string
{
    $v = campo($campo, $max);
    if ($v === '' && !$obligatorio) {
        return '';
    }
    if (!lista_valida($lista, $v)) {
        $e[$campo] = $mensaje;
    }
    return $v;
}

/** Nombre de un procedimiento activo (CodiProc) o null si no existe. */
function procedimiento_nombre(?string $codigo): ?string
{
    if ($codigo === null || trim($codigo) === '') {
        return null;
    }
    $st = db()->prepare('SELECT NombProc FROM CodiProc WHERE CodiProc = ? AND Activo = 1');
    $st->execute([trim($codigo)]);
    $n = $st->fetchColumn();
    return $n === false ? null : (string) $n;
}

/** Busca procedimientos activos por código o nombre (máximo 30). */
function procedimientos_buscar(string $texto): array
{
    $texto = trim($texto);
    if (mb_strlen($texto) < 2) {
        return [];
    }
    $st = db()->prepare("SELECT CodiProc AS c, NombProc AS n FROM CodiProc
                          WHERE Activo = 1 AND (CodiProc LIKE ? OR NombProc LIKE ?)
                          ORDER BY (CodiProc LIKE ?) DESC, NombProc LIMIT 30");
    $st->execute([$texto . '%', '%' . $texto . '%', $texto . '%']);
    return $st->fetchAll();
}

/** Nombre de un suministro activo (CodiSumi) o null si no existe. */
function suministro_nombre(?string $codigo): ?string
{
    if ($codigo === null || trim($codigo) === '') {
        return null;
    }
    $st = db()->prepare('SELECT NombSumi FROM CodiSumi WHERE CodiSumi = ? AND SumiActi = 1');
    $st->execute([trim($codigo)]);
    $n = $st->fetchColumn();
    return $n === false ? null : (string) $n;
}

/** Busca suministros activos por código o nombre (máximo 30). */
function suministros_buscar(string $texto): array
{
    $texto = trim($texto);
    if (mb_strlen($texto) < 2) {
        return [];
    }
    $st = db()->prepare("SELECT CodiSumi AS c, NombSumi AS n FROM CodiSumi
                          WHERE SumiActi = 1 AND (CodiSumi LIKE ? OR NombSumi LIKE ?)
                          ORDER BY (CodiSumi LIKE ?) DESC, NombSumi LIMIT 30");
    $st->execute([$texto . '%', '%' . $texto . '%', $texto . '%']);
    return $st->fetchAll();
}

/** Procedimiento del POST: vacío o un código activo de CodiProc. */
function hc_procedimiento(string $campo, bool $obligatorio, array &$e): string
{
    $v = campo($campo, 15);
    if ($v === '') {
        if ($obligatorio) {
            $e[$campo] = 'Escriba el procedimiento (código CUPS o nombre).';
        }
    } elseif (procedimiento_nombre($v) === null) {
        $e[$campo] = "El procedimiento $v no existe o no está activo.";
    }
    return $v;
}

/** Último ConsCons (consulta) de la admisión, o 0 si no hay. */
function hc_ultima_consulta(string $consAdmi): int
{
    $st = db()->prepare('SELECT IFNULL(MAX(ConsCons), 0) FROM RipsCons WHERE CodiInst = ? AND ConsAdmi = ?');
    $st->execute([CODI_INST, $consAdmi]);
    return (int) $st->fetchColumn();
}

/**
 * Signos vitales opcionales dentro de otro formulario (consulta, evolucion), como la fila de signos
 * de SIHOS. Si no se escribio ningun signo devuelve [null, []]; si hay alguno, se validan todos.
 */
function hc_signos_opcionales(): array
{
    $alguno = false;
    foreach (array_keys(SIGNOS_RANGOS) as $c) {
        if (trim((string) ($_POST[$c] ?? '')) !== '') {
            $alguno = true;
        }
    }
    if (!$alguno) {
        return [null, []];
    }
    return signos_validar(false);
}

/**
 * Diagnostico principal y relacionados con su tipo (catalogo TipoDiag), como la tabla de
 * diagnosticos de SIHOS (Principal, Rela 1..n). $campos: [campoCodigo => campoTipo], el primero es el principal.
 */
function hc_diagnosticos(array $campos, array &$d, array &$e, bool $principalObligatorio = true): void
{
    $primero = true;
    foreach ($campos as $cd => $ct) {
        $d[$cd] = hc_diagnostico($cd, $primero && $principalObligatorio, $e, $primero ? 'el diagnóstico principal' : 'el diagnóstico');
        $d[$ct] = ($d[$cd] !== '' || $primero)
            ? hc_de_lista('TipoDiag', $ct, $d[$cd] !== '', $e, 'Seleccione el tipo de diagnóstico.', 1) : '';
        if ($d[$ct] === '') {
            $d[$ct] = '0';
        }
        $primero = false;
    }
}

/** Lee filas repetidas del POST (campos con [] ), descartando las filas vacías (sin $clave). */
function hc_filas(array $campos, string $clave): array
{
    $n = is_array($_POST[$clave] ?? null) ? count($_POST[$clave]) : 0;
    $filas = [];
    for ($i = 0; $i < min($n, 30); $i++) {
        $f = [];
        foreach ($campos as $c => $max) {
            $v = $_POST[$c][$i] ?? '';
            $f[$c] = is_string($v) ? mb_substr(trim($v), 0, $max) : '';
        }
        if ($f[$clave] !== '') {
            $filas[] = $f;
        }
    }
    return $filas;
}

/** Número de una fila repetida (acepta coma decimal) o null. */
function hc_numero(string $v): ?float
{
    $v = str_replace(',', '.', trim($v));
    return ($v !== '' && is_numeric($v)) ? (float) $v : null;
}

// ---------------------------------------------------------------------
// 2. Consultas: RipsCons + Antecede + EstaGene (un solo guardado)
// ---------------------------------------------------------------------

/** Antecedentes que se preguntan: columna => [etiqueta, columna de descripción]. */
const ANTECEDENTES = [
    // En el orden de SIHOS. Planificacion y Factor de riesgo no tienen columna de descripcion.
    'MetoPlan' => ['Planificación', null],
    'Familiar' => ['Familiares', 'FamiDesc'],
    'Personal' => ['Personales', 'PersDesc'],
    'Patologi' => ['Patológicos', 'PatoDesc'],
    'Obstetri' => ['Obstétricos', 'ObstDesc'],
    'Ginecolo' => ['Ginecológicos', 'GineDesc'],
    'Quirurgi' => ['Quirúrgicos', 'QuirDesc'],
    'ToxiAler' => ['Tóxicos', 'ToxiDesc'],
    'AlerSiNo' => ['Alérgicos', 'AlerDesc'],
    'Fisiolog' => ['Fisiológicos', 'FisiDesc'],
    'Alimenta' => ['Alimentarios', 'AlimDesc'],
    'Traumati' => ['Traumáticos', 'TrauDesc'],
    'Farmacol' => ['Farmacológicos', 'FarmDesc'],
    'FactRies' => ['Factor de riesgo', null],
];

/** Sintomas de la revision por sistemas de SIHOS (1 = si, 2 = no): columna de RipsCons => etiqueta. */
const SINTOMATICOS = [
    'SintResp' => 'Sintomático respiratorio',
    'SintPiel' => 'Sintomático de piel',
    'SintNerv' => 'Sintomático nervioso periférico',
    'TubeMult' => 'Tuberculosis multidrogoresistente',
];

/** Sistemas del examen físico: columna => [etiqueta, columna de descripción]. */
const EXAMEN_SISTEMAS = [
    // En el orden y con las etiquetas de SIHOS (Torax = CardPulm, G/U = GeniUrin)
    'Cabeza'   => ['Cabeza', 'CabeDesc'],
    'Ojos'     => ['Ojos', 'OjosDesc'],
    'Oidos'    => ['Oídos', 'OidoDesc'],
    'Nariz'    => ['Nariz', 'NariDesc'],
    'Boca'     => ['Boca', 'BocaDesc'],
    'Cuello'   => ['Cuello', 'CuelDesc'],
    'CardPulm' => ['Tórax', 'CardDesc'],
    'Abdomen'  => ['Abdomen', 'AbdoDesc'],
    'GeniUrin' => ['G/U', 'GeniDesc'],
    'Ano'      => ['Ano', 'AnoDesc'],
    'Extremid' => ['Extremidades', 'ExtrDesc'],
    'Neurolog' => ['Neurológico', 'NeurDesc'],
    'OsteMusc' => ['Osteomuscular', 'OsteDesc'],
    'Piel'     => ['Piel', 'PielDesc'],
];

function consulta_validar(array $a): array
{
    $d = [];
    $e = [];
    hc_fecha_hora($a, $d, $e, 'FechCons', 'HoraCons', 'consulta');
    $d['TipoCons'] = hc_procedimiento('TipoCons', false, $e);
    $d['FinaCons'] = hc_de_lista('FinaCons', 'FinaCons', true, $e, 'Seleccione la finalidad de la consulta.', 2);
    foreach (['MotiCons', 'EnfeActu', 'ReviSist', 'ObseReco', 'LaboImag'] as $c) {
        $d[$c] = campo($c, 5000);
    }
    if ($d['MotiCons'] === '') $e['MotiCons'] = 'Escriba el motivo de consulta.';
    if ($d['EnfeActu'] === '') $e['EnfeActu'] = 'Escriba la enfermedad actual.';
    hc_diagnosticos(['CodiDiag' => 'TipoDiag', 'CodiRel1' => 'TipoDia1', 'CodiRel2' => 'TipoDia2',
                     'CodiRel3' => 'TipoDia3', 'CodiRel4' => 'TipoDia4'], $d, $e);
    // Revision por sistemas: sintomaticos (1 = si, 2 = no) y perimetros
    foreach (SINTOMATICOS as $c => $etq) {
        $d[$c] = campo($c, 1) === '1' ? 1 : 2;
    }
    foreach (['PeriAbdo' => [0, 200, 'abdominal'], 'PeriTorx' => [0, 150, 'torácico']] as $c => [$min, $max, $que]) {
        $v = campo($c, 5);
        $d[$c] = $v === '' ? null : (int) $v;
        if ($v !== '' && (!ctype_digit($v) || (int) $v > $max)) $e[$c] = "El perímetro $que debe estar entre $min y $max cm.";
    }
    // Plan de manejo: destino (catalogo DestSali; RipsCons.DestSali es numerico)
    $d['DestSali'] = hc_de_lista('DestSali', 'ConsDest', false, $e, 'Seleccione un destino válido.', 2);
    // Antecedentes: 1 = si, 2 = no refiere
    foreach (ANTECEDENTES as $c => [$etq, $desc]) {
        $d[$c] = campo($c, 1) === '1' ? 1 : 2;
        if ($desc !== null) {
            $d[$desc] = campo($desc, 2000);
            if ($d[$c] === 1 && $d[$desc] === '') $e[$desc] = "Describa los antecedentes $etq.";
        }
    }
    // Signos vitales de la consulta (opcionales): toma de SignVita ligada con ConsCons
    [$d['signos'], $es] = hc_signos_opcionales();
    $e += $es;
    // Examen físico: 1 = normal, 2 = anormal, vacío = no examinado
    $d['EstaGene'] = campo('EstaGene', 5000);
    foreach (EXAMEN_SISTEMAS as $c => [$etq, $desc]) {
        $v = campo($c, 1);
        $d[$c] = in_array($v, ['1', '2'], true) ? (int) $v : null;
        $d[$desc] = campo($desc, 2000);
        if ($d[$c] === 2 && $d[$desc] === '') $e[$desc] = "Describa el hallazgo anormal en $etq.";
    }
    return [$d, $e];
}

/** Guarda la consulta (RipsCons), los antecedentes (Antecede) y el examen físico (EstaGene). Devuelve ConsCons. */
function consulta_guardar(array $a, array $d, array $u): int
{
    return hc_transaccion(function (PDO $pdo) use ($a, $d, $u) {
        $login = $u['Login'];
        $modu = hc_modulo($a);
        $cons = hc_siguiente($pdo, 'RipsCons', 'ConsCons', $a['ConsAdmi']);
        $ahora = hc_digitacion($login);
        hc_insertar($pdo, 'RipsCons', [
            'CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'], 'ConsCons' => $cons, 'CodiModu' => $modu,
            'FechCons' => $d['FechCons'], 'HoraCons' => $d['HoraCons'], 'UsuaCons' => $login,
            'TipoCons' => $d['TipoCons'], 'FinaCons' => $d['FinaCons'],
            'MotiCons' => $d['MotiCons'], 'EnfeActu' => $d['EnfeActu'], 'ReviSist' => $d['ReviSist'],
            'TipoDiag' => (int) $d['TipoDiag'], 'TipoDia1' => (int) $d['TipoDia1'], 'TipoDia2' => (int) $d['TipoDia2'],
            'TipoDia3' => (int) $d['TipoDia3'], 'TipoDia4' => (int) $d['TipoDia4'],
            'CodiDiag' => $d['CodiDiag'], 'CodiRel1' => $d['CodiRel1'], 'CodiRel2' => $d['CodiRel2'],
            'CodiRel3' => $d['CodiRel3'], 'CodiRel4' => $d['CodiRel4'],
            'SintResp' => $d['SintResp'], 'SintPiel' => $d['SintPiel'], 'SintNerv' => $d['SintNerv'], 'TubeMult' => $d['TubeMult'],
            'PeriAbdo' => $d['PeriAbdo'], 'PeriTorx' => $d['PeriTorx'] ?? 0, 'LaboImag' => $d['LaboImag'],
            'CodiEspe' => $u['CodiEspe'] ?? null, 'ObseReco' => $d['ObseReco'],
            // La consulta queda realizada y cerrada al guardarla
            'EstaReal' => 1, 'FechCier' => $ahora['FechDigi'], 'HoraCier' => $ahora['HoraDigi'], 'UsuaCier' => $login,
            'UsuaAsis' => $login, 'EstaCarg' => 0, 'NumeLiqu' => 0, 'ConsDeFa' => 0, 'CentCost' => '',
            'DestSali' => $d['DestSali'] !== '' ? (int) $d['DestSali'] : 4,
            'ServEgre' => $a['ServEgre'], 'CodiServ' => $a['ServEgre'],
        ] + $ahora);

        $ante = hc_siguiente($pdo, 'Antecede', 'ConsAnte', $a['ConsAdmi']);
        $fila = ['CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'], 'ConsAnte' => $ante,
                 'TipoDocu' => $a['TipoDocu'], 'NumeUsua' => $a['NumeUsua'], 'CodiModu' => $modu, 'ConsCons' => $cons,
                ];
        foreach (ANTECEDENTES as $c => [, $desc]) {
            $fila[$c] = $d[$c];
            if ($desc !== null) {
                $fila[$desc] = $d[$desc];
            }
        }
        hc_insertar($pdo, 'Antecede', $fila + $ahora);

        $examen = $d['EstaGene'] !== '' || array_filter(array_keys(EXAMEN_SISTEMAS), fn ($c) => $d[$c] !== null);
        if ($examen) {
            $fila = ['CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'],
                     'ConsEsGe' => hc_siguiente($pdo, 'EstaGene', 'ConsEsGe', $a['ConsAdmi']),
                     'CodiModu' => $modu, 'ConsCons' => $cons, 'ConsHoPr' => 0,
                     'Fecha' => $d['FechCons'], 'Hora' => $d['HoraCons'], 'EstaGene' => $d['EstaGene']];
            foreach (EXAMEN_SISTEMAS as $c => [, $desc]) {
                $fila[$c] = $d[$c];
                $fila[$desc] = $d[$desc];
            }
            hc_insertar($pdo, 'EstaGene', $fila + $ahora);
        }
        if ($d['signos']) {
            signos_insertar($pdo, $a, $d['signos'] + ['FechToma' => $d['FechCons'], 'HoraToma' => $d['HoraCons']], $login, 0, $cons);
        }
        return $cons;
    });
}

/** Consultas de la admisión (más reciente arriba), con antecedentes y examen físico ligados. */
function consultas_de_admision(string $cons): array
{
    $st = db()->prepare('SELECT r.*, f.NombFina FROM RipsCons r LEFT JOIN FinaCons f ON f.CodiFina = r.FinaCons
                          WHERE r.CodiInst = ? AND r.ConsAdmi = ? ORDER BY r.FechCons DESC, r.HoraCons DESC, r.ConsCons DESC');
    $st->execute([CODI_INST, $cons]);
    $r = $st->fetchAll();
    $ante = db()->prepare('SELECT * FROM Antecede WHERE CodiInst = ? AND ConsAdmi = ? AND ConsCons = ? LIMIT 1');
    $exam = db()->prepare('SELECT * FROM EstaGene WHERE CodiInst = ? AND ConsAdmi = ? AND ConsCons = ? LIMIT 1');
    foreach ($r as &$c) {
        $ante->execute([CODI_INST, $cons, $c['ConsCons']]);
        $c['antecedentes'] = $ante->fetch() ?: null;
        $exam->execute([CODI_INST, $cons, $c['ConsCons']]);
        $c['examen'] = $exam->fetch() ?: null;
    }
    return $r;
}

// ---------------------------------------------------------------------
// 4. Prescripción: EncaPres + DetaPres
// ---------------------------------------------------------------------

/** Horas de cada unidad de tiempo (CodiTiem): 1 hora(s), 2 día(s), 3 mes(es) (según el comentario de DetaPres). */
const TIEMPO_HORAS = [1 => 1, 2 => 24, 3 => 720];

/** Campos de cada medicamento de la prescripción (se envían como arreglos: CodiSumi[] ...). */
const PRES_CAMPOS = ['CodiSumi' => 20, 'CantSumi' => 12, 'UnidMedi' => 2, 'CodiVia' => 1, 'CantFrec' => 3,
                     'TiemFrec' => 1, 'CantPeDu' => 3, 'TiemPeDu' => 1, 'PresMedi' => 1000];

function prescripcion_validar(array $a): array
{
    $d = [];
    $e = [];
    hc_fecha_hora($a, $d, $e, 'FechPres', 'HoraPres', 'prescripción');
    $d['PresSali'] = campo('PresSali', 1) === '1' ? 1 : 2;
    $d['ObseOrde'] = campo('ObseOrde', 5000);
    $d['CodiDiag'] = hc_diagnostico('PresDiag', false, $e);
    $d['CodiRel1'] = hc_diagnostico('PresRel1', false, $e);
    $d['CodiRel2'] = hc_diagnostico('PresRel2', false, $e);
    // Tipo de prescripcion: 1 = regular, 2 = control (comentario de EncaPres.TipoPres)
    $d['TipoPres'] = campo('TipoPres', 1) === '2' ? 2 : 1;
    $d['items'] = hc_filas(PRES_CAMPOS, 'CodiSumi');
    if (!$d['items']) {
        $e['CodiSumi'] = 'Agregue al menos un medicamento.';
    }
    foreach ($d['items'] as $i => &$it) {
        $n = $i + 1;
        $nombre = suministro_nombre($it['CodiSumi']);
        if ($nombre === null) {
            $e["item$n"] = "Medicamento $n: el código {$it['CodiSumi']} no existe o no está activo.";
            continue;
        }
        $it['NombSumi'] = $nombre;
        $cant = hc_numero($it['CantSumi']);
        if ($cant === null || $cant <= 0 || $cant > 99999) $e["item{$n}c"] = "Medicamento $n: escriba la dosis.";
        if (!lista_valida('UnidMedi', $it['UnidMedi'])) $e["item{$n}u"] = "Medicamento $n: seleccione la unidad.";
        if (!lista_valida('ViaAdmi', $it['CodiVia'])) $e["item{$n}v"] = "Medicamento $n: seleccione la vía.";
        $frec = (int) $it['CantFrec'];
        $dura = (int) $it['CantPeDu'];
        if ($frec < 1 || $frec > 99 || !lista_valida('CodiTiem', $it['TiemFrec'])) $e["item{$n}f"] = "Medicamento $n: escriba la frecuencia (cada cuánto).";
        if ($dura < 1 || $dura > 99 || !lista_valida('CodiTiem', $it['TiemPeDu'])) $e["item{$n}d"] = "Medicamento $n: escriba la duración.";
        // Número de dosis = duración / frecuencia (en horas); cantidad total = dosis x número de dosis
        $hf = $frec * (TIEMPO_HORAS[(int) $it['TiemFrec']] ?? 0);
        $hd = $dura * (TIEMPO_HORAS[(int) $it['TiemPeDu']] ?? 0);
        $it['NumeDosi'] = ($hf > 0 && $hd > 0) ? max(1, (int) ceil($hd / $hf)) : 1;
        $it['CantTota'] = round(($cant ?? 0) * $it['NumeDosi'], 2);
        $it['CantSumi'] = $cant ?? 0;
        if ($it['PresMedi'] === '') {
            $it['PresMedi'] = sprintf('%s: %s %s VIA %s CADA %d %s DURANTE %d %s',
                $nombre, rtrim(rtrim(number_format((float) $it['CantSumi'], 2, '.', ''), '0'), '.'),
                lista_nombre('UnidMedi', $it['UnidMedi']), lista_nombre('ViaAdmi', $it['CodiVia']),
                $frec, lista_nombre('CodiTiem', $it['TiemFrec']), $dura, lista_nombre('CodiTiem', $it['TiemPeDu']));
        }
    }
    return [$d, $e];
}

/** Guarda la prescripción. Devuelve ConsPres. */
function prescripcion_guardar(array $a, array $d, string $login): int
{
    return hc_transaccion(function (PDO $pdo) use ($a, $d, $login) {
        $modu = hc_modulo($a);
        $pres = hc_siguiente($pdo, 'EncaPres', 'ConsPres', $a['ConsAdmi']);
        $ahora = hc_digitacion($login);
        hc_insertar($pdo, 'EncaPres', [
            'CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'], 'ConsPres' => $pres, 'TipoPres' => $d['TipoPres'],
            'CodiModu' => $modu, 'CodiServ' => $a['ServEgre'], 'CentCost' => '',
            'ConsCons' => hc_ultima_consulta($a['ConsAdmi']),
            // Consecutivo global de SIHOS: en la contingencia es temporal (= ConsPres); se reasigna al cargar
            'Consecut' => $pres,
            'Fecha' => $d['FechPres'], 'Hora' => $d['HoraPres'], 'FechEntr' => $d['FechPres'],
            'ObseOrde' => $d['ObseOrde'], 'PresSali' => $d['PresSali'],
            'CodiDiag' => $d['CodiDiag'] !== '' ? $d['CodiDiag'] : ($a['DiagIngr'] ?? ''),
            'CodiRel1' => $d['CodiRel1'], 'CodiRel2' => $d['CodiRel2'], 'CodiRel3' => '', 'CodiRel4' => '', 'ImprOrde' => 0,
        ] + $ahora);
        foreach ($d['items'] as $i => $it) {
            hc_insertar($pdo, 'DetaPres', [
                'CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'], 'ConsPres' => $pres, 'Item' => $i + 1,
                'CodiModu' => $modu, 'CodiFina' => null, 'CodiSumi' => $it['CodiSumi'],
                'CantSumi' => $it['CantSumi'], 'Contenid' => 0, 'CodiVia' => (int) $it['CodiVia'],
                'HoraApli' => 0, 'HoraInic' => $d['HoraPres'],
                'CantFrec' => (int) $it['CantFrec'], 'TiemFrec' => (int) $it['TiemFrec'],
                'CantPeDu' => (int) $it['CantPeDu'], 'TiemPeDu' => (int) $it['TiemPeDu'],
                'NumeDosi' => $it['NumeDosi'], 'CantTota' => $it['CantTota'], 'CantApli' => 0,
                'CantSoli' => 0, 'CantEntr' => 0, 'CantDevo' => 0, 'PresMedi' => $it['PresMedi'], 'MediPrin' => 0,
                'CantFact' => 0, 'CodiDocu' => '', 'NumeLiqu' => 0, 'ConsDeFa' => 0, 'CodiServ' => $a['ServEgre'],
                'FechSusp' => '0000-00-00', 'HoraSusp' => '00:00:00', 'UsuaSusp' => '',
                'UnidMedi' => (int) $it['UnidMedi'],
            ] + $ahora);
        }
        return $pres;
    });
}

/** Prescripciones de la admisión (más reciente arriba) con sus medicamentos. */
function prescripciones_de_admision(string $cons): array
{
    $st = db()->prepare('SELECT * FROM EncaPres WHERE CodiInst = ? AND ConsAdmi = ? ORDER BY Fecha DESC, Hora DESC, ConsPres DESC');
    $st->execute([CODI_INST, $cons]);
    $r = $st->fetchAll();
    $det = db()->prepare('SELECT d.*, s.NombSumi FROM DetaPres d LEFT JOIN CodiSumi s ON s.CodiSumi = d.CodiSumi
                           WHERE d.CodiInst = ? AND d.ConsAdmi = ? AND d.ConsPres = ? ORDER BY d.Item');
    foreach ($r as &$p) {
        $det->execute([CODI_INST, $cons, $p['ConsPres']]);
        $p['items'] = $det->fetchAll();
    }
    return $r;
}

// ---------------------------------------------------------------------
// 5. Órdenes médicas: texto libre (EncaData/DetaData TipoObje 7) y órdenes (EncaOrde/DetaOrde)
// ---------------------------------------------------------------------

/** CodiItem de la orden médica en DetaData según el módulo: 131 Urgencias, 130 Observación (REGLAS). */
function orden_medica_item(array $a): ?int
{
    return ['urg' => 131, 'obs' => 130][modulo_de_servicio($a['ServEgre'])] ?? null;
}

function orden_medica_validar(array $a): array
{
    $d = [];
    $e = [];
    hc_fecha_hora($a, $d, $e, 'FechOrMe', 'HoraOrMe', 'orden médica');
    $d['Texto'] = campo('TextoOrden', 10000);
    if ($d['Texto'] === '') $e['TextoOrden'] = 'Escriba la orden médica.';
    if (orden_medica_item($a) === null) $e['TextoOrden'] = 'La orden médica en texto libre no aplica en este módulo.';
    return [$d, $e];
}

function orden_medica_guardar(array $a, array $d, string $login): int
{
    return hc_transaccion(function (PDO $pdo) use ($a, $d, $login) {
        $cons = hc_siguiente($pdo, 'EncaData', 'ConsData', $a['ConsAdmi'], 'AND TipoObje = 7');
        $ahora = hc_digitacion($login);
        hc_insertar($pdo, 'EncaData', [
            'CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'], 'TipoObje' => 7, 'ConsData' => $cons,
            'CodiModu' => hc_modulo($a), 'Fecha' => $d['FechOrMe'], 'Hora' => $d['HoraOrMe'], 'UsuaAsis' => $login,
        ] + $ahora);
        hc_insertar($pdo, 'DetaData', [
            'CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'], 'TipoObje' => 7, 'ConsData' => $cons,
            'CodiItem' => orden_medica_item($a), 'CodiSino' => null, 'CodiEsta' => 0, 'Texto' => $d['Texto'],
        ] + $ahora);
        return $cons;
    });
}

function ordenes_medicas_de_admision(string $cons): array
{
    $st = db()->prepare('SELECT e.*, d.Texto FROM EncaData e
                           JOIN DetaData d ON d.CodiInst = e.CodiInst AND d.ConsAdmi = e.ConsAdmi AND d.TipoObje = e.TipoObje AND d.ConsData = e.ConsData
                          WHERE e.CodiInst = ? AND e.ConsAdmi = ? AND e.TipoObje = 7
                          ORDER BY e.Fecha DESC, e.Hora DESC, e.ConsData DESC');
    $st->execute([CODI_INST, $cons]);
    return $st->fetchAll();
}

/** Campos de cada procedimiento ordenado (arreglos: OrdProc[] ...). */
const ORDEN_CAMPOS = ['OrdProc' => 15, 'OrdCant' => 5, 'OrdObse' => 70];

function ordenes_validar(array $a): array
{
    $d = [];
    $e = [];
    hc_fecha_hora($a, $d, $e, 'FechOrde', 'HoraOrde', 'orden');
    $d['ObseOrde'] = campo('ObseOrdeProc', 5000);
    $d['CodiDiag'] = hc_diagnostico('OrdeDiag', false, $e);
    foreach ([1, 2, 3, 4] as $i) {
        $d["CodiRel$i"] = hc_diagnostico("OrdeRel$i", false, $e);
    }
    // Finalidad de la orden (catalogo FinaCons, "No aplica" = 10 por defecto), autorizacion y ambulatoria
    $d['CodiFina'] = hc_de_lista('FinaCons', 'OrdeFina', false, $e, 'Seleccione una finalidad válida.', 2);
    if ($d['CodiFina'] === '') $d['CodiFina'] = '10';
    $d['Autoriza'] = campo('Autoriza', 1) === '1' ? 1 : 0;
    $d['OrdeAmbu'] = campo('OrdeAmbu', 1) === '1' ? 1 : 0;
    $d['items'] = hc_filas(ORDEN_CAMPOS, 'OrdProc');
    if (!$d['items']) {
        $e['OrdProc'] = 'Agregue al menos un procedimiento, laboratorio o imagen.';
    }
    foreach ($d['items'] as $i => &$it) {
        $n = $i + 1;
        $it['NombProc'] = procedimiento_nombre($it['OrdProc']);
        if ($it['NombProc'] === null) $e["ord$n"] = "Ítem $n: el procedimiento {$it['OrdProc']} no existe o no está activo.";
        $cant = (int) $it['OrdCant'];
        if ($cant < 1 || $cant > 999) $e["ord{$n}c"] = "Ítem $n: la cantidad debe estar entre 1 y 999.";
        $it['OrdCant'] = $cant;
    }
    return [$d, $e];
}

function ordenes_guardar(array $a, array $d, string $login): int
{
    return hc_transaccion(function (PDO $pdo) use ($a, $d, $login) {
        $modu = hc_modulo($a);
        $orde = hc_siguiente($pdo, 'EncaOrde', 'ConsOrde', $a['ConsAdmi']);
        $ahora = hc_digitacion($login);
        hc_insertar($pdo, 'EncaOrde', [
            'CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'], 'ConsOrde' => $orde, 'CodiModu' => $modu,
            'CodiServ' => $a['ServEgre'], 'ConsCons' => hc_ultima_consulta($a['ConsAdmi']),
            // Consecutivo general de SIHOS: temporal (= ConsOrde) en la contingencia; se reasigna al cargar
            'Consecut' => $orde,
            'Fecha' => $d['FechOrde'], 'Hora' => $d['HoraOrde'], 'ObseOrde' => $d['ObseOrde'],
            'CodiDiag' => $d['CodiDiag'] !== '' ? $d['CodiDiag'] : ($a['DiagIngr'] ?? ''),
            'CodiFina' => $d['CodiFina'],
            'CodiRel1' => $d['CodiRel1'], 'CodiRel2' => $d['CodiRel2'], 'CodiRel3' => $d['CodiRel3'], 'CodiRel4' => $d['CodiRel4'],
            'OrdeSali' => 0, 'OrdeAmbu' => $d['OrdeAmbu'], 'ImprOrde' => 0, 'Autoriza' => $d['Autoriza'],
        ] + $ahora);
        foreach ($d['items'] as $i => $it) {
            hc_insertar($pdo, 'DetaOrde', [
                'CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'], 'ConsOrde' => $orde, 'Item' => $i + 1,
                'CodiModu' => $modu, 'CodiProc' => $it['OrdProc'], 'CodiFina' => null,
                'CantSumi' => $it['OrdCant'], 'ObseProc' => $it['OrdObse'], 'CantReal' => 0, 'CantFact' => 0,
                'CodiDocu' => '', 'NumeLiqu' => 0, 'ConsDeFa' => 0, 'TipoHora' => '', 'CodiServ' => $a['ServEgre'],
                'CodiProf' => $login, 'FechSusp' => '0000-00-00', 'HoraSusp' => '00:00:00', 'UsuaSusp' => '',
            ] + $ahora);
        }
        return $orde;
    });
}

function ordenes_de_admision(string $cons): array
{
    $st = db()->prepare('SELECT * FROM EncaOrde WHERE CodiInst = ? AND ConsAdmi = ? ORDER BY Fecha DESC, Hora DESC, ConsOrde DESC');
    $st->execute([CODI_INST, $cons]);
    $r = $st->fetchAll();
    $det = db()->prepare('SELECT d.*, p.NombProc, f.NombFina FROM DetaOrde d
                            LEFT JOIN CodiProc p ON p.CodiProc = d.CodiProc
                            LEFT JOIN FinaProc f ON f.CodiFina = d.CodiFina
                           WHERE d.CodiInst = ? AND d.ConsAdmi = ? AND d.ConsOrde = ? ORDER BY d.Item');
    foreach ($r as &$o) {
        $det->execute([CODI_INST, $cons, $o['ConsOrde']]);
        $o['items'] = $det->fetchAll();
    }
    return $r;
}

// ---------------------------------------------------------------------
// 6. Procedimientos realizados: HojaProc
// ---------------------------------------------------------------------

/** HojaProc guarda los usuarios en varchar(8): se recorta el login a 8 caracteres. */
function login8(string $login): string
{
    return mb_substr($login, 0, 8);
}

function procedimiento_validar(array $a): array
{
    $d = [];
    $e = [];
    hc_fecha_hora($a, $d, $e, 'FechProc', 'HoraProc', 'procedimiento');
    // Ítem de orden que se atiende (opcional): el procedimiento y la finalidad salen del ítem
    $d['OrdenItem'] = campo('OrdenItem', 10);
    $d['orden'] = null;
    if ($d['OrdenItem'] !== '') {
        $pend = ordenes_pendientes($a['ConsAdmi']);
        if (!isset($pend[$d['OrdenItem']])) {
            $e['OrdenItem'] = 'El ítem de orden no existe o ya se realizó completo.';
        } else {
            $d['orden'] = $pend[$d['OrdenItem']];
            $_POST['CodiProc'] = $d['orden']['CodiProc'];
            if (campo('CodiFina', 1) === '' && $d['orden']['CodiFina'] !== null) {
                $_POST['CodiFina'] = $d['orden']['CodiFina'];
            }
        }
    }
    $d['CodiProc'] = hc_procedimiento('CodiProc', true, $e);
    $d['CodiFina'] = hc_de_lista('FinaProc', 'CodiFina', true, $e, 'Seleccione la finalidad del procedimiento.', 1);
    hc_diagnosticos(['DiagPrin' => 'ProcTipoDiag', 'DiagRela' => 'ProcTipoDiaR', 'DiagRel1' => 'ProcTipoDia1',
                     'DiagRel2' => 'ProcTipoDia2', 'DiagComp' => 'ProcTipoDiaC'], $d, $e);
    $d['IndiAdic'] = campo('IndiAdic', 5000);
    $cant = campo('CantProc', 5);
    $d['CantProc'] = $cant === '' ? 1 : (int) $cant;
    if ($cant !== '' && (!ctype_digit($cant) || (int) $cant < 1 || (int) $cant > 999)) $e['CantProc'] = 'La cantidad debe estar entre 1 y 999.';
    $d['ProcReal'] = campo('ProcReal', 1) === '1' ? 1 : 0;
    return [$d, $e];
}

function procedimiento_guardar(array $a, array $d, string $login): int
{
    return hc_transaccion(function (PDO $pdo) use ($a, $d, $login) {
        $cons = hc_siguiente($pdo, 'HojaProc', 'ConsHoPr', $a['ConsAdmi']);
        $l8 = login8($login);
        $dig = hc_digitacion($l8);
        hc_insertar($pdo, 'HojaProc', [
            'CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'], 'ConsHoPr' => $cons, 'CodiModu' => hc_modulo($a),
            'ConsCons' => min(99, hc_ultima_consulta($a['ConsAdmi'])), 'EsCrue' => 0,
            'CodiProc' => $d['CodiProc'], 'CodiFina' => $d['CodiFina'],
            'FechProc' => $d['FechProc'], 'HoraProc' => $d['HoraProc'],
            'TipoDiag' => (int) $d['ProcTipoDiag'], 'TipoDiaR' => (int) $d['ProcTipoDiaR'], 'TipoDia1' => (int) $d['ProcTipoDia1'],
            'TipoDia2' => (int) $d['ProcTipoDia2'], 'TipoDiaC' => (int) $d['ProcTipoDiaC'],
            'DiagPrin' => $d['DiagPrin'], 'DiagRela' => $d['DiagRela'], 'DiagRel1' => $d['DiagRel1'], 'DiagRel2' => $d['DiagRel2'],
            'DiagRel3' => '', 'DiagComp' => $d['DiagComp'], 'IndiAdic' => $d['IndiAdic'], 'CodiProf' => $l8, 'UsuaAsis' => $l8,
            'ProcReal' => $d['ProcReal'], 'CantProc' => $d['CantProc'], 'CantFact' => 0,
            // Orden que se atiende: NumeOrde = consecutivo general de la orden (EncaOrde.Consecut), Item = ítem
            'NumeOrde' => $d['orden'] ? (int) $d['orden']['Consecut'] : 0, 'Item' => $d['orden'] ? (int) $d['orden']['Item'] : 0,
            'CentCost' => '0', 'ServEgre' => $a['ServEgre'], 'CodiDocu' => '', 'NumeLiqu' => 0, 'ConsDeFa' => 0,
            'NumePiez' => 0, 'CuadPiez' => 0, 'CodiServ' => $a['ServEgre'],
        ] + $dig);
        if ($d['orden']) {
            $pdo->prepare('UPDATE DetaOrde SET CantReal = CantReal + 1, FechModi = CURDATE(), HoraModi = CURTIME(), UsuaModi = ?
                            WHERE CodiInst = ? AND ConsAdmi = ? AND ConsOrde = ? AND Item = ?')
                ->execute([$login, CODI_INST, $a['ConsAdmi'], $d['orden']['ConsOrde'], $d['orden']['Item']]);
        }
        return $cons;
    });
}

function procedimientos_de_admision(string $cons): array
{
    $st = db()->prepare('SELECT h.*, p.NombProc, f.NombFina FROM HojaProc h
                           LEFT JOIN CodiProc p ON p.CodiProc = h.CodiProc
                           LEFT JOIN FinaProc f ON f.CodiFina = h.CodiFina
                          WHERE h.CodiInst = ? AND h.ConsAdmi = ? ORDER BY h.FechProc DESC, h.HoraProc DESC, h.ConsHoPr DESC');
    $st->execute([CODI_INST, $cons]);
    return $st->fetchAll();
}

// ---------------------------------------------------------------------
// 7. Notas de enfermería (HojaEnfe) y administración de medicamentos (HojaMedi)
// ---------------------------------------------------------------------

/**
 * Tipo de nota (catalogo TipoNota) de cada pestana: en SIHOS "Notas Enfermeria" y "Notas Medicas" no tienen
 * selector de tipo. Se toma del catalogo por nombre; null si el catalogo no tiene ese tipo.
 */
function tipo_nota(string $pestana): ?string
{
    $buscar = ['enfermeria' => 'ENFERMER', 'medica' => 'MEDIC'][$pestana] ?? null;
    foreach (lista('TipoNota') as $c => $n) {
        if ($buscar !== null && stripos($n, $buscar) !== false) {
            return (string) $c;
        }
    }
    return null;
}

function nota_validar(array $a): array
{
    $d = [];
    $e = [];
    $d['pestana'] = campo('NotaPestana', 12) === 'medica' ? 'medica' : 'enfermeria';
    $px = $d['pestana'] === 'medica' ? 'Med' : '';
    hc_fecha_hora($a, $d, $e, 'FechNota' . $px, 'HoraNota' . $px, 'nota');
    $d['FechNota'] = $d['FechNota' . $px];
    $d['HoraNota'] = $d['HoraNota' . $px];
    $d['TipoNota'] = tipo_nota($d['pestana']);
    if ($d['TipoNota'] === null) {
        $e['NotaEnfe' . $px] = 'El catálogo TipoNota no tiene el tipo de nota de esta pestaña.';
    }
    $d['Reviza'] = $d['pestana'] === 'medica' && campo('Reviza', 1) === '1' ? 1 : 0;
    $d['NotaEnfe' . $px] = campo('NotaEnfe' . $px, 10000);
    if ($d['NotaEnfe' . $px] === '') $e['NotaEnfe' . $px] = 'Escriba la nota.';
    $d['NotaEnfe'] = $d['NotaEnfe' . $px];
    return [$d, $e];
}

/* Validacion anterior (con selector de tipo), ya no se usa */
function nota_validar_con_tipo(array $a): array
{
    $d = [];
    $e = [];
    hc_fecha_hora($a, $d, $e, 'FechNota', 'HoraNota', 'nota');
    $d['TipoNota'] = hc_de_lista('TipoNota', 'TipoNota', true, $e, 'Seleccione el tipo de nota.', 2);
    $d['NotaEnfe'] = campo('NotaEnfe', 10000);
    if ($d['NotaEnfe'] === '') $e['NotaEnfe'] = 'Escriba la nota.';
    return [$d, $e];
}

function nota_guardar(array $a, array $d, string $login): int
{
    return hc_transaccion(function (PDO $pdo) use ($a, $d, $login) {
        $cons = hc_siguiente($pdo, 'HojaEnfe', 'ConsHoEn', $a['ConsAdmi']);
        hc_insertar($pdo, 'HojaEnfe', [
            'CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'], 'TipoNota' => (int) $d['TipoNota'], 'Procedim' => '',
            'ConsHoEn' => $cons, 'CodiModu' => hc_modulo($a), 'CodiServ' => $a['ServEgre'], 'ConsCons' => 0,
            'FechNota' => $d['FechNota'], 'HoraNota' => $d['HoraNota'], 'NotaEnfe' => $d['NotaEnfe'],
            // Notas medicas: casilla "Revisada" de SIHOS
            'Reviza' => $d['Reviza'] ?? 0, 'UsuaRevi' => !empty($d['Reviza']) ? $login : '',
            'HoraRevi' => !empty($d['Reviza']) ? date('H:i:s') : '00:00:00', 'FechRevi' => !empty($d['Reviza']) ? date('Y-m-d') : '0000-00-00',
            'DeclLeid' => 0,
        ] + hc_digitacion($login));
        return $cons;
    });
}

function notas_de_admision(string $cons): array
{
    $st = db()->prepare('SELECT h.*, t.NombTipo FROM HojaEnfe h LEFT JOIN TipoNota t ON t.CodiTipo = h.TipoNota
                          WHERE h.CodiInst = ? AND h.ConsAdmi = ? ORDER BY h.FechNota DESC, h.HoraNota DESC, h.ConsHoEn DESC');
    $st->execute([CODI_INST, $cons]);
    return $st->fetchAll();
}

/** Medicamentos prescritos en la admisión que no están suspendidos: ["ConsPres-Item" => fila]. */
function medicamentos_prescritos(string $cons): array
{
    $st = db()->prepare("SELECT d.*, s.NombSumi FROM DetaPres d LEFT JOIN CodiSumi s ON s.CodiSumi = d.CodiSumi
                          WHERE d.CodiInst = ? AND d.ConsAdmi = ? AND d.FechSusp = '0000-00-00'
                          ORDER BY d.ConsPres DESC, d.Item");
    $st->execute([CODI_INST, $cons]);
    $r = [];
    foreach ($st as $f) {
        $r[$f['ConsPres'] . '-' . $f['Item']] = $f;
    }
    return $r;
}

function medicamento_validar(array $a): array
{
    $d = [];
    $e = [];
    hc_fecha_hora($a, $d, $e, 'FechMedi', 'HoraMedi', 'administración');
    $clave = campo('MediPres', 10);
    $pres = medicamentos_prescritos($a['ConsAdmi']);
    if (!isset($pres[$clave])) {
        $e['MediPres'] = 'Seleccione un medicamento prescrito.';
    } else {
        $d['det'] = $pres[$clave];
    }
    $cant = campo_numero('CantMedi');
    if ($cant === null || $cant <= 0 || $cant > 99999) $e['CantMedi'] = 'Escriba la cantidad aplicada.';
    $d['CantMedi'] = $cant ?? 0;
    $d['IndiAdic'] = campo('MediObse', 255);
    return [$d, $e];
}

/** Registra la aplicación del medicamento (HojaMedi) y suma la cantidad aplicada en DetaPres.CantApli. */
function medicamento_guardar(array $a, array $d, string $login): int
{
    return hc_transaccion(function (PDO $pdo) use ($a, $d, $login) {
        $det = $d['det'];
        $cons = hc_siguiente($pdo, 'HojaMedi', 'ConsHoMe', $a['ConsAdmi']);
        hc_insertar($pdo, 'HojaMedi', [
            'CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'], 'ConsHoMe' => $cons, 'CodiModu' => hc_modulo($a),
            'CodiServ' => $a['ServEgre'], 'FechMedi' => $d['FechMedi'], 'HoraMedi' => $d['HoraMedi'],
            'FechPlan' => $d['FechMedi'], 'HoraPlan' => $d['HoraMedi'], 'CodiMedi' => $det['CodiSumi'],
            'ViaAdmi' => (int) $det['CodiVia'], 'EstaApli' => 1, 'CantMedi' => $d['CantMedi'],
            'UnidMedi' => (int) ($det['UnidMedi'] ?: 1), 'IndiAdic' => $d['IndiAdic'], 'EsFact' => 1, 'CantFact' => 0,
            'UsuaAsis' => $login, 'NumeOrde' => (int) $det['ConsPres'], 'Item' => (int) $det['Item'],
            'CodiDocu' => '', 'NumeLiqu' => 0, 'ConsDeFa' => 0, 'ValoUnit' => 0, 'ValoTota' => 0, 'EstaFact' => 0,
            'Despacha' => 0, 'DispMedi' => 0, 'Correctos' => 0, 'CentCost' => '0',
        ] + hc_digitacion($login));
        $pdo->prepare('UPDATE DetaPres SET CantApli = CantApli + ?, FechModi = CURDATE(), HoraModi = CURTIME(), UsuaModi = ?
                        WHERE CodiInst = ? AND ConsAdmi = ? AND ConsPres = ? AND Item = ?')
            ->execute([$d['CantMedi'], $login, CODI_INST, $a['ConsAdmi'], $det['ConsPres'], $det['Item']]);
        return $cons;
    });
}

function medicamentos_aplicados(string $cons): array
{
    $st = db()->prepare('SELECT h.*, s.NombSumi FROM HojaMedi h LEFT JOIN CodiSumi s ON s.CodiSumi = h.CodiMedi
                          WHERE h.CodiInst = ? AND h.ConsAdmi = ? ORDER BY h.FechMedi DESC, h.HoraMedi DESC, h.ConsHoMe DESC');
    $st->execute([CODI_INST, $cons]);
    return $st->fetchAll();
}

// ---------------------------------------------------------------------
// 8. Evolución: EvolInte
// ---------------------------------------------------------------------

function evolucion_validar(array $a): array
{
    $d = [];
    $e = [];
    hc_fecha_hora($a, $d, $e, 'FechEvol', 'HoraEvol', 'evolución');
    foreach (['Subjetivo', 'Objetivo', 'Analisis', 'PlanMane'] as $c) {
        $d[$c] = campo($c, 10000);
    }
    if ($d['Subjetivo'] === '' && $d['Objetivo'] === '') $e['Subjetivo'] = 'Escriba lo subjetivo o lo objetivo.';
    if ($d['Analisis'] === '') $e['Analisis'] = 'Escriba el análisis.';
    if ($d['PlanMane'] === '') $e['PlanMane'] = 'Escriba el plan de manejo.';
    hc_diagnosticos(['EvolDiag' => 'EvolTipoDiag', 'EvolRel1' => 'EvolTipoRel1', 'EvolRel2' => 'EvolTipoRel2',
                     'EvolRel3' => 'EvolTipoRel3', 'EvolRel4' => 'EvolTipoRel4'], $d, $e);
    $d['CodiDiag'] = $d['EvolDiag'];
    $d['TipoDiag'] = $d['EvolTipoDiag'];
    // Signos vitales de la evolucion (opcionales): toma de SignVita ligada con ConsEvol
    [$d['signos'], $es] = hc_signos_opcionales();
    $e += $es;
    $d['CodiProc'] = hc_procedimiento('EvolProc', false, $e);
    $d['ContSign'] = campo('ContSign', 1) === '1' ? 1 : 0;
    $d['ContLiqu'] = campo('ContLiqu', 1) === '1' ? 1 : 0;
    return [$d, $e];
}

function evolucion_guardar(array $a, array $d, string $login): int
{
    return hc_transaccion(function (PDO $pdo) use ($a, $d, $login) {
        $cons = hc_siguiente($pdo, 'EvolInte', 'ConsEvol', $a['ConsAdmi']);
        hc_insertar($pdo, 'EvolInte', [
            'CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'], 'ConsEvol' => $cons, 'CodiModu' => hc_modulo($a),
            'FechEvol' => $d['FechEvol'], 'HoraEvol' => $d['HoraEvol'], 'CodiProc' => $d['CodiProc'],
            'NumeOrde' => 0, 'Item' => 0, 'CodiDocu' => '', 'NumeLiqu' => 0, 'ConsDeFa' => 0,
            'Subjetivo' => $d['Subjetivo'], 'Objetivo' => $d['Objetivo'],
            'CodiDiag' => $d['EvolDiag'], 'CodiRel1' => $d['EvolRel1'], 'CodiRel2' => $d['EvolRel2'], 'CodiRel3' => $d['EvolRel3'],
            'CodiRel4' => $d['EvolRel4'],
            'TipoDiag' => (int) $d['EvolTipoDiag'], 'TipoDiag1' => (int) $d['EvolTipoRel1'], 'TipoDiag2' => (int) $d['EvolTipoRel2'],
            'TipoDiag3' => (int) $d['EvolTipoRel3'], 'TipoDiag4' => (int) $d['EvolTipoRel4'],
            'Analisis' => $d['Analisis'], 'ContSign' => $d['ContSign'], 'ContLiqu' => $d['ContLiqu'],
            'ContRevi' => 0, 'MediRevi' => '', 'CentCost' => '', 'ServEgre' => $a['ServEgre'],
            'FechRevi' => '0000-00-00', 'HoraRevi' => '00:00:00', 'PlanMane' => $d['PlanMane'], 'CodiServ' => $a['ServEgre'],
        ] + hc_digitacion($login));
        if ($d['signos']) {
            signos_insertar($pdo, $a, $d['signos'] + ['FechToma' => $d['FechEvol'], 'HoraToma' => $d['HoraEvol']], $login, 0, 0, $cons);
        }
        return $cons;
    });
}

function evoluciones_de_admision(string $cons): array
{
    $st = db()->prepare('SELECT * FROM EvolInte WHERE CodiInst = ? AND ConsAdmi = ? ORDER BY FechEvol DESC, HoraEvol DESC, ConsEvol DESC');
    $st->execute([CODI_INST, $cons]);
    return $st->fetchAll();
}

// ---------------------------------------------------------------------
// 9. Egreso: SaliInte y cierre de la admisión
// ---------------------------------------------------------------------

/** true si el estado a la salida (EstaSali) es "muerto" (se busca por nombre en el catálogo). */
function estado_salida_muerto($codigo): bool
{
    return $codigo !== '' && stripos(lista_nombre('EstaSali', $codigo), 'MUERT') !== false;
}

function egreso_validar(array $a): array
{
    $d = [];
    $e = [];
    hc_fecha_hora($a, $d, $e, 'FechSali', 'HoraSali', 'salida');
    $ce = modulo_de_servicio($a['ServEgre']) === 'ce';
    if (campo('ConfEgre', 1) !== '1') {
        $e['ConfEgre'] = 'Marque la confirmación: al guardar, la admisión queda cerrada y ya no se puede modificar.';
    }
    if ($ce) {
        // En Consulta Externa solo se cierra la atención (ver supuestos en docs/REGLAS.md)
        return [$d + ['solo_cierre' => true], $e];
    }
    $d['CausSali'] = hc_de_lista('CausSali', 'CausSali', true, $e, 'Seleccione la causa de salida.', 1);
    $d['DestSali'] = hc_de_lista('DestSali', 'DestSali', true, $e, 'Seleccione el destino de salida.', 2);
    $d['EstaSali'] = hc_de_lista('EstaSali', 'EstaSali', true, $e, 'Seleccione el estado a la salida.', 1);
    $d['TipoEgre'] = hc_de_lista('TipoEgre', 'TipoEgre', true, $e, 'Seleccione el tipo de egreso.', 1);
    hc_diagnosticos(['DiagEgre' => 'EgreTipoDiag', 'EgreRel1' => 'EgreTipoRel1', 'EgreRel2' => 'EgreTipoRel2',
                     'EgreRel3' => 'EgreTipoRel3', 'EgreComp' => 'EgreTipoComp'], $d, $e);
    $d['TipoDiag'] = $d['EgreTipoDiag'];
    $inca = campo('DiasInca', 3);
    $d['DiasInca'] = $inca === '' ? null : (int) $inca;
    if ($inca !== '' && (!ctype_digit($inca) || (int) $inca > 99)) $e['DiasInca'] = 'Los días de incapacidad deben estar entre 0 y 99.';
    $d['ObseSali'] = campo('ObseSali', 5000);
    $d['DiagMuer'] = '';
    $d['FechMuer'] = '0000-00-00';
    $d['HoraMuer'] = '00:00:00';
    if (!isset($e['EstaSali']) && estado_salida_muerto($d['EstaSali'])) {
        // DiagMuer es varchar(4): se guarda el CIE-10 de 4 caracteres
        $d['DiagMuer'] = hc_diagnostico('DiagMuer', true, $e, 'la causa de muerte');
        if (strlen($d['DiagMuer']) > 4) $e['DiagMuer'] = 'La causa de muerte debe ser un código CIE-10 de 4 caracteres.';
        hc_fecha_hora($a, $d, $e, 'FechMuer', 'HoraMuer', 'muerte');
    }
    return [$d, $e];
}

/** Guarda el egreso (SaliInte) y cierra la admisión (Admision.Cerrado = 1). La cama queda libre al cerrar. */
function egreso_guardar(array $a, array $d, string $login): void
{
    hc_transaccion(function (PDO $pdo) use ($a, $d, $login) {
        $st = $pdo->prepare('SELECT Cerrado FROM Admision WHERE CodiInst = ? AND ConsAdmi = ? FOR UPDATE');
        $st->execute([CODI_INST, $a['ConsAdmi']]);
        if ((int) $st->fetchColumn() === 1) {
            throw new RuntimeException('La admisión ya estaba cerrada.');
        }
        if (empty($d['solo_cierre'])) {
            $seg = max(0, strtotime($d['FechSali'] . ' ' . $d['HoraSali']) - strtotime($a['FechIngr'] . ' ' . $a['HoraIngr']));
            hc_insertar($pdo, 'SaliInte', [
                'CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'], 'CodiModu' => hc_modulo($a),
                'FechSali' => $d['FechSali'], 'HoraSali' => $d['HoraSali'],
                'DiasEsta' => min(999, intdiv($seg, 86400)), 'HoraEsta' => intdiv($seg % 86400, 3600),
                'CausSali' => (int) $d['CausSali'], 'DestSali' => $d['DestSali'], 'DiasInca' => $d['DiasInca'],
                'DiagEgre' => $d['DiagEgre'], 'DiagRel1' => $d['EgreRel1'], 'DiagRel2' => $d['EgreRel2'], 'DiagRel3' => $d['EgreRel3'],
                'DiagRel4' => '', 'DiagComp' => $d['EgreComp'],
                // TipoDia4 = tipo del diagnostico de complicacion (comentario de SaliInte)
                'TipoDiag' => (int) $d['EgreTipoDiag'], 'TipoDia1' => (int) $d['EgreTipoRel1'], 'TipoDia2' => (int) $d['EgreTipoRel2'],
                'TipoDia3' => (int) $d['EgreTipoRel3'], 'TipoDia4' => (int) $d['EgreTipoComp'],
                'EstaSali' => (int) $d['EstaSali'], 'DiagMuer' => $d['DiagMuer'] !== '' ? $d['DiagMuer'] : null,
                'FechMuer' => $d['FechMuer'], 'HoraMuer' => $d['HoraMuer'], 'ObseSali' => $d['ObseSali'],
                'CodiProf' => $login, 'UnidEdad' => $a['UnidEdad'], 'ValoEdad' => (int) $a['ValoEdad'],
                'ServEgre' => $a['ServEgre'], 'CodiCama' => $a['CamaActu'] !== '' ? $a['CamaActu'] : null,
                'TipoEgre' => (int) $d['TipoEgre'],
            ] + hc_digitacion($login));
        }
        $pdo->prepare('UPDATE Admision SET Cerrado = 1, FechCier = CURDATE(), HoraCier = CURTIME(), UsuaCier = ?,
                              FechEgre = ?, HoraEgre = ?, FechModi = CURDATE(), HoraModi = CURTIME(), UsuaModi = ?
                        WHERE CodiInst = ? AND ConsAdmi = ?')
            ->execute([$login, $d['FechSali'], $d['HoraSali'], $login, CODI_INST, $a['ConsAdmi']]);
    });
}

function egreso_de_admision(string $cons): ?array
{
    $st = db()->prepare('SELECT * FROM SaliInte WHERE CodiInst = ? AND ConsAdmi = ?');
    $st->execute([CODI_INST, $cons]);
    return $st->fetch() ?: null;
}

// ---------------------------------------------------------------------
// Traslado de cama (TrasCama), solo en Observación e Internación
// ---------------------------------------------------------------------

/** Inicio del tramo actual en la cama: salida del último traslado o ingreso de la admisión. */
function traslado_inicio_tramo(string $consAdmi, array $a): array
{
    $st = db()->prepare('SELECT FechSali, HoraSali FROM TrasCama WHERE CodiInst = ? AND ConsAdmi = ? ORDER BY ConsTras DESC LIMIT 1');
    $st->execute([CODI_INST, $consAdmi]);
    $f = $st->fetch();
    return $f ? [$f['FechSali'], $f['HoraSali']] : [$a['FechIngr'], $a['HoraIngr']];
}

function traslado_validar(array $a): array
{
    $d = [];
    $e = [];
    hc_fecha_hora($a, $d, $e, 'FechTras', 'HoraTras', 'traslado');
    $mod = modulo_de_servicio($a['ServEgre']);
    $servicios = $mod ? MODULOS_DETALLE[$mod]['servicios'] : [];
    $d['ServDest'] = campo('ServDest', 3);
    if (!in_array($d['ServDest'], $servicios, true)) {
        $e['ServDest'] = 'Seleccione el servicio destino.';
    }
    $d['CamaDest'] = campo('CamaDest', 10);
    if (!isset($e['ServDest'])) {
        $camas = camas($d['ServDest']);
        if (!array_key_exists($d['CamaDest'], $camas)) {
            $e['CamaDest'] = 'Seleccione la cama destino.';
        } elseif ($d['CamaDest'] === $a['CamaActu']) {
            $e['CamaDest'] = 'La cama destino es la misma cama actual.';
        } elseif (strpos($camas[$d['CamaDest']], '(ocupada)') !== false) {
            $e['CamaDest'] = 'La cama destino está ocupada.';
        }
    }
    if (!$e) {
        [$fi, $hi] = traslado_inicio_tramo($a['ConsAdmi'], $a);
        if ($d['FechTras'] . ' ' . $d['HoraTras'] < $fi . ' ' . $hi) {
            $e['HoraTras'] = 'El traslado no puede ser anterior al último movimiento de cama (' . fecha_hora("$fi $hi") . ').';
        }
    }
    return [$d, $e];
}

/** Cierra el tramo en la cama actual (TrasCama) y mueve la admisión a la cama destino. */
function traslado_guardar(array $a, array $d, string $login): int
{
    return hc_transaccion(function (PDO $pdo) use ($a, $d, $login) {
        $cons = hc_siguiente($pdo, 'TrasCama', 'ConsTras', $a['ConsAdmi']);
        [$fi, $hi] = traslado_inicio_tramo($a['ConsAdmi'], $a);
        $seg = max(0, strtotime($d['FechTras'] . ' ' . $d['HoraTras']) - strtotime("$fi $hi"));
        hc_insertar($pdo, 'TrasCama', [
            'CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'], 'ConsTras' => $cons,
            'CodiServ' => $a['ServEgre'], 'CodiModu' => hc_modulo($a), 'CamaOrig' => (string) $a['CamaActu'],
            'ServEgre' => $d['ServDest'], 'EntoAten_id' => null, 'CamaDest' => $d['CamaDest'],
            'FechIngr' => $fi, 'HoraIngr' => $hi, 'FechSali' => $d['FechTras'], 'HoraSali' => $d['HoraTras'],
            // Dias y Horas del tramo: dias completos y horas restantes
            'Dias' => intdiv($seg, 86400), 'Horas' => intdiv($seg % 86400, 3600),
            'CoinDest' => '', 'CoadDest' => '', 'CentEgre' => '',
        ] + hc_digitacion($login));
        $pdo->prepare('UPDATE Admision SET CamaActu = ?, ServEgre = ?, FechModi = CURDATE(), HoraModi = CURTIME(), UsuaModi = ?
                        WHERE CodiInst = ? AND ConsAdmi = ?')
            ->execute([$d['CamaDest'], $d['ServDest'], $login, CODI_INST, $a['ConsAdmi']]);
        return $cons;
    });
}

function traslados_de_admision(string $cons): array
{
    $st = db()->prepare('SELECT * FROM TrasCama WHERE CodiInst = ? AND ConsAdmi = ? ORDER BY ConsTras DESC');
    $st->execute([CODI_INST, $cons]);
    return $st->fetchAll();
}

// ---------------------------------------------------------------------
// Materiales usados (HojaMate)
// ---------------------------------------------------------------------

function material_validar(array $a): array
{
    $d = [];
    $e = [];
    hc_fecha_hora($a, $d, $e, 'FechMate', 'HoraMate', 'uso del material');
    $d['CodiMate'] = campo('CodiMate', 20);
    if ($d['CodiMate'] === '' || suministro_nombre($d['CodiMate']) === null) {
        $e['CodiMate'] = 'Escriba un material o suministro activo (código o nombre).';
    }
    $d['UnidMate'] = hc_de_lista('CodiUnid', 'UnidMate', true, $e, 'Seleccione la unidad.', 2);
    $cant = campo_numero('CantMate');
    if ($cant === null || $cant <= 0 || $cant > 99999) $e['CantMate'] = 'Escriba la cantidad usada.';
    $d['CantMate'] = $cant ?? 0;
    $d['IndiAdic'] = campo('MateObse', 255);
    return [$d, $e];
}

function material_guardar(array $a, array $d, string $login): int
{
    return hc_transaccion(function (PDO $pdo) use ($a, $d, $login) {
        $cons = hc_siguiente($pdo, 'HojaMate', 'ConsHoMa', $a['ConsAdmi']);
        hc_insertar($pdo, 'HojaMate', [
            'CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'], 'ConsHoMa' => $cons, 'CodiModu' => hc_modulo($a),
            'CodiServ' => $a['ServEgre'], 'FechMate' => $d['FechMate'], 'HoraMate' => $d['HoraMate'],
            'CodiMate' => $d['CodiMate'], 'UnidMate' => $d['UnidMate'], 'EsFact' => 1, 'IndiAdic' => $d['IndiAdic'],
            'CantMate' => $d['CantMate'], 'CantFact' => 0, 'NumeOrde' => 0, 'Item' => 0, 'CentCost' => '0',
            'CodiDocu' => '', 'NumeLiqu' => 0, 'ConsDeFa' => 0, 'UsuaAsis' => $login,
        ] + hc_digitacion($login));
        return $cons;
    });
}

function materiales_de_admision(string $cons): array
{
    $st = db()->prepare('SELECT h.*, s.NombSumi FROM HojaMate h LEFT JOIN CodiSumi s ON s.CodiSumi = h.CodiMate
                          WHERE h.CodiInst = ? AND h.ConsAdmi = ? ORDER BY h.FechMate DESC, h.HoraMate DESC, h.ConsHoMa DESC');
    $st->execute([CODI_INST, $cons]);
    return $st->fetchAll();
}

// ---------------------------------------------------------------------
// Remisión (Remision) e incapacidad (IncaPaci)
// ---------------------------------------------------------------------

function remision_validar(array $a): array
{
    $d = [];
    $e = [];
    hc_fecha_hora($a, $d, $e, 'FechRemi', 'HoraRemi', 'remisión');
    $d['RemiMoti'] = hc_de_lista('MotiRemi', 'RemiMoti', true, $e, 'Seleccione el motivo de remisión.', 2);
    $d['ModaSoli'] = hc_de_lista('ModaSoli', 'ModaSoli', true, $e, 'Seleccione la modalidad de la solicitud.', 2);
    $d['EspeRemi'] = hc_de_lista('Espe', 'EspeRemi', false, $e, 'Seleccione una especialidad válida.', 3);
    $d['InstDest'] = campo('InstDest', 200);
    $d['MotiRemi'] = campo('MotiRemiTexto', 5000);
    if ($d['MotiRemi'] === '') $e['MotiRemiTexto'] = 'Describa el motivo de la remisión (resumen clínico).';
    $d['OtroMoti'] = campo('OtroMoti', 2000);
    $d['DiagRemi'] = hc_diagnostico('DiagRemi', true, $e, 'el diagnóstico de remisión');
    $d['TipoDiag'] = hc_de_lista('TipoDiag', 'RemiTipoDiag', true, $e, 'Seleccione el tipo de diagnóstico.', 1);
    $d['NombAcep'] = mb_strtoupper(campo('NombAcep', 80));
    $d['CargAcep'] = campo('CargAcep', 40);
    $d['NumeAuto'] = campo('RemiAuto', 15);
    $d['Ambulanc'] = campo('Ambulanc', 1) === '1' ? 1 : 0;
    $d['PlacAmbu'] = mb_strtoupper(campo('PlacAmbu', 10));
    if ($d['Ambulanc'] && $d['PlacAmbu'] === '') $e['PlacAmbu'] = 'Escriba la placa de la ambulancia.';
    // Fecha y hora de aceptacion (opcionales, como en SIHOS); si no se escriben y hay quien acepta, se usa la de la remision
    $d['FechAcep'] = campo('FechAcep', 10);
    $d['HoraAcep'] = hora_valida(campo('HoraAcep', 8)) ?? '';
    if ($d['FechAcep'] !== '' && !fecha_valida($d['FechAcep'])) $e['FechAcep'] = 'Fecha de aceptación no válida.';
    if ($d['FechAcep'] !== '' && $d['HoraAcep'] === '') $e['HoraAcep'] = 'Escriba la hora de aceptación.';
    return [$d, $e];
}

function remision_guardar(array $a, array $d, string $login): int
{
    return hc_transaccion(function (PDO $pdo) use ($a, $d, $login) {
        $cons = hc_siguiente($pdo, 'Remision', 'CodiRemi', $a['ConsAdmi']);
        $acepta = $d['NombAcep'] !== '';
        hc_insertar($pdo, 'Remision', [
            'CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'], 'CodiRemi' => $cons, 'ConsAuto' => 0,
            'CodiModu' => hc_modulo($a),
            // No hay catálogo local de instituciones receptoras: el nombre va al inicio del motivo escrito
            'MotiRemi' => ($d['InstDest'] !== '' ? 'INSTITUCION DESTINO: ' . mb_strtoupper($d['InstDest']) . "\n" : '') . $d['MotiRemi'],
            'EspeRemi' => $d['EspeRemi'], 'InstRemi' => '', 'NombAcep' => $d['NombAcep'], 'CargAcep' => $d['CargAcep'],
            'NumeAuto' => $d['NumeAuto'], 'Ambulanc' => $d['Ambulanc'], 'PlacAmbu' => $d['PlacAmbu'] !== '' ? $d['PlacAmbu'] : null,
            'ModaSoli' => (int) $d['ModaSoli'], 'RemiMoti' => (int) $d['RemiMoti'], 'OtroMoti' => $d['OtroMoti'],
            'FechAcep' => ($d['FechAcep'] ?? '') !== '' ? $d['FechAcep'] : ($acepta ? $d['FechRemi'] : '0000-00-00'),
            'HoraAcep' => ($d['FechAcep'] ?? '') !== '' ? $d['HoraAcep'] : ($acepta ? $d['HoraRemi'] : '00:00:00'),
            'TipoRemi' => 0, 'FechSali' => $d['FechRemi'], 'HoraSali' => $d['HoraRemi'],
            'FechLLega' => '0000-00-00', 'HoraLLega' => '00:00:00', 'FechCier' => '0000-00-00', 'HoraCier' => '00:00:00',
            'UsuaCier' => '', 'Cerrado' => 0,
            'DiagRemi' => $d['DiagRemi'], 'DiagRel1' => '', 'DiagRel2' => '', 'DiagRel3' => '', 'DiagRel4' => '', 'DiagComp' => '',
            'TipoDiag' => $d['TipoDiag'], 'TipoDia1' => '', 'TipoDia2' => '', 'TipoDia3' => '', 'TipoDia4' => '',
        ] + hc_digitacion($login));
        return $cons;
    });
}

function remisiones_de_admision(string $cons): array
{
    $st = db()->prepare('SELECT r.*, m.NombMoti, s.NombModa FROM Remision r
                           LEFT JOIN MotiRemi m ON m.CodiMoti = r.RemiMoti LEFT JOIN ModaSoli s ON s.CodiModa = r.ModaSoli
                          WHERE r.CodiInst = ? AND r.ConsAdmi = ? ORDER BY r.CodiRemi DESC');
    $st->execute([CODI_INST, $cons]);
    return $st->fetchAll();
}

function incapacidad_validar(array $a): array
{
    $d = [];
    $e = [];
    hc_fecha_hora($a, $d, $e, 'FechInca', 'HoraInca', 'incapacidad');
    $d['TipoInca'] = hc_de_lista('TipoInca', 'TipoInca', true, $e, 'Seleccione el tipo de incapacidad.', 1);
    $d['OrigInca'] = campo('OrigInca', 1);
    if (!in_array($d['OrigInca'], ['1', '2'], true)) $e['OrigInca'] = 'Seleccione el origen (común o laboral).';
    $dias = campo('DiasIncaPaci', 4);
    if (!ctype_digit($dias) || (int) $dias < 1 || (int) $dias > 540) $e['DiasIncaPaci'] = 'Los días de incapacidad deben estar entre 1 y 540.';
    $d['DiasInca'] = (int) $dias;
    $d['ObseInca'] = campo('ObseInca', 5000);
    return [$d, $e];
}

function incapacidad_guardar(array $a, array $d, string $login): int
{
    return hc_transaccion(function (PDO $pdo) use ($a, $d, $login) {
        $cons = hc_siguiente($pdo, 'IncaPaci', 'ConsInca', $a['ConsAdmi']);
        hc_insertar($pdo, 'IncaPaci', [
            'CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'], 'ConsInca' => $cons, 'CodiModu' => hc_modulo($a),
            'FechInca' => $d['FechInca'], 'HoraInca' => $d['HoraInca'], 'TipoInca' => (int) $d['TipoInca'],
            'OrigInca' => (int) $d['OrigInca'], 'DiasInca' => $d['DiasInca'], 'ObseInca' => $d['ObseInca'],
            'TipoAlca' => 0, 'EmbaMult' => 0, 'FePoPart' => '0000-00-00', 'EdadGest' => 0,
        ] + hc_digitacion($login));
        return $cons;
    });
}

function incapacidades_de_admision(string $cons): array
{
    $st = db()->prepare('SELECT i.*, t.NombTipo FROM IncaPaci i LEFT JOIN TipoInca t ON t.CodiTipo = i.TipoInca
                          WHERE i.CodiInst = ? AND i.ConsAdmi = ? ORDER BY i.ConsInca DESC');
    $st->execute([CODI_INST, $cons]);
    return $st->fetchAll();
}

// ---------------------------------------------------------------------
// Ítems de orden pendientes (para ligar un procedimiento realizado a su orden)
// ---------------------------------------------------------------------

/** Ítems de DetaOrde con cantidad pendiente (CantReal < CantSumi), no suspendidos: ["ConsOrde-Item" => fila]. */
function ordenes_pendientes(string $cons): array
{
    $st = db()->prepare("SELECT d.*, e.Consecut, p.NombProc FROM DetaOrde d
                           JOIN EncaOrde e ON e.CodiInst = d.CodiInst AND e.ConsAdmi = d.ConsAdmi AND e.ConsOrde = d.ConsOrde
                           LEFT JOIN CodiProc p ON p.CodiProc = d.CodiProc
                          WHERE d.CodiInst = ? AND d.ConsAdmi = ? AND d.CantReal < d.CantSumi AND d.FechSusp = '0000-00-00'
                          ORDER BY d.ConsOrde, d.Item");
    $st->execute([CODI_INST, $cons]);
    $r = [];
    foreach ($st as $f) {
        $r[$f['ConsOrde'] . '-' . $f['Item']] = $f;
    }
    return $r;
}
