<?php
/**
 * Pantalla de trabajo del modulo (igual para Urgencias, Observacion e Internacion y Consulta Externa),
 * organizada como en SIHOS: encabezado de la admision arriba y pestanas numeradas debajo.
 *
 *   atencion.php?modulo=urg                       modulo sin admision (se abre "Historias abiertas")
 *   atencion.php?modulo=urg&nueva=1               encabezado vacio para buscar el documento
 *   atencion.php?modulo=urg&TipoDocu=CC&NumeUsua=  paciente buscado: si tiene admision abierta se carga;
 *                                                 si no, el encabezado queda editable para crearla
 *   atencion.php?id=C26092500001&tab=triage       admision cargada, en la pestana indicada
 *
 * Los formularios envian a esta misma pagina: accion=admision | triage | signos | consulta | prescripcion |
 * orden_medica | ordenes | procedimiento | nota | medicamento | evolucion | egreso | cierre | material |
 * remision | incapacidad | traslado.
 * Las pestanas de cada modulo (nombres, orden y numeracion de SIHOS) estan en pestanas_lista() de
 * src/formulario.php; sus paneles en src/vistas/pestana_*.php (triage y signos vitales, aqui mismo).
 * Siempre se vuelve a esta pantalla (POST y redireccion).
 */
require __DIR__ . '/../src/inicio.php';
require __DIR__ . '/../src/historia.php';
require __DIR__ . '/../src/formulario.php';

$u = requiere_login();

// --- Buscar por número de admisión (encabezado: se escribe y Enter), como SIHOS: abre cualquier historia de la
// contingencia, abierta o cerrada (la cerrada en solo lectura) ------------------------------------------------
if (is_string($_GET['adm'] ?? null)) {
    $numAdm = mb_substr(admision_numero_limpio($_GET['adm']), 0, 12);
    if ($numAdm !== '' && admision_obtener($numAdm)) {
        redirigir('atencion.php?id=' . urlencode($numAdm));
    }
    flash('error', $numAdm === '' ? 'Escriba el número de admisión.' : "No existe la admisión $numAdm.");
    redirigir('atencion.php?modulo=' . urlencode(is_string($_GET['modulo'] ?? null) ? $_GET['modulo'] : (string) modulo_actual()) . '&nueva=1');
}

// --- Admision cargada y modulo -------------------------------------------
$a = null;
if (isset($_GET['id'])) {
    $a = admision_o_404($_GET['id']);
    // Diagnóstico de ingreso efectivo (no se digita): el de la admisión o el de la primera consulta/procedimiento
    $a['DiagIngr'] = diag_ingreso($a);
    $clave = modulo_de_servicio($a['ServEgre']) ?? modulo_actual();
} else {
    $clave = is_string($_GET['modulo'] ?? null) ? $_GET['modulo'] : modulo_actual();
}
if ($clave === null || !isset(MODULOS_DETALLE[$clave])) {
    redirigir('modulo.php');
}
$mod = modulo_o_404($clave);
modulo_elegir($clave);
$base = 'atencion.php?modulo=' . $clave;

// --- Paciente buscado desde el encabezado (sin admision cargada) --------
$docTipo = $a['TipoDocu'] ?? (is_string($_GET['TipoDocu'] ?? null) ? $_GET['TipoDocu'] : 'CC');
$docNum  = $a['NumeUsua'] ?? (is_string($_GET['NumeUsua'] ?? null) ? mb_substr(trim($_GET['NumeUsua']), 0, 20) : '');
$pac = null;
$buscado = !$a && $docNum !== '';
if ($buscado) {
    $pac = paciente_obtener($docTipo, $docNum);
    if (!$pac) {
        // Se escribió parte del documento o el nombre y se pulsó Buscar: si hay UN solo paciente, se carga
        $candidatos = pacientes_buscar($docNum, 2);
        if (count($candidatos) === 1) {
            $pac = paciente_obtener($candidatos[0]['TipoDocu'], $candidatos[0]['NumeUsua']);
            [$docTipo, $docNum] = [$candidatos[0]['TipoDocu'], $candidatos[0]['NumeUsua']];
        }
    }
    // Como pidió el usuario: buscar al paciente SOLO carga el encabezado; nunca abre una historia ni guarda nada.
    // Si ya tiene una admisión abierta se avisa con un botón para abrirla (la decisión es del profesional).
    $abiertaPac = $pac ? admision_abierta_de_paciente($pac['TipoDocu'], $pac['NumeUsua']) : null;
}
$urlBusqueda = $base . '&TipoDocu=' . urlencode($docTipo) . '&NumeUsua=' . urlencode($docNum);

// Valores por defecto de la nueva admision: afiliacion del paciente y lo mas usado en SIHOS
$d = [];
$eA = [];
if ($pac) {
    $d = [
        'CodiServ' => $mod['servicios'][0], 'FechIngr' => date('Y-m-d'), 'HoraIngr' => date('H:i'),
        'CodiAdmi' => $pac['CodiAdmi'], 'NumeCont' => $pac['NumeCont'], 'TipoUsua' => $pac['TipoUsua'],
        'TipoAfil' => $pac['TipoAfil'], 'CodiEstr' => $pac['EstrPaci'], 'ViaIngre' => $mod['ViaIngre'],
        'CausExte' => '13', 'GrupoAte' => 'O', 'CondUsua' => $pac['SexoUsua'] === 'F' ? '4' : '5',
        'TipoAcom' => '1', 'Parentes' => '',
    ];
}

// --- Datos de la admision cargada ---------------------------------------
$editable = $a ? admision_editable($a) : false;
// "Modificar" (como SIHOS): el encabezado de la admisión abierta se vuelve editable y se guarda con "Guardar"
$modificando = $a && $editable && (isset($_GET['modificar']) || ($_POST['accion'] ?? '') === 'admision_modificar');
if ($modificando) {
    $d = [
        'CodiServ' => $a['CodiServ'], 'FechIngr' => $a['FechIngr'], 'HoraIngr' => substr($a['HoraIngr'], 0, 5),
        'NumeAuto' => $a['NumeAuto'], 'NumePoli' => $a['NumePoli'], 'CodiAdmi' => $a['CodiAdmi'], 'NumeCont' => $a['NumeCont'],
        'TipoUsua' => $a['TipoUsua'], 'TipoAfil' => $a['TipoAfil'], 'CodiEstr' => $a['CodiEstr'], 'ViaIngre' => $a['ViaIngre'],
        'CausExte' => $a['CausExte'], 'GrupoAte' => $a['GrupoAte'], 'CondUsua' => $a['CondUsua'], 'TipoAcom' => $a['TipoAcom'],
        'NombAcom' => $a['NombAcom'], 'Parentes' => $a['Parentes'], 'TeleAcom' => $a['TeleAcom'], 'CodiCama' => $a['CamaActu'],
    ];
}
$aqui = $a ? 'atencion.php?id=' . urlencode($a['ConsAdmi']) : $base;
$triage = $a ? triage_de_admision($a['ConsAdmi']) : null;
$signos = $a ? signos_de_admision($a['ConsAdmi']) : [];
$tieneTriage = (bool) $mod['triage'];

$disponibles = $a ? pestanas_disponibles($mod) : [];
// Pestana por defecto: el triage si falta (Urgencias); si no, la primera despues del triage
$porDefecto = $disponibles ? (($disponibles[0] === 'triage' && $triage) ? $disponibles[1] : $disponibles[0]) : '';
$tab = in_array($_GET['tab'] ?? '', $disponibles, true) ? $_GET['tab'] : $porDefecto;

$t  = ['FechTria' => date('Y-m-d'), 'HoraTria' => date('H:i'), 'CodiDiag' => $a['DiagIngr'] ?? '', 'CondTria' => '1'];
$st = [];
$eT = [];
$ultima = $signos[0] ?? null;
$sv = ['FechToma' => date('Y-m-d'), 'HoraToma' => date('H:i'), 'Peso' => $ultima['Peso'] ?? '', 'Talla' => $ultima['Talla'] ?? ''];
$eS = [];

