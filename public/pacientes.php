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
?>
<h1>Pacientes</h1>
<p class="ayuda">Busque por número de documento o por nombres y apellidos. Si el paciente no aparece, créelo.</p>

<form method="get" class="buscador">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Documento o nombre (ej. 1123303767 o PEREZ MARIA)" autofocus>
    <button type="submit" class="boton boton-primario">Buscar</button>
    <a href="paciente_nuevo.php<?= preg_match('/^[0-9A-Za-z]+$/', $q) ? '?NumeUsua=' . e(urlencode($q)) : '' ?>" class="boton boton-claro">Crear paciente</a>
</form>

<?php if ($q !== ''): ?>
    <?php if (!$resultados): ?>
        <div class="alerta alerta-aviso">No se encontró ningún paciente con “<?= e($q) ?>”.
            <a href="paciente_nuevo.php<?= preg_match('/^[0-9A-Za-z]+$/', $q) ? '?NumeUsua=' . e(urlencode($q)) : '' ?>">Crear paciente nuevo</a>.</div>
    <?php else: ?>
        <div class="tabla-contenedor">
        <table class="tabla">
            <thead><tr><th>Documento</th><th>Nombre</th><th>Edad</th><th>Sexo</th><th>EPS</th><th>Abrir admisión en</th></tr></thead>
            <tbody>
            <?php foreach ($resultados as $p):
                $abierta = admision_abierta_de_paciente($p['TipoDocu'], $p['NumeUsua']);
                [$ev, $eu] = ($p['FechNaci'] && $p['FechNaci'] !== '0000-00-00') ? edad_sihos($p['FechNaci'], date('Y-m-d')) : ['', 'A'];
                $doc = 'TipoDocu=' . urlencode($p['TipoDocu']) . '&NumeUsua=' . urlencode($p['NumeUsua']);
            ?>
                <tr>
                    <td><?= e($p['TipoDocu'] . ' ' . $p['NumeUsua']) ?></td>
                    <td><?= e(paciente_nombre($p)) ?></td>
                    <td><?= $ev !== '' ? e(edad_texto($ev, $eu)) : '—' ?></td>
                    <td><?= e($p['SexoUsua']) ?></td>
                    <td><?= e($p['CodiAdmi'] ? lista_nombre('Admi', $p['CodiAdmi']) : '—') ?></td>
                    <td>
                        <?php if ($abierta): ?>
                            <a href="admision.php?id=<?= e(urlencode($abierta['ConsAdmi'])) ?>">Admisión abierta <?= e($abierta['ConsAdmi']) ?></a>
                        <?php else: ?>
                            <?php foreach (MODULOS_DETALLE as $clave => $m): ?>
                                <a href="admision_nueva.php?modulo=<?= e($clave) ?>&amp;<?= e($doc) ?>" class="boton boton-claro boton-chico"><?= e($m['nombre']) ?></a>
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
