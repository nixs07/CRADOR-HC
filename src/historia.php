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

/**
 * Antecedentes en el orden de SIHOS (docs/RECORRIDO_SIHOS.md §3): columna => [etiqueta, columna de descripción,
 * módulos donde aparece]. Andrológicos y Conciliación medicamentosa no están en Consulta Externa.
 */
const ANTECEDENTES = [
    'MetoPlan' => ['Planificación', null, 'todos'],
    'Familiar' => ['Familiares', 'FamiDesc', 'todos'],
    'Personal' => ['Personales', 'PersDesc', 'todos'],
    'Patologi' => ['Patológicos', 'PatoDesc', 'todos'],
    'Obstetri' => ['Obstétricos', 'ObstDesc', 'todos'],
    'Ginecolo' => ['Ginecológicos', 'GineDesc', 'todos'],
    'Quirurgi' => ['Quirúrgicos', 'QuirDesc', 'todos'],
    'ToxiAler' => ['Tóxicos', 'ToxiDesc', 'todos'],
    'AlerSiNo' => ['Alérgicos', 'AlerDesc', 'todos'],
    'Fisiolog' => ['Fisiológicos', 'FisiDesc', 'todos'],
    'Alimenta' => ['Alimentarios', 'AlimDesc', 'todos'],
    'Traumati' => ['Traumáticos', 'TrauDesc', 'todos'],
    'Farmacol' => ['Farmacológicos', 'FarmDesc', 'todos'],
    'Andropo'  => ['Andrológicos', 'AndroDesc', 'hosp'],
    'Consilia' => ['Conciliación medicamentosa', 'ConsiDesc', 'hosp'],
    'FactRies' => ['Factor riesgo', null, 'todos'],
];

/**
 * Opciones de cada antecedente como SIHOS: Si | No | No Sabe | No Corresponde. 1 = sí y 2 = no son los de siempre;
 * **3 = No Sabe y 4 = No Corresponde son un supuesto pendiente de confirmar** (docs/consultas_sihos.sql).
 */
const ANTE_OPCIONES = ['1' => 'Si', '2' => 'No', '3' => 'No Sabe', '4' => 'No Corresponde'];

/** Antecedentes que se muestran en el módulo. */
function antecedentes_modulo(bool $ce): array
{
    return array_filter(ANTECEDENTES, fn ($x) => $x[2] === 'todos' || !$ce);
}

/** Sintomas de la revision por sistemas de SIHOS (1 = si, 2 = no): columna de RipsCons => etiqueta. */
const SINTOMATICOS = [
    'SintResp' => 'Sintomático Respiratorio',
    'SintPiel' => 'Sintomático de Piel',
    'SintNerv' => 'Sintomático Nervioso Periférico',
];

/** Sistemas del examen físico: columna => [etiqueta, columna de descripción]. Orden de Urgencias/Observación. */
const EXAMEN_SISTEMAS = [
    'Cabeza'   => ['Cabeza', 'CabeDesc'],
    'Cuello'   => ['Cuello', 'CuelDesc'],
    'CardPulm' => ['Tórax', 'CardDesc'],
    'Abdomen'  => ['Abdomen', 'AbdoDesc'],
    'GeniUrin' => ['G/U', 'GeniDesc'],
    'Extremid' => ['Extremidades', 'ExtrDesc'],
    'Neurolog' => ['Neurológico', 'NeurDesc'],
    'Nariz'    => ['Nariz', 'NariDesc'],
    'Oidos'    => ['Oídos', 'OidoDesc'],
    'Boca'     => ['Boca', 'BocaDesc'],
    'Ojos'     => ['Ojos', 'OjosDesc'],
    'Piel'     => ['Piel', 'PielDesc'],
    'Ano'      => ['Ano', 'AnoDesc'],
    'OsteMusc' => ['Osteomuscular', 'OsteDesc'],
];

