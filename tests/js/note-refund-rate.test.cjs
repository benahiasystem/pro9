const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const loadModule = require('./helpers/load-module.cjs');

// ######## INICIO TASA DEL DIA EN DEVOLUCIONES ########
const filename = path.resolve(__dirname, '../../resources/js/views/tenant/documents/partials/note-operations.vue');
const parsed = compiler.parseComponent(fs.readFileSync(filename, 'utf8'));
const exchangeRate = loadModule(path.resolve(__dirname, '../../resources/js/mixins/functions.js')).exchangeRate;
const sandbox = {module: {exports: {}}, exchangeRate, RetentionForm: {}, noteRequestId: () => 'refund-key',
    moment: () => ({format: () => '2026-10-21'})};
vm.runInNewContext(parsed.script.content.replace(/^import .*;$/gm, '').replace('export default', 'module.exports ='), sandbox);
const component = sandbox.module.exports;

function context(get) {
    const ctx = {...component.data(), showDialog: true, documentId: 7, refunds: {can_create: true, currency_type_id: 'VES'},
        $http: {get}, $eventHub: {$emit() {}}, $emit() {}};
    for (const [name, fn] of Object.entries({...exchangeRate.methods, ...component.methods})) ctx[name] = fn.bind(ctx);
    return ctx;
}

test('opening the refund loads the current rate with all eight decimal places', async () => {
    const requests = [];
    const ctx = context(async url => {
        requests.push(url);
        if (url === '/documents/7/refunds') return {data: {refunds: {can_create: true, currency_type_id: 'VES'}, adjustments: {}, invoice_id: 1}};
        if (url === '/document_payments/tables') return {data: {}};
        return {data: {sale: '883.39630001'}};
    });
    await ctx.load();
    assert.ok(requests.includes('/services/exchange/2026-10-21'));
    assert.equal(ctx.refundForm.exchange_rate, '883.39630001');
    assert.equal(ctx.refundForm.exchange_rate_source, 'BCV');
    assert.equal(ctx.loadingRefundRate, false);
    assert.equal(ctx.busy, false);
});

test('changing the refund date uses its rate and ignores an older response', async () => {
    const pending = {};
    const ctx = context(url => new Promise(resolve => {pending[url] = resolve;}));
    const first = ctx.resetForms();
    ctx.refundForm.date_of_payment = '2026-10-20';
    const second = ctx.loadRefundRate();
    pending['/services/exchange/2026-10-20']({data: {sale: '880.12345678'}});
    await second;
    pending['/services/exchange/2026-10-21']({data: {sale: '883.39630001'}});
    await first;
    assert.equal(ctx.refundForm.exchange_rate, '880.12345678');
});

test('a failed rate lookup leaves manual entry available and explains the error', async () => {
    const ctx = context(async () => {throw new Error('Unavailable');});
    await ctx.resetForms();
    assert.equal(ctx.refundForm.exchange_rate, '');
    assert.equal(ctx.refundForm.exchange_rate_source, 'manual');
    assert.match(ctx.error, /introducirla manualmente/);
    assert.equal(ctx.loadingRefundRate, false);
});

test('a response for another note cannot overwrite the current refund', async () => {
    let resolve;
    const ctx = context(() => new Promise(done => {resolve = done;}));
    const request = ctx.resetForms();
    ctx.sequence++;
    ctx.refundForm = {exchange_rate: '900.00000000'};
    resolve({data: {sale: '883.39630001'}});
    await request;
    assert.equal(ctx.refundForm.exchange_rate, '900.00000000');
});

test('refund submission is blocked until the rate lookup completes', async () => {
    const ctx = context(async () => ({data: {sale: '883.39630001'}}));
    ctx.loadingRefundRate = true;
    ctx.mutate = () => assert.fail('Must not register while loading the rate');
    await ctx.refund();
});

test('reopening an unfinished refund retries a missing rate without discarding its data', async () => {
    const ctx = context(async url => {
        if (url === '/documents/7/refunds') return {data: {refunds: {can_create: true, currency_type_id: 'VES'}, adjustments: {}, invoice_id: 1}};
        if (url === '/document_payments/tables') return {data: {}};
        return {data: {sale: '883.39630001'}};
    });
    ctx.refundForm = {operation_key: 'existing-key', date_of_payment: '2026-10-21', exchange_rate: '', original_amount: '500'};
    await ctx.load();
    assert.equal(ctx.refundForm.exchange_rate, '883.39630001');
    assert.equal(ctx.refundForm.operation_key, 'existing-key');
    assert.equal(ctx.refundForm.original_amount, '500');
});

test('refund template compiles', () => {
    assert.deepEqual(compiler.compile(parsed.template.content).errors, []);
});
// ######## FIN TASA DEL DIA EN DEVOLUCIONES ########
