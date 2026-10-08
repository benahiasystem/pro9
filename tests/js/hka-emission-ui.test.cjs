const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const babel = require('@babel/core');
const parsed = compiler.parseComponent(fs.readFileSync('resources/js/views/tenant/documents/index.vue', 'utf8'));
const exportsObject = {};
vm.runInNewContext(babel.transformSync(parsed.script.content, {
    configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs']
}).code, {
    exports: exportsObject,
    require: name => name.includes('vuex') ? { mapActions: () => ({}), mapState: () => ({}) } : {},
});
const component = exportsObject.default;
function context(post) {
    const events = [], messages = [];
    return { hkaBusy: {}, events, messages, $http: { post },
        $set: (object, key, value) => { object[key] = value; },
        $eventHub: { $emit: event => events.push(event) },
        $message: { info: message => messages.push(message), error: message => messages.push(message) },
    };
}
test('HKA status starts visible beside the commercial status', () => {
    const columns = component.data().columns;
    assert.equal(columns.hka_status.visible, true);
    assert.ok(columns.state_type.order < columns.hka_status.order);
    assert.ok(columns.hka_status.order < columns.personalized.order);
    assert.deepEqual(compiler.compile(parsed.template.content).errors, []);
});
test('query updates the row, reloads the list and suppresses a concurrent click', async () => {
    let resolve, calls = 0;
    const ctx = context(url => {
        calls++;
        assert.equal(url, '/documents/13/query-hka');
        return new Promise(r => { resolve = r; });
    });
    const row = { id: 13, fiscal_emission: { status: 'uncertain' } };
    const first = component.methods.hkaAction.call(ctx, row, 'query');
    await component.methods.hkaAction.call(ctx, row, 'query');
    assert.equal(calls, 1);
    resolve({ data: { fiscal_emission: { status: 'confirmed', description: 'Confirmado', control_number: '00-00000002' } } });
    await first;
    assert.equal(row.fiscal_emission.control_number, '00-00000002');
    assert.deepEqual(ctx.events, ['reloadData']);
    assert.equal(ctx.hkaBusy[13], false);
});
test('a failed request preserves the row and explains that the sale is saved', async () => {
    const ctx = context(async () => { throw Error('network'); });
    const row = { id: 13, fiscal_emission: { status: 'uncertain' } };
    await component.methods.hkaAction.call(ctx, row, 'send');
    assert.equal(row.fiscal_emission.status, 'uncertain');
    assert.match(ctx.messages[0], /venta está guardada/);
    assert.equal(ctx.hkaBusy[13], false);
    assert.deepEqual(ctx.events, []);
});
