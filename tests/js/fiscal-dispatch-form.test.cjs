const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const babel = require('@babel/core');

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
const source = fs.readFileSync(path.join(__dirname, '../../resources/js/views/tenant/dispatches/create.vue'), 'utf8');
const component = compiler.parseComponent(source);
const code = babel.transformSync(component.script.content, {
    configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs']
}).code;
const moduleExports = {};
vm.runInNewContext(code, {
    exports: moduleExports,
    require: name => name.includes('vuex') ? {mapState: () => ({}), mapActions: () => ({})} : {},
    _: require('lodash'),
});
const submit = moduleExports.default.methods.submit;

function context(post) {
    const ctx = {
        loading_submit: false, config: {}, $refs: {}, resource: 'dispatches',
        form: {document_type_id: '09', customer_id: 42, total_weight: 2, origin_address_id: 1, delivery_address_id: 2},
        origin_addresses: [{id: 1}], delivery_addresses: [{id: 2}], errors: {}, messages: [], resets: 0,
        verifyQuantityItems: async () => ({validate: true}),
        prepareFiscalSale() { this.form.operation_key ||= 'same-operation'; return true; },
        initForm() { this.form = {document_type_id: '09'}; },
        setDefaultCustomer() { this.resets++; this.form.customer_id = 1; },
        $http: {post},
    };
    ctx.$message = {error: message => ctx.messages.push(message)};
    return ctx;
}

test('failed dispatch keeps customer and operation for a retry; success alone resets the form', async () => {
    const payloads = [];
    const ctx = context(async (url, payload) => {
        assert.equal(url, '/dispatches');
        payloads.push(JSON.stringify(payload));
        if (payloads.length === 1) throw {response: {status: 422, data: {message: 'No disponible', errors: {items: ['Revisar']}}}};
        return {data: {success: true, data: {id: 9}}};
    });
    await submit.call(ctx);
    assert.equal(ctx.form.customer_id, 42);
    assert.equal(ctx.form.operation_key, 'same-operation');
    assert.equal(ctx.resets, 0);
    assert.deepEqual(ctx.errors.items, ['Revisar']);
    assert.equal(ctx.loading_submit, false);
    await submit.call(ctx);
    assert.equal(payloads[1], payloads[0]);
    assert.equal(ctx.recordId, 9);
    assert.equal(ctx.showDialogFinish, true);
    assert.equal(ctx.resets, 1);
    assert.equal(ctx.form.operation_key, undefined);
});

test('concurrent clicks during asynchronous validation submit only one request', async () => {
    let finish;
    let requests = 0;
    const ctx = context(() => { requests++; return new Promise(resolve => {finish = resolve;}); });
    const first = submit.call(ctx);
    const second = submit.call(ctx);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(requests, 1);
    finish({data: {success: true, data: {id: 10}}});
    await Promise.all([first, second]);
    assert.equal(ctx.loading_submit, false);
});

test('network errors preserve the form and release the submit button', async () => {
    const ctx = context(async () => {throw new Error('Network error');});
    await submit.call(ctx);
    assert.equal(ctx.form.customer_id, 42);
    assert.equal(ctx.form.operation_key, 'same-operation');
    assert.equal(ctx.loading_submit, false);
    assert.equal(ctx.resets, 0);
    assert.match(ctx.messages[0], /reintentar/);
});
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