// Acciones de las pestanas: accion => [pestanas donde se guarda (la primera del modulo que exista), validar, guardar, mensaje]
const ACCIONES_HISTORIA = [
    'consulta'      => [['consulta', 'anamnesis'], 'consulta_validar', 'consulta_guardar', 'Consulta No. %d registrada.'],
    'prescripcion'  => [['prescripcion'], 'prescripcion_validar', 'prescripcion_guardar', 'Prescripción No. %d registrada.'],
    'orden_medica'  => [['ordenes_medicas'], 'orden_medica_validar', 'orden_medica_guardar', 'Orden médica No. %d registrada.'],
    'ordenes'       => [['ordenacion'], 'ordenes_validar', 'ordenes_guardar', 'Orden No. %d registrada.'],
    'procedimiento' => [['procedimientos'], 'procedimiento_validar', 'procedimiento_guardar', 'Procedimiento No. %d registrado.'],
    // Notas: la pestana sale del campo NotaPestana (enfermeria | medica)
    'nota'          => [['notas_enfermeria', 'notas_medicas'], 'nota_validar', 'nota_guardar', 'Nota No. %d registrada.'],
    'medicamento'   => [['medicamentos'], 'medicamento_validar', 'medicamento_guardar', 'Aplicación de medicamento No. %d registrada.'],
    'evolucion'     => [['evolucion'], 'evolucion_validar', 'evolucion_guardar', 'Evolución No. %d registrada.'],
    'egreso'        => [['egreso'], 'egreso_validar', 'egreso_guardar', 'Egreso guardado. Para terminar pulse Cerrar Historia.'],
    'material'      => [['materiales'], 'material_validar', 'material_guardar', 'Material No. %d registrado.'],
    'plan'          => [['plan'], 'plan_validar', 'plan_guardar', 'Plan de manejo de la consulta No. %d guardado.'],
    'remision'      => [['remisiones'], 'remision_validar', 'remision_guardar', 'Remisión No. %d registrada.'],
    'incapacidad'   => [['incapacidad'], 'incapacidad_validar', 'incapacidad_guardar', 'Incapacidad No. %d registrada.'],
    // Desde el encabezado; vuelven a la pestana en la que se estaba:
    // Cambio de Atencion (Observacion 23): traslado de cama (TrasCama)
    'traslado'      => [['cambio'], 'traslado_validar', 'traslado_guardar', 'Cambio de atención No. %d registrado.'],
    // "Cerrar Historia" del encabezado (los 3 modulos)
    'cierre'        => [[], 'cierre_validar', 'cierre_guardar', 'Historia cerrada.'],
];

/**
 * Pestaña de Consulta Externa (1 a 4 o 7) de cada campo con error de la consulta. Se queda en la pestaña del Guardar
 * pulsado ($preferida) si alguno de los errores está ahí; si no, va a la del primer error.
 */
function consulta_ce_pestana(array $errores, string $preferida = ''): string
{
    $antecedentes = ['FechRegl', 'FechPart', 'MetoDesc', 'FamiPare', 'FamiDiag', 'AlerTipo', 'AlerMedi', 'FarmMedi', 'FactTipo', 'RecoNomb'];
    foreach (ANTECEDENTES as $c => [, $desc]) {
        $antecedentes[] = $c;
        if ($desc !== null) $antecedentes[] = $desc;
    }
    $revision = array_merge(['ReviSist', 'EstaGene', 'PeriAbdo', 'PeriTorx', 'PeriCint', 'PeriCade'], array_keys(SINTOMATICOS), array_keys(SIGNOS_RANGOS),
                            array_keys(EXAMEN_SISTEMAS), array_column(EXAMEN_SISTEMAS, 1));
    $pestanas = [];
    foreach (array_keys($errores) as $campo) {
        if (in_array($campo, ['FechCons', 'HoraCons', 'TipoCons', 'FinaCons', 'MotiCons', 'EnfeActu'], true)) $pestanas[] = 'anamnesis';
        elseif (in_array($campo, ['ObseReco', 'Especif', 'ObserCd', 'Conducta', 'EstaCodo'], true)) $pestanas[] = 'plan';
        elseif (in_array($campo, $revision, true)) $pestanas[] = 'revision';
        elseif (in_array($campo, $antecedentes, true) || strncmp($campo, 'reco', 4) === 0) $pestanas[] = 'antecedentes';
        else $pestanas[] = 'laboratorios';
    }
    if ($preferida !== '' && in_array($preferida, $pestanas, true)) {
        return $preferida;
    }
    return $pestanas[0] ?? ($preferida !== '' ? $preferida : 'anamnesis');
}
$F = [];   // datos enviados por formulario (para volver a mostrarlos si hay errores)
$E = [];   // errores por formulario

// --- Guardar (POST) -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'admision') {
        if (!$pac) {
            flash('error', 'Paciente no encontrado. Búsquelo o créelo primero.');
            redirigir($base . '&nueva=1');
        }
        [$d, $eA] = admision_validar($mod, $pac);
        if (!$eA) {
            $cons = admision_crear($mod, $pac, $d, $u['Login']);
            flash('ok', "Admisión $cons creada.");
            redirigir('atencion.php?id=' . urlencode($cons));
        }
    } elseif ($accion === 'admision_modificar' && $a) {
        if (!$editable) {
            flash('error', 'La admisión está cerrada, anulada o ya se cargó a SIHOS: no se puede modificar.');
            redirigir($aqui);
        }
        [$d, $eA] = admision_validar($mod, $a, $a);
        if (!$eA) {
            admision_modificar($a, $mod, $d, $u['Login']);
            flash('ok', 'Admisión ' . $a['ConsAdmi'] . ' modificada.');
            redirigir($aqui);
        }
    } elseif ($a && isset(ACCIONES_HISTORIA[$accion])) {
        // Pestanas de la historia: validar en src/historia.php, guardar en una transaccion y volver a la pestana
        [$candidatas, $validar, $guardar, $mensaje] = ACCIONES_HISTORIA[$accion];
        if ($accion === 'nota') {
            $candidatas = [($_POST['NotaPestana'] ?? '') === 'medica' ? 'notas_medicas' : 'notas_enfermeria'];
        }
        $pest = null;
        foreach ($candidatas as $c) {
            if (in_array($c, $disponibles, true)) { $pest = $c; break; }
        }
        if (!$candidatas) {
            // Acciones del encabezado: se queda en la pestana actual
            $pest = $tab;
        }
        if (!$editable) {
            flash('error', 'La admisión no se puede modificar.');
            redirigir($aqui . '&tab=' . ($pest ?? $tab));
        }
        if ($pest === null) {
            flash('error', 'Esa pestaña no aplica en este módulo.');
            redirigir($aqui);
        }
        $tab = $pest;
        [$F[$accion], $E[$accion]] = $validar($a);
        if (!$E[$accion]) {
            $n = $guardar($a, $F[$accion], $accion === 'consulta' ? $u : $u['Login']);
            if ($accion === 'consulta') {
                // La consulta sigue abierta en el formulario (cada sección tiene su Guardar) hasta "Cerrar Consulta".
                // Se queda en la MISMA sección: en Consulta Externa, la pestaña del Guardar pulsado (1, 2, 3, 4 o 7);
                // en Urgencias/Observación, el acordeón (sec=) de la pestaña Consultas
                $boton = $F[$accion]['boton'] ?? '';
                $cerrada = $boton === 'cerrar';
                $secciones = ['anamnesis' => 'Anamnesis', 'antecedentes' => 'Antecedentes', 'revision' => 'Revisión por sistema y examen',
                              'laboratorios' => 'Laboratorios y diagnósticos', 'plan' => 'Plan de manejo'];
                if ($clave === 'ce' && in_array($boton, $disponibles, true)) {
                    $pest = $boton;
                }
                flash('ok', $cerrada ? "Consulta No. $n cerrada."
                    : (isset($secciones[$boton]) ? 'Sección ' . $secciones[$boton] . " guardada en la consulta No. $n." : "Consulta No. $n guardada."));
                redirigir($aqui . '&tab=' . $pest . ($cerrada ? '' : '&cons=' . $n . (isset($secciones[$boton]) ? '&sec=' . $boton : '')));
            }
            flash('ok', sprintf($mensaje, $n));
            redirigir($aqui . '&tab=' . $pest);
        }
        if ($accion === 'cierre' && isset($E['cierre']['egreso'])) {
            // Urgencias y Observacion: sin egreso no se cierra; se lleva a la pestana Egreso a llenarlo
            flash('aviso', $E['cierre']['egreso']);
            redirigir($aqui . '&tab=egreso');
        }
        if ($accion === 'consulta' && $clave === 'ce') {
            $tab = consulta_ce_pestana($E[$accion], (string) ($F[$accion]['boton'] ?? ''));
        }
    } elseif ($a && ($accion === 'triage' || $accion === 'signos')) {
        $ingreso = $a['FechIngr'] . ' ' . $a['HoraIngr'];
        if (!$editable) {
            flash('error', 'La admisión no se puede modificar.');
            redirigir($aqui);
        }
        if ($accion === 'triage') {
            $tab = 'triage';
            if (!$tieneTriage) {
                flash('error', 'El triage solo se registra en Urgencias.');
                redirigir($aqui);
            }
            if ($triage) {
                flash('aviso', 'La admisión ya tiene triage.');
                redirigir($aqui . '&tab=triage');
            }
            [$t, $et] = triage_validar();
            [$st, $es] = signos_validar(false, SIGNOS_OBLIGATORIOS_TRIAGE);
            $eT = $et + $es;
            if (!$eT && $t['FechTria'] . ' ' . $t['HoraTria'] < $ingreso) {
                $eT['HoraTria'] = 'El triage no puede ser anterior al ingreso (' . fecha_hora($ingreso) . ').';
            }
            if (!$eT) {
                triage_guardar($a, $t, $st, $u['Login']);
                flash('ok', 'Triage registrado.');
                redirigir($aqui . '&tab=triage');
            }
        } else {
            $tab = 'signos';
            [$sv, $eS] = signos_validar(true);
            if (!$eS && $sv['FechToma'] . ' ' . $sv['HoraToma'] < $ingreso) {
                $eS['HoraToma'] = 'La toma no puede ser anterior al ingreso (' . fecha_hora($ingreso) . ').';
            }
            // Como SIHOS: la toma debe ser POSTERIOR a la ultima registrada
            $ultima = $eS ? '' : signos_ultima_toma($a['ConsAdmi']);
            if ($ultima !== '' && $sv['FechToma'] . ' ' . $sv['HoraToma'] <= $ultima) {
                $eS['HoraToma'] = 'La fecha y hora de la toma no puede ser inferior o igual a los anteriormente digitados ('
                                . fecha_hora($ultima) . ').';
            }
            if (!$eS) {
                $n = signos_guardar($a, $sv, $u['Login']);
                flash('ok', "Toma de signos No. $n registrada.");
                redirigir($aqui . '&tab=signos');
            }
        }
    }
}

