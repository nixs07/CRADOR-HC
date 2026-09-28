<?php
/**
 * Ayudas para pintar formularios: valor anterior y mensaje de error por campo.
 */

/** Valor de $datos[$clave] escapado para un atributo value="". */
function v(array $datos, string $clave): string
{
    return e($datos[$clave] ?? '');
}

/** Clase CSS "con-error" si el campo tiene error. */
function ce(array $errores, string $clave): string
{
    return isset($errores[$clave]) ? ' con-error' : '';
}

/** Mensaje de error debajo del campo (vacio si no hay). */
function me(array $errores, string $clave): string
{
    return isset($errores[$clave]) ? '<div class="error-campo">' . e($errores[$clave]) . '</div>' : '';
}

/** Resumen de errores arriba del formulario. */
function errores_resumen(array $errores): string
{
    if (!$errores) {
        return '';
    }
    $html = '<div class="alerta alerta-error"><strong>Revise los datos:</strong><ul>';
    foreach ($errores as $m) {
        $html .= '<li>' . e($m) . '</li>';
    }
    return $html . '</ul></div>';
}

/** Estado de la admision como en SIHOS: ['texto', 'clase CSS']. */
function admision_estado(array $a): array
{
    if ((int) $a['Anulado'] === 1) {
        return ['Anulada', 'error'];
    }
    return (int) $a['Cerrado'] === 1 ? ['Cerrada', 'cerrada'] : ['Abierta', 'abierta'];
}

/** Campo del encabezado en modo lectura (grisado, como SIHOS con la admision cargada). */
function campo_lectura(string $etiqueta, $valor, string $clase = ''): string
{
    $valor = trim((string) $valor);
    return '<div class="campo-lectura ' . e($clase) . '"><span class="cl-etiqueta">' . e($etiqueta) . '</span>'
         . '<span class="cl-valor">' . ($valor !== '' ? e($valor) : '&nbsp;') . '</span></div>';
}

/** Nota de las pestañas de SIHOS que no tienen tablas en la contingencia. */
const NO_DISPONIBLE = 'No disponible';

/**
 * Lista FIJA de pestañas por módulo (respaldo de pestanas_usuario() cuando no están los catálogos de permisos):
 * Urgencias según docs/REVISION_SIHOS.md; Observación y Consulta Externa según docs/RECORRIDO_SIHOS.md §4 y §5
 * (barra del usuario NIXON07). Nombres, orden y numeración de SIHOS
 * (docs/SIHOS_PANTALLAS.md): [número => [id, nombre, nota]]. El id es el del panel (data-panel) y el de
 * ?tab=; si la nota no está vacía la pestaña se muestra deshabilitada. Los números que no se ven en las
 * capturas de SIHOS no se pintan (la numeración salta, como en SIHOS). Supuestos en docs/REGLAS.md.
 */
