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

/** Encabezado del paciente/admision que se repite en las pantallas clinicas. */
function encabezado_admision(array $a): void
{
    $mod = modulo_de_servicio($a['ServEgre']);
    ?>
    <div class="encabezado-paciente">
        <div>
            <div class="ep-nombre"><?= e(paciente_nombre($a)) ?></div>
            <div class="ep-datos">
                <?= e($a['TipoDocu'] . ' ' . $a['NumeUsua']) ?> ·
                <?= e(edad_texto($a['ValoEdad'], $a['UnidEdad'])) ?> ·
                <?= e(lista_nombre('Sexo', $a['SexoUsua'])) ?> ·
                <?= e($a['NombAdmi'] ?? $a['CodiAdmi']) ?> (contrato <?= e($a['NumeCont']) ?>)
            </div>
        </div>
        <div class="ep-admision">
            <div>Admisión <strong><?= e($a['ConsAdmi']) ?></strong> <span class="etiqueta">temporal</span></div>
            <div><?= e($mod ? MODULOS_DETALLE[$mod]['nombre'] : $a['ServEgre']) ?>
                <?= $a['CamaActu'] ? '· Cama ' . e($a['CamaActu']) : '' ?>
                · Ingreso <?= e(fecha_hora($a['FechIngr'] . ' ' . $a['HoraIngr'])) ?></div>
            <?php if ($a['ClasTria']): ?>
                <div><span class="etiqueta triage-<?= (int) $a['ClasTria'] ?>">Triage <?= e(['', 'I', 'II', 'III', 'IV', 'V'][(int) $a['ClasTria']] ?? $a['ClasTria']) ?></span></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/** Campos de signos vitales (se usan en triage y en la toma de signos). */
function campos_signos(array $d, array $e): void
{
    ?>
    <div class="rejilla rejilla-signos">
        <?php foreach (SIGNOS_RANGOS as $c => [$etiqueta, $min, $max, $oblig]):
            $valor = (isset($d[$c]) && (float) $d[$c] != 0) ? (float) $d[$c] : ''; ?>
            <div><label for="<?= e($c) ?>"><?= e($etiqueta) ?><?= $oblig ? ' *' : '' ?></label>
                <input type="number" id="<?= e($c) ?>" name="<?= e($c) ?>" value="<?= e($valor) ?>" step="any"
                       min="<?= e($min) ?>" max="<?= e($max) ?>" inputmode="decimal" class="<?= ce($e, $c) ?>"<?= $oblig ? ' required' : '' ?>>
                <?= me($e, $c) ?></div>
        <?php endforeach; ?>
        <div class="calculado"><span>IMC</span><strong id="calc-imc">—</strong></div>
        <div class="calculado"><span>Presión arterial media</span><strong id="calc-tm">—</strong></div>
    </div>
    <?php
}