// --- Historias abiertas del modulo (ventana) -----------------------------
// Ventana "Historias": TODAS las admisiones del módulo (abiertas y cerradas; sin anuladas), filtro de estado
$estadoHist = in_array($_GET['estado'] ?? '', ['abiertas', 'cerradas'], true) ? $_GET['estado'] : 'todas';
$todas = admisiones_del_modulo($mod, $estadoHist);
$abiertasModulo = count(array_filter($todas, fn ($f) => (int) $f['Cerrado'] !== 1));
if ($estadoHist === 'cerradas') {
    $abiertasModulo = count(admisiones_abiertas($mod));
}
$serv = is_string($_GET['serv'] ?? null) && in_array($_GET['serv'], $mod['servicios'], true) ? $_GET['serv'] : '';
// Filtros de SIHOS: Seleccione Servicio · Mostrar N registros (el consultorio no se muestra al profesional:
// decision del usuario, ver docs/REGLAS.md)
$mostrar = in_array((int) ($_GET['mostrar'] ?? 25), [10, 25, 50, 100], true) ? (int) ($_GET['mostrar'] ?? 25) : 25;
$filas = array_values(array_filter($todas, fn ($f) => $serv === '' || $f['ServEgre'] === $serv));
$totalFiltradas = count($filas);
$filas = array_slice($filas, 0, $mostrar);
$historiasAbiertas = isset($_GET['historias']) || (!$a && !$buscado && !isset($_GET['nueva']) && !isset($_GET['buscar']));

// Ventana "Buscar" del encabezado (como SIHOS): por número de admisión, documento o nombre del paciente,
// abiertas y cerradas, de los 3 módulos. No se mezcla con el formulario de la admisión.
$buscarAbierto = isset($_GET['buscar']);
$bq = is_string($_GET['bq'] ?? null) ? mb_substr(trim($_GET['bq']), 0, 60) : '';
[$resBuscar, $totBuscar] = ($buscarAbierto && $bq !== '')
    ? admisiones_listado(['desde' => '', 'hasta' => '', 'modulo' => '', 'estado' => '', 'serv' => '', 'q' => $bq], 1, 50)
    : [[], 0];

// Campos de signos con prefijo en el id cuando conviven los formularios de triage y signos
$prefijoSignos = 'toma-';

// Datos del paciente para el encabezado
$p = $a ?? $pac;
[$ev, $eu] = ($p && ($p['FechNaci'] ?? '') && $p['FechNaci'] !== '0000-00-00') ? edad_sihos($p['FechNaci'], date('Y-m-d')) : ['', 'A'];
$edad = $a ? edad_texto($a['ValoEdad'], $a['UnidEdad']) : ($ev !== '' ? edad_texto($ev, $eu) : '');
$servicios = array_intersect_key(lista('Serv'), array_flip($mod['servicios']));

