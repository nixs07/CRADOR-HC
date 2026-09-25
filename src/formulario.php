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

/**
 * Pestañas de la historia en el orden de SIHOS, con la misma numeración en los 3 módulos:
 * [id, nombre, nota si no aplica en el módulo]. El id es el del panel (data-panel) y el de ?tab=.
 */
function pestanas_lista(array $mod): array
{
    return [
        1 => ['triage', 'Triage', $mod['triage'] ? '' : 'Solo Urgencias'],
        2 => ['consulta', 'Consultas', ''],
        3 => ['signos', 'Signos vitales', ''],
        4 => ['prescripcion', 'Prescripción', ''],
        5 => ['ordenes', 'Órdenes médicas', ''],
        6 => ['procedimientos', 'Procedimientos', ''],
        7 => ['notas', 'Notas de enfermería', ''],
        8 => ['evolucion', 'Evolución', $mod['clave'] === 'ce' ? 'No aplica en Consulta Externa' : ''],
        9 => ['egreso', $mod['clave'] === 'ce' ? 'Cerrar atención' : 'Egreso', ''],
    ];
}

/** Ids de las pestañas disponibles en el módulo, en orden. */
function pestanas_disponibles(array $mod): array
{
    $r = [];
    foreach (pestanas_lista($mod) as [$id, , $nota]) {
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
        <?php foreach (pestanas_lista($mod) as $n => [$id, $nombre, $nota]):
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

/** Etiquetas cortas de los signos para la fila compacta (como la tabla de signos de SIHOS). */
const SIGNOS_CORTOS = [
    'Peso' => 'Peso (kg)', 'Talla' => 'Talla (cm)', 'Pulso' => 'FC (lpm)', 'Respirac' => 'FR (rpm)',
    'Temperat' => 'Temp (°C)', 'PANume' => 'PA sistólica', 'PADeno' => 'PA diastólica',
    'Saturaci' => 'SatO₂ (%)', 'GlucMetr' => 'Glucometría', 'Dolor' => 'Dolor (0-10)',
];

/**
 * Campos de signos vitales (se usan en triage y en la toma de signos), en el orden de SIHOS:
 * Peso, Talla, IMC, FC, FR, Temp, PA, TM, Saturacion, Glucometria (y dolor).
 * $prefijo: se antepone al id (nunca al name) cuando hay dos formularios de signos en la pagina.
 */
function campos_signos(array $d, array $e, string $prefijo = ''): void
{
    $orden = ['Peso', 'Talla', 'IMC', 'Pulso', 'Respirac', 'Temperat', 'PANume', 'PADeno', 'TM', 'Saturaci', 'GlucMetr', 'Dolor'];
    ?>
    <div class="rejilla-signos">
        <?php foreach ($orden as $c):
            if ($c === 'IMC' || $c === 'TM'): ?>
                <div class="calculado" title="<?= $c === 'IMC' ? 'Índice de masa corporal (se calcula solo)' : 'Presión arterial media (se calcula sola)' ?>">
                    <span><?= $c === 'IMC' ? 'IMC' : 'TM (PAM)' ?></span><strong id="<?= e($prefijo) ?>calc-<?= strtolower($c) ?>" data-calc="<?= strtolower($c) ?>">—</strong></div>
            <?php continue; endif;
            [$etiqueta, $min, $max, $oblig] = SIGNOS_RANGOS[$c];
            $valor = (isset($d[$c]) && (float) $d[$c] != 0) ? (float) $d[$c] : ''; ?>
            <div class="signo"><label for="<?= e($prefijo . $c) ?>" title="<?= e($etiqueta) ?>"><?= e(SIGNOS_CORTOS[$c] ?? $etiqueta) ?><?= $oblig ? ' <span class="obligatorio" aria-hidden="true">*</span>' : '' ?></label>
                <input type="number" id="<?= e($prefijo . $c) ?>" name="<?= e($c) ?>" value="<?= e($valor) ?>" step="any"
                       min="<?= e($min) ?>" max="<?= e($max) ?>" inputmode="decimal" class="<?= ce($e, $c) ?>"<?= $oblig ? ' required' : '' ?>
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
