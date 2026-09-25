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
 * orden_medica | ordenes | procedimiento | nota | medicamento | evolucion | egreso.
 * Los paneles de las pestanas 2 y 4 a 9 estan en src/vistas/pestana_*.php.
 * Siempre se vuelve a esta pantalla (POST y redireccion).
 */
require __DIR__ . '/../src/inicio.php';
require __DIR__ . '/../src/historia.php';
require __DIR__ . '/../src/formulario.php';

$u = requiere_login();

// --- Admision cargada y modulo -------------------------------------------
$a = null;
if (isset($_GET['id'])) {
    $a = admision_o_404($_GET['id']);
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
    if ($pac) {
        $abierta = admision_abierta_de_paciente($pac['TipoDocu'], $pac['NumeUsua']);
        if ($abierta) {
            flash('aviso', 'El paciente ya tiene una admisión abierta.');
            redirigir('atencion.php?id=' . urlencode($abierta['ConsAdmi']));
        }
    }
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
$aqui = $a ? 'atencion.php?id=' . urlencode($a['ConsAdmi']) : $base;
$triage = $a ? triage_de_admision($a['ConsAdmi']) : null;
$signos = $a ? signos_de_admision($a['ConsAdmi']) : [];
$tieneTriage = (bool) $mod['triage'];

$disponibles = $a ? pestanas_disponibles($mod) : [];
$tab = in_array($_GET['tab'] ?? '', $disponibles, true) ? $_GET['tab']
     : (($tieneTriage && $a && !$triage) ? 'triage' : 'signos');

$t  = ['FechTria' => date('Y-m-d'), 'HoraTria' => date('H:i'), 'CodiDiag' => $a['DiagIngr'] ?? '', 'CondTria' => '1'];
$st = [];
$eT = [];
$ultima = $signos[0] ?? null;
$sv = ['FechToma' => date('Y-m-d'), 'HoraToma' => date('H:i'), 'Peso' => $ultima['Peso'] ?? '', 'Talla' => $ultima['Talla'] ?? ''];
$eS = [];

// Acciones de las pestanas nuevas: accion => [pestana, validar, guardar, mensaje]
const ACCIONES_HISTORIA = [
    'consulta'      => ['consulta', 'consulta_validar', 'consulta_guardar', 'Consulta No. %d registrada.'],
    'prescripcion'  => ['prescripcion', 'prescripcion_validar', 'prescripcion_guardar', 'Prescripción No. %d registrada.'],
    'orden_medica'  => ['ordenes', 'orden_medica_validar', 'orden_medica_guardar', 'Orden médica No. %d registrada.'],
    'ordenes'       => ['ordenes', 'ordenes_validar', 'ordenes_guardar', 'Orden No. %d registrada.'],
    'procedimiento' => ['procedimientos', 'procedimiento_validar', 'procedimiento_guardar', 'Procedimiento No. %d registrado.'],
    'nota'          => ['notas', 'nota_validar', 'nota_guardar', 'Nota No. %d registrada.'],
    'medicamento'   => ['notas', 'medicamento_validar', 'medicamento_guardar', 'Aplicación de medicamento No. %d registrada.'],
    'evolucion'     => ['evolucion', 'evolucion_validar', 'evolucion_guardar', 'Evolución No. %d registrada.'],
    'egreso'        => ['egreso', 'egreso_validar', 'egreso_guardar', 'Admisión cerrada: egreso registrado.'],
    'material'      => ['notas', 'material_validar', 'material_guardar', 'Material No. %d registrado.'],
    'remision'      => ['egreso', 'remision_validar', 'remision_guardar', 'Remisión No. %d registrada.'],
    'incapacidad'   => ['egreso', 'incapacidad_validar', 'incapacidad_guardar', 'Incapacidad No. %d registrada.'],
    // Traslado de cama: se hace desde el encabezado; vuelve a la pestaña en la que se estaba
    'traslado'      => ['', 'traslado_validar', 'traslado_guardar', 'Traslado de cama No. %d registrado.'],
];
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
    } elseif ($a && isset(ACCIONES_HISTORIA[$accion])) {
        // Pestanas 2 y 4 a 9: validar en src/historia.php, guardar en una transaccion y volver a la pestana
        [$pest, $validar, $guardar, $mensaje] = ACCIONES_HISTORIA[$accion];
        if ($pest === '') {
            $pest = $tab;
            if (!$mod['cama']) {
                flash('error', 'El traslado de cama solo aplica en Observación e Internación.');
                redirigir($aqui);
            }
        }
        $tab = $pest;
        if (!$editable) {
            flash('error', 'La admisión no se puede modificar.');
            redirigir($aqui . '&tab=' . $pest);
        }
        if (!in_array($pest, $disponibles, true)) {
            flash('error', 'Esa pestaña no aplica en este módulo.');
            redirigir($aqui);
        }
        [$F[$accion], $E[$accion]] = $validar($a);
        if (!$E[$accion]) {
            $n = $guardar($a, $F[$accion], $accion === 'consulta' ? $u : $u['Login']);
            flash('ok', sprintf($mensaje, $n));
            redirigir($aqui . '&tab=' . $pest);
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
            [$st, $es] = signos_validar(false);
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
            if (!$eS) {
                $n = signos_guardar($a, $sv, $u['Login']);
                flash('ok', "Toma de signos No. $n registrada.");
                redirigir($aqui . '&tab=signos');
            }
        }
    }
}