// Los avisos "guardado / no se guardó" van ABAJO, junto a Continuar (pie fijo), no arriba: así no hay que subir la página
vista_inicio($a ? 'Admisión ' . $a['ConsAdmi'] : $mod['nombre'], false);
?>
<section class="encabezado-trabajo" aria-label="Encabezado de la admisión">
    <!-- Barra como SIHOS: Admisión · Fecha · Hora · Autorización (Observación: Cama) · SOAT · estado -->
    <div class="et-barra">
        <div class="et-admision">
            <!-- Como SIHOS: se escribe el número de admisión y Enter; abre la historia aunque esté cerrada -->
            <form method="get" action="atencion.php" class="et-buscar-admision" role="search">
                <input type="hidden" name="modulo" value="<?= e($clave) ?>">
                <label class="et-etiqueta" for="adm">Admisión</label>
                <input type="text" id="adm" name="adm" value="<?= $a ? e($a['ConsAdmi']) : 'C' . date('ymd') ?>" maxlength="14" autocomplete="off"
                       placeholder="Número + Enter" title="Escriba el número de admisión y pulse Enter (abiertas y cerradas)"
                       inputmode="text" spellcheck="false">
            </form>
            <?php if ($a): [$estado, $claseEstado] = admision_estado($a); ?>
                <span class="etiqueta" title="Número temporal: al cargar a SIHOS se asigna el definitivo">Temporal</span>
            <?php endif; ?>
        </div>
        <?php if ($a && !$modificando): ?>
        <div class="et-barra-campos">
            <?= campo_lectura('Fecha', date('d/m/Y', strtotime($a['FechIngr'])), 'c-fecha') ?>
            <?= campo_lectura('Hora', substr($a['HoraIngr'], 0, 5), 'c-hora') ?>
            <?php if ($clave === 'obs'): ?>
                <?= campo_lectura('Cama', $a['CamaActu'], 'c-hora') ?>
            <?php else: ?>
                <?= campo_lectura('Autorización', $a['NumeAuto'], 'c-auto') ?>
            <?php endif; ?>
            <?= campo_lectura('SOAT', $a['NumePoli'], 'c-auto') ?>
            <span class="etiqueta etiqueta-<?= e($claseEstado) ?> et-estado"><?= e($estado) ?></span>
        </div>
        <?php elseif ($pac || $modificando): ?>
        <!-- Nueva admisión o Modificar: los campos de la barra pertenecen al formulario de la admisión (atributo form) -->
        <div class="et-barra-campos et-barra-edicion">
            <div class="c-fecha"><label for="FechIngr">Fecha</label>
                <input type="date" id="FechIngr" name="FechIngr" form="form-admision" value="<?= v($d, 'FechIngr') ?>" max="<?= date('Y-m-d') ?>" class="<?= ce($eA, 'FechIngr') ?>" required></div>
            <div class="c-hora"><label for="HoraIngr">Hora</label>
                <input type="time" id="HoraIngr" name="HoraIngr" form="form-admision" value="<?= e(substr($d['HoraIngr'], 0, 5)) ?>" class="<?= ce($eA, 'HoraIngr') ?>" required></div>
            <div class="c-auto"><label for="NumeAuto">Autorización</label>
                <input type="text" id="NumeAuto" name="NumeAuto" form="form-admision" value="<?= v($d, 'NumeAuto') ?>" maxlength="50"></div>
            <div class="c-auto"><label for="NumePoli">SOAT</label>
                <input type="text" id="NumePoli" name="NumePoli" form="form-admision" value="<?= v($d, 'NumePoli') ?>" maxlength="30" title="Póliza SOAT (accidentes de tránsito)"></div>
        </div>
        <?php else: ?>
        <div class="et-barra-campos">
            <?= campo_lectura('Fecha', '', 'c-fecha') ?><?= campo_lectura('Hora', '', 'c-hora') ?>
            <?= campo_lectura($clave === 'obs' ? 'Cama' : 'Autorización', '', 'c-auto') ?><?= campo_lectura('SOAT', '', 'c-auto') ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Documento: buscar paciente -->
    <form method="get" action="atencion.php" class="et-fila et-buscar" id="form-buscar" role="search">
        <input type="hidden" name="modulo" value="<?= e($clave) ?>">
        <div class="c-5">
            <label for="NumeUsua">Documento</label>
            <div class="doc-campos">
                <select id="TipoDocu" name="TipoDocu" aria-label="Tipo de documento">
                    <?php foreach (lista('TipoDocu') as $c => $n): ?><option value="<?= e($c) ?>" title="<?= e($n) ?>"<?= (string) $c === (string) $docTipo ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                </select>
                <input type="text" id="NumeUsua" name="NumeUsua" value="<?= e($docNum) ?>" maxlength="60" placeholder="Número o nombre" autocomplete="off"
                       data-buscar="pacientes" data-minimo="3" title="Escriba el documento o el nombre (desde 3 caracteres)"<?= (!$a && !$pac) ? ' autofocus' : '' ?>>
                <button type="submit" class="boton boton-primario" title="Buscar paciente"><?= icono('search') ?><span>Buscar</span></button>
                <a href="pacientes.php" class="boton boton-claro boton-puntos" title="Buscar por nombre" aria-label="Buscar paciente por nombre">…</a>
            </div>
        </div>
        <?= campo_lectura('Usuario', $p ? paciente_nombre($p) : '', 'c-3 et-nombre') ?>
        <?= campo_lectura('F. Nacimiento', $p && $p['FechNaci'] && $p['FechNaci'] !== '0000-00-00' ? date('d/m/Y', strtotime($p['FechNaci'])) : '', 'c-1') ?>
        <?= campo_lectura('Edad', $p ? $edad : '', 'c-1') ?>
        <?= campo_lectura('Género', $p ? lista_nombre('Sexo', $p['SexoUsua']) : '', 'c-1') ?>
        <?php if ($a): ?><?= campo_lectura('Grupo', lista_nombre('GrupAten', $a['GrupoAte']), 'c-1') ?><?php endif; ?>
    </form>

    <?php if ($buscado && !$pac): ?>
        <div class="et-aviso">
            <div class="alerta alerta-aviso"><?= icono('triangle-alert') ?><div>No existe un paciente con documento <?= e($docTipo . ' ' . $docNum) ?>.
                <a href="paciente_nuevo.php?TipoDocu=<?= e(urlencode($docTipo)) ?>&amp;NumeUsua=<?= e(urlencode($docNum)) ?>">Crear paciente nuevo</a>.</div></div>
        </div>
    <?php elseif ($pac && !empty($abiertaPac)): ?>
        <!-- El paciente ya tiene una admisión abierta: NO se abre sola; el profesional decide -->
        <div class="et-aviso">
            <div class="alerta alerta-aviso"><?= icono('triangle-alert') ?><div>
                Este paciente ya tiene la admisión <strong><?= e($abiertaPac['ConsAdmi']) ?></strong> abierta
                (ingreso <?= e(fecha_hora($abiertaPac['FechIngr'] . ' ' . $abiertaPac['HoraIngr'])) ?>).
                Para crear otra, primero cierre esa historia.
                <a href="atencion.php?id=<?= e(urlencode($abiertaPac['ConsAdmi'])) ?>" class="boton boton-claro boton-chico"><?= icono('file-text') ?>Abrir esa admisión</a>
            </div></div>
        </div>
    <?php endif; ?>

    <?php if ($a && !$modificando): ?>
        <!-- Admision cargada: filas del encabezado de SIHOS en modo lectura -->
        <div class="et-fila">
            <?= campo_lectura('Servicio Origen (C.Costos)', trim(($a['NombServOrig'] ?? $a['CodiServ']) . ($a['CentCost'] !== '' ? ' (' . $a['CentCost'] . ')' : '')), 'c-3') ?>
            <?= campo_lectura('Cama Origen', $a['CodiCama'], 'c-1') ?>
            <?= campo_lectura('Vía Ingreso', lista_nombre('ViaIngre', $a['ViaIngre']), 'c-2') ?>
            <?= campo_lectura('Servicio Actual', $a['NombServ'] ?? $a['ServEgre'], 'c-3') ?>
            <?= campo_lectura('Cama Actual', $a['CamaActu'], 'c-1') ?>
            <?= campo_lectura('Entorno de Atención', $a['EntoAten'], 'c-2') ?>
        </div>
        <div class="et-fila">
            <?= campo_lectura('Causa Externa', lista_nombre('CausExte', $a['CausExte']), 'c-3') ?>
            <?= campo_lectura('Estado Ingreso', lista_nombre('EstaIngr', $a['EstaIngr']), 'c-1') ?>
            <?= campo_lectura('Condición', lista_nombre('CondUsua', $a['CondUsua']), 'c-2') ?>
            <?= campo_lectura('Discapacidad', $a['NombDisc'] ?? 'Sin discapacidad', 'c-2') ?>
            <?php /* Solo lectura: Admision.DiagIngr o, si está vacío, el de la primera consulta o procedimiento */ ?>
            <?= campo_lectura('Diagnóstico', diag_texto($a['DiagIngr']), 'c-4') ?>
        </div>
        <!-- EPS, Contrato, Tipo de usuario, Afiliación y Categoría: ocultos en SIHOS, visibles en HSCJ (decisión del usuario) -->
        <div class="et-fila">
            <?= campo_lectura('EPS', $a['NombAdmi'] ?? $a['CodiAdmi'], 'c-4') ?>
            <?= campo_lectura('Contrato', $a['NumeCont'], 'c-2') ?>
            <?= campo_lectura('Tipo de usuario', lista_nombre('TipoUsua', $a['TipoUsua']), 'c-2') ?>
            <?= campo_lectura('Afiliación', lista_nombre('TipoAfil', $a['TipoAfil']), 'c-2') ?>
            <?= campo_lectura('Categoría', $a['CodiEstr'], 'c-2') ?>
        </div>

    <?php elseif ($pac || $modificando): ?>
        <!-- Paciente sin admision abierta (o "Modificar" de la admisión cargada): el encabezado queda editable para crearla aqui mismo -->
        <form method="post" action="<?= e($modificando ? $aqui : $urlBusqueda) ?>" class="formulario et-form" id="form-admision" data-una-vez>
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="<?= $modificando ? 'admision_modificar' : 'admision' ?>">
            <?= errores_resumen($eA) ?>
            <div class="et-fila">
                <div class="c-3"><label for="CodiServ">Servicio</label>
                    <select id="CodiServ" name="CodiServ" class="<?= ce($eA, 'CodiServ') ?>" required><?= opciones_arreglo($servicios, $d['CodiServ'], false) ?></select><?= me($eA, 'CodiServ') ?></div>
                <?php if ($mod['cama'] && $modificando): ?>
                <?= campo_lectura('Cama', $a['CamaActu'] . ' (se cambia en Cambio de Atención)', 'c-3') ?>
                <?php elseif ($mod['cama']): ?>
                <div class="c-3"><label for="CodiCama">Cama</label>
                    <select id="CodiCama" name="CodiCama" class="<?= ce($eA, 'CodiCama') ?>" required
                            data-depende="api.php?que=camas" data-de="CodiServ" data-param="serv">
                        <?= opciones_arreglo(camas($d['CodiServ']), $d['CodiCama'] ?? '') ?>
                    </select><?= me($eA, 'CodiCama') ?></div>
                <?php else: ?>
                <div class="c-3"><label for="ViaIngre">Vía de ingreso</label>
                    <select id="ViaIngre" name="ViaIngre" class="<?= ce($eA, 'ViaIngre') ?>" required><?= opciones('ViaIngre', $d['ViaIngre']) ?></select><?= me($eA, 'ViaIngre') ?></div>
                <?php endif; ?>
            </div>
            <div class="et-fila">
                <?php if ($mod['cama']): ?>
                <div class="c-2"><label for="ViaIngre">Vía de ingreso</label>
                    <select id="ViaIngre" name="ViaIngre" class="<?= ce($eA, 'ViaIngre') ?>" required><?= opciones('ViaIngre', $d['ViaIngre']) ?></select><?= me($eA, 'ViaIngre') ?></div>
                <?php endif; ?>
                <div class="<?= $mod['cama'] ? 'c-3' : 'c-3' ?>"><label for="CausExte">Causa externa</label>
                    <select id="CausExte" name="CausExte" class="<?= ce($eA, 'CausExte') ?>" required><?= opciones('CausExte', $d['CausExte'], true, true) ?></select><?= me($eA, 'CausExte') ?></div>
                <div class="<?= $mod['cama'] ? 'c-2' : 'c-3' ?>"><label for="CondUsua">Condición de la usuaria</label>
                    <select id="CondUsua" name="CondUsua" class="<?= ce($eA, 'CondUsua') ?>" required><?= opciones('CondUsua', $d['CondUsua']) ?></select><?= me($eA, 'CondUsua') ?></div>
                <div class="<?= $mod['cama'] ? 'c-2' : 'c-3' ?>"><label for="GrupoAte">Grupo poblacional</label>
                    <select id="GrupoAte" name="GrupoAte" class="<?= ce($eA, 'GrupoAte') ?>" required><?= opciones('GrupAten', $d['GrupoAte']) ?></select><?= me($eA, 'GrupoAte') ?></div>
                <!-- Diagnóstico de ingreso: no se digita; se llena solo con el de la primera consulta o procedimiento -->
                <?= campo_lectura('Diagnóstico de ingreso', 'Se toma de la primera consulta', 'c-3') ?>
            </div>
            <div class="et-fila">
                <div class="c-3"><label for="CodiAdmi">EPS</label>
                    <select id="CodiAdmi" name="CodiAdmi" class="<?= ce($eA, 'CodiAdmi') ?>" required><?= opciones('Admi', $d['CodiAdmi']) ?></select><?= me($eA, 'CodiAdmi') ?></div>
                <div class="c-3"><label for="NumeCont">Contrato</label>
                    <select id="NumeCont" name="NumeCont" class="<?= ce($eA, 'NumeCont') ?>" required
                            data-depende="api.php?que=contratos" data-de="CodiAdmi" data-param="admi">
                        <?= opciones_arreglo($d['CodiAdmi'] ? contratos($d['CodiAdmi']) : [], $d['NumeCont']) ?>
                    </select><?= me($eA, 'NumeCont') ?></div>
                <div class="c-2"><label for="TipoUsua">Tipo de usuario</label>
                    <select id="TipoUsua" name="TipoUsua" class="<?= ce($eA, 'TipoUsua') ?>" required><?= opciones('TipoUsua', $d['TipoUsua']) ?></select><?= me($eA, 'TipoUsua') ?></div>
                <div class="c-2"><label for="TipoAfil">Afiliación</label>
                    <select id="TipoAfil" name="TipoAfil" class="<?= ce($eA, 'TipoAfil') ?>" required><?= opciones('TipoAfil', $d['TipoAfil']) ?></select><?= me($eA, 'TipoAfil') ?></div>
                <div class="c-2"><label for="CodiEstr">Categoría</label>
                    <select id="CodiEstr" name="CodiEstr" class="<?= ce($eA, 'CodiEstr') ?>" required
                            data-depende="api.php?que=estratos&amp;aten=<?= (int) $mod['TipoAten'] ?>" data-de="CodiAdmi,TipoAfil" data-param="admi,afil">
                        <?= opciones_arreglo(($d['CodiAdmi'] && $d['TipoAfil']) ? estratos($d['CodiAdmi'], $mod['TipoAten'], $d['TipoAfil']) : [], $d['CodiEstr']) ?>
                    </select><?= me($eA, 'CodiEstr') ?></div>
            </div>
            <div class="et-fila">
                <!-- Sin "Motivo de ingreso": SIHOS no lo tiene en el encabezado (Admision.MotiCons se guarda '.') -->
                <div class="c-3"><label for="TipoAcom">Acompañante</label>
                    <select id="TipoAcom" name="TipoAcom" class="<?= ce($eA, 'TipoAcom') ?>" required><?= opciones('TipoAcom', $d['TipoAcom']) ?></select><?= me($eA, 'TipoAcom') ?></div>
                <div class="c-5"><label for="NombAcom">Nombre del acompañante</label>
                    <input type="text" id="NombAcom" name="NombAcom" value="<?= v($d, 'NombAcom') ?>" maxlength="80"></div>
                <div class="c-2"><label for="Parentes">Parentesco</label>
                    <select id="Parentes" name="Parentes" class="<?= ce($eA, 'Parentes') ?>"><?= opciones('Parentes', $d['Parentes']) ?></select><?= me($eA, 'Parentes') ?></div>
                <div class="c-2"><label for="TeleAcom">Teléfono</label>
                    <input type="text" id="TeleAcom" name="TeleAcom" value="<?= v($d, 'TeleAcom') ?>" maxlength="10" inputmode="tel"></div>
            </div>
        </form>

    <?php else: ?>
        <!-- Sin admision: campos vacios en modo lectura hasta buscar el documento -->
        <div class="et-fila et-vacia">
            <?= campo_lectura('Servicio Origen (C.Costos)', '', 'c-3') ?><?= campo_lectura('Cama Origen', '', 'c-1') ?><?= campo_lectura('Vía Ingreso', '', 'c-2') ?>
            <?= campo_lectura('Servicio Actual', '', 'c-3') ?><?= campo_lectura('Cama Actual', '', 'c-1') ?><?= campo_lectura('Entorno de Atención', '', 'c-2') ?>
            <?= campo_lectura('Causa Externa', '', 'c-3') ?><?= campo_lectura('Estado Ingreso', '', 'c-1') ?><?= campo_lectura('Condición', '', 'c-2') ?>
            <?= campo_lectura('Discapacidad', '', 'c-2') ?><?= campo_lectura('Diagnóstico', '', 'c-4') ?>
        </div>
        <p class="et-ayuda"><?= icono('info') ?><span>Para una admisión nueva escriba el <strong>documento</strong> del paciente. Para abrir una historia escriba su <strong>número de admisión</strong> y pulse <strong>Enter</strong>, o use <strong>Buscar</strong> (por admisión, documento o nombre) o <strong>Historias</strong>.</span></p>
    <?php endif; ?>
    <!-- Botones del encabezado de SIHOS (los que no aplican en contingencia, deshabilitados).
         La admisión se crea o se modifica SOLO con "Guardar" (Enter no envía el formulario). -->
    <div class="et-acciones-sihos">
        <a href="<?= e($base) ?>&amp;nueva=1" class="boton boton-nuevo-sihos"><?= icono('user-plus') ?>Nuevo</a>
        <?php if ($pac || $modificando): ?>
            <button type="submit" form="form-admision" class="boton boton-primario boton-guardar-sihos"><?= icono('save') ?>Guardar</button>
            <a href="<?= e($modificando ? $aqui : $base . '&nueva=1') ?>" class="boton boton-claro"><?= icono('x') ?>Cancelar</a>
        <?php endif; ?>
        <?php if ($a && $editable && !$modificando): ?>
            <a href="<?= e($aqui) ?>&amp;modificar=1" class="boton boton-claro"><?= icono('pencil') ?>Modificar</a>
        <?php elseif (!$modificando): ?>
            <button type="button" class="boton boton-claro" disabled title="<?= $a ? 'La historia no se puede modificar' : 'Cargue una historia' ?>"><?= icono('pencil') ?>Modificar</button>
        <?php endif; ?>
        <button type="button" class="boton boton-claro" disabled title="No disponible"><?= icono('trash-2') ?>Eliminar</button>
        <a href="<?= e($aqui) ?>&amp;buscar=1" class="boton boton-claro" data-abrir-ventana="buscar-historia"><?= icono('search') ?>Buscar</a>
        <button type="button" class="boton boton-claro" disabled title="No disponible"><?= icono('printer') ?>Imprimir</button>
        <a href="<?= e($base) ?>&amp;nueva=1" class="boton boton-claro" title="Vaciar el encabezado"><?= icono('x') ?>Limpiar</a>
        <button type="button" class="boton boton-claro" disabled title="No disponible"><?= icono('ban') ?>Anular</button>
        <?php if ($a && $editable && !$modificando): ?>
            <a href="<?= e($aqui) ?>&amp;tab=<?= e($tab) ?>&amp;cerrar=1" class="boton boton-peligro" data-abrir-ventana="cerrar-historia"><?= icono('log-out') ?>Cerrar Historia</a>
        <?php else: ?>
            <button type="button" class="boton boton-peligro" disabled title="<?= $a ? ($modificando ? 'Termine de modificar' : 'La historia ya está cerrada') : 'Cargue una historia' ?>"><?= icono('log-out') ?>Cerrar Historia</button>
        <?php endif; ?>
        <span class="et-sep"></span>
        <a href="<?= e($base) ?>&amp;historias=1" class="boton boton-claro" data-abrir-ventana="historias"><?= icono('clipboard-list') ?>Historias <span class="contador" title="Abiertas"><?= $abiertasModulo ?></span></a>
    </div>
