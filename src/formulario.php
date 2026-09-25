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

/**
 * Encabezado de la admision (como la parte de arriba de la historia en SIHOS):
 * numero, fecha y hora, documento, paciente, nacimiento, edad, genero, EPS/contrato,
 * servicio, cama, via de ingreso, causa externa, diagnostico y estado.
 * $masDatos: muestra tambien el resto de los datos del ingreso (solo en la ficha).
 */
function encabezado_admision(array $a, bool $masDatos = false): void
{
    $mod = modulo_de_servicio($a['ServEgre']);
    [$estado, $claseEstado] = admision_estado($a);
    $nacimiento = ($a['FechNaci'] ?? '') && $a['FechNaci'] !== '0000-00-00' ? date('d/m/Y', strtotime($a['FechNaci'])) : '—';
    ?>
    <section class="encabezado-admision" aria-label="Encabezado de la admisión">
        <div class="ea-cabeza">
            <div class="ep-persona">
                <span class="ep-avatar"><?= icono('user') ?></span>
                <div>
                    <div class="ep-nombre"><?= e(paciente_nombre($a)) ?></div>
                    <div class="ep-datos"><span><?= e($a['TipoDocu'] . ' ' . $a['NumeUsua']) ?></span><span><?= e(edad_texto($a['ValoEdad'], $a['UnidEdad'])) ?></span><span><?= e(lista_nombre('Sexo', $a['SexoUsua'])) ?></span></div>
                </div>
            </div>
            <div class="ea-admision">
                <div class="ea-numero"><small>Admisión</small><strong><?= e($a['ConsAdmi']) ?></strong></div>
                <div class="ea-chips">
                    <span class="etiqueta etiqueta-<?= e($claseEstado) ?>"><?= e($estado) ?></span>
                    <span class="etiqueta" title="Número temporal: al cargar a SIHOS se asigna el definitivo">Temporal</span>
                    <?php if ($a['ClasTria']): ?>
                        <span class="etiqueta triage-<?= (int) $a['ClasTria'] ?>">Triage <?= e(triage_romano($a['ClasTria'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <dl class="ea-datos">
            <div><dt>Fecha y hora</dt><dd><?= e(fecha_hora($a['FechIngr'] . ' ' . $a['HoraIngr'])) ?></dd></div>
            <div><dt>F. nacimiento</dt><dd><?= e($nacimiento) ?></dd></div>
            <div class="ea-ancho"><dt>EPS / contrato</dt><dd><?= e($a['NombAdmi'] ?? $a['CodiAdmi']) ?> · <?= e($a['NumeCont']) ?></dd></div>
            <div><dt>Servicio</dt><dd><?= e($a['NombServ'] ?? ($mod ? MODULOS_DETALLE[$mod]['nombre'] : $a['ServEgre'])) ?></dd></div>
            <?php if ($a['CamaActu']): ?><div><dt>Cama</dt><dd><?= e($a['CamaActu']) ?></dd></div><?php endif; ?>
            <div><dt>Vía de ingreso</dt><dd><?= e(lista_nombre('ViaIngre', $a['ViaIngre'])) ?></dd></div>
            <div><dt>Causa externa</dt><dd><?= e(lista_nombre('CausExte', $a['CausExte'])) ?></dd></div>
            <div class="ea-ancho"><dt>Diagnóstico</dt><dd><?= e($a['DiagIngr'] ? $a['DiagIngr'] . ' · ' . (diagnostico_nombre($a['DiagIngr']) ?? '') : '—') ?></dd></div>
        </dl>
        <?php if ($masDatos): ?>
        <details class="ea-mas">
            <summary><?= icono('file-text') ?>Más datos del ingreso</summary>
            <dl class="ea-datos">
                <div><dt>Tipo de usuario / afiliación</dt><dd><?= e(lista_nombre('TipoUsua', $a['TipoUsua'])) ?> · <?= e(lista_nombre('TipoAfil', $a['TipoAfil'])) ?> · categoría <?= e($a['CodiEstr']) ?></dd></div>
                <div><dt>Autorización</dt><dd><?= e($a['NumeAuto'] ?: '—') ?></dd></div>
                <div><dt>Grupo poblacional</dt><dd><?= e(lista_nombre('GrupAten', $a['GrupoAte'])) ?> · <?= e(lista_nombre('CondUsua', $a['CondUsua'])) ?></dd></div>
                <div><dt>Acompañante</dt><dd><?= e(lista_nombre('TipoAcom', $a['TipoAcom'])) ?><?= $a['NombAcom'] ? ' · ' . e($a['NombAcom']) . ' (' . e(lista_nombre('Parentes', $a['Parentes'])) . ') ' . e($a['TeleAcom']) : '' ?></dd></div>
                <div class="ea-ancho"><dt>Motivo</dt><dd class="texto-largo"><?= e($a['MotiCons']) ?></dd></div>
                <div><dt>Registró</dt><dd><?= e($a['UsuaDigi']) ?> · <?= e(fecha_hora($a['FechDigi'] . ' ' . $a['HoraDigi'])) ?></dd></div>
                <div><dt>Carga a SIHOS</dt><dd><span class="etiqueta etiqueta-<?= e($a['estado_carga'] ?? 'pendiente') ?>"><?= e($a['estado_carga'] ?? 'pendiente') ?></span></dd></div>
            </dl>
        </details>
        <?php endif; ?>
    </section>
    <?php
}

/**
 * Pestanas numeradas de la historia, en el orden de SIHOS. Las que aun no existen
 * (siguientes bloques de la fase 2) se ven deshabilitadas.
 * $activa: 'triage' o 'signos'. $enFicha: true en admision.php (las pestanas cambian
 * la seccion visible con JavaScript); en triage.php y signos.php llevan a la ficha.
 */
function pestanas_historia(array $a, string $activa, int $nSignos, bool $enFicha = false): void
{
    $mod = modulo_de_servicio($a['ServEgre']);
    $base = $enFicha ? '' : 'admision.php?id=' . urlencode($a['ConsAdmi']);
    $pestanas = [];
    if ($mod && MODULOS_DETALLE[$mod]['triage']) {
        $pestanas[] = ['triage', 'Triage', 'siren'];
    }
    $pestanas[] = ['signos', 'Signos vitales', 'heart-pulse'];
    $proximas = ['Consulta', 'Prescripción', 'Órdenes médicas', 'Procedimientos', 'Notas de enfermería', 'Evolución', 'Egreso'];
    $n = 0;
    ?>
    <nav class="pestanas" aria-label="Pestañas de la historia"<?= $enFicha ? ' data-pestanas data-inicial="' . e($activa) . '"' : '' ?>>
        <?php foreach ($pestanas as [$id, $nombre, $ic]): $n++; ?>
            <a href="<?= e($base) ?>#<?= e($id) ?>"<?= $id === $activa ? ' class="actual" aria-current="page"' : '' ?>>
                <span class="pestana-numero"><?= $n ?></span><?= e($nombre) ?>
                <?php if ($id === 'signos'): ?><span class="contador"><?= $nSignos ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <?php foreach ($proximas as $nombre): $n++; ?>
            <span class="pestana-proxima" aria-disabled="true" title="Próximamente (siguiente bloque de la fase 2)">
                <span class="pestana-numero"><?= $n ?></span><?= e($nombre) ?><small>Próximamente</small></span>
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
 */
function campos_signos(array $d, array $e): void
{
    $orden = ['Peso', 'Talla', 'IMC', 'Pulso', 'Respirac', 'Temperat', 'PANume', 'PADeno', 'TM', 'Saturaci', 'GlucMetr', 'Dolor'];
    ?>
    <div class="rejilla-signos">
        <?php foreach ($orden as $c):
            if ($c === 'IMC' || $c === 'TM'): ?>
                <div class="calculado" title="<?= $c === 'IMC' ? 'Índice de masa corporal (se calcula solo)' : 'Presión arterial media (se calcula sola)' ?>">
                    <span><?= $c === 'IMC' ? 'IMC' : 'TM (PAM)' ?></span><strong id="calc-<?= strtolower($c) ?>">—</strong></div>
            <?php continue; endif;
            [$etiqueta, $min, $max, $oblig] = SIGNOS_RANGOS[$c];
            $valor = (isset($d[$c]) && (float) $d[$c] != 0) ? (float) $d[$c] : ''; ?>
            <div class="signo"><label for="<?= e($c) ?>" title="<?= e($etiqueta) ?>"><?= e(SIGNOS_CORTOS[$c] ?? $etiqueta) ?><?= $oblig ? ' <span class="obligatorio" aria-hidden="true">*</span>' : '' ?></label>
                <input type="number" id="<?= e($c) ?>" name="<?= e($c) ?>" value="<?= e($valor) ?>" step="any"
                       min="<?= e($min) ?>" max="<?= e($max) ?>" inputmode="decimal" class="<?= ce($e, $c) ?>"<?= $oblig ? ' required' : '' ?>
                       aria-label="<?= e($etiqueta) ?>">
                <?= me($e, $c) ?></div>
        <?php endforeach; ?>
    </div>
    <?php
}