// --- Historias abiertas del modulo (ventana) -----------------------------
$todas = admisiones_abiertas($mod);
$serv = is_string($_GET['serv'] ?? null) && in_array($_GET['serv'], $mod['servicios'], true) ? $_GET['serv'] : '';
$q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 60) : '';
$filas = array_values(array_filter($todas, function ($f) use ($serv, $q) {
    if ($serv !== '' && $f['ServEgre'] !== $serv) {
        return false;
    }
    if ($q === '') {
        return true;
    }
    $b = mb_strtoupper($q);
    return str_contains($f['NumeUsua'], $q) || str_contains($f['ConsAdmi'], $b)
        || str_contains(mb_strtoupper(paciente_nombre($f)), $b);
}));
$historiasAbiertas = isset($_GET['historias']) || (!$a && !$buscado && !isset($_GET['nueva']));

// Campos de signos con prefijo en el id cuando conviven los formularios de triage y signos
$prefijoSignos = ($a && $tieneTriage && !$triage && $editable) ? 'toma-' : '';

// Datos del paciente para el encabezado
$p = $a ?? $pac;
[$ev, $eu] = ($p && ($p['FechNaci'] ?? '') && $p['FechNaci'] !== '0000-00-00') ? edad_sihos($p['FechNaci'], date('Y-m-d')) : ['', 'A'];
$edad = $a ? edad_texto($a['ValoEdad'], $a['UnidEdad']) : ($ev !== '' ? edad_texto($ev, $eu) : '');
$servicios = array_intersect_key(lista('Serv'), array_flip($mod['servicios']));