/** Orden de los sistemas en Consulta Externa (distinto al de Urgencias, RECORRIDO §5). */
const EXAMEN_ORDEN_CE = ['Cabeza', 'Ojos', 'Oidos', 'Nariz', 'Boca', 'Cuello', 'CardPulm', 'Abdomen', 'GeniUrin', 'Ano',
                         'Extremid', 'Neurolog', 'OsteMusc', 'Piel'];

/** Sistemas en el orden del módulo. */
function examen_sistemas(bool $ce): array
{
    if (!$ce) {
        return EXAMEN_SISTEMAS;
    }
    $r = [];
    foreach (EXAMEN_ORDEN_CE as $c) {
        $r[$c] = EXAMEN_SISTEMAS[$c];
    }
    return $r;
}

/**
 * Opciones de cada sistema como SIHOS: Normal | Anormal | No se Explora (por defecto Normal).
 * 1 = normal, 2 = anormal; **No se Explora = NULL es un supuesto pendiente de confirmar**.
 */
const EXAMEN_OPCIONES = ['1' => 'Normal', '2' => 'Anormal', '' => 'No se Explora'];

/**
 * Secciones de la consulta. En Urgencias/Observación son acordeones con su propio Guardar; en Consulta Externa,
 * pestañas con su propio Guardar. Todas envían el mismo formulario: el botón dice qué sección se guarda y
 * "cerrar" (Cerrar Consulta, solo Urgencias/Observación) guarda y cierra la consulta.
 */
const CONSULTA_BOTONES = ['anamnesis', 'antecedentes', 'revision', 'laboratorios', 'plan', 'cerrar'];

/** Consulta abierta de la admisión que el formulario está editando (null = nueva). */
function consulta_editable(array $a, int $consCons): ?array
{
    if ($consCons <= 0) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM RipsCons WHERE CodiInst = ? AND ConsAdmi = ? AND ConsCons = ?');
    $st->execute([CODI_INST, $a['ConsAdmi'], $consCons]);
    $c = $st->fetch();
    if (!$c) {
        return null;
    }
    // En Urgencias/Observación una consulta cerrada ya no se edita
    $ce = modulo_de_servicio($a['ServEgre']) === 'ce';
    return ($ce || $c['FechCier'] === '0000-00-00' || $c['FechCier'] === null) ? $c : null;
}

