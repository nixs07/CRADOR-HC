// Recorrido de prueba de CRADOR-HC (Playwright): recorre el flujo completo con datos inventados,
// imprime una linea "--" por paso (URL y mensajes) y toma capturas en $OUT.
//   OUT=docs/capturas NODE_PATH=$(npm root -g) node docs/capturas/recorrido.js
const { chromium } = require('playwright');
const B = process.env.BASE || 'http://localhost:8080/';
const OUT = process.env.OUT || './capturas';
const fs = require('fs'); fs.mkdirSync(OUT, { recursive: true });

async function foto(p, nombre) {
  const f = `${OUT}/${nombre}.png`;
  await p.evaluate(() => window.scrollTo(0, 0)); await p.waitForTimeout(150);
  await p.screenshot({ path: f, fullPage: true }); console.log('FOTO', f);
}
async function estado(p, etiqueta) {
  // Mensajes visibles (no los de paneles o ventanas ocultas)
  const alertas = await p.$$eval('.alerta, .error-campo', els => els.filter(e => e.offsetParent !== null).map(e => e.innerText.trim()));
  console.log('--', etiqueta, p.url().replace(B, '/'), JSON.stringify(alertas));
}
async function primeraOpcion(p, sel) {
  const v = await p.$eval(sel, s => { const o = [...s.options].find(o => o.value); return s.value || (o ? o.value : ''); });
  await p.selectOption(sel, v);
}
async function ingresar(p, login) {
  await p.goto(B + 'login.php'); await p.fill('#login', login); await p.fill('#clave', 'prueba123');
  await p.click('main button[type=submit]'); await p.waitForLoadState();
}
// Busca el documento en el encabezado de la pantalla del modulo
async function buscarDocumento(p, modulo, tipo, doc) {
  await p.goto(B + 'atencion.php?modulo=' + modulo + '&nueva=1');
  await p.selectOption('#TipoDocu', tipo); await p.fill('#NumeUsua', doc);
  await p.click('#form-buscar button[type=submit]'); await p.waitForLoadState();
}

