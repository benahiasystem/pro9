const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const compiler = require('vue-template-compiler');
const component = compiler.parseComponent(fs.readFileSync('modules/Sale/Resources/assets/js/views/technical-services/partials/options.vue', 'utf8'));
const context = {module: {exports: {}}, DocumentOptions: {}, SaleNoteOptions: {}, SeriesForm: {}, functions: {}, fiscalSale: {},
    mapActions: () => ({}), mapState: () => ({})};
vm.runInNewContext(component.script.content.replace(/^import .*$/gm, '').replace('export default', 'module.exports ='), context);
const methods = context.module.exports.methods;

test('technical service template compiles with fiscal profile summary', () => {
    assert.deepEqual(compiler.compile(component.template.content).errors, []);
});
test('invoice does not inherit a commercial series', () => {
    const ctx = {form: {document_type_id: '01', series: 'NV01'}, series: [{number: 'NV01'}]};
    methods.filterSeries.call(ctx);
    assert.equal(ctx.form.series, null);
    assert.equal(ctx.series.length, 0);
});
test('missing fiscal profile prevents technical service submission', async () => {
    let checked = false;
    await methods.submit.call({form: {document_type_id: '01'}, prepareFiscalSale: () => {checked = true; return false;},
        validatePaymentDestination: () => {throw Error('Unexpected submission');}});
    assert.equal(checked, true);
});
