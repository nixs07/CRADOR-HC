const { chromium } = require('playwright');
const B = 'http://localhost:8080/';
const OUT = process.env.OUT || './capturas';
const fs = require('fs'); fs.mkdirSync(OUT, { recursive: true });
let n = 0;
// Captura de pagina completa. num: numero fijo (si no, el siguiente de la secuencia 01..19)
async function foto(p, nombre, num) {
  if (!num) n++; const f = `${OUT}/${String(num || n).padStart(2, '0')}_${nombre}.png`;
  await p.evaluate(() => window.scrollTo(0, 0)); await p.waitForTimeout(150);
  await p.screenshot({ path: f, fullPage: true }); console.log('FOTO', f);
}
async function estado(p, etiqueta) {
  const alertas = await p.$$eval('.alerta, .error-campo', els => els.map(e => e.innerText.trim()));
  console.log('--', etiqueta, p.url(), JSON.stringify(alertas));
}
async function primeraOpcion(p, sel) {
  const v = await p.$eval(sel, s => { const o = [...s.options].find(o => o.value); return s.value || (o ? o.value : ''); });
  await p.selectOption(sel, v);
}
(async () => {
  const br = await chromium.launch({ args: ['--lang=es-CO'] });
  const p = await br.newPage({ viewport: { width: 1280, height: 900 }, locale: 'es-CO', timezoneId: 'America/Bogota' });
  p.on('console', m => { if (m.type() === 'error') console.log('CONSOLE', m.text()); });
  p.on('pageerror', e => console.log('PAGEERROR', e.message));
  p.on('response', r => { if (r.status() >= 400) console.log('HTTP', r.status(), r.url()); });

  await p.goto(B + 'login.php'); await foto(p, 'login');
  await p.fill('#login', 'MEDPRUEBA'); await p.fill('#clave', 'malaclave');
  await p.click('main button[type=submit]'); await estado(p, 'login malo');
  await p.fill('#login', 'MEDPRUEBA'); await p.fill('#clave', 'prueba123');
  await p.click('main button[type=submit]'); await p.waitForLoadState(); await estado(p, 'login');
  await foto(p, 'seleccion_modulo', 23);
  await p.click('a.modulo-urg'); await p.waitForLoadState(); await estado(p, 'modulo urgencias');
  await p.goto(B + 'index.php'); await foto(p, 'tablero');

  await p.goto(B + 'pacientes.php?q=PRUEBA'); await foto(p, 'pacientes_busqueda');

  const nuevos = [
    { doc: '99100001', nom: 'CARLOS', ape: 'FICTICIO', ape2: 'URGENCIAS', naci: '1985-04-12', sexo: 'M', eps: 'EPSP01', tu: '2', ta: 'D', mod: 'urg' },
    { doc: '99000006', tipo: 'CC', existe: true, mod: 'obs' },
    { doc: '99000008', tipo: 'TI', existe: true, mod: 'ce' },
  ];
  const adm = {};
  for (const [i, x] of nuevos.entries()) {
    if (!x.existe) {
    await p.goto(B + 'paciente_nuevo.php?NumeUsua=' + x.doc);
    await p.selectOption('#TipoDocu', 'CC');
    await p.fill('#NombUsua', x.nom); await p.fill('#Ape1Usua', x.ape); await p.fill('#Ape2Usua', x.ape2);
    await p.fill('#FechNaci', x.naci); await p.selectOption('#SexoUsua', x.sexo);
    await p.fill('#DireResi', 'CALLE INVENTADA ' + (i + 1)); await p.fill('#TeleCelu', '300000010' + i);
    await p.selectOption('#CodiAdmi', x.eps);
    await p.waitForTimeout(500);
    await primeraOpcion(p, '#NumeCont');
    await p.selectOption('#TipoUsua', x.tu); await p.selectOption('#TipoAfil', x.ta);
    if (i === 0) await foto(p, 'paciente_nuevo');
    await p.click('main button[type=submit]'); await p.waitForLoadState();
    await estado(p, 'paciente creado ' + x.doc);
    if (i === 0) await foto(p, 'paciente_creado');
    }

    await p.goto(B + `admision_nueva.php?modulo=${x.mod}&TipoDocu=${x.tipo || 'CC'}&NumeUsua=${x.doc}`);
    await estado(p, 'form admision ' + x.mod);
    const h = new Date(Date.now() - 3600e3 - 5 * 3600e3); // hace 1 h, hora Colombia (UTC-5)
    await p.fill('#HoraIngr', h.toISOString().slice(11, 16));
    await p.fill('#DiagIngr', x.mod === 'urg' ? 'R101' : x.mod === 'obs' ? 'A09X' : 'Z000');
    await p.fill('#MotiCons', 'Motivo de prueba (datos inventados) ' + x.mod);
    if (x.mod === 'obs') await primeraOpcion(p, '#CodiCama');
    await primeraOpcion(p, '#NumeCont');
    await primeraOpcion(p, '#CodiEstr');
    await foto(p, 'admision_nueva_' + x.mod);
    await p.click('main button[type=submit]'); await p.waitForLoadState();
    await estado(p, 'admision creada ' + x.mod);
    adm[x.mod] = new URL(p.url()).searchParams.get('id');
    await foto(p, 'ficha_' + x.mod);
  }
  console.log('ADM', JSON.stringify(adm));

  // Triage en urgencias
  // Triage dentro de la historia (pestana 1)
  await p.goto(B + 'admision.php?id=' + adm.urg);
  await p.click('a[data-tab=triage]');
  await p.selectOption('#ClasTria', '3');
  await p.fill('#MotiCons', 'ME DUELE EL ESTOMAGO DESDE AYER');
  await p.fill('#HallClin', 'Paciente consciente, abdomen blando, dolor en epigastrio. Datos inventados.');
  await p.fill('#PANume', '120'); await p.fill('#PADeno', '80'); await p.fill('#Pulso', '88');
  await p.fill('#Respirac', '18'); await p.fill('#Temperat', '37.2'); await p.fill('#Saturaci', '97');
  await p.fill('#Peso', '72'); await p.fill('#Talla', '170'); await p.fill('#Dolor', '6');
  await foto(p, 'triage');
  await p.click('#triage button[type=submit]'); await p.waitForLoadState(); await estado(p, 'triage guardado');

  // Signos
  // Signos dentro de la historia (pestana 2): se cambia de pestana sin recargar
  await p.click('a[data-tab=signos]');
  await p.fill('#PANume', '118'); await p.fill('#PADeno', '76'); await p.fill('#Pulso', '82');
  await p.fill('#Respirac', '17'); await p.fill('#Temperat', '36.8'); await p.fill('#Saturaci', '98');
  await p.fill('#Dolor', '3'); await p.fill('#GlucMetr', '105');
  await foto(p, 'signos');
  await p.click('#signos button[type=submit]'); await p.waitForLoadState(); await estado(p, 'signos guardados');
  await foto(p, 'ficha_urg_con_triage_y_signos');

  for (const m of ['urg', 'obs', 'ce']) { await p.goto(B + 'admisiones.php?modulo=' + m); await foto(p, 'lista_' + m); }
  await p.goto(B + 'index.php'); await foto(p, 'tablero_final');

  // Celular (390x844): tablero, lista de urgencias y ficha
  const m = await br.newPage({ viewport: { width: 390, height: 844 }, locale: 'es-CO', timezoneId: 'America/Bogota', isMobile: true, hasTouch: true });
  await m.goto(B + 'login.php'); await m.fill('#login', 'MEDPRUEBA'); await m.fill('#clave', 'prueba123');
  await m.click('main button[type=submit]'); await m.waitForLoadState();
  await m.goto(B + 'index.php'); await foto(m, 'movil_tablero', 20);
  await m.goto(B + 'admisiones.php?modulo=urg'); await foto(m, 'movil_lista_urg', 21);
  await m.goto(B + 'admision.php?id=' + adm.urg); await foto(m, 'movil_ficha', 22);
  await m.click('[data-menu-abrir]'); await m.waitForTimeout(400);
  await m.screenshot({ path: `${OUT}/24_movil_menu.png` }); console.log('FOTO', `${OUT}/24_movil_menu.png`);
  await m.close();

  await p.click('header button[type=submit]'); await p.waitForLoadState(); await estado(p, 'salir');
  await p.fill('#login', 'NIXON07'); await p.fill('#clave', 'prueba123');
  await p.click('main button[type=submit]'); await p.waitForLoadState(); await estado(p, 'login admin');
  await foto(p, 'tablero_administrador');
  await br.close();
})();
