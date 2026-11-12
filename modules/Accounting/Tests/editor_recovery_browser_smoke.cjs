const { chromium } = require(process.env.ACCOUNTING_PLAYWRIGHT_MODULE || '@playwright/test');
const fs = require('node:fs'),
  path = require('node:path'),
  assert = require('node:assert/strict'),
  { randomUUID } = require('node:crypto');
const root = path.resolve(process.env.ACCOUNTING_RUNTIME_DIR || '.accounting-isolated');
assert.equal(path.basename(root), '.accounting-isolated');
if (!process.env.ACCOUNTING_FIXTURE_LOCKED) {
  const locked = require('node:child_process').spawnSync(
    'flock',
    [root + '/fixture.lock', process.execPath, __filename],
    { stdio: 'inherit', env: { ...process.env, ACCOUNTING_FIXTURE_LOCKED: '1' } },
  );
  process.exit(locked.status ?? 1);
}
const actors = JSON.parse(fs.readFileSync(root + '/clone-access.json')),
  actor = actors.autor;
const fixture = JSON.parse(fs.readFileSync(root + '/auxiliary-fixture.json')),
  data = fixture.ui;
const origin = 'http://contabilidad.localhost:9080',
  phrase = 'Capturas ficticias cifradas 2026',
  stamp = Date.now();
const concept = 'Captura automática incompleta ' + stamp,
  number = 'FV-CAPTURA-' + stamp;
let page,
  contexts = [];
const errors = [],
  checks = [];
