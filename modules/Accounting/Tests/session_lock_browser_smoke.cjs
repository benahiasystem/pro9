const { chromium } = require(process.env.ACCOUNTING_PLAYWRIGHT_MODULE || '@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(process.env.ACCOUNTING_RUNTIME_DIR || '.accounting-isolated');
assert.equal(path.basename(root), '.accounting-isolated');
if (!process.env.ACCOUNTING_FIXTURE_LOCKED) {
  const locked = require('node:child_process').spawnSync(
    'flock',
    [root + '/fixture.lock', process.execPath, __filename],
    {
      stdio: 'inherit',
      env: { ...process.env, ACCOUNTING_FIXTURE_LOCKED: '1' },
    },
  );
  process.exit(locked.status ?? 1);
}
const actors = JSON.parse(fs.readFileSync(root + '/clone-access.json'));
const data = JSON.parse(fs.readFileSync(root + '/auxiliary-fixture.json')).ui;
const origin = 'http://contabilidad.localhost:9080';
const phrase = 'Bloqueo ficticio de prueba 2026';
const stamp = Date.now();
const concept = 'Captura privada de bloqueo ' + stamp;
const number = 'FV-BLOQUEO-' + stamp;
const checks = [],
  errors = [],
  dialogs = [];
let browser, page;
function persist() {
  fs.writeFileSync(root + '/clone-access.json', JSON.stringify(actors, null, 2));
}
function observe(p) {
  p.on('pageerror', (error) => errors.push(error.message));
  p.on('dialog', async (dialog) => {
    dialogs.push(dialog.type());
    await dialog.dismiss();
  });
  page = p;
  return p;
}
function headers(actor) {
  return {
    Host: 'contabilidad.localhost:9080',
    Accept: 'application/json',
    Authorization: 'Bearer ' + actor.token,
    'X-Accounting-Verification': actor.verification,
  };
}
async function login(p, actor) {
  await p.goto(origin + '/contabilidad/');
  await p.getByLabel('Correo electrónico de SAB', { exact: true }).fill(actor.email);
  await p.getByLabel('Contraseña', { exact: true }).fill(actor.password);
  const response = p.waitForResponse(
    (r) => r.url().endsWith('/api/login') && r.request().method() === 'POST',
  );
  await p.getByRole('button', { name: 'Entrar', exact: true }).click();
  actor.token = (await (await response).json()).token;
  const code = actor.recovery_codes.shift();
  assert(code);
  persist();
  await p.getByLabel('Código de verificación', { exact: true }).fill(code);
  const verification = p.waitForResponse(
    (r) => r.url().endsWith('/auth/mfa/verify') && r.request().method() === 'POST',
  );
  await p.getByRole('button', { name: 'Verificar y continuar', exact: true }).click();
  actor.verification = (await (await verification).json()).verification_token;
  persist();
  await p.getByLabel('Empresa activa', { exact: true }).selectOption(data.company_id);
}
async function stored(p) {
  await p
    .locator('.recovery-status')
    .filter({ hasText: 'Captura cifrada conservada en este equipo' })
    .waitFor();
}
async function locked(p) {
  await p.getByRole('heading', { name: 'Sesión bloqueada', exact: true }).waitFor();
  assert.equal(await p.locator('#protected-application').isVisible(), false);
  assert.notEqual(await p.locator('#protected-application').getAttribute('inert'), null);
  await p.getByRole('button', { name: /^(Desbloquear copia|Verificar mi cuenta)$/ }).waitFor();
  await p.waitForFunction(() => !document.querySelector('.session-lock fieldset')?.disabled);
}
async function unlockLocal(p, value = phrase) {
  await p.getByLabel('Frase local de desbloqueo', { exact: true }).fill(value);
  await p.getByRole('button', { name: 'Desbloquear copia', exact: true }).click();
}
async function resume(p) {
  await p.getByRole('heading', { name: 'Recuperar una captura', exact: true }).waitFor();
  await p
    .getByRole('dialog')
    .getByRole('button', { name: 'Continuar captura', exact: true })
    .click();
  await p.getByRole('dialog').waitFor({ state: 'hidden' });
}
(async () => {
  browser = await chromium.launch({
    executablePath: '/opt/google/chrome/chrome',
    headless: true,
    args: ['--no-sandbox', '--host-resolver-rules=MAP contabilidad.localhost 127.0.0.1'],
  });
  const context = await browser.newContext({
    viewport: { width: 1600, height: 1050 },
    acceptDownloads: true,
  });
  page = observe(await context.newPage());
  const probe = await context.request.get('http://127.0.0.1:9080/api/accounting/v1/health', {
    headers: { Host: 'contabilidad.localhost:9080', Accept: 'application/json' },
  });
  assert.equal(probe.headers()['x-accounting-isolated-test'], '1');
  await page.clock.install({ time: new Date() });
  await login(page, actors.autor);
  await page.getByRole('link', { name: 'Preparar trabajo local', exact: true }).click();
  await page.getByLabel('Frase local de cifrado', { exact: true }).fill(phrase);
  await page.getByLabel('Repetir frase local', { exact: true }).fill(phrase);
  const initialPreparation = page.waitForResponse(
    (r) => r.url().endsWith('/offline/prepare') && r.request().method() === 'POST',
  );
  await page.getByRole('button', { name: 'Preparar copia cifrada', exact: true }).click();
  const initialPackage = (await (await initialPreparation).json()).data;
  await page.getByRole('heading', { name: 'Copia desbloqueada', exact: true }).waitFor();
  await page.waitForFunction(() => !!navigator.serviceWorker.controller);
  await page.getByRole('link', { name: 'Comprobantes', exact: true }).click();
  await page.getByRole('link', { name: 'Nuevo comprobante', exact: true }).click();
  await page.getByLabel('Concepto *', { exact: true }).fill(concept);
  await page.getByLabel('Cuenta de línea 1', { exact: true }).selectOption(data.chart['1.1.02'].id);
  await page.getByLabel('Debe de línea 1', { exact: true }).fill('12,');
  await stored(page);
  await context.setOffline(true);
  await page.clock.fastForward(14 * 60 * 1000 + 50000);
  assert.equal(
    await page.getByRole('heading', { name: 'Sesión bloqueada', exact: true }).count(),
    0,
  );
  await page.getByLabel('Concepto *', { exact: true }).fill(concept + ' actualizado');
  await stored(page);
  await page.clock.fastForward(14 * 60 * 1000 + 50000);
  assert.equal(
    await page.getByRole('heading', { name: 'Sesión bloqueada', exact: true }).count(),
    0,
  );
  await page.clock.fastForward(11000);
  await locked(page);
  assert(!(await page.locator('body').innerText()).includes(concept));
  checks.push(
    'actividad real renueva el plazo y quince minutos sin actividad bloquean y ocultan los datos',
  );
  await unlockLocal(page, 'Frase ficticia equivocada');
  await page.getByRole('alert').filter({ hasText: 'La frase local no es correcta' }).waitFor();
  assert.equal(await page.locator('#protected-application').isVisible(), false);
  await unlockLocal(page);
  await page
    .getByRole('heading', { name: 'Sesión bloqueada', exact: true })
    .waitFor({ state: 'hidden' });
  assert.equal(
    await page.getByLabel('Concepto *', { exact: true }).inputValue(),
    concept + ' actualizado',
  );
  assert.equal(await page.getByLabel('Debe de línea 1', { exact: true }).inputValue(), '12,');
  checks.push(
    'frase incorrecta mantiene el bloqueo y la correcta recupera campos incompletos sin conexión',
  );
  await page.evaluate(() => {
    window.__lockOriginalPut = IDBObjectStore.prototype.put;
    IDBObjectStore.prototype.put = function (...args) {
      if (this.name === 'recoveries') throw new DOMException('Full', 'QuotaExceededError');
      return window.__lockOriginalPut.apply(this, args);
    };
  });
  const memoryConcept = concept + ' solo memoria';
  await page.getByLabel('Concepto *', { exact: true }).fill(memoryConcept);
  await page.getByRole('button', { name: 'Bloquear sesión', exact: true }).click();
  await locked(page);
  await page
    .getByRole('alert')
    .filter({ hasText: 'Hay cambios cuyo guardado no pudo confirmarse' })
    .waitFor();
  assert(!(await page.locator('body').innerText()).includes(memoryConcept));
  await unlockLocal(page);
  await page
    .getByRole('heading', { name: 'Sesión bloqueada', exact: true })
    .waitFor({ state: 'hidden' });
  assert.equal(await page.getByLabel('Concepto *', { exact: true }).inputValue(), memoryConcept);
  await page.evaluate(() => {
    IDBObjectStore.prototype.put = window.__lockOriginalPut;
    delete window.__lockOriginalPut;
  });
  await page.getByLabel('Concepto *', { exact: true }).fill(memoryConcept + ' conservada');
  await stored(page);
  checks.push('cuota agotada bloquea la interfaz sin perder la captura retenida en memoria');
  await page.getByRole('link', { name: 'Comprobantes', exact: true }).click();
  await page.getByRole('link', { name: 'Nuevo comprobante', exact: true }).click();
  await page.getByRole('dialog').waitFor();
  await page.clock.fastForward(15 * 60 * 1000 + 1000);
  await locked(page);
  assert.equal(await page.getByRole('dialog').count(), 0);
  assert.equal(await page.locator('.modal-backdrop:visible').count(), 0);
  await unlockLocal(page);
  await resume(page);
  assert.equal(
    await page.getByLabel('Concepto *', { exact: true }).inputValue(),
    memoryConcept + ' conservada',
  );
  checks.push('un diálogo abierto tampoco expone datos ni captura el foco durante el bloqueo');
  const first = page;
  const second = observe(await context.newPage());
  await second.goto(origin + '/contabilidad/');
  await second.getByRole('link', { name: 'Abrir copia local', exact: true }).click();
  await second.getByLabel('Frase local', { exact: true }).fill(phrase);
  await second.getByRole('button', { name: 'Desbloquear copia', exact: true }).click();
  await second.getByRole('heading', { name: 'Copia desbloqueada', exact: true }).waitFor();
  await second.getByRole('link', { name: 'Abrir comprobantes', exact: true }).click();
  await context.setOffline(false);
  const reviewer = observe(await context.newPage());
  await login(reviewer, actors.revisor);
  await first.getByRole('button', { name: 'Bloquear sesión', exact: true }).click();
  await locked(first);
  await locked(second);
  assert.equal(
    await reviewer.getByRole('heading', { name: 'Sesión bloqueada', exact: true }).count(),
    0,
  );
  checks.push('el bloqueo alcanza otras pestañas del mismo propietario y respeta otra cuenta');
  await first.getByRole('button', { name: 'Cerrar esta sesión', exact: true }).click();
  const download = first.waitForEvent('download');
  await first.getByRole('button', { name: 'Descargar respaldo cifrado', exact: true }).click();
  const backup = root + '/session-lock-backup.json';
  await (await download).saveAs(backup);
  fs.chmodSync(backup, 0o600);
  const text = fs.readFileSync(backup, 'utf8');
  assert(!text.includes(concept) && !text.includes(phrase));
  assert.equal(JSON.parse(text).recoveries.length, 1);
  await first.getByRole('button', { name: 'Seguir aquí', exact: true }).click();
  checks.push(
    'respaldo cifrado disponible con la sesión bloqueada sin incluir campos privados en claro',
  );
  page = reviewer;
  await page.getByRole('link', { name: 'Documentos', exact: true }).click();
  await page.getByRole('link', { name: 'Nuevo documento', exact: true }).click();
  await page.getByLabel('Número del documento', { exact: true }).fill(number);
  await page.getByLabel('Base del documento', { exact: true }).fill('100');
  await page.getByLabel('IVA', { exact: true }).fill('0');
  await page.getByLabel('Otros tributos', { exact: true }).fill('0');
  await page.getByRole('button', { name: 'Sumar importes', exact: true }).click();
  let release, committed;
  let serverDocument;
  let writes = 0;
  const held = new Promise((resolve) => {
    release = resolve;
  });
  const accepted = new Promise((resolve) => {
    committed = resolve;
  });
  const pattern = '**' + data.base + '/documents';
  await page.route(pattern, async (route) => {
    if (route.request().method() !== 'POST') return route.continue();
    writes++;
    const response = await route.fetch();
    assert.equal(response.status(), 200);
    serverDocument = (await response.json()).data;
    committed();
    await held;
    await route.fulfill({ response });
  });
  await page.getByRole('button', { name: 'Guardar borrador', exact: true }).click();
  await accepted;
  await page.getByRole('button', { name: 'Bloquear sesión', exact: true }).click();
  await locked(page);
  release();
  await page
    .getByText('Hay una operación enviada a SAB pendiente de mostrar su resultado.', {
      exact: false,
    })
    .waitFor();
  assert(new URL(page.url()).pathname.endsWith('/documentos/nuevo'));
  const saved = await context.request.get(
    'http://127.0.0.1:9080' + data.base + '/documents?q=' + encodeURIComponent(number),
    { headers: headers(actors.revisor) },
  );
  assert.equal((await saved.json()).total, 1);
  checks.push(
    'una operación confirmada en SAB conserva su respuesta pendiente mientras la pantalla está bloqueada',
  );
  await page.getByLabel('Correo electrónico de SAB', { exact: true }).fill(actors.autor.email);
  await page.getByLabel('Contraseña', { exact: true }).fill(actors.autor.password);
  await page.getByRole('button', { name: 'Verificar mi cuenta', exact: true }).click();
  await page
    .getByRole('alert')
    .filter({ hasText: 'Esta pantalla pertenece a otra cuenta' })
    .waitFor();
  assert.equal(await page.locator('#protected-application').isVisible(), false);
  checks.push(
    'la contraseña válida de otro usuario no abre la sesión ni entrega su respuesta pendiente',
  );
  await page.getByLabel('Correo electrónico de SAB', { exact: true }).fill(actors.revisor.email);
  await page.getByLabel('Contraseña', { exact: true }).fill(actors.revisor.password);
  const auth = page.waitForResponse(
    (r) => r.url().endsWith('/api/login') && r.request().method() === 'POST',
  );
  await page.getByRole('button', { name: 'Verificar mi cuenta', exact: true }).click();
  actors.revisor.token = (await (await auth).json()).token;
  persist();
  await page.getByLabel('Código de verificación', { exact: true }).waitFor();
  assert.equal(await page.locator('#protected-application').isVisible(), false);
  const code = actors.revisor.recovery_codes.shift();
  assert(code);
  persist();
  await page.getByLabel('Código de verificación', { exact: true }).fill(code);
  const verification = page.waitForResponse(
    (r) => r.url().endsWith('/auth/mfa/verify') && r.request().method() === 'POST',
  );
  await page.getByRole('button', { name: 'Verificar y desbloquear', exact: true }).click();
  actors.revisor.verification = (await (await verification).json()).verification_token;
  persist();
  await page.waitForURL('**/documentos/' + serverDocument.id);
  await page.getByRole('heading', { name: number, exact: true }).waitFor();
  assert.equal(writes, 1);
  checks.push(
    'la cuenta original y su segundo factor muestran la única confirmación sin repetir el envío',
  );
  const reviewerPage = page;
  page = first;
  await page.getByRole('button', { name: 'Usar mi cuenta de SAB', exact: true }).click();
  await page.getByLabel('Correo electrónico de SAB', { exact: true }).fill(actors.autor.email);
  await page.getByLabel('Contraseña', { exact: true }).fill(actors.autor.password);
  const authorAuth = page.waitForResponse(
    (r) => r.url().endsWith('/api/login') && r.request().method() === 'POST',
  );
  await page.getByRole('button', { name: 'Verificar mi cuenta', exact: true }).click();
  actors.autor.token = (await (await authorAuth).json()).token;
  persist();
  await page.getByLabel('Código de verificación', { exact: true }).waitFor();
  const authorCode = actors.autor.recovery_codes.shift();
  assert(authorCode);
  persist();
  await page.getByLabel('Código de verificación', { exact: true }).fill(authorCode);
  const authorVerification = page.waitForResponse(
    (r) => r.url().endsWith('/auth/mfa/verify') && r.request().method() === 'POST',
  );
  await page.getByRole('button', { name: 'Verificar y desbloquear', exact: true }).click();
  actors.autor.verification = (await (await authorVerification).json()).verification_token;
  persist();
  await page
    .getByText(
      'Cuenta de SAB verificada. Escribe tu frase local para reabrir la copia y conservar sus borradores.',
      { exact: true },
    )
    .waitFor();
  assert.equal(await page.locator('#protected-application').isVisible(), false);
  const renewed = page.waitForResponse(
    (r) => r.url().endsWith('/offline/prepare') && r.request().method() === 'POST',
  );
  await unlockLocal(page);
  const renewedResponse = await renewed;
  assert.equal(renewedResponse.status(), 200);
  const renewedPackage = (await renewedResponse.json()).data;
  assert.equal(renewedPackage.scope.from, initialPackage.scope.from);
  assert.equal(renewedPackage.scope.to, initialPackage.scope.to);
  await page
    .getByRole('heading', { name: 'Sesión bloqueada', exact: true })
    .waitFor({ state: 'hidden' });
  assert.equal(
    await page.getByLabel('Concepto *', { exact: true }).inputValue(),
    memoryConcept + ' conservada',
  );
  await page.getByRole('link', { name: 'Preparar trabajo local', exact: true }).click();
  await page
    .getByRole('row')
    .filter({ hasText: memoryConcept + ' conservada' })
    .waitFor();
  assert.equal(
    await second.getByRole('heading', { name: 'Sesión bloqueada', exact: true }).isVisible(),
    true,
  );
  checks.push(
    'SAB vuelve a autorizar la copia con su frase e intervalo originales sin abrir otras pestañas',
  );
  await unlockLocal(second);
  await second.getByRole('heading', { name: 'Sesión bloqueada', exact: true }).waitFor({ state: 'hidden' });
  const authorLogout = first.waitForResponse(r => r.url().endsWith('/auth/logout') && r.request().method() === 'POST');
  await first.getByRole('button', { name: 'Cerrar sesión', exact: true }).click();
  assert.equal((await authorLogout).status(), 200);
  await first.waitForURL('**/acceso'); await first.getByRole('button', { name: 'Entrar', exact: true }).waitFor();
  await locked(second);
  await first.getByRole('link', { name: 'Abrir copia local', exact: true }).click();
  await first.getByLabel('Frase local', { exact: true }).fill(phrase);
  await first.getByRole('button', { name: 'Desbloquear copia', exact: true }).click();
  await first.getByRole('heading', { name: 'Copia desbloqueada', exact: true }).waitFor();
  await first.getByRole('row').filter({ hasText: memoryConcept + ' conservada' }).waitFor();
  checks.push('cerrar sesión bloquea las otras pestañas y permite recuperar la captura cifrada con su frase');
  page = reviewerPage;

  await page.unroute(pattern);
  await page.getByRole('link', { name: 'Comprobantes', exact: true }).click();
  await page.getByRole('link', { name: 'Nuevo comprobante', exact: true }).click();
  await page
    .getByLabel('Concepto *', { exact: true })
    .fill('Memoria ficticia pendiente de cierre ' + stamp);
  await page.getByRole('button', { name: 'Bloquear sesión', exact: true }).click();
  await locked(page);
  await page.getByRole('button', { name: 'Cerrar esta sesión', exact: true }).click();
  await page.getByRole('heading', { name: 'Cerrar esta sesión', exact: true }).waitFor();
  await page.getByRole('button', { name: 'Seguir aquí', exact: true }).click();
  await page.getByRole('heading', { name: 'Sesión bloqueada', exact: true }).waitFor();
  assert.equal(
    await page
      .locator('#protected-application')
      .getByLabel('Concepto *', { exact: true })
      .inputValue(),
    'Memoria ficticia pendiente de cierre ' + stamp,
  );
  checks.push(
    'cancelar el cierre conserva el trabajo en memoria y exige una decisión explícita para descartarlo',
  );
  await page.screenshot({ path: root + '/session-lock-desktop.png', fullPage: true });
  await page.setViewportSize({ width: 390, height: 844 });
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
  await page.screenshot({ path: root + '/session-lock-mobile.png', fullPage: true });
  await page.getByRole('button', { name: 'Cerrar esta sesión', exact: true }).click();
  await page
    .getByRole('button', { name: 'Cerrar y descartar solo la memoria', exact: true })
    .click();
  await page.waitForURL('**/acceso');
  await page.getByRole('button', { name: 'Entrar', exact: true }).waitFor();
  assert.deepEqual(dialogs, []);
  assert.deepEqual(errors, []);
  checks.push(
    'cierre confirmado limpia la sesión y conserva una interfaz móvil sin desbordamientos ni errores',
  );
  const result = { passed: checks.length, checks, pageErrors: errors };
  fs.writeFileSync(root + '/session-lock-browser-tests.json', JSON.stringify(result, null, 2));
  console.log(JSON.stringify(result, null, 2));
  await browser.close();
})().catch(async (error) => {
  if (page && !page.isClosed()) {
    await page.screenshot({ path: root + '/session-lock-failure.png', fullPage: true });
    console.error(
      JSON.stringify({
        url: page.url(),
        alerts: await page.getByRole('alert').allTextContents(),
        pageErrors: errors,
      }),
    );
  }
  console.error(error.stack);
  if (browser) await browser.close();
  process.exit(1);
});