function pestanas_lista(array $mod): array
{
    $n = NO_DISPONIBLE;
    if ($mod['clave'] === 'urg') {
        return [
            1 => ['triage', 'Triage', ''],
            2 => ['consulta', 'Consultas', ''],
            3 => ['menor', 'Atención del Menor', $n],
            4 => ['prescripcion', 'Prescripción', ''],
            5 => ['ordenes_medicas', 'ORDENES MEDICAS', ''],
            6 => ['procedimientos', 'Procedimientos', ''],
            7 => ['ordenacion', 'Ordenación', ''],
            8 => ['evolucion', 'Evolución', ''],
            9 => ['notas_enfermeria', 'Notas Enfermería', ''],
            10 => ['notas_medicas', 'Notas Médicas', ''],
            11 => ['medicamentos', 'Medicamentos', ''],
            12 => ['consentimiento', 'Consentimiento', $n],
            13 => ['liquidos', 'Líquidos', $n],
            14 => ['oxigeno', 'Oxígeno', $n],
            15 => ['remisiones', 'Remisiones', ''],
            16 => ['materiales', 'Materiales', ''],
            17 => ['incapacidad', 'Incapacidad', ''],
            18 => ['signos', 'Signos Vitales', ''],
            19 => ['nopos', 'No POS', $n],
            20 => ['plan', 'Plan de Manejo', ''],
            21 => ['neurologico', 'Neurológico', $n],
            22 => ['terapias', 'PROCEDIMIENTO TERAPIAS', $n],
            23 => ['quemaduras', 'Esquema de Quemaduras', $n],
            24 => ['glucometria', 'GLUCOMETRIA', $n],
            25 => ['devoluciones', 'Devoluciones', $n],
            26 => ['egreso', 'Egreso', ''],
            27 => ['cambio', 'Cambio de Atención', $n],
            28 => ['imagenes', 'Imágenes', $n],
        ];
    }
    if ($mod['clave'] === 'obs') {
        return [
            1 => ['consulta', 'Consultas', ''],
            2 => ['evolucion', 'Evolución', ''],
            3 => ['prescripcion', 'Prescripción', ''],
            4 => ['ordenes_medicas', 'ORDENES MEDICAS', ''],
            5 => ['nopos', 'No POS', $n],
            6 => ['ordenacion', 'Ordenación', ''],
            7 => ['notas_enfermeria', 'Notas Enfermería', ''],
            8 => ['notas_medicas', 'Notas Médicas', ''],
            9 => ['procedimientos', 'Procedimientos', ''],
            10 => ['medicamentos', 'Medicamentos', ''],
            11 => ['consentimiento', 'Consentimiento', $n],
            12 => ['cirugia', 'Cirugía', $n],
            13 => ['anestesia', 'Anestesia', $n],
            14 => ['signos', 'Signos Vitales', ''],
            15 => ['neurologico', 'Neurológico', $n],
            16 => ['oxigeno', 'Oxígeno', $n],
            17 => ['liquidos', 'Líquidos', $n],
            18 => ['materiales', 'Materiales', ''],
            19 => ['devoluciones', 'Devoluciones', $n],
            20 => ['recien', 'Recién Nacidos', $n],
            21 => ['incapacidad', 'Incapacidad', ''],
            22 => ['egreso', 'Egreso', ''],
            23 => ['cambio', 'Cambio de Atención', ''],
            24 => ['remisiones', 'Remisiones', ''],
            25 => ['plan', 'Plan de Manejo', ''],
            26 => ['glucometria', 'GLUCOMETRIA', $n],
            // En SIHOS siguen sin numero visible; aqui se numeran a continuacion
            27 => ['pyp', 'PyP', $n],
            28 => ['imagenes', 'Imágenes', $n],
            29 => ['labdiag', 'Laboratorios y Diagnósticos', $n],
            30 => ['saludpublica', 'SALUD PUBLICA', $n],
            31 => ['menor', 'Atención del Menor', $n],
        ];
    }
    // Consulta Externa: la consulta (RipsCons) va repartida en las pestañas 1 a 4 y 7 (Plan de Manejo), un solo
    // formulario; no hay pestaña de egreso: la atención se cierra con "Cerrar Historia" del encabezado.
    return [
        1 => ['anamnesis', 'Anamnesis', ''],
        2 => ['revision', 'Rev.Sistemas y Ex.Físico', ''],
        3 => ['antecedentes', 'Antecedentes', ''],
        4 => ['laboratorios', 'Laboratorios y Diagnósticos', ''],
        5 => ['prescripcion', 'Prescripción A', ''],
        6 => ['ordenacion', 'Ordenación', ''],
        7 => ['plan', 'Plan de Manejo', ''],
        8 => ['control', 'Control', $n],
        9 => ['tamizaje', 'Tamizaje Riesgo Cardiovascular', $n],
        10 => ['consentimiento', 'Consentimiento', $n],
        11 => ['procedimientos', 'Procedimientos', ''],
        12 => ['menor', 'Atención del Menor', $n],
        13 => ['incapacidad', 'Incapacidad', ''],
        14 => ['notas_medicas', 'Notas Médicas', ''],
        15 => ['imagenes', 'Imágenes', $n],
        // En SIHOS siguen despues de Imagenes; aqui se numeran a continuacion
        16 => ['medicamentos', 'Medicamentos', ''],
        17 => ['nopos', 'No POS', $n],
        18 => ['remisiones', 'Remisiones', ''],
        19 => ['notas_enfermeria', 'Notas Enfermería', ''],
        20 => ['saludpublica', 'SALUD PUBLICA', $n],
        21 => ['cambio', 'Cambio de Atención', $n],
    ];
}

