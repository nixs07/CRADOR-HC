<?php
/**
 * Nueva admision (encabezado completo, igual que en SIHOS) en un modulo.
 *   admision_nueva.php?modulo=urg&TipoDocu=CC&NumeUsua=123
 */
require __DIR__ . '/../src/inicio.php';
require __DIR__ . '/../src/atencion.php';
require __DIR__ . '/../src/formulario.php';

$u = requiere_login();
$mod = modulo_o_404($_GET['modulo'] ?? null);

$pac = paciente_obtener((string) ($_GET['TipoDocu'] ?? ''), (string) ($_GET['NumeUsua'] ?? ''));
if (!$pac) {
    flash('error', 'Paciente no encontrado. Búsquelo o créelo primero.');
    redirigir('pacientes.php');
}
$abierta = admision_abierta_de_paciente($pac['TipoDocu'], $pac['NumeUsua']);
if ($abierta) {
    flash('aviso', 'El paciente ya tiene una admisión abierta.');
    redirigir('admision.php?id=' . urlencode($abierta['ConsAdmi']));
}

// Valores por defecto: los datos de afiliacion del paciente y lo mas usado en SIHOS
$d = [
    'CodiServ' => $mod['servicios'][0],
    'FechIngr' => date('Y-m-d'),
    'HoraIngr' => date('H:i'),
    'CodiAdmi' => $pac['CodiAdmi'],
    'NumeCont' => $pac['NumeCont'],
    'TipoUsua' => $pac['TipoUsua'],
    'TipoAfil' => $pac['TipoAfil'],
    'CodiEstr' => $pac['EstrPaci'],
    'ViaIngre' => $mod['ViaIngre'],
    'CausExte' => '13',
    'GrupoAte' => 'O',
    'CondUsua' => $pac['SexoUsua'] === 'F' ? '4' : '5',
    'TipoAcom' => '1',
    'Parentes' => '',
];
$e = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    [$d, $e] = admision_validar($mod, $pac);
    if (!$e) {
        $cons = admision_crear($mod, $pac, $d, $u['Login']);
        flash('ok', "Admisión $cons creada.");
        redirigir('admision.php?id=' . urlencode($cons));
    }
}

[$ev, $eu] = edad_sihos($pac['FechNaci'], date('Y-m-d'));
$servicios = array_intersect_key(lista('Serv'), array_flip($mod['servicios']));

vista_inicio('Nueva admisión');
?>
<p class="migas"><a href="pacientes.php?q=<?= e(urlencode($pac['NumeUsua'])) ?>"><?= icono('arrow-left') ?>Pacientes</a></p>
<div class="cabecera-pagina">
    <div>
        <div class="antetitulo"><?= icono(MODULOS_ICONO[$mod['clave']] ?? 'clipboard-plus') ?><?= e($mod['nombre']) ?></div>
        <h1>Nueva admisión · <?= e($mod['nombre']) ?></h1>
        <p>Encabezado completo de la admisión, igual que en SIHOS. El número queda temporal hasta cargarlo.</p>
    </div>
</div>
<div class="encabezado-paciente">
    <div class="ep-persona">
        <span class="ep-avatar"><?= icono('user') ?></span>
        <div>
            <div class="ep-nombre"><?= e(paciente_nombre($pac)) ?></div>
            <div class="ep-datos"><span><?= e($pac['TipoDocu'] . ' ' . $pac['NumeUsua']) ?></span><span><?= e(edad_texto($ev, $eu)) ?></span><span><?= e(lista_nombre('Sexo', $pac['SexoUsua'])) ?></span></div>
        </div>
    </div>
</div>
<?= errores_resumen($e) ?>