(async () => {
  const br = await chromium.launch({ args: ['--lang=es-CO'] });
  const p = await br.newPage({ viewport: { width: 1280, height: 900 }, locale: 'es-CO', timezoneId: 'America/Bogota' });
  p.on('console', m => { if (m.type() === 'error') console.log('CONSOLE', m.text()); });
  p.on('pageerror', e => console.log('PAGEERROR', e.message));
  p.on('response', r => { if (r.status() >= 400) console.log('HTTP', r.status(), r.url()); });

  await p.goto(B + 'login.php'); await foto(p, '01_login');
  await p.fill('#login', 'MEDPRUEBA'); await p.fill('#clave', 'malaclave');
  await p.click('main button[type=submit]'); await estado(p, 'login malo');
  await p.fill('#login', 'MEDPRUEBA'); await p.fill('#clave', 'prueba123');
  await p.click('main button[type=submit]'); await p.waitForLoadState(); await estado(p, 'login');
  await foto(p, '23_seleccion_modulo');

  // El medico no ve el tablero: termina en la seleccion de modulo
  await p.goto(B + 'index.php'); await estado(p, 'medico abre tablero');

  await p.click('a.modulo-urg'); await p.waitForLoadState(); await estado(p, 'modulo urgencias');
  await foto(p, '15_lista_urg');

  // Busqueda por nombre ("...") y paciente nuevo
  await p.goto(B + 'pacientes.php?q=PRUEBA'); await foto(p, '03_pacientes_busqueda');

  const casos = [
    { doc: '99100001', tipo: 'CC', nom: 'CARLOS', ape: 'FICTICIO', ape2: 'URGENCIAS', naci: '1985-04-12', sexo: 'M', eps: 'EPSP01', tu: '2', ta: 'D', mod: 'urg', nuevo: true },
    { doc: '99000006', tipo: 'CC', mod: 'obs' },
    { doc: '99000008', tipo: 'TI', mod: 'ce' },
  ];
  const nums = { urg: ['06', '07'], obs: ['08', '09'], ce: ['10', '11'] };
  const adm = {};
  for (const x of casos) {
    await buscarDocumento(p, x.mod, x.tipo, x.doc);
    if (x.nuevo) {
      await estado(p, 'documento no existe ' + x.doc);
      await p.click('text=Crear paciente nuevo'); await p.waitForLoadState();
      await p.fill('#NombUsua', x.nom); await p.fill('#Ape1Usua', x.ape); await p.fill('#Ape2Usua', x.ape2);
      await p.fill('#FechNaci', x.naci); await p.selectOption('#SexoUsua', x.sexo);
      await p.fill('#DireResi', 'CALLE INVENTADA 1'); await p.fill('#TeleCelu', '3000000100');
      await p.selectOption('#CodiAdmi', x.eps); await p.waitForTimeout(500);
      await primeraOpcion(p, '#NumeCont');
      await p.selectOption('#TipoUsua', x.tu); await p.selectOption('#TipoAfil', x.ta);
      await foto(p, '04_paciente_nuevo');
      await p.click('main button[type=submit]'); await p.waitForLoadState();
      await estado(p, 'paciente creado ' + x.doc);
      await foto(p, '05_paciente_creado');
    }
    await estado(p, 'encabezado nueva admision ' + x.mod);
    const h = new Date(Date.now() - 3600e3 - 5 * 3600e3); // hace 1 h, hora Colombia (UTC-5)
    await p.fill('#HoraIngr', h.toISOString().slice(11, 16));
    await p.fill('#DiagIngr', x.mod === 'urg' ? 'R101' : x.mod === 'obs' ? 'A09X' : 'Z000');
    await p.fill('#form-admision #MotiCons', 'Motivo de prueba (datos inventados) ' + x.mod);
    if (x.mod === 'obs') await primeraOpcion(p, '#CodiCama');
    await primeraOpcion(p, '#NumeCont');
    await primeraOpcion(p, '#CodiEstr');
    await foto(p, nums[x.mod][0] + '_admision_nueva_' + x.mod);
    await p.click('#form-admision button[type=submit]'); await p.waitForLoadState();
    await estado(p, 'admision creada ' + x.mod);
    adm[x.mod] = new URL(p.url()).searchParams.get('id');
    await foto(p, nums[x.mod][1] + '_ficha_' + x.mod);
  }
  console.log('ADM', JSON.stringify(adm));

  // --- Urgencias: pestañas con los nombres, el orden y la numeración de SIHOS ---
  const pestana = async (t) => { await p.click(`a[data-tab=${t}]`); await p.waitForTimeout(100); };
  const guardar = async (sel, etiqueta) => { await p.click(sel); await p.waitForLoadState(); await estado(p, etiqueta); };
  const signos = async (px, v) => { for (const [c, x] of Object.entries(v)) await p.fill('#' + px + c, x); };
  await p.goto(B + 'atencion.php?id=' + adm.urg);
  console.log('-- pestañas urg', JSON.stringify(await p.$$eval('[data-pestanas] > *', l => l.map(x => x.innerText.replace(/\s+/g, ' ').trim()))));

  // 1. Triage (con "Continuar en el consultorio")
  await pestana('triage');
  await p.selectOption('#ClasTria', '3');
  await p.fill('#triage #MotiCons', 'ME DUELE EL ESTOMAGO DESDE AYER');
  await p.fill('#HallClin', 'Paciente consciente, abdomen blando, dolor en epigastrio. Datos inventados.');
  await signos('', { PANume: '120', PADeno: '80', Pulso: '88', Respirac: '18', Temperat: '37.2', Saturaci: '97', Peso: '72', Talla: '170', Dolor: '6', FetoCard: '', Oximetria: '96' });
  await p.selectOption('#CodiCons', 'U01');
  await foto(p, '12_triage');
  await guardar('#triage button[type=submit]', 'triage guardado');

  // Continuar (pie) pasa a la pestaña 2 sin recargar; 18. Signos Vitales
  await p.click('[data-continuar]'); console.log('-- continuar lleva a', await p.$eval('a.actual', a => a.dataset.tab));
  await pestana('signos');
  await signos('toma-', { PANume: '118', PADeno: '76', Pulso: '82', Respirac: '17', Temperat: '36.8', Saturaci: '98', Dolor: '3', GlucMetr: '105' });
  await foto(p, '13_signos');
  await guardar('#signos button[type=submit]', 'signos guardados');
  await foto(p, '14_ficha_urg_con_triage_y_signos');

  // 2. Consultas: acordeones de SIHOS
  await pestana('consulta');
  await p.fill('#ConsMoti', 'DOLOR EN LA BOCA DEL ESTOMAGO (datos inventados)');
  await p.fill('#EnfeActu', 'Cuadro de 1 dia de dolor epigastrico urente, sin vomito. Datos inventados.');
  await p.click('#consulta summary:has-text("Antecedentes")');
  await p.selectOption('#ante-Patologi', '1'); await p.fill('#ante-PatoDesc', 'GASTRITIS HACE 2 AÑOS');
  await p.selectOption('#ante-AlerSiNo', '2');
  await p.click('#consulta summary:has-text("Revisión por Sistema")');
  await p.fill('#ConsRevi', 'NIEGA OTROS SINTOMAS'); await p.selectOption('#SintResp', '2');
  await signos('cons-', { PANume: '116', PADeno: '74', Pulso: '80', Respirac: '16', Temperat: '36.7', Saturaci: '98', Oximetria: '97' });
  await p.fill('#EstaGene', 'ALERTA, HIDRATADO, AFEBRIL'); await p.fill('#PeriAbdo', '88'); await p.fill('#PeriTorx', '92');
  await p.selectOption('#ex-Cabeza', '1'); await p.selectOption('#ex-CardPulm', '1');
  await p.selectOption('#ex-Abdomen', '2'); await p.fill('#consulta input[name=AbdoDesc]', 'DOLOR A LA PALPACION EN EPIGASTRIO');
  await p.fill('#LaboImag', 'SIN PARACLINICOS PREVIOS (inventado)');
  await p.fill('#cons-CodiDiag', 'K297'); await p.selectOption('#cons-TipoDiag', '1');
  await p.fill('#cons-CodiRel1', 'E86X'); await p.selectOption('#cons-TipoDia1', '2');
  await p.selectOption('#ConsDest', '04');
  await p.fill('#ObseReco', 'OMEPRAZOL, DIETA BLANDA, CONTROL EN 24 HORAS');
  await foto(p, '27_consulta_formulario');
  await guardar('#consulta button[type=submit]', 'consulta guardada');
  await foto(p, '27_consulta');

  // 4. Prescripción
  await pestana('prescripcion');
  await p.selectOption('#TipoPres', '1'); await p.fill('#PresRel1', 'E86X');
  const f1 = p.locator('#prescripcion [data-fila]').nth(0);
  await f1.locator('input[name="CodiSumi[]"]').fill('MP0004'); await f1.locator('input[name="CantSumi[]"]').fill('20');
  await f1.locator('select[name="UnidMedi[]"]').selectOption('1'); await f1.locator('select[name="CodiVia[]"]').selectOption('1');
  await f1.locator('input[name="CantFrec[]"]').fill('24'); await f1.locator('input[name="CantPeDu[]"]').fill('7');
  await p.click('#prescripcion [data-agregar-fila]');
  const f2 = p.locator('#prescripcion [data-fila]').nth(1);
  await f2.locator('input[name="CodiSumi[]"]').fill('MP0002'); await f2.locator('input[name="CantSumi[]"]').fill('1');
  await f2.locator('select[name="UnidMedi[]"]').selectOption('4'); await f2.locator('select[name="CodiVia[]"]').selectOption('2');
  await f2.locator('input[name="CantFrec[]"]').fill('8'); await f2.locator('input[name="CantPeDu[]"]').fill('1');
  await foto(p, '28_prescripcion_formulario');
  await guardar('#prescripcion button[type=submit]', 'prescripcion guardada');
  await foto(p, '28_prescripcion');

  // 5. ORDENES MEDICAS y 7. Ordenación
  await pestana('ordenes_medicas');
  await p.fill('#TextoOrden', 'DIETA BLANDA\nLEV: SSN 0.9% 100 CC/HORA\nCONTROL DE SIGNOS VITALES CADA 4 HORAS\nAVISAR CAMBIOS');
  await guardar('#ordenes_medicas button[type=submit]', 'orden medica guardada');
  await foto(p, '38_ordenes_medicas');
  await pestana('ordenacion');
  await p.check('#Autoriza'); await p.selectOption('#OrdeFina', '10'); await p.fill('#OrdeRel1', 'E86X');
  const o1 = p.locator('#ordenacion [data-fila]').nth(0);
  await o1.locator('input[name="OrdProc[]"]').fill('902210');
  await p.click('#ordenacion [data-agregar-fila]');
  const o2 = p.locator('#ordenacion [data-fila]').nth(1);
  await o2.locator('input[name="OrdProc[]"]').fill('871121'); await o2.locator('input[name="OrdObse[]"]').fill('DESCARTAR NEUMOPERITONEO');
  await guardar('#ordenacion button[type=submit]', 'ordenes guardadas');
  await foto(p, '29_ordenacion');

  // 6. Procedimientos (uno suelto y otro que atiende un ítem de la orden: NumeOrde/Item y CantReal)
  await pestana('procedimientos');
  await p.fill('#CodiProc', '939403'); await p.selectOption('#CodiFina', '2'); await p.fill('#CantProc', '1');
  await p.fill('#IndiAdic', 'NEBULIZACION CON SSN, SIN COMPLICACIONES (datos inventados)');
  await p.fill('#proc-DiagRela', 'E86X'); await p.selectOption('#proc-ProcTipoDiaR', '2');
  await guardar('#procedimientos button[type=submit]', 'procedimiento guardado');
  await pestana('procedimientos');
  const pend = await p.$eval('#OrdenItem', s => [...s.options].find(o => o.value).value);
  await p.selectOption('#OrdenItem', pend); await p.selectOption('#CodiFina', '1');
  await p.fill('#IndiAdic', 'TOMA DE MUESTRA PARA HEMOGRAMA');
  await guardar('#procedimientos button[type=submit]', 'procedimiento de la orden guardado');
  await foto(p, '30_procedimientos');

  // 8. Evolución (con fila de signos y Rela 1)
  await pestana('evolucion');
  await p.fill('#Subjetivo', 'REFIERE MEJORIA DEL DOLOR'); await p.fill('#Objetivo', 'ABDOMEN BLANDO, DOLOR LEVE EN EPIGASTRIO');
  await signos('evol-', { PANume: '114', PADeno: '72', Pulso: '78', Respirac: '16', Temperat: '36.6' });
  await p.fill('#evol-EvolDiag', 'K297'); await p.fill('#evol-EvolRel1', 'E86X'); await p.selectOption('#evol-EvolTipoRel1', '2');
  await p.fill('#Analisis', 'EVOLUCION FAVORABLE'); await p.fill('#PlanMane', 'CONTINUAR MANEJO, VALORAR SALIDA');
  await p.check('#ContSign');
  await guardar('#evolucion button[type=submit]', 'evolucion guardada');
  await foto(p, '32_evolucion');

  // 9. Notas Enfermería y 10. Notas Médicas (con "Revisada")
  await pestana('notas_enfermeria');
  await p.fill('#NotaEnfe', 'PACIENTE EN CAMILLA, ALERTA, CON LEV PERMEABLES. SE TOMAN SIGNOS. (datos inventados)');
  await guardar('#notas_enfermeria button[type=submit]', 'nota de enfermeria guardada');
  await foto(p, '31_notas_enfermeria');
  await pestana('notas_medicas');
  await p.fill('#NotaEnfeMed', 'SE EXPLICA AL PACIENTE EL PLAN DE MANEJO. (datos inventados)'); await p.check('#Reviza');
  await guardar('#notas_medicas button[type=submit]', 'nota medica guardada');
  await foto(p, '39_notas_medicas');

  // 11. Medicamentos, 16. Materiales, 15. Remisiones y 17. Incapacidad
  await pestana('medicamentos');
  const med = await p.$eval('#MediPres', s => [...s.options].find(o => o.value).value);
  await p.selectOption('#MediPres', med); await p.fill('#CantMedi', '1'); await p.fill('#MediObse', 'SIN REACCIONES');
  await guardar('#medicamentos button[type=submit]', 'medicamento aplicado');
  await foto(p, '40_medicamentos');
  await pestana('materiales');
  await p.fill('#CodiMate', 'MQ0002'); await p.selectOption('#UnidMate', '01'); await p.fill('#CantMate', '1');
  await p.fill('#MateObse', 'CANALIZACION VENA ANTEBRAZO IZQUIERDO');
  await guardar('#materiales button[type=submit]', 'material registrado');
  await foto(p, '41_materiales');
  await pestana('remisiones');
  await p.selectOption('#EspeRemi', { index: 1 }); await p.fill('#InstDest', 'HOSPITAL DE PRUEBA NIVEL II (inventado)');
  await p.fill('#NombAcep', 'MEDICO DE PRUEBA RECEPTOR'); await p.fill('#CargAcep', 'MEDICO DE TURNO');
  await p.selectOption('#RemiMoti', '2'); await p.selectOption('#ModaSoli', '2');
  await p.fill('#FechAcep', await p.inputValue('#FechRemi')); await p.fill('#HoraAcep', await p.inputValue('#HoraRemi'));
  await p.fill('#MotiRemiTexto', 'PACIENTE REQUIERE VALORACION POR CIRUGIA GENERAL. Datos inventados.');
  await guardar('#remisiones button[type=submit]', 'remision guardada (urg)');
  await foto(p, '42_remisiones');
  await pestana('incapacidad');
  await p.fill('#DiasIncaPaci', '2'); await p.fill('#ObseInca', 'REPOSO RELATIVO (inventado)');
  await guardar('#incapacidad button[type=submit]', 'incapacidad guardada (urg)');
  await foto(p, '43_incapacidad');

  for (const [m, n] of [['obs', '16'], ['ce', '17']]) {
    await p.goto(B + 'atencion.php?modulo=' + m + '&historias=1'); await foto(p, n + '_lista_' + m);
  }

  // Celular (390x844)
  const c = await br.newPage({ viewport: { width: 390, height: 844 }, locale: 'es-CO', timezoneId: 'America/Bogota', isMobile: true, hasTouch: true });
  await ingresar(c, 'MEDPRUEBA');
  await foto(c, '20_movil_seleccion_modulo');
  // La ventana de historias abiertas se desplaza por dentro: captura del tamano de la pantalla
  await c.goto(B + 'atencion.php?modulo=urg&historias=1'); await c.screenshot({ path: `${OUT}/21_movil_lista_urg.png` });
  await c.goto(B + 'atencion.php?id=' + adm.urg + '&tab=triage'); await foto(c, '22_movil_ficha');
  await c.goto(B + 'atencion.php?id=' + adm.urg + '&tab=signos'); await foto(c, '25_movil_signos');
  await c.goto(B + 'atencion.php?id=' + adm.urg + '&tab=consulta'); await foto(c, '48_movil_consulta');
  await c.goto(B + 'atencion.php?modulo=urg&TipoDocu=CC&NumeUsua=99000007'); await foto(c, '26_movil_nueva_admision');
  await c.click('[data-menu-abrir]'); await c.waitForTimeout(400);
  await c.screenshot({ path: `${OUT}/24_movil_menu.png` }); console.log('FOTO', `${OUT}/24_movil_menu.png`);
  await c.close();

  // --- Observación: traslado de cama, 1. Consultas (acordeones), 21. Incapacidad y 22. Egreso con la remisión ---
  await p.goto(B + 'atencion.php?id=' + adm.obs + '&tab=signos');
  console.log('-- pestañas obs', JSON.stringify(await p.$$eval('[data-pestanas] > *', l => l.map(x => x.innerText.replace(/\s+/g, ' ').trim()))));
  await p.click('#traslado > summary');
  await p.selectOption('#CamaDest', 'HOSP10');
  await guardar('#traslado button[type=submit]', 'traslado de cama');
  await p.click('#traslado > summary');
  await foto(p, '36_traslado');
  await p.goto(B + 'atencion.php?id=' + adm.obs + '&tab=consulta'); await foto(p, '44_obs_consultas');
  await p.goto(B + 'atencion.php?id=' + adm.obs + '&tab=egreso');
  await p.click('text=Remisión a otra institución');
  await p.selectOption('#RemiMoti', '2'); await p.selectOption('#ModaSoli', '2');
  await p.fill('#InstDest', 'HOSPITAL DE PRUEBA NIVEL II (inventado)');
  await p.fill('#MotiRemiTexto', 'PACIENTE REQUIERE VALORACION POR MEDICINA INTERNA. Datos inventados.');
  await p.fill('#NombAcep', 'MEDICO DE PRUEBA RECEPTOR'); await p.check('input[name=Ambulanc]'); await p.fill('#PlacAmbu', 'OXX000');
  await guardar('#egreso form:has(input[name=accion][value=remision]) button[type=submit]', 'remision guardada (obs)');
  await foto(p, '37_egreso_remision');
  await p.goto(B + 'atencion.php?id=' + adm.obs + '&tab=incapacidad');
  await p.fill('#DiasIncaPaci', '3'); await p.fill('#ObseInca', 'REPOSO EN CASA (inventado)');
  await guardar('#incapacidad button[type=submit]', 'incapacidad guardada (obs)');

  // Egreso en Observación (cierra la admisión y libera la cama)
  await p.goto(B + 'atencion.php?id=' + adm.obs + '&tab=egreso');
  await p.fill('#ObseSali', 'SALE EN BUENAS CONDICIONES (datos inventados)');
  await p.fill('#egre-EgreRel1', 'E86X'); await p.selectOption('#egre-EgreTipoRel1', '2');
  // Sin marcar la confirmacion: el servidor tambien la exige (se quita el "required" del navegador para probarlo)
  await p.$eval('#egreso input[name=ConfEgre]', c => c.removeAttribute('required'));
  await p.click('#egreso form:has(input[name=accion][value=egreso]) button[type=submit]'); await p.waitForLoadState(); await estado(p, 'egreso sin confirmar');
  await p.check('#egreso input[name=ConfEgre]');
  await foto(p, '33_egreso');
  await p.click('#egreso form:has(input[name=accion][value=egreso]) button[type=submit]'); await p.waitForLoadState(); await estado(p, 'egreso guardado');
  await foto(p, '34_egreso_cerrado');

  // --- Consulta Externa: la consulta repartida en las pestañas 1 a 4 (un solo formulario) ---
  await p.goto(B + 'atencion.php?id=' + adm.ce);
  console.log('-- pestañas ce', JSON.stringify(await p.$$eval('[data-pestanas] > *', l => l.map(x => x.innerText.replace(/\s+/g, ' ').trim()))));
  console.log('-- pestaña por defecto ce', await p.$eval('a.actual', a => a.dataset.tab));
  await p.fill('#ConsMoti', 'CONTROL DE CRECIMIENTO (datos inventados)');
  await foto(p, '45_ce_anamnesis');
  await pestana('revision');
  await p.selectOption('#ex-Cabeza', '1'); await p.selectOption('#ex-Piel', '1'); await p.fill('#PeriAbdo', '60');
  await foto(p, '46_ce_revision');
  await pestana('antecedentes');
  await p.selectOption('#ante-Familiar', '1'); await p.fill('#ante-FamiDesc', 'MADRE CON HIPERTENSION (inventado)');
  await pestana('laboratorios');
  await p.fill('#LaboImag', 'NO TRAE PARACLINICOS'); await p.fill('#cons-CodiDiag', 'Z000'); await p.selectOption('#cons-TipoDiag', '1');
  await p.fill('#ObseReco', 'CONTROL EN 6 MESES');
  // Sin enfermedad actual (pestaña 1): el error debe llevar a la pestaña 1
  await guardar('#laboratorios button[type=submit]', 'consulta CE sin enfermedad actual');
  console.log('-- pestaña con el error', await p.$eval('a.actual', a => a.dataset.tab));
  await p.fill('#EnfeActu', 'ASISTE A CONTROL, SIN QUEJAS. Datos inventados.');
  await guardar('#anamnesis button[type=submit]', 'consulta CE guardada');
  await pestana('notas_medicas');
  await p.fill('#NotaEnfeMed', 'SE ENTREGAN RECOMENDACIONES. (datos inventados)');
  await guardar('#notas_medicas button[type=submit]', 'nota medica CE guardada');
  // "Cerrar Historia" del encabezado (no hay pestaña de egreso en Consulta Externa)
  await p.click('a[data-abrir-ventana=cerrar-historia]');
  await p.check('#cerrar-historia input[name=ConfEgre]');
  await foto(p, '47_ce_cerrar_historia');
  await p.click('#cerrar-historia button[type=submit]'); await p.waitForLoadState(); await estado(p, 'historia CE cerrada');
  await foto(p, '35_ce_cerrada');
  await p.goto(B + 'atencion.php?modulo=urg&historias=1');

  // Salir e ingreso del administrador: el administrador si ve el tablero
  await p.keyboard.press('Escape'); // cierra la ventana de historias abiertas
  await p.click('header button[type=submit]'); await p.waitForLoadState(); await estado(p, 'salir');
  await p.fill('#login', 'NIXON07'); await p.fill('#clave', 'prueba123');
  await p.click('main button[type=submit]'); await p.waitForLoadState(); await estado(p, 'login admin');
  await foto(p, '19_tablero_administrador');
  await br.close();
})();
