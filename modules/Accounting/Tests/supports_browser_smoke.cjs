const { chromium, expect } = require(process.env.ACCOUNTING_PLAYWRIGHT_MODULE || '@playwright/test');
const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const { createHash } = require('node:crypto'), { spawnSync } = require('node:child_process');
const root = path.resolve(process.env.ACCOUNTING_RUNTIME_DIR || '.accounting-isolated');
assert.equal(path.basename(root), '.accounting-isolated');
if (!process.env.ACCOUNTING_FIXTURE_LOCKED) {
  const run = spawnSync('flock', [root + '/fixture.lock', process.execPath, __filename], { stdio: 'inherit', env: { ...process.env, ACCOUNTING_FIXTURE_LOCKED: '1' } });
  process.exit(run.status ?? 1);
}
const prepared = spawnSync('docker', ['exec', '-u', '1000:1000', '-e', 'ACCOUNTING_FIXTURE_LOCKED=1', 'contabilidad-api-v1-web', 'php', 'modules/Accounting/Tests/prepare_supports_fixture.php', 'ui'], { encoding: 'utf8' });
assert.equal(prepared.status, 0, prepared.stdout + prepared.stderr);
const fixture = JSON.parse(fs.readFileSync(root + '/supports-ui-fixture.json'));
assert(fixture.company_name.startsWith('Soportes ficticios UI '));
const actors = JSON.parse(fs.readFileSync(root + '/clone-access.json')), actor = actors.revisor;
const origin = 'http://contabilidad.localhost:9080', base = '/api/accounting/v1/companies/' + fixture.company_id;
const checks = [], pageErrors = [], phrase = 'Soportes ficticios recuperables 2026';
const record = (message) => { checks.push(message); console.log('Check ' + checks.length + ': ' + message); };
let browser, context, page;
async function navigate(target) {
  const verify = page.getByRole('button', { name: 'Verificar archivos nuevamente', exact: true });
  if (await verify.isVisible()) await expect(verify).toBeEnabled();
  await page.evaluate((value) => { history.pushState({}, '', '/contabilidad' + value); window.dispatchEvent(new PopStateEvent('popstate')); }, target);
}
async function api(target) {
  const r = await context.request.get('http://127.0.0.1:9080' + base + target, { headers: { Host: 'contabilidad.localhost:9080', Accept: 'application/json', Authorization: 'Bearer ' + actor.token, 'X-Accounting-Verification': actor.verification } });
  assert.equal(r.headers()['x-accounting-isolated-test'], '1'); assert.equal(r.status(), 200, await r.text()); return r.json();
}
async function stored() {
  await page.locator('.recovery-status').filter({ hasText: 'Captura cifrada conservada en este equipo' }).waitFor();
}
async function prepareUpload(name, title, buffer = Buffer.from('Evidencia ficticia de la interfaz contable.\n')) {
  const open = page.getByRole('button', { name: 'Adjuntar soporte', exact: true });
  if (await open.isVisible()) await open.click();
  await page.locator('#support-file').setInputFiles({ name, mimeType: 'text/plain', buffer });
  await page.getByLabel('Descripción del soporte', { exact: true }).fill(title);
  await page.getByLabel('Motivo y relación con el registro', { exact: true }).fill('Conservar la evidencia ficticia y verificar la trazabilidad de la operación.');
  await expect(page.getByRole('button', { name: 'Conservar soporte en PRO9', exact: true })).toBeEnabled();
}
async function send() {
  const pending = page.waitForResponse((r) => r.url().includes('/attachments') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Conservar soporte en PRO9', exact: true }).click();
  const response = await pending; assert.equal(response.status(), 200, await response.text());
  return (await response.json()).data;
}
(async () => {
  browser = await chromium.launch({ executablePath: '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox', '--host-resolver-rules=MAP contabilidad.localhost 127.0.0.1'] });
  context = await browser.newContext({ viewport: { width: 1600, height: 1050 }, serviceWorkers: 'block', acceptDownloads: true });
  const health = await context.request.get('http://127.0.0.1:9080/api/accounting/v1/health', { headers: { Host: 'contabilidad.localhost:9080', Accept: 'application/json' } });
  assert.equal(health.headers()['x-accounting-isolated-test'], '1');
  page = await context.newPage(); page.on('pageerror', (e) => pageErrors.push(e.message));
  await page.goto(origin + '/contabilidad/');
  await page.getByLabel('Correo electrónico de PRO9').fill(actor.email); await page.getByLabel('Contraseña', { exact: true }).fill(actor.password);
  const auth = page.waitForResponse((r) => r.url().endsWith('/api/login') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Entrar', exact: true }).click(); actor.token = (await (await auth).json()).token;
  await page.getByLabel('Código de verificación', { exact: true }).waitFor(); const code = actor.recovery_codes.shift(); assert(code);
  fs.writeFileSync(root + '/clone-access.json', JSON.stringify(actors, null, 2));
  await page.getByLabel('Código de verificación', { exact: true }).fill(code);
  const verified = page.waitForResponse((r) => r.url().endsWith('/auth/mfa/verify') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Verificar y continuar' }).click(); actor.verification = (await (await verified).json()).verification_token;
  fs.writeFileSync(root + '/clone-access.json', JSON.stringify(actors, null, 2));
  await page.getByLabel('Empresa activa', { exact: true }).selectOption(fixture.company_id);
  await page.getByRole('heading', { name: 'Tu contabilidad, en orden' }).waitFor();

  await navigate('/comprobantes/' + fixture.posted_id); await page.locator('.evidence-item').waitFor();
  await page.getByRole('button', { name: 'Ver imagen', exact: true }).click();
  await page.locator('.evidence-preview').waitFor();
  assert((await page.locator('.evidence-preview').getAttribute('src')).startsWith('blob:'));
  await page.getByRole('dialog').getByRole('button', { name: 'Cerrar', exact: true }).click();
  const download = page.waitForEvent('download'); await page.getByRole('button', { name: 'Descargar original', exact: true }).click();
  const output = root + '/support-ui-original.png'; await (await download).saveAs(output);
  assert.equal(createHash('sha256').update(fs.readFileSync(output)).digest('hex'), fixture.attachment_sha256);
  record('Lista y detalle contextual de soportes, vista de imagen recodificada y descarga original con SHA-256');

  let downloads = 0; page.on('download', () => downloads++);
  await page.route('**/attachments/*/download', async (route) => { const response = await route.fetch(); await route.fulfill({ response, body: Buffer.concat([await response.body(), Buffer.from('alterado')]) }); });
  await page.getByRole('button', { name: 'Descargar original', exact: true }).click();
  await page.getByRole('alert').filter({ hasText: 'no coincide con la huella' }).waitFor(); assert.equal(downloads, 0);
  await page.unroute('**/attachments/*/download');
  record('La interfaz bloquea un archivo alterado antes de abrirlo o guardarlo');

  const postedBefore = (await api('/entries/' + fixture.posted_id)).data;
  await prepareUpload('anexo-posterior.txt', 'Aclaración posterior ficticia'); const later = await send();
  assert(later.attachment.after_posting); await page.getByRole('status').filter({ hasText: 'Soporte conservado como evidencia adicional' }).first().waitFor();
  assert.deepEqual((await api('/entries/' + fixture.posted_id)).data, postedBefore);
  await expect(page.locator('.evidence-panel .loading-state')).toHaveCount(0);
  await expect(page.locator('.evidence-item')).toHaveCount(2);
  await page.evaluate(() => window.scrollTo(0, 0)); await page.screenshot({ path: root + '/supports-desktop.png' });
  record('Anexo posterior conservado con autor, motivo e historial; el asiento definitivo permanece idéntico');

  await page.getByRole('link', { name: 'Preparar trabajo local', exact: true }).click();
  await page.getByLabel('Frase local de cifrado', { exact: true }).fill(phrase);
  await page.getByLabel('Repetir frase local', { exact: true }).fill(phrase);
  await page.getByRole('button', { name: 'Preparar copia cifrada', exact: true }).click();
  await page.getByRole('heading', { name: 'Copia desbloqueada', exact: true }).waitFor();
  await navigate('/comprobantes/' + fixture.pending_id); await page.locator('.evidence-panel').waitFor();
  const secretTitle = 'Referencia ficticia de envío recuperable', secretBytes = Buffer.from('ARCHIVO_FICTICIO_NO_GUARDAR_EN_INDEXEDDB_2026\n');
  await prepareUpload('recuperable.txt', secretTitle, secretBytes);
  let lostResult;
  await page.route('**/evidence/journal_entry/' + fixture.pending_id + '/attachments', async (route) => { const response = await route.fetch(); assert.equal(response.status(), 200); lostResult = (await response.json()).data; await route.abort('failed'); });
  await page.getByRole('button', { name: 'Conservar soporte en PRO9', exact: true }).click();
  await page.getByRole('alert').filter({ hasText: 'No hay respuesta de PRO9' }).waitFor(); await stored(); assert(lostResult);
  await page.unroute('**/evidence/journal_entry/' + fixture.pending_id + '/attachments');
  await page.getByRole('link', { name: 'Preparar trabajo local', exact: true }).click();
  const captureRow = page.getByRole('row').filter({ hasText: secretTitle }); await captureRow.waitFor(); await expect(captureRow).toContainText('Metadatos de soporte');
  const backupEvent = page.waitForEvent('download'); await page.getByRole('button', { name: /^Descargar copia / }).click();
  const backupPath = root + '/supports-ui-backup.json'; await (await backupEvent).saveAs(backupPath); fs.chmodSync(backupPath, 0o600);
  const backupText = fs.readFileSync(backupPath, 'utf8'), backup = JSON.parse(backupText);
  assert.equal(backup.version, 5); assert.equal(backup.recoveries.length, 1);
  assert(!backupText.includes(secretTitle) && !backupText.includes(secretBytes.toString()) && !backupText.includes(phrase));
  await captureRow.getByRole('link', { name: 'Continuar captura', exact: true }).click();
  await page.getByRole('heading', { name: 'Recuperar una captura', exact: true }).waitFor();
  await page.getByRole('dialog').getByRole('row').filter({ hasText: secretTitle }).getByRole('button', { name: 'Continuar captura', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Reintentar mismo envío', exact: true })).toBeDisabled();
  await page.getByRole('button', { name: 'Consultar confirmación', exact: true }).click();
  await page.locator('.evidence-item').filter({ hasText: secretTitle }).waitFor();
  const afterLost = (await api('/evidence/journal_entry/' + fixture.pending_id));
  assert.equal(afterLost.meta.total, 1); assert.equal(afterLost.data[0].id, lostResult.attachment.id);
  assert.equal((await api('/entries/' + fixture.pending_id)).data.status, 'observed');
  record('Respuesta perdida recuperada desde metadatos cifrados versión cinco, sin volver a seleccionar ni duplicar el archivo');

  await prepareUpload('pendiente.txt', 'Envío ficticio cancelable');
  await page.route('**/evidence/journal_entry/' + fixture.pending_id + '/attachments', (route) => route.abort('failed'));
  await page.getByRole('button', { name: 'Conservar soporte en PRO9', exact: true }).click(); await page.getByRole('alert').filter({ hasText: 'No hay respuesta de PRO9' }).waitFor(); await stored();
  await page.unroute('**/evidence/journal_entry/' + fixture.pending_id + '/attachments');
  await page.locator('#support-file').setInputFiles({ name: 'otro.txt', mimeType: 'text/plain', buffer: Buffer.from('Archivo distinto') });
  await page.getByRole('alert').filter({ hasText: 'no coincide con el envío pendiente' }).waitFor();
  await page.getByRole('button', { name: 'Cancelar envío pendiente', exact: true }).click();
  await page.getByLabel('Motivo de la cancelación', { exact: true }).fill('Preparar otro soporte ficticio sin modificar el envío anterior.');
  const cancellation = page.waitForResponse((r) => r.url().includes('/operations/attachment.add/') && r.url().endsWith('/cancel'));
  await page.getByRole('button', { name: 'Confirmar cancelación', exact: true }).click();
  const cancellationResponse = await cancellation; assert.equal(cancellationResponse.status(), 200, await cancellationResponse.text());
  assert.equal((await cancellationResponse.json()).data.status, 'cancelled');
  await page.getByRole('status').filter({ hasText: 'Envío cancelado en PRO9' }).first().waitFor();
  assert.equal((await api('/evidence/journal_entry/' + fixture.pending_id)).meta.total, 1);
  record('Un envío pendiente rechaza otro archivo y exige confirmación de PRO9 para cancelar su identidad');

  await navigate('/documentos/' + fixture.document_id); await page.getByRole('heading', { name: 'FV-SOPORTE-FICTICIO', exact: true }).waitFor(); await page.locator('.evidence-panel').waitFor();
  await prepareUpload('factura-ficticia.txt', 'Soporte documental ficticio'); await send();
  await page.locator('.evidence-item').filter({ hasText: 'Soporte documental ficticio' }).waitFor();
  const document = (await api('/documents/' + fixture.document_id)).data;
  assert.equal(document.source_revision, fixture.source_revision); assert.equal(document.version, fixture.document_version + 1);
  record('Soporte vinculado al documento con su revisión de origen intacta');

  await navigate('/comprobantes/' + fixture.posted_id); await page.locator('.evidence-item').first().waitFor();
  await page.getByRole('link', { name: 'Consultar historial de este registro', exact: true }).click();
  await page.locator('.audit-filter-grid').waitFor(); await page.getByRole('button', { name: /^Ver evento / }).first().waitFor();
  assert.equal(await page.getByRole('button', { name: /^Ver evento / }).count(), 25);
  await page.getByRole('button', { name: 'Página siguiente', exact: true }).click(); await expect(page.locator('.audit-pagination')).toContainText('Página 2');
  await page.getByRole('button', { name: /^Ver evento / }).first().click(); await page.locator('.audit-detail pre').waitFor();
  await page.getByRole('button', { name: 'Verificar historial', exact: true }).click(); await page.getByRole('heading', { name: 'Historial verificado', exact: true }).waitFor();
  assert(!(await page.locator('.audit-verification').getAttribute('class')).includes('legacy'));
  await page.locator('.audit-section-heading').first().scrollIntoViewIfNeeded(); await page.screenshot({ path: root + '/audit-desktop.png' });
  record('Auditoría del caso paginada, detalle del evento y comprobación completa de las huellas versión dos');

  await page.setViewportSize({ width: 390, height: 844 }); await page.waitForTimeout(250);
  await page.evaluate(() => window.scrollTo(0, 0)); await page.screenshot({ path: root + '/audit-mobile.png' });
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'Auditoría desborda el móvil.');
  await navigate('/comprobantes/' + fixture.posted_id); await page.locator('.evidence-item').first().waitFor();
  await expect(page.locator('.evidence-panel .loading-state')).toHaveCount(0);
  await page.locator('.evidence-panel').evaluate((node) => window.scrollTo(0, window.scrollY + node.getBoundingClientRect().top - 138)); await page.screenshot({ path: root + '/supports-mobile.png' });
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'Soportes desbordan el móvil.');
  await context.setOffline(true); await page.getByText('Conecta con PRO9 para consultar, verificar o adjuntar los archivos originales.', { exact: true }).waitFor();
  assert.equal(await page.getByRole('button', { name: 'Descargar original', exact: true }).count(), 0);
  await context.setOffline(false); await page.locator('.evidence-item').first().waitFor();
  record('Interfaz móvil sin desbordes; sin conexión los archivos privados no se ofrecen como si estuvieran disponibles');

  await page.setViewportSize({ width: 1600, height: 1050 });
  await page.getByLabel('Empresa activa', { exact: true }).selectOption(fixture.foreign_company_id);
  await page.getByRole('link', { name: 'Auditoría y archivo', exact: true }).click();
  await page.getByRole('button', { name: 'Verificar historial', exact: true }).click(); await page.locator('.audit-verification.legacy').waitFor();
  assert(!(await page.locator('main').textContent()).includes('FICTICIO_NO_MOSTRAR'));
  record('Cambio de empresa y presentación honesta de antecedentes con huellas versión uno');

  await context.close();
  context = await browser.newContext({ viewport: { width: 1600, height: 1050 }, serviceWorkers: 'block' });
  page = await context.newPage(); page.on('pageerror', (e) => pageErrors.push(e.message));
  await page.goto(origin + '/contabilidad/');
  await page.getByLabel('Correo electrónico de PRO9').fill(actor.email); await page.getByLabel('Contraseña', { exact: true }).fill(actor.password);
  const restoredAuth = page.waitForResponse((r) => r.url().endsWith('/api/login') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Entrar', exact: true }).click(); actor.token = (await (await restoredAuth).json()).token;
  await page.getByLabel('Código de verificación', { exact: true }).waitFor(); const restoreCode = actor.recovery_codes.shift(); assert(restoreCode);
  fs.writeFileSync(root + '/clone-access.json', JSON.stringify(actors, null, 2)); await page.getByLabel('Código de verificación', { exact: true }).fill(restoreCode);
  const restoredVerification = page.waitForResponse((r) => r.url().endsWith('/auth/mfa/verify') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Verificar y continuar' }).click();
  let restoredVerificationResponse = await restoredVerification;
  if (restoredVerificationResponse.status() === 429) {
    const delay = Number(restoredVerificationResponse.headers()['retry-after'] || '60'); assert(delay > 0 && delay <= 60);
    console.log('Se respeta Retry-After del servidor antes de repetir la verificación.');
    await page.waitForTimeout(delay * 1000);
    const retryVerification = page.waitForResponse((r) => r.url().endsWith('/auth/mfa/verify') && r.request().method() === 'POST');
    await page.getByRole('button', { name: 'Verificar y continuar' }).click(); restoredVerificationResponse = await retryVerification;
  }
  assert.equal(restoredVerificationResponse.status(), 200, await restoredVerificationResponse.text());
  actor.verification = (await restoredVerificationResponse.json()).verification_token;
  fs.writeFileSync(root + '/clone-access.json', JSON.stringify(actors, null, 2));
  await page.getByLabel('Empresa activa', { exact: true }).selectOption(fixture.company_id);
  await page.getByRole('link', { name: 'Preparar trabajo local', exact: true }).click();
  await page.getByLabel('Archivo de respaldo cifrado', { exact: true }).setInputFiles(backupPath);
  await page.getByLabel('Frase del respaldo', { exact: true }).fill(phrase);
  await page.getByRole('button', { name: 'Recuperar y conservar borradores', exact: true }).click();
  await page.getByText(/Recuperación completada:/).waitFor();
  const restoredCapture = page.getByRole('row').filter({ hasText: secretTitle }); await restoredCapture.waitFor();
  await restoredCapture.getByRole('link', { name: 'Continuar captura', exact: true }).click();
  await page.getByRole('heading', { name: 'Recuperar una captura', exact: true }).waitFor();
  await page.getByRole('dialog').getByRole('row').filter({ hasText: secretTitle }).getByRole('button', { name: 'Continuar captura', exact: true }).click();
  await expect(page.getByLabel('Descripción del soporte', { exact: true })).toHaveValue(secretTitle);
  await expect(page.getByRole('button', { name: 'Reintentar mismo envío', exact: true })).toBeDisabled();
  await page.getByRole('button', { name: 'Consultar confirmación', exact: true }).click();
  await page.locator('.evidence-item').filter({ hasText: secretTitle }).waitFor();
  assert.equal((await api('/evidence/journal_entry/' + fixture.pending_id)).meta.total, 1);
  record('Restauración real del respaldo versión cinco en otro navegador conserva la identidad y recupera la confirmación sin bytes locales');

  assert.deepEqual(pageErrors, []);
  const result = { passed: checks.length, checks, pageErrors, data: 'fictitious', isolated: true };
  fs.writeFileSync(root + '/supports-browser-tests.json', JSON.stringify(result, null, 2)); console.log(JSON.stringify(result, null, 2));
  await browser.close();
})().catch(async (e) => {
  if (page) { await page.screenshot({ path: root + '/supports-browser-failure.png', fullPage: true }).catch(() => {}); console.error(JSON.stringify({ url: page.url(), alerts: await page.getByRole('alert').allTextContents().catch(() => []) })); }
  console.error(e.stack); if (browser) await browser.close(); process.exit(1);
});
