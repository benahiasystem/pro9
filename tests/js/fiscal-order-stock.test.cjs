const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const babel = require('@babel/core');

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
const component = compiler.parseComponent(fs.readFileSync(require('node:path').join(__dirname, '../../resources/js/views/tenant/orders/index.vue'), 'utf8'));
const code = babel.transformSync(component.script.content, {configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs']}).code;
const exportsObject = {};
vm.runInNewContext(code, {exports: exportsObject, require: name => name.includes('vuex') ? {mapState: () => ({}), mapActions: () => ({})} : {}, _: require('lodash')});
const save = exportsObject.default.methods.save;

function context(post) {
    const ctx = {record: {items: [{id: 4, cantidad: 2}], stock_discounted: false}, totalProduct: [4], form: {4: 12}, statusField: 'status_order_id', closed: false, messages: [], $http: {post}, $eventHub: {$emit() {}}, close() {this.closed = true;}};
    ctx.$message = {success: m => ctx.messages.push(m), warning: m => ctx.messages.push(m), error: m => ctx.messages.push(m)};
    return ctx;
}

test('order stock rejection keeps form and does not mark a reservation', async () => {
    const ctx = context(async (url, payload) => {
        assert.equal(url, '/statusOrder/update');
        assert.equal(JSON.stringify(payload.discount), JSON.stringify([{id: 12, cantidad: 2}]));
        return {data: {type: 'warning', message: 'Stock insuficiente'}};
    });
    await save.call(ctx);
    assert.equal(ctx.closed, false);
    assert.equal(ctx.record.stock_discounted, false);
    assert.deepEqual(ctx.messages, ['Stock insuficiente']);
});

test('confirmed reservation marks success and closes the form', async () => {
    const ctx = context(async () => ({data: {type: 'success', message: 'Reservado'}}));
    await save.call(ctx);
    assert.equal(ctx.closed, true);
    assert.equal(ctx.record.stock_discounted, true);
});

test('network error keeps the order available for retry', async () => {
    const ctx = context(async () => {throw new Error('network');});
    await save.call(ctx);
    assert.equal(ctx.closed, false);
    assert.equal(ctx.record.stock_discounted, false);
    assert.equal(ctx.messages.length, 1);
});
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