</section>

<?php if ($a && !$editable): ?>
    <div class="alerta alerta-aviso"><?= icono('lock') ?><div>Esta admisión está cerrada, anulada o ya se cargó a SIHOS: solo se puede consultar.</div></div>
<?php endif; ?>

<?php
// Registros de cada pestana (para las listas y los contadores)
if ($a) {
    $consultas = consultas_de_admision($a['ConsAdmi']);
    $prescripciones = prescripciones_de_admision($a['ConsAdmi']);
    $ordenesMedicas = ordenes_medicas_de_admision($a['ConsAdmi']);
    $ordenes = ordenes_de_admision($a['ConsAdmi']);
    $procedimientos = procedimientos_de_admision($a['ConsAdmi']);
    $notas = notas_de_admision($a['ConsAdmi']);
    $prescritos = $editable ? medicamentos_prescritos($a['ConsAdmi']) : [];
    $aplicados = medicamentos_aplicados($a['ConsAdmi']);
    $evoluciones = evoluciones_de_admision($a['ConsAdmi']);
    $egreso = egreso_de_admision($a['ConsAdmi']);
    $materiales = materiales_de_admision($a['ConsAdmi']);
    $remisiones = remisiones_de_admision($a['ConsAdmi']);
    $incapacidades = incapacidades_de_admision($a['ConsAdmi']);
    $pendientes = $editable ? ordenes_pendientes($a['ConsAdmi']) : [];
    $tipoMedica = tipo_nota('medica');
    $nMedicas = count(array_filter($notas, fn ($n) => (string) $n['TipoNota'] === (string) $tipoMedica));
    $conteos = ['triage' => $triage ? 1 : 0, 'consulta' => count($consultas), 'anamnesis' => count($consultas),
                'signos' => count($signos), 'prescripcion' => count($prescripciones), 'ordenes_medicas' => count($ordenesMedicas),
                'ordenacion' => count($ordenes), 'procedimientos' => count($procedimientos),
                'notas_enfermeria' => count($notas) - $nMedicas, 'notas_medicas' => $nMedicas,
                'medicamentos' => count($aplicados), 'materiales' => count($materiales), 'remisiones' => count($remisiones),
                'incapacidad' => count($incapacidades), 'evolucion' => count($evoluciones),
                'plan' => count(array_filter($consultas, fn ($c) => trim((string) $c['ObseReco']) !== '')),
                'cambio' => $clave === 'obs' ? count(traslados_de_admision($a['ConsAdmi'])) : 0,
                'egreso' => $egreso ? 1 : 0];
}
if ($a) {
    pestanas_historia($a, $mod, $tab, $conteos);
}
?>

