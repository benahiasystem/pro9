const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const babel = require('@babel/core');
// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
const parsed = compiler.parseComponent(fs.readFileSync(require('node:path').join(__dirname, '../../resources/js/components/DataTableDocuments.vue'), 'utf8'));
const exported = {};
vm.runInNewContext(babel.transformSync(parsed.script.content, {configFile: false, babelrc: false, plugins: ['@babel/plugin-transform-modules-commonjs']}).code, {exports: exported, require: () => ({mapActions: () => ({}), mapState: () => ({})})});
const methods = exported.default.methods;
test('invalid control shows error and releases loading without discarding filters', async () => {
    let message;
    const ctx = {resource: 'documents', loading_submit: true, search: {control_number: 'invalid'}, records: [{id: 1}], getQueryParameters: () => 'control_number=invalid',
        $message: {error: text => {message = text;}}, $http: {get: async () => {throw {response: {data: {message: {control_number: ['Control inválido']}}}};}}};
    assert.equal(await methods.getRecords.call(ctx), false);
    assert.equal(ctx.loading_submit, false);
    assert.equal(ctx.search.control_number, 'invalid');
    assert.equal(message, 'Control inválido');
});
test('failed query keeps filter panel visible and does not fetch totals', async () => {
    const ctx = {getRecords: async () => false, getTotalRecords: () => {throw Error('Unexpected total request');}, auto_hide_filters: true, see_more: true};
    await methods.getRecordsByFilter.call(ctx);
    assert.equal(ctx.see_more, true);
});
test('reset clears control filter', () => {
    const ctx = {search: {control_number: '00-2'}};
    methods.initForm.call(ctx);
    assert.equal(ctx.search.control_number, null);
});
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
