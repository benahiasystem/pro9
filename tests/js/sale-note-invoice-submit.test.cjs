const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const babel = require('@babel/core');
const compiler = require('vue-template-compiler');

// ######## INICIO MONEDA VENEZUELA HOTEL ########
function loadScript(source, resolveImport = require) {
    const exports = {};
    const code = babel.transformSync(source, {
        configFile: false, babelrc: false,
        plugins: ['@babel/plugin-transform-modules-commonjs'],
    }).code;
    vm.runInNewContext(code, {exports, require: resolveImport, $: Object.assign(() => [], {each() {}})});
    return exports;
}

const filename = path.join(__dirname, '../../resources/js/views/tenant/sale_notes/partials/option_documents.vue');
const parsed = compiler.parseComponent(fs.readFileSync(filename, 'utf8'));
const component = loadScript(parsed.script.content, name => {
    if (name.endsWith('.vue')) return {};
    if (name === '@mixins/functions') return {fnRestrictSaleItemsCpe: {}};
    if (name === '@mixins/fiscal-payment-settings') return {};
    if (name.startsWith('.')) {
        return require('./helpers/load-module.cjs')(path.resolve(path.dirname(filename), name + '.js'));
    }
    return require(name);
}).default;

function conversion(currency, rate, fetchedRate) {
    const requests = [];
    const errors = [];
    const ctx = {
        ...component.data(),
        document: {
            document_type_id: '01', series_id: 1, sale_note_id: 9,
            date_of_issue: '2026-10-01', currency_type_id: currency,
            exchange_rate_sale: rate, items: [{item: {lots_enabled: false}}],
            payments: [{payment_destination_id: 'cash'}], fee: [],
        },
        form: {id: 9},
        fnValidateRestrictSaleItemsCpe: () => ({success: true}),
        ensureFiscalPaymentsReady: async () => true,
        $message: {error: message => errors.push(message)},
        $eventHub: {$emit() {}}, $emit() {},
        $http: {
            get: async url => {
                requests.push({method: 'get', url});
                return {data: {sale: fetchedRate}};
            },
            post: async (url, document) => {
                requests.push({method: 'post', url, document: {...document}});
                return {data: {success: true, data: {id: 10}}};
            },
        },
    };
    for (const [name, method] of Object.entries(component.methods)) ctx[name] = method.bind(ctx);
    ctx.resetDocument = () => {};
    return {ctx, requests, errors};
}

for (const currency of ['VES', 'USD']) {
    test(`sale note conversion submits a ${currency} invoice with its recorded exchange rate`, async () => {
        const {ctx, requests, errors} = conversion(currency, 40);
        await ctx.submit();
        assert.deepEqual(errors, []);
        const post = requests.find(request => request.method === 'post');
        assert.ok(post, 'Conversion must reach the document endpoint');
        assert.equal(post.url, '/documents');
        assert.equal(post.document.exchange_rate_sale, '40.00000000');
        assert.equal(post.document.sale_note_id, 9);
        assert.equal(post.document.currency_type_id, currency);
        assert.equal(ctx.documentNewId, 10);
        assert.equal(ctx.loading_submit, false);
        assert.ok(!requests.some(request => request.url.startsWith('/services/exchange/')));
    });
}

test('conversion obtains a missing exchange rate and sends it without replacing it', async () => {
    const {ctx, requests, errors} = conversion('USD', 0, '42.50');
    await ctx.submit();
    assert.deepEqual(errors, []);
    assert.equal(requests[0].url, '/services/exchange/2026-10-01');
    assert.equal(requests.find(request => request.method === 'post').document.exchange_rate_sale, '42.50000000');
});

test('invalid exchange rate blocks conversion before saving or marking the note converted', async () => {
    const {ctx, requests, errors} = conversion('VES', 0, 0);
    await ctx.submit();
    assert.equal(errors.length, 1);
    assert.match(errors[0], /Tasa fuera de rango/);
    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, '/services/exchange/2026-10-01');
    assert.equal(ctx.documentNewId, null);
    assert.equal(ctx.loading_submit, false);
});

