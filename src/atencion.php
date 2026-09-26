<?php
/**
 * Registro de atenciones: pacientes, admisiones, triage y signos vitales.
 *
 * Todo se guarda en tablas con los MISMOS nombres y columnas de SIHOS. Los valores
 * fijos (EstaIngr = 1, EntoAten = 20, CentCost vacio...) son los que SIHOS guarda en
 * la practica (revisado en admisiones reales de agosto-septiembre 2026).
 */

require_once __DIR__ . '/listas.php';

// ---------------------------------------------------------------------
// Utilidades
// ---------------------------------------------------------------------

/** Texto del POST recortado a $max caracteres (cadena vacia si no viene). */
function campo(string $nombre, int $max = 255): string
{
    $v = $_POST[$nombre] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

/** Numero del POST (acepta coma decimal) o null si viene vacio o no es numero. */
function campo_numero(string $nombre): ?float
{
    $v = str_replace(',', '.', trim((string) ($_POST[$nombre] ?? '')));
    return ($v !== '' && is_numeric($v)) ? (float) $v : null;
}

/** Fecha Y-m-d valida o null. */
function fecha_valida(string $v): ?string
{
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
}

/** Hora H:i o H:i:s valida (devuelve H:i:s) o null. */
function hora_valida(string $v): ?string
{
    foreach (['H:i:s', 'H:i'] as $f) {
        $d = DateTime::createFromFormat($f, $v);
        if ($d && $d->format($f) === $v) {
            return $d->format('H:i:s');
        }
    }
    return null;
}

/**
 * Edad como la guarda SIHOS: [valor, unidad] con unidad A (anos), M (meses) o D (dias).
 */
function edad_sihos(string $fechaNaci, string $fechaRef): array
{
    $n = new DateTime($fechaNaci);
    $r = new DateTime($fechaRef);
    if ($n > $r) {
        return [0, 'D'];
    }
    $d = $n->diff($r);
    if ($d->y >= 1) {
        return [$d->y, 'A'];
    }
    if ($d->m >= 1) {
        return [$d->m, 'M'];
    }
    return [$d->days, 'D'];
}

/** Edad legible: [30, 'A'] -> "30 años" */
function edad_texto($valor, $unidad): string
{
    $u = ['A' => 'años', 'M' => 'meses', 'D' => 'días'][$unidad] ?? $unidad;
    return $valor . ' ' . $u;
}

/** Nombre completo de un paciente (fila de Paciente). */
function paciente_nombre(array $p): string
{
    return trim(preg_replace('/\s+/', ' ', ($p['NombUsua'] ?? '') . ' ' . ($p['NombUsu1'] ?? '') . ' '
        . ($p['Ape1Usua'] ?? '') . ' ' . ($p['Ape2Usua'] ?? '')));
}

/** Modulo (clave de MODULOS_DETALLE) al que pertenece un servicio, o null. */
function modulo_de_servicio(?string $codiServ): ?string
{
    foreach (MODULOS_DETALLE as $clave => $m) {
        if (in_array($codiServ, $m['servicios'], true)) {
            return $clave;
        }
    }
    return null;
}

/** Datos del modulo o termina con 404 si la clave no existe. */
function modulo_o_404(?string $clave): array
{
    if (!isset(MODULOS_DETALLE[$clave])) {
        http_response_code(404);
        exit('Módulo no válido.');
    }
    return MODULOS_DETALLE[$clave] + ['clave' => $clave];
}

// ---------------------------------------------------------------------
// Pacientes
// ---------------------------------------------------------------------

function paciente_obtener(string $tipo, string $numero): ?array
{
    $st = db()->prepare('SELECT * FROM Paciente WHERE TipoDocu = ? AND NumeUsua = ?');
    $st->execute([$tipo, $numero]);
    return $st->fetch() ?: null;
}

/** Busca pacientes por numero de documento o por nombre/apellido (maximo 50). */
function pacientes_buscar(string $texto): array
{
    $texto = trim($texto);
    if ($texto === '') {
        return [];
    }
    $cols = 'TipoDocu, NumeUsua, NombUsua, NombUsu1, Ape1Usua, Ape2Usua, FechNaci, SexoUsua, CodiAdmi';
    if (preg_match('/^[0-9A-Za-z]+$/', $texto) && preg_match('/[0-9]/', $texto)) {
        $st = db()->prepare("SELECT $cols FROM Paciente WHERE NumeUsua LIKE ? ORDER BY NumeUsua LIMIT 50");
        $st->execute([$texto . '%']);
        return $st->fetchAll();
    }
    // Por nombre: cada palabra debe aparecer en algun nombre o apellido
    $where = [];
    $params = [];
    foreach (array_slice(preg_split('/\s+/', $texto), 0, 4) as $palabra) {
        $where[] = '(NombUsua LIKE ? OR NombUsu1 LIKE ? OR Ape1Usua LIKE ? OR Ape2Usua LIKE ?)';
        array_push($params, $palabra . '%', $palabra . '%', $palabra . '%', $palabra . '%');
    }
    $st = db()->prepare("SELECT $cols FROM Paciente WHERE " . implode(' AND ', $where) . ' ORDER BY Ape1Usua, Ape2Usua, NombUsua LIMIT 50');
    $st->execute($params);
    return $st->fetchAll();
}

/**
 * Valida los datos de un paciente nuevo (del POST). Devuelve [datos, errores].
 */
function paciente_validar(): array
{
    $d = [
        'TipoDocu' => strtoupper(campo('TipoDocu', 2)),
        'NumeUsua' => strtoupper(campo('NumeUsua', 20)),
        'NombUsua' => mb_strtoupper(campo('NombUsua', 20)),
        'NombUsu1' => mb_strtoupper(campo('NombUsu1', 20)),
        'Ape1Usua' => mb_strtoupper(campo('Ape1Usua', 30)),
        'Ape2Usua' => mb_strtoupper(campo('Ape2Usua', 30)),
        'FechNaci' => campo('FechNaci', 10),
        'SexoUsua' => campo('SexoUsua', 1),
        'ResiDepa' => campo('ResiDepa', 2),
        'ResiMuni' => campo('ResiMuni', 3),
        'ResiZona' => campo('ResiZona', 1),
        'DireResi' => mb_strtoupper(campo('DireResi', 80)),
        'TeleCelu' => campo('TeleCelu', 12),
        'CodiAdmi' => campo('CodiAdmi', 6),
        'TipoUsua' => campo('TipoUsua', 2),
        'TipoAfil' => campo('TipoAfil', 1),
        'NumeCont' => campo('NumeCont', 15),
    ];
    $e = [];
    if (!lista_valida('TipoDocu', $d['TipoDocu'])) $e['TipoDocu'] = 'Seleccione el tipo de documento.';
    if (!preg_match('/^[0-9A-Z]{3,20}$/', $d['NumeUsua'])) $e['NumeUsua'] = 'Documento no válido (solo números y letras, sin puntos ni espacios).';
    if ($d['NombUsua'] === '') $e['NombUsua'] = 'Escriba el primer nombre.';
    if ($d['Ape1Usua'] === '') $e['Ape1Usua'] = 'Escriba el primer apellido.';
    if (!fecha_valida($d['FechNaci']) || $d['FechNaci'] > date('Y-m-d') || $d['FechNaci'] < '1900-01-01') {
        $e['FechNaci'] = 'Fecha de nacimiento no válida.';
    }
    if (!lista_valida('Sexo', $d['SexoUsua'])) $e['SexoUsua'] = 'Seleccione el sexo.';
    if (!lista_valida('Depa', $d['ResiDepa'])) {
        $e['ResiDepa'] = 'Seleccione el departamento.';
    } elseif (!array_key_exists($d['ResiMuni'], municipios($d['ResiDepa']))) {
        $e['ResiMuni'] = 'Seleccione el municipio.';
    }
    if (!lista_valida('Zona', $d['ResiZona'])) $e['ResiZona'] = 'Seleccione la zona.';
    if (!lista_valida('Admi', $d['CodiAdmi'])) $e['CodiAdmi'] = 'Seleccione la EPS.';
    if (!lista_valida('TipoUsua', $d['TipoUsua'])) $e['TipoUsua'] = 'Seleccione el tipo de usuario.';
    if (!lista_valida('TipoAfil', $d['TipoAfil'])) $e['TipoAfil'] = 'Seleccione el tipo de afiliación.';
    if ($d['NumeCont'] !== '' && !array_key_exists($d['NumeCont'], contratos($d['CodiAdmi']))) {
        $e['NumeCont'] = 'El contrato no pertenece a la EPS seleccionada.';
    }
    return [$d, $e];
}

/** Crea un paciente nuevo (datos ya validados) y lo marca para crearlo en SIHOS al cargar. */
function paciente_crear(array $d, string $login): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('INSERT INTO Paciente (TipoDocu, NumeUsua, NombUsua, NombUsu1, Ape1Usua, Ape2Usua, CodiInst,
                                    CodiAdmi, TipoUsua, TipoAfil, NumeCont, FechNaci, SexoUsua, ResiDepa, ResiMuni, ResiZona,
                                    DireResi, TeleCelu, FechDigi, HoraDigi, UsuaDigi, FechModi, HoraModi, UsuaModi)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, CURDATE(), CURTIME(), ?)');
        $st->execute([$d['TipoDocu'], $d['NumeUsua'], $d['NombUsua'], $d['NombUsu1'], $d['Ape1Usua'], $d['Ape2Usua'], CODI_INST,
            $d['CodiAdmi'], (int) $d['TipoUsua'], $d['TipoAfil'], $d['NumeCont'] ?: null, $d['FechNaci'], $d['SexoUsua'],
            $d['ResiDepa'], $d['ResiMuni'], $d['ResiZona'], $d['DireResi'], $d['TeleCelu'], $login, $login]);
        $st = $pdo->prepare('INSERT INTO cont_paciente (TipoDocu, NumeUsua, accion, fecha, usuario) VALUES (?, ?, ?, NOW(), ?)');
        $st->execute([$d['TipoDocu'], $d['NumeUsua'], 'nuevo', $login]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

// ---------------------------------------------------------------------
// Admisiones
// ---------------------------------------------------------------------

/**
 * Numero TEMPORAL de admision: "C" + AAMMDD + consecutivo de 5 digitos (12 caracteres).
 * Nunca choca con SIHOS (que usa solo digitos). El definitivo se asigna al cargar (fase 3).
 * Debe llamarse dentro de la transaccion y con el candado de admision tomado.
 */
function admision_numero_temporal(PDO $pdo): string
{
    $prefijo = 'C' . date('ymd');
    $st = $pdo->prepare('SELECT MAX(ConsAdmi) FROM Admision WHERE CodiInst = ? AND ConsAdmi LIKE ?');
    $st->execute([CODI_INST, $prefijo . '%']);
    $max = $st->fetchColumn();
    $sig = $max ? ((int) substr($max, 7)) + 1 : 1;
    if ($sig > 99999) {
        throw new RuntimeException('Se agotó el consecutivo temporal de admisiones del día.');
    }
    return $prefijo . str_pad((string) $sig, 5, '0', STR_PAD_LEFT);
}

/** Admision abierta (no cerrada ni anulada) del paciente, si tiene. */
function admision_abierta_de_paciente(string $tipo, string $numero): ?array
{
    $st = db()->prepare('SELECT ConsAdmi, CodiServ, ServEgre, FechIngr, HoraIngr FROM Admision
                          WHERE CodiInst = ? AND TipoDocu = ? AND NumeUsua = ? AND Cerrado = 2 AND Anulado = 2
                          ORDER BY FechIngr DESC, HoraIngr DESC LIMIT 1');
    $st->execute([CODI_INST, $tipo, $numero]);
    return $st->fetch() ?: null;
}

/**
 * Valida el formulario de nueva admision. Devuelve [datos, errores].
 * $mod = MODULOS_DETALLE[...] + clave, $pac = fila de Paciente.
 */
function admision_validar(array $mod, array $pac): array
{
    $d = [
        'CodiServ' => campo('CodiServ', 3),
        'FechIngr' => campo('FechIngr', 10),
        'HoraIngr' => campo('HoraIngr', 8),
        'CodiAdmi' => campo('CodiAdmi', 6),
        'NumeCont' => campo('NumeCont', 15),
        'TipoUsua' => campo('TipoUsua', 2),
        'TipoAfil' => campo('TipoAfil', 1),
        'CodiEstr' => campo('CodiEstr', 1),
        'NumeAuto' => campo('NumeAuto', 50),
        'ViaIngre' => campo('ViaIngre', 2),
        'CausExte' => campo('CausExte', 2),
        'GrupoAte' => campo('GrupoAte', 1),
        'CondUsua' => campo('CondUsua', 1),
        'DiagIngr' => strtoupper(campo('DiagIngr', 8)),
        'CodiCama' => campo('CodiCama', 10),
        'MotiCons' => campo('MotiCons', 5000),
        'TipoAcom' => campo('TipoAcom', 1),
        'NombAcom' => mb_strtoupper(campo('NombAcom', 80)),
        'Parentes' => campo('Parentes', 2),
        'TeleAcom' => campo('TeleAcom', 10),
    ];
    $e = [];
    if (!in_array($d['CodiServ'], $mod['servicios'], true)) $e['CodiServ'] = 'Servicio no válido para este módulo.';
    $d['HoraIngr'] = hora_valida($d['HoraIngr']) ?? $d['HoraIngr'];
    if (!fecha_valida($d['FechIngr'])) {
        $e['FechIngr'] = 'Fecha de ingreso no válida.';
    } elseif ($d['FechIngr'] . ' ' . $d['HoraIngr'] > date('Y-m-d H:i:s', time() + 300)) {
        $e['FechIngr'] = 'La fecha y hora de ingreso no puede ser futura.';
    } elseif ($d['FechIngr'] < date('Y-m-d', strtotime('-30 days'))) {
        $e['FechIngr'] = 'La fecha de ingreso tiene más de 30 días. Revísela.';
    }
    if (!hora_valida($d['HoraIngr'])) $e['HoraIngr'] = 'Hora de ingreso no válida.';
    if (!lista_valida('Admi', $d['CodiAdmi'])) {
        $e['CodiAdmi'] = 'Seleccione la EPS.';
    } elseif (!array_key_exists($d['NumeCont'], contratos($d['CodiAdmi']))) {
        $e['NumeCont'] = 'Seleccione un contrato activo de la EPS.';
    }
    if (!lista_valida('TipoUsua', $d['TipoUsua'])) $e['TipoUsua'] = 'Seleccione el tipo de usuario.';
    if (!lista_valida('TipoAfil', $d['TipoAfil'])) {
        $e['TipoAfil'] = 'Seleccione el tipo de afiliación.';
    } elseif (!isset($e['CodiAdmi'])
        && !array_key_exists($d['CodiEstr'], estratos($d['CodiAdmi'], $mod['TipoAten'], $d['TipoAfil']))) {
        $e['CodiEstr'] = 'Seleccione una categoría válida para la EPS y el tipo de afiliación.';
    }
    if (!lista_valida('ViaIngre', $d['ViaIngre'])) $e['ViaIngre'] = 'Seleccione la vía de ingreso.';
    if (!lista_valida('CausExte', $d['CausExte'])) $e['CausExte'] = 'Seleccione la causa externa.';
    if (!lista_valida('GrupAten', $d['GrupoAte'])) $e['GrupoAte'] = 'Seleccione el grupo poblacional.';
    if (!lista_valida('CondUsua', $d['CondUsua'])) $e['CondUsua'] = 'Seleccione la condición de la usuaria.';
    if ($d['DiagIngr'] !== '' && diagnostico_nombre($d['DiagIngr']) === null) {
        $e['DiagIngr'] = 'El diagnóstico no existe en el CIE-10 activo.';
    }
    if ($mod['cama']) {
        $camas = camas($d['CodiServ']);
        if (!array_key_exists($d['CodiCama'], $camas)) {
            $e['CodiCama'] = 'Seleccione la cama.';
        } elseif (strpos($camas[$d['CodiCama']], '(ocupada)') !== false) {
            $e['CodiCama'] = 'La cama está ocupada por otra admisión abierta.';
        }
    } else {
        $d['CodiCama'] = '';
    }
    if (!lista_valida('TipoAcom', $d['TipoAcom'])) $e['TipoAcom'] = 'Seleccione el tipo de acompañante.';
    if ($d['Parentes'] === '') {
        $d['Parentes'] = '1';
    } elseif (!lista_valida('Parentes', $d['Parentes'])) {
        $e['Parentes'] = 'Parentesco no válido.';
    }
    if (admision_abierta_de_paciente($pac['TipoDocu'], $pac['NumeUsua'])) {
        $e['general'] = 'El paciente ya tiene una admisión abierta en la contingencia.';
    }
    return [$d, $e];
}

/** Crea la admision (datos validados). Devuelve el numero temporal. */
function admision_crear(array $mod, array $pac, array $d, string $login): string
{
    [$valoEdad, $unidEdad] = edad_sihos($pac['FechNaci'], $d['FechIngr']);
    $pdo = db();
    // Candado para que dos equipos no saquen el mismo numero al mismo tiempo
    if ((int) $pdo->query("SELECT GET_LOCK('crador_consadmi', 10)")->fetchColumn() !== 1) {
        throw new RuntimeException('No fue posible reservar el número de admisión. Intente de nuevo.');
    }
    try {
        $pdo->beginTransaction();
        $cons = admision_numero_temporal($pdo);
        $st = $pdo->prepare('INSERT INTO Admision (CodiInst, ConsAdmi, TipoDocu, NumeUsua, ValoEdad, UnidEdad, GrupoAte,
                CodiAdmi, TipoUsua, TipoAfil, CodiEstr, NumeCont, NumeAuto, NumePoli, FechIngr, HoraIngr, EstaIngr, ViaIngre,
                VienRefe, DiagIngr, TipoAten, CodiServ, CentCost, CodiCama, MotiCons, ServEgre, CentEgre, CamaActu,
                CausExte, EntoAten, CondUsua, TipoAcom, NombAcom, TeleAcom, Parentes, Reingres, Cerrado, Anulado,
                FechDigi, HoraDigi, UsuaDigi, FechModi, HoraModi, UsuaModi)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'\', ?, ?, 1, ?, 0, ?, ?, ?, \'\', ?, ?, ?, \'\', ?,
                ?, 20, ?, ?, ?, ?, ?, 0, 2, 2, CURDATE(), CURTIME(), ?, CURDATE(), CURTIME(), ?)');
        $st->execute([CODI_INST, $cons, $pac['TipoDocu'], $pac['NumeUsua'], $valoEdad, $unidEdad, $d['GrupoAte'],
            $d['CodiAdmi'], (int) $d['TipoUsua'], $d['TipoAfil'], $d['CodiEstr'], $d['NumeCont'], $d['NumeAuto'],
            $d['FechIngr'], $d['HoraIngr'], (int) $d['ViaIngre'], $d['DiagIngr'], $mod['TipoAten'], $d['CodiServ'],
            $d['CodiCama'], $d['MotiCons'] !== '' ? $d['MotiCons'] : '.', $d['CodiServ'], $d['CodiCama'],
            $d['CausExte'], (int) $d['CondUsua'], (int) $d['TipoAcom'], $d['NombAcom'] ?: null, $d['TeleAcom'] ?: null,
            (int) $d['Parentes'], $login, $login]);
        $st = $pdo->prepare("INSERT INTO cont_carga_sihos (CodiInst, ConsAdmiTemp, estado, fecha_registro, usuario_registro)
                             VALUES (?, ?, 'pendiente', NOW(), ?)");
        $st->execute([CODI_INST, $cons, $login]);
        $pdo->commit();
        return $cons;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('crador_consadmi')");
    }
}

/** Admision con datos del paciente, o null. */
function admision_obtener(string $cons): ?array
{
    $st = db()->prepare('SELECT a.*, p.NombUsua, p.NombUsu1, p.Ape1Usua, p.Ape2Usua, p.FechNaci, p.SexoUsua,
                                c.NombAdmi, s.NombServ, cc.estado AS estado_carga
                           FROM Admision a
                           LEFT JOIN Paciente p ON p.TipoDocu = a.TipoDocu AND p.NumeUsua = a.NumeUsua
                           LEFT JOIN CodiAdmi c ON c.CodiAdmi = a.CodiAdmi
                           LEFT JOIN CodiServ s ON s.CodiServ = a.ServEgre
                           LEFT JOIN cont_carga_sihos cc ON cc.CodiInst = a.CodiInst AND cc.ConsAdmiTemp = a.ConsAdmi
                          WHERE a.CodiInst = ? AND a.ConsAdmi = ?');
    $st->execute([CODI_INST, $cons]);
    return $st->fetch() ?: null;
}

/** Admision o termina con mensaje si no existe. */
function admision_o_404(?string $cons): array
{
    $a = is_string($cons) ? admision_obtener($cons) : null;
    if (!$a) {
        flash('error', 'La admisión no existe.');
        redirigir(pagina_inicio());
    }
    return $a;
}

/** true si la admision se puede modificar (abierta y aun no cargada a SIHOS). */
function admision_editable(array $a): bool
{
    return (int) $a['Cerrado'] === 2 && (int) $a['Anulado'] === 2 && ($a['estado_carga'] ?? 'pendiente') !== 'cargada';
}

/** Admisiones abiertas de un modulo (por servicio actual ServEgre), con ultimo triage. */
function admisiones_abiertas(array $mod): array
{
    $marcas = implode(',', array_fill(0, count($mod['servicios']), '?'));
    $st = db()->prepare("SELECT a.ConsAdmi, a.TipoDocu, a.NumeUsua, a.ValoEdad, a.UnidEdad, a.FechIngr, a.HoraIngr,
                                a.ServEgre, a.CamaActu, a.ClasTria, a.DiagIngr, a.UsuaDigi,
                                p.NombUsua, p.NombUsu1, p.Ape1Usua, p.Ape2Usua, p.SexoUsua, c.NombAdmi, s.NombServ,
                                (SELECT COUNT(*) FROM SignVita v WHERE v.CodiInst = a.CodiInst AND v.ConsAdmi = a.ConsAdmi) AS signos
                           FROM Admision a
                           LEFT JOIN Paciente p ON p.TipoDocu = a.TipoDocu AND p.NumeUsua = a.NumeUsua
                           LEFT JOIN CodiAdmi c ON c.CodiAdmi = a.CodiAdmi
                           LEFT JOIN CodiServ s ON s.CodiServ = a.ServEgre
                          WHERE a.CodiInst = ? AND a.ServEgre IN ($marcas) AND a.Cerrado = 2 AND a.Anulado = 2
                          ORDER BY IFNULL(a.ClasTria, 9), a.FechIngr, a.HoraIngr");
    $st->execute(array_merge([CODI_INST], $mod['servicios']));
    return $st->fetchAll();
}

// ---------------------------------------------------------------------
// Triage (solo Urgencias)
// ---------------------------------------------------------------------

function triage_de_admision(string $cons): ?array
{
    $st = db()->prepare('SELECT * FROM Triage WHERE CodiInst = ? AND ConsAdmi = ? ORDER BY ConsTria DESC LIMIT 1');
    $st->execute([CODI_INST, $cons]);
    return $st->fetch() ?: null;
}

/** Valida el formulario de triage. Devuelve [datos, errores]. Los signos se validan aparte. */
function triage_validar(): array
{
    $d = [
        'FechTria' => campo('FechTria', 10),
        'HoraTria' => campo('HoraTria', 8),
        'MotiCons' => campo('MotiCons', 5000),
        'HallClin' => campo('HallClin', 5000),
        'CodiDiag' => strtoupper(campo('CodiDiag', 8)),
        'ClasTria' => campo('ClasTria', 1),
        'CondTria' => campo('CondTria', 2),
        'CodiCons' => campo('CodiCons', 5),
        'Conducta' => campo('Conducta', 5000),
    ];
    $e = [];
    if (!fecha_valida($d['FechTria'])) $e['FechTria'] = 'Fecha no válida.';
    $h = hora_valida($d['HoraTria']);
    $h ? $d['HoraTria'] = $h : $e['HoraTria'] = 'Hora no válida.';
    if ($d['MotiCons'] === '') $e['MotiCons'] = 'Escriba el motivo de consulta.';
    if ($d['HallClin'] === '') $e['HallClin'] = 'Escriba los hallazgos clínicos.';
    if ($d['CodiDiag'] !== '' && diagnostico_nombre($d['CodiDiag']) === null) {
        $e['CodiDiag'] = 'El diagnóstico no existe en el CIE-10 activo.';
    }
    if (!lista_valida('ClasTria', $d['ClasTria'])) $e['ClasTria'] = 'Seleccione la clasificación del triage.';
    if (!lista_valida('CondTria', $d['CondTria'])) $e['CondTria'] = 'Seleccione la conducta.';
    // "Continuar en el consultorio" (opcional), del catalogo de consultorios
    if ($d['CodiCons'] !== '' && !lista_valida('Cons', $d['CodiCons'])) $e['CodiCons'] = 'Seleccione un consultorio válido.';
    return [$d, $e];
}

/**
 * Guarda el triage con sus signos vitales (en SIHOS la pantalla de triage guarda
 * los signos como toma No. 1 con SintResp/SintPiel = 2) y actualiza Admision.ClasTria.
 *
 * ConsTria en SIHOS es el numero de triage DEL PACIENTE (1, 2, 3... en toda su historia).
 * Aqui se calcula con los triages locales; al cargar a SIHOS (fase 3) se recalcula.
 */
function triage_guardar(array $a, array $t, array $s, string $login): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT IFNULL(MAX(ConsTria), 0) + 1 FROM Triage WHERE CodiInst = ? AND TipoDocu = ? AND NumeUsua = ?');
        $st->execute([CODI_INST, $a['TipoDocu'], $a['NumeUsua']]);
        $consTria = (int) $st->fetchColumn();

        $st = $pdo->prepare('INSERT INTO Triage (CodiInst, ConsAdmi, ConsTria, TipoDocu, NumeUsua, FechTria, HoraTria, MotiCons,
                                    HallClin, CodiDiag, ClasTria, CondTria, Conducta, Realizad, CodiAdmi, NumeCont, TipoUsua,
                                    FechDigi, HoraDigi, UsuaDigi, FechModi, HoraModi, UsuaModi, CodiCons)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, CURDATE(), CURTIME(), ?, CURDATE(), CURTIME(), ?, ?)');
        $st->execute([CODI_INST, $a['ConsAdmi'], $consTria, $a['TipoDocu'], $a['NumeUsua'], $t['FechTria'], $t['HoraTria'],
            $t['MotiCons'], $t['HallClin'], $t['CodiDiag'], (int) $t['ClasTria'], $t['CondTria'], $t['Conducta'],
            $a['CodiAdmi'], $a['NumeCont'], $a['TipoUsua'], $login, $login, $t['CodiCons'] ?? '']);

        signos_insertar($pdo, $a, $s + ['FechToma' => $t['FechTria'], 'HoraToma' => $t['HoraTria']], $login, 2);

        $st = $pdo->prepare('UPDATE Admision SET ClasTria = ?, FechModi = CURDATE(), HoraModi = CURTIME(), UsuaModi = ?
                              WHERE CodiInst = ? AND ConsAdmi = ?');
        $st->execute([(int) $t['ClasTria'], $login, CODI_INST, $a['ConsAdmi']]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

// ---------------------------------------------------------------------
// Signos vitales
// ---------------------------------------------------------------------

/**
 * Rangos aceptados para cada signo: campo => [etiqueta, minimo, maximo, obligatorio].
 * Son limites para detectar errores de digitacion, no rangos clinicos normales.
 */
const SIGNOS_RANGOS = [
    'PANume'   => ['Presión sistólica (mmHg)', 40, 300, true],
    'PADeno'   => ['Presión diastólica (mmHg)', 20, 200, true],
    'Pulso'    => ['Frecuencia cardiaca (lpm)', 20, 250, true],
    'Respirac' => ['Frecuencia respiratoria (rpm)', 5, 80, true],
    'Temperat' => ['Temperatura (°C)', 30, 45, true],
    'Saturaci' => ['Saturación O₂ (%)', 40, 100, false],
    'Peso'     => ['Peso (kg)', 0.3, 350, false],
    'Talla'    => ['Talla (cm)', 20, 250, false],
    'FetoCard' => ['Fetocardia (lat/min)', 60, 220, false],
    'Oximetria' => ['Oximetría (%)', 40, 100, false],
    'Dolor'    => ['Dolor (0 a 10)', 0, 10, false],
    'GlucMetr' => ['Glucometría (mg/dL)', 10, 999, false],
];

/** Valida los signos del POST. Devuelve [datos, errores]. $conFecha: pide FechToma/HoraToma. */
function signos_validar(bool $conFecha): array
{
    $d = [];
    $e = [];
    foreach (SIGNOS_RANGOS as $c => [$etiqueta, $min, $max, $oblig]) {
        $v = campo_numero($c);
        if ($v === null) {
            if ($oblig) $e[$c] = "Escriba $etiqueta.";
            $d[$c] = 0;
        } elseif ($v < $min || $v > $max) {
            $e[$c] = "$etiqueta fuera de rango ($min a $max).";
            $d[$c] = $v;
        } else {
            $d[$c] = $v;
        }
    }
    if (!isset($e['PANume'], $e['PADeno']) && $d['PADeno'] >= $d['PANume'] && $d['PANume'] > 0) {
        $e['PADeno'] = 'La diastólica debe ser menor que la sistólica.';
    }
    if ($conFecha) {
        $d['FechToma'] = campo('FechToma', 10);
        $h = hora_valida(campo('HoraToma', 8));
        if (!fecha_valida($d['FechToma'])) $e['FechToma'] = 'Fecha no válida.';
        $h ? $d['HoraToma'] = $h : $e['HoraToma'] = 'Hora no válida.';
        if (!isset($e['FechToma']) && $h && $d['FechToma'] . ' ' . $h > date('Y-m-d H:i:s', time() + 300)) {
            $e['FechToma'] = 'La toma no puede ser en el futuro.';
        }
    }
    return [$d, $e];
}

/**
 * Inserta una toma de signos. Calcula IMC (MasaCorp) y presion arterial media (TM)
 * como SIHOS: IMC = peso / talla(m)^2 ; TM = (sistolica + 2 x diastolica) / 3.
 * $sintomas = valor de SintResp/SintPiel (SIHOS guarda 2 en triage y 0 en tomas posteriores).
 */
function signos_insertar(PDO $pdo, array $a, array $s, string $login, int $sintomas = 0, int $consCons = 0, int $consEvol = 0): int
{
    $st = $pdo->prepare('SELECT IFNULL(MAX(ConsSign), 0) + 1 FROM SignVita WHERE CodiInst = ? AND ConsAdmi = ?');
    $st->execute([CODI_INST, $a['ConsAdmi']]);
    $cons = (int) $st->fetchColumn();

    $imc = ($s['Peso'] > 0 && $s['Talla'] > 0) ? round($s['Peso'] / (($s['Talla'] / 100) ** 2), 2) : 0;
    $tm = ($s['PANume'] > 0 && $s['PADeno'] > 0) ? (int) round(($s['PANume'] + 2 * $s['PADeno']) / 3) : 0;
    $mod = MODULOS_DETALLE[modulo_de_servicio($a['ServEgre'])] ?? null;

    $st = $pdo->prepare('INSERT INTO SignVita (CodiInst, ConsAdmi, ConsSign, ConsEvol, CodiModu, FechToma, ConsCons, id_clap,
                                HoraToma, Peso, Talla, MasaCorp, Pulso, Respirac, Temperat, PANume, PADeno, FetoCard, Saturaci,
                                SintResp, SintPiel, UnidEdad, ValoEdad, Dolor, TM, Oximetria, GlucMetr,
                                FechDigi, HoraDigi, UsuaDigi, FechModi, HoraModi, UsuaModi)
                         VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                                 CURDATE(), CURTIME(), ?, CURDATE(), CURTIME(), ?)');
    $st->execute([CODI_INST, $a['ConsAdmi'], $cons, $consEvol, $mod['CodiModu'] ?? 0, $s['FechToma'], $consCons, $s['HoraToma'],
        $s['Peso'], $s['Talla'], $imc, (int) $s['Pulso'], (int) $s['Respirac'], $s['Temperat'], (int) $s['PANume'],
        (int) $s['PADeno'], (int) ($s['FetoCard'] ?? 0), $s['Saturaci'], $sintomas, $sintomas, $a['UnidEdad'], $a['ValoEdad'],
        $s['Dolor'], $tm, (int) ($s['Oximetria'] ?? 0) > 0 ? (int) $s['Oximetria'] : null, (int) $s['GlucMetr'], $login, $login]);
    return $cons;
}

/** Guarda una toma de signos independiente (transaccion propia). */
function signos_guardar(array $a, array $s, string $login): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $cons = signos_insertar($pdo, $a, $s, $login, 0);
        $pdo->commit();
        return $cons;
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

function signos_de_admision(string $cons): array
{
    $st = db()->prepare('SELECT * FROM SignVita WHERE CodiInst = ? AND ConsAdmi = ? ORDER BY FechToma DESC, HoraToma DESC, ConsSign DESC');
    $st->execute([CODI_INST, $cons]);
    return $st->fetchAll();
}
