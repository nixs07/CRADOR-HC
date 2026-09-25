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
 * Pestanas numeradas de la historia, en el orden de SIHOS y con la misma numeracion en los
 * 3 modulos: 1. Triage (solo Urgencias), 2. Consultas, 3. Signos vitales, 4. Prescripcion,
 * 5. Ordenes medicas, 6. Procedimientos, 7. Notas de enfermeria, 8. Evolucion, 9. Egreso.
 * Las que aun no existen se ven deshabilitadas "Proximamente". Sin admision cargada, todas
 * quedan deshabilitadas. Cada pestana es un enlace ?id=..&tab=.. (funciona sin JavaScript);
 * con JavaScript cambia el panel sin recargar (js/interfaz.js).
 */
function pestanas_historia(?array $a, array $mod, string $activa, int $nSignos): void
{
    $base = $a ? 'atencion.php?id=' . urlencode($a['ConsAdmi']) . '&tab=' : '';
    $lista = [
        1 => ['triage', 'Triage', $mod['triage'] ? '' : 'Solo Urgencias'],
        2 => ['', 'Consultas', 'Próximamente'],
        3 => ['signos', 'Signos vitales', ''],
        4 => ['', 'Prescripción', 'Próximamente'],
        5 => ['', 'Órdenes médicas', 'Próximamente'],
        6 => ['', 'Procedimientos', 'Próximamente'],
        7 => ['', 'Notas de enfermería', 'Próximamente'],
        8 => ['', 'Evolución', 'Próximamente'],
        9 => ['', 'Egreso', 'Próximamente'],
    ];
    ?>
    <nav class="pestanas" aria-label="Pestañas de la historia" data-pestanas>
        <?php foreach ($lista as $n => [$id, $nombre, $nota]):
            if ($id !== '' && $nota === '' && $a): ?>
                <a href="<?= e($base . $id) ?>" data-tab="<?= e($id) ?>"<?= $id === $activa ? ' class="actual" aria-selected="true"' : ' aria-selected="false"' ?>>
                    <span class="pestana-numero"><?= $n ?></span><?= e($nombre) ?>
                    <?php if ($id === 'signos'): ?><span class="contador"><?= $nSignos ?></span><?php endif; ?>
                </a>
            <?php else: ?>
                <span class="pestana-proxima" aria-disabled="true" title="<?= e($nota ?: 'Cargue una admisión') ?>">
                    <span class="pestana-numero"><?= $n ?></span><?= e($nombre) ?><?php if ($nota): ?><small><?= e($nota) ?></small><?php endif; ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <?php
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