test('recorded receipts are shown read-only and newly added receipts expose currency and fiscal fields', () => {
    assert.deepEqual(compiler.compile(parsed.template.content).errors, []);
    assert.match(parsed.template.content, /Cobro registrado:/);
    assert.match(parsed.template.content, /row\.original_amount/);
    assert.match(parsed.template.content, /row\.exchange_rate/);
    assert.match(parsed.template.content, /<igtf-payment-fields/);
    assert.match(parsed.template.content, /:disabled="isRecordedReceipt\(row\)"/);
    const {ctx} = conversion('VES', 40);
    ctx.document.payments = [{id: 5, payment_destination_id: null}];
    assert.equal(ctx.validatePaymentDestination().error_by_item, 0);
    ctx.document.payments = [{source_sale_note_payment_id: 5, payment_destination_id: null}];
    assert.equal(ctx.validatePaymentDestination().error_by_item, 0);
    ctx.document.payments.push({id: null, payment_destination_id: null});
    assert.equal(ctx.validatePaymentDestination().error_by_item, 1);
});
// ######## FIN MONEDA VENEZUELA HOTEL ########

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
test('sale note conversion preserves recorded destinations absent from the active catalog', () => {
    const {ctx} = conversion('VES', 40);
    const historical = {id: 7, sale_note_id: 9, payment_destination_id: 45, currency_type_id: 'USD', payment: '100'};
    const sourceOnly = {id: null, source_sale_note_payment_id: 8, payment_destination_id: 46};
    const quotationSource = {id: null, source_quotation_payment_id: 9, payment_destination_id: 49};
    const draft = {id: null, payment_destination_id: 47};
    const availableDraft = {id: null, payment_destination_id: '48'};
    ctx.form.payments = [historical, sourceOnly, draft, availableDraft, quotationSource];
    ctx.payment_destinations = [{id: 'cash'}, {id: 48}];
    const result = ctx.getPaymentsData({payments: []});
    assert.equal(result[0].payment_destination_id, 45);
    assert.equal(result[1].payment_destination_id, 46);
    assert.equal(result[2].payment_destination_id, null);
    assert.equal(result[3].payment_destination_id, '48');
    assert.equal(result[4].payment_destination_id, 49);
    assert.equal(draft.payment_destination_id, 47);
    assert.notEqual(result[0], historical);
});

test('sale note conversion cannot remove recorded receipts', () => {
    const {ctx} = conversion('VES', 40);
    const recorded = {id: 7, payment: '50'}, draft = {id: null, payment: '10'};
    ctx.document.payments = [recorded, draft];
    ctx.clickCancel(0);
    assert.deepEqual(ctx.document.payments, [recorded, draft]);
    ctx.clickCancel(1);
    assert.deepEqual(ctx.document.payments, [recorded]);
    assert.match(parsed.template.content, /v-if="!isRecordedReceipt\(row\)"[\s\S]*?@click\.prevent="clickCancel\(index\)"/);
    const originOnly = {source_sale_note_payment_id: 8, payment: '12'};
    ctx.document.payments = [originOnly];
    ctx.clickCancel(0);
    assert.deepEqual(ctx.document.payments, [originOnly]);
});

test('sale note conversion preserves receipts when changing to credit and suggests the outstanding balance', () => {
    const {ctx} = conversion('VES', 40);
    const receipt = {id: 4, payment: '25', currency_type_id: 'USD'};
    ctx.form.sale_note = {fee: []};
    ctx.document.total = '100'; ctx.document.payments = [receipt];
    ctx.document.payment_condition_id = '02';
    ctx.changePaymentCondition();
    assert.equal(ctx.document.payments[0], receipt);
    assert.equal(ctx.document.fee[0].amount, '75.00');
    ctx.document.payments.push({payment: '15'});
    component.watch['document.payments'].handler.call(ctx);
    assert.equal(ctx.document.fee[0].amount, '60.00');
    ctx.document.fee[0].amount = '33.00';
    ctx.document.payments[1].payment = '20';
    component.watch['document.payments'].handler.call(ctx);
    assert.equal(ctx.document.fee[0].amount, '33.00');
    ctx.document.payment_condition_id = '01'; ctx.changePaymentCondition();
    ctx.document.payment_condition_id = '02'; ctx.changePaymentCondition();
    assert.equal(ctx.document.payments.length, 2);
    assert.equal(ctx.document.fee[0].amount, '33.00');
    ctx.document.payments = []; ctx.document.fee = [];
    ctx.changePaymentCondition();
    assert.equal(ctx.document.payments.length, 0);
    assert.equal(ctx.document.fee[0].amount, '100.00');
    assert.doesNotMatch(parsed.template.content, /v-show="document.payment_condition_id != '02'"/);
});
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
test('sale note conversion sends credit receipts and keeps installments out of a cash invoice', async () => {
    const {ctx, requests} = conversion('VES', 40);
    ctx.document.payment_condition_id = '02';
    ctx.document.fee = [{amount: '75'}];
    ctx.document.payments = [{id: 4, payment: '25'}];
    await ctx.submit();
    let posts = requests.filter(request => request.method === 'post');
    assert.equal(posts[0].document.fee[0].amount, '75');
    assert.equal(posts[0].document.payments[0].payment, '25');
    ctx.document.payment_condition_id = '01';
    await ctx.submit();
    posts = requests.filter(request => request.method === 'post');
    assert.equal(posts[1].document.fee.length, 0);
    assert.equal(posts[1].document.payments[0].payment, '25');
    assert.equal(ctx.document.fee[0].amount, '75');
});
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
