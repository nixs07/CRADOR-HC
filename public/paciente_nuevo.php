<?php
/**
 * Crear paciente nuevo (no existe en la copia local de SIHOS).
 * Queda marcado en cont_paciente para crearlo en SIHOS al cargar.
 */
require __DIR__ . '/../src/inicio.php';
require __DIR__ . '/../src/atencion.php';
require __DIR__ . '/../src/formulario.php';

$u = requiere_login();

$d = [
    'TipoDocu' => is_string($_GET['TipoDocu'] ?? null) ? $_GET['TipoDocu'] : 'CC',
    'NumeUsua' => is_string($_GET['NumeUsua'] ?? null) ? $_GET['NumeUsua'] : '',
    'ResiDepa' => '86', 'ResiMuni' => '865', 'ResiZona' => 'U',
];
$e = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    [$d, $e] = paciente_validar();
    if (!$e && paciente_obtener($d['TipoDocu'], $d['NumeUsua'])) {
        $e['NumeUsua'] = 'Ya existe un paciente con ese documento.';
    }
    if (!$e) {
        paciente_crear($d, $u['Login']);
        flash('ok', 'Paciente creado. Ahora abra la admisión.');
        redirigir('pacientes.php?q=' . urlencode($d['NumeUsua']));
    }
}

vista_inicio('Nuevo paciente');
?>
<p class="migas"><a href="pacientes.php"><?= icono('arrow-left') ?>Pacientes</a></p>
<div class="cabecera-pagina">
    <div>
        <div class="antetitulo"><?= icono('user-plus') ?>Registro</div>
        <h1>Nuevo paciente</h1>
        <p>Solo para pacientes que <strong>no aparecen</strong> en la búsqueda. Al volver SIHOS se crean allá.</p>
    </div>
</div>
<?= errores_resumen($e) ?>

