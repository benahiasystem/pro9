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
    if (name.startsWith('.')) {
        return loadScript(fs.readFileSync(path.resolve(path.dirname(filename), name + '.js'), 'utf8'));
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
        assert.equal(post.document.exchange_rate_sale, 40);
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
    assert.equal(requests.find(request => request.method === 'post').document.exchange_rate_sale, 42.5);
});

test('invalid exchange rate blocks conversion before saving or marking the note converted', async () => {
    const {ctx, requests, errors} = conversion('VES', 0, 0);
    await ctx.submit();
    assert.equal(errors.length, 1);
    assert.match(errors[0], /tipo de cambio válido/);
    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, '/services/exchange/2026-10-01');
    assert.equal(ctx.documentNewId, null);
    assert.equal(ctx.loading_submit, false);
});
// ######## FIN MONEDA VENEZUELA HOTEL ########
