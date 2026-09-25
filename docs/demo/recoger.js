// Recoge el HTML renderizado de las pantallas de la app (datos de prueba) para la demo estatica.
const { chromium } = require('playwright');
const fs = require('fs');
const B = 'http://localhost:8080/';
const paginas = {}; const api = {};
async function tomar(p, clave, url) {
  const r = await p.goto(B + url); await p.waitForLoadState('networkidle');
  const d = await p.evaluate(() => ({ cls: document.body.className, html: document.body.innerHTML, title: document.title }));
  d.html = d.html.replace(/<script[\s\S]*?<\/script>/g, '');
  paginas[clave] = d; console.log(clave, r.status(), d.title);
}
async function ingresar(p, login) {
  await p.goto(B + 'login.php'); await p.fill('#login', login); await p.fill('#clave', 'prueba123');
  await p.click('main button[type=submit]'); await p.waitForLoadState();
}
(async () => {
  const br = await chromium.launch({ args: ['--lang=es-CO'] });
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 }, locale: 'es-CO', timezoneId: 'America/Bogota' });
  const p = await ctx.newPage();
  await tomar(p, 'login.php', 'login.php');
  await ingresar(p, 'MEDPRUEBA');
  await tomar(p, 'modulo.php', 'modulo.php');
  const ids = { urg: ['C26092500001', 'PRUEBA000001', 'PRUEBA000002', 'PRUEBA000003'],
                obs: ['C26092500002', 'PRUEBA000005', 'PRUEBA000006'],
                ce: ['C26092500003', 'PRUEBA000007', 'PRUEBA000008', 'PRUEBA000009'] };
  for (const m of Object.keys(ids)) {
    await tomar(p, 'atencion.php?modulo=' + m, 'atencion.php?modulo=' + m + '&historias=1');
    await tomar(p, 'atencion.php?modulo=' + m + '&nueva=1', 'atencion.php?modulo=' + m + '&nueva=1');
    for (const id of ids[m]) await tomar(p, `atencion.php?modulo=${m}&id=${id}`, `atencion.php?modulo=${m}&id=${id}`);
    for (const doc of ['99000007']) await tomar(p, `atencion.php?modulo=${m}&TipoDocu=CC&NumeUsua=${doc}`, `atencion.php?modulo=${m}&TipoDocu=CC&NumeUsua=${doc}`);
  }
  await tomar(p, 'pacientes.php', 'pacientes.php');
  await tomar(p, 'pacientes.php?q=PRUEBA', 'pacientes.php?q=PRUEBA');
  await tomar(p, 'paciente_nuevo.php', 'paciente_nuevo.php');
  // Respuestas de listas dependientes y buscadores
  const pedir = async (q) => { api[q] = await p.evaluate(u => fetch(u).then(r => r.json()), B + 'api.php?' + q); };
  for (const d of ['86', '52']) await pedir('que=municipios&depa=' + d);
  for (const a of ['EPSP01', 'EPSP02']) { await pedir('que=contratos&admi=' + a);
    for (const at of [1, 3]) for (const f of ['A', 'B', 'C', 'D']) await pedir(`que=estratos&admi=${a}&aten=${at}&afil=${f}`); }
  for (const s of ['008', '007', '001', '013']) await pedir('que=camas&serv=' + s);
  const letras = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'.split('');
  const catalogo = {};
  for (const que of ['diagnosticos', 'procedimientos', 'suministros']) {
    const todos = {};
    for (const a of letras) for (const b of letras) {
      const r = await p.evaluate(u => fetch(u).then(r => r.json()), B + `api.php?que=${que}&q=${a}${b}`);
      (r || []).forEach(x => { todos[x.c] = x.n; });
    }
    catalogo[que] = Object.entries(todos).map(([c, n]) => ({ c, n }));
    console.log(que, catalogo[que].length);
  }
  // Administrador
  await p.goto(B + 'logout.php').catch(() => {});
  const ctx2 = await br.newContext({ viewport: { width: 1280, height: 900 }, locale: 'es-CO' });
  const q = await ctx2.newPage(); await ingresar(q, 'NIXON07');
  await tomar(q, 'admin:index.php', 'index.php');
  await tomar(q, 'admin:catalogos.php', 'catalogos.php');
  fs.writeFileSync('recogido.json', JSON.stringify({ paginas, api, catalogo }));
  await br.close();
})();