async function context(profile) {
  fs.mkdirSync(profile, { recursive: true, mode: 0o700 });
  const c = await chromium.launchPersistentContext(profile, {
    executablePath: '/opt/google/chrome/chrome',
    headless: true,
    acceptDownloads: true,
    viewport: { width: 1600, height: 1050 },
    args: ['--no-sandbox', '--host-resolver-rules=MAP contabilidad.localhost 127.0.0.1'],
  });
  contexts.push(c);
  return c;
}
function observe(p) {
  page = p;
  p.on('pageerror', (e) => errors.push(e.message));
  return p;
}
function headers(person = actor) {
  return {
    Host: 'contabilidad.localhost:9080',
    Accept: 'application/json',
    Authorization: 'Bearer ' + person.token,
    'X-Accounting-Verification': person.verification,
  };
}
async function close(c) {
  await c.close();
  contexts = contexts.filter((x) => x !== c);
}
async function login(p) {
  await p.goto(origin + '/contabilidad/');
  await p.getByLabel('Correo electrónico de PRO9').fill(actor.email);
  await p.getByLabel('Contraseña', { exact: true }).fill(actor.password);
  const auth = p.waitForResponse(
    (r) => r.url().endsWith('/api/login') && r.request().method() === 'POST',
  );
  await p.getByRole('button', { name: 'Entrar', exact: true }).click();
  actor.token = (await (await auth).json()).token;
  await p.getByLabel('Código de verificación', { exact: true }).waitFor();
  const code = actor.recovery_codes.shift();
  assert(code);
  fs.writeFileSync(root + '/clone-access.json', JSON.stringify(actors, null, 2));
  await p.getByLabel('Código de verificación', { exact: true }).fill(code);
  const verified = p.waitForResponse(
    (r) => r.url().endsWith('/auth/mfa/verify') && r.request().method() === 'POST',
  );
  await p.getByRole('button', { name: 'Verificar y continuar' }).click();
  actor.verification = (await (await verified).json()).verification_token;
  fs.writeFileSync(root + '/clone-access.json', JSON.stringify(actors, null, 2));
  await p.getByLabel('Empresa activa', { exact: true }).waitFor();
  await p.getByLabel('Empresa activa', { exact: true }).selectOption(data.company_id);
}
async function stored(p) {
  await p
    .locator('.recovery-status')
    .filter({ hasText: 'Captura cifrada conservada en este equipo' })
    .waitFor();
}
async function resume(p, text) {
  await p.getByRole('heading', { name: 'Recuperar una captura', exact: true }).waitFor();
  await p
    .getByRole('dialog')
    .getByRole('row')
    .filter({ hasText: text })
    .getByRole('button', { name: 'Continuar captura', exact: true })
    .click();
  await p
    .getByRole('heading', { name: 'Recuperar una captura', exact: true })
    .waitFor({ state: 'hidden' });
}
async function unlock(p) {
  await p.goto(origin + '/contabilidad/');
  await p.getByRole('link', { name: 'Abrir copia local', exact: true }).click();
  await p.getByLabel('Frase local', { exact: true }).fill(phrase);
  await p.getByRole('button', { name: 'Desbloquear copia', exact: true }).click();
  await p.getByRole('heading', { name: 'Copia desbloqueada', exact: true }).waitFor();
  await p.getByRole('link', { name: 'Abrir comprobantes', exact: true }).click();
}
async function work(p) {
  await p.getByRole('link', { name: 'Preparar trabajo local', exact: true }).click();
  await p
    .getByRole('heading', { name: 'Capturas conservadas automáticamente', exact: true })
    .waitFor();
}
async function openCapture(p, text) {
  const section = p.locator('section').filter({
    has: p.getByRole('heading', { name: 'Capturas conservadas automáticamente', exact: true }),
  });
  await section
    .getByRole('row')
    .filter({ hasText: text })
    .getByRole('link', { name: 'Continuar captura', exact: true })
    .click();
  await resume(p, text);
}
async function upload(p, file) {
  await p.getByLabel('Archivo de respaldo cifrado', { exact: true }).setInputFiles(file);
  await p.getByLabel('Frase del respaldo', { exact: true }).fill(phrase);
  const prepared = p.waitForResponse(
    (r) => r.url().endsWith('/offline/prepare') && r.request().method() === 'POST',
  );
  await p.getByRole('button', { name: 'Recuperar y conservar borradores', exact: true }).click();
  await prepared;
  await p.waitForFunction(() => document.querySelector('#restore-file')?.disabled === false);
}
(async () => {
  const profile = root + '/editor-recovery-profile-' + stamp;
  let c = await context(profile);
  page = observe(c.pages()[0]);
  const probe = await c.request.get('http://127.0.0.1:9080/api/accounting/v1/health', {
    headers: { Host: 'contabilidad.localhost:9080', Accept: 'application/json' },
  });
  assert.equal(probe.headers()['x-accounting-isolated-test'], '1');
  await login(page);
  const draftInput = {
    accounting_date: data.date,
    concept: 'Base ficticia para conflicto ' + stamp,
    local_id: randomUUID(),
    lines: [
      { account_id: data.chart['1.1.02'].id, debit: '100.00', credit: '0.00' },
      { account_id: data.chart['3.1'].id, debit: '0.00', credit: '100.00' },
    ],
  };
  const created = await c.request.post('http://127.0.0.1:9080' + data.base + '/entries', {
    headers: { ...headers(), 'Idempotency-Key': randomUUID() },
    data: draftInput,
  });
  assert.equal(created.status(), 200);
  const serverDraft = (await created.json()).data;
  assert.equal(serverDraft.version, 1);
  await page.getByRole('link', { name: 'Preparar trabajo local', exact: true }).click();
  await page.getByLabel('Frase local de cifrado', { exact: true }).fill(phrase);
  await page.getByLabel('Repetir frase local', { exact: true }).fill(phrase);
  await page.getByRole('button', { name: 'Preparar copia cifrada', exact: true }).click();
  await page.getByRole('heading', { name: 'Copia desbloqueada', exact: true }).waitFor();
  await page.waitForFunction(() => !!navigator.serviceWorker.controller);
  await page.getByRole('link', { name: 'Comprobantes', exact: true }).click();
  await page.getByRole('link', { name: 'Nuevo comprobante', exact: true }).click();
  await page.getByLabel('Concepto *', { exact: true }).fill(concept);
  await page.getByLabel('Cuenta de línea 1', { exact: true }).selectOption(data.chart['1.1.02'].id);
  await page.getByLabel('Debe de línea 1', { exact: true }).fill('12,');
  await stored(page);
  await page.getByLabel('Empresa activa', { exact: true }).selectOption(fixture.http.company_id);
  await page.getByRole('link', { name: 'Preparar trabajo local', exact: true }).click();
  assert.equal(await page.getByText(concept, { exact: true }).count(), 0);
  assert.equal(
    await page
      .getByRole('heading', { name: 'Capturas conservadas automáticamente', exact: true })
      .count(),
    0,
  );
  await page.getByLabel('Empresa activa', { exact: true }).selectOption(data.company_id);
  await page.getByRole('link', { name: 'Comprobantes', exact: true }).click();
  await page.getByRole('link', { name: 'Nuevo comprobante', exact: true }).click();
  await resume(page, concept);
  assert.equal(await page.getByLabel('Debe de línea 1', { exact: true }).inputValue(), '12,');
  assert.equal(await page.getByLabel('Cuenta de línea 2', { exact: true }).inputValue(), '');
  checks.push('captura incompleta conservada automáticamente y aislada al cambiar de empresa');
  const raw = await page.evaluate(
    () =>
      new Promise((resolve, reject) => {
        const r = indexedDB.open('benahia-accounting-local-v1');
        r.onerror = () => reject(r.error);
        r.onsuccess = () => {
          const db = r.result,
            tx = db.transaction('recoveries', 'readonly'),
            q = tx.objectStore('recoveries').getAll();
          q.onsuccess = () =>
            resolve({
              version: db.version,
              rows: q.result.map((row) => ({
                id: row.id,
                bytes: row.cipher.data.byteLength,
                json: JSON.stringify(row),
              })),
            });
          tx.oncomplete = () => db.close();
        };
      }),
  );
  assert.equal(raw.rows.length, 1);
  assert(raw.rows[0].bytes > 16);
  assert(!raw.rows[0].json.includes(concept));
  checks.push('formulario cifrado en IndexedDB sin concepto ni importes en claro');
  await close(c);
  c = await context(profile);
  await c.setOffline(true);
  page = observe(c.pages()[0]);
  await unlock(page);
  await page.getByRole('link', { name: 'Nuevo comprobante', exact: true }).click();
  await resume(page, concept);
  assert.equal(await page.getByLabel('Concepto *', { exact: true }).inputValue(), concept);
  assert.equal(await page.getByLabel('Debe de línea 1', { exact: true }).inputValue(), '12,');
  checks.push(
    'cierre completo del navegador y reapertura sin red recuperan los campos a medio llenar',
  );
  await page.setViewportSize({ width: 390, height: 844 });
  await page.waitForFunction(
    () => document.querySelector('.sidebar').getBoundingClientRect().right <= 1,
  );
  assert.equal(
    await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth),
    false,
  );
  await page.getByLabel('Haber de línea 2', { exact: true }).scrollIntoViewIfNeeded();
  assert.equal(await page.evaluate(() => window.scrollX), 0);
  assert.equal(await page.locator('.editor-lines').evaluate((el) => el.scrollLeft > 0), true);
  assert.equal(await page.getByLabel('Debe de línea 1', { exact: true }).inputValue(), '12,');
  await page.screenshot({ path: root + '/editor-recovery-form-mobile.png', fullPage: true });
  await page.setViewportSize({ width: 1600, height: 1050 });
  await page.locator('.editor-lines').evaluate((el) => (el.scrollLeft = 0));
  checks.push('el editor móvil desplaza solo las líneas contables sin desbordar la página');
  await page.getByRole('link', { name: 'Documentos', exact: true }).click();
  await page.getByRole('link', { name: 'Nuevo documento', exact: true }).click();
  await page.getByLabel('Número del documento', { exact: true }).fill(number);
  await page.getByLabel('Base del documento', { exact: true }).fill('1,');
  await stored(page);
  await page.getByRole('button', { name: 'Conservar en este equipo', exact: true }).click();
  await page.waitForURL('**/trabajo-local');
  await openCapture(page, number);
  assert.equal(await page.getByLabel('Base del documento', { exact: true }).inputValue(), '1,');
  checks.push('documento manual preparado y conservado sin conexión antes de su revisión fiscal');
  await page.evaluate(() => {
    window.__recoveryPut = IDBObjectStore.prototype.put;
    IDBObjectStore.prototype.put = function (...args) {
      if (this.name === 'recoveries')
        throw new DOMException('Quota exceeded', 'QuotaExceededError');
      return window.__recoveryPut.apply(this, args);
    };
  });
  await page.getByLabel('Número de control fiscal', { exact: true }).fill('CONTROL-CONSERVADO');
  await page
    .getByRole('alert')
    .filter({ hasText: 'No hay espacio para conservar los cambios' })
    .waitFor();
  assert.equal(
    await page
      .locator('.recovery-status')
      .filter({ hasText: 'Captura cifrada conservada' })
      .count(),
    0,
  );
  await page.getByRole('link', { name: 'Documentos', exact: true }).click();
  await page.getByRole('heading', { name: 'Hay cambios sin guardar', exact: true }).waitFor();
  await page.getByRole('button', { name: 'Seguir editando', exact: true }).click();
  assert.equal(
    await page.getByLabel('Número de control fiscal', { exact: true }).inputValue(),
    'CONTROL-CONSERVADO',
  );
  await page.evaluate(() => {
    IDBObjectStore.prototype.put = window.__recoveryPut;
    delete window.__recoveryPut;
  });
  await page.getByLabel('Número de control fiscal', { exact: true }).fill('CONTROL-RECUPERADO');
  await stored(page);
  checks.push('cuota agotada impide anunciar guardado y conserva la edición para reintentar');
  const first = page,
    second = observe(await c.newPage());
  await unlock(second);
  await work(second);
  await openCapture(second, number);
  await first.getByLabel('Número del documento', { exact: true }).fill(number + '-PRIMERO');
  await stored(first);
  await second.getByLabel('Número del documento', { exact: true }).fill(number + '-SEGUNDO');
  await stored(second);
  await second
    .getByText('Otra pestaña cambió la captura. Se conservaron ambas versiones para revisión.', {
      exact: true,
    })
    .waitFor();
  await work(second);
  await second
    .getByRole('row')
    .filter({ hasText: number + '-PRIMERO' })
    .waitFor();
  await second
    .getByRole('row')
    .filter({ hasText: number + '-SEGUNDO' })
    .waitFor();
  checks.push('edición concurrente en dos pestañas conserva ambas alternativas');
  page = second;
  const download = page.waitForEvent('download');
  await page.getByRole('button', { name: /^Descargar copia / }).click();
  const backup = root + '/editor-recovery-backup.json';
  await (await download).saveAs(backup);
  fs.chmodSync(backup, 0o600);
  const backupText = fs.readFileSync(backup, 'utf8'),
    parsed = JSON.parse(backupText);
  assert.equal(parsed.version, 5);
  assert(Array.isArray(parsed.outbox));
  assert(Array.isArray(parsed.selections));
  assert.equal(parsed.recoveries.length, 3);
  assert(
    !backupText.includes(concept) && !backupText.includes(number) && !backupText.includes(phrase),
  );
  checks.push('respaldo cifrado versión cinco incluye capturas, alternativas, decisiones y envíos sin texto privado');
  await close(c);
  const target = await context(root + '/editor-recovery-restore-' + stamp);
  page = observe(target.pages()[0]);
  await login(page);
  await page.getByRole('link', { name: 'Preparar trabajo local', exact: true }).click();
  await upload(page, backup);
  await page
    .getByText('Recuperación completada: 3 borradores conservados.', { exact: true })
    .waitFor();
  await page
    .getByRole('row')
    .filter({ hasText: number + '-PRIMERO' })
    .waitFor();
  await upload(page, backup);
  await page
    .getByText('Recuperación completada: 3 borradores conservados.', { exact: true })
    .waitFor();
  assert.equal(
    await page
      .getByRole('row')
      .filter({ hasText: number + '-PRIMERO' })
      .count(),
    1,
  );
  checks.push('restauración en otro navegador conserva capturas y reimportar no las duplica');
  const versionFourFile = root + '/editor-recovery-v4-backup.json';
  fs.writeFileSync(versionFourFile, JSON.stringify({ ...parsed, version: 4 }), { mode: 0o600 });
  await upload(page, versionFourFile);
  await page.getByText('Recuperación completada: 3 borradores conservados.', { exact: true }).waitFor();
  checks.push('compatibilidad del respaldo versión cuatro con decisiones y capturas conservadas');
  const versionThree = { ...parsed, version: 3 };
  delete versionThree.selections;
  const versionThreeFile = root + '/editor-recovery-v3-backup.json';
  fs.writeFileSync(versionThreeFile, JSON.stringify(versionThree), { mode: 0o600 });
  await upload(page, versionThreeFile);
  await page.getByText('Recuperación completada: 3 borradores conservados.', { exact: true }).waitFor();
  checks.push('compatibilidad del respaldo versión tres con sus envíos conservados');
  const previous = { ...parsed, version: 2 };
  delete previous.outbox;
  delete previous.selections;
  const previousFile = root + '/editor-recovery-v2-backup.json';
  fs.writeFileSync(previousFile, JSON.stringify(previous), { mode: 0o600 });
  await upload(page, previousFile);
  await page.getByText('Recuperación completada: 3 borradores conservados.', { exact: true }).waitFor();
  checks.push('compatibilidad del respaldo versión dos sin una lista de envíos');
  const legacy = { ...parsed, version: 1 };
  delete legacy.recoveries;
  delete legacy.outbox;
  delete legacy.selections;
  const legacyFile = root + '/editor-recovery-legacy-backup.json';
  fs.writeFileSync(legacyFile, JSON.stringify(legacy), { mode: 0o600 });
  await upload(page, legacyFile);
  await page
    .getByText('Recuperación completada: 3 borradores conservados.', { exact: true })
    .waitFor();
  assert.equal(
    await page
      .getByRole('row')
      .filter({ hasText: number + '-SEGUNDO' })
      .count(),
    1,
  );
  checks.push('respaldo versión uno compatible sin borrar capturas nuevas ya presentes');
  const corrupt = JSON.parse(backupText),
    bytes = corrupt.recoveries[0].cipher.data;
  corrupt.recoveries[0].cipher.data = (bytes[0] === 'A' ? 'B' : 'A') + bytes.slice(1);
  const corruptFile = root + '/editor-recovery-corrupt-backup.json';
  fs.writeFileSync(corruptFile, JSON.stringify(corrupt), { mode: 0o600 });
  await upload(page, corruptFile);
  await page.getByRole('alert').filter({ hasText: 'integridad' }).waitFor();
  assert.equal(
    await page
      .getByRole('row')
      .filter({ hasText: number + '-PRIMERO' })
      .count(),
    1,
  );
  checks.push('una captura alterada invalida el respaldo sin cambiar las versiones existentes');
  for (const localPhrase of ['frase local incorrecta de prueba', phrase]) {
    await page.getByLabel('Frase local de cifrado', { exact: true }).fill(localPhrase);
    await page.getByLabel('Repetir frase local', { exact: true }).fill(localPhrase);
    await page.getByRole('button', { name: 'Preparar copia cifrada', exact: true }).click();
    await page.getByRole('button', { name: 'Preparar copia cifrada', exact: true }).waitFor();
    if (localPhrase !== phrase)
      await page.getByRole('alert').filter({ hasText: 'frase local anterior' }).waitFor();
    await page
      .getByRole('row')
      .filter({ hasText: number + '-PRIMERO' })
      .waitFor();
  }
  checks.push('renovar exige la frase anterior y mantiene la clave de las capturas existentes');
  for (const [kind, query] of [
    ['entries', concept],
    ['documents', number],
  ]) {
    const response = await target.request.get(
      'http://127.0.0.1:9080' + data.base + '/' + kind + '?q=' + encodeURIComponent(query),
      { headers: headers() },
    );
    assert.equal(response.status(), 200);
    assert.equal((await response.json()).total, 0);
  }
  checks.push('el autoguardado y su recuperación no crean documentos ni comprobantes en PRO9');
  await page.getByRole('link', { name: 'Comprobantes', exact: true }).click();
  await page.getByLabel('Buscar', { exact: true }).fill(draftInput.concept);
  await page.getByRole('cell', { name: draftInput.concept, exact: true }).click();
  await page
    .locator('.entry-preview')
    .filter({ hasText: draftInput.concept })
    .getByRole('link', { name: 'Ver comprobante', exact: true })
    .click();
  await page.getByRole('link', { name: 'Editar borrador', exact: true }).click();
  await page.getByLabel('Concepto *', { exact: true }).waitFor();
  const localConcept = 'Cambio local para comparar ' + stamp;
  await page.getByLabel('Concepto *', { exact: true }).fill(localConcept);
  await stored(page);
  const reviewer = actors.revisor,
    reviewAuth = await target.request.post('http://127.0.0.1:9080/api/login', {
      headers: { Host: 'contabilidad.localhost:9080', Accept: 'application/json' },
      data: { email: reviewer.email, password: reviewer.password },
    });
  reviewer.token = (await reviewAuth.json()).token;
  const reviewCode = reviewer.recovery_codes.shift();
  assert(reviewCode);
  fs.writeFileSync(root + '/clone-access.json', JSON.stringify(actors, null, 2));
  const verified = await target.request.post(
    'http://127.0.0.1:9080/api/accounting/v1/auth/mfa/verify',
    {
      headers: { ...headers(reviewer), 'X-Accounting-Verification': '' },
      data: { code: reviewCode },
    },
  );
  assert.equal(verified.status(), 200);
  reviewer.verification = (await verified.json()).verification_token;
  fs.writeFileSync(root + '/clone-access.json', JSON.stringify(actors, null, 2));
  const remoteConcept = 'Cambio remoto confirmado ' + stamp,
    remote = await target.request.patch(
      'http://127.0.0.1:9080' + data.base + '/entries/' + serverDraft.id,
      {
        headers: { ...headers(reviewer), 'If-Match': '1' },
        data: { ...draftInput, concept: remoteConcept },
      },
    );
  assert.equal(remote.status(), 200);
  assert.equal((await remote.json()).data.version, 2);
  await work(page);
  await openCapture(page, localConcept);
  const conflict = page.waitForResponse(
    (r) => r.url().endsWith('/entries/' + serverDraft.id) && r.request().method() === 'PATCH',
  );
  await page.getByRole('button', { name: 'Guardar borrador', exact: true }).first().click();
  assert.equal((await conflict).status(), 412);
  await page
    .getByRole('alert')
    .filter({ hasText: 'Otra persona modificó este comprobante' })
    .waitFor();
  assert.equal(await page.getByLabel('Concepto *', { exact: true }).inputValue(), localConcept);
  const unchanged = await target.request.get(
    'http://127.0.0.1:9080' + data.base + '/entries/' + serverDraft.id,
    { headers: headers() },
  );
  assert.equal((await unchanged.json()).data.concept, remoteConcept);
  await page.getByRole('dialog', { name: 'Comparar versiones', exact: true })
    .getByRole('button', { name: 'Volver a editar mi alternativa', exact: true }).click();
  await work(page);
  checks.push(
    'recuperar conserva la versión de partida y rechaza sobrescribir el cambio de otro usuario',
  );
  await openCapture(page, number + '-PRIMERO');
  await page.getByLabel('Base del documento', { exact: true }).fill('100,00');
  await page.getByLabel('IVA', { exact: true }).fill('0');
  await page.getByLabel('Otros tributos', { exact: true }).fill('0');
  await page.getByRole('button', { name: 'Sumar importes', exact: true }).click();
  await stored(page);
  let releaseWrite, writeStarted;
  const holdWrite = new Promise((resolve) => (releaseWrite = resolve)),
    interceptedWrite = new Promise((resolve) => (writeStarted = resolve)),
    writePattern = '**' + data.base + '/documents';
  const holdDocument = async (route) => {
    if (route.request().method() === 'POST') {
      writeStarted();
      await holdWrite;
    }
    await route.continue();
  };
  await page.route(writePattern, holdDocument);
  const documentSaved = page.waitForResponse(
    (r) => r.url().endsWith(data.base + '/documents') && r.request().method() === 'POST',
  );
  await page.getByRole('button', { name: 'Guardar borrador', exact: true }).click();
  await interceptedWrite;
  assert.equal(await page.getByLabel('Número del documento', { exact: true }).isDisabled(), true);
  assert.equal(
    await page.getByRole('button', { name: 'Guardar borrador', exact: true }).isDisabled(),
    true,
  );
  await page.getByLabel('Empresa activa', { exact: true }).selectOption(fixture.http.company_id);
  await page.waitForFunction(
    (id) => document.querySelector('#active-company').value === id,
    data.company_id,
  );
  await page.getByRole('link', { name: 'Documentos', exact: true }).click();
  assert(new URL(page.url()).pathname.endsWith('/documentos/nuevo'));
  releaseWrite();
  const documentResponse = await documentSaved;
  assert.equal(documentResponse.status(), 200);
  const confirmedDocument = (await documentResponse.json()).data;
  assert.equal(confirmedDocument.number, number + '-PRIMERO');
  assert.equal(confirmedDocument.amounts.total, '100.000000000000');
  await page.waitForURL('**/documentos/' + confirmedDocument.id);
  await page.unroute(writePattern, holdDocument);
  await work(page);
  assert.equal(
    await page
      .getByRole('row')
      .filter({ hasText: number + '-PRIMERO' })
      .count(),
    0,
  );
  assert.equal(
    await page
      .getByRole('row')
      .filter({ hasText: number + '-SEGUNDO' })
      .count(),
    1,
  );
  await page.getByRole('row').filter({ hasText: localConcept }).waitFor();
  checks.push('el guardado confirmado bloquea cambios durante el envío y retira solo su captura');
  const notice = page.getByRole('button', { name: 'Cerrar aviso', exact: true });
  if (await notice.isVisible()) await notice.click();
  await page.screenshot({ path: root + '/editor-recovery-desktop.png', fullPage: true });
  await page.setViewportSize({ width: 390, height: 844 });
  await page.waitForFunction(
    () => document.querySelector('.sidebar').getBoundingClientRect().right <= 1,
  );
  assert.equal(
    await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth),
    false,
  );
  await page.screenshot({ path: root + '/editor-recovery-mobile.png', fullPage: true });
  assert.deepEqual(errors, []);
  checks.push('capturas y restauración adaptadas a móvil sin errores de ejecución');
  const result = { passed: checks.length, checks, pageErrors: errors };
  fs.writeFileSync(root + '/editor-recovery-browser-tests.json', JSON.stringify(result, null, 2));
  console.log(JSON.stringify(result, null, 2));
  for (const ctx of [...contexts]) await close(ctx);
})().catch(async (e) => {
  if (page && !page.isClosed()) {
    await page.screenshot({ path: root + '/editor-recovery-failure.png', fullPage: true });
    console.error(
      JSON.stringify({
        url: page.url(),
        alerts: await page.getByRole('alert').allTextContents(),
        pageErrors: errors,
      }),
    );
  }
  console.error(e.stack);
  for (const ctx of contexts) {
    try {
      await ctx.close();
    } catch {}
  }
  process.exit(1);
});
