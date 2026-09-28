// Recorrido de prueba de CRADOR-HC (Playwright): recorre el flujo completo con datos inventados,
// imprime una linea "--" por paso (URL y mensajes) y toma capturas en $OUT.
//   OUT=docs/capturas NODE_PATH=$(npm root -g) node docs/capturas/recorrido.js
const { chromium } = require('playwright');
const B = process.env.BASE || 'http://localhost:8080/';
const OUT = process.env.OUT || './capturas';
const fs = require('fs'); fs.mkdirSync(OUT, { recursive: true });

async function foto(p, nombre) {
  const f = `${OUT}/${nombre}.png`;
  // El pie (Volver · aviso · Continuar) es fijo abajo: en la captura de página completa se deja al final
  await p.evaluate(() => { window.scrollTo(0, 0); document.querySelectorAll('.pie-trabajo').forEach(x => { x.style.position = 'static'; }); });
  await p.waitForTimeout(350);
  await p.screenshot({ path: f, fullPage: true }); console.log('FOTO', f);
  await p.evaluate(() => document.querySelectorAll('.pie-trabajo').forEach(x => { x.style.position = ''; }));
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
// Ventana automatica al abrir la historia (Urgencias y Observacion): antecedentes toxicos, alergicos y reconciliacion
async function cerrarAlertas(p, foto_) {
  const v = await p.$('#alertas-paciente');
  if (v && await v.isVisible()) {
    console.log('-- ventana automatica al abrir la historia', JSON.stringify((await v.innerText()).replace(/\s+/g, ' ').slice(0, 160)));
    if (foto_) { await p.screenshot({ path: `${OUT}/${foto_}.png` }); console.log('FOTO', foto_); }
    await p.click('#alertas-paciente a.boton-primario');
  }
}
// Autocompletar propio: escribe, espera la lista y escoge la opcion con ese codigo (loc: selector o Locator)
async function autocompletar(p, loc, texto, codigo, foto_) {
  const inp = typeof loc === 'string' ? p.locator(loc) : loc;
  await inp.click(); await inp.fill(''); await inp.pressSequentially(texto, { delay: 30 });
  const op = p.locator('.ac-lista:not([hidden]) li', { hasText: codigo }).first();
  await op.waitFor({ state: 'visible', timeout: 5000 });
  if (foto_) { await inp.evaluate(el => el.scrollIntoView({ block: 'center' })); await p.waitForTimeout(200); await p.screenshot({ path: `${OUT}/${foto_}.png` }); console.log('FOTO', foto_); }
  await op.click();
  const v = await inp.inputValue();
  console.log('-- autocompletar', JSON.stringify(texto), '->', v);
}
// Busca el documento en el encabezado de la pantalla del modulo
// Aviso de "guardado / no se guardó": abajo, junto a Continuar, visible sin subir la página
async function aviso(p, etiqueta) {
  const r = await p.evaluate(() => {
    const pie = document.querySelector('.pie-trabajo .pie-avisos');
    const t = pie ? pie.innerText.replace(/\s+/g, ' ').trim() : '';
    const b = pie ? pie.getBoundingClientRect() : null;
    return { texto: t, visible: !!b && b.height > 0 && b.top >= 0 && b.bottom <= window.innerHeight, scrollY: Math.round(window.scrollY),
             tab: (document.querySelector('a.actual') || { dataset: {} }).dataset.tab || '' };
  });
  console.log('-- aviso abajo', etiqueta, JSON.stringify(r));
  return r;
}
// Consulta en la base del stack de prueba (PROYECTO = nombre del proyecto de Docker Compose): solo para verificar
function bd(sql) {
  const { execFileSync } = require('child_process');
  const proy = process.env.PROYECTO || 'hscj2';
  try {
    return execFileSync('docker', ['compose', '-p', proy, 'exec', '-T', 'db', 'sh', '-c',
      'mysql -N -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -e "$0" 2>/dev/null', sql], { encoding: 'utf8' }).trim();
  } catch (e) { return 'ERROR ' + e.message.split('\n')[0]; }
}
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
  // Ingreso vertical y centrado también en celular
  await p.setViewportSize({ width: 390, height: 800 }); await foto(p, '02_login_movil');
  await p.setViewportSize({ width: 1280, height: 900 });
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
    if (x.mod === 'ce') {
      // Documento con autocompletar (desde 3 caracteres, por número o nombre): al escoger carga el paciente
      await p.goto(B + 'atencion.php?modulo=ce&nueva=1');
      await autocompletar(p, '#NumeUsua', x.doc.slice(0, 6), x.tipo + ' ' + x.doc, '56_documento_autocompletar');
      await p.waitForLoadState(); await p.waitForSelector('#form-admision');
    } else {
      await buscarDocumento(p, x.mod, x.tipo, x.doc);
    }
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
    // Diagnóstico de ingreso: ya no se digita (se llena con el de la primera consulta o procedimiento)
    console.log('-- diagnostico de ingreso en nueva admision', await p.$eval('#form-admision', f => f.querySelector('[name=DiagIngr]') ? 'CAMPO' : 'solo lectura'));
    if (x.mod === 'obs') await primeraOpcion(p, '#CodiCama');
    await primeraOpcion(p, '#NumeCont');
    await primeraOpcion(p, '#CodiEstr');
    await foto(p, nums[x.mod][0] + '_admision_nueva_' + x.mod);
    await p.click('#form-admision button[type=submit]'); await p.waitForLoadState();
    await estado(p, 'admision creada ' + x.mod);
    await cerrarAlertas(p);
    adm[x.mod] = new URL(p.url()).searchParams.get('id');
    await foto(p, nums[x.mod][1] + '_ficha_' + x.mod);
  }
  console.log('ADM', JSON.stringify(adm));

  // --- Urgencias: pestañas con los nombres, el orden y la numeración de SIHOS ---
  const pestana = async (t) => { await p.click(`a[data-tab=${t}]`); await p.waitForTimeout(100); };
  const guardar = async (sel, etiqueta) => { await p.click(sel); await p.waitForLoadState(); await estado(p, etiqueta); };
  const signos = async (px, v) => { for (const [c, x] of Object.entries(v)) await p.fill('#' + px + c, x); };
  await p.goto(B + 'atencion.php?id=' + adm.urg);
  await cerrarAlertas(p);
  console.log('-- pestañas urg', JSON.stringify(await p.$$eval('[data-pestanas] > *', l => l.map(x => x.innerText.replace(/\s+/g, ' ').trim()))));

  // 1. Triage (sin consultorio: decision del usuario). Primero sin signos y con peso de 350: mensajes de SIHOS
  await pestana('triage');
  await p.fill('#Peso', '350');
  await p.$eval('#triage form', f => { f.noValidate = true; });
  await guardar('#triage button[type=submit]', 'triage sin signos (mensajes de SIHOS)');
  await foto(p, '55_triage_obligatorios');
  await pestana('triage');
  await p.selectOption('#ClasTria', '3');
  await p.fill('#triage #MotiCons', 'ME DUELE EL ESTOMAGO DESDE AYER');
  await p.fill('#HallClin', 'Paciente consciente, abdomen blando, dolor en epigastrio. Datos inventados.');
  await signos('', { PANume: '120', PADeno: '80', Pulso: '88', Respirac: '18', Temperat: '37.2', Saturaci: '97', Peso: '72', Talla: '170', FetoCard: '', Oximetria: '96' });
  // Diagnostico por nombre con el autocompletar (Z002: el codigo que antes el servidor rechazaba)
  await autocompletar(p, '#CodiDiag', 'crecimiento', 'Z002');
  await foto(p, '12_triage');
  await guardar('#triage button[type=submit]', 'triage guardado');

  // Sin consulta: el DXP de la prescripción es una lista vacía y no deja guardar (normativa 2275)
  await pestana('prescripcion');
  console.log('-- DXP sin consulta', JSON.stringify(await p.$$eval('#PresDiag option', l => l.map(o => o.textContent))));
  await p.$eval('#prescripcion form', f => { f.noValidate = true; });
  await p.locator('#prescripcion tbody [data-fila]').nth(0).locator('input[name="CodiSumi[]"]').fill('MP0006');
  await guardar('#prescripcion button[type=submit]', 'prescripcion sin consulta (no deja guardar)');
  await aviso(p, 'prescripcion sin consulta');
  await p.goto(B + 'atencion.php?id=' + adm.urg + '&tab=triage');
  // Continuar (pie) pasa a la pestaña 2 sin recargar; 18. Signos Vitales
  await p.click('[data-continuar]'); console.log('-- continuar lleva a', await p.$eval('a.actual', a => a.dataset.tab));
  await pestana('signos');
  // Como SIHOS: cada toma debe ser POSTERIOR a la anterior (el triage es la primera)
  const horaMas = async (min) => {   // hora por defecto del formulario (hora del servidor) + min minutos
    const [h, m] = (await p.inputValue('#HoraToma')).split(':').map(Number); const t = (h * 60 + m + min) % 1440;
    await p.fill('#HoraToma', String(Math.floor(t / 60)).padStart(2, '0') + ':' + String(t % 60).padStart(2, '0'));
  };
  await horaMas(1);
  await signos('toma-', { PANume: '118', PADeno: '76', Pulso: '82', Respirac: '17', Temperat: '36.8', Saturaci: '98', GlucMetr: '105' });
  await foto(p, '13_signos');
  await guardar('#signos button[type=submit]', 'signos guardados');
  // Sin límites de valor (como SIHOS; solo peso <= 300 Kg): una toma solo con PA 1/1
  await pestana('signos');
  await horaMas(2); await signos('toma-', { PANume: '1', PADeno: '1' });
  await guardar('#signos button[type=submit]', 'signos PA 1/1 guardados (sin limites)');
  await foto(p, '14_ficha_urg_con_triage_y_signos');

  // 2. Consultas: cinco acordeones, cada uno con su Guardar, y "Cerrar Consulta"
  await pestana('consulta');
  await p.fill('#ConsMoti', 'DOLOR EN LA BOCA DEL ESTOMAGO (datos inventados)');
  await p.fill('#EnfeActu', 'Cuadro de 1 dia de dolor epigastrico urente, sin vomito. Datos inventados.');
  await guardar('#consulta button[name=boton][value=anamnesis]', 'consulta: guardar anamnesis');
  await aviso(p, 'anamnesis');
  await p.click('#consulta summary:has-text("Antecedentes")');
  // Con No la descripción queda de solo lectura; con Si se habilita (y es obligatoria), igual los campos adicionales
  console.log('-- antecedente No: descripcion solo lectura', await p.$eval('#ante-PersDesc', i => i.readOnly), 'parentesco deshabilitado', await p.$eval('#FamiPare', s => s.disabled));
  await p.selectOption('#ante-Personal', '1');
  console.log('-- antecedente Si: descripcion editable', !(await p.$eval('#ante-PersDesc', i => i.readOnly)));
  await p.fill('#ante-PersDesc', 'TEXTO QUE NO SE DEBE BORRAR (inventado)');
  // Planificación: el método es una lista con nombres (códigos provisionales)
  await p.selectOption('#ante-MetoPlan', '1');
  console.log('-- metodos de planificacion', JSON.stringify(await p.$$eval('#MetoDesc option', l => l.map(o => o.value + ' ' + o.textContent))));
  await p.selectOption('#MetoDesc', { label: 'Implante Subdermico' });
  await p.selectOption('#ante-Patologi', '1'); await p.fill('#ante-PatoDesc', 'GASTRITIS HACE 2 AÑOS');
  await p.selectOption('#ante-AlerSiNo', '1'); await p.fill('#ante-AlerDesc', 'PENICILINA (inventado)');
  await p.selectOption('#AlerTipo', '21'); await autocompletar(p, '#AlerMedi', 'MP0001', 'MP0001');
  await p.selectOption('#ante-ToxiAler', '1'); await p.fill('#ante-ToxiDesc', 'TABAQUISMO (inventado)');
  await p.selectOption('#ante-Familiar', '1'); await p.fill('#ante-FamiDesc', 'PADRE DIABETICO (inventado)');
  await p.selectOption('#FamiPare', '1'); await autocompletar(p, '#FamiDiag', 'E86X', 'E86X');
  await p.selectOption('#Preg502', '98');
  await p.selectOption('#ante-FactRies', '1'); await p.selectOption('#FactTipo', '28');
  await p.selectOption('#ante-Andropo', '4');
  // Reconciliacion medicamentosa (RecoMedi)
  await p.check('#RecoMedi');
  const rc = p.locator('#consulta [data-filas] tbody tr').filter({ has: p.locator('input[name="RecoNomb[]"]') }).first();
  await autocompletar(p, rc.locator('input[name="RecoNomb[]"]'), 'omepra', 'MP0004');
  await rc.locator('input[name="RecoCant[]"]').fill('20'); await rc.locator('input[name="RecoFrec[]"]').fill('24');
  await rc.locator('select[name="RecoVia[]"]').selectOption('1'); await rc.locator('input[name="RecoNota[]"]').fill('LO TOMA EN CASA (inventado)');
  await p.evaluate(() => document.querySelector('#consulta button[name=boton][value=antecedentes]').scrollIntoView({ block: 'center' }));
  const yAntes = await p.evaluate(() => Math.round(window.scrollY));
  await guardar('#consulta button[name=boton][value=antecedentes]', 'consulta: guardar antecedentes');
  const r6 = await aviso(p, 'antecedentes (se queda en la seccion)');
  console.log('-- se queda en Antecedentes: acordeon abierto', await p.$eval('#sec-antecedentes', d => d.open), 'scroll antes', yAntes, 'despues', r6.scrollY);
  await p.screenshot({ path: `${OUT}/57_guardar_se_queda.png` }); console.log('FOTO 57_guardar_se_queda');
  // Con "No" NO se borra el texto que ya existía
  await p.selectOption('#ante-Personal', '2');
  await guardar('#consulta button[name=boton][value=antecedentes]', 'consulta: antecedente Personal pasa a No');
  console.log('-- BD Antecede Personal/PersDesc/MetoPlan/MetoDesc', bd("SELECT Personal, PersDesc, MetoPlan, MetoDesc FROM Antecede WHERE ConsAdmi = '" + adm.urg + "'"));
  await p.$eval('#sec-antecedentes', d => d.scrollIntoView({ block: 'start' }));
  await p.screenshot({ path: `${OUT}/58_antecedentes_si_no.png` }); console.log('FOTO 58_antecedentes_si_no');
  await p.click('#consulta summary:has-text("Revisión por Sistema")');
  await p.fill('#ConsRevi', 'NIEGA OTROS SINTOMAS'); await p.selectOption('#SintResp', '2');
  await signos('cons-', { PANume: '116', PADeno: '74', Pulso: '80', Respirac: '16', Temperat: '36.7', Saturaci: '98', Oximetria: '97' });
  await p.fill('#EstaGene', 'ALERTA, HIDRATADO, AFEBRIL'); await p.fill('#PeriAbdo', '88'); await p.fill('#PeriTorx', '92');
  await p.selectOption('#ex-Abdomen', '2'); await p.fill('#consulta input[name=AbdoDesc]', 'DOLOR A LA PALPACION EN EPIGASTRIO');
  await p.selectOption('#ex-Ano', '3');   // No se Explora = 3 (confirmado)
  await p.fill('#LaboImag', 'SIN PARACLINICOS PREVIOS (inventado)');
  await autocompletar(p, '#cons-CodiDiag', 'gastritis', 'K297'); await p.selectOption('#cons-TipoDiag', '1');
  await p.fill('#cons-CodiRel1', 'E86X'); await p.selectOption('#cons-TipoDia1', '2');
  await p.selectOption('#ConsDest', '04'); await p.selectOption('#Conducta', '121');
  await p.fill('#ObseReco', 'OMEPRAZOL, DIETA BLANDA, CONTROL EN 24 HORAS');
  await foto(p, '27_consulta_formulario');
  await guardar('#consulta button[name=boton][value=cerrar]', 'consulta cerrada');
  await foto(p, '27_consulta');
  // 20. Plan de Manejo: el mismo ObseReco de la consulta
  await pestana('plan');
  await p.fill('#ObseRecoPlan', 'OMEPRAZOL, DIETA BLANDA, CONTROL EN 24 HORAS. SIGNOS DE ALARMA EXPLICADOS.');
  await guardar('#plan button[type=submit]', 'plan de manejo guardado');
  await foto(p, '49_plan_manejo');

  // 4. Prescripción
  await pestana('prescripcion');
  // Rejilla de SIHOS: Cantidad por dosis · Unidad · Vía · Cada · A partir de · Número (Dosis) · Cantidad solicitada
  // DXP = lista con los diagnósticos de la consulta; DXR 1-4 con buscador CIE-10
  console.log('-- DXP con consulta', JSON.stringify(await p.$$eval('#PresDiag option', l => l.map(o => o.textContent))));
  await p.selectOption('#TipoPres', '1'); await autocompletar(p, '#PresRel1', 'R101', 'R101');
  const f1 = p.locator('#prescripcion tbody [data-fila]').nth(0);
  await autocompletar(p, f1.locator('input[name="CodiSumi[]"]'), 'omepra', 'MP0004', '53_prescripcion_autocompletar');
  await f1.locator('input[name="CantSumi[]"]').fill('20');
  await f1.locator('select[name="UnidMedi[]"]').selectOption('1'); await f1.locator('select[name="CodiVia[]"]').selectOption('1');
  await f1.locator('select[name="HoraApli[]"]').selectOption('24');
  await f1.locator('input[name="HoraInic[]"]').fill('08:00');
  await f1.locator('input[name="PresMedi[]"]').fill('EN AYUNAS');
  await f1.locator('select[name="MediPrin[]"]').selectOption('1');
  const f2 = p.locator('#prescripcion tbody [data-fila]').nth(1);
  // Un solo buscador (código o nombre): al escoger llena código, nombre, unidad, vía y contenido de la fila.
  // Ejemplo de SIHOS: diclofenaco 75 mg cada 12 h -> 2 dosis -> 150 mg -> 2 unidades (Contenid 75)
  await autocompletar(p, f2.locator('input[name="CodiSumi[]"]'), 'diclofen', 'MP0006'); await f2.locator('input[name="CantSumi[]"]').fill('75');
  console.log('-- buscador de medicamentos llena unidad y via', await f2.locator('select[name="UnidMedi[]"]').inputValue(), await f2.locator('select[name="CodiVia[]"]').inputValue());
  await f2.locator('select[name="HoraApli[]"]').selectOption('12');
  console.log('-- calculo 75 mg c/12 h: NumeDosi', await f2.locator('input[name="NumeDosi[]"]').inputValue(), 'CantSoli', await f2.locator('input[name="CantSoli[]"]').inputValue(), JSON.stringify(await f2.locator('[data-total]').innerText()));
  await f2.locator('input[name="PresMedi[]"]').fill('APLICAR LENTO');
  // AHORA = 1 dosis; acetaminofen 1000 mg c/6 h -> 4 dosis -> 4000 mg -> 8 tabletas de 500
  const f3 = p.locator('#prescripcion tbody [data-fila]').nth(2);
  await autocompletar(p, f3.locator('input[name="CodiSumi[]"]'), 'acetamin', 'MP0001'); await f3.locator('input[name="CantSumi[]"]').fill('1000');
  await f3.locator('select[name="HoraApli[]"]').selectOption('0');
  console.log('-- calculo 1000 mg AHORA: NumeDosi', await f3.locator('input[name="NumeDosi[]"]').inputValue(), 'CantSoli', await f3.locator('input[name="CantSoli[]"]').inputValue());
  await f3.locator('select[name="HoraApli[]"]').selectOption('6');
  console.log('-- calculo 1000 mg c/6 h: NumeDosi', await f3.locator('input[name="NumeDosi[]"]').inputValue(), 'CantSoli', await f3.locator('input[name="CantSoli[]"]').inputValue());
  await f3.locator('input[name="PresMedi[]"]').fill('SI HAY FIEBRE');
  // Máximo 24 horas: 3 dosis cada 12 h no deja guardar (mensaje de SIHOS)
  await f2.locator('input[name="NumeDosi[]"]').fill('3');
  console.log('-- 3 dosis c/12 h', JSON.stringify(await f2.locator('[data-total]').innerText()));
  await p.$eval('#prescripcion form', f => { f.noValidate = true; });
  await guardar('#prescripcion button[type=submit]', 'prescripcion de mas de 24 horas');
  await aviso(p, 'prescripcion mas de 24 h');
  await p.locator('#prescripcion tbody [data-fila]').nth(1).locator('input[name="NumeDosi[]"]').fill('2');
  await foto(p, '28_prescripcion_formulario');
  await guardar('#prescripcion button[type=submit]', 'prescripcion guardada');
  await aviso(p, 'prescripcion guardada');
  console.log('-- BD DetaPres', bd("SELECT CONCAT_WS(' ', CodiSumi, CantSumi, 'HoraApli', HoraApli, 'NumeDosi', NumeDosi, 'CantTota', CantTota, 'Contenid', Contenid, 'CantSoli', CantSoli) FROM DetaPres WHERE ConsAdmi = '" + adm.urg + "' ORDER BY Item").replace(/\n/g, ' | '));
  console.log('-- BD EncaPres DXP', bd("SELECT CONCAT_WS(' ', CodiDiag, CodiRel1) FROM EncaPres WHERE ConsAdmi = '" + adm.urg + "'"));
  await foto(p, '28_prescripcion');

  // 5. ORDENES MEDICAS y 7. Ordenación
  await pestana('ordenes_medicas');
  await p.fill('#TextoOrden', 'DIETA BLANDA\nLEV: SSN 0.9% 100 CC/HORA\nCONTROL DE SIGNOS VITALES CADA 4 HORAS\nAVISAR CAMBIOS');
  await guardar('#ordenes_medicas button[type=submit]', 'orden medica guardada');
  await foto(p, '38_ordenes_medicas');
  await pestana('ordenacion');
  console.log('-- DXP Ordenacion', JSON.stringify(await p.$$eval('#OrdeDiag option', l => l.map(o => o.textContent))));
  await p.selectOption('#OrdeFina', '10'); await autocompletar(p, '#OrdeRel1', 'R101', 'R101');
  const o1 = p.locator('#ordenacion tbody [data-fila]').nth(0);
  await autocompletar(p, o1.locator('input[name="OrdProc[]"]'), 'hemograma', '902210', '54_ordenacion_autocompletar');
  const o2 = p.locator('#ordenacion tbody [data-fila]').nth(1);
  await autocompletar(p, o2.locator('input[name="OrdProc[]"]'), 'radiografia', '871121'); await o2.locator('input[name="OrdObse[]"]').fill('DESCARTAR NEUMOPERITONEO');
  await guardar('#ordenacion button[type=submit]', 'ordenes guardadas');
  await foto(p, '29_ordenacion');

  // 6. Procedimientos (el selector "Atiende la orden" no está en SIHOS: quedó oculto)
  await pestana('procedimientos');
  console.log('-- procedimiento: diagnostico principal por defecto (el de la consulta)', await p.inputValue('#proc-DiagPrin'));
  console.log('-- BD diagnostico de ingreso (primera consulta)', bd("SELECT DiagIngr FROM Admision WHERE ConsAdmi = '" + adm.urg + "'"));
  await p.fill('#CodiProc', '939403'); await p.selectOption('#CodiFina', '2'); await p.fill('#CantProc', '1');
  await p.fill('#IndiAdic', 'NEBULIZACION CON SSN, SIN COMPLICACIONES (datos inventados)');
  await p.fill('#proc-DiagRela', 'E86X'); await p.selectOption('#proc-ProcTipoDiaR', '2');
  await guardar('#procedimientos button[type=submit]', 'procedimiento guardado');
  await pestana('procedimientos');
  await p.fill('#CodiProc', '902210'); await p.selectOption('#CodiFina', '1'); await p.check('#ProcRevi');
  await p.fill('#IndiAdic', 'TOMA DE MUESTRA PARA HEMOGRAMA');
  await guardar('#procedimientos button[type=submit]', 'procedimiento revisado guardado');
  await foto(p, '30_procedimientos');

  // 8. Evolución (con fila de signos y Rela 1)
  await pestana('evolucion');
  await p.fill('#EvolProc', '89060102'); await p.fill('#Subjetivo', 'REFIERE MEJORIA DEL DOLOR'); await p.fill('#Objetivo', 'ABDOMEN BLANDO, DOLOR LEVE EN EPIGASTRIO');
  await signos('evol-', { Peso: '68', Talla: '165', PANume: '114', PADeno: '72', Pulso: '78', Respirac: '16', Temperat: '36.6' });
  await p.fill('#evol-EvolDiag', 'K297'); await p.fill('#evol-EvolRel1', 'E86X'); await p.selectOption('#evol-EvolTipoRel1', '2');
  await p.fill('#Analisis', 'EVOLUCION FAVORABLE'); await p.fill('#PlanMane', 'CONTINUAR MANEJO, VALORAR SALIDA');
  await p.check('#ContSign'); await p.check('#EvolRevi');
  await guardar('#evolucion button[type=submit]', 'evolucion guardada');
  await foto(p, '32_evolucion');

  // 9. Notas Enfermería y 10. Notas Médicas (con "Revisada")
  await pestana('notas_enfermeria');
  await p.fill('#NotaEnfe', 'PACIENTE EN CAMILLA, ALERTA, CON LEV PERMEABLES. SE TOMAN SIGNOS. (datos inventados)');
  await p.fill('#NotaActi', '939403'); await p.check('#Reviza');
  await guardar('#notas_enfermeria button[type=submit]', 'nota de enfermeria guardada');
  await foto(p, '31_notas_enfermeria');
  await pestana('notas_medicas');
  await p.fill('#NotaEnfeMed', 'SE EXPLICA AL PACIENTE EL PLAN DE MANEJO. (datos inventados)'); await p.check('#RevizaMed');
  await guardar('#notas_medicas button[type=submit]', 'nota medica guardada');
  await foto(p, '39_notas_medicas');

  // 11. Medicamentos, 16. Materiales, 15. Remisiones y 17. Incapacidad
  await pestana('medicamentos');
  const m1 = p.locator('#medicamentos form[method=post] tbody tr').first();
  await m1.locator('input[name="CantMedi[]"]').fill('1'); await m1.locator('input[name="MediObse[]"]').fill('SIN REACCIONES');
  await guardar('#medicamentos form[method=post] button[type=submit]', 'medicamento aplicado');
  await foto(p, '40_medicamentos');
  await pestana('materiales');
  const mt = p.locator('#materiales tbody [data-fila]').first();
  await autocompletar(p, mt.locator('input[name="CodiMate[]"]'), 'MQ0002', 'MQ0002'); await mt.locator('select[name="UnidMate[]"]').selectOption('01');
  await mt.locator('input[name="CantMate[]"]').fill('1'); await mt.locator('input[name="MateObse[]"]').fill('CANALIZACION VENA ANTEBRAZO IZQUIERDO');
  await guardar('#materiales button[type=submit]', 'material registrado');
  await foto(p, '41_materiales');
  await pestana('remisiones');
  await p.selectOption('#EspeRemi', { index: 1 }); await p.selectOption('#InstRemi', '02'); await p.fill('#RemiAuto', 'AUT-0001'); await p.check('#Ambulanc'); await p.fill('#PlacAmbu', 'OAA123');
  await p.fill('#NombAcep', 'MEDICO DE PRUEBA RECEPTOR'); await p.fill('#CargAcep', 'MEDICO DE TURNO');
  await p.selectOption('#RemiMoti', '2'); await p.selectOption('#ModaSoli', '2');
  await p.fill('#FechAcep', await p.inputValue('#FechRemi')); await p.fill('#HoraAcep', await p.inputValue('#HoraRemi'));
  await p.fill('#MotiRemiTexto', 'PACIENTE REQUIERE VALORACION POR CIRUGIA GENERAL. Datos inventados.');
  await guardar('#remisiones button[type=submit]', 'remision guardada (urg)');
  await foto(p, '42_remisiones');
  await pestana('incapacidad');
  await p.fill('#DiasIncaPaci', '2'); await p.fill('#ObseInca', 'REPOSO RELATIVO (inventado)');
  await p.fill('#EdadGest', '0');
  await guardar('#incapacidad button[type=submit]', 'incapacidad guardada (urg)');
  await foto(p, '43_incapacidad');
  // Al volver a abrir la historia sale la ventana automatica con los antecedentes toxicos y alergicos
  await p.goto(B + 'atencion.php?id=' + adm.urg);
  await cerrarAlertas(p, '52_ventana_antecedentes');

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
  await p.goto(B + 'atencion.php?id=' + adm.obs + '&tab=cambio');
  console.log('-- pestañas obs', JSON.stringify(await p.$$eval('[data-pestanas] > *', l => l.map(x => x.innerText.replace(/\s+/g, ' ').trim()))));
  // 23. Cambio de Atención (pestaña, TrasCama)
  await p.selectOption('#CamaDest', 'HOSP10');
  await guardar('#cambio button[type=submit]', 'cambio de atencion (traslado de cama)');
  await foto(p, '36_cambio_atencion');
  // 1. Consultas en Observación (TipoCons por defecto 89060102) y 24. Remisiones (pestaña propia)
  await p.goto(B + 'atencion.php?id=' + adm.obs + '&tab=consulta');
  await p.fill('#ConsMoti', 'DIARREA DE 2 DIAS (datos inventados)'); await p.fill('#EnfeActu', 'Deposiciones liquidas, sin sangre. Datos inventados.');
  await p.fill('#cons-CodiDiag', 'A09X'); await p.selectOption('#cons-TipoDiag', '1');
  // Cerrar Consulta exige (como SIHOS) signos vitales y Revisión por Sistema
  await p.click('#consulta summary:has-text("Revisión por Sistema")');
  await p.fill('#ConsRevi', 'NIEGA OTROS SINTOMAS');
  await signos('cons-', { Peso: '70', Talla: '170', PANume: '110', PADeno: '70', Pulso: '84', Respirac: '18', Temperat: '37', Saturaci: '97' });
  await p.fill('#ObseReco', 'HIDRATACION ORAL, CONTROL DE LIQUIDOS');
  await p.selectOption('#ConsDest', '03'); await p.selectOption('#Conducta', '125');
  await guardar('#consulta button[name=boton][value=cerrar]', 'consulta obs guardada y cerrada');
  await foto(p, '44_obs_consultas');
  await p.goto(B + 'atencion.php?id=' + adm.obs + '&tab=remisiones');
  await p.selectOption('#RemiMoti', '2'); await p.selectOption('#ModaSoli', '2');
  await p.selectOption('#InstRemi', '03'); await p.fill('#RemiAuto', 'AUT-0002'); await p.selectOption('#EspeRemi', { index: 1 }); await p.fill('#CargAcep', 'MEDICO DE TURNO');
  await p.fill('#MotiRemiTexto', 'PACIENTE REQUIERE VALORACION POR MEDICINA INTERNA. Datos inventados.');
  await p.fill('#NombAcep', 'MEDICO DE PRUEBA RECEPTOR'); await p.check('input[name=Ambulanc]');
  await guardar('#remisiones button[type=submit]', 'remision guardada (obs)');
  await foto(p, '37_obs_remisiones');
  await p.goto(B + 'atencion.php?id=' + adm.obs + '&tab=incapacidad');
  await p.fill('#DiasIncaPaci', '3'); await p.fill('#ObseInca', 'REPOSO EN CASA (inventado)');
  await guardar('#incapacidad button[type=submit]', 'incapacidad guardada (obs)');

  // Cerrar Historia sin egreso: lleva a la pestaña Egreso (como SIHOS)
  await p.goto(B + 'atencion.php?id=' + adm.obs + '&tab=signos');
  await p.click('.et-acciones-sihos a[data-abrir-ventana=cerrar-historia]');
  await p.click('#cerrar-historia button[type=submit]'); await p.waitForLoadState(); await estado(p, 'cerrar historia sin egreso');
  // Egreso en Observación: Guardar, Modificar y luego Cerrar Historia
  await p.goto(B + 'atencion.php?id=' + adm.obs + '&tab=egreso');
  await p.fill('#ObseSali', 'SALE EN BUENAS CONDICIONES (datos inventados)');
  await p.fill('#egre-EgreRel1', 'E86X'); await p.selectOption('#egre-EgreTipoRel1', '2');
  await foto(p, '33_egreso');
  await guardar('#egreso form:has(input[name=accion][value=egreso]) button[type=submit]', 'egreso guardado (sin cerrar)');
  await p.fill('#ObseSali', 'SALE EN BUENAS CONDICIONES, CITA DE CONTROL (datos inventados)');
  await guardar('#egreso form:has(input[name=accion][value=egreso]) button[type=submit]', 'egreso modificado');
  await p.click('#egreso a[data-abrir-ventana=cerrar-historia]');
  await foto(p, '51_cerrar_historia');
  await p.click('#cerrar-historia button[type=submit]'); await p.waitForLoadState(); await estado(p, 'historia obs cerrada');
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
  await p.selectOption('#FamiPare', '2'); await autocompletar(p, '#FamiDiag', 'R101', 'R101');
  // Guardar en la pestaña 3. Antecedentes: se queda en Antecedentes (no vuelve a Anamnesis)
  await pestana('anamnesis'); await p.fill('#EnfeActu', 'ASISTE A CONTROL, SIN QUEJAS. Datos inventados.'); await pestana('antecedentes');
  await guardar('#antecedentes button[type=submit]', 'consulta CE: guardar antecedentes');
  await aviso(p, 'CE antecedentes (se queda en la pestaña)');
  await pestana('anamnesis'); await p.fill('#EnfeActu', '');
  await pestana('laboratorios');
  await p.fill('#LaboImag', 'NO TRAE PARACLINICOS'); await p.fill('#cons-CodiDiag', 'Z000'); await p.selectOption('#cons-TipoDiag', '1');
  await pestana('plan');
  await p.fill('#ObseReco', 'CONTROL EN 6 MESES'); await p.selectOption('#Conducta', '126'); await p.selectOption('#EstaCodo', '04');
  await foto(p, '50_ce_plan');
  // Sin enfermedad actual (pestaña 1): el error debe llevar a la pestaña 1
  await guardar('#plan button[type=submit]', 'consulta CE sin enfermedad actual');
  console.log('-- pestaña con el error', await p.$eval('a.actual', a => a.dataset.tab));
  await p.fill('#EnfeActu', 'ASISTE A CONTROL, SIN QUEJAS. Datos inventados.');
  await guardar('#anamnesis button[type=submit]', 'consulta CE guardada');
  // 5. Prescripción A (fórmula de salida fija: PresSali = 2)
  await pestana('prescripcion');
  // Ejemplos de SIHOS (fórmula ambulatoria): c/12 h por 5 días -> 10; c/8 h por 3 días -> 9; c/6 h por 3 días -> 12;
  // 1000 mg x 12 = 12000 / 500 = 24 tabletas
  console.log('-- DXP Prescripcion A', JSON.stringify(await p.$$eval('#PresDiag option', l => l.map(o => o.textContent))));
  const fc = p.locator('#prescripcion tbody [data-fila]').nth(0);
  await autocompletar(p, fc.locator('input[name="CodiSumi[]"]'), 'acetamin', 'MP0001'); await fc.locator('input[name="CantSumi[]"]').fill('1000');
  await fc.locator('select[name="CodiVia[]"]').selectOption('1');
  for (const [fr, du, dt] of [['12', '5', '2'], ['8', '3', '2'], ['6', '3', '2']]) {
    await fc.locator('input[name="CantFrec[]"]').fill(fr); await fc.locator('select[name="TiemFrec[]"]').selectOption('1');
    await fc.locator('input[name="CantPeDu[]"]').fill(du); await fc.locator('select[name="TiemPeDu[]"]').selectOption(dt);
    console.log(`-- calculo ambulatorio c/${fr} h por ${du} dias: Total (Dosis)`, await fc.locator('input[name="NumeDosi[]"]').inputValue(), 'CantSoli', await fc.locator('input[name="CantSoli[]"]').inputValue());
  }
  await foto(p, '59_prescripcion_a_calculo');
  await guardar('#prescripcion button[type=submit]', 'prescripcion A CE guardada');
  console.log('-- BD DetaPres CE', bd("SELECT CONCAT_WS(' ', CodiSumi, CantSumi, 'c/', CantFrec, TiemFrec, 'x', CantPeDu, TiemPeDu, 'NumeDosi', NumeDosi, 'CantTota', CantTota, 'Contenid', Contenid, 'CantSoli', CantSoli) FROM DetaPres WHERE ConsAdmi = '" + adm.ce + "'"));
  await pestana('notas_medicas');
  await p.fill('#NotaEnfeMed', 'SE ENTREGAN RECOMENDACIONES. (datos inventados)');
  await guardar('#notas_medicas button[type=submit]', 'nota medica CE guardada');
  // "Cerrar Historia" del encabezado (no hay pestaña de egreso en Consulta Externa)
  await p.click('.et-acciones-sihos a[data-abrir-ventana=cerrar-historia]');
  await foto(p, '47_ce_cerrar_historia');
  await p.click('#cerrar-historia button[type=submit]'); await p.waitForLoadState(); await estado(p, 'historia CE cerrada');
  await foto(p, '35_ce_cerrada');
  // Buscar como SIHOS: número de admisión + Enter abre una historia CERRADA (solo lectura)
  await p.goto(B + 'atencion.php?modulo=urg&nueva=1');
  await p.fill('#adm', adm.obs.toLowerCase()); await p.press('#adm', 'Enter'); await p.waitForLoadState();
  console.log('-- admision cerrada por numero', p.url().replace(B, '/'), JSON.stringify(await p.$eval('.et-estado', e => e.innerText)),
              'solo lectura:', await p.$$eval('.alerta', l => l.some(x => x.innerText.includes('solo se puede consultar'))));
  await cerrarAlertas(p);
  await foto(p, '60_admision_cerrada_por_numero');
  await p.goto(B + 'atencion.php?modulo=urg&nueva=1');
  await p.fill('#adm', 'C00000000000'); await p.press('#adm', 'Enter'); await p.waitForLoadState(); await aviso(p, 'admision que no existe');
  // El documento también abre las cerradas: sin admisión abierta sale la lista de sus admisiones
  await buscarDocumento(p, 'urg', 'CC', '99000006');
  console.log('-- admisiones del paciente (documento)', JSON.stringify(await p.$$eval('.tabla-admisiones tbody tr', l => l.map(x => x.innerText.replace(/\s+/g, ' ').trim()))));
  await foto(p, '61_documento_admisiones_cerradas');
  // Ventana "Historias" del módulo: TODAS las admisiones (abiertas y cerradas) con su estado y filtro
  await p.goto(B + 'atencion.php?modulo=ce&historias=1');
  const filasHist = async () => p.$$eval('#ventana-historias tbody tr, .tabla-historias tbody tr', l => [...new Set(l)].map(x => {
    const c = x.querySelector('[data-etiqueta="Admisión"] a'), e = x.querySelector('[data-etiqueta="Estado"]');
    return (c ? c.innerText.trim() : '') + ' ' + (e ? e.innerText.trim() : ''); }));
  console.log('-- historias CE (todas)', JSON.stringify(await filasHist()));
  await foto(p, '62_historias_todas');
  await p.goto(B + 'atencion.php?modulo=ce&historias=1&estado=cerradas');
  console.log('-- historias CE (cerradas)', JSON.stringify(await filasHist()));
  // Clic en una cerrada: se abre en solo lectura
  await p.click('.tabla-historias tbody tr:first-child [data-etiqueta="Admisión"] a'); await p.waitForLoadState();
  console.log('-- cerrada desde la ventana', p.url().replace(B, '/'), JSON.stringify(await p.$eval('.et-estado', e => e.innerText)));
  await cerrarAlertas(p);
  // Pantalla "Admisiones" (menú lateral): los 3 módulos, filtro por estado y búsqueda por número de admisión
  await p.click('nav.menu a[href^="admisiones.php"]'); await p.waitForLoadState();
  await p.selectOption('select[name=estado]', 'cerrada'); await p.click('.filtros-admisiones button[type=submit]'); await p.waitForLoadState();
  const filasAdm = async () => p.$$eval('.tabla-listado-admisiones tbody tr', l => l.map(x => x.querySelector('[data-etiqueta="Admisión"]').innerText.trim()
    + ' ' + x.querySelector('[data-etiqueta="Estado"]').innerText.trim()));
  console.log('-- admisiones cerradas', JSON.stringify(await filasAdm()));
  await foto(p, '63_admisiones_cerradas');
  await p.selectOption('select[name=estado]', ''); await p.fill('#q-admisiones', adm.obs); await p.press('#q-admisiones', 'Enter'); await p.waitForLoadState();
  console.log('-- admisiones buscar por numero', adm.obs, JSON.stringify(await filasAdm()));
  await foto(p, '64_admisiones_buscar');
  const resp = await p.request.get(B + 'admisiones.php?filtrar=1&estado=cerrada&csv=1');
  console.log('-- admisiones CSV', resp.headers()['content-type'], (await resp.text()).split('\n').length - 2, 'filas');
  await p.setViewportSize({ width: 390, height: 844 }); await p.goto(B + 'admisiones.php?filtrar=1');
  await p.screenshot({ path: `${OUT}/65_movil_admisiones.png` }); console.log('FOTO', '65_movil_admisiones');
  await p.setViewportSize({ width: 1280, height: 900 });
  await p.goto(B + 'atencion.php?modulo=urg&historias=1');

  // Salir e ingreso del administrador: el administrador si ve el tablero
  await p.keyboard.press('Escape'); // cierra la ventana de historias abiertas
  await p.click('header button[type=submit]'); await p.waitForLoadState(); await estado(p, 'salir');
  await p.fill('#login', 'NIXON07'); await p.fill('#clave', 'prueba123');
  await p.click('main button[type=submit]'); await p.waitForLoadState(); await estado(p, 'login admin');
  await foto(p, '19_tablero_administrador');
  await br.close();
})();