<?php if ($a): ?>

<?php if ($tieneTriage): ?>
<section id="triage" class="seccion panel" data-panel="triage"<?= $tab === 'triage' ? '' : ' hidden' ?>>
    <div class="panel-cabeza">
        <h2><?= icono('siren') ?><?= e(pestana_titulo($mod, 'triage')) ?></h2>
        <?php if ($triage): ?><span class="etiqueta etiqueta-abierta"><?= icono('circle-check') ?>Registrado</span>
        <?php endif; ?>
    </div>
    <?php if ($triage): ?>
        <dl class="datos">
            <dt>Clasificación</dt><dd><span class="etiqueta triage-<?= (int) $triage['ClasTria'] ?>"><?= e(lista_nombre('ClasTria', $triage['ClasTria'])) ?></span>
                · Conducta: <?= e(lista_nombre('CondTria', $triage['CondTria'])) ?></dd>
            <dt>Fecha y hora</dt><dd><?= e(fecha_hora($triage['FechTria'] . ' ' . $triage['HoraTria'])) ?> · <?= e($triage['UsuaDigi']) ?></dd>
            <dt>Motivo de consulta</dt><dd class="texto-largo"><?= e($triage['MotiCons']) ?></dd>
            <dt>Hallazgos clínicos</dt><dd class="texto-largo"><?= e($triage['HallClin']) ?></dd>
            <dt>Impresión diagnóstica</dt><dd><?= e($triage['CodiDiag'] ? $triage['CodiDiag'] . ' · ' . (diagnostico_nombre($triage['CodiDiag']) ?? '') : '—') ?></dd>
            <?php if (trim((string) $triage['Conducta']) !== ''): ?>
                <dt>Observaciones de conducta</dt><dd class="texto-largo"><?= e($triage['Conducta']) ?></dd>
            <?php endif; ?>
        </dl>
        <?php
        // Signos tomados en el triage: la toma con la misma fecha y hora (en SIHOS, toma No. 1)
        $signoTriage = null;
        foreach ($signos as $tomaSv) {
            if ($tomaSv['FechToma'] === $triage['FechTria'] && $tomaSv['HoraToma'] === $triage['HoraTria']) { $signoTriage = $tomaSv; }
        }
        if (!$signoTriage) {
            foreach ($signos as $tomaSv) { if ((int) $tomaSv['ConsSign'] === 1) { $signoTriage = $tomaSv; } }
        }
        ?>
        <h3 class="subtitulo-panel"><?= icono('heart-pulse') ?>Signos vitales del triage</h3>
        <?php if (!$signoTriage): ?>
            <div class="alerta vacio"><?= icono('info') ?><div>No se encontró la toma de signos del triage.</div></div>
        <?php else: $st1 = $signoTriage; ?>
        <?= tabla_signos([$st1], false) ?>
        <?php endif; ?>
    <?php elseif (!$editable): ?>
        <div class="alerta vacio"><?= icono('info') ?><div>El paciente aún no tiene triage.</div></div>
    <?php else: ?>
        <div class="panel-cuerpo">
        <?= errores_resumen($eT) ?>
        <form method="post" action="<?= e($aqui) ?>&amp;tab=triage" class="formulario formulario-panel" data-signos data-una-vez>
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="triage">
            <div class="barra-registro">
                <div class="br-fecha"><?= campos_fecha_hora('FechTria', 'HoraTria', $t, $eT) ?></div>
                <div class="br-campo br-profesional"><label>Profesional</label><span><?= e($u['Nombre']) ?></span></div>
            </div>
            <label for="MotiCons">Motivo <span class="obligatorio" aria-hidden="true">*</span></label>
            <textarea id="MotiCons" name="MotiCons" rows="2" maxlength="5000" class="<?= ce($eT, 'MotiCons') ?>" required><?= v($t, 'MotiCons') ?></textarea><?= me($eT, 'MotiCons') ?>
            <div class="subgrupo">
                <h3><?= icono('heart-pulse') ?>Signos vitales</h3>
                <?php campos_signos($st, $eT, '', SIGNOS_OBLIGATORIOS_TRIAGE); ?>
            </div>
            <label for="HallClin">Hallazgos Clínicos <span class="obligatorio" aria-hidden="true">*</span></label>
            <textarea id="HallClin" name="HallClin" rows="4" maxlength="5000" class="<?= ce($eT, 'HallClin') ?>" required><?= v($t, 'HallClin') ?></textarea><?= me($eT, 'HallClin') ?>
            <div class="rejilla">
                <div><label for="CodiDiag">Impresión Diagnóstica (CIE-10)</label>
                    <input type="text" id="CodiDiag" name="CodiDiag" value="<?= v($t, 'CodiDiag') ?>" maxlength="8" data-diagnostico autocomplete="off" class="<?= ce($eT, 'CodiDiag') ?>" placeholder="Código o nombre">
                    <div class="nota-campo" id="CodiDiag-nombre"><?= e(diagnostico_nombre($t['CodiDiag'] ?? '') ?? '') ?></div><?= me($eT, 'CodiDiag') ?></div>
                <div><label for="ClasTria">Clasificación</label>
                    <select id="ClasTria" name="ClasTria" class="<?= ce($eT, 'ClasTria') ?>" required><?= opciones_arreglo(lista('ClasTria'), $t['ClasTria'] ?? '') ?></select><?= me($eT, 'ClasTria') ?></div>
                <div><label for="CondTria">Conducta</label>
                    <select id="CondTria" name="CondTria" class="<?= ce($eT, 'CondTria') ?>" required><?= opciones('CondTria', $t['CondTria'] ?? '') ?></select><?= me($eT, 'CondTria') ?></div>
            </div>
            <textarea id="Conducta" name="Conducta" rows="2" maxlength="5000" aria-label="Texto de la conducta"><?= v($t, 'Conducta') ?></textarea>
            <?= botonera(['Guardar', 'Modificar', 'Imprimir', 'Consultar']) ?>
        </form>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<section id="signos" class="seccion panel" data-panel="signos"<?= $tab === 'signos' ? '' : ' hidden' ?>>
    <div class="panel-cabeza">
        <div>
            <h2><?= icono('heart-pulse') ?><?= e(pestana_titulo($mod, 'signos')) ?></h2>
            <p><?= count($signos) ?> <?= count($signos) === 1 ? 'toma registrada' : 'tomas registradas' ?></p>
        </div>
    </div>
    <?php if ($editable): ?>
        <div class="panel-cuerpo">
        <?= errores_resumen($eS) ?>
        <form method="post" action="<?= e($aqui) ?>&amp;tab=signos" class="formulario formulario-panel" data-signos data-una-vez>
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="signos">
            <?php
            $tomasAnt = [];
            foreach ($signos as $s) { $tomasAnt['reg-signos-' . (int) $s['ConsSign']] = (int) $s['ConsSign'] . ' · ' . fecha_hora($s['FechToma'] . ' ' . $s['HoraToma']); }
            ?>
            <?= barra_registro('Nuevo', ['-'], 'FechToma', 'HoraToma', $sv, $eS) ?>
            <div class="subgrupo">
                <?php campos_signos($sv, $eS, $prefijoSignos, []); ?>
            </div>
            <?= botonera(['Guardar', 'Cancelar', 'Imprimir']) ?>
        </form>
        </div>
    <?php endif; ?>

    <h3 class="titulo-tabla"><?= icono('history') ?>Tomas anteriores</h3>
    <?php if (!$signos): ?>
        <div class="alerta vacio"><?= icono('info') ?><div>No hay tomas de signos vitales.</div></div>
    <?php else: ?>
    <?= tabla_signos($signos, true) ?>
    <?php endif; ?>
</section>
<?php
// Panel de cada pestana disponible (triage y signos estan arriba). Archivo de la vista por pestana:
$vistas = ['consulta' => 'consulta', 'anamnesis' => 'consulta', 'prescripcion' => 'prescripcion',
           'ordenes_medicas' => 'ordenes_medicas', 'ordenacion' => 'ordenacion', 'procedimientos' => 'procedimientos',
           'evolucion' => 'evolucion', 'notas_enfermeria' => 'notas', 'notas_medicas' => 'notas',
           'medicamentos' => 'medicamentos', 'materiales' => 'materiales', 'remisiones' => 'remisiones',
           'incapacidad' => 'incapacidad', 'egreso' => 'egreso', 'plan' => 'plan', 'cambio' => 'cambio'];
foreach ($disponibles as $vista) {
    // En Consulta Externa el Plan de Manejo (7) lo pinta el formulario de la consulta
    if (!isset($vistas[$vista]) || ($vista === 'plan' && $clave === 'ce')) {
        continue;
    }
    // Cada panel se pinta en su propia funcion para que sus variables no pisen las de esta pagina
    (function () use ($vista, $vistas, $a, $mod, $u, $editable, $aqui, $tab, $F, $E, $triage, $consultas, $prescripciones,
                      $ordenesMedicas, $ordenes, $procedimientos, $notas, $prescritos, $aplicados, $evoluciones, $egreso,
                      $materiales, $remisiones, $incapacidades, $pendientes) {
        require __DIR__ . '/../src/vistas/pestana_' . $vistas[$vista] . '.php';
    })();
}
?>
<?php endif; ?>