vista_inicio($a ? 'Admisión ' . $a['ConsAdmi'] : $mod['nombre']);
?>
<section class="encabezado-trabajo" aria-label="Encabezado de la admisión">
    <!-- Barra: numero de admision, estado y botones del encabezado -->
    <div class="et-barra">
        <div class="et-admision">
            <span class="et-etiqueta">Admisión</span>
            <strong><?= $a ? e($a['ConsAdmi']) : ($pac ? 'Nueva' : '—') ?></strong>
            <?php if ($a): [$estado, $claseEstado] = admision_estado($a); ?>
                <span class="etiqueta etiqueta-<?= e($claseEstado) ?>"><?= e($estado) ?></span>
                <span class="etiqueta" title="Número temporal: al cargar a SIHOS se asigna el definitivo">Temporal</span>
                <?php if ($a['ClasTria']): ?><span class="etiqueta triage-<?= (int) $a['ClasTria'] ?>">Triage <?= e(triage_romano($a['ClasTria'])) ?></span><?php endif; ?>
            <?php elseif ($pac): ?>
                <span class="etiqueta etiqueta-curso">Nueva admisión</span>
            <?php endif; ?>
        </div>
        <div class="et-botones">
            <a href="<?= e($base) ?>&amp;historias=1" class="boton boton-claro" data-abrir-ventana="historias"><?= icono('clipboard-list') ?>Historias abiertas <span class="contador"><?= count($todas) ?></span></a>
            <a href="<?= e($base) ?>&amp;nueva=1" class="boton boton-claro"><?= icono('user-plus') ?>Nueva admisión</a>
            <a href="<?= e($base) ?>&amp;nueva=1" class="boton boton-claro" title="Vaciar el encabezado"><?= icono('x') ?>Limpiar</a>
        </div>
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
                <input type="text" id="NumeUsua" name="NumeUsua" value="<?= e($docNum) ?>" maxlength="20" placeholder="Número" autocomplete="off"<?= (!$a && !$pac) ? ' autofocus' : '' ?>>
                <button type="submit" class="boton boton-primario" title="Buscar paciente"><?= icono('search') ?><span>Buscar</span></button>
                <a href="pacientes.php" class="boton boton-claro boton-puntos" title="Buscar por nombre" aria-label="Buscar paciente por nombre">…</a>
            </div>
        </div>
        <?= campo_lectura('Usuario', $p ? paciente_nombre($p) : '', 'c-3 et-nombre') ?>
        <?= campo_lectura('F. nacimiento', $p && $p['FechNaci'] && $p['FechNaci'] !== '0000-00-00' ? date('d/m/Y', strtotime($p['FechNaci'])) : '', 'c-2') ?>
        <?= campo_lectura('Edad · género', $p ? $edad . ' · ' . lista_nombre('Sexo', $p['SexoUsua']) : '', 'c-2') ?>
    </form>

    <?php if ($buscado && !$pac): ?>
        <div class="et-aviso">
            <div class="alerta alerta-aviso"><?= icono('triangle-alert') ?><div>No existe un paciente con documento <?= e($docTipo . ' ' . $docNum) ?>.
                <a href="paciente_nuevo.php?TipoDocu=<?= e(urlencode($docTipo)) ?>&amp;NumeUsua=<?= e(urlencode($docNum)) ?>">Crear paciente nuevo</a>.</div></div>
        </div>
    <?php endif; ?>

    <?php if ($a): ?>
        <!-- Admision cargada: datos en modo lectura, como en SIHOS -->
        <div class="et-fila">
            <?= campo_lectura('Fecha', date('d/m/Y', strtotime($a['FechIngr'])), 'c-2') ?>
            <?= campo_lectura('Hora', substr($a['HoraIngr'], 0, 5), 'c-1') ?>
            <?= campo_lectura('Autorización', $a['NumeAuto'], 'c-2') ?>
            <?= campo_lectura('Servicio', $a['NombServ'] ?? $a['ServEgre'], 'c-3') ?>
            <?php if ($mod['cama']): ?><?= campo_lectura('Cama', $a['CamaActu'], 'c-1') ?><?php endif; ?>
            <?= campo_lectura('Vía de ingreso', lista_nombre('ViaIngre', $a['ViaIngre']), 'c-2') ?>
            <?= campo_lectura('Entorno de atención', $a['EntoAten'], $mod['cama'] ? 'c-1' : 'c-2') ?>
        </div>
        <div class="et-fila">
            <?= campo_lectura('Causa externa', lista_nombre('CausExte', $a['CausExte']), 'c-3') ?>
            <?= campo_lectura('Condición', lista_nombre('CondUsua', $a['CondUsua']), 'c-2') ?>
            <?= campo_lectura('Grupo poblacional', lista_nombre('GrupAten', $a['GrupoAte']), 'c-2') ?>
            <?= campo_lectura('Diagnóstico de ingreso', $a['DiagIngr'] ? $a['DiagIngr'] . ' · ' . (diagnostico_nombre($a['DiagIngr']) ?? '') : '', 'c-5') ?>
        </div>
        <div class="et-fila">
            <?= campo_lectura('EPS', $a['NombAdmi'] ?? $a['CodiAdmi'], 'c-4') ?>
            <?= campo_lectura('Contrato', $a['NumeCont'], 'c-2') ?>
            <?= campo_lectura('Tipo de usuario', lista_nombre('TipoUsua', $a['TipoUsua']), 'c-2') ?>
            <?= campo_lectura('Afiliación', lista_nombre('TipoAfil', $a['TipoAfil']), 'c-2') ?>
            <?= campo_lectura('Categoría', $a['CodiEstr'], 'c-2') ?>
        </div>
        <?php if ($mod['cama']) { require __DIR__ . '/../src/vistas/traslado_cama.php'; } ?>
        <details class="ea-mas">
            <summary><?= icono('file-text') ?>Motivo, acompañante y registro</summary>
            <div class="et-fila">
                <?= campo_lectura('Motivo de ingreso', $a['MotiCons'], 'c-6') ?>
                <?= campo_lectura('Acompañante', lista_nombre('TipoAcom', $a['TipoAcom']) . ($a['NombAcom'] ? ' · ' . $a['NombAcom'] . ' (' . lista_nombre('Parentes', $a['Parentes']) . ') ' . $a['TeleAcom'] : ''), 'c-6') ?>
                <?= campo_lectura('Registró', $a['UsuaDigi'] . ' · ' . fecha_hora($a['FechDigi'] . ' ' . $a['HoraDigi']), 'c-4') ?>
                <?= campo_lectura('Carga a SIHOS', $a['estado_carga'] ?? 'pendiente', 'c-2') ?>
            </div>
        </details>

    <?php elseif ($pac): ?>
        <!-- Paciente sin admision abierta: el encabezado queda editable para crearla aqui mismo -->
        <form method="post" action="<?= e($urlBusqueda) ?>" class="formulario et-form" id="form-admision" data-una-vez>
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="admision">
            <?= errores_resumen($eA) ?>
            <div class="et-fila">
                <div class="c-2"><label for="FechIngr">Fecha</label>
                    <input type="date" id="FechIngr" name="FechIngr" value="<?= v($d, 'FechIngr') ?>" max="<?= date('Y-m-d') ?>" class="<?= ce($eA, 'FechIngr') ?>" required><?= me($eA, 'FechIngr') ?></div>
                <div class="c-2"><label for="HoraIngr">Hora</label>
                    <input type="time" id="HoraIngr" name="HoraIngr" value="<?= e(substr($d['HoraIngr'], 0, 5)) ?>" class="<?= ce($eA, 'HoraIngr') ?>" required><?= me($eA, 'HoraIngr') ?></div>
                <div class="c-2"><label for="NumeAuto">Autorización</label>
                    <input type="text" id="NumeAuto" name="NumeAuto" value="<?= v($d, 'NumeAuto') ?>" maxlength="50"></div>
                <div class="c-3"><label for="CodiServ">Servicio</label>
                    <select id="CodiServ" name="CodiServ" class="<?= ce($eA, 'CodiServ') ?>" required><?= opciones_arreglo($servicios, $d['CodiServ'], false) ?></select><?= me($eA, 'CodiServ') ?></div>
                <?php if ($mod['cama']): ?>
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
                <div class="c-3"><label for="DiagIngr">Diagnóstico de ingreso (CIE-10)</label>
                    <input type="text" id="DiagIngr" name="DiagIngr" value="<?= v($d, 'DiagIngr') ?>" maxlength="8" data-diagnostico autocomplete="off" class="<?= ce($eA, 'DiagIngr') ?>" placeholder="Código o nombre">
                    <div class="nota-campo" id="DiagIngr-nombre"><?= e(diagnostico_nombre($d['DiagIngr'] ?? '') ?? '') ?></div><?= me($eA, 'DiagIngr') ?></div>
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
                <div class="c-4"><label for="MotiCons">Motivo de ingreso</label>
                    <textarea id="MotiCons" name="MotiCons" rows="2" maxlength="5000"><?= v($d, 'MotiCons') ?></textarea></div>
                <div class="c-2"><label for="TipoAcom">Acompañante</label>
                    <select id="TipoAcom" name="TipoAcom" class="<?= ce($eA, 'TipoAcom') ?>" required><?= opciones('TipoAcom', $d['TipoAcom']) ?></select><?= me($eA, 'TipoAcom') ?></div>
                <div class="c-2"><label for="NombAcom">Nombre del acompañante</label>
                    <input type="text" id="NombAcom" name="NombAcom" value="<?= v($d, 'NombAcom') ?>" maxlength="80"></div>
                <div class="c-2"><label for="Parentes">Parentesco</label>
                    <select id="Parentes" name="Parentes" class="<?= ce($eA, 'Parentes') ?>"><?= opciones('Parentes', $d['Parentes']) ?></select><?= me($eA, 'Parentes') ?></div>
                <div class="c-2"><label for="TeleAcom">Teléfono</label>
                    <input type="text" id="TeleAcom" name="TeleAcom" value="<?= v($d, 'TeleAcom') ?>" maxlength="10" inputmode="tel"></div>
            </div>
            <div class="acciones et-acciones">
                <button type="submit" class="boton boton-primario"><?= icono('save') ?>Crear admisión</button>
                <a href="<?= e($base) ?>&amp;nueva=1" class="boton boton-claro"><?= icono('x') ?>Cancelar</a>
            </div>
        </form>

    <?php else: ?>
        <!-- Sin admision: campos vacios en modo lectura hasta buscar el documento -->
        <div class="et-fila et-vacia">
            <?= campo_lectura('Fecha', '', 'c-2') ?><?= campo_lectura('Hora', '', 'c-1') ?><?= campo_lectura('Autorización', '', 'c-2') ?>
            <?= campo_lectura('Servicio', '', 'c-3') ?><?= campo_lectura('Vía de ingreso', '', 'c-2') ?><?= campo_lectura('Causa externa', '', 'c-2') ?>
            <?= campo_lectura('EPS', '', 'c-4') ?><?= campo_lectura('Contrato', '', 'c-2') ?><?= campo_lectura('Diagnóstico de ingreso', '', 'c-6') ?>
        </div>
        <p class="et-ayuda"><?= icono('info') ?><span>Escriba el documento y pulse <strong>Buscar</strong> para cargar al paciente, o abra <strong>Historias abiertas</strong>.</span></p>
    <?php endif; ?>
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
    $conteos = ['triage' => $triage ? 1 : 0, 'consulta' => count($consultas), 'signos' => count($signos),
                'prescripcion' => count($prescripciones), 'ordenes' => count($ordenesMedicas) + count($ordenes),
                'procedimientos' => count($procedimientos), 'notas' => count($notas) + count($aplicados) + count($materiales),
                'evolucion' => count($evoluciones), 'egreso' => ($egreso ? 1 : 0) + count($remisiones) + count($incapacidades)];
}
pestanas_historia($a, $mod, $tab, $conteos ?? []);
?>

