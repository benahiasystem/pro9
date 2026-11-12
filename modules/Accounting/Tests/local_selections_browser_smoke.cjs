const {chromium} = require(process.env.ACCOUNTING_PLAYWRIGHT_MODULE || '@playwright/test');
const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const root = path.resolve(process.env.ACCOUNTING_RUNTIME_DIR || '.accounting-isolated');
assert.equal(path.basename(root), '.accounting-isolated');
if (!process.env.ACCOUNTING_FIXTURE_LOCKED) {
  const locked = require('node:child_process').spawnSync('flock', [root+'/fixture.lock', process.execPath, __filename],
    {stdio:'inherit', env:{...process.env, ACCOUNTING_FIXTURE_LOCKED:'1'}});
  process.exit(locked.status ?? 1);
}
const actors = JSON.parse(fs.readFileSync(root+'/clone-access.json')), actor = actors.autor, reviewer = actors.revisor;
const fixture = JSON.parse(fs.readFileSync(root+'/local-selections-fixture.json'));
const origin = 'http://contabilidad.localhost:9080', base = '/api/accounting/v1/companies/'+fixture.company_id;
const phrase = 'Alternativas cifradas de prueba 2026', stamp = Date.now();
const original = 'Alternativa del respaldo '+stamp, edited = 'Edición local posterior '+stamp;
let browser, context, page, chart;
const checks = [], errors = [];
function headers(user = reviewer) { return {Host:'contabilidad.localhost:9080', Accept:'application/json', Authorization:'Bearer '+user.token, 'X-Accounting-Verification':user.verification}; }
async function request(url, data, user = reviewer, method = 'GET', extra = {}) {
  let r = await context.request.fetch(origin+url, {method, data, headers:{...headers(user),...extra}});
  if (r.status()===429 && method==='GET') {
    const seconds=Number(r.headers()['retry-after']); assert(Number.isFinite(seconds)&&seconds<=60);
    await new Promise(resolve=>setTimeout(resolve,(seconds+0.1)*1000));
    r=await context.request.fetch(origin+url,{method,headers:{...headers(user),...extra}});
  }
  assert.equal(r.headers()['x-accounting-isolated-test'],'1');
  const body=await r.json(); assert.equal(r.status(),200,'Respuesta inesperada: '+body.code);return body;
}
async function observe(c) { context=c;page=await c.newPage();page.on('pageerror',e=>errors.push(e.message));return page; }
async function login() {
  await page.goto(origin+'/contabilidad/');
  await page.getByLabel('Correo electrónico de PRO9').fill(actor.email);
  await page.getByLabel('Contraseña',{exact:true}).fill(actor.password);
  const auth=page.waitForResponse(r=>r.url().endsWith('/api/login')&&r.request().method()==='POST');
  await page.getByRole('button',{name:'Entrar',exact:true}).click();actor.token=(await (await auth).json()).token;
  await page.getByLabel('Código de verificación',{exact:true}).waitFor();const code=actor.recovery_codes.shift();assert(code);
  fs.writeFileSync(root+'/clone-access.json',JSON.stringify(actors,null,2));
  await page.getByLabel('Código de verificación',{exact:true}).fill(code);
  const verified=page.waitForResponse(r=>r.url().endsWith('/auth/mfa/verify'));
  await page.getByRole('button',{name:'Verificar y continuar'}).click();actor.verification=(await (await verified).json()).verification_token;
  fs.writeFileSync(root+'/clone-access.json',JSON.stringify(actors,null,2));
  await page.getByLabel('Empresa activa',{exact:true}).selectOption(fixture.company_id);
}
async function route(url,p=page) { await p.evaluate(url=>{history.pushState({},'','/contabilidad'+url);window.dispatchEvent(new PopStateEvent('popstate'));},url); }
async function work(p=page) { await p.getByRole('link',{name:'Preparar trabajo local',exact:true}).click();await p.getByRole('heading',{name:'Borradores del equipo',exact:true}).waitFor(); }
const section=(heading,p=page)=>p.locator('section.card').filter({has:p.getByRole('heading',{name:heading,exact:true})});
async function fill(concept,amount='75,20',p=page) {
  await p.getByLabel('Concepto *',{exact:true}).fill(concept);
  await p.getByLabel('Cuenta de línea 1',{exact:true}).selectOption(chart['1.1.02'].id);
  await p.getByLabel('Cuenta de línea 2',{exact:true}).selectOption(chart['3.1'].id);
  await p.getByLabel('Debe de línea 1',{exact:true}).fill(amount);
  await p.getByLabel('Haber de línea 2',{exact:true}).fill(amount);
}
async function save(p=page) { await p.getByRole('button',{name:'Guardar borrador',exact:true}).first().click();await p.waitForURL('**/comprobantes/local-*'); }
async function download(name) {
  const event=page.waitForEvent('download');await page.getByRole('button',{name:/^Descargar copia /}).click();
  const file=root+'/'+name;await (await event).saveAs(file);fs.chmodSync(file,0o600);return file;
}
async function upload(file) {
  await page.getByLabel('Archivo de respaldo cifrado',{exact:true}).setInputFiles(file);
  await page.getByLabel('Frase del respaldo',{exact:true}).fill(phrase);
  async function submit() {
    const prepared=page.waitForResponse(r=>r.url().endsWith('/offline/prepare')&&r.request().method()==='POST');
    await page.getByRole('button',{name:'Recuperar y conservar borradores',exact:true}).click();return prepared;
  }
  let prepared=await submit();
  if (prepared.status()===429) {
    const seconds=Number(prepared.headers()['retry-after']);assert(Number.isFinite(seconds)&&seconds<=60);
    await new Promise(resolve=>setTimeout(resolve,(seconds+0.1)*1000));prepared=await submit();
  }
  assert.equal(prepared.status(),200);await page.getByText(/Recuperación completada:/).waitFor();
}
async function choose(concept,reason) {
  const dialog=page.getByRole('dialog',{name:'Comparar alternativas locales',exact:true});await dialog.waitFor();
  await dialog.getByLabel(new RegExp(concept)).first().check();
  await dialog.getByLabel('Motivo de la elección local',{exact:true}).fill(reason);
  await dialog.getByRole('button',{name:'Guardar elección local',exact:true}).click();
  await dialog.waitFor({state:'hidden'});
}
async function prepareAgain(concept) {
  await section('Alternativas conservadas').getByRole('row').filter({hasText:concept}).getByRole('button',{name:'Preparar otra elección',exact:true}).first().click();
}
async function encryptedSnapshot(p=page) {
  return p.evaluate(()=>new Promise((resolve,reject)=>{
    const q=indexedDB.open('benahia-accounting-local-v1');q.onerror=()=>reject(q.error);
    q.onsuccess=()=>{const db=q.result,names=['drafts','selections'].filter(name=>db.objectStoreNames.contains(name)),tx=db.transaction(names,'readonly'),values={};
      for(const name of names){const read=tx.objectStore(name).getAll();read.onsuccess=()=>values[name]=read.result.map(row=>({id:row.id,updatedAt:row.updatedAt,iv:[...new Uint8Array(row.cipher.iv)],data:[...new Uint8Array(row.cipher.data)]})).sort((a,b)=>a.id.localeCompare(b.id));}
      tx.oncomplete=()=>{db.close();resolve(JSON.stringify(values));};tx.onerror=()=>reject(tx.error);
    };
  }));
}
(async()=>{
  browser=await chromium.launch({executablePath:'/opt/google/chrome/chrome',headless:true,args:['--no-sandbox','--host-resolver-rules=MAP contabilidad.localhost 127.0.0.1']});
  await observe(await browser.newContext({viewport:{width:1600,height:1050},acceptDownloads:true}));
  const probe=await context.request.get('http://127.0.0.1:9080/api/accounting/v1/health',{headers:{Host:'contabilidad.localhost:9080',Accept:'application/json'}});
  assert.equal(probe.headers()['x-accounting-isolated-test'],'1');await login();
  chart=Object.fromEntries((await request(base+'/catalogs/accounts')).data.map(row=>[row.code,row]));
  let writes=0;context.on('request',r=>{if(r.method()==='POST'&&/\/(?:entries|offline\/sync)$/.test(new URL(r.url()).pathname))writes++;});
  await page.getByRole('link',{name:'Preparar trabajo local',exact:true}).click();
  await page.getByLabel('Frase local de cifrado',{exact:true}).fill(phrase);
  await page.getByLabel('Repetir frase local',{exact:true}).fill(phrase);
  await page.getByRole('button',{name:'Preparar copia cifrada',exact:true}).click();
  await page.getByRole('heading',{name:'Copia desbloqueada',exact:true}).waitFor();
  await route('/comprobantes/nuevo');await fill(original);await context.setOffline(true);await save();
  const originalUrl=page.url();await work();const firstBackup=await download('local-choice-original-backup.json');
  await section('Borradores del equipo').getByRole('link',{name:original,exact:true}).click();
  await page.getByRole('link',{name:'Editar borrador',exact:true}).click();
  await fill(edited,'9007199254740993,12345678');await save();await work();
  await context.setOffline(false);await upload(firstBackup);
  await section('Borradores del equipo').getByRole('row').filter({hasText:original}).getByRole('button',{name:'Comparar alternativas locales',exact:true}).click();
  let localDialog=page.getByRole('dialog',{name:'Comparar alternativas locales',exact:true});
  await localDialog.waitFor();assert.equal(await localDialog.getByRole('button',{name:'Guardar elección local',exact:true}).isDisabled(),true);
  await localDialog.getByLabel(new RegExp(original)).check();
  assert((await localDialog.innerText()).includes('9007199254740993.12345678'));
  await page.screenshot({path:root+'/local-alternatives-desktop.png',fullPage:true});
  await context.setOffline(true);
  await localDialog.getByLabel('Motivo de la elección local',{exact:true}).fill('Seleccioné expresamente la versión del respaldo tras comparar los soportes.');
  const encryptedBefore=await encryptedSnapshot();
  await page.evaluate(()=>{
    window.__selectionOriginalAdd=IDBObjectStore.prototype.add;
    IDBObjectStore.prototype.add=function(...args){if(this.name==='selections')throw new DOMException('Sin espacio para registrar la elección.','QuotaExceededError');return window.__selectionOriginalAdd.apply(this,args);};
  });
  await localDialog.getByRole('button',{name:'Guardar elección local',exact:true}).click();
  await localDialog.getByRole('alert').filter({hasText:/espacio/}).waitFor();
  assert.equal(await encryptedSnapshot(),encryptedBefore);
  await page.evaluate(()=>{IDBObjectStore.prototype.add=window.__selectionOriginalAdd;delete window.__selectionOriginalAdd;});
  checks.push('si se agota el espacio después de preparar los antecedentes, la transacción conserva todas las versiones originales');
  await localDialog.getByRole('button',{name:'Guardar elección local',exact:true}).click();await localDialog.waitFor({state:'hidden'});
  await section('Alternativas conservadas').getByRole('row').filter({hasText:edited}).waitFor();
  assert.equal(await section('Borradores del equipo').getByRole('row').count(),2);
  assert.equal(await section('Alternativas conservadas').getByRole('row').count(),3);assert.equal(writes,0);
  checks.push('elegir sin conexión exige alternativa y motivo, conserva ambas versiones y no envía operaciones');
  await section('Alternativas conservadas').getByRole('row').filter({hasText:edited}).getByRole('link',{name:'Ver alternativa',exact:true}).click();
  await page.getByRole('heading',{name:'Alternativa conservada',exact:true}).waitFor();
  assert.equal(await page.getByRole('link',{name:'Editar borrador',exact:true}).count(),0);
  const archivedUrl=page.url();await route(new URL(archivedUrl).pathname.replace('/contabilidad','')+'/editar');
  await page.waitForURL(archivedUrl);await work();
  checks.push('los antecedentes son de consulta; abrir su enlace de edición conserva la alternativa sin modificarla');
  const selectedId=(await section('Borradores del equipo').getByRole('link',{name:original,exact:true}).getAttribute('href')).split('/local-')[1];
  const backup=await download('local-choices-selected-backup.json'), backupText=fs.readFileSync(backup,'utf8'), parsed=JSON.parse(backupText);
  assert.equal(parsed.version,5);assert.equal(parsed.selections.length,1);assert.equal(parsed.drafts.length,3);
  assert(!backupText.includes(original)&&!backupText.includes(edited)&&!backupText.includes(phrase));
  assert(parsed.drafts.some(row=>row.id===selectedId));
  const reordered=root+'/local-choices-result-first-backup.json';
  fs.writeFileSync(reordered,JSON.stringify({...parsed,drafts:[...parsed.drafts].sort((a,b)=>a.id===selectedId?-1:b.id===selectedId?1:a.id.localeCompare(b.id))}));
  fs.chmodSync(reordered,0o600);
  await context.close();
  await observe(await browser.newContext({viewport:{width:1600,height:1050},acceptDownloads:true}));await login();
  localDialog=page.getByRole('dialog',{name:'Comparar alternativas locales',exact:true});
  await page.getByRole('link',{name:'Preparar trabajo local',exact:true}).click();await upload(reordered);
  assert.equal(await section('Borradores del equipo').getByRole('row').count(),2);
  assert.equal(await section('Alternativas conservadas').getByRole('row').count(),3);
  assert.deepEqual(JSON.parse(await encryptedSnapshot()).drafts.map(row=>row.id).sort(),parsed.drafts.map(row=>row.id).sort());
  await upload(backup);assert.equal(await section('Alternativas conservadas').getByRole('row').count(),3);
  checks.push('el respaldo versión cuatro conserva las tres identidades al importar primero el resultado elegido y al repetirlo en otro orden');
  await prepareAgain(edited);await choose(edited,'Recuperé el antecedente y lo contrasté de nuevo antes de continuar esta versión.');
  const secondBackup=JSON.parse(fs.readFileSync(await download('local-choices-chain-backup.json'),'utf8'));
  assert.equal(secondBackup.selections.length,2);assert.equal(secondBackup.drafts.length,4);
  checks.push('reutilizar una alternativa conservada crea otro borrador y conserva la cadena de decisiones');
  let serverEntry, syncs=0;
  await page.route('**/offline/sync',async route=>{syncs++;const response=await route.fetch();assert.equal(response.status(),200);serverEntry=(await response.json()).data;await route.abort('failed');});
  await section('Borradores del equipo').getByRole('row').filter({hasText:edited}).getByRole('button',{name:'Sincronizar borrador',exact:true}).click();
  await page.getByRole('heading',{name:'Envíos conservados',exact:true}).waitFor();
  await prepareAgain(original);await localDialog.waitFor();
  assert.equal(await localDialog.getByRole('button',{name:'Guardar elección local',exact:true}).count(),0);
  await page.unroute('**/offline/sync');
  await localDialog.getByRole('button',{name:'Consultar confirmación',exact:true}).click();
  await localDialog.getByRole('button',{name:'Guardar elección local',exact:true}).waitFor();
  assert.equal(syncs,1);assert.equal(serverEntry.local_selection_receipts.length,2);
  await localDialog.getByRole('button',{name:'Volver al trabajo local',exact:true}).click();
  await section('Borradores del equipo').getByText('Sincronizado',{exact:true}).waitFor();
  assert.equal((await request(base+'/entries/'+serverEntry.id)).data.total_debit,'9007199254740993.12345678');
  checks.push('un envío incierto bloquea nuevas elecciones; su confirmación recupera una sola entidad y las auditorías anteriores');
  await prepareAgain(original);
  // Select the received active version to exercise explicit keep-server even when its base is current.
  await localDialog.getByLabel(new RegExp(edited+'.*revisión local 2')).check();
  await localDialog.getByLabel('Motivo de la elección local',{exact:true}).fill('Confirmo de nuevo la versión recibida tras revisar sus antecedentes locales.');
  await localDialog.getByRole('button',{name:'Guardar elección local',exact:true}).click();await localDialog.waitFor({state:'hidden'});
  await section('Borradores del equipo').getByRole('button',{name:'Comparar versiones',exact:true}).click();
  const comparison=page.getByRole('dialog',{name:'Comparar versiones',exact:true});await comparison.waitFor();
  await comparison.getByLabel('Conservar PRO9 y archivar mi alternativa',{exact:true}).check();
  await comparison.getByLabel('Motivo de la decisión',{exact:true}).fill('Conservo expresamente la versión vigente de PRO9 sin cambiar sus importes.');
  await comparison.getByRole('button',{name:'Guardar decisión',exact:true}).click();await comparison.waitFor({state:'hidden'});
  await section('Borradores del equipo').getByText('Sincronizado',{exact:true}).waitFor();
  assert.equal((await request(base+'/entries/'+serverEntry.id)).data.version,1);
  checks.push('conservar PRO9 resuelve una elección cuya versión de partida ya era actual sin crear otro cambio');
  await prepareAgain(original);await choose(original,'Reutilizo el antecedente del respaldo tras una nueva comprobación de soportes.');
  await section('Borradores del equipo').getByRole('button',{name:'Comparar versiones',exact:true}).click();await comparison.waitFor();
  await comparison.getByLabel('Guardar mi alternativa como nueva versión para revisión',{exact:true}).check();
  await comparison.getByLabel('Motivo de la decisión',{exact:true}).fill('La alternativa histórica es la que corresponde al soporte revisado por el contador.');
  await comparison.getByRole('button',{name:'Guardar decisión',exact:true}).click();await comparison.waitFor({state:'hidden'});
  await section('Borradores del equipo').getByText('Sincronizado',{exact:true}).waitFor();
  const applied=(await request(base+'/entries/'+serverEntry.id)).data;
  assert.equal(applied.total_debit,'75.20000000');assert.equal(applied.version,2);assert.equal(applied.status,'draft');
  checks.push('aplicar un antecedente exige comparar con PRO9 y devuelve una nueva versión para revisión con el mismo registro');

  await route('/comprobantes/nuevo');await fill('Concurrencia inicial '+stamp,'33,33');await context.setOffline(true);await save();
  const concurrentUrl=page.url();await page.getByRole('link',{name:'Editar borrador',exact:true}).click();
  const first=page, second=await context.newPage();second.on('pageerror',e=>errors.push(e.message));
  await first.waitForFunction(()=>!!navigator.serviceWorker.controller);
  await second.goto(origin+'/contabilidad/');await second.getByRole('link',{name:'Abrir copia local',exact:true}).click();
  await second.getByLabel('Frase local',{exact:true}).fill(phrase);await second.getByRole('button',{name:'Desbloquear copia',exact:true}).click();
  await second.getByRole('heading',{name:'Copia desbloqueada',exact:true}).waitFor();
  await route(new URL(concurrentUrl).pathname.replace('/contabilidad','')+'/editar',second);
  await second.getByLabel('Concepto *',{exact:true}).waitFor();
  await first.getByLabel('Concepto *',{exact:true}).fill('Edición primera pestaña '+stamp);await save(first);
  await second.getByLabel('Concepto *',{exact:true}).fill('Edición segunda pestaña '+stamp);await save(second);page=second;
  await work();const list=section('Borradores del equipo');
  await list.getByRole('row').filter({hasText:'Edición primera pestaña '+stamp}).waitFor();
  await list.getByRole('row').filter({hasText:'Edición segunda pestaña '+stamp}).waitFor();
  await list.getByRole('row').filter({hasText:'Edición segunda pestaña '+stamp}).getByRole('button',{name:'Comparar alternativas locales',exact:true}).click();
  await choose('Edición segunda pestaña '+stamp,'Conservo la segunda edición tras comparar lo preparado en ambas pestañas.');
  await list.getByRole('row').filter({hasText:'Edición segunda pestaña '+stamp}).waitFor();
  checks.push('dos pestañas que parten de la misma revisión conservan ambas ediciones y exigen una elección antes de enviar');
  page=first;await work();await context.setOffline(false);
  await work();await section('Borradores del equipo').getByRole('row').filter({hasText:'Edición segunda pestaña '+stamp}).getByRole('button',{name:'Sincronizar borrador',exact:true}).click();
  await section('Borradores del equipo').getByRole('row').filter({hasText:'Edición segunda pestaña '+stamp}).getByText('Sincronizado',{exact:true}).waitFor();
  const found=(await request(base+'/entries?q='+encodeURIComponent('Edición segunda pestaña '+stamp))).data;
  assert.equal(found.length,1);assert.equal(found[0].total_debit,'33.33000000');
  checks.push('la elección entre pestañas se recibe como un único borrador y conserva sus importes exactos');
  await context.setOffline(true);await route('/comprobantes/nuevo',first);
  await first.getByLabel('Concepto *',{exact:true}).fill('Captura sin asiento '+stamp);
  await first.locator('.recovery-status').filter({hasText:'Captura cifrada conservada en este equipo'}).waitFor();
  await route('/comprobantes/nuevo',second);
  await second.getByRole('dialog',{name:'Recuperar una captura',exact:true}).getByRole('row').filter({hasText:'Captura sin asiento '+stamp}).getByRole('button',{name:'Continuar captura',exact:true}).click();
  await fill('Primera captura terminada '+stamp,'55,55',first);await save(first);
  await fill('Segunda captura terminada '+stamp,'66,66',second);await save(second);page=second;await work();
  await section('Borradores del equipo').getByRole('row').filter({hasText:'Primera captura terminada '+stamp}).getByRole('button',{name:'Comparar alternativas locales',exact:true}).waitFor();
  await section('Borradores del equipo').getByRole('row').filter({hasText:'Segunda captura terminada '+stamp}).getByRole('button',{name:'Comparar alternativas locales',exact:true}).click();
  await choose('Segunda captura terminada '+stamp,'Conservo esta captura terminada tras revisar su alternativa y sus soportes.');
  page=first;await work();await context.setOffline(false);await work();
  await section('Borradores del equipo').getByRole('row').filter({hasText:'Segunda captura terminada '+stamp}).getByRole('button',{name:'Sincronizar borrador',exact:true}).click();
  await section('Borradores del equipo').getByRole('row').filter({hasText:'Segunda captura terminada '+stamp}).getByText('Sincronizado',{exact:true}).waitFor();
  const completed=(await request(base+'/entries?q='+encodeURIComponent('captura terminada '+stamp))).data;
  assert.equal(completed.length,1);assert.equal(completed[0].total_debit,'66.66000000');
  checks.push('terminar dos capturas incompletas de la misma identidad conserva ambas alternativas y recibe solo la elegida');
  await page.screenshot({path:root+'/local-alternatives-history-desktop.png',fullPage:true});
  await page.setViewportSize({width:390,height:844});await prepareAgain('Edición primera pestaña '+stamp);await localDialog.waitFor();
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
  await page.screenshot({path:root+'/local-alternatives-mobile.png',fullPage:true});
  await localDialog.getByRole('button',{name:'Volver al trabajo local',exact:true}).click();
  checks.push('la comparación y el historial se adaptan a móvil sin desbordar la página');
  const legacyContext=await browser.newContext({viewport:{width:1500,height:1050}}),legacyPage=await legacyContext.newPage();
  legacyPage.on('pageerror',e=>errors.push(e.message));
  await legacyPage.goto(origin+'/api/accounting/v1/health');
  const oldBackup=JSON.parse(fs.readFileSync(firstBackup,'utf8'));
  await legacyPage.evaluate(backup=>new Promise((resolve,reject)=>{
    const buffer=text=>Uint8Array.from(atob(text),char=>char.charCodeAt(0)).buffer;
    const decode=row=>({...row,cipher:{iv:buffer(row.cipher.iv),data:buffer(row.cipher.data)}});
    const open=indexedDB.open('benahia-accounting-local-v1',30);open.onerror=()=>reject(open.error);
    open.onupgradeneeded=()=>{const db=open.result;for(const name of ['workspaces','drafts','recoveries','outbox']){const store=db.createObjectStore(name,{keyPath:'id'});for(const index of name==='workspaces'?['companyId','userId']:['workspaceId','updatedAt'])store.createIndex(index,index);}};
    open.onsuccess=()=>{const db=open.result,tx=db.transaction(['workspaces','drafts','recoveries','outbox'],'readwrite');
      tx.objectStore('workspaces').put({...decode(backup.workspace),salt:buffer(backup.workspace.salt)});
      for(const row of backup.drafts)tx.objectStore('drafts').put(decode(row));
      for(const row of backup.recoveries)tx.objectStore('recoveries').put(decode(row));
      for(const row of backup.outbox)tx.objectStore('outbox').put(decode(row));
      tx.oncomplete=()=>{db.close();resolve();};tx.onerror=()=>reject(tx.error);
    };
  }),oldBackup);
  await legacyPage.goto(origin+'/contabilidad/');await legacyPage.getByRole('link',{name:'Abrir copia local',exact:true}).waitFor();
  await legacyPage.waitForFunction(()=>!!navigator.serviceWorker.controller);await legacyContext.setOffline(true);
  await legacyPage.getByRole('link',{name:'Abrir copia local',exact:true}).click();await legacyPage.getByLabel('Frase local',{exact:true}).fill(phrase);
  await legacyPage.getByRole('button',{name:'Desbloquear copia',exact:true}).click();await legacyPage.getByRole('heading',{name:'Copia desbloqueada',exact:true}).waitFor();
  await route(new URL(originalUrl).pathname.replace('/contabilidad',''),legacyPage);
  await legacyPage.getByText(original,{exact:true}).first().waitFor();
  const version=await legacyPage.evaluate(()=>new Promise((resolve,reject)=>{const q=indexedDB.open('benahia-accounting-local-v1');q.onerror=()=>reject(q.error);q.onsuccess=()=>{const version=q.result.version;q.result.close();resolve(version);};}));
  assert.equal(version,40);await legacyContext.close();
  checks.push('una base nativa versión tres se actualiza a cuatro y conserva su copia y borrador cifrados, abiertos sin servidor');
  assert.deepEqual(errors,[]);const result={passed:checks.length,checks,pageErrors:errors};
  fs.writeFileSync(root+'/local-selections-browser-tests.json',JSON.stringify(result,null,2));console.log(JSON.stringify(result,null,2));await browser.close();
})().catch(async error=>{
  if(page&&!page.isClosed()){await page.screenshot({path:root+'/local-selections-browser-failure.png',fullPage:true});console.error(JSON.stringify({url:page.url(),alerts:await page.getByRole('alert').allTextContents(),pageErrors:errors}));}
  console.error(error.stack);if(browser)await browser.close();process.exit(1);
});