<!-- Pie de la pantalla de trabajo (fijo abajo): Volver, el aviso de "guardado / no se guardó" y Continuar, como en SIHOS -->
<?php
$erroresPantalla = [];
foreach ([$eA, $eT, $eS] as $lista) { $erroresPantalla = array_merge($erroresPantalla, array_values($lista)); }
foreach ($E as $lista) { $erroresPantalla = array_merge($erroresPantalla, array_values($lista)); }
$avisos = avisos_flash();
if ($erroresPantalla) {
    $avisos .= '<div class="alerta alerta-error" role="alert">' . icono('circle-alert') . '<div><strong>No se guardó.</strong> '
             . e(implode(' · ', array_slice($erroresPantalla, 0, 3))) . (count($erroresPantalla) > 3 ? ' · (' . (count($erroresPantalla) - 3) . ' más)' : '')
             . '</div></div>';
}
?>
<div class="pie-trabajo"<?= $avisos !== '' ? ' data-con-aviso' : '' ?>>
    <a href="<?= e($base) ?>&amp;historias=1" class="boton boton-claro" data-abrir-ventana="historias"><?= icono('arrow-left') ?>Volver a historias</a>
    <div class="pie-avisos" aria-live="polite"><?= $avisos ?></div>
    <?php if ($a): $pos = array_search($tab, $disponibles, true); $siguiente = $disponibles[$pos + 1] ?? null; ?>
        <a href="<?= e($aqui) ?>&amp;tab=<?= e($siguiente ?? '') ?>" class="boton boton-primario" data-continuar data-tab="<?= e($siguiente ?? '') ?>"<?= $siguiente ? '' : ' hidden' ?>>Continuar <?= icono('chevron-right') ?></a>
    <?php endif; ?>
</div>

<?php if ($a && $editable): $egresoCierre = $clave === 'ce' ? null : egreso_de_admision($a['ConsAdmi']); ?>
<!-- Ventana "Cerrar Historia" (botón del encabezado en los 3 módulos): confirmación en la página -->
<div class="ventana" id="cerrar-historia" role="dialog" aria-modal="true" aria-labelledby="cerrar-titulo"<?= isset($_GET['cerrar']) ? '' : ' hidden' ?>>
    <div class="ventana-caja ventana-chica">
        <div class="ventana-cabeza">
            <h2 id="cerrar-titulo"><?= icono('log-out') ?>Cerrar Historia</h2>
            <a href="<?= e($aqui) ?>&amp;tab=<?= e($tab) ?>" class="boton-icono" data-cerrar-ventana aria-label="Cerrar"><?= icono('x') ?></a>
        </div>
        <form method="post" action="<?= e($aqui) ?>&amp;tab=<?= e($tab) ?>" class="formulario ventana-cuerpo" data-una-vez>
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="cierre">
            <?php if ($clave !== 'ce' && !$egresoCierre): ?>
                <div class="alerta alerta-aviso"><?= icono('triangle-alert') ?><div>Esta historia aún no tiene egreso. Al continuar se abre la pestaña Egreso para registrarlo.</div></div>
            <?php else: ?>
                <p>¿Cerrar la historia de la admisión <strong><?= e($a['ConsAdmi']) ?></strong>? Quedará cerrada y ya no se podrá modificar<?= $mod['cama'] ? '; la cama ' . e($a['CamaActu']) . ' queda libre' : '' ?>.</p>
            <?php endif; ?>
            <div class="acciones">
                <button type="submit" class="boton boton-peligro"><?= icono('log-out') ?><?= ($clave !== 'ce' && !$egresoCierre) ? 'Ir a Egreso' : 'Cerrar Historia' ?></button>
                <a href="<?= e($aqui) ?>&amp;tab=<?= e($tab) ?>" class="boton boton-claro" data-cerrar-ventana>Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($a && $clave !== 'ce' && $_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['tab']) && !isset($_GET['cerrar']) && !isset($_GET['historias'])):
    $alerta = antecedentes_alerta($a['TipoDocu'], $a['NumeUsua']); ?>
<!-- Ventana automática al abrir la historia (Urgencias y Observación), como SIHOS: antecedentes tóxicos,
     alérgicos y reconciliación medicamentosa del paciente -->
<div class="ventana" id="alertas-paciente" role="dialog" aria-modal="true" aria-labelledby="alertas-titulo">
    <div class="ventana-caja ventana-chica">
        <div class="ventana-cabeza">
            <h2 id="alertas-titulo"><?= icono('triangle-alert') ?><?= e(paciente_nombre($a)) ?></h2>
            <a href="<?= e($aqui) ?>&amp;tab=<?= e($tab) ?>" class="boton-icono" data-cerrar-ventana aria-label="Cerrar"><?= icono('x') ?></a>
        </div>
        <div class="ventana-cuerpo alertas-paciente">
            <?php foreach (['toxicos' => 'Antecedentes Tóxicos', 'alergicos' => 'Antecedentes Alérgicos'] as $k => $titulo): ?>
                <h3 class="subtitulo-panel"><?= e($titulo) ?></h3>
                <?php if (!$alerta[$k]): ?>
                    <p class="nota-campo">No refiere.</p>
                <?php else: ?>
                    <ul class="lista-alertas"><?php foreach ($alerta[$k] as $x): ?>
                        <li><?= texto_registro($x['texto']) ?> <small>(<?= e($x['ConsAdmi']) ?> · <?= e(date('d/m/Y', strtotime($x['FechDigi']))) ?>)</small></li>
                    <?php endforeach; ?></ul>
                <?php endif; ?>
            <?php endforeach; ?>
            <h3 class="subtitulo-panel">Reconciliación Medicamentosa</h3>
            <?php $recoAlerta = reconciliacion_de_paciente($a['TipoDocu'], $a['NumeUsua']); ?>
            <?php if (!$recoAlerta): ?>
                <p class="nota-campo">Sin registros.</p>
            <?php else: ?>
                <ul class="lista-alertas"><?php foreach ($recoAlerta as $r): ?>
                    <li><?= e($r['NombSumi']) ?> · <?= e((float) $r['CantSumi']) ?> · cada <?= (int) $r['FrecApli'] ?> h · <?= e(lista_nombre('ViaAdmi', $r['ViaAdmin'])) ?>
                        <small>(<?= e($r['NotaSumi']) ?> · <?= e($r['ConsAdmi']) ?>)</small></li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
            <div class="acciones"><a href="<?= e($aqui) ?>&amp;tab=<?= e($tab) ?>" class="boton boton-primario" data-cerrar-ventana>Aceptar</a></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Ventana "Historias" del modulo (como la de SIHOS, con abiertas y cerradas) -->