<form method="post" class="formulario" data-una-vez>
    <?= csrf_campo() ?>
    <fieldset>
        <legend>Identificación</legend>
        <div class="rejilla">
            <div><label for="TipoDocu">Tipo de documento</label>
                <select id="TipoDocu" name="TipoDocu" class="<?= ce($e, 'TipoDocu') ?>" required><?= opciones('TipoDocu', $d['TipoDocu'] ?? '') ?></select><?= me($e, 'TipoDocu') ?></div>
            <div><label for="NumeUsua">Número de documento</label>
                <input type="text" id="NumeUsua" name="NumeUsua" value="<?= v($d, 'NumeUsua') ?>" maxlength="20" class="<?= ce($e, 'NumeUsua') ?>" required><?= me($e, 'NumeUsua') ?></div>
            <div><label for="NombUsua">Primer nombre</label>
                <input type="text" id="NombUsua" name="NombUsua" value="<?= v($d, 'NombUsua') ?>" maxlength="20" class="<?= ce($e, 'NombUsua') ?>" required><?= me($e, 'NombUsua') ?></div>
            <div><label for="NombUsu1">Segundo nombre</label>
                <input type="text" id="NombUsu1" name="NombUsu1" value="<?= v($d, 'NombUsu1') ?>" maxlength="20"></div>
            <div><label for="Ape1Usua">Primer apellido</label>
                <input type="text" id="Ape1Usua" name="Ape1Usua" value="<?= v($d, 'Ape1Usua') ?>" maxlength="30" class="<?= ce($e, 'Ape1Usua') ?>" required><?= me($e, 'Ape1Usua') ?></div>
            <div><label for="Ape2Usua">Segundo apellido</label>
                <input type="text" id="Ape2Usua" name="Ape2Usua" value="<?= v($d, 'Ape2Usua') ?>" maxlength="30"></div>
            <div><label for="FechNaci">Fecha de nacimiento</label>
                <input type="date" id="FechNaci" name="FechNaci" value="<?= v($d, 'FechNaci') ?>" max="<?= date('Y-m-d') ?>" class="<?= ce($e, 'FechNaci') ?>" required><?= me($e, 'FechNaci') ?></div>
            <div><label for="SexoUsua">Sexo</label>
                <select id="SexoUsua" name="SexoUsua" class="<?= ce($e, 'SexoUsua') ?>" required><?= opciones('Sexo', $d['SexoUsua'] ?? '') ?></select><?= me($e, 'SexoUsua') ?></div>
        </div>
    </fieldset>

    <fieldset>
        <legend>Residencia y contacto</legend>
        <div class="rejilla">
            <div><label for="ResiDepa">Departamento</label>
                <select id="ResiDepa" name="ResiDepa" class="<?= ce($e, 'ResiDepa') ?>" required><?= opciones('Depa', $d['ResiDepa'] ?? '') ?></select><?= me($e, 'ResiDepa') ?></div>
            <div><label for="ResiMuni">Municipio</label>
                <select id="ResiMuni" name="ResiMuni" class="<?= ce($e, 'ResiMuni') ?>" required
                        data-depende="api.php?que=municipios" data-de="ResiDepa" data-param="depa">
                    <?= opciones_arreglo(!empty($d['ResiDepa']) ? municipios($d['ResiDepa']) : [], $d['ResiMuni'] ?? '') ?>
                </select><?= me($e, 'ResiMuni') ?></div>
            <div><label for="ResiZona">Zona</label>
                <select id="ResiZona" name="ResiZona" class="<?= ce($e, 'ResiZona') ?>" required><?= opciones('Zona', $d['ResiZona'] ?? '') ?></select><?= me($e, 'ResiZona') ?></div>
            <div><label for="DireResi">Dirección</label>
                <input type="text" id="DireResi" name="DireResi" value="<?= v($d, 'DireResi') ?>" maxlength="80"></div>
            <div><label for="TeleCelu">Celular</label>
                <input type="text" id="TeleCelu" name="TeleCelu" value="<?= v($d, 'TeleCelu') ?>" maxlength="12" inputmode="tel"></div>
        </div>
    </fieldset>

    <fieldset>
        <legend>Afiliación</legend>
        <div class="rejilla">
            <div><label for="CodiAdmi">EPS</label>
                <select id="CodiAdmi" name="CodiAdmi" class="<?= ce($e, 'CodiAdmi') ?>" required><?= opciones('Admi', $d['CodiAdmi'] ?? '') ?></select><?= me($e, 'CodiAdmi') ?></div>
            <div><label for="NumeCont">Contrato habitual (opcional)</label>
                <select id="NumeCont" name="NumeCont" class="<?= ce($e, 'NumeCont') ?>"
                        data-depende="api.php?que=contratos" data-de="CodiAdmi" data-param="admi">
                    <?= opciones_arreglo(!empty($d['CodiAdmi']) ? contratos($d['CodiAdmi']) : [], $d['NumeCont'] ?? '') ?>
                </select><?= me($e, 'NumeCont') ?></div>
            <div><label for="TipoUsua">Tipo de usuario</label>
                <select id="TipoUsua" name="TipoUsua" class="<?= ce($e, 'TipoUsua') ?>" required><?= opciones('TipoUsua', $d['TipoUsua'] ?? '') ?></select><?= me($e, 'TipoUsua') ?></div>
            <div><label for="TipoAfil">Tipo de afiliación</label>
                <select id="TipoAfil" name="TipoAfil" class="<?= ce($e, 'TipoAfil') ?>" required><?= opciones('TipoAfil', $d['TipoAfil'] ?? '') ?></select><?= me($e, 'TipoAfil') ?></div>
        </div>
    </fieldset>

    <div class="acciones">
        <button type="submit" class="boton boton-primario"><?= icono('save') ?>Crear paciente</button>
        <a href="pacientes.php" class="boton boton-claro">Cancelar</a>
    </div>
</form>
<script src="js/formularios.js"></script>
<?php
vista_fin();
