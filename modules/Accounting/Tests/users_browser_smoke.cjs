const { chromium } = require(process.env.ACCOUNTING_PLAYWRIGHT_MODULE || '@playwright/test');
const fs = require('node:fs'),
  assert = require('node:assert/strict');
const root = require('node:path').resolve(
  process.env.ACCOUNTING_RUNTIME_DIR || '.accounting-isolated',
);
assert.equal(require('node:path').basename(root), '.accounting-isolated');
if (!process.env.ACCOUNTING_FIXTURE_LOCKED) {
  const locked = require('node:child_process').spawnSync(
    'flock',
    [root + '/fixture.lock', process.execPath, __filename],
    { stdio: 'inherit', env: { ...process.env, ACCOUNTING_FIXTURE_LOCKED: '1' } },
  );
  process.exit(locked.status ?? 1);
}
const users = JSON.parse(fs.readFileSync(root + '/clone-access.json')),
  actor = users.revisor;
let page, browser;
(async () => {
  browser = await chromium.launch({
    executablePath: '/opt/google/chrome/chrome',
    headless: true,
    args: ['--no-sandbox', '--host-resolver-rules=MAP contabilidad.localhost 127.0.0.1'],
  });
  const context = await browser.newContext({ viewport: { width: 1600, height: 1050 } });
  const probe = await context.request.get('http://127.0.0.1:9080/api/accounting/v1/health', {
    headers: { Host: 'contabilidad.localhost:9080', Accept: 'application/json' },
  });
  assert.equal(probe.headers()['x-accounting-isolated-test'], '1');
  page = await context.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await page.goto('http://contabilidad.localhost:9080/contabilidad/');
  await page.getByLabel('Correo electrónico de PRO9').fill(actor.email);
  await page.getByLabel('Contraseña', { exact: true }).fill(actor.password);
  const loginResponse = page.waitForResponse(
    (r) => r.url().endsWith('/api/login') && r.request().method() === 'POST',
  );
  await page.getByRole('button', { name: 'Entrar', exact: true }).click();
  actor.token = (await (await loginResponse).json()).token;
  await page.getByLabel('Código de verificación', { exact: true }).waitFor();
  const code = actor.recovery_codes.shift();
  assert(code);
  fs.writeFileSync(root + '/clone-access.json', JSON.stringify(users, null, 2));
  await page.getByLabel('Código de verificación', { exact: true }).fill(code);
  const verified = page.waitForResponse(
    (r) => r.url().endsWith('/auth/mfa/verify') && r.request().method() === 'POST',
  );
  await page.getByRole('button', { name: 'Verificar y continuar' }).click();
  actor.verification = (await (await verified).json()).verification_token;
  fs.writeFileSync(root + '/clone-access.json', JSON.stringify(users, null, 2));
  await page.getByLabel('Empresa activa', { exact: true }).waitFor();
  await page.getByRole('link', { name: 'Empresas', exact: true }).click();
  await page.getByRole('button', { name: 'Crear empresa', exact: true }).first().waitFor();
  await page.getByRole('button', { name: 'Crear empresa', exact: true }).first().click();
  const name = 'Empresa UI de permisos ' + Date.now();
  await page.getByLabel('Razón social *').fill(name);
  await page
    .getByLabel('RIF *', { exact: true })
    .fill('J-' + String(Date.now() % 100000000).padStart(8, '0') + '-0');
  await page
    .getByRole('dialog')
    .getByRole('button', { name: 'Crear empresa', exact: true })
    .click();
  const row = page.getByRole('row').filter({ hasText: name });
  await row.getByRole('button', { name: 'Abrir empresa', exact: true }).click();
  await page.getByRole('heading', { name: 'Tu contabilidad, en orden' }).waitFor();
  assert.equal(await page.getByRole('link', { name: 'Nuevo comprobante', exact: true }).count(), 0);
  await page.getByRole('link', { name: 'Usuarios y permisos', exact: true }).click();
  await page.getByRole('heading', { name: 'Usuarios y permisos', exact: true }).waitFor();
  const self = page.getByRole('row').filter({ hasText: actor.email });
  await self.getByRole('button', { name: 'Editar permisos' }).click();
  await page.getByRole('checkbox', { name: 'Contador', exact: false }).check();
  await page
    .getByLabel('Motivo del cambio *')
    .fill('Asignación expresa de la responsabilidad contable para el piloto aislado.');
  await page.getByRole('button', { name: 'Guardar permisos', exact: true }).click();
  await page.getByRole('dialog').waitFor({ state: 'hidden' });
  await self.getByText('Contador', { exact: false }).waitFor();
  await page.screenshot({ path: root + '/users-desktop.png', fullPage: true });
  await page.getByRole('link', { name: 'Inicio', exact: true }).click();
  await page.getByRole('link', { name: 'Nuevo comprobante', exact: true }).waitFor();
  assert.deepEqual(errors, []);
  const result = {
    passed: 4,
    checks: [
      'Creación de empresa con catálogos',
      'Administrador sin acción de contabilizar implícita',
      'Asignación de contador con motivo en interfaz',
      'Permisos actualizados sin nueva sesión',
    ],
    pageErrors: errors,
  };
  fs.writeFileSync(root + '/users-ui-tests.json', JSON.stringify(result, null, 2));
  console.log(JSON.stringify(result));
  await browser.close();
})().catch(async (e) => {
  if (page) {
    await page.screenshot({ path: root + '/users-ui-failure.png', fullPage: true });
    console.error(
      JSON.stringify({ url: page.url(), alerts: await page.getByRole('alert').allTextContents() }),
    );
  }
  console.error(e.stack);
  if (browser) await browser.close();
  process.exit(1);
});