<div class="ventana" id="historias" role="dialog" aria-modal="true" aria-labelledby="historias-titulo"<?= $historiasAbiertas ? '' : ' hidden' ?>>
    <div class="ventana-caja">
        <div class="ventana-cabeza">
            <h2 id="historias-titulo"><?= icono(MODULOS_ICONO[$clave]) ?>Historias · <?= e($mod['nombre']) ?> <span class="contador" title="Total mostrado"><?= count($filas) ?></span></h2>
            <a href="<?= e($aqui) ?><?= $a ? '' : '&amp;nueva=1' ?>" class="boton-icono" data-cerrar-ventana aria-label="Cerrar"><?= icono('x') ?></a>
        </div>
        <form method="get" action="atencion.php" class="buscador filtros" data-auto-envio>
            <input type="hidden" name="modulo" value="<?= e($clave) ?>">
            <input type="hidden" name="historias" value="1">
            <select name="serv" aria-label="Servicio" class="filtro-servicio">
                <option value="">Seleccione Servicio</option>
                <?php foreach ($servicios as $c => $n): ?>
                    <option value="<?= e($c) ?>"<?= $serv === (string) $c ? ' selected' : '' ?>><?= e($n) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="estado" aria-label="Estado" class="filtro-estado">
                <?php foreach (['todas' => 'Todas', 'abiertas' => 'Abiertas', 'cerradas' => 'Cerradas'] as $k => $n): ?>
                    <option value="<?= $k ?>"<?= $estadoHist === $k ? ' selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
            </select>
            <label class="mostrar">Mostrar <select name="mostrar" aria-label="Registros por página">
                <?php foreach ([10, 25, 50, 100] as $m): ?><option value="<?= $m ?>"<?= $m === $mostrar ? ' selected' : '' ?>><?= $m ?></option><?php endforeach; ?>
            </select> registros</label>
            <noscript><button type="submit" class="boton boton-claro">Filtrar</button></noscript>
        </form>
        <div class="ventana-cuerpo">
        <?php if (!$filas): ?>
            <div class="alerta vacio"><?= icono('info') ?><div><?= $todas ? 'Ninguna historia coincide con el filtro.' : 'No hay ' . ['todas' => 'admisiones', 'abiertas' => 'admisiones abiertas', 'cerradas' => 'admisiones cerradas'][$estadoHist] . ' en ' . e($mod['nombre']) . '.' ?></div></div>
        <?php else: ?>
        <div class="tabla-contenedor tabla-tarjetas">
        <!-- Columnas de SIHOS: Servicio · Cama · Admisión · Fecha · Duración · T · Autoriza. · Triage · Med · Ord ·
             Paciente · Edad · Estado · Profesional; filas coloreadas por triage. Sin Consultorio: decisión del usuario -->
        <table class="tabla tabla-historias">
            <thead><tr>
                <th>Servicio</th><th>Cama</th><th>Admisión</th><th>Fecha</th><th>Duración</th><th title="Tipo de contrato: E = Evento, C = Cápita">T</th>
                <th>Autoriza.</th><th>Triage</th><th title="Medicamentos: pendientes por aplicar (jeringa) o aplicados (visto)">Med</th>
                <th title="Órdenes pendientes">Ord</th><th>Paciente</th><th>Edad</th><th>Estado</th><th>Profesional</th>
            </tr></thead>
            <tbody>
            <?php foreach ($filas as $f): $url = 'atencion.php?id=' . urlencode($f['ConsAdmi']); $ct = (int) $f['ClasTria']; $cerr = (int) $f['Cerrado'] === 1; ?>
                <tr data-href="<?= e($url) ?>" class="<?= $cerr ? 'fila-cerrada' : ($ct ? 'fila-triage-' . $ct : '') ?><?= $a && $a['ConsAdmi'] === $f['ConsAdmi'] ? ' fila-actual' : '' ?>">
                    <td data-etiqueta="Servicio"><?= e($f['NombServ'] ?? $f['ServEgre']) ?></td>
                    <td data-etiqueta="Cama"><?= e($f['CamaActu'] ?: '') ?></td>
                    <td data-etiqueta="Admisión" class="celda-codigo"><a href="<?= e($url) ?>"><?= e($f['ConsAdmi']) ?></a>
                        <?php [$et, $ec] = admision_estado($f); ?><small class="bloque"><span class="etiqueta etiqueta-<?= e($ec) ?>"><?= e($et) ?></span></small></td>
                    <td data-etiqueta="Fecha" class="sin-salto"><?= e(fecha_hora($f['FechIngr'] . ' ' . $f['HoraIngr'])) ?></td>
                    <td data-etiqueta="Duración" class="sin-salto"><?= $cerr ? '' : e(duracion_desde($f['FechIngr'], $f['HoraIngr'])) ?></td>
                    <td data-etiqueta="T"><?php $tc = ['1' => 'E', '2' => 'C'][(string) $f['TipoCont']] ?? ''; ?><?php if ($tc): ?><span class="etiqueta" title="<?= $tc === 'E' ? 'Evento' : 'Cápita' ?>"><?= $tc ?></span><?php endif; ?></td>
                    <td data-etiqueta="Autoriza."><?= e($f['NumeAuto']) ?></td>
                    <td data-etiqueta="Triage"><?php if ($ct): ?><span class="etiqueta triage-<?= $ct ?>"><?= e(triage_romano($ct)) ?></span>
                        <?php elseif ($tieneTriage): ?><a href="<?= e($url) ?>&amp;tab=triage" class="sin-triage"><?= icono('circle-alert') ?>Sin triage</a><?php endif; ?></td>
                    <!-- Med / Ord: indicadores con ícono como SIHOS (no son datos) -->
                    <td data-etiqueta="Med"><?php if ((int) $f['med_pend']): ?><span class="indicador indicador-pendiente" title="<?= (int) $f['med_pend'] ?> medicamento(s) pendiente(s) por aplicar"><?= icono('syringe') ?></span>
                        <?php elseif ((int) $f['med_total']): ?><span class="indicador indicador-ok" title="Medicamentos aplicados"><?= icono('circle-check') ?></span><?php endif; ?></td>
                    <td data-etiqueta="Ord"><?php if ((int) $f['ord_pend']): ?><span class="indicador indicador-pendiente" title="<?= (int) $f['ord_pend'] ?> ítem(s) de órdenes pendiente(s)"><?= icono('flask-conical') ?></span><?php endif; ?></td>
                    <td class="celda-principal celda-paciente" data-etiqueta="Paciente"><a href="<?= e($url) ?>"><?= e(paciente_nombre($f)) ?></a>
                        <small class="bloque"><?= e($f['TipoDocu'] . ' ' . $f['NumeUsua']) ?></small></td>
                    <td data-etiqueta="Edad" class="sin-salto"><?= e(edad_texto($f['ValoEdad'], $f['UnidEdad'])) ?></td>
                    <td data-etiqueta="Estado"><span class="etiqueta etiqueta-<?= e($ec) ?>"><?= e($et) ?></span></td>
                    <td data-etiqueta="Profesional"><?= e($f['UsuaDigi']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="nota-campo">Mostrando <?= count($filas) ?> de <?= $totalFiltradas ?> registros.</p>
        <?php endif; ?>
        </div>
    </div>
</div>
<!-- Ventana "Buscar" del encabezado (como SIHOS): admisión, documento o nombre; abiertas y cerradas de los 3 módulos -->
<div class="ventana" id="buscar-historia" role="dialog" aria-modal="true" aria-labelledby="buscar-titulo"<?= $buscarAbierto ? '' : ' hidden' ?>>
    <div class="ventana-caja">
        <div class="ventana-cabeza">
            <h2 id="buscar-titulo"><?= icono('search') ?>Buscar historia</h2>
            <a href="<?= e($aqui) ?><?= $a ? '' : '&amp;nueva=1' ?>" class="boton-icono" data-cerrar-ventana aria-label="Cerrar"><?= icono('x') ?></a>
        </div>
        <form method="get" action="atencion.php" class="buscador buscar-historia">
            <input type="hidden" name="modulo" value="<?= e($clave) ?>">
            <input type="hidden" name="buscar" value="1">
            <div class="buscador-campo"><?= icono('search') ?>
                <input type="text" name="bq" value="<?= e($bq) ?>" placeholder="Número de admisión, documento o nombre del paciente" aria-label="Buscar historia" autocomplete="off"<?= $buscarAbierto ? ' autofocus' : '' ?>>
            </div>
            <button type="submit" class="boton boton-primario"><?= icono('search') ?>Buscar</button>
        </form>
        <div class="ventana-cuerpo">
        <?php if ($bq === ''): ?>
            <div class="alerta vacio"><?= icono('info') ?><div>Escriba el <strong>número de admisión</strong> (ej. C26092800001), el <strong>documento</strong> o el <strong>nombre</strong> del paciente. Salen las historias abiertas y cerradas de los tres módulos.</div></div>
        <?php elseif (!$resBuscar): ?>
            <div class="alerta vacio"><?= icono('info') ?><div>No se encontró ninguna historia con “<?= e($bq) ?>”.</div></div>
        <?php else: ?>
        <div class="tabla-contenedor tabla-tarjetas">
        <table class="tabla tabla-historias">
            <thead><tr><th>Admisión</th><th>Paciente</th><th>Ingreso</th><th>Servicio</th><th>Diagnóstico</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($resBuscar as $r): $url = 'atencion.php?id=' . urlencode($r['ConsAdmi']); [$er, $cr] = admision_estado($r); ?>
                <tr data-href="<?= e($url) ?>"<?= (int) $r['Cerrado'] === 1 ? ' class="fila-cerrada"' : '' ?>>
                    <td data-etiqueta="Admisión" class="celda-codigo"><a href="<?= e($url) ?>"><?= e($r['ConsAdmi']) ?></a></td>
                    <td class="celda-principal celda-paciente" data-etiqueta="Paciente"><a href="<?= e($url) ?>"><?= e(paciente_nombre($r)) ?></a>
                        <small class="bloque"><?= e($r['TipoDocu'] . ' ' . $r['NumeUsua']) ?></small></td>
                    <td data-etiqueta="Ingreso" class="sin-salto"><?= e(fecha_hora($r['FechIngr'] . ' ' . $r['HoraIngr'])) ?></td>
                    <td data-etiqueta="Servicio"><?= e($r['NombServ'] ?? $r['ServEgre']) ?></td>
                    <td data-etiqueta="Diagnóstico"><?= e(diag_texto(diag_ingreso($r))) ?></td>
                    <td data-etiqueta="Estado"><span class="etiqueta etiqueta-<?= e($cr) ?>"><?= e($er) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="nota-campo">Mostrando <?= count($resBuscar) ?> de <?= $totBuscar ?> historias.</p>
        <?php endif; ?>
        </div>
    </div>
</div>
<script>
// La admisión se crea o se modifica solo con el botón "Guardar": Enter en un campo no envía el formulario
document.querySelectorAll('#form-admision input, input[form="form-admision"]').forEach(function (campo) {
    campo.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); } });
});
</script>
<script src="js/formularios.js"></script>
<?php
vista_fin();