function consulta_validar(array $a): array
{
    $d = [];
    $e = [];
    $ce = modulo_de_servicio($a['ServEgre']) === 'ce';
    $d['ce'] = $ce;
    $d['boton'] = in_array($_POST['boton'] ?? '', CONSULTA_BOTONES, true) ? $_POST['boton'] : 'anamnesis';
    if ($ce && $d['boton'] === 'cerrar') {
        $d['boton'] = 'plan';   // En Consulta Externa la consulta no se cierra (verificado en SIHOS)
    }
    $d['editar'] = consulta_editable($a, (int) campo('ConsConsEdit', 6));
    $completa = $d['boton'] === 'cerrar';
    hc_fecha_hora($a, $d, $e, 'FechCons', 'HoraCons', 'consulta');
    // Anamnesis (siempre): TipoCons obligatorio (verificado), finalidad, motivo y enfermedad actual
    $d['TipoCons'] = hc_procedimiento('TipoCons', true, $e);
    $d['FinaCons'] = hc_de_lista('FinaCons', 'FinaCons', true, $e, 'Seleccione la finalidad de la consulta.', 2);
    foreach (['MotiCons', 'EnfeActu', 'ReviSist', 'ObseReco', 'LaboImag'] as $c) {
        $d[$c] = campo($c, 5000);
    }
    if ($d['MotiCons'] === '') $e['MotiCons'] = 'Escriba el motivo de consulta.';
    if ($d['EnfeActu'] === '') $e['EnfeActu'] = 'Escriba la enfermedad actual.';
    // Diagnóstico principal: obligatorio al guardar Laboratorios y Diagnósticos, el Plan o al cerrar
    $conDx = in_array($d['boton'], ['laboratorios', 'plan', 'cerrar'], true) || campo('CodiDiag', 8) !== '';
    hc_diagnosticos(['CodiDiag' => 'TipoDiag', 'CodiRel1' => 'TipoDia1', 'CodiRel2' => 'TipoDia2',
                     'CodiRel3' => 'TipoDia3', 'CodiRel4' => 'TipoDia4'], $d, $e, $conDx);
    // Plan de manejo: obligatorio en Urgencias y Observación (100 % en SIHOS) al guardar el Plan o al cerrar
    if (!$ce && in_array($d['boton'], ['plan', 'cerrar'], true) && $d['ObseReco'] === '') {
        $e['ObseReco'] = 'Escriba el plan de manejo y recomendaciones.';
    }
    // Revision por sistemas: sintomaticos (1 = si, 2 = no) y perimetros
    foreach (SINTOMATICOS as $c => $etq) {
        $d[$c] = campo($c, 1) === '1' ? 1 : 2;
    }
    foreach (['PeriAbdo' => [0, 200, 'abdominal'], 'PeriTorx' => [0, 150, 'torácico']] as $c => [$min, $max, $que]) {
        $v = campo($c, 5);
        $d[$c] = $v === '' ? null : (int) $v;
        if ($v !== '' && (!ctype_digit($v) || (int) $v > $max)) $e[$c] = "El perímetro $que debe estar entre $min y $max cm.";
    }
    // Consulta Externa: Código Dorado de la pestaña 7 (solo los textos; el resto no tiene catálogo local)
    $d['Especif'] = campo('Especif', 5000);
    $d['ObserCd'] = campo('ObserCd', 5000);
    // Destino (catalogo DestSali; en Consulta Externa siempre 4)
    $d['DestSali'] = $ce ? '' : hc_de_lista('DestSali', 'ConsDest', false, $e, 'Seleccione un destino válido.', 2);
    // Antecedentes: Si | No | No Sabe | No Corresponde (ver ANTE_OPCIONES)
    foreach (antecedentes_modulo($ce) as $c => [$etq, $desc]) {
        $v = campo($c, 1);
        $d[$c] = isset(ANTE_OPCIONES[$v]) ? (int) $v : 2;
        if ($desc !== null) {
            $d[$desc] = campo($desc, 2000);
            if ($d[$c] === 1 && $d[$desc] === '') $e[$desc] = "Describa los antecedentes $etq.";
        }
    }
    // Consulta Externa: FUR y Fecha Probable del Parto en Obstétricos (Antecede.FechRegl / FechPart)
    foreach (['FechRegl' => 'FUR', 'FechPart' => 'Fecha probable del parto'] as $c => $etq) {
        $v = $ce ? campo($c, 10) : '';
        $d[$c] = $v === '' ? '0000-00-00' : $v;
        if ($v !== '' && !fecha_valida($v)) $e[$c] = "$etq no válida.";
    }
    // Signos vitales de la consulta (opcionales) y, en Consulta Externa, el índice cintura-cadera
    [$d['signos'], $es] = hc_signos_opcionales();
    $e += $es;
    $d['PeriCint'] = hc_numero((string) campo('PeriCint', 6));
    $d['PeriCade'] = hc_numero((string) campo('PeriCade', 6));
    foreach (['PeriCint' => 'cintura', 'PeriCade' => 'cadera'] as $c => $que) {
        if ($d[$c] !== null && ($d[$c] < 0 || $d[$c] > 999.99)) $e[$c] = "El perímetro de $que no cabe en la columna (0 a 999.99).";
    }
    if (($d['PeriCint'] || $d['PeriCade']) && !$d['signos']) {
        $d['signos'] = array_fill_keys(array_keys(SIGNOS_RANGOS), 0);
    }
    // Examen físico: Normal (1, por defecto) | Anormal (2, con descripción) | No se Explora (NULL)
    $d['EstaGene'] = campo('EstaGene', 5000);
    foreach (EXAMEN_SISTEMAS as $c => [$etq, $desc]) {
        $v = $_POST[$c] ?? '1';
        $d[$c] = $v === '2' ? 2 : ($v === '' ? null : 1);
        $d[$desc] = campo($desc, 2000);
        if ($d[$c] === 2 && $d[$desc] === '') $e[$desc] = "Describa el hallazgo anormal en $etq.";
    }
    return [$d, $e];
}

