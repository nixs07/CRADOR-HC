<?php
/**
 * Buscar paciente (por documento o nombre) y abrir admision.
 */
require __DIR__ . '/../src/inicio.php';
require __DIR__ . '/../src/atencion.php';
require __DIR__ . '/../src/formulario.php';

requiere_login();

$q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 60) : '';
$resultados = $q !== '' ? pacientes_buscar($q) : [];

vista_inicio('Pacientes');
$urlCrear = 'paciente_nuevo.php' . (preg_match('/^[0-9A-Za-z]+$/', $q) ? '?NumeUsua=' . urlencode($q) : '');
?>
<div class="cabecera-pagina">
    <div>
        <div class="antetitulo"><?= icono('users') ?>Admisión</div>
        <h1>Pacientes</h1>
        <p>Busque por número de documento o por nombres y apellidos. Si el paciente no aparece, créelo.</p>
    </div>
    <div class="acciones">
        <a href="<?= e($urlCrear) ?>" class="boton boton-claro"><?= icono('user-plus') ?>Crear paciente</a>
    </div>
</div>

<form method="get" class="buscador" role="search">
    <div class="buscador-campo">
        <?= icono('search') ?>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Documento o nombre (ej. 1123303767 o PEREZ MARIA)" aria-label="Documento o nombre" autofocus>
    </div>
    <button type="submit" class="boton boton-primario"><?= icono('search') ?>Buscar</button>
</form>

<?php if ($q !== ''): ?>
    <?php if (!$resultados): ?>
        <div class="alerta alerta-aviso"><?= icono('triangle-alert') ?><div>No se encontró ningún paciente con “<?= e($q) ?>”.
            <a href="<?= e($urlCrear) ?>">Crear paciente nuevo</a>.</div></div>
    <?php else: ?>
        <div class="titulo-con-acciones"><h2><?= count($resultados) ?> <?= count($resultados) === 1 ? 'paciente encontrado' : 'pacientes encontrados' ?></h2></div>
        <div class="tabla-contenedor tabla-tarjetas">
        <table class="tabla">
            <thead><tr><th>Paciente</th><th>Documento</th><th>Edad</th><th>Sexo</th><th>EPS</th><th>Abrir admisión en</th></tr></thead>
            <tbody>
            <?php foreach ($resultados as $p):
                $abierta = admision_abierta_de_paciente($p['TipoDocu'], $p['NumeUsua']);
                [$ev, $eu] = ($p['FechNaci'] && $p['FechNaci'] !== '0000-00-00') ? edad_sihos($p['FechNaci'], date('Y-m-d')) : ['', 'A'];
                $doc = 'TipoDocu=' . urlencode($p['TipoDocu']) . '&NumeUsua=' . urlencode($p['NumeUsua']);
            ?>
                <tr>
                    <td class="celda-principal celda-paciente"><strong><?= e(paciente_nombre($p)) ?></strong></td>
                    <td data-etiqueta="Documento" class="sin-salto"><?= e($p['TipoDocu'] . ' ' . $p['NumeUsua']) ?></td>
                    <td data-etiqueta="Edad" class="sin-salto"><?= $ev !== '' ? e(edad_texto($ev, $eu)) : '—' ?></td>
                    <td data-etiqueta="Sexo"><?= e($p['SexoUsua']) ?></td>
                    <td data-etiqueta="EPS"><?= e($p['CodiAdmi'] ? lista_nombre('Admi', $p['CodiAdmi']) : '—') ?></td>
                    <td data-etiqueta="Abrir admisión en" class="celda-acciones">
                        <?php if ($abierta): ?>
                            <a href="admision.php?id=<?= e(urlencode($abierta['ConsAdmi'])) ?>" class="boton boton-claro boton-chico"><?= icono('file-text') ?>Admisión abierta <?= e($abierta['ConsAdmi']) ?></a>
                        <?php else: ?>
                            <?php foreach (MODULOS_DETALLE as $clave => $m): ?>
                                <a href="admision_nueva.php?modulo=<?= e($clave) ?>&amp;<?= e($doc) ?>" class="boton boton-claro boton-chico"><?= icono(MODULOS_ICONO[$clave]) ?><?= e($m['nombre']) ?></a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if (count($resultados) >= 50): ?>
            <p class="ayuda">Se muestran los primeros 50. Escriba más datos para afinar la búsqueda.</p>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
<?php
vista_fin();
