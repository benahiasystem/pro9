/* Live, reversible integration check. Requires Playwright Core and a running local SAB. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { createRequire } = require('node:module');
const { WebSocket } = require('ws');
const qaRequire = createRequire(process.env.PLAYWRIGHT_PACKAGE || '/tmp/sab-centrifugo-qa/package.json');
const { chromium } = qaRequire('playwright-core');
const output = path.resolve(process.env.SAB_QA_OUTPUT || 'informes/evidencias/centrifugo_2026_11_11');
const hostname = process.env.SAB_QA_HOST || 'bbc.localhost';
assert.match(hostname, /^[a-z0-9.-]+$/i);
const baseUrl = `http://${hostname}`;
const manifest = JSON.parse(fs.readFileSync('public/mozo/realtime-manifest.json', 'utf8'));
const results = {};
let stage = 'initialization';

function php(code) {
    const bootstrap = `<?php require 'vendor/autoload.php'; $app = require 'bootstrap/app.php';
        $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
        $host = Hyn\\Tenancy\\Models\\Hostname::with('website')->where('fqdn', ${JSON.stringify(hostname)})->firstOrFail();
        $env = app(Hyn\\Tenancy\\Environment::class); $env->hostname($host); $env->tenant($host->website);
        $app->instance(Hyn\\Tenancy\\Contracts\\CurrentHostname::class, $host);
    `;
    return JSON.parse(execFileSync('docker', ['exec', '-i', 'sab-php', 'php'], {
        input: bootstrap + code, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'],
    }));
}

async function protocol(token) {
    const socket = new WebSocket('ws://127.0.0.1/connection/websocket', {
        headers: { Host: 'ws.localhost', Origin: baseUrl },
    });
    const waiters = new Map();
    let id = 0;
    socket.on('message', raw => {
        for (const line of raw.toString().split('\n')) {
            const message = JSON.parse(line);
            if (!Object.keys(message).length) { socket.send('{}'); continue; }
            const waiter = waiters.get(message.id);
            if (waiter) { waiters.delete(message.id); waiter(message); }
        }
    });
    socket.on('close', (code, reason) => {
        for (const resolve of waiters.values()) resolve({ error: { code, message: reason.toString() } });
        waiters.clear();
    });
    await new Promise((resolve, reject) => { socket.once('open', resolve); socket.once('error', reject); });
    async function rpc(command) {
        const current = ++id;
        return await new Promise((resolve, reject) => {
            const timeout = setTimeout(() => reject(new Error('WebSocket RPC timeout')), 8000);
            waiters.set(current, value => { clearTimeout(timeout); resolve(value); });
            socket.send(JSON.stringify({ id: current, ...command }));
        });
    }
    const connected = await rpc({ connect: token ? { token } : {} });
    return { socket, rpc, connected };
}

async function main() {
    fs.mkdirSync(output, { recursive: true });
    // Secrets stay in memory; do not log fixture, HTTP bodies or WS connect frames.
    const fixture = php(`
        $user = App\\Models\\Tenant\\User::where('type', 'admin')->where('locked', false)->firstOrFail();
        if (!$user->api_token) throw new RuntimeException('An existing authenticated test user is required.');
        $tokens = app(App\\Services\\CentrifugoTokenService::class);
        $other = $tokens->issue($user, 'qa-other.invalid', (int)$host->website_id + 1000000);
        config(['centrifugo.token_ttl' => 3]);
        $short = $tokens->issue($user, $host->fqdn, (int)$host->website_id);
        config(['centrifugo.token_ttl' => -30]);
        $expired = $tokens->issue($user, $host->fqdn, (int)$host->website_id);
        echo json_encode(['api_token'=>$user->api_token, 'id'=>$user->id,
          'establishment_id'=>$user->establishment_id, 'other'=>$other, 'short'=>$short, 'expired'=>$expired]);
    `);
    const headers = { Authorization: `Bearer ${fixture.api_token}`, Accept: 'application/json' };
    const guest = await fetch(`${baseUrl}/api/restaurant/realtime/token`, { headers: { Accept: 'application/json' } });
    assert.equal(guest.status, 401); results.guest_http = guest.status;
    const response = await fetch(`${baseUrl}/api/restaurant/realtime/token?channel=restaurant:qa-other.invalid`, { headers });
    assert.equal(response.status, 200);
    assert.match(response.headers.get('cache-control'), /no-store/);
    const auth = await response.json();
    assert.equal(auth.channel, `restaurant:${hostname}`);
    results.authenticated_http = response.status;
    results.channel_cannot_be_overridden = true;

    stage = 'anonymous connection';
    const noToken = await protocol();
    assert.ok(noToken.connected.error); noToken.socket.close();
    results.anonymous_denied = true;
    stage = 'expired token';
    const expired = await protocol(fixture.expired.token);
    assert.ok(expired.connected.error); expired.socket.close();
    results.expired_jwt_denied = true;
    stage = 'tampered token';
    const tampered = await protocol(auth.token.slice(0, -4) + 'AAAA');
    assert.ok(tampered.connected.error); tampered.socket.close();
    results.tampered_jwt_denied = true;
    stage = 'valid connection';
    const current = await protocol(auth.token);
    assert.ok(current.connected.connect);
    stage = 'valid subscription';
    const ok = await current.rpc({ subscribe: { channel: auth.channel, token: auth.subscription_token } });
    assert.ok(ok.subscribe);
    stage = 'missing subscription token';
    const noSubToken = await current.rpc({ subscribe: { channel: 'restaurant:unauthorized.invalid' } });
    assert.ok(noSubToken.error);
    stage = 'cross tenant subscription';
    const crossTenant = await current.rpc({ subscribe: { channel: fixture.other.channel, token: fixture.other.subscription_token } });
    assert.ok(crossTenant.error);
    current.socket.close();
    stage = 'wrong channel token';
    const mismatch = await protocol(auth.token);
    assert.ok(mismatch.connected.connect);
    const wrongChannel = await mismatch.rpc({ subscribe: { channel: fixture.other.channel, token: auth.subscription_token } });
    assert.ok(wrongChannel.error);
    mismatch.socket.close();
    stage = 'client publication';
    const publisher = await protocol(auth.token);
    assert.ok(publisher.connected.connect);
    await publisher.rpc({ subscribe: { channel: auth.channel, token: auth.subscription_token } });
    const publish = await publisher.rpc({ publish: { channel: auth.channel, data: { event: 'qa-forbidden' } } });
    assert.ok(publish.error);
    publisher.socket.close();
    results.valid_subscription = true;
    results.cross_company_same_local_user_denied = true;
    results.subscription_without_token_denied = true;
    results.client_publish_denied = true;
    const privateApi = await fetch('http://ws.localhost/api/publish', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' });
    assert.equal(privateApi.status, 404); results.public_http_api_blocked = true;

    stage = 'browser launch';
    const browser = await chromium.launch({
        executablePath: process.env.CHROMIUM_PATH || '/home/benahia/.cache/ms-playwright/chromium-1208/chrome-linux64/chrome',
        headless: true, args: ['--no-sandbox'],
    });
    let tableId;
    let marker;
    let page;
    try {
        const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, serviceWorkers: 'block' });
        await context.addInitScript(data => {
            for (const [key, value] of Object.entries(data)) localStorage.setItem(key, String(value));
        }, { token: fixture.api_token, url: hostname, ssl: 'http://', userRole: 'ADM',
            name: 'Verificación tiempo real', establishmentId: fixture.establishment_id,
            sellerId: fixture.id, isBlockedPin: 0 });
        page = await context.newPage();
        const pageErrors = [];
        page.on('pageerror', error => pageErrors.push(error.message.replace(/[A-Za-z0-9_-]{40,}/g, '[redacted]')));
        stage = 'Mozo page connection';
        await page.goto(`${baseUrl}/mozo/app/mesas`, { waitUntil: 'networkidle', timeout: 30000 });
        await page.waitForFunction(async original => (await import(original)).c.value === 'connected', manifest.original, { timeout: 15000 });
        results.mozo_connected = true;
        const before = await page.evaluate(async original => {
            const rt = await import(original);
            const master = await import('/mozo/assets/masterService.e2218f63.js');
            return { state: rt.c.value, tables: master.u().getMesas2().length };
        }, manifest.original);
        results.before = before;
        marker = `QA-RT-${Date.now()}`;
        stage = 'real table update';
        const environments = await (await fetch(`${baseUrl}/api/restaurant/tablesAndEnv`, { headers })).json();
        const environment = environments.environments.find(env => env.active)?.name || 'Ambiente 1';
        const created = await fetch(`${baseUrl}/api/restaurant/table`, {
            method: 'POST', headers: { ...headers, 'Content-Type': 'application/json' },
            body: JSON.stringify({ label: marker, environment, order_status: 'pending', status: 'available', products: [], personas: 1 }),
        });
        assert.equal(created.status, 200);
        const createdData = await created.json();
        assert.equal(createdData.success, true); tableId = createdData.data.id;
        await page.waitForFunction(async ({ label }) => {
            const master = await import('/mozo/assets/masterService.e2218f63.js');
            return master.u().getMesas2().some(table => table.label === label);
        }, { label: marker }, { timeout: 10000 });
        results.real_table_controller_event_received = true;
        await page.getByText(marker, { exact: true }).first().waitFor({ state: 'visible', timeout: 10000 });
        results.table_change_visible_in_mozo = true;
        stage = 'command and stock events';
        // Corrupt only the QA browser's in-memory stores, then publish the genuine DB snapshots.
        const stock = await (await fetch(`${baseUrl}/api/restaurant/items/stock`, { headers })).json();
        assert.ok(stock.data.length);
        const first = stock.data[0];
        await page.evaluate(async ({ original, itemId }) => {
            const rt = await import(original);
            const master = await import('/mozo/assets/masterService.e2218f63.js');
            master.a().stockMap[itemId] = -999;
            rt.u().applyCommandData({ productsStatusReceived: [{ id: -999, mesa_id: 0 }] });
        }, { original: manifest.original, itemId: first.item_id });
        const snapshots = php(`
            $stock=app(Modules\\Restaurant\\Http\\Controllers\\RestaurantController::class)->getStockStatus()['data'];
            $command=app(Modules\\Restaurant\\Http\\Controllers\\RestaurantItemOrderStatusController::class)->getStatusItems(0)['data'];
            foreach(['stock-updated'=>$stock, 'command-items-updated'=>$command] as $event=>$payload)
                app(App\\Services\\CentrifugoService::class)->publish('restaurant:'.$host->fqdn, compact('event','payload'));
            echo json_encode(['received_count'=>count($command['productsStatusReceived'])]);
        `);
        await page.waitForFunction(async ({ original, itemId, available, count }) => {
            const rt = await import(original);
            const master = await import('/mozo/assets/masterService.e2218f63.js');
            return master.a().getAvailable(itemId) === Number(available)
                && rt.u().productsStatusReceived.length === count
                && !rt.u().productsStatusReceived.some(item => item.id === -999);
        }, { original: manifest.original, itemId: first.item_id, available: first.available, count: snapshots.received_count });
        results.stock_event_applied = true;
        results.command_event_applied = true;

        stage = 'reconnection after service restart';
        let socketCount = 0;
        page.on('websocket', () => socketCount++);
        await page.evaluate(async () => (await import('/mozo/assets/masterService.e2218f63.js')).u().setDataMesas([]));
        execFileSync('docker', ['restart', 'sab-centrifugo'], { stdio: 'pipe' });
        const reconnectDeadline = Date.now() + 20000;
        while (socketCount < 1 && Date.now() < reconnectDeadline) await new Promise(resolve => setTimeout(resolve, 200));
        assert.ok(socketCount >= 1);
        await page.waitForFunction(async original => (await import(original)).c.value === 'connected', manifest.original, { timeout: 20000 });
        // A server restart must have opened a new transport and resynchronized the real table.
        await page.waitForFunction(async label => (await import('/mozo/assets/masterService.e2218f63.js')).u().getMesas2().some(t => t.label === label), marker);
        await page.getByText(marker, { exact: true }).first().waitFor({ state: 'visible', timeout: 10000 });
        results.reconnect_and_resync_after_server_restart = true;
        await page.screenshot({ path: path.join(output, 'mozo-conectado.png'), fullPage: true });
        results.page_errors = pageErrors;
        assert.deepEqual(pageErrors, []);
        // Logout must close the live connection, including reactive store changes.
        await page.evaluate(async entry => { (await import(entry)).u().logoutUser(); }, manifest.entry);
        await page.waitForFunction(async original => (await import(original)).c.value === 'disconnected', manifest.original);
        results.logout_disconnects = true;
        await context.close();
        stage = 'JWT renewal in browser';
        const renewedContext = await browser.newContext({ serviceWorkers: 'block' });
        await renewedContext.addInitScript(data => {
            for (const [key, value] of Object.entries(data)) localStorage.setItem(key, String(value));
        }, { token: fixture.api_token, url: hostname, ssl: 'http://', userRole: 'ADM', name: 'Verificación renovación', establishmentId: fixture.establishment_id });
        let requests = 0;
        const refreshCommands = { connection: 0, subscription: 0 };
        await renewedContext.route('**/api/restaurant/realtime/token', async route => {
            requests++;
            if (requests === 1) {
                // Sign immediately before the first connect, so the short JWT cannot expire during page load.
                const short = php(`config(['centrifugo.token_ttl'=>5]); $user=App\\Models\\Tenant\\User::findOrFail(${Number(fixture.id)});
                    echo json_encode(app(App\\Services\\CentrifugoTokenService::class)->issue($user, $host->fqdn, (int)$host->website_id));`);
                await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(short) });
            }
            else await route.continue();
        });
        const renewedPage = await renewedContext.newPage();
        renewedPage.on('websocket', socket => socket.on('framesent', ({ payload }) => {
            for (const line of String(payload).split('\n')) {
                try {
                    const command = JSON.parse(line);
                    if (command.refresh) refreshCommands.connection++;
                    if (command.sub_refresh) refreshCommands.subscription++;
                } catch {}
            }
        }));
        await renewedPage.goto(`${baseUrl}/mozo/app/mesas`, { waitUntil: 'networkidle' });
        await renewedPage.waitForFunction(async original => (await import(original)).c.value === 'connected', manifest.original);
        const deadline = Date.now() + 15000;
        while ((!refreshCommands.connection || !refreshCommands.subscription || requests < 2) && Date.now() < deadline) await new Promise(resolve => setTimeout(resolve, 200));
        assert.ok(requests >= 2 && refreshCommands.connection >= 1 && refreshCommands.subscription >= 1,
            `Renewal: requests=${requests}, connection=${refreshCommands.connection}, subscription=${refreshCommands.subscription}`);
        assert.equal(await renewedPage.evaluate(async original => (await import(original)).c.value, manifest.original), 'connected');
        results.expiring_jwt_renewed_by_real_authenticated_endpoint = true;
        await renewedContext.close();
    } catch (error) {
        if (page && !page.isClosed()) await page.screenshot({ path: path.join(output, 'failure.png'), fullPage: true }).catch(() => {});
        throw error;
    } finally {
        if (marker) {
            php(`Modules\\Restaurant\\Models\\RestaurantTable::where('label', ${JSON.stringify(marker)})->delete();
                $snapshot=app(Modules\\Restaurant\\Http\\Controllers\\RestaurantConfigurationController::class)->tablesAndEnv();
                app(App\\Services\\CentrifugoService::class)->publish('restaurant:'.$host->fqdn, ['event'=>'tables-env-updated','payload'=>$snapshot]);
                echo json_encode(['removed'=>true]);`);
            results.temporary_table_removed = true;
        }
        await browser.close();
    }
    fs.writeFileSync(path.join(output, 'verification.json'), JSON.stringify(results, null, 2) + '\n');
    console.log(JSON.stringify(results, null, 2));
}
main().catch(error => { console.error(stage + ': ' + error.message.replace(/[A-Za-z0-9_-]{40,}/g, '[redacted]')); process.exitCode = 1; });