/**
 * Guarda la consulta (RipsCons), sus antecedentes (Antecede), su examen (EstaGene) y su toma de signos (SignVita).
 * Si el formulario edita una consulta abierta, la actualiza; si no, la crea. "Cerrar Consulta" (Urgencias y
 * Observación) llena FechCier/HoraCier/UsuaCier. En Consulta Externa queda realizada sin cierre (verificado).
 * Devuelve ConsCons.
 */
function consulta_guardar(array $a, array $d, array $u): int
{
    return hc_transaccion(function (PDO $pdo) use ($a, $d, $u) {
        $login = $u['Login'];
        $modu = hc_modulo($a);
        $ahora = hc_digitacion($login);
        $edit = $d['editar'];
        $cons = $edit ? (int) $edit['ConsCons'] : hc_siguiente($pdo, 'RipsCons', 'ConsCons', $a['ConsAdmi']);
        $cerrar = $d['boton'] === 'cerrar';
        $fila = [
            'FechCons' => $d['FechCons'], 'HoraCons' => $d['HoraCons'],
            'TipoCons' => $d['TipoCons'], 'FinaCons' => $d['FinaCons'],
            'MotiCons' => $d['MotiCons'], 'EnfeActu' => $d['EnfeActu'], 'ReviSist' => $d['ReviSist'],
            'TipoDiag' => (int) $d['TipoDiag'], 'TipoDia1' => (int) $d['TipoDia1'], 'TipoDia2' => (int) $d['TipoDia2'],
            'TipoDia3' => (int) $d['TipoDia3'], 'TipoDia4' => (int) $d['TipoDia4'],
            'CodiDiag' => $d['CodiDiag'], 'CodiRel1' => $d['CodiRel1'], 'CodiRel2' => $d['CodiRel2'],
            'CodiRel3' => $d['CodiRel3'], 'CodiRel4' => $d['CodiRel4'],
            'SintResp' => $d['SintResp'], 'SintPiel' => $d['SintPiel'], 'SintNerv' => $d['SintNerv'],
            'PeriAbdo' => $d['PeriAbdo'], 'PeriTorx' => $d['PeriTorx'] ?? 0, 'LaboImag' => $d['LaboImag'],
            'ObseReco' => $d['ObseReco'], 'DestSali' => $d['DestSali'] !== '' ? (int) $d['DestSali'] : 4,
        ];
        if ($d['ce']) {
            $fila += ['Especif' => $d['Especif'] !== '' ? $d['Especif'] : null, 'ObserCd' => $d['ObserCd'] !== '' ? $d['ObserCd'] : null];
        }
        if ($cerrar) {
            $fila += ['FechCier' => $ahora['FechDigi'], 'HoraCier' => $ahora['HoraDigi'], 'UsuaCier' => $login];
        }
        if ($edit) {
            hc_actualizar($pdo, 'RipsCons', $fila + ['FechModi' => $ahora['FechModi'], 'HoraModi' => $ahora['HoraModi'], 'UsuaModi' => $login],
                          ['ConsCons' => $cons], $a);
        } else {
            hc_insertar($pdo, 'RipsCons', array_merge([
                'CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'], 'ConsCons' => $cons, 'CodiModu' => $modu,
                'UsuaCons' => $login, 'TubeMult' => 2, 'CodiEspe' => $u['CodiEspe'] ?? null,
                // Realizada al guardar; el cierre es aparte ("Cerrar Consulta"); en Consulta Externa nunca se cierra
                'EstaReal' => 1, 'FechCier' => '0000-00-00', 'HoraCier' => '00:00:00', 'UsuaCier' => '',
                'UsuaAsis' => $login, 'EstaCarg' => 0, 'NumeLiqu' => 0, 'ConsDeFa' => 0, 'CentCost' => '',
                'ServEgre' => $a['ServEgre'], 'CodiServ' => $a['ServEgre'],
            ], $fila) + $ahora);
        }

        // Antecedentes: una fila por consulta (ConsCons)
        $ante = ['TipoDocu' => $a['TipoDocu'], 'NumeUsua' => $a['NumeUsua'], 'CodiModu' => $modu,
                 'FechRegl' => $d['FechRegl'], 'FechPart' => $d['FechPart']];
        foreach (antecedentes_modulo($d['ce']) as $c => [, $desc]) {
            $ante[$c] = $d[$c];
            if ($desc !== null) {
                $ante[$desc] = $d[$desc];
            }
        }
        $st = $pdo->prepare('SELECT ConsAnte FROM Antecede WHERE CodiInst = ? AND ConsAdmi = ? AND ConsCons = ? LIMIT 1');
        $st->execute([CODI_INST, $a['ConsAdmi'], $cons]);
        $consAnte = $st->fetchColumn();
        if ($consAnte !== false) {
            hc_actualizar($pdo, 'Antecede', $ante + ['FechModi' => $ahora['FechModi'], 'HoraModi' => $ahora['HoraModi'], 'UsuaModi' => $login],
                          ['ConsAnte' => (int) $consAnte], $a);
        } else {
            hc_insertar($pdo, 'Antecede', ['CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'],
                'ConsAnte' => hc_siguiente($pdo, 'Antecede', 'ConsAnte', $a['ConsAdmi']), 'ConsCons' => $cons] + $ante + $ahora);
        }

        // Examen fisico (EstaGene), ligado por ConsCons
        $exam = ['CodiModu' => $modu, 'ConsHoPr' => 0, 'Fecha' => $d['FechCons'], 'Hora' => $d['HoraCons'], 'EstaGene' => $d['EstaGene']];
        foreach (EXAMEN_SISTEMAS as $c => [, $desc]) {
            $exam[$c] = $d[$c];
            $exam[$desc] = $d[$desc];
        }
        $st = $pdo->prepare('SELECT ConsEsGe FROM EstaGene WHERE CodiInst = ? AND ConsAdmi = ? AND ConsCons = ? LIMIT 1');
        $st->execute([CODI_INST, $a['ConsAdmi'], $cons]);
        $consEsGe = $st->fetchColumn();
        if ($consEsGe !== false) {
            hc_actualizar($pdo, 'EstaGene', $exam + ['FechModi' => $ahora['FechModi'], 'HoraModi' => $ahora['HoraModi'], 'UsuaModi' => $login],
                          ['ConsEsGe' => (int) $consEsGe], $a);
        } else {
            hc_insertar($pdo, 'EstaGene', ['CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi'],
                'ConsEsGe' => hc_siguiente($pdo, 'EstaGene', 'ConsEsGe', $a['ConsAdmi']), 'ConsCons' => $cons] + $exam + $ahora);
        }

        // Signos de la consulta: la toma ligada con ConsCons se reemplaza si ya existe
        if ($d['signos']) {
            $pdo->prepare('DELETE FROM SignVita WHERE CodiInst = ? AND ConsAdmi = ? AND ConsCons = ?')
                ->execute([CODI_INST, $a['ConsAdmi'], $cons]);
            $s = $d['signos'] + ['FechToma' => $d['FechCons'], 'HoraToma' => $d['HoraCons']];
            $n = signos_insertar($pdo, $a, $s, $login, 0, $cons);
            if ($d['PeriCint'] || $d['PeriCade']) {
                $icc = ($d['PeriCint'] && $d['PeriCade']) ? round($d['PeriCint'] / $d['PeriCade'], 2) : 0;
                $pdo->prepare('UPDATE SignVita SET PeriCint = ?, PeriCade = ?, ResCXC = ? WHERE CodiInst = ? AND ConsAdmi = ? AND ConsSign = ?')
                    ->execute([$d['PeriCint'] ?? 0, $d['PeriCade'] ?? 0, $icc, CODI_INST, $a['ConsAdmi'], $n]);
            }
        }
        return $cons;
    });
}