<?php if (!$a): ?>
    <section class="panel panel-vacio">
        <div class="panel-cuerpo">
            <?= icono('clipboard-list') ?>
            <p><strong>No hay una admisión cargada.</strong><br>Las pestañas se activan al cargar una historia abierta o crear la admisión.</p>
        </div>
    </section>
<?php else: ?>

<?php if ($tieneTriage): ?>
<section id="triage" class="seccion panel" data-panel="triage"<?= $tab === 'triage' ? '' : ' hidden' ?>>
    <div class="panel-cabeza">
        <h2><?= icono('siren') ?>1. Triage</h2>
        <?php if ($triage): ?><span class="etiqueta etiqueta-abierta"><?= icono('circle-check') ?>Registrado</span>
        <?php else: ?><span class="legend-nota">Profesional: <?= e($u['Nombre']) ?></span><?php endif; ?>
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
    <?php elseif (!$editable): ?>
        <div class="alerta vacio"><?= icono('info') ?><div>El paciente aún no tiene triage.</div></div>
    <?php else: ?>
        <div class="panel-cuerpo">
        <?= errores_resumen($eT) ?>
        <form method="post" action="<?= e($aqui) ?>&amp;tab=triage" class="formulario formulario-panel" data-signos data-una-vez>
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="triage">
            <div class="alerta vacio"><?= icono('info') ?><div>El paciente aún no tiene triage.</div></div>
            <p class="ayuda">Se guarda también la toma de signos No. 1, como en SIHOS.</p>
            <div class="rejilla rejilla-fecha">
                <div><label for="FechTria">Fecha</label>
                    <input type="date" id="FechTria" name="FechTria" value="<?= v($t, 'FechTria') ?>" max="<?= date('Y-m-d') ?>" class="<?= ce($eT, 'FechTria') ?>" required><?= me($eT, 'FechTria') ?></div>
                <div><label for="HoraTria">Hora</label>
                    <input type="time" id="HoraTria" name="HoraTria" value="<?= e(substr($t['HoraTria'] ?? '', 0, 5)) ?>" class="<?= ce($eT, 'HoraTria') ?>" required><?= me($eT, 'HoraTria') ?></div>
            </div>
            <label for="MotiCons">Motivo de consulta (palabras del paciente) <span class="obligatorio" aria-hidden="true">*</span></label>
            <textarea id="MotiCons" name="MotiCons" rows="2" maxlength="5000" class="<?= ce($eT, 'MotiCons') ?>" required><?= v($t, 'MotiCons') ?></textarea><?= me($eT, 'MotiCons') ?>
            <div class="subgrupo">
                <h3><?= icono('heart-pulse') ?>Signos vitales</h3>
                <?php campos_signos($st, $eT); ?>
            </div>
            <label for="HallClin">Hallazgos clínicos <span class="obligatorio" aria-hidden="true">*</span></label>
            <textarea id="HallClin" name="HallClin" rows="4" maxlength="5000" class="<?= ce($eT, 'HallClin') ?>" required><?= v($t, 'HallClin') ?></textarea><?= me($eT, 'HallClin') ?>
            <div class="rejilla">
                <div><label for="CodiDiag">Impresión diagnóstica (CIE-10)</label>
                    <input type="text" id="CodiDiag" name="CodiDiag" value="<?= v($t, 'CodiDiag') ?>" maxlength="8" data-diagnostico autocomplete="off" class="<?= ce($eT, 'CodiDiag') ?>" placeholder="Código o nombre">
                    <div class="nota-campo" id="CodiDiag-nombre"><?= e(diagnostico_nombre($t['CodiDiag'] ?? '') ?? '') ?></div><?= me($eT, 'CodiDiag') ?></div>
                <div><label for="ClasTria">Clasificación</label>
                    <select id="ClasTria" name="ClasTria" class="<?= ce($eT, 'ClasTria') ?>" required><?= opciones_arreglo(lista('ClasTria'), $t['ClasTria'] ?? '') ?></select><?= me($eT, 'ClasTria') ?></div>
                <div><label for="CondTria">Conducta</label>
                    <select id="CondTria" name="CondTria" class="<?= ce($eT, 'CondTria') ?>" required><?= opciones('CondTria', $t['CondTria'] ?? '') ?></select><?= me($eT, 'CondTria') ?></div>
            </div>
            <label for="Conducta">Observaciones de la conducta</label>
            <textarea id="Conducta" name="Conducta" rows="2" maxlength="5000"><?= v($t, 'Conducta') ?></textarea>
            <div class="acciones acciones-panel">
                <button type="submit" class="boton boton-primario"><?= icono('save') ?>Guardar triage</button>
                <button type="reset" class="boton boton-claro"><?= icono('refresh-cw') ?>Limpiar</button>
            </div>
        </form>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<section id="signos" class="seccion panel" data-panel="signos"<?= $tab === 'signos' ? '' : ' hidden' ?>>
    <div class="panel-cabeza">
        <div>
            <h2><?= icono('heart-pulse') ?>3. Signos vitales</h2>
            <p><?= count($signos) ?> <?= count($signos) === 1 ? 'toma registrada' : 'tomas registradas' ?></p>
        </div>
    </div>
    <?php if ($editable): ?>
        <div class="panel-cuerpo">
        <?= errores_resumen($eS) ?>
        <form method="post" action="<?= e($aqui) ?>&amp;tab=signos" class="formulario formulario-panel" data-signos data-una-vez>
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="signos">
            <h3 class="titulo-form"><?= icono('plus') ?>Nueva toma <span class="legend-nota">Peso y talla se proponen con los de la última toma</span></h3>
            <div class="rejilla rejilla-fecha">
                <div><label for="FechToma">Fecha</label>
                    <input type="date" id="FechToma" name="FechToma" value="<?= v($sv, 'FechToma') ?>" max="<?= date('Y-m-d') ?>" class="<?= ce($eS, 'FechToma') ?>" required><?= me($eS, 'FechToma') ?></div>
                <div><label for="HoraToma">Hora</label>
                    <input type="time" id="HoraToma" name="HoraToma" value="<?= e(substr((string) ($sv['HoraToma'] ?? ''), 0, 5)) ?>" class="<?= ce($eS, 'HoraToma') ?>" required><?= me($eS, 'HoraToma') ?></div>
            </div>
            <div class="subgrupo">
                <?php campos_signos($sv, $eS, $prefijoSignos); ?>
            </div>
            <div class="acciones acciones-panel">
                <button type="submit" class="boton boton-primario"><?= icono('save') ?>Guardar signos</button>
                <button type="reset" class="boton boton-claro"><?= icono('refresh-cw') ?>Limpiar</button>
            </div>
        </form>
        </div>
    <?php endif; ?>

    <h3 class="titulo-tabla"><?= icono('history') ?>Tomas anteriores</h3>
    <?php if (!$signos): ?>
        <div class="alerta vacio"><?= icono('info') ?><div>No hay tomas de signos vitales.</div></div>
    <?php else: ?>
    <div class="tabla-contenedor">
    <table class="tabla tabla-signos">
        <thead><tr><th>Toma</th><th>Fecha y hora</th><th class="num">Peso</th><th class="num">Talla</th><th class="num">IMC</th><th class="num">FC</th><th class="num">FR</th><th class="num">T °C</th>
            <th>PA</th><th class="num">TM</th><th class="num">SatO₂</th><th class="num">Gluco</th><th class="num">Dolor</th><th>Registró</th></tr></thead>
        <tbody>
        <?php foreach ($signos as $s): ?>
            <tr>
                <td><span class="contador"><?= (int) $s['ConsSign'] ?></span></td>
                <td class="sin-salto"><?= e(fecha_hora($s['FechToma'] . ' ' . $s['HoraToma'])) ?></td>
                <td class="num"><?= (float) $s['Peso'] > 0 ? e((float) $s['Peso']) : '—' ?></td>
                <td class="num"><?= (float) $s['Talla'] > 0 ? e((float) $s['Talla']) : '—' ?></td>
                <td class="num"><?= (float) $s['MasaCorp'] > 0 ? e((float) $s['MasaCorp']) : '—' ?></td>
                <td class="num"><?= (int) $s['Pulso'] ?></td>
                <td class="num"><?= (int) $s['Respirac'] ?></td>
                <td class="num"><?= e((float) $s['Temperat']) ?></td>
                <td class="sin-salto"><strong><?= (int) $s['PANume'] ?>/<?= (int) $s['PADeno'] ?></strong></td>
                <td class="num"><?= (int) $s['TM'] ?></td>
                <td class="num"><?= (float) $s['Saturaci'] > 0 ? e((float) $s['Saturaci']) . '%' : '—' ?></td>
                <td class="num"><?= (int) $s['GlucMetr'] > 0 ? (int) $s['GlucMetr'] : '—' ?></td>
                <td class="num"><?= e((float) $s['Dolor']) ?></td>
                <td><?= e($s['UsuaDigi']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>
<?php
foreach (['consulta', 'prescripcion', 'ordenes', 'procedimientos', 'notas', 'evolucion', 'egreso'] as $vista) {
    if (in_array($vista, $disponibles, true)) {
        // Cada panel se pinta en su propia funcion para que sus variables no pisen las de esta pagina
        (function () use ($vista, $a, $mod, $editable, $aqui, $tab, $F, $E, $triage, $consultas, $prescripciones,
                          $ordenesMedicas, $ordenes, $procedimientos, $notas, $prescritos, $aplicados, $evoluciones, $egreso,
                          $materiales, $remisiones, $incapacidades, $pendientes) {
            require __DIR__ . '/../src/vistas/pestana_' . $vista . '.php';
        })();
    }
}
?>
<?php endif; ?>

<!-- Pie de la pantalla de trabajo: Volver y Continuar, como en SIHOS -->
<div class="pie-trabajo">
    <a href="<?= e($base) ?>&amp;historias=1" class="boton boton-claro" data-abrir-ventana="historias"><?= icono('arrow-left') ?>Volver a historias abiertas</a>
    <?php if ($a): $pos = array_search($tab, $disponibles, true); $siguiente = $disponibles[$pos + 1] ?? null; ?>
        <a href="<?= e($aqui) ?>&amp;tab=<?= e($siguiente ?? '') ?>" class="boton boton-primario" data-continuar data-tab="<?= e($siguiente ?? '') ?>"<?= $siguiente ? '' : ' hidden' ?>>Continuar <?= icono('chevron-right') ?></a>
    <?php endif; ?>
</div>

<!-- Ventana "Historias abiertas" del modulo (como la de SIHOS) -->
<div class="ventana" id="historias" role="dialog" aria-modal="true" aria-labelledby="historias-titulo"<?= $historiasAbiertas ? '' : ' hidden' ?>>
    <div class="ventana-caja">
        <div class="ventana-cabeza">
            <h2 id="historias-titulo"><?= icono(MODULOS_ICONO[$clave]) ?>Historias abiertas · <?= e($mod['nombre']) ?> <span class="contador"><?= count($todas) ?></span></h2>
            <a href="<?= e($aqui) ?><?= $a ? '' : '&amp;nueva=1' ?>" class="boton-icono" data-cerrar-ventana aria-label="Cerrar"><?= icono('x') ?></a>
        </div>
        <form method="get" action="atencion.php" class="buscador filtros" role="search">
            <input type="hidden" name="modulo" value="<?= e($clave) ?>">
            <input type="hidden" name="historias" value="1">
            <div class="buscador-campo">
                <?= icono('search') ?>
                <input type="text" name="q" value="<?= e($q) ?>" placeholder="Documento, nombre o admisión" aria-label="Buscar por documento, nombre o admisión">
            </div>
            <select name="serv" aria-label="Servicio" class="filtro-servicio">
                <option value="">Todos los servicios</option>
                <?php foreach ($servicios as $c => $n): ?>
                    <option value="<?= e($c) ?>"<?= $serv === (string) $c ? ' selected' : '' ?>><?= e($c . ' · ' . $n) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="boton boton-claro"><?= icono('search') ?>Filtrar</button>
        </form>
        <div class="ventana-cuerpo">
        <?php if (!$filas): ?>
            <div class="alerta vacio"><?= icono('info') ?><div><?= $todas ? 'Ninguna historia abierta coincide con el filtro.' : 'No hay admisiones abiertas en ' . e($mod['nombre']) . '.' ?></div></div>
        <?php else: ?>
        <div class="tabla-contenedor tabla-tarjetas">
        <table class="tabla tabla-historias">
            <thead><tr>
                <th>Paciente</th>
                <?php if ($tieneTriage): ?><th>Triage</th><?php endif; ?>
                <th>Servicio</th>
                <?php if ($mod['cama']): ?><th>Cama</th><?php endif; ?>
                <th>Admisión</th><th>Fecha</th><th>Duración</th><th>Edad</th><th>Estado</th><th>Profesional</th>
            </tr></thead>
            <tbody>
            <?php foreach ($filas as $f): $url = 'atencion.php?id=' . urlencode($f['ConsAdmi']); ?>
                <tr data-href="<?= e($url) ?>"<?= $a && $a['ConsAdmi'] === $f['ConsAdmi'] ? ' class="fila-actual"' : '' ?>>
                    <td class="celda-principal celda-paciente"><a href="<?= e($url) ?>"><?= e(paciente_nombre($f)) ?></a>
                        <small class="bloque"><?= e($f['TipoDocu'] . ' ' . $f['NumeUsua']) ?> · <?= e($f['NombAdmi']) ?></small></td>
                    <?php if ($tieneTriage): ?>
                        <td data-etiqueta="Triage"><?php if ($f['ClasTria']): ?>
                            <span class="etiqueta triage-<?= (int) $f['ClasTria'] ?>"><?= e(triage_romano($f['ClasTria'])) ?></span>
                        <?php else: ?><a href="<?= e($url) ?>&amp;tab=triage" class="sin-triage"><?= icono('circle-alert') ?>Sin triage</a><?php endif; ?></td>
                    <?php endif; ?>
                    <td data-etiqueta="Servicio"><?= e($f['NombServ'] ?? $f['ServEgre']) ?></td>
                    <?php if ($mod['cama']): ?><td data-etiqueta="Cama"><?php if ($f['CamaActu']): ?><span class="chip"><?= icono('bed-double') ?><?= e($f['CamaActu']) ?></span><?php else: ?>—<?php endif; ?></td><?php endif; ?>
                    <td data-etiqueta="Admisión" class="celda-codigo"><?= e($f['ConsAdmi']) ?></td>
                    <td data-etiqueta="Fecha" class="sin-salto"><?= e(fecha_hora($f['FechIngr'] . ' ' . $f['HoraIngr'])) ?></td>
                    <td data-etiqueta="Duración" class="sin-salto"><span class="duracion"><?= icono('clock') ?><?= e(duracion_desde($f['FechIngr'], $f['HoraIngr'])) ?></span></td>
                    <td data-etiqueta="Edad" class="sin-salto"><?= e(edad_texto($f['ValoEdad'], $f['UnidEdad'])) ?> · <?= e($f['SexoUsua']) ?></td>
                    <td data-etiqueta="Estado"><span class="etiqueta etiqueta-curso">En curso</span></td>
                    <td data-etiqueta="Profesional"><?= e($f['UsuaDigi']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
        </div>
    </div>
</div>
<script src="js/formularios.js"></script>
<?php
vista_fin();
