const { chromium, expect } = require(process.env.ACCOUNTING_PLAYWRIGHT_MODULE || '@playwright/test');
const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const root = path.resolve(process.env.ACCOUNTING_RUNTIME_DIR || '.accounting-isolated');
assert.equal(path.basename(root), '.accounting-isolated');
if (!process.env.ACCOUNTING_FIXTURE_LOCKED) {
  const run = spawnSync('flock', [root + '/fixture.lock', process.execPath, __filename], { stdio: 'inherit', env: { ...process.env, ACCOUNTING_FIXTURE_LOCKED: '1' } });
  process.exit(run.status ?? 1);
}
const prepared = spawnSync('docker', ['exec', '-u', '1000:1000', '-e', 'ACCOUNTING_FIXTURE_LOCKED=1', 'contabilidad-api-v1-web', 'php', 'modules/Accounting/Tests/prepare_reports_fixture.php', 'ui'], { encoding: 'utf8' });
assert.equal(prepared.status, 0, prepared.stdout + prepared.stderr);
const fixture = JSON.parse(fs.readFileSync(root + '/reports-ui-fixture.json'));
assert(fixture.company_name.startsWith('Exportaciones ficticias UI '));
const actors = JSON.parse(fs.readFileSync(root + '/clone-access.json')), actor = actors.revisor;
const origin = 'http://contabilidad.localhost:9080', checks = [], pageErrors = [];
let browser, page;
async function choose(title) {
  const home = page.getByRole('button', { name: 'Todos los informes', exact: true });
  if (await home.isVisible()) await home.click();
  await page.locator('.report-type').filter({ has: page.getByRole('heading', { name: title, exact: true }) }).click();
  for (const label of ['Limpiar cuenta', 'Limpiar tercero', 'Limpiar centro de costo']) {
    const clear = page.getByRole('button', { name: label, exact: true });
    if (await clear.isVisible()) await clear.click();
  }
  if (await page.getByLabel('Desde', { exact: true }).isVisible()) await page.getByLabel('Desde', { exact: true }).fill(fixture.from);
  await page.getByLabel(title === 'Partidas abiertas al corte' ? 'Fecha de corte' : 'Hasta', { exact: true }).fill(fixture.to);
}
async function query(kind) {
  const response = page.waitForResponse(r => r.url().includes('/reports/' + kind + '?'));
  await page.getByRole('button', { name: 'Consultar informe', exact: true }).click();
  const result = await response; assert.equal(result.status(), 200, await result.text());
  await page.locator('.report-result').waitFor();
  return (await result.json()).data;
}
async function lookup(label, term, text) {
  await page.getByRole('combobox', { name: label, exact: true }).fill(term);
  const option = page.getByRole('option', { name: text, exact: true });
  await option.waitFor(); await option.click();
}
async function navigateReports() {
  await page.getByRole('link', { name: 'Libros e informes', exact: true }).click();
  await page.getByRole('heading', { name: 'Libros e informes', exact: true }).waitFor();
}
(async () => {
  browser = await chromium.launch({ executablePath: '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox', '--host-resolver-rules=MAP contabilidad.localhost 127.0.0.1'] });
  const context = await browser.newContext({ viewport: { width: 1600, height: 1100 }, serviceWorkers: 'block', acceptDownloads: true });
  const probe = await context.request.get('http://127.0.0.1:9080/api/accounting/v1/health', { headers: { Host: 'contabilidad.localhost:9080', Accept: 'application/json' } });
  assert.equal(probe.headers()['x-accounting-isolated-test'], '1');
  page = await context.newPage(); page.on('pageerror', error => pageErrors.push(error.message));
  await page.goto(origin + '/contabilidad/');
  await page.getByLabel('Correo electrónico de PRO9').fill(actor.email);
  await page.getByLabel('Contraseña', { exact: true }).fill(actor.password);
  const auth = page.waitForResponse(r => r.url().endsWith('/api/login') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Entrar', exact: true }).click(); actor.token = (await (await auth).json()).token;
  await page.getByLabel('Código de verificación', { exact: true }).waitFor();
  const code = actor.recovery_codes.shift(); assert(code); fs.writeFileSync(root + '/clone-access.json', JSON.stringify(actors, null, 2));
  await page.getByLabel('Código de verificación', { exact: true }).fill(code);
  const verified = page.waitForResponse(r => r.url().endsWith('/auth/mfa/verify') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Verificar y continuar' }).click(); actor.verification = (await (await verified).json()).verification_token; fs.writeFileSync(root + '/clone-access.json', JSON.stringify(actors, null, 2));
  await page.getByLabel('Empresa activa', { exact: true }).selectOption(fixture.company_id);
  await page.getByRole('heading', { name: 'Tu contabilidad, en orden' }).waitFor();
  await navigateReports();
  assert.equal(await page.locator('.report-type').count(), 9);
  checks.push('Nueve informes agrupados con el diseño Índigo preciso');

  await choose('Libro Diario'); const journal = await query('journal');
  assert.equal(journal.total, 507); assert.equal(await page.locator('.report-data-table tbody tr').count(), 100);
  assert((await page.locator('.report-export-toolbar').textContent()).includes('507 filas completas'));
  const csvDownload = page.waitForEvent('download'); await page.getByRole('button', { name: 'CSV', exact: true }).click();
  const csvFile = await csvDownload; await csvFile.saveAs(root + '/reports-ui-journal.csv');
  assert.equal(fs.readFileSync(root + '/reports-ui-journal.csv', 'utf8').split(/\r?\n/).filter(line => line.startsWith('detalle;')).length, 507);
  await page.getByRole('region', { name: 'Versión conservada', exact: true }).waitFor();
  await expect(page.getByRole('button', { name: 'Descargar XLSX', exact: true })).toBeEnabled();
  checks.push('CSV completo de 507 líneas desde una vista de 100, con versión conservada');

  let downloads = 0; page.on('download', () => downloads++);
  await page.route('**/report-exports/*/download/pdf', async route => { const response = await route.fetch(); const body = await response.body(); await route.fulfill({ response, body: Buffer.concat([body, Buffer.from('alterado')]) }); });
  await page.getByRole('button', { name: 'Descargar PDF', exact: true }).click();
  await page.getByRole('alert').filter({ hasText: 'no coincide con la huella' }).waitFor(); assert.equal(downloads, 0);
  await page.unroute('**/report-exports/*/download/pdf');
  const pdfDownload = page.waitForEvent('download'); await page.getByRole('button', { name: 'Descargar PDF', exact: true }).click();
  await (await pdfDownload).saveAs(root + '/reports-ui-journal.pdf');
  assert(fs.readFileSync(root + '/reports-ui-journal.pdf').subarray(0, 5).equals(Buffer.from('%PDF-')));
  checks.push('Verificación SHA-256 antes de descargar; un archivo alterado no se guarda');

  await page.getByRole('button', { name: 'Consultar datos actuales', exact: true }).click(); await page.locator('.report-result').waitFor();
  await page.getByRole('button', { name: 'Página siguiente', exact: true }).click();
  await page.getByText('507 filas · Página 2 de 6', { exact: true }).waitFor();
  await choose('Libro Mayor'); assert.equal(await page.getByRole('button', { name: 'Consultar informe', exact: true }).isDisabled(), true);
  await lookup('Cuenta', '1.1.02', '1.1.02 · Bancos');
  const ledger = await query('ledger'); assert.equal(ledger.opening, '1000.00'); assert.equal(ledger.totals.balance, '4432.50');
  checks.push('Paginación del Diario y Mayor con cuenta buscada y saldo inicial exacto');

  await choose('Estado de resultados');
  await page.getByRole('checkbox', { name: 'Añadir intervalo comparativo', exact: true }).check();
  await page.getByLabel('Comparativo desde', { exact: true }).fill((fixture.year - 1) + '-01-01');
  await page.getByLabel('Comparativo hasta', { exact: true }).fill((fixture.year - 1) + '-11-30');
  const income = await query('income-statement'); assert.equal(income.totals.result, '1830.00'); assert.equal(income.comparison.totals.result, '1000.00');
  await page.evaluate(() => window.scrollTo(0, 0)); await page.screenshot({ path: root + '/reports-desktop.png' });
  checks.push('Estado preliminar con intervalo comparativo y controles completos');

  await choose('Partidas abiertas al corte'); const items = await query('open-items'); assert.equal(items.totals.book, '2400.00');
  await page.getByLabel('Fecha de corte', { exact: true }).fill(fixture.year + '-12-31'); assert.equal((await query('open-items')).totals.book, '4000.00');
  await choose('Resumen de documentos'); assert.equal((await query('documents')).total, 0);
  await page.getByRole('checkbox', { name: /Incluir documentos pendientes/ }).check();
  assert.equal((await query('documents')).data_scope, 'control_with_pending');
  checks.push('Corte histórico con reverso posterior y documentos pendientes identificados');

  await choose('Balance de comprobación'); await query('trial-balance');
  let intercepted = false;
  await page.route('**/report-exports', async route => {
    if (route.request().method() === 'POST' && !intercepted) { intercepted = true; await route.fetch(); await route.abort('failed'); }
    else await route.continue();
  });
  await page.getByRole('button', { name: 'Conservar versión', exact: true }).click();
  await page.getByText('Versión pendiente de confirmar', { exact: true }).waitFor();
  await page.getByRole('button', { name: 'Consultar resultado', exact: true }).click();
  await page.getByRole('region', { name: 'Versión conservada', exact: true }).waitFor(); await page.unroute('**/report-exports');
  await expect(page.locator('.report-history tbody tr')).toHaveCount(2);
  await expect(page.getByRole('button', { name: 'Descargar XLSX', exact: true })).toBeEnabled();
  checks.push('Respuesta perdida recuperada mediante recibo, sin capturar otra versión');

  await page.setViewportSize({ width: 390, height: 844 });
  await page.waitForFunction(() => !document.querySelector('.sidebar') || document.querySelector('.sidebar').getBoundingClientRect().right <= 1);
  await page.evaluate(() => window.scrollTo(0, 0)); await page.screenshot({ path: root + '/reports-mobile.png', fullPage: true });
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), false);
  const small = await page.locator('.report-frozen-card .button').evaluateAll(buttons => buttons.map(button => button.getBoundingClientRect().height).filter(height => height > 0 && height < 43)); assert.deepEqual(small, []);
  checks.push('Móvil de 390 px sin desbordamiento de página y controles de al menos 44 px');
  await page.setViewportSize({ width: 1600, height: 1100 });

  await page.getByRole('link', { name: 'Preparar trabajo local', exact: true }).click();
  const phrase = 'Copia de informes ficticios 2026';
  await page.getByLabel('Frase local de cifrado', { exact: true }).fill(phrase); await page.getByLabel('Repetir frase local').fill(phrase);
  await page.getByRole('button', { name: 'Preparar copia cifrada', exact: true }).click(); await page.getByRole('heading', { name: 'Copia desbloqueada' }).waitFor();
  await navigateReports(); await context.setOffline(true); await page.getByText(/Sin conexión puedes consultar los estados/).waitFor();
  const cards = page.locator('.report-type'); assert.equal(await cards.evaluateAll(items => items.filter(item => item.disabled).length), 6);
  await cards.filter({ has: page.getByRole('heading', { name: 'Balance de comprobación', exact: true }) }).click();
  await page.getByRole('button', { name: 'Consultar informe', exact: true }).click(); await page.locator('.report-result').waitFor();
  assert.equal(await page.getByRole('button', { name: 'Conservar versión', exact: true }).count(), 0);
  assert.equal(await page.getByRole('button', { name: 'CSV', exact: true }).count(), 0);
  assert.equal(await page.locator('.report-history').count(), 0);
  checks.push('Sin conexión solo estados preparados; captura y descargas definitivas requieren PRO9');
  assert.deepEqual(pageErrors, []);
  const result = { passed: checks.length, checks, pageErrors, fakeDataOnly: true };
  fs.writeFileSync(root + '/reports-browser-tests.json', JSON.stringify(result, null, 2)); console.log(JSON.stringify({ passed: checks.length, pageErrors }));
  await browser.close();
})().catch(async error => {
  if (page) { await page.screenshot({ path: root + '/reports-browser-failure.png', fullPage: true }).catch(() => {}); console.error(JSON.stringify({ url: page.url(), alerts: await page.getByRole('alert').allTextContents().catch(() => []) })); }
  console.error(error.stack); if (browser) await browser.close(); process.exit(1);
});
