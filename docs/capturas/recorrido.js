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

  // Signos: boton Continuar del pie (pasa a la pestana 3 sin recargar)
  await p.click('[data-continuar]');
  await p.fill('#PANume', '118'); await p.fill('#PADeno', '76'); await p.fill('#Pulso', '82');
  await p.fill('#Respirac', '17'); await p.fill('#Temperat', '36.8'); await p.fill('#Saturaci', '98');
  await p.fill('#Dolor', '3'); await p.fill('#GlucMetr', '105');
  await foto(p, '13_signos');
  await p.click('#signos button[type=submit]'); await p.waitForLoadState(); await estado(p, 'signos guardados');
  await foto(p, '14_ficha_urg_con_triage_y_signos');

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

  // Salir e ingreso del administrador: el administrador si ve el tablero
  await p.keyboard.press('Escape'); // cierra la ventana de historias abiertas
  await p.click('header button[type=submit]'); await p.waitForLoadState(); await estado(p, 'salir');
  await p.fill('#login', 'NIXON07'); await p.fill('#clave', 'prueba123');
  await p.click('main button[type=submit]'); await p.waitForLoadState(); await estado(p, 'login admin');
  await foto(p, '19_tablero_administrador');
  await br.close();
})();