/** Datos de una consulta guardada para volver a mostrarla en el formulario (editar una consulta abierta). */
function consulta_a_datos(array $c): array
{
    $d = $c;
    $d['ConsDest'] = sprintf('%02d', (int) $c['DestSali']);
    foreach ($c['antecedentes'] ?? [] as $k => $v) {
        $d[$k] = $v;
    }
    foreach ($c['examen'] ?? [] as $k => $v) {
        if ($k !== 'EstaGene' || !isset($d['EstaGene'])) {
            $d[$k] = $v;
        }
    }
    $d['EstaGene'] = $c['examen']['EstaGene'] ?? '';
    foreach ($c['signos'] ?? [] as $k => $v) {
        $d[$k] = $v;
    }
    foreach (['FechRegl', 'FechPart'] as $k) {
        if (($d[$k] ?? '') === '0000-00-00') $d[$k] = '';
    }
    $d['HoraCons'] = substr((string) $c['HoraCons'], 0, 5);
    return $d;
}

/** UPDATE de una fila de la admisión: $fila [columna => valor] (columnas del código), $llave [columna => valor]. */
function hc_actualizar(PDO $pdo, string $tabla, array $fila, array $llave, array $a): void
{
    $sql = 'UPDATE `' . $tabla . '` SET ' . implode(', ', array_map(fn ($c) => "`$c` = ?", array_keys($fila)))
         . ' WHERE CodiInst = ? AND ConsAdmi = ? AND ' . implode(' AND ', array_map(fn ($c) => "`$c` = ?", array_keys($llave)));
    $pdo->prepare($sql)->execute(array_merge(array_values($fila), [CODI_INST, $a['ConsAdmi']], array_values($llave)));
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
    $sign = db()->prepare('SELECT Peso, Talla, Pulso, Respirac, Temperat, PANume, PADeno, FetoCard, Saturaci, Oximetria, GlucMetr,
                                  PeriCint, PeriCade, ResCXC
                             FROM SignVita WHERE CodiInst = ? AND ConsAdmi = ? AND ConsCons = ? ORDER BY ConsSign DESC LIMIT 1');
    foreach ($r as &$c) {
        $ante->execute([CODI_INST, $cons, $c['ConsCons']]);
        $c['antecedentes'] = $ante->fetch() ?: null;
        $exam->execute([CODI_INST, $cons, $c['ConsCons']]);
        $c['examen'] = $exam->fetch() ?: null;
        $sign->execute([CODI_INST, $cons, $c['ConsCons']]);
        $c['signos'] = $sign->fetch() ?: null;
    }
    return $r;
}

// ---------------------------------------------------------------------
// Plan de Manejo (Urgencias 20, Observación 25): RipsCons.ObseReco de una consulta ya guardada
// ---------------------------------------------------------------------

function plan_validar(array $a): array
{
    // Como SIHOS, sin selector: el plan es el de la consulta más reciente de la admisión
    $d = [];
    $e = [];
    $d['ConsCons'] = hc_ultima_consulta($a['ConsAdmi']);
    if ($d['ConsCons'] === 0) {
        $e['ObseRecoPlan'] = 'Registre primero la consulta.';
    }
    $d['ObseReco'] = campo('ObseRecoPlan', 5000);
    if ($d['ObseReco'] === '') $e['ObseRecoPlan'] = 'Escriba las recomendaciones y el plan de manejo.';
    $d['DestSali'] = hc_de_lista('DestSali', 'PlanDest', false, $e, 'Seleccione un destino válido.', 2);
    // Código Dorado: solo los textos (Acciones inmediatas, Continuidad y Estado no tienen catálogo local)
    $d['Especif'] = campo('Especif', 5000);
    $d['ObserCd'] = campo('ObserCd', 5000);
    return [$d, $e];
}

/** Actualiza el plan de manejo (ObseReco, DestSali, Especif, ObserCd) de la consulta. Devuelve ConsCons. */
function plan_guardar(array $a, array $d, string $login): int
{
    return hc_transaccion(function (PDO $pdo) use ($a, $d, $login) {
        hc_actualizar($pdo, 'RipsCons', ['ObseReco' => $d['ObseReco'], 'DestSali' => $d['DestSali'] !== '' ? (int) $d['DestSali'] : 4,
            'Especif' => $d['Especif'] !== '' ? $d['Especif'] : null, 'ObserCd' => $d['ObserCd'] !== '' ? $d['ObserCd'] : null,
            'FechModi' => date('Y-m-d'), 'HoraModi' => date('H:i:s'), 'UsuaModi' => $login], ['ConsCons' => $d['ConsCons']], $a);
        return $d['ConsCons'];
    });
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
    // PresSali (verificado en SIHOS): 1 = hospitalaria, 2 = fórmula de salida. En Consulta Externa siempre 2
    $d['PresSali'] = (modulo_de_servicio($a['ServEgre']) === 'ce' || campo('PresSali', 1) === '2') ? 2 : 1;
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
    $d['Autoriza'] = 0;   // Verificado en SIHOS: siempre 0 (igual que OrdeSali)
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
            // Orden que se atiende: NumeOrde = número de la orden DENTRO de la admisión (EncaOrde.ConsOrde,
            // verificado contra SIHOS; no es EncaOrde.Consecut), Item = ítem de DetaOrde
            'NumeOrde' => $d['orden'] ? (int) $d['orden']['ConsOrde'] : 0, 'Item' => $d['orden'] ? (int) $d['orden']['Item'] : 0,
            'CentCost' => '', 'ServEgre' => $a['ServEgre'], 'CodiDocu' => '', 'NumeLiqu' => 0, 'ConsDeFa' => 0,
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
 * selector de tipo: el tipo sale de la pestana.
 */
function tipo_nota(string $pestana): string
{
    // Codigos fijos de SIHOS (verificado): 1 = nota de enfermeria, 2 = nota medica, 5 = consentimiento
    return $pestana === 'medica' ? '2' : '1';
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
    $d['Reviza'] = $d['pestana'] === 'medica' && campo('Reviza', 1) === '1' ? 1 : 0;
    $d['NotaEnfe' . $px] = campo('NotaEnfe' . $px, 10000);
    if ($d['NotaEnfe' . $px] === '') $e['NotaEnfe' . $px] = 'Escriba la nota.';
    $d['NotaEnfe'] = $d['NotaEnfe' . $px];
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
            'Despacha' => 0, 'DispMedi' => 0, 'Correctos' => 0, 'CentCost' => '',
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
    // Como SIHOS: Estado, Causa, Destino (sin "Tipo de egreso": SaliInte.TipoEgre queda con su valor por defecto 0)
    $d['EstaSali'] = hc_de_lista('EstaSali', 'EstaSali', true, $e, 'Seleccione el estado a la salida.', 1);
    $d['CausSali'] = hc_de_lista('CausSali', 'CausSali', true, $e, 'Seleccione la causa de salida.', 1);
    $d['DestSali'] = hc_de_lista('DestSali', 'DestSali', true, $e, 'Seleccione el destino de salida.', 2);
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

/**
 * Guarda (o modifica, si ya existe) el egreso SaliInte. Como en SIHOS, Guardar/Modificar NO cierran la historia:
 * se cierra aparte con "Cerrar Historia" (cierre_guardar). Devuelve 1.
 */
function egreso_guardar(array $a, array $d, string $login): int
{
    return hc_transaccion(function (PDO $pdo) use ($a, $d, $login) {
        $seg = max(0, strtotime($d['FechSali'] . ' ' . $d['HoraSali']) - strtotime($a['FechIngr'] . ' ' . $a['HoraIngr']));
        $fila = [
            'CodiModu' => hc_modulo($a), 'FechSali' => $d['FechSali'], 'HoraSali' => $d['HoraSali'],
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
        ];
        $st = $pdo->prepare('SELECT COUNT(*) FROM SaliInte WHERE CodiInst = ? AND ConsAdmi = ? FOR UPDATE');
        $st->execute([CODI_INST, $a['ConsAdmi']]);
        if ((int) $st->fetchColumn() === 0) {
            hc_insertar($pdo, 'SaliInte', ['CodiInst' => CODI_INST, 'ConsAdmi' => $a['ConsAdmi']] + $fila + hc_digitacion($login));
        } else {
            // Modificar: las columnas vienen del código, nunca del usuario
            $fila += ['FechModi' => date('Y-m-d'), 'HoraModi' => date('H:i:s'), 'UsuaModi' => $login];
            $sql = 'UPDATE SaliInte SET ' . implode(', ', array_map(fn ($c) => "`$c` = ?", array_keys($fila)))
                 . ' WHERE CodiInst = ? AND ConsAdmi = ?';
            $pdo->prepare($sql)->execute(array_merge(array_values($fila), [CODI_INST, $a['ConsAdmi']]));
        }
        return 1;
    });
}

/**
 * "Cerrar Historia" del encabezado (los 3 módulos). En Urgencias y Observación exige el egreso (SaliInte) como
 * SIHOS; en Consulta Externa no hay egreso y solo se cierra la admisión.
 */
function cierre_validar(array $a): array
{
    $e = [];
    $ce = modulo_de_servicio($a['ServEgre']) === 'ce';
    $eg = $ce ? null : egreso_de_admision($a['ConsAdmi']);
    if (!$ce && !$eg) {
        $e['egreso'] = 'Antes de cerrar la historia registre el egreso.';
    }
    return [['egreso' => $eg], $e];
}

/** Cierra la admisión (Cerrado = 1). La cama queda libre. FechEgre/HoraEgre = salida del egreso o el momento del cierre. */
function cierre_guardar(array $a, array $d, string $login): int
{
    return hc_transaccion(function (PDO $pdo) use ($a, $d, $login) {
        $st = $pdo->prepare('SELECT Cerrado FROM Admision WHERE CodiInst = ? AND ConsAdmi = ? FOR UPDATE');
        $st->execute([CODI_INST, $a['ConsAdmi']]);
        if ((int) $st->fetchColumn() === 1) {
            throw new RuntimeException('La admisión ya estaba cerrada.');
        }
        $fs = $d['egreso']['FechSali'] ?? date('Y-m-d');
        $hs = $d['egreso']['HoraSali'] ?? date('H:i:s');
        $pdo->prepare('UPDATE Admision SET Cerrado = 1, FechCier = CURDATE(), HoraCier = CURTIME(), UsuaCier = ?,
                              FechEgre = ?, HoraEgre = ?, FechModi = CURDATE(), HoraModi = CURTIME(), UsuaModi = ?
                        WHERE CodiInst = ? AND ConsAdmi = ?')
            ->execute([$login, $fs, $hs, $login, CODI_INST, $a['ConsAdmi']]);
        return 1;
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
            'CantMate' => $d['CantMate'], 'CantFact' => 0, 'NumeOrde' => 0, 'Item' => 0, 'CentCost' => '',
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
    $st = db()->prepare("SELECT d.*, p.NombProc FROM DetaOrde d
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
