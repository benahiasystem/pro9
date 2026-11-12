const { chromium } = require(process.env.ACCOUNTING_PLAYWRIGHT_MODULE || '@playwright/test');
const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const { randomUUID } = require('node:crypto');
const root = path.resolve(process.env.ACCOUNTING_RUNTIME_DIR || '.accounting-isolated');
assert.equal(path.basename(root), '.accounting-isolated');
if (!process.env.ACCOUNTING_FIXTURE_LOCKED) {
  const result = require('node:child_process').spawnSync('flock', [root + '/fixture.lock', process.execPath, __filename], {
    stdio: 'inherit', env: { ...process.env, ACCOUNTING_FIXTURE_LOCKED: '1' },
  });
  process.exit(result.status ?? 1);
}
const actors = JSON.parse(fs.readFileSync(root + '/clone-access.json'));
const actor = actors.autor, reviewer = actors.revisor;
const fixture = JSON.parse(fs.readFileSync(root + '/auxiliary-fixture.json')).ui;
const origin = 'http://contabilidad.localhost:9080', base = '/api/accounting/v1/companies/' + fixture.company_id;
const phrase = 'Conflictos cifrados de prueba 2026', stamp = Date.now();
const checks = [], errors = [];
let browser, context, page, chart, currentEntry;
function headers(user = actor) {
  if (!user) return { Host: 'contabilidad.localhost:9080', Accept: 'application/json' };
  return { Host: 'contabilidad.localhost:9080', Accept: 'application/json', Authorization: 'Bearer ' + user.token, 'X-Accounting-Verification': user.verification };
}
async function request(url, data, user = reviewer, method = 'GET', extra = {}, status = 200) {
  let r = await context.request.fetch(origin + url, { method, data, headers: { ...headers(user), ...extra } });
  if (r.status() === 429 && method === 'GET') {
    const seconds = Number(r.headers()['retry-after']);
    assert(Number.isFinite(seconds) && seconds <= 60);
    await new Promise(resolve => setTimeout(resolve, (seconds + 0.1) * 1000));
    r = await context.request.fetch(origin + url, { method, headers: { ...headers(user), ...extra } });
  }
  assert.equal(r.headers()['x-accounting-isolated-test'], '1');
  const body = await r.json();
  assert.equal(r.status(), status, 'Respuesta aislada inesperada: ' + body.code);
  return body;
}
async function login() {
  await page.goto(origin + '/contabilidad/');
  await page.getByLabel('Correo electrónico de PRO9').fill(actor.email);
  await page.getByLabel('Contraseña', { exact: true }).fill(actor.password);
  const response = page.waitForResponse(r => r.url().endsWith('/api/login') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Entrar', exact: true }).click();
  let authResponse = await response;
  if (authResponse.status() === 429) {
    const delay = Number(authResponse.headers()['retry-after']); assert(delay > 0 && delay <= 60);
    console.log('Se respeta Retry-After antes de volver a iniciar sesión en el clon.');
    await page.waitForTimeout(delay * 1000);
    const retry = page.waitForResponse(r => r.url().endsWith('/api/login') && r.request().method() === 'POST');
    await page.getByRole('button', { name: 'Entrar', exact: true }).click(); authResponse = await retry;
  }
  assert.equal(authResponse.status(), 200); actor.token = (await authResponse.json()).token;
  await page.getByLabel('Código de verificación', { exact: true }).waitFor();
  const code = actor.recovery_codes.shift(); assert(code);
  fs.writeFileSync(root + '/clone-access.json', JSON.stringify(actors, null, 2));
  await page.getByLabel('Código de verificación', { exact: true }).fill(code);
  const mfa = page.waitForResponse(r => r.url().endsWith('/auth/mfa/verify'));
  await page.getByRole('button', { name: 'Verificar y continuar' }).click();
  let verificationResponse = await mfa;
  if (verificationResponse.status() === 429) {
    const delay = Number(verificationResponse.headers()['retry-after']); assert(delay > 0 && delay <= 60);
    console.log('Se respeta Retry-After antes de repetir la verificación del clon.');
    await page.waitForTimeout(delay * 1000);
    const retry = page.waitForResponse(r => r.url().endsWith('/auth/mfa/verify') && r.request().method() === 'POST');
    await page.getByRole('button', { name: 'Verificar y continuar' }).click(); verificationResponse = await retry;
  }
  assert.equal(verificationResponse.status(), 200); actor.verification = (await verificationResponse.json()).verification_token;
  fs.writeFileSync(root + '/clone-access.json', JSON.stringify(actors, null, 2));
  await page.getByLabel('Empresa activa', { exact: true }).selectOption(fixture.company_id);
}
async function route(url) {
  await page.evaluate(url => { history.pushState({}, '', '/contabilidad' + url); window.dispatchEvent(new PopStateEvent('popstate')); }, url);
}
function input(concept, amount = '9007199254740993.12345678') {
  return { accounting_date: fixture.date, concept, local_id: randomUUID(), entry_type: 'regular', lines: [
    { account_id: chart['1.1.02'].id, debit: amount, credit: '0' },
    { account_id: chart['3.1'].id, debit: '0', credit: amount },
  ] };
}
async function create(concept, amount) {
  const data = input(concept, amount);
  return (await request(base + '/entries', data, actor, 'POST', { 'Idempotency-Key': randomUUID() })).data;
}
async function serverEdit(entry, concept) {
  const data = input(concept); data.lines = entry.lines;
  return (await request(base + '/entries/' + entry.id, data, reviewer, 'PATCH', { 'If-Match': String(entry.version) })).data;
}
async function selectLocalAndReason(dialog) {
  await dialog.getByLabel('Guardar mi alternativa como nueva versión para revisión', { exact: true }).check();
  await dialog.getByLabel('Motivo de la decisión', { exact: true }).fill('Revisé ambas alternativas y conservo expresamente mi edición local.');
}
async function clickSave() {
  await page.getByRole('button', { name: 'Guardar borrador', exact: true }).first().click();
}
async function fillJournal(concept, amount = '75,20') {
  await page.getByLabel('Concepto *', { exact: true }).fill(concept);
  await page.getByLabel('Cuenta de línea 1', { exact: true }).selectOption(chart['1.1.02'].id);
  await page.getByLabel('Cuenta de línea 2', { exact: true }).selectOption(chart['3.1'].id);
  await page.getByLabel('Debe de línea 1', { exact: true }).fill(amount);
  await page.getByLabel('Haber de línea 2', { exact: true }).fill(amount);
}
async function localWork() {
  await page.getByRole('link', { name: 'Preparar trabajo local', exact: true }).click();
}
(async () => {
  browser = await chromium.launch({ executablePath: '/opt/google/chrome/chrome', headless: true,
    args: ['--no-sandbox', '--host-resolver-rules=MAP contabilidad.localhost 127.0.0.1'] });
  context = await browser.newContext({ viewport: { width: 1600, height: 1050 }, acceptDownloads: true });
  page = await context.newPage(); page.on('pageerror', error => errors.push(error.message));
  await request('/api/accounting/v1/health', undefined, null, 'GET', {}, 401);
  await login();
  chart = Object.fromEntries((await request(base + '/catalogs/accounts')).data.map(row => [row.code, row]));
  currentEntry = await create('Base de comparación ' + stamp);
  // Use the app router without reloading credentials for deterministic editor targets.
  await route('/comprobantes/' + currentEntry.id + '/editar');
  await page.getByLabel('Concepto *', { exact: true }).fill('Mi alternativa exacta ' + stamp);
  currentEntry = await serverEdit(currentEntry, 'Cambio concurrente de PRO9 ' + stamp);
  await clickSave();
  const dialog = page.getByRole('dialog', { name: 'Comparar versiones', exact: true });
  await dialog.waitFor();
  await dialog.getByRole('cell', { name: 'Mi alternativa exacta ' + stamp, exact: true }).waitFor();
  await dialog.getByRole('cell', { name: currentEntry.concept, exact: true }).waitFor();
  assert((await dialog.innerText()).includes('9007199254740993.12345678'));
  assert.equal(await dialog.getByRole('button', { name: 'Guardar decisión', exact: true }).isDisabled(), true);
  await page.screenshot({ path: root + '/conflict-comparison-desktop.png', fullPage: true });
  checks.push('comparación explícita muestra ambas alternativas y todos los decimales; exige decisión y motivo');
  await selectLocalAndReason(dialog);
  currentEntry = await serverEdit(currentEntry, 'Otro cambio durante la comparación ' + stamp);
  await dialog.getByRole('button', { name: 'Guardar decisión', exact: true }).click();
  await dialog.getByRole('heading', { name: 'Envío pendiente de confirmar', exact: true }).waitFor();
  await dialog.getByLabel('Motivo para cancelar el envío pendiente').fill('Cancelo este intento rechazado para comparar la última versión de PRO9.');
  await dialog.getByRole('button', { name: 'Cancelar envío pendiente', exact: true }).click();
  await dialog.getByRole('cell', { name: currentEntry.concept, exact: true }).waitFor();
  await selectLocalAndReason(dialog);
  await dialog.getByRole('button', { name: 'Guardar decisión', exact: true }).click();
  await page.waitForURL('**/comprobantes/' + currentEntry.id);
  currentEntry = (await request(base + '/entries/' + currentEntry.id)).data;
  assert.equal(currentEntry.concept, 'Mi alternativa exacta ' + stamp);
  assert.equal(currentEntry.status, 'draft'); assert.equal(currentEntry.approved_by, null);
  checks.push('un segundo cambio concurrente no se sobrescribe; tras cancelar y comparar se guarda una nueva versión para revisión');

  await page.getByRole('link', { name: 'Editar borrador', exact: true }).click();
  await page.getByLabel('Concepto *', { exact: true }).fill('Alternativa archivada ' + stamp);
  currentEntry = await serverEdit(currentEntry, 'Versión conservada en PRO9 ' + stamp);
  await clickSave(); await dialog.waitFor();
  await dialog.getByLabel('Conservar PRO9 y archivar mi alternativa', { exact: true }).check();
  await dialog.getByLabel('Motivo de la decisión').fill('Revisión expresa: la versión de PRO9 es la que debe conservarse.');
  await dialog.getByRole('button', { name: 'Guardar decisión', exact: true }).click();
  await page.waitForURL('**/comprobantes/' + currentEntry.id);
  assert.equal((await request(base + '/entries/' + currentEntry.id)).data.version, currentEntry.version);
  checks.push('conservar PRO9 archiva la alternativa sin incrementar ni modificar el comprobante');

  const docPayload = {kind: 'sale_invoice', number: 'CONFLICTO-UI-' + stamp, issue_date: fixture.date, fiscal_date: fixture.date,
    currency: 'VES', exchange_rate: '1', exchange_source: 'Moneda del libro', exchange_date: fixture.date,
    amounts: {base: '100.123456789012', vat: '0', other_taxes: '0', total: '100.123456789012'}, tax_components: []};
  let doc = (await request(base + '/documents', docPayload, actor, 'POST', {'Idempotency-Key': randomUUID()})).data;
  await route('/documentos/' + doc.id + '/editar');
  await page.getByLabel('Número del documento', {exact: true}).fill('LOCAL-UI-' + stamp);
  doc = (await request(base + '/documents/' + doc.id, {...docPayload, number: 'SERVIDOR-UI-' + stamp}, reviewer, 'PATCH', {'If-Match': String(doc.version)})).data;
  await page.getByRole('button', {name: 'Guardar borrador', exact: true}).click();
  await dialog.waitFor(); assert((await dialog.innerText()).includes('100.123456789012'));
  await selectLocalAndReason(dialog);
  await dialog.getByRole('button', {name: 'Guardar decisión', exact: true}).click();
  await page.waitForURL('**/documentos/' + doc.id);
  doc = (await request(base + '/documents/' + doc.id)).data;
  assert.equal(doc.number, 'LOCAL-UI-' + stamp); assert.equal(doc.amounts.total, '100.123456789012');
  checks.push('el documento manual resuelve su comparación conservando doce decimales y sus versiones');

  await localWork();
  await page.getByLabel('Frase local de cifrado', {exact: true}).fill(phrase);
  await page.getByLabel('Repetir frase local', {exact: true}).fill(phrase);
  await page.getByRole('button', {name: 'Preparar copia cifrada', exact: true}).click();
  await page.getByRole('heading', {name: 'Copia desbloqueada', exact: true}).waitFor();
  await route('/comprobantes/nuevo');
  await fillJournal('Creación con respuesta perdida ' + stamp);
  let creations = 0, receivedId;
  await page.route('**/api/accounting/v1/companies/*/entries', async route => {
    if (route.request().method() !== 'POST') return route.continue();
    creations++; const response = await route.fetch();
    receivedId = (await response.json()).data.id;
    await route.abort('failed');
  });
  await clickSave();
  const pending = page.getByRole('region', {name: 'Envío pendiente de confirmar', exact: true});
  await pending.waitFor();
  await page.getByLabel('Concepto *', {exact: true}).fill('Cambios posteriores a la creación ' + stamp);
  await clickSave();
  assert.equal(creations, 1);
  assert.equal(await page.getByLabel('Concepto *', {exact: true}).inputValue(), 'Cambios posteriores a la creación ' + stamp);
  await page.unroute('**/api/accounting/v1/companies/*/entries');
  await clickSave();
  await page.waitForURL('**/comprobantes/' + receivedId);
  assert.equal((await request(base + '/entries/' + receivedId)).data.concept, 'Cambios posteriores a la creación ' + stamp);
  checks.push('una creación confirmada con respuesta perdida no se duplica y los cambios posteriores requieren su propio guardado');

  await route('/comprobantes/nuevo');
  await fillJournal('Sincronización original ' + stamp);
  await context.setOffline(true); await clickSave();
  await page.waitForURL('**/comprobantes/local-*'); const localUrl = page.url();
  await context.setOffline(false); await localWork();
  let synchronizations = 0, synchronizedId;
  await page.route('**/offline/sync', async route => {
    synchronizations++; const response = await route.fetch();
    synchronizedId = (await response.json()).data.id;
    await route.abort('failed');
  });
  await page.getByRole('row').filter({hasText: 'Sincronización original ' + stamp}).getByRole('button', {name: 'Sincronizar borrador', exact: true}).click();
  await pending.waitFor();
  const download = page.waitForEvent('download');
  await page.getByRole('button', {name: /^Descargar copia /}).click();
  const backupPath = root + '/conflicts-encrypted-backup.json'; await (await download).saveAs(backupPath);
  fs.chmodSync(backupPath, 0o600);
  const backupText = fs.readFileSync(backupPath, 'utf8'), backup = JSON.parse(backupText);
  assert.equal(backup.version, 5); assert(backup.outbox.length > 0); assert(Array.isArray(backup.selections));
  assert(!backupText.includes('Sincronización original ' + stamp)); assert(!backupText.includes(phrase));
  checks.push('el respaldo incluye el envío original cifrado sin revelar sus datos ni la frase');
  await page.getByRole('link', {name: 'Sincronización original ' + stamp, exact: true}).click();
  await page.getByRole('link', {name: 'Editar borrador', exact: true}).click();
  await page.getByLabel('Concepto *', {exact: true}).fill('Edición posterior a sincronizar ' + stamp);
  await clickSave(); await page.waitForURL(localUrl); await localWork();
  await page.unroute('**/offline/sync');
  await pending.getByRole('button', {name: 'Consultar confirmación', exact: true}).click();
  await pending.waitFor({state: 'hidden'});
  await page.getByRole('row').filter({hasText: 'Edición posterior a sincronizar ' + stamp}).getByRole('button', {name: 'Sincronizar borrador', exact: true}).waitFor();
  assert.equal(synchronizations, 1);
  await page.getByRole('row').filter({hasText: 'Edición posterior a sincronizar ' + stamp}).getByRole('button', {name: 'Sincronizar borrador', exact: true}).click();
  await page.getByRole('row').filter({hasText: 'Edición posterior a sincronizar ' + stamp}).getByText('Sincronizado', {exact: true}).waitFor();
  assert.equal((await request(base + '/entries/' + synchronizedId)).data.concept, 'Edición posterior a sincronizar ' + stamp);
  checks.push('recuperar una sincronización no sobrescribe la edición local posterior ni crea otro comprobante');
  await page.screenshot({path: root + '/conflicts-recovered-workspace.png', fullPage: true});
  await context.close();
  context = await browser.newContext({viewport: {width: 1600, height: 1050}, acceptDownloads: true});
  page = await context.newPage(); page.on('pageerror', error => errors.push(error.message));
  await login(); await localWork();
  let restoredWrites = 0;
  context.on('request', req => {
    if (req.method() === 'POST' && /\/(?:entries|offline\/sync)$/.test(new URL(req.url()).pathname)) restoredWrites++;
  });
  await page.getByLabel('Archivo de respaldo cifrado', {exact: true}).setInputFiles(backupPath);
  await page.getByLabel('Frase del respaldo', {exact: true}).fill(phrase);
  async function recover() {
    const prepared = page.waitForResponse(r => r.url().endsWith('/offline/prepare') && r.request().method() === 'POST');
    await page.getByRole('button', {name: 'Recuperar y conservar borradores', exact: true}).click();
    return prepared;
  }
  let prepared = await recover();
  if (prepared.status() === 429) {
    const seconds = Number(prepared.headers()['retry-after']);
    assert(Number.isFinite(seconds) && seconds <= 60);
    await new Promise(resolve => setTimeout(resolve, (seconds + 0.1) * 1000));
    prepared = await recover();
  }
  assert.equal(prepared.status(), 200);
  await page.getByText(/Recuperación completada:/).waitFor();
  const restoredPanels = page.getByRole('region', {name: 'Envío pendiente de confirmar', exact: true});
  assert.equal(await restoredPanels.count(), backup.outbox.length);
  for (let n = 0; n < backup.outbox.length; n++) {
    const originalCount = await restoredPanels.count();
    await restoredPanels.first().getByRole('button', {name: 'Consultar confirmación', exact: true}).click();
    await page.waitForFunction(count => document.querySelectorAll('.request-recovery').length < count, originalCount);
  }
  assert.equal(restoredWrites, 0);
  checks.push('otro navegador recupera las confirmaciones del respaldo con una nueva autorización sin repetir operaciones');
  await page.waitForFunction(() => !!navigator.serviceWorker.controller);
  await context.setOffline(true);
  await route('/comprobantes');
  await page.getByLabel('Buscar', {exact: true}).fill('Edición posterior a sincronizar ' + stamp);
  await page.getByText('Edición posterior a sincronizar ' + stamp, {exact: true}).first().waitFor();
  await route(new URL(localUrl).pathname.replace('/contabilidad', ''));
  await page.getByText('Edición posterior a sincronizar ' + stamp, {exact: true}).first().waitFor();
  checks.push('la consulta sin conexión y el enlace local original muestran la versión nueva de la copia renovada');
  assert.deepEqual(errors, []);
  const result = {passed: checks.length, checks, pageErrors: errors};
  fs.writeFileSync(root + '/conflicts-browser-tests.json', JSON.stringify(result, null, 2));
  console.log(JSON.stringify(result, null, 2)); await browser.close();
})().catch(async error => {
  if (page && !page.isClosed()) {
    await page.screenshot({path: root + '/conflicts-browser-failure.png', fullPage: true});
    console.error(JSON.stringify({url: page.url(), alerts: await page.getByRole('alert').allTextContents()}));
  }
  console.error(error.stack); if (browser) await browser.close(); process.exit(1);
});