/** Texto sin tildes y en mayúsculas, para comparar nombres de objetos de SIHOS con los de la lista. */
function nombre_objeto(string $t): string
{
    $t = strtr(mb_strtoupper(trim($t)), ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
    return preg_replace('/\s+/', ' ', $t);
}

/**
 * Barra de pestañas del usuario en el módulo, como SIHOS (docs/RECORRIDO_SIHOS.md §0.1): los objetos que el
 * usuario tiene permitidos en el módulo, ordenados por el MÍNIMO de ModuObje.Orden de cada objeto y, en empate,
 * por CodiObje, numerados 1..N. Los objetos que CRADOR-HC no implementa quedan deshabilitados con su número.
 *
 * Usa los catálogos UsuaGrup, Permisos, ModuObje y Objetos con sus columnas REALES (sql/04_listas_permisos.sql,
 * docs/RESULTADO_CONSULTAS_SIHOS.md): Permisos de los grupos del usuario en el módulo, Objetos.Activo = 1,
 * MIN(ModuObje.Orden) sin los de Orden >= 99 (reportes), empate por CodiObje. Si las tablas no existen o no tienen
 * filas para el usuario, se usa la lista fija pestanas_lista().
 */
function pestanas_usuario(string $login, array $mod): array
{
    $fija = pestanas_lista($mod);
    try {
        $st = db()->prepare('SELECT o.CodiObje, o.NombObje, MIN(m.Orden) AS Orden
                               FROM UsuaGrup ug
                               JOIN Permisos pe ON pe.CodiGrup = ug.CodiGrup AND pe.CodiModu = ?
                               JOIN Objetos o ON o.CodiObje = pe.CodiObje AND o.Activo = 1
                               JOIN ModuObje m ON m.CodiModu = pe.CodiModu AND m.CodiObje = pe.CodiObje AND m.Orden < 99
                              WHERE ug.Login = ?
                              GROUP BY o.CodiObje, o.NombObje
                              ORDER BY MIN(m.Orden), o.CodiObje');
        $st->execute([(int) $mod['CodiModu'], $login]);
        $objetos = $st->fetchAll();
    } catch (Throwable $e) {
        return $fija;
    }
    if (!$objetos) {
        return $fija;
    }
    // Id del panel de CRADOR-HC por nombre de objeto (de la lista fija del módulo)
    $porNombre = [];
    foreach ($fija as [$id, $nombre, $nota]) {
        $porNombre[nombre_objeto($nombre)] = [$id, $nota];
    }
    // Sin repetidos, como SIHOS: con los permisos reales hay objetos DISTINTOS (otro CodiObje) con el mismo nombre
    // (p. ej. dos "Procedimientos" o dos "Imagenes" en Consulta Externa). Se deja solo el primero por orden, tanto
    // por nombre como por panel (el mismo CodiObje ya viene una sola vez por el GROUP BY).
    $barra = [];
    $vistos = [];
    $n = 0;
    foreach ($objetos as $o) {
        $clave = nombre_objeto((string) $o['NombObje']);
        [$id, $nota] = $porNombre[$clave] ?? ['obj' . (int) $o['CodiObje'], NO_DISPONIBLE];
        if (isset($vistos['n:' . $clave]) || isset($vistos['p:' . $id])) {
            continue;
        }
        $vistos['n:' . $clave] = $vistos['p:' . $id] = true;
        $barra[++$n] = [$id, (string) $o['NombObje'], $nota];
    }
    return $barra;
}

/** Barra del usuario conectado en el módulo (se calcula una vez por página). */
function pestanas_barra(array $mod): array
{
    static $cache = [];
    $login = (string) (usuario_actual()['Login'] ?? '');
    return $cache[$mod['clave'] . '|' . $login] ??= pestanas_usuario($login, $mod);
}

/** Número de SIHOS de una pestaña en el módulo ("7." para Ordenación en Urgencias), o '' si no está. */
function pestana_numero(array $mod, string $id): string
{
    foreach (pestanas_barra($mod) as $n => [$pid]) {
        if ($pid === $id) {
            return $n . '. ';
        }
    }
    return '';
}

/** Título del panel con el número y el nombre de SIHOS ("7. Ordenación"). */
function pestana_titulo(array $mod, string $id): string
{
    foreach (pestanas_barra($mod) as $n => [$pid, $nombre]) {
        if ($pid === $id) {
            return $n . '. ' . $nombre;
        }
    }
    return $id;
}

/** Ids de las pestañas disponibles en el módulo, en orden. */
function pestanas_disponibles(array $mod): array
{
    $r = [];
    foreach (pestanas_barra($mod) as [$id, , $nota]) {
        if ($nota === '') {
            $r[] = $id;
        }
    }
    return $r;
}

/**
 * Barra de pestañas numeradas. Sin admisión cargada todas quedan deshabilitadas. Cada pestaña
 * es un enlace ?id=..&tab=.. (funciona sin JavaScript); con JavaScript cambia el panel sin
 * recargar (js/interfaz.js). $conteos: número de registros por pestaña (se muestra al lado).
 */
function pestanas_historia(?array $a, array $mod, string $activa, array $conteos = []): void
{
    $base = $a ? 'atencion.php?id=' . urlencode($a['ConsAdmi']) . '&tab=' : '';
    ?>
    <nav class="pestanas" aria-label="Pestañas de la historia" data-pestanas>
        <?php foreach (pestanas_barra($mod) as $n => [$id, $nombre, $nota]):
            if ($nota === '' && $a): ?>
                <a href="<?= e($base . $id) ?>" data-tab="<?= e($id) ?>"<?= $id === $activa ? ' class="actual" aria-selected="true"' : ' aria-selected="false"' ?>>
                    <span class="pestana-numero"><?= $n ?></span><?= e($nombre) ?>
                    <?php if (!empty($conteos[$id])): ?><span class="contador"><?= (int) $conteos[$id] ?></span><?php endif; ?>
                </a>
            <?php else: ?>
                <span class="pestana-proxima" aria-disabled="true" title="<?= e($nota ?: 'Cargue una admisión') ?>">
                    <span class="pestana-numero"><?= $n ?></span><?= e($nombre) ?><?php if ($nota): ?><small><?= e($nota) ?></small><?php endif; ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <?php
}

/**
 * Campo de texto con buscador de catálogo (CIE-10, procedimientos o suministros) y el nombre
 * del código debajo. $que: diagnosticos | procedimientos | suministros.
 */
function campo_buscador(string $nombre, string $etiqueta, array $datos, array $errores, string $que,
                        bool $obligatorio = false, string $clase = '', ?string $id = null): string
{
    $id = $id ?? $nombre;
    $valor = (string) ($datos[$nombre] ?? '');
    $max = ['diagnosticos' => 8, 'procedimientos' => 15, 'suministros' => 20][$que] ?? 20;
    $nom = $que === 'diagnosticos' ? diagnostico_nombre($valor)
         : ($que === 'procedimientos' ? (function_exists('procedimiento_nombre') ? procedimiento_nombre($valor) : null)
         : (function_exists('suministro_nombre') ? suministro_nombre($valor) : null));
    return '<div class="' . e($clase) . '"><label for="' . e($id) . '">' . e($etiqueta)
         . ($obligatorio ? ' <span class="obligatorio" aria-hidden="true">*</span>' : '') . '</label>'
         . '<input type="text" id="' . e($id) . '" name="' . e($nombre) . '" value="' . e($valor) . '" maxlength="' . $max . '"'
         . ' data-buscar="' . e($que) . '" autocomplete="off" class="' . ce($errores, $nombre) . '" placeholder="Código o nombre"'
         . ($obligatorio ? ' required' : '') . '>'
         . '<div class="nota-campo" id="' . e($id) . '-nombre">' . e($nom ?? '') . '</div>' . me($errores, $nombre) . '</div>';
}

/** Lista desplegable de un catálogo con etiqueta y error. */
function campo_lista(string $nombre, string $etiqueta, string $lista, array $datos, array $errores,
                     bool $obligatorio = true, string $clase = '', ?string $id = null): string
{
    $id = $id ?? $nombre;
    return '<div class="' . e($clase) . '"><label for="' . e($id) . '">' . e($etiqueta)
         . ($obligatorio ? ' <span class="obligatorio" aria-hidden="true">*</span>' : '') . '</label>'
         . '<select id="' . e($id) . '" name="' . e($nombre) . '" class="' . ce($errores, $nombre) . '"' . ($obligatorio ? ' required' : '') . '>'
         . opciones($lista, $datos[$nombre] ?? '') . '</select>' . me($errores, $nombre) . '</div>';
}

/** Fecha y hora de un registro (dos campos) dentro de una rejilla. */
function campos_fecha_hora(string $cf, string $ch, array $datos, array $errores, bool $obligatorio = true): string
{
    $req = $obligatorio ? ' required' : '';
    if (($datos[$cf] ?? '') === '0000-00-00') {
        unset($datos[$cf], $datos[$ch]);
    }
    return '<div><label for="' . e($cf) . '">Fecha</label><input type="date" id="' . e($cf) . '" name="' . e($cf) . '" value="'
         . e($datos[$cf] ?? date('Y-m-d')) . '" max="' . date('Y-m-d') . '" class="' . ce($errores, $cf) . '"' . $req . '>' . me($errores, $cf) . '</div>'
         . '<div><label for="' . e($ch) . '">Hora</label><input type="time" id="' . e($ch) . '" name="' . e($ch) . '" value="'
         . e(substr((string) ($datos[$ch] ?? date('H:i')), 0, 5)) . '" class="' . ce($errores, $ch) . '"' . $req . '>' . me($errores, $ch) . '</div>';
}

/** Área de texto con etiqueta y error. */
function campo_texto(string $nombre, string $etiqueta, array $datos, array $errores, int $filas = 3,
                     bool $obligatorio = false, int $max = 5000, ?string $id = null): string
{
    $id = $id ?? $nombre;
    return '<label for="' . e($id) . '">' . e($etiqueta) . ($obligatorio ? ' <span class="obligatorio" aria-hidden="true">*</span>' : '')
         . '</label><textarea id="' . e($id) . '" name="' . e($nombre) . '" rows="' . $filas . '" maxlength="' . $max . '" class="'
         . ce($errores, $nombre) . '"' . ($obligatorio ? ' required' : '') . '>' . e($datos[$nombre] ?? '') . '</textarea>' . me($errores, $nombre);
}

/** Botones Guardar / Limpiar al final de un formulario de pestaña. */
function botones_panel(string $guardar): string
{
    return '<div class="acciones acciones-panel"><button type="submit" class="boton boton-primario">' . icono('save') . e($guardar)
         . '</button><button type="reset" class="boton boton-claro">' . icono('refresh-cw') . 'Limpiar</button></div>';
}

/** Etiquetas de los signos en la fila compacta, como en SIHOS. */
const SIGNOS_CORTOS = [
    'Peso' => 'Peso (Kg)', 'Talla' => 'Talla (cm)', 'Pulso' => 'FC (Min)', 'Respirac' => 'FR (Min)',
    'Temperat' => 'Temp (°C)', 'PANume' => 'PA sist', 'PADeno' => 'PA diast', 'FetoCard' => 'Fetocardia (Lat/min)',
    'Saturaci' => 'Saturación (%)', 'Oximetria' => 'Oximetría', 'GlucMetr' => 'Glucometría',
];

/**
 * Campos de signos vitales (se usan en triage y en la toma de signos), en el orden de SIHOS:
 * Peso, Talla, IMC, FC, FR, Temp, PA, TM, Fetocardia, Saturacion, Oximetria, Glucometria (sin Dolor: no existe en SIHOS).
 * $prefijo: se antepone al id (nunca al name) cuando hay dos formularios de signos en la pagina.
 * $obligatorios: campos que se marcan obligatorios (SIGNOS_OBLIGATORIOS_TRIAGE, _EVOLUCION o [] = ninguno).
 */
function campos_signos(array $d, array $e, string $prefijo = '', array $obligatorios = []): void
{
    $orden = ['Peso', 'Talla', 'IMC', 'Pulso', 'Respirac', 'Temperat', 'PANume', 'PADeno', 'TM', 'FetoCard', 'Saturaci',
              'Oximetria', 'GlucMetr'];
    ?>
    <div class="rejilla-signos">
        <?php foreach ($orden as $c):
            if ($c === 'IMC' || $c === 'TM'): ?>
                <div class="calculado" title="<?= $c === 'IMC' ? 'Índice de masa corporal (se calcula solo)' : 'Presión arterial media (se calcula sola)' ?>">
                    <span><?= $c === 'IMC' ? 'IMC (Kg/m²)' : 'TM' ?></span><strong id="<?= e($prefijo) ?>calc-<?= strtolower($c) ?>" data-calc="<?= strtolower($c) ?>">—</strong></div>
            <?php continue; endif;
            [$etiqueta, $min, $max] = SIGNOS_RANGOS[$c];
            $oblig = in_array($c, $obligatorios, true);
            $valor = (isset($d[$c]) && (float) $d[$c] != 0) ? (float) $d[$c] : ''; ?>
            <div class="signo"><label for="<?= e($prefijo . $c) ?>" title="<?= e($etiqueta) ?>"><?= e(SIGNOS_CORTOS[$c] ?? $etiqueta) ?><?= $oblig ? ' <span class="obligatorio" aria-hidden="true">*</span>' : '' ?></label>
                <input type="number" id="<?= e($prefijo . $c) ?>" name="<?= e($c) ?>" value="<?= e($valor) ?>" step="any"
                       min="<?= e($min) ?>" max="<?= e($max) ?>" inputmode="decimal" class="<?= ce($e, $c) ?>"<?= $oblig ? ' required' : '' ?>
                       data-mensaje="<?= e($oblig ? SIGNOS_RANGOS[$c][3] : '') ?>"<?= $c === 'Peso' ? ' data-mensaje-max="' . e(SIGNOS_PESO_MAXIMO) . '"' : '' ?>
                       aria-label="<?= e($etiqueta) ?>">
                <?= me($e, $c) ?></div>
        <?php endforeach; ?>
    </div>
    <?php
}

/** Abre el panel de una pestaña (sección con título). Se cierra con '</section>'. */
function panel_abrir(string $id, string $titulo, string $icono, string $activa, string $sub = ''): string
{
    return '<section id="' . e($id) . '" class="seccion panel" data-panel="' . e($id) . '"' . ($id === $activa ? '' : ' hidden') . '>'
         . '<div class="panel-cabeza"><div><h2>' . icono($icono) . e($titulo) . '</h2>'
         . ($sub !== '' ? '<p>' . e($sub) . '</p>' : '') . '</div></div>';
}

/** Aviso de "sin registros" dentro de un panel. */
function panel_vacio(string $texto): string
{
    return '<div class="alerta vacio">' . icono('info') . '<div>' . e($texto) . '</div></div>';
}

/** Texto con saltos de línea para mostrar un registro guardado. */
function texto_registro(?string $t): string
{
    return trim((string) $t) === '' ? '—' : e($t);
}

/** Código y nombre de un diagnóstico para mostrar ("R101 · DOLOR ..."). */
function diag_texto(?string $codigo): string
{
    $codigo = trim((string) $codigo);
    return $codigo === '' ? '' : $codigo . ' · ' . (diagnostico_nombre($codigo) ?? '');
}

/**
 * Barra superior de la pestaña como en SIHOS: Nuevo/Nueva, No. (registros anteriores), Fecha, Hora, campos propios
 * y, a la derecha, los botones de barra que tenga la pestaña en SIHOS (p. ej. Cargos · Consultar · Imprimir),
 * deshabilitados porque no aplican en la contingencia.
 *  $anteriores: [id del elemento del registro => texto]; al escoger uno se abre y se muestra (js/interfaz.js).
 *  $cf / $ch: nombres de los campos de fecha y hora del formulario ('' si la barra no los lleva).
 *  $nuevoUrl: si se da, "Nuevo" es un enlace (formulario en blanco de un registro nuevo); si no, limpia el formulario.
 */
function barra_registro(string $nuevo, array $anteriores, string $cf, string $ch, array $datos, array $errores,
                        string $extra = '', string $profesional = '', array $derecha = [], string $nuevoUrl = ''): string
{
    $html = '<div class="barra-registro">';
    if ($nuevo !== '') {
        $html .= $nuevoUrl !== ''
            ? '<a href="' . e($nuevoUrl) . '" class="boton boton-nuevo">' . icono('plus') . e($nuevo) . '</a>'
            : '<button type="reset" class="boton boton-nuevo" title="Formulario en blanco para un registro nuevo">' . icono('plus') . e($nuevo) . '</button>';
    }
    if ($anteriores !== ['-']) {
        $html .= selector_anteriores($anteriores);
    }
    if ($cf !== '') {
        $html .= '<div class="br-fecha">' . campos_fecha_hora($cf, $ch, $datos, $errores) . '</div>';
    }
    $html .= $extra;
    if ($profesional !== '') {
        $html .= '<div class="br-campo br-profesional"><label>Profesional</label><span>' . e($profesional) . '</span></div>';
    }
    if ($derecha) {
        $html .= '<div class="br-derecha">';
        foreach ($derecha as $b) {
            $html .= boton_no_aplica($b, true);
        }
        $html .= '</div>';
    }
    return $html . '</div>';
}

/** Selector "No." de la barra: registros anteriores (al escoger uno se abre y se muestra). */
function selector_anteriores(array $anteriores): string
{
    $html = '<div class="br-campo"><label>No.</label><select data-ir-registro aria-label="Registros anteriores"'
          . ($anteriores ? '' : ' disabled') . '><option value="">' . ($anteriores ? 'Anteriores (' . count($anteriores) . ')' : 'Sin registros')
          . '</option>';
    foreach ($anteriores as $id => $texto) {
        $html .= '<option value="' . e($id) . '">' . e($texto) . '</option>';
    }
    return $html . '</select></div>';
}

/** Íconos de los botones de SIHOS. */
const BOTON_ICONO = ['Guardar' => 'save', 'Modificar' => 'pencil', 'Consultar' => 'search', 'Imprimir' => 'printer',
                     'Cancelar' => 'x', 'Limpiar' => 'refresh-cw', 'Eliminar' => 'trash-2', 'Cargos' => 'clipboard-list',
                     'Cerrar Consulta' => 'lock', 'Nuevo' => 'plus', 'Nueva' => 'plus'];

/** Botón de SIHOS que no aplica en la contingencia: visible y deshabilitado. */
function boton_no_aplica(string $texto, bool $chico = false, string $motivo = 'No disponible'): string
{
    return '<button type="button" class="boton boton-claro' . ($chico ? ' boton-chico' : '') . '" disabled title="' . e($motivo) . '">'
         . icono(BOTON_ICONO[$texto] ?? 'info') . e($texto) . '</button>';
}

/**
 * Botonera inferior de la pestaña con los botones de SIHOS en su orden. Los de $enviar son submit (el primero
 * resaltado; ['Texto' => 'valor'] envía name="boton" con ese valor), los de $limpiar son reset y el resto se ve
 * deshabilitado ("No disponible").
 */
function botonera(array $lista, array $enviar = ['Guardar'], array $limpiar = ['Cancelar', 'Limpiar']): string
{
    $html = '<div class="acciones acciones-panel botonera-sihos">';
    $primero = true;
    foreach ($lista as $b) {
        $valor = null;
        $esEnvio = in_array($b, $enviar, true) || array_key_exists($b, $enviar);
        if (array_key_exists($b, $enviar) && is_string($enviar[$b])) {
            $valor = $enviar[$b];
        }
        if ($esEnvio) {
            $html .= '<button type="submit" class="boton ' . ($primero ? 'boton-primario' : 'boton-claro') . '"'
                   . ($valor !== null ? ' name="boton" value="' . e($valor) . '"' : '') . '>' . icono(BOTON_ICONO[$b] ?? 'save') . e($b) . '</button>';
            $primero = false;
        } elseif (in_array($b, $limpiar, true)) {
            $html .= '<button type="reset" class="boton boton-claro">' . icono(BOTON_ICONO[$b] ?? 'x') . e($b) . '</button>';
        } else {
            $html .= boton_no_aplica($b);
        }
    }
    return $html . '</div>';
}

/**
 * Campo de SIHOS sin columna (o sin catálogo) en CRADOR-HC: se muestra deshabilitado para que la pantalla sea
 * igual. $tipo: text | select | textarea | checkbox. Los campos deshabilitados no se envían.
 */
function campo_sin_columna(string $etiqueta, string $tipo = 'select', string $clase = '', string $valor = ''): string
{
    $t = 'title="Sin columna o sin catálogo local: no disponible"';
    if ($tipo === 'checkbox') {
        return '<div class="' . e($clase) . ' sin-columna"><label class="opcion"><input type="checkbox" disabled ' . $t . '> ' . e($etiqueta) . '</label></div>';
    }
    $campo = $tipo === 'textarea' ? '<textarea rows="2" disabled ' . $t . '>' . e($valor) . '</textarea>'
           : ($tipo === 'text' ? '<input type="text" value="' . e($valor) . '" disabled ' . $t . '>'
           : '<select disabled ' . $t . '><option>' . e($valor !== '' ? $valor : '— No disponible —') . '</option></select>');
    return '<div class="' . e($clase) . ' sin-columna"><label>' . e($etiqueta) . '</label>' . $campo . '</div>';
}

/** Lista Sí / No de SIHOS (1 = sí, 2 = no). $sinValor: texto de la opción vacía ('' = sin opción vacía). */
function campo_sino(string $nombre, string $etiqueta, array $datos, array $errores, string $id = ''): string
{
    $id = $id !== '' ? $id : $nombre;
    $v = (string) ($datos[$nombre] ?? '2');
    return '<div class="campo-sino"><label for="' . e($id) . '">' . e($etiqueta) . '</label>'
         . '<select id="' . e($id) . '" name="' . e($nombre) . '" class="' . ce($errores, $nombre) . '">'
         . '<option value="1"' . ($v === '1' ? ' selected' : '') . '>Sí</option>'
         . '<option value="2"' . ($v !== '1' ? ' selected' : '') . '>No</option></select>' . me($errores, $nombre) . '</div>';
}

/**
 * Tabla de diagnósticos de SIHOS: una fila por diagnóstico (Principal, Rela 1...) con el código CIE-10
 * (buscador) y el tipo. $filas: [[etiqueta, campo del código, campo del tipo], ...]; la primera es la principal.
 * $prefijo: se antepone a los id (nunca a los name) si el mismo campo aparece en dos formularios.
 */
function tabla_diagnosticos(array $filas, array $datos, array $errores, bool $principalObligatorio = true, string $prefijo = ''): string
{
    $html = '<div class="tabla-diag" role="group" aria-label="Diagnósticos"><div class="td-cabeza"><span>Diagnóstico</span><span>Código CIE-10 / nombre</span><span>Tipo</span></div>';
    foreach ($filas as $i => [$etq, $cd, $ct]) {
        $obl = $i === 0 && $principalObligatorio;
        $html .= '<div class="td-fila"><span class="td-etiqueta">' . e($etq) . ($obl ? ' <span class="obligatorio" aria-hidden="true">*</span>' : '') . '</span>'
               . campo_buscador($cd, $etq . ' (CIE-10)', $datos, $errores, 'diagnosticos', $obl, 'td-codigo', $prefijo . $cd)
               . '<div class="td-tipo"><label for="' . e($prefijo . $ct) . '">Tipo</label><select id="' . e($prefijo . $ct) . '" name="' . e($ct) . '" class="'
               . ce($errores, $ct) . '">' . opciones('TipoDiag', ($datos[$ct] ?? '') === '0' ? '' : ($datos[$ct] ?? '')) . '</select>' . me($errores, $ct) . '</div></div>';
    }
    return $html . '</div>';
}

/** Casilla (checkbox) con valor 1. */
function casilla(string $nombre, string $etiqueta, array $datos, string $id = ''): string
{
    $id = $id !== '' ? $id : $nombre;
    return '<label class="opcion" for="' . e($id) . '"><input type="checkbox" id="' . e($id) . '" name="' . e($nombre) . '" value="1"'
         . (!empty($datos[$nombre]) ? ' checked' : '') . '> ' . e($etiqueta) . '</label>';
}

/**
 * Tabla de tomas de signos vitales. $historica = true: la tabla histórica de la pestaña Signos Vitales con las
 * columnas de SIHOS (Cons · Evolución · Sede · Fecha · Hora · Peso · Talla · IMC · FC · FR · Temp · PA ·
 * Fetocardia · Saturación · Glucometría · Profesional). false: la fila de signos del triage (§0.2 del recorrido).
 * Los valores se muestran tal cual se guardaron (sin límites), 0 incluido.
 */
function tabla_signos(array $tomas, bool $historica): string
{
    $n = fn ($v) => e(rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'));
    if ($historica) {
        $h = '<div class="tabla-contenedor"><table class="tabla tabla-signos"><thead><tr><th>Cons</th><th>Evolución</th><th>Sede</th>'
           . '<th>Fecha</th><th>Hora</th><th class="num">Peso</th><th class="num">Talla</th><th class="num">IMC</th><th class="num">FC</th>'
           . '<th class="num">FR</th><th class="num">Temp</th><th>PA</th><th class="num">Fetocardia</th><th class="num">Saturación</th>'
           . '<th class="num">Glucometría</th><th>Profesional</th></tr></thead><tbody>';
        foreach ($tomas as $s) {
            $h .= '<tr id="reg-signos-' . (int) $s['ConsSign'] . '"><td><span class="contador">' . (int) $s['ConsSign'] . '</span></td>'
                . '<td class="num">' . ((int) ($s['ConsEvol'] ?? 0) ?: '') . '</td><td>' . e($s['CodiInst']) . '</td>'
                . '<td class="sin-salto">' . e(date('d/m/Y', strtotime($s['FechToma']))) . '</td><td>' . e(substr($s['HoraToma'], 0, 5)) . '</td>'
                . '<td class="num">' . $n($s['Peso']) . '</td><td class="num">' . $n($s['Talla']) . '</td><td class="num">' . $n($s['MasaCorp']) . '</td>'
                . '<td class="num">' . (int) $s['Pulso'] . '</td><td class="num">' . (int) $s['Respirac'] . '</td><td class="num">' . $n($s['Temperat']) . '</td>'
                . '<td class="sin-salto">' . (int) $s['PANume'] . '/' . (int) $s['PADeno'] . '</td><td class="num">' . (int) $s['FetoCard'] . '</td>'
                . '<td class="num">' . $n($s['Saturaci']) . '</td><td class="num">' . (int) $s['GlucMetr'] . '</td><td>' . e($s['UsuaDigi']) . '</td></tr>';
        }
        return $h . '</tbody></table></div>';
    }
    $h = '<div class="tabla-contenedor tabla-triage"><table class="tabla"><thead><tr><th>Peso (Kg)</th><th>Talla (cm)</th><th>IMC (Kg/m²)</th>'
       . '<th>FC (Min)</th><th>FR (Min)</th><th>Temp (°C)</th><th>PA</th><th>TM</th><th>Fetocardia (Lat/min)</th><th>Saturación (%)</th>'
       . '<th>Oximetría</th><th>Glucometría</th></tr></thead><tbody>';
    foreach ($tomas as $s) {
        $h .= '<tr><td class="num">' . $n($s['Peso']) . '</td><td class="num">' . $n($s['Talla']) . '</td><td class="num">' . $n($s['MasaCorp']) . '</td>'
            . '<td class="num">' . (int) $s['Pulso'] . '</td><td class="num">' . (int) $s['Respirac'] . '</td><td class="num">' . $n($s['Temperat']) . '</td>'
            . '<td class="num sin-salto">' . (int) $s['PANume'] . '/' . (int) $s['PADeno'] . '</td><td class="num">' . (int) $s['TM'] . '</td>'
            . '<td class="num">' . (int) $s['FetoCard'] . '</td><td class="num">' . $n($s['Saturaci']) . '</td>'
            . '<td class="num">' . ($s['Oximetria'] === null ? '' : (int) $s['Oximetria']) . '</td><td class="num">' . (int) $s['GlucMetr'] . '</td></tr>';
    }
    return $h . '</tbody></table></div>';
}