<form method="post" class="formulario" data-una-vez>
    <?= csrf_campo() ?>
    <fieldset>
        <legend>Ingreso</legend>
        <div class="rejilla">
            <div><label for="CodiServ">Servicio</label>
                <select id="CodiServ" name="CodiServ" class="<?= ce($e, 'CodiServ') ?>" required><?= opciones_arreglo($servicios, $d['CodiServ'], false) ?></select><?= me($e, 'CodiServ') ?></div>
            <div><label for="FechIngr">Fecha de ingreso</label>
                <input type="date" id="FechIngr" name="FechIngr" value="<?= v($d, 'FechIngr') ?>" max="<?= date('Y-m-d') ?>" class="<?= ce($e, 'FechIngr') ?>" required><?= me($e, 'FechIngr') ?></div>
            <div><label for="HoraIngr">Hora de ingreso</label>
                <input type="time" id="HoraIngr" name="HoraIngr" value="<?= e(substr($d['HoraIngr'], 0, 5)) ?>" class="<?= ce($e, 'HoraIngr') ?>" required><?= me($e, 'HoraIngr') ?></div>
            <?php if ($mod['cama']): ?>
            <div><label for="CodiCama">Cama</label>
                <select id="CodiCama" name="CodiCama" class="<?= ce($e, 'CodiCama') ?>" required
                        data-depende="api.php?que=camas" data-de="CodiServ" data-param="serv">
                    <?= opciones_arreglo(camas($d['CodiServ']), $d['CodiCama'] ?? '') ?>
                </select><?= me($e, 'CodiCama') ?></div>
            <?php endif; ?>
            <div><label for="ViaIngre">Vía de ingreso</label>
                <select id="ViaIngre" name="ViaIngre" class="<?= ce($e, 'ViaIngre') ?>" required><?= opciones('ViaIngre', $d['ViaIngre']) ?></select><?= me($e, 'ViaIngre') ?></div>
            <div><label for="CausExte">Causa externa</label>
                <select id="CausExte" name="CausExte" class="<?= ce($e, 'CausExte') ?>" required><?= opciones('CausExte', $d['CausExte'], true, true) ?></select><?= me($e, 'CausExte') ?></div>
            <div><label for="DiagIngr">Diagnóstico de ingreso (CIE-10)</label>
                <input type="text" id="DiagIngr" name="DiagIngr" value="<?= v($d, 'DiagIngr') ?>" maxlength="8" data-diagnostico autocomplete="off" class="<?= ce($e, 'DiagIngr') ?>" placeholder="Código o nombre">
                <div class="nota-campo" id="DiagIngr-nombre"><?= e(diagnostico_nombre($d['DiagIngr'] ?? '') ?? '') ?></div><?= me($e, 'DiagIngr') ?></div>
        </div>
        <label for="MotiCons">Motivo de ingreso</label>
        <textarea id="MotiCons" name="MotiCons" rows="2" maxlength="5000"><?= v($d, 'MotiCons') ?></textarea>
    </fieldset>

    <fieldset>
        <legend>Entidad y contrato</legend>
        <div class="rejilla">
            <div><label for="CodiAdmi">EPS</label>
                <select id="CodiAdmi" name="CodiAdmi" class="<?= ce($e, 'CodiAdmi') ?>" required><?= opciones('Admi', $d['CodiAdmi']) ?></select><?= me($e, 'CodiAdmi') ?></div>
            <div><label for="NumeCont">Contrato</label>
                <select id="NumeCont" name="NumeCont" class="<?= ce($e, 'NumeCont') ?>" required
                        data-depende="api.php?que=contratos" data-de="CodiAdmi" data-param="admi">
                    <?= opciones_arreglo($d['CodiAdmi'] ? contratos($d['CodiAdmi']) : [], $d['NumeCont']) ?>
                </select><?= me($e, 'NumeCont') ?></div>
            <div><label for="TipoUsua">Tipo de usuario</label>
                <select id="TipoUsua" name="TipoUsua" class="<?= ce($e, 'TipoUsua') ?>" required><?= opciones('TipoUsua', $d['TipoUsua']) ?></select><?= me($e, 'TipoUsua') ?></div>
            <div><label for="TipoAfil">Tipo de afiliación</label>
                <select id="TipoAfil" name="TipoAfil" class="<?= ce($e, 'TipoAfil') ?>" required><?= opciones('TipoAfil', $d['TipoAfil']) ?></select><?= me($e, 'TipoAfil') ?></div>
            <div><label for="CodiEstr">Categoría / nivel</label>
                <select id="CodiEstr" name="CodiEstr" class="<?= ce($e, 'CodiEstr') ?>" required
                        data-depende="api.php?que=estratos&amp;aten=<?= (int) $mod['TipoAten'] ?>" data-de="CodiAdmi,TipoAfil" data-param="admi,afil">
                    <?= opciones_arreglo(($d['CodiAdmi'] && $d['TipoAfil']) ? estratos($d['CodiAdmi'], $mod['TipoAten'], $d['TipoAfil']) : [], $d['CodiEstr']) ?>
                </select><?= me($e, 'CodiEstr') ?></div>
            <div><label for="NumeAuto">Autorización / observación</label>
                <input type="text" id="NumeAuto" name="NumeAuto" value="<?= v($d, 'NumeAuto') ?>" maxlength="50"></div>
            <div><label for="GrupoAte">Grupo poblacional</label>
                <select id="GrupoAte" name="GrupoAte" class="<?= ce($e, 'GrupoAte') ?>" required><?= opciones('GrupAten', $d['GrupoAte']) ?></select><?= me($e, 'GrupoAte') ?></div>
            <div><label for="CondUsua">Condición de la usuaria</label>
                <select id="CondUsua" name="CondUsua" class="<?= ce($e, 'CondUsua') ?>" required><?= opciones('CondUsua', $d['CondUsua']) ?></select><?= me($e, 'CondUsua') ?></div>
        </div>
    </fieldset>

    <fieldset>
        <legend>Acompañante</legend>
        <div class="rejilla">
            <div><label for="TipoAcom">Tipo de acompañante</label>
                <select id="TipoAcom" name="TipoAcom" class="<?= ce($e, 'TipoAcom') ?>" required><?= opciones('TipoAcom', $d['TipoAcom']) ?></select><?= me($e, 'TipoAcom') ?></div>
            <div><label for="NombAcom">Nombre</label>
                <input type="text" id="NombAcom" name="NombAcom" value="<?= v($d, 'NombAcom') ?>" maxlength="80"></div>
            <div><label for="Parentes">Parentesco</label>
                <select id="Parentes" name="Parentes" class="<?= ce($e, 'Parentes') ?>"><?= opciones('Parentes', $d['Parentes']) ?></select><?= me($e, 'Parentes') ?></div>
            <div><label for="TeleAcom">Teléfono</label>
                <input type="text" id="TeleAcom" name="TeleAcom" value="<?= v($d, 'TeleAcom') ?>" maxlength="10" inputmode="tel"></div>
        </div>
    </fieldset>

    <div class="acciones">
        <button type="submit" class="boton boton-primario"><?= icono('save') ?>Crear admisión</button>
        <a href="pacientes.php?q=<?= e(urlencode($pac['NumeUsua'])) ?>" class="boton boton-claro">Cancelar</a>
    </div>
</form>
<script src="js/formularios.js"></script>
<?php
vista_fin();
