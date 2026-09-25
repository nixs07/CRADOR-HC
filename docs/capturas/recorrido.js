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

  // Triage en la pestana 1 de la historia
  await p.goto(B + 'atencion.php?id=' + adm.urg);
  await p.click('a[data-tab=triage]');
  await p.selectOption('#ClasTria', '3');
  await p.fill('#triage #MotiCons', 'ME DUELE EL ESTOMAGO DESDE AYER');
  await p.fill('#HallClin', 'Paciente consciente, abdomen blando, dolor en epigastrio. Datos inventados.');
  await p.fill('#PANume', '120'); await p.fill('#PADeno', '80'); await p.fill('#Pulso', '88');
  await p.fill('#Respirac', '18'); await p.fill('#Temperat', '37.2'); await p.fill('#Saturaci', '97');
  await p.fill('#Peso', '72'); await p.fill('#Talla', '170'); await p.fill('#Dolor', '6');
  await foto(p, '12_triage');
  await p.click('#triage button[type=submit]'); await p.waitForLoadState(); await estado(p, 'triage guardado');

  // Continuar (pie) pasa a la pestana 2 sin recargar; los signos estan en la pestana 3
  await p.click('[data-continuar]'); console.log('-- continuar lleva a', await p.$eval('a.actual', a => a.dataset.tab));
  await p.click('a[data-tab=signos]');
  await p.fill('#PANume', '118'); await p.fill('#PADeno', '76'); await p.fill('#Pulso', '82');
  await p.fill('#Respirac', '17'); await p.fill('#Temperat', '36.8'); await p.fill('#Saturaci', '98');
  await p.fill('#Dolor', '3'); await p.fill('#GlucMetr', '105');
  await foto(p, '13_signos');
  await p.click('#signos button[type=submit]'); await p.waitForLoadState(); await estado(p, 'signos guardados');
  await foto(p, '14_ficha_urg_con_triage_y_signos');

  // --- Pestañas 2 y 4 a 8 en la admisión de Urgencias ---
  const pestana = async (t) => { await p.click(`a[data-tab=${t}]`); await p.waitForTimeout(100); };
  const guardar = async (sel, etiqueta) => { await p.click(sel); await p.waitForLoadState(); await estado(p, etiqueta); };

  await pestana('consulta');
  await p.fill('#ConsMoti', 'DOLOR EN LA BOCA DEL ESTOMAGO (datos inventados)');
  await p.fill('#EnfeActu', 'Cuadro de 1 dia de dolor epigastrico urente, sin vomito. Datos inventados.');
  await p.check('#consulta input[name=Patologi][value="1"]'); await p.fill('#consulta input[name=PatoDesc]', 'GASTRITIS HACE 2 AÑOS');
  await p.check('#consulta input[name=Cabeza][value="1"]'); await p.check('#consulta input[name=CardPulm][value="1"]');
  await p.check('#consulta input[name=Abdomen][value="2"]'); await p.fill('#consulta input[name=AbdoDesc]', 'DOLOR A LA PALPACION EN EPIGASTRIO');
  await p.fill('#EstaGene', 'ALERTA, HIDRATADO, AFEBRIL');
  await p.fill('#ConsDiag', 'K297'); await p.selectOption('#TipoDiag', '1');
  await p.fill('#ConsDiag', 'K297');
  await p.fill('#ObseReco', 'OMEPRAZOL, DIETA BLANDA, CONTROL EN 24 HORAS');
  await guardar('#consulta button[type=submit]', 'consulta guardada');
  await foto(p, '27_consulta');

  await pestana('prescripcion');
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

  await pestana('ordenes');
  await p.fill('#TextoOrden', 'DIETA BLANDA\nLEV: SSN 0.9% 100 CC/HORA\nCONTROL DE SIGNOS VITALES CADA 4 HORAS\nAVISAR CAMBIOS');
  await guardar('#ordenes form:has(input[name=accion][value=orden_medica]) button[type=submit]', 'orden medica guardada');
  await pestana('ordenes');
  const o1 = p.locator('#ordenes [data-fila]').nth(0);
  await o1.locator('input[name="OrdProc[]"]').fill('902210');
  await p.click('#ordenes [data-agregar-fila]');
  const o2 = p.locator('#ordenes [data-fila]').nth(1);
  await o2.locator('input[name="OrdProc[]"]').fill('871121'); await o2.locator('input[name="OrdObse[]"]').fill('DESCARTAR NEUMOPERITONEO');
  await guardar('#ordenes form:has(input[name=accion][value=ordenes]) button[type=submit]', 'ordenes guardadas');
  await foto(p, '29_ordenes');

  await pestana('procedimientos');
  await p.fill('#CodiProc', '939403'); await p.selectOption('#CodiFina', '2');
  await p.fill('#IndiAdic', 'NEBULIZACION CON SSN, SIN COMPLICACIONES (datos inventados)');
  await guardar('#procedimientos button[type=submit]', 'procedimiento guardado');
  await foto(p, '30_procedimientos');

  await pestana('notas');
  await p.fill('#NotaEnfe', 'PACIENTE EN CAMILLA, ALERTA, CON LEV PERMEABLES. SE TOMAN SIGNOS. (datos inventados)');
  await guardar('#notas form:has(input[name=accion][value=nota]) button[type=submit]', 'nota guardada');
  await pestana('notas');
  const med = await p.$eval('#MediPres', s => [...s.options].find(o => o.value).value);
  await p.selectOption('#MediPres', med); await p.fill('#CantMedi', '1'); await p.fill('#MediObse', 'SIN REACCIONES');
  await guardar('#notas form:has(input[name=accion][value=medicamento]) button[type=submit]', 'medicamento aplicado');
  await foto(p, '31_notas');

  await pestana('evolucion');
  await p.fill('#Subjetivo', 'REFIERE MEJORIA DEL DOLOR'); await p.fill('#Objetivo', 'ABDOMEN BLANDO, DOLOR LEVE EN EPIGASTRIO');
  await p.fill('#Analisis', 'EVOLUCION FAVORABLE'); await p.fill('#PlanMane', 'CONTINUAR MANEJO, VALORAR SALIDA');
  await p.fill('#EvolDiag', 'K297'); await p.check('input[name=ContSign]');
  await guardar('#evolucion button[type=submit]', 'evolucion guardada');
  await foto(p, '32_evolucion');

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
  await c.goto(B + 'atencion.php?modulo=urg&TipoDocu=CC&NumeUsua=99000007'); await foto(c, '26_movil_nueva_admision');
  await c.click('[data-menu-abrir]'); await c.waitForTimeout(400);
  await c.screenshot({ path: `${OUT}/24_movil_menu.png` }); console.log('FOTO', `${OUT}/24_movil_menu.png`);
  await c.close();

  // --- Egreso en Observación (cierra la admisión y libera la cama) y cierre en Consulta Externa ---
  await p.goto(B + 'atencion.php?id=' + adm.obs + '&tab=egreso');
  await p.fill('#ObseSali', 'SALE EN BUENAS CONDICIONES (datos inventados)');
  // Sin marcar la confirmacion: el servidor tambien la exige (se quita el "required" del navegador para probarlo)
  await p.$eval('input[name=ConfEgre]', c => c.removeAttribute('required'));
  await p.click('#egreso button[type=submit]'); await p.waitForLoadState(); await estado(p, 'egreso sin confirmar');
  await p.check('input[name=ConfEgre]');
  await foto(p, '33_egreso');
  await p.click('#egreso button[type=submit]'); await p.waitForLoadState(); await estado(p, 'egreso guardado');
  await foto(p, '34_egreso_cerrado');
  await p.goto(B + 'atencion.php?id=' + adm.ce + '&tab=egreso');
  await p.check('input[name=ConfEgre]');
  await p.click('#egreso button[type=submit]'); await p.waitForLoadState(); await estado(p, 'atencion CE cerrada');
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
