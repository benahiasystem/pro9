const { chromium } = require(process.env.ACCOUNTING_PLAYWRIGHT_MODULE || '@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(process.env.ACCOUNTING_RUNTIME_DIR || '.accounting-isolated');
assert.equal(path.basename(root), '.accounting-isolated');
if (!process.env.ACCOUNTING_FIXTURE_LOCKED) {
  const locked = require('node:child_process').spawnSync('flock', [root + '/fixture.lock', process.execPath, __filename], {
    stdio: 'inherit', env: { ...process.env, ACCOUNTING_FIXTURE_LOCKED: '1' },
  });
  process.exit(locked.status ?? 1);
}
const actors = JSON.parse(fs.readFileSync(root + '/clone-access.json'));
const data = JSON.parse(fs.readFileSync(root + '/auxiliary-fixture.json')).ui;
const actor = actors.autor;
const origin = 'http://contabilidad.localhost:9080';
const phrase = 'Copia ficticia del auxiliar 2026';
let browser, page;
async function login() {
  await page.goto(origin + '/contabilidad/');
  await page.getByLabel('Correo electrónico de SAB').fill(actor.email);
  await page.getByLabel('Contraseña', { exact: true }).fill(actor.password);
  await page.getByRole('button', { name: 'Entrar', exact: true }).click();
  await page.getByLabel('Código de verificación', { exact: true }).waitFor();
  const code = actor.recovery_codes.shift();
  assert(code);
  fs.writeFileSync(root + '/clone-access.json', JSON.stringify(actors, null, 2));
  await page.getByLabel('Código de verificación', { exact: true }).fill(code);
  await page.getByRole('button', { name: 'Verificar y continuar' }).click();
  await page.getByLabel('Empresa activa', { exact: true }).waitFor();
  await page.getByLabel('Empresa activa', { exact: true }).selectOption(data.company_id);
}
async function assertAuxiliary() {
  const row = page.getByRole('row').filter({ hasText: 'FV-HISTORICA-1' });
  await row.getByText('60,00', { exact: true }).waitFor();
  await row.getByText('2.400,00', { exact: true }).waitFor();
  await page.getByRole('status').filter({ hasText: 'Auxiliar de la copia preparada el' }).waitFor();
  assert.equal(await page.getByRole('link', { name: 'Preparar cobro o pago', exact: true }).count(), 0);
  assert.equal(await page.getByRole('link', { name: 'Saldos iniciales', exact: true }).count(), 0);
  assert.equal(await row.getByRole('link', { name: 'Cobrar', exact: true }).count(), 0);
  await row.getByRole('button', { name: 'FV-HISTORICA-1', exact: true }).click();
  await page.getByRole('dialog').getByRole('cell', { name: '40,00 USD', exact: true }).waitFor();
  await page.getByRole('dialog').getByText('El documento de origen está fuera del intervalo preparado. Consúltalo al conectar con SAB.', { exact: true }).waitFor();
  assert.equal(await page.getByRole('dialog').getByRole('link', { name: 'Abrir documento de origen' }).count(), 0);
  await page.getByRole('button', { name: 'Cerrar', exact: true }).click();
}
(async () => {
  browser = await chromium.launch({ executablePath: '/opt/google/chrome/chrome', headless: true,
    args: ['--no-sandbox', '--host-resolver-rules=MAP contabilidad.localhost 127.0.0.1'] });
  const context = await browser.newContext({ viewport: { width: 1600, height: 1050 } });
  const probe = await context.request.get('http://127.0.0.1:9080/api/accounting/v1/health', {
    headers: { Host: 'contabilidad.localhost:9080', Accept: 'application/json' },
  });
  assert.equal(probe.headers()['x-accounting-isolated-test'], '1');
  page = await context.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  await login();
  await page.getByRole('link', { name: 'Preparar trabajo local', exact: true }).click();
  await page.getByLabel('Consulta desde', { exact: true }).fill(data.date);
  await page.getByLabel('Consulta hasta', { exact: true }).fill(data.date);
  await page.getByLabel('Frase local de cifrado', { exact: true }).fill(phrase);
  await page.getByLabel('Repetir frase local', { exact: true }).fill(phrase);
  const prepared = page.waitForResponse(r => r.url().endsWith('/offline/prepare') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Preparar copia cifrada', exact: true }).click();
  const pkg = (await (await prepared).json()).data;
  assert.equal(pkg.open_items[0].remaining_original, '60.000000000000');
  assert.equal(pkg.open_items[0].remaining_book, '2400.00000000');
  assert.equal(pkg.scope.open_items_complete, true);
  assert.equal(pkg.documents.length, 0);
  await page.getByRole('heading', { name: 'Copia desbloqueada', exact: true }).waitFor();
  await page.waitForFunction(() => !!navigator.serviceWorker.controller);
  const checks = ['copia completa del auxiliar con deuda histórica fuera del intervalo documental'];
  const auxiliaryPath = '**/api/accounting/v1/companies/' + data.company_id + '/open-items?*';
  await context.route(auxiliaryPath, route => route.abort('failed'));
  await page.getByRole('link', { name: 'Auxiliares', exact: true }).click();
  await assertAuxiliary();
  assert.equal(await page.evaluate(() => navigator.onLine), true);
  checks.push('SAB sin respuesta identifica de inmediato la lectura local aunque el navegador tenga red');
  await context.unroute(auxiliaryPath);
  await context.setOffline(true);
  await page.getByRole('button', { name: 'Consultar', exact: true }).click();
  await assertAuxiliary();
  checks.push('consulta sin conexión conserva USD 60, VES 2400, aplicación e intervalo explícitos');
  const encrypted = await page.evaluate(() => new Promise((resolve, reject) => {
    const request = indexedDB.open('benahia-accounting-local-v1');
    request.onerror = () => reject(request.error);
    request.onsuccess = () => {
      const db = request.result, tx = db.transaction('workspaces', 'readonly');
      const rows = tx.objectStore('workspaces').getAll();
      rows.onsuccess = () => resolve(JSON.stringify(rows.result));
      tx.oncomplete = () => db.close();
    };
  }));
  assert(!encrypted.includes('FV-HISTORICA-1'));
  assert(!encrypted.includes(phrase));
  checks.push('saldo y referencia permanecen cifrados en IndexedDB');
  await page.reload();
  await page.getByRole('link', { name: 'Abrir copia local', exact: true }).click();
  await page.getByLabel('Frase local', { exact: true }).fill(phrase);
  await page.getByRole('button', { name: 'Desbloquear copia', exact: true }).click();
  await page.getByRole('heading', { name: 'Copia desbloqueada', exact: true }).waitFor();
  await page.getByRole('link', { name: 'Abrir comprobantes', exact: true }).click();
  await page.getByRole('link', { name: 'Auxiliares', exact: true }).click();
  await assertAuxiliary();
  checks.push('recarga y desbloqueo sin servidor recuperan el mismo auxiliar');
  const closeNotice = page.getByRole('button', { name: 'Cerrar aviso', exact: true });
  if (await closeNotice.isVisible()) await closeNotice.click();
  await page.screenshot({ path: root + '/auxiliary-offline-desktop.png', fullPage: true });
  await page.setViewportSize({ width: 390, height: 844 });
  await page.waitForFunction(() => document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), false);
  await page.screenshot({ path: root + '/auxiliary-offline-mobile.png', fullPage: true });
  assert.deepEqual(errors, []);
  checks.push('consulta móvil sin desbordamiento ni errores de ejecución');
  const result = { passed: checks.length, checks, pageErrors: errors };
  fs.writeFileSync(root + '/auxiliary-offline-browser-tests.json', JSON.stringify(result, null, 2));
  console.log(JSON.stringify(result, null, 2));
  await browser.close();
})().catch(async e => {
  if (page && !page.isClosed()) {
    await page.screenshot({ path: root + '/auxiliary-offline-failure.png', fullPage: true });
    console.error(JSON.stringify({ url: page.url(), alerts: await page.getByRole('alert').allTextContents() }));
  }
  console.error(e.stack);
  if (browser) await browser.close();
  process.exit(1);
});
