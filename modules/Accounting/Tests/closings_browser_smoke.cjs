const { chromium } = require(process.env.ACCOUNTING_PLAYWRIGHT_MODULE || '@playwright/test');
const fs = require('node:fs'), assert = require('node:assert/strict'), path = require('node:path');
const root = path.resolve(process.env.ACCOUNTING_RUNTIME_DIR || '.accounting-isolated');
assert.equal(path.basename(root), '.accounting-isolated');
if (!process.env.ACCOUNTING_FIXTURE_LOCKED) {
  const locked = require('node:child_process').spawnSync('flock', [root + '/fixture.lock', process.execPath, __filename], {
    stdio: 'inherit', env: { ...process.env, ACCOUNTING_FIXTURE_LOCKED: '1' },
  });
  process.exit(locked.status ?? 1);
}
const users = JSON.parse(fs.readFileSync(root + '/clone-access.json')), actor = users.revisor;
const fixture = JSON.parse(fs.readFileSync(root + '/closings-fixture.json'));
let page, browser;
(async () => {
  browser = await chromium.launch({ executablePath: '/opt/google/chrome/chrome', headless: true,
    args: ['--no-sandbox', '--host-resolver-rules=MAP contabilidad.localhost 127.0.0.1'] });
  const context = await browser.newContext({ viewport: { width: 1600, height: 1050 }, serviceWorkers: 'block' });
  const probe = await context.request.get('http://127.0.0.1:9080/api/accounting/v1/health', { headers: { Host: 'contabilidad.localhost:9080', Accept: 'application/json' } });
  assert.equal(probe.headers()['x-accounting-isolated-test'], '1');
  page = await context.newPage();
  const errors = [], checks = [];
  page.on('pageerror', e => errors.push(e.message));
  await page.goto('http://contabilidad.localhost:9080/contabilidad/');
  await page.getByLabel('Correo electrónico de PRO9').fill(actor.email);
  await page.getByLabel('Contraseña', { exact: true }).fill(actor.password);
  const login = page.waitForResponse(r => r.url().endsWith('/api/login') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Entrar', exact: true }).click();
  actor.token = (await (await login).json()).token;
  await page.getByLabel('Código de verificación', { exact: true }).waitFor();
  const code = actor.recovery_codes.shift(); assert(code);
  fs.writeFileSync(root + '/clone-access.json', JSON.stringify(users, null, 2));
  await page.getByLabel('Código de verificación', { exact: true }).fill(code);
  const verification = page.waitForResponse(r => r.url().endsWith('/auth/mfa/verify') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Verificar y continuar' }).click();
  actor.verification = (await (await verification).json()).verification_token;
  fs.writeFileSync(root + '/clone-access.json', JSON.stringify(users, null, 2));
  await page.getByLabel('Empresa activa', { exact: true }).waitFor();
  await page.getByLabel('Empresa activa', { exact: true }).selectOption(fixture.company_id);
  await page.getByRole('heading', { name: 'Tu contabilidad, en orden' }).waitFor();
  await page.getByRole('link', { name: 'Cierres', exact: true }).click();
  await page.getByLabel('Ejercicio', { exact: true }).selectOption(String(fixture.year));
  await page.locator('.months .month').filter({ hasText: 'Enero' }).click();
  await page.getByRole('heading', { name: 'Enero ' + fixture.year, exact: true }).waitFor();
  await page.getByRole('button', { name: 'Revisar y cerrar mes', exact: true }).waitFor({ state: 'visible' });
  assert.equal(await page.getByRole('button', { name: 'Revisar y cerrar mes', exact: true }).isEnabled(), true);
  await page.screenshot({ path: root + '/closings-desktop.png', fullPage: true });
  checks.push('revisión mensual con períodos, controles y auxiliares desde la PWA compilada');

  await page.getByRole('button', { name: 'Revisar y cerrar mes', exact: true }).click();
  const dialog = page.getByRole('dialog', { name: 'Revisión final del cierre', exact: true });
  const confirmClose = dialog.getByRole('button', { name: 'Confirmar cierre mensual', exact: true });
  assert.equal(await confirmClose.isDisabled(), true);
  for (const label of ['Conciliación con PRO9 y otros orígenes', 'Conciliación de cajas y bancos', 'Revisión fiscal y soportes']) {
    await dialog.getByLabel(label, { exact: true }).fill('Papel de trabajo ficticio conciliado y revisado para este mes.');
  }
  await dialog.getByLabel('Motivo de la confirmación del cierre', { exact: true }).fill('Cierre ficticio preparado desde la interfaz con sus soportes.');
  await dialog.getByRole('checkbox').check();
  assert.equal(await confirmClose.isEnabled(), true);
  checks.push('la confirmación exige conciliaciones, motivo y revisión expresa');

  let closeCalls = 0, confirmedClose;
  await page.route('**/periods/*/close', async route => {
    closeCalls++;
    const response = await route.fetch(); assert.equal(response.status(), 200);
    confirmedClose = (await response.json()).data;
    await route.abort('connectionfailed');
  });
  await confirmClose.click();
  await page.getByText('Hay un envío sin confirmar', { exact: true }).waitFor();
  await dialog.getByRole('button', { name: 'Volver', exact: true }).click();
  assert.equal(closeCalls, 1); assert.equal(confirmedClose.period.status, 'closed');
  await page.unroute('**/periods/*/close');
  await page.getByRole('button', { name: 'Consultar resultado', exact: true }).click();
  await page.getByRole('button', { name: 'Reabrir período', exact: true }).waitFor();
  assert.equal(closeCalls, 1);
  assert.equal(await page.getByText('Hay un envío sin confirmar', { exact: true }).count(), 0);
  checks.push('respuesta perdida recuperada por recibo sin volver a enviar el cierre');

  await page.getByRole('button', { name: 'Ver revisión', exact: true }).click();
  const saved = page.getByRole('dialog', { name: 'Revisión conservada', exact: true });
  await saved.getByText('SHA-256 · ' + confirmedClose.snapshot_sha256, { exact: true }).waitFor();
  const downloadEvent = page.waitForEvent('download');
  await saved.getByRole('button', { name: 'Descargar revisión JSON', exact: true }).click();
  const download = await downloadEvent;
  const exported = JSON.parse(fs.readFileSync(await download.path(), 'utf8'));
  assert.equal(exported.snapshot_sha256, confirmedClose.snapshot_sha256);
  assert.equal(exported.snapshot.reports['trial-balance'].totals.debit, '0.00');
  await saved.getByRole('button', { name: 'Cerrar revisión', exact: true }).click();
  checks.push('revisión e informes conservados descargables con su hash');

  await page.getByRole('button', { name: 'Reabrir período', exact: true }).click();
  const reopen = page.getByRole('dialog', { name: 'Reapertura del período', exact: true });
  assert.equal(await reopen.getByRole('button', { name: 'Confirmar reapertura', exact: true }).isDisabled(), true);
  await reopen.getByText('Enero ' + fixture.year + ' · Versión 2 → 3', { exact: true }).waitFor();
  await reopen.getByLabel('Motivo de la reapertura', { exact: true }).fill('Reapertura ficticia para registrar un ajuste con evidencia revisada.');
  await reopen.getByRole('checkbox').check();
  await reopen.getByRole('button', { name: 'Confirmar reapertura', exact: true }).click();
  await reopen.waitFor({ state: 'hidden' });
  await page.getByRole('button', { name: 'Revisar y cerrar mes', exact: true }).waitFor();
  await page.locator('.closing-heading').getByText(/Versión 3/).waitFor();
  checks.push('reapertura con motivo, alcance explícito y nueva versión del período');

  async function request(endpoint, body, headers = {}) {
    const response = await context.request.fetch('http://contabilidad.localhost:9080' + endpoint, {
      method: body === undefined ? 'GET' : 'POST', data: body,
      headers: { Accept: 'application/json', Authorization: 'Bearer ' + actor.token, 'X-Accounting-Verification': actor.verification, ...headers },
    });
    assert.equal(response.headers()['x-accounting-isolated-test'], '1');
    assert.equal(response.status(), 200, 'La API aislada debe aceptar la operación ficticia.');
    return response.json();
  }
  const base = '/api/accounting/v1/companies/' + fixture.company_id;
  const accounts = (await request(base + '/catalogs/accounts')).data;
  let entry = (await request(base + '/entries', { accounting_date: fixture.year + '-01-20', concept: 'Ajuste ficticio posterior a reapertura', lines: [
    { account_id: accounts.find(a => a.code === '1.1.02').id, debit: '55.25', credit: '0' },
    { account_id: accounts.find(a => a.code === '3.1').id, debit: '0', credit: '55.25' },
  ] }, { 'Idempotency-Key': require('node:crypto').randomUUID() })).data;
  entry = (await request(base + '/entries/' + entry.id + '/submit', {}, { 'If-Match': String(entry.version) })).data;
  entry = (await request(base + '/entries/' + entry.id + '/approve', { comment: 'Autocontrol explícito del ajuste ficticio de la prueba UI.' }, { 'If-Match': String(entry.version) })).data;
  await request(base + '/entries/' + entry.id + '/post', {}, { 'If-Match': String(entry.version), 'Idempotency-Key': require('node:crypto').randomUUID() });
  await page.evaluate(() => { history.pushState({}, '', '/contabilidad/informes?tipo=trial-balance'); window.dispatchEvent(new PopStateEvent('popstate')); });
  await page.getByLabel('Desde', { exact: true }).fill(fixture.year + '-01-01');
  await page.getByLabel('Hasta', { exact: true }).fill(fixture.year + '-01-31');
  await page.getByRole('button', { name: /Consultar|Generar/ }).click();
  await page.getByText(new RegExp('Período ' + fixture.year + '-01 · Versión 3 · Abierto')).waitFor();
  await page.getByRole('cell', { name: '55,25', exact: true }).first().waitFor();
  checks.push('los informes posteriores al ajuste muestran importes nuevos y versión del período reabierto');

  await page.getByRole('link', { name: 'Cierres', exact: true }).click();
  await page.getByLabel('Ejercicio', { exact: true }).selectOption(String(fixture.year));
  await page.locator('.months .month').filter({ hasText: 'Enero' }).click();
  await page.getByRole('heading', { name: 'Enero ' + fixture.year, exact: true }).waitFor();
  const closeRow = page.getByRole('row').filter({ hasText: 'Cierre ficticio preparado desde la interfaz' });
  await closeRow.getByRole('button', { name: 'Ver revisión', exact: true }).click();
  await saved.getByText('SHA-256 · ' + confirmedClose.snapshot_sha256, { exact: true }).waitFor();
  assert.equal(await saved.locator('.saved-totals dd').filter({ hasText: '0,00 VES' }).count(), 3);
  await page.screenshot({ path: root + '/closings-preserved-report.png', fullPage: true });
  await saved.getByRole('button', { name: 'Cerrar revisión', exact: true }).click();
  checks.push('la versión anterior sigue intacta después de contabilizar movimientos nuevos');

  await page.locator('.months .month').filter({ hasText: 'Febrero' }).click();
  await page.getByRole('heading', { name: 'Febrero ' + fixture.year, exact: true }).waitFor();
  await page.getByText('Cierra primero los períodos anteriores desde el inicio del libro.', { exact: false }).waitFor();
  assert.equal(await page.getByRole('button', { name: 'Revisar y cerrar mes', exact: true }).isDisabled(), true);
  checks.push('un mes posterior no se cierra mientras el anterior permanece abierto');

  await context.setOffline(true);
  await page.getByText('Sin conexión puedes consultar el estado de los períodos incluidos en tu copia.', { exact: false }).waitFor();
  assert.equal(await page.getByRole('button', { name: 'Revisar y cerrar mes', exact: true }).count(), 0);
  assert.equal(await page.getByRole('button', { name: 'Reabrir período', exact: true }).count(), 0);
  checks.push('sin conexión desaparecen los controles definitivos y no se crea una cola de cierres');
  await context.setOffline(false);
  await page.getByLabel('Ejercicio', { exact: true }).waitFor({ state: 'visible' });
  await page.locator('.months .month').filter({ hasText: 'Enero' }).click();
  await page.getByRole('heading', { name: 'Enero ' + fixture.year, exact: true }).waitFor();
  await page.setViewportSize({ width: 390, height: 844 });
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
  await page.screenshot({ path: root + '/closings-mobile.png', fullPage: true });
  checks.push('la revisión mensual se adapta a móvil sin desbordar la página');
  assert.deepEqual(errors, []);
  const result = { passed: checks.length, checks, pageErrors: errors };
  fs.writeFileSync(root + '/closings-ui-tests.json', JSON.stringify(result, null, 2));
  console.log(JSON.stringify(result));
  await browser.close();
})().catch(async e => {
  if (page) await page.screenshot({ path: root + '/closings-ui-failure.png', fullPage: true });
  console.error(e.message); if (browser) await browser.close(); process.exit(1);
});
