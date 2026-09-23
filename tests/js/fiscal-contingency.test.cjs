const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const babel = require('@babel/core');

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
const component = compiler.parseComponent(fs.readFileSync(require('node:path').join(__dirname, '../../resources/js/views/tenant/documents/partials/fiscal-status.vue'), 'utf8'));
const exportsObject = {};
vm.runInNewContext(babel.transformSync(component.script.content, {configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs']}).code, {exports: exportsObject});
const methods = exportsObject.default.methods;

for (const success of [true, false]) {
    test(`contingency ${success ? 'closes after success' : 'retains cause and profile for retry'}`, async () => {
        const ctx = {contingencyProfile: 5, contingencyReason: ' Interrupción ', contingencyOpen: true,
            act: async (action, body) => {
                assert.equal(action, 'contingency'); assert.equal(body.profile_id, 5); assert.equal(body.reason, 'Interrupción');
                return success;
            }};
        await methods.startContingency.call(ctx);
        assert.equal(ctx.contingencyOpen, !success);
        assert.equal(ctx.contingencyProfile, success ? null : 5);
        assert.equal(ctx.contingencyReason, success ? '' : ' Interrupción ');
    });
}

test('empty cause cannot reserve a physical control', async () => {
    await methods.startContingency.call({contingencyProfile: 5, contingencyReason: ' ', act: () => {throw Error('Unexpected request');}});
});

test('fiscal status template compiles including contingency form', () => {
    assert.deepEqual(compiler.compile(component.template.content).errors, []);
});

for (const body of [{errors: {fiscal: ['Debe conciliar la emisión']}}, {message: {fiscal: ['Debe conciliar la emisión']}}]) {
    test('contingency validation failure is shown as readable text', () => {
        const ctx = {};
        methods.failure.call(ctx, {response: {status: 422, data: body}});
        assert.equal(ctx.error, 'Debe conciliar la emisión');
    });
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########

for (const relative of ['components/DataTableDocuments.vue', 'views/tenant/documents/index.vue', 'views/tenant/dispatches/index.vue']) {
    test(`fiscal identifier template compiles: ${relative}`, () => {
        const parsed = compiler.parseComponent(fs.readFileSync(require('node:path').join(__dirname, '../../resources/js', relative), 'utf8'));
        assert.deepEqual(compiler.compile(parsed.template.content).errors, []);
    });
}
